<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    use HasFactory;

    public const DRAFT = 'DRAFT';

    public const PENDING = 'WAITING_APPROVAL';

    public const PUBLISHED = 'PUBLISHED';

    public const REJECTED = 'REJECTED';

    public const CLOSED = 'CLOSED';

    public const EXPIRED = 'EXPIRED';

    public const SUSPENDED = 'SUSPENDED';

    public const EXTERNAL = 'EXTERNAL';

    public const INTERNAL = 'INTERNAL';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return ['deadline' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'distributed_at' => 'datetime', 'salary_visible' => 'boolean', 'credit_reserved' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED)->where('deadline', '>', now())->where('expires_at', '>', now())->whereHas('company', fn (Builder $q) => $q->where('status', 'ACTIVE')->where('verification_status', 'VERIFIED')->whereHas('owner', fn (Builder $u) => $u->where('status', 'ACTIVE')));
    }

    public function isLive(): bool
    {
        return self::query()->live()->whereKey($this->id)->exists();
    }

    public function structuredData(): array
    {
        if (! $this->isLive()) {
            return [];
        }
        $description = collect(['description', 'responsibilities', 'qualifications', 'benefits', 'skills', 'education', 'experience'])->map(fn ($field) => $this->{$field} ? '<p>'.nl2br(e($this->{$field})).'</p>' : '')->implode('');
        $data = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $this->title, 'description' => $description, 'datePosted' => $this->published_at->toIso8601String(), 'validThrough' => $this->deadline->min($this->expires_at)->toIso8601String(), 'hiringOrganization' => ['@type' => 'Organization', 'name' => $this->company->name], 'url' => route('jobs.show', $this), 'employmentType' => match ($this->employment_type) {
            'Full-time' => 'FULL_TIME','Part-time' => 'PART_TIME','Kontrak','Freelance' => 'CONTRACTOR','Magang' => 'INTERN',default => 'OTHER'
        }];
        if ($this->work_arrangement === 'REMOTE') {
            $data['jobLocationType'] = 'TELECOMMUTE';
            $data['applicantLocationRequirements'] = ['@type' => 'AdministrativeArea', 'name' => $this->location, 'containedInPlace' => ['@type' => 'Country', 'name' => $this->country_code]];
        } else {
            $data['jobLocation'] = ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $this->location, 'addressRegion' => $this->province, 'addressCountry' => $this->country_code]];
        }
        if ($this->salary_visible && $this->salary_min) {
            $data['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => 'IDR', 'value' => ['@type' => 'QuantitativeValue', 'minValue' => $this->salary_min, 'maxValue' => $this->salary_max ?? $this->salary_min, 'unitText' => match ($this->salary_period) {
                'tahun' => 'YEAR','jam' => 'HOUR',default => 'MONTH'
            }]];
        }

        return $data;
    }

    public function salaryLabel(): string
    {
        if (! $this->salary_visible || ! $this->salary_min) {
            return 'Gaji tidak ditampilkan';
        }

        return 'Rp '.number_format($this->salary_min, 0, ',', '.').($this->salary_max ? ' – '.number_format($this->salary_max, 0, ',', '.') : '').' / '.$this->salary_period;
    }
}
