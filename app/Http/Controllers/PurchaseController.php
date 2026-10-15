<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use App\Services\MidtransPayments;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, Package $package, MidtransPayments $payments): RedirectResponse
    {
        abort_unless($package->active && $request->user()->company?->canPublish(), 403, 'Perusahaan harus aktif dan terverifikasi.');
        if (! config('services.midtrans.server_key') || ! config('services.midtrans.merchant_id')) {
            return back()->withErrors(['payment' => 'Pembayaran Midtrans belum diaktifkan.']);
        }
        $purchase = DB::transaction(function () use ($request, $package) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            return Purchase::firstOrCreate(['user_id' => $request->user()->id, 'package_id' => $package->id, 'status' => 'PENDING', 'provider' => 'midtrans'], ['reference' => (string) Str::uuid(), 'package_name' => $package->name, 'amount' => $package->price, 'credits' => $package->credits]);
        });
        try {
            if (! $purchase->wasRecentlyCreated) {
                $payments->reconcile($purchase);
            }
            $payments->invoice($purchase);
        } catch (\Throwable $exception) {
            Log::warning('Midtrans request failed', ['purchase_id' => $purchase->id, 'type' => $exception::class]);

            return to_route('purchases.show', $purchase)->withErrors(['payment' => 'Status invoice belum dapat dipastikan. Jangan membayar ulang; periksa status atau hubungi admin.']);
        }

        return to_route('purchases.show', $purchase);
    }

    public function show(Request $request, Purchase $purchase): View
    {
        abort_unless($purchase->user_id === $request->user()->id || $request->user()->can('admin'), 403);

        return view('purchases.show', compact('purchase'));
    }

    public function refresh(Request $request, Purchase $purchase, MidtransPayments $payments): RedirectResponse
    {
        abort_unless($purchase->user_id === $request->user()->id || $request->user()->can('admin'), 403);
        abort_unless($purchase->provider === 'midtrans', 422, 'Transaksi provider lama harus diperiksa oleh admin.');
        try {
            $payments->reconcile($purchase);
            $payments->invoice($purchase);
        } catch (\Throwable $exception) {
            Log::warning('Midtrans request failed', ['purchase_id' => $purchase->id, 'type' => $exception::class]);

            return back()->withErrors(['payment' => 'Provider belum dapat dihubungi. Status tidak diubah.']);
        }

        return back()->with('success', 'Pemeriksaan status pembayaran selesai.');
    }

    public function webhook(Request $request, MidtransPayments $payments): JsonResponse
    {
        $data = $request->validate(['order_id' => 'required|uuid', 'status_code' => 'required|string|size:3', 'gross_amount' => ['required', 'string', 'max:30', 'regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/'], 'signature_key' => 'required|string|size:128']);
        $serverKey = config('services.midtrans.server_key');
        abort_unless(is_string($serverKey) && $serverKey !== '' && hash_equals(hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$serverKey), $data['signature_key']), 401);
        $purchase = Purchase::where('provider', 'midtrans')->where('reference', $data['order_id'])->firstOrFail();
        // Query Midtrans so delayed notifications cannot overwrite newer payment state.
        try {
            if (! $payments->reconcile($purchase)) {
                return response()->json(['received' => false], 503);
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Midtrans verification temporarily unavailable', ['purchase_id' => $purchase->id, 'type' => $exception::class]);

            return response()->json(['received' => false], 503);
        }

        return response()->json(['received' => true]);
    }
}
