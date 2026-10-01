<?php

namespace App\Console\Commands;

use App\Jobs\SendJobEmail;
use App\Jobs\SendPurchaseReceipt;
use App\Models\EmailDelivery;
use App\Models\JobPosting;
use App\Models\Purchase;
use App\Models\Subscription;
use Illuminate\Console\Command;

class DistributeJobs extends Command
{
    protected $signature = 'jobs:distribute';

    protected $description = 'Prepare resumable matching subscriber deliveries';

    public function handle(): int
    {
        JobPosting::live()->whereNull('distributed_at')->orderBy('id')->limit(10)->get()->each(function ($job) {
            $subscribers = Subscription::with('user')->where('id', '>', $job->distribution_cursor)->where('active', true)->where('suppressed', false)->where('subscribed_at', '<=', $job->published_at)->orderBy('id')->limit(config('sharekerja.email_batch_size'))->get();
            foreach ($subscribers as $subscription) {
                if ($subscription->user->status === 'ACTIVE' && $subscription->matches($job)) {
                    EmailDelivery::firstOrCreate(['job_posting_id' => $job->id, 'subscription_id' => $subscription->id]);
                }
                $job->update(['distribution_cursor' => $subscription->id]);
            }
            if ($subscribers->count() < config('sharekerja.email_batch_size')) {
                $job->update(['distributed_at' => now()]);
            }
        });
        if (config('sharekerja.email_per_minute') > 0 && config('sharekerja.email_per_hour') > 0) {
            Purchase::where('status', 'PAID')->where('receipt_status', 'PENDING')->where(fn ($query) => $query->whereNull('receipt_queued_at')->orWhere('receipt_queued_at', '<', now()->subHour()))->limit(50)->get()->each(function ($purchase) {
                $purchase->update(['receipt_queued_at' => now()]);
                try {
                    SendPurchaseReceipt::dispatch($purchase->id)->onQueue('default');
                } catch (\Throwable $exception) {
                    $purchase->update(['receipt_queued_at' => null]);
                    report($exception);
                }
            });
            EmailDelivery::where('status', 'PENDING')->where(fn ($query) => $query->whereNull('queued_at')->orWhere('queued_at', '<', now()->subHour()))->orderBy('updated_at')->limit(config('sharekerja.email_batch_size'))->get()->each(function ($delivery) {
                $delivery->update(['queued_at' => now()]);
                try {
                    SendJobEmail::dispatch($delivery->id)->onQueue('emails');
                } catch (\Throwable $exception) {
                    $delivery->update(['queued_at' => null]);
                    report($exception);
                }
            });
        }

        return self::SUCCESS;
    }
}
