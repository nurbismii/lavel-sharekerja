<?php

namespace App\Console\Commands;

use App\Models\EmailDelivery;
use App\Models\JobPosting;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MaintainPlatform extends Command
{
    protected $signature = 'platform:maintain';

    protected $description = 'Close expired postings, release stale application reservations, and record scheduler health';

    public function handle(): int
    {
        JobPosting::where('status', JobPosting::PUBLISHED)->where(fn ($query) => $query->where('deadline', '<=', now())->orWhere('expires_at', '<=', now()))->update(['status' => JobPosting::EXPIRED]);
        JobPosting::where('status', JobPosting::PENDING)->where('deadline', '<=', now())->with('company')->chunkById(100, function ($jobs) {
            foreach ($jobs as $job) {
                DB::transaction(function () use ($job) {
                    $owner = User::whereKey($job->company->owner_user_id)->lockForUpdate()->firstOrFail();
                    $job = JobPosting::whereKey($job->id)->lockForUpdate()->firstOrFail();
                    if ($job->status !== JobPosting::PENDING || $job->deadline?->isFuture()) {
                        return;
                    }
                    if ($job->credit_reserved) {
                        $owner->reserved_credits--;
                        $owner->save();
                    }
                    $job->update(['credit_reserved' => false, 'status' => JobPosting::DRAFT, 'review_reason' => 'Deadline telah lewat sebelum review. Perbarui deadline dan ajukan kembali.']);
                    DB::table('audit_logs')->insert(['action' => 'job.pending-expired', 'subject' => 'job:'.$job->id, 'reason' => 'Reservasi dilepas setelah deadline', 'created_at' => now()]);
                }, 3);
            }
        });
        EmailDelivery::where('status', 'SENDING')->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'UNKNOWN']);
        Purchase::where('receipt_status', 'SENDING')->where('updated_at', '<', now()->subMinutes(5))->update(['receipt_status' => 'UNKNOWN']);
        Cache::put('platform:heartbeat', now()->toIso8601String(), now()->addDays(2));

        return self::SUCCESS;
    }
}
