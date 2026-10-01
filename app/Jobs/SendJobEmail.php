<?php

namespace App\Jobs;

use App\Models\EmailDelivery;
use App\Models\JobPosting;
use App\Models\Subscription;
use App\Services\EmailQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendJobEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(EmailQuota $quota): void
    {
        $lock = Cache::lock('email-delivery:'.$this->deliveryId, 60);
        if (! $lock->get()) {
            return;
        }
        try {
            $delivery = EmailDelivery::find($this->deliveryId);
            if (! $delivery || $delivery->status !== 'PENDING') {
                return;
            }
            $job = JobPosting::with('company')->find($delivery->job_posting_id);
            $subscription = Subscription::with('user')->find($delivery->subscription_id);
            if (! $job?->isLive() || ! $subscription?->active || $subscription->suppressed || $subscription->user->status !== 'ACTIVE' || ! $subscription->matches($job) || $subscription->subscribed_at->gt($job->published_at)) {
                $delivery->update(['status' => 'SKIPPED']);

                return;
            }
            if (! $quota->acquire()) {
                $delivery->update(['queued_at' => null]);

                return;
            }
            $delivery->update(['status' => 'SENDING']);
            try {
                Mail::send('emails.job', compact('job', 'subscription'), fn ($message) => $message->to($subscription->user->email)->subject('Lowongan baru: '.$job->title));
                $delivery->update(['status' => 'ACCEPTED', 'accepted_at' => now()]);
            } catch (\Throwable $exception) {
                $delivery->update(['status' => 'UNKNOWN']);
                Log::warning('Job email outcome unknown', ['delivery_id' => $delivery->id, 'type' => $exception::class]);
            }
        } finally {
            $lock->release();
        }
    }
}
