<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditEntry;
use App\Models\EmailDelivery;
use App\Models\JobPosting;
use App\Models\JobReport;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', ['subscribers' => Subscription::with('user')->latest()->paginate(15, ['*'], 'subscribers_page'), 'received' => Purchase::where('status', 'PAID')->sum('amount'), 'refunded' => Purchase::where('refund_status', 'SUCCEEDED')->sum('amount'), 'heartbeat' => Cache::get('platform:heartbeat'), 'backlog' => DB::table('jobs')->count(), 'companies' => Company::with('owner')->where('verification_status', 'PENDING')->paginate(10, ['*'], 'companies_page'), 'jobs' => JobPosting::with('company')->whereIn('status', [JobPosting::PENDING, JobPosting::PUBLISHED, JobPosting::SUSPENDED])->latest()->paginate(15, ['*'], 'jobs_page'), 'reports' => JobReport::whereIn('status', ['OPEN', 'IN_REVIEW'])->latest()->paginate(10, ['*'], 'reports_page'), 'packages' => Package::orderBy('price')->get(), 'purchases' => Purchase::with('user')->latest()->paginate(15, ['*'], 'purchases_page'), 'users' => User::with('roles')->latest()->paginate(15, ['*'], 'users_page'), 'logs' => DB::table('audit_logs')->latest('id')->paginate(20, ['*'], 'logs_page'), 'mailStats' => EmailDelivery::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function verify(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:VERIFIED,REJECTED', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($request, $company, $data) {
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            abort_unless($company->verification_status === 'PENDING', 409);
            $company->update(['verification_status' => $data['status'], 'review_reason' => $data['reason']]);
            $this->audit($request, 'company.verify', 'company:'.$company->id, $data['reason']);
        });

        return back()->with('success', 'Verifikasi diperbarui.');
    }

    public function job(Request $request, JobPosting $job, PublicationService $service): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:approve,reject,suspend,restore', 'reason' => 'nullable|required_unless:action,approve|string|max:1000']);
        $service->transition($job, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Moderasi lowongan selesai.');
    }

    public function package(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'price' => 'required|integer|min:10000|max:100000000', 'credits' => 'required|integer|min:1|max:1000', 'description' => 'nullable|string|max:500', 'active' => 'required|boolean']);
        Package::create($data);
        $this->audit($request, 'package.create', 'package', $data['name']);

        return back()->with('success', 'Paket berhasil dibuat.');
    }

    public function togglePackage(Request $request, Package $package): RedirectResponse
    {
        $package->update(['active' => ! $package->active]);
        $this->audit($request, 'package.toggle', 'package:'.$package->id, (string) $package->active);

        return back()->with('success', 'Status paket diperbarui.');
    }

    public function report(Request $request, JobReport $report): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:IN_REVIEW,RESOLVED,DISMISSED', 'reason' => 'required|string|max:1000']);
        $report->update(['status' => $data['status']]);
        $this->audit($request, 'report.review', 'report:'.$report->id, $data['reason']);

        return back()->with('success', 'Laporan diperbarui.');
    }

    public function user(Request $request, User $user, PublicationService $service): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:ACTIVE,SUSPENDED', 'reason' => 'required|string|max:1000']);
        abort_if($user->id === $request->user()->id || $user->hasRole('SUPER_ADMIN'), 422, 'Perubahan akun admin menggunakan prosedur administratif terpisah.');
        DB::transaction(function () use ($request, $user, $service, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($data['status'] === 'SUSPENDED' && $user->company) {
                foreach ($user->company->jobs()->whereIn('status', [JobPosting::PENDING, JobPosting::PUBLISHED])->get() as $job) {
                    $service->transition($job, 'suspend', $request->user(), $data['reason']);
                }
            }
            $user->status = $data['status'];
            $user->save();
            $this->audit($request, 'user.status', 'user:'.$user->id, $data['reason']);
        });

        return back()->with('success', 'Status akun diperbarui. Lowongan perlu review sebelum terbit kembali.');
    }

    public function refund(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:hold,success,failed', 'reason' => 'required|string|max:1000', 'reference' => 'nullable|required_if:action,success|string|max:150']);
        DB::transaction(function () use ($request, $purchase, $data) {
            $user = User::whereKey($purchase->user_id)->lockForUpdate()->firstOrFail();
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            abort_unless($purchase->status === 'PAID', 422);
            if ($data['action'] === 'hold') {
                abort_unless(in_array($purchase->refund_status, ['NONE', 'FAILED'], true), 422);
                $grant = CreditEntry::where('operation_key', 'purchase:'.$purchase->id)->firstOrFail();
                abort_if(CreditEntry::where('user_id', $user->id)->where('type', 'PURCHASE')->where('id', '>', $grant->id)->exists() || CreditEntry::where('user_id', $user->id)->where('id', '>', $grant->id)->where('amount', '<', 0)->exists() || $user->availableCredits() < $purchase->credits, 422, 'Pembelian tidak memenuhi refund penuh biasa. Tangani sebagai sengketa.');
                $user->reserved_credits += $purchase->credits;
                $purchase->refund_status = 'PENDING';
            } else {
                abort_unless($purchase->refund_status === 'PENDING', 422);
                $user->reserved_credits -= $purchase->credits;
                if ($data['action'] === 'success') {
                    $user->credits -= $purchase->credits;
                    CreditEntry::create(['user_id' => $user->id, 'amount' => -$purchase->credits, 'type' => 'REFUND', 'operation_key' => 'refund:'.$purchase->id, 'reason' => $data['reason'].' / '.$data['reference']]);
                    $purchase->refund_status = 'SUCCEEDED';
                    $purchase->refund_reference = $data['reference'];
                } else {
                    $purchase->refund_status = 'FAILED';
                }
            }
            $user->save();
            $purchase->save();
            $this->audit($request, 'refund.'.$data['action'], 'purchase:'.$purchase->id, $data['reason']);
        });

        return back()->with('success', 'Pencatatan refund diperbarui. Pengembalian dana dilakukan dan diverifikasi melalui Midtrans.');
    }

    private function audit(Request $request, string $action, string $subject, string $reason): void
    {
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $action, 'subject' => $subject, 'reason' => $reason, 'created_at' => now()]);
    }

    public function subscriber(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['suppressed' => 'required|boolean', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($request, $subscription, $data) {
            $subscription->update(['suppressed' => (bool) $data['suppressed']]);
            $this->audit($request, 'subscription.suppression', 'subscription:'.$subscription->id, $data['reason']);
        });

        return back()->with('success', 'Status suppression diperbarui. Persetujuan langganan pengguna tidak diubah.');
    }
}
