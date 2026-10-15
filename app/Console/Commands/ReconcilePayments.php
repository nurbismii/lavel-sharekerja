<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use App\Services\MidtransPayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Reconcile pending Midtrans invoices without issuing duplicate credits';

    public function handle(MidtransPayments $payments): int
    {
        if (! config('services.midtrans.server_key') || ! config('services.midtrans.merchant_id')) {
            return self::SUCCESS;
        }
        Purchase::where('provider', 'midtrans')->whereIn('status', ['PENDING', 'EXPIRED', 'FAILED'])->where('created_at', '>', now()->subDays(30))->orderBy('updated_at')->limit(25)->get()->each(function ($purchase) use ($payments) {
            try {
                $payments->reconcile($purchase);
                $purchase->touch();
            } catch (\Throwable $exception) {
                Log::warning('Midtrans request failed', ['purchase_id' => $purchase->id, 'type' => $exception::class]);
            }
        });

        return self::SUCCESS;
    }
}
