<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use App\Services\XenditPayments;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        return view('purchases.packages', ['packages' => Package::where('active', true)->orderBy('price')->get()]);
    }

    public function store(Request $request, Package $package, XenditPayments $payments): RedirectResponse
    {
        abort_unless($package->active && $request->user()->company?->canPublish(), 403, 'Perusahaan harus aktif dan terverifikasi.');
        if (! config('services.xendit.secret_key')) {
            return back()->withErrors(['payment' => 'Pembayaran Xendit belum diaktifkan.']);
        }
        $purchase = DB::transaction(function () use ($request, $package) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            return Purchase::firstOrCreate(['user_id' => $request->user()->id, 'package_id' => $package->id, 'status' => 'PENDING'], ['reference' => (string) Str::uuid(), 'package_name' => $package->name, 'amount' => $package->price, 'credits' => $package->credits]);
        });
        $lock = Cache::lock('invoice:'.$purchase->id, 30);
        if ($lock->get()) {
            try {
                if ($purchase->wasRecentlyCreated) {
                    $payments->invoice($purchase);
                } else {
                    $payments->reconcile($purchase);
                }
            } catch (\Throwable $exception) {
                Log::warning('Xendit request failed', ['purchase_id' => $purchase->id, 'type' => $exception::class]);

                return to_route('purchases.show', $purchase)->withErrors(['payment' => 'Status invoice belum dapat dipastikan. Jangan membayar ulang; periksa status atau hubungi admin.']);
            } finally {
                $lock->release();
            }
        }

        return to_route('purchases.show', $purchase);
    }

    public function show(Request $request, Purchase $purchase): View
    {
        abort_unless($purchase->user_id === $request->user()->id || $request->user()->can('admin'), 403);

        return view('purchases.show', compact('purchase'));
    }

    public function refresh(Request $request, Purchase $purchase, XenditPayments $payments): RedirectResponse
    {
        abort_unless($purchase->user_id === $request->user()->id || $request->user()->can('admin'), 403);
        try {
            $payments->reconcile($purchase);
        } catch (\Throwable $exception) {
            Log::warning('Xendit request failed', ['purchase_id' => $purchase->id, 'type' => $exception::class]);

            return back()->withErrors(['payment' => 'Provider belum dapat dihubungi. Status tidak diubah.']);
        }

        return back()->with('success', 'Pemeriksaan status pembayaran selesai.');
    }

    public function webhook(Request $request, XenditPayments $payments): JsonResponse
    {
        $token = config('services.xendit.webhook_token');
        abort_unless(is_string($token) && $token !== '' && hash_equals($token, (string) $request->header('x-callback-token')), 401);
        $data = $request->validate(['external_id' => 'required|uuid', 'id' => 'required|string|max:100']);
        $purchase = Purchase::where('reference', $data['external_id'])->firstOrFail();
        abort_unless(! $purchase->provider_id || $purchase->provider_id === $data['id'], 422);
        // Fetch authenticated provider state instead of granting credit from a browser or unverified payload.
        try {
            $payments->reconcile($purchase);
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Xendit verification temporarily unavailable', ['purchase_id' => $purchase->id, 'type' => $exception::class]);

            return response()->json(['received' => false], 503);
        }

        return response()->json(['received' => true]);
    }
}
