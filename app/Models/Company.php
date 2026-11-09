<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function scopePublishable(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE')->where('verification_status', 'VERIFIED')
            ->whereHas('owner', fn (Builder $owner) => $owner->where('status', 'ACTIVE'));
    }

    public function canPublish(): bool
    {
        return $this->verification_status === 'VERIFIED' && $this->status === 'ACTIVE' && $this->owner->status === 'ACTIVE';
    }
}
