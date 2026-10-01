<?php

namespace App\Console\Commands;

use App\Models\CreditEntry;
use App\Models\JobPosting;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Console\Command;

class AuditCredits extends Command
{
    protected $signature = 'credits:audit';

    protected $description = 'Read-only reconciliation of credit balances against ledger and active holds';

    public function handle(): int
    {
        $mismatches = 0;
        User::where(fn ($query) => $query->where('credits', '!=', 0)->orWhere('reserved_credits', '!=', 0)->orWhereIn('id', CreditEntry::select('user_id')))->chunkById(100, function ($users) use (&$mismatches) {
            foreach ($users as $user) {
                $balance = (int) CreditEntry::where('user_id', $user->id)->sum('amount');
                $reserved = JobPosting::whereHas('company', fn ($query) => $query->where('owner_user_id', $user->id))->where('credit_reserved', true)->count() + (int) Purchase::where('user_id', $user->id)->where('refund_status', 'PENDING')->sum('credits');
                if ($balance !== $user->credits || $reserved !== $user->reserved_credits || $user->availableCredits() < 0) {
                    $this->error('Mismatch on user #'.$user->id);
                    $mismatches++;
                }
            }
        });
        if (! $mismatches) {
            $this->info('Balances and holds match the ledger.');
        }

        return $mismatches ? self::FAILURE : self::SUCCESS;
    }
}
