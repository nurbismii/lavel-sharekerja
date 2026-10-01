<?php

namespace App\Jobs;

use App\Models\Purchase;
use App\Services\EmailQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPurchaseReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $purchaseId) {}

    public function handle(EmailQuota $quota): void
    {
        $lock = Cache::lock('purchase-receipt:'.$this->purchaseId, 60);
        if (! $lock->get()) {
            return;
        }
        try {
            $purchase = Purchase::with('user')->find($this->purchaseId);
            if (! $purchase || $purchase->status !== 'PAID' || $purchase->receipt_status !== 'PENDING') {
                return;
            }
            if (! $quota->acquire()) {
                $purchase->update(['receipt_queued_at' => null]);

                return;
            }
            $purchase->update(['receipt_status' => 'SENDING']);
            try {
                Mail::send('emails.receipt', compact('purchase'), fn ($message) => $message->to($purchase->user->email)->subject('Pembayaran Sharekerjaid terverifikasi'));
                $purchase->update(['receipt_status' => 'ACCEPTED']);
            } catch (\Throwable $exception) {
                $purchase->update(['receipt_status' => 'UNKNOWN']);
                Log::warning('Receipt delivery outcome unknown', ['purchase_id' => $purchase->id, 'type' => $exception::class]);
            }
        } finally {
            $lock->release();
        }
    }
}
