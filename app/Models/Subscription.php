<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['preferences' => 'array', 'active' => 'boolean', 'suppressed' => 'boolean', 'subscribed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(JobPosting $job): bool
    {
        foreach (($this->preferences ?? []) as $field => $values) {
            if ($values && ! in_array($job->{$field}, $values, true)) {
                return false;
            }
        }

        return true;
    }
}
