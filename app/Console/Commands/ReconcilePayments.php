<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use App\Services\XenditPayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Reconcile pending Xendit invoices without issuing duplicate credits';

    public function handle(XenditPayments $payments): int
    {
        if (! config('services.xendit.secret_key')) {
            return self::SUCCESS;
        }
        Purchase::whereIn('status', ['PENDING', 'EXPIRED'])->where('created_at', '>', now()->subDays(30))->orderBy('updated_at')->limit(25)->get()->each(function ($purchase) use ($payments) {
            try {
                $payments->reconcile($purchase);
                $purchase->touch();
            } catch (\Throwable $exception) {
                Log::warning('Xendit request failed', ['purchase_id' => $purchase->id, 'type' => $exception::class]);
            }
        });

        return self::SUCCESS;
    }
}
