<?php

namespace App\Services;

use App\Http\Requests\JobPostingRequest;
use App\Models\CreditEntry;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublicationService
{
    public function transition(JobPosting $job, string $action, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($job, $action, $actor, $reason) {
            $owner = User::whereKey($job->company->owner_user_id)->lockForUpdate()->firstOrFail();
            $job = JobPosting::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $from = $job->status;
            $adminActions = ['approve', 'reject', 'suspend', 'restore'];
            abort_unless(in_array($action, $adminActions, true) ? $actor->can('admin') : $actor->can('manage-job', $job), 403);
            if (in_array($action, ['reject', 'suspend', 'restore'], true) && ! $reason) {
                throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi.']);
            }
            if ($action === 'submit') {
                if ($job->status === JobPosting::PENDING) {
                    return;
                }
                $this->require(in_array($job->status, [JobPosting::DRAFT, JobPosting::REJECTED], true), 'Lowongan tidak dapat diajukan.');
                Validator::make($job->getAttributes(), JobPostingRequest::publicationRules())->validate();
                $this->require($job->company->canPublish() && $job->deadline->isFuture(), 'Verifikasi perusahaan dan deadline harus valid.');
                $this->require($owner->availableCredits() >= 1, 'Kredit belum cukup. Beli paket terlebih dahulu.');
                $owner->reserved_credits++;
                $job->credit_reserved = true;
                $job->status = JobPosting::PENDING;
            } elseif ($action === 'approve') {
                if ($job->status === JobPosting::PUBLISHED) {
                    return;
                }
                $this->require($job->status === JobPosting::PENDING && $job->credit_reserved, 'Pengajuan tidak memiliki reservasi aktif.');
                Validator::make($job->getAttributes(), JobPostingRequest::publicationRules())->validate();
                $this->require($job->company->canPublish() && $job->deadline->isFuture(), 'Perusahaan atau deadline tidak valid.');
                $this->require($owner->credits >= 1 && $owner->reserved_credits >= 1, 'Saldo perlu direkonsiliasi.');
                $owner->credits--;
                $owner->reserved_credits--;
                $job->credit_reserved = false;
                CreditEntry::create(['user_id' => $owner->id, 'amount' => -1, 'type' => 'PUBLISH', 'operation_key' => 'publish:'.$job->id, 'reason' => 'Publikasi '.$job->title]);
                $job->status = JobPosting::PUBLISHED;
                $job->published_at = now();
                $job->expires_at = now()->addDays(config('sharekerja.publication_days'));
            } elseif ($action === 'withdraw' || $action === 'reject') {
                $this->require($job->status === JobPosting::PENDING, 'Pengajuan sudah diproses.');
                $owner->reserved_credits -= $job->credit_reserved ? 1 : 0;
                $job->credit_reserved = false;
                $job->status = $action === 'withdraw' ? JobPosting::DRAFT : JobPosting::REJECTED;
            } elseif ($action === 'close') {
                $this->require($job->status === JobPosting::PUBLISHED, 'Lowongan tidak sedang terbit.');
                $job->status = JobPosting::CLOSED;
            } elseif ($action === 'suspend') {
                $this->require(in_array($job->status, [JobPosting::PENDING, JobPosting::PUBLISHED], true), 'Lowongan tidak dapat ditangguhkan.');
                $owner->reserved_credits -= $job->credit_reserved ? 1 : 0;
                $job->credit_reserved = false;
                $job->status = JobPosting::SUSPENDED;
            } elseif ($action === 'restore') {
                $this->require($job->status === JobPosting::SUSPENDED, 'Lowongan tidak sedang ditangguhkan.');
                $this->require($job->company->canPublish(), 'Perusahaan belum aktif dan terverifikasi.');
                $job->status = ! $job->published_at ? JobPosting::DRAFT : (($job->deadline->isFuture() && $job->expires_at->isFuture()) ? JobPosting::PUBLISHED : JobPosting::EXPIRED);
            } else {
                abort(422);
            }
            $job->review_reason = $reason;
            $owner->save();
            $job->save();
            DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'subject' => 'job:'.$job->id, 'reason' => $from.' → '.$job->status.($reason ? ': '.$reason : ''), 'created_at' => now()]);
        }, 3);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['job' => $message]);
        }
    }
}
