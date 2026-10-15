<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruiter');
    }

    public function rules(): array
    {
        $rules = self::publicationRules();
        foreach ($rules as $field => $rule) {
            if ($field === 'title') {
                continue;
            }
            $parts = is_array($rule) ? $rule : explode('|', $rule);
            $rules[$field] = array_merge(['nullable'], array_filter($parts, fn ($item) => ! is_string($item) || (! str_starts_with($item, 'required') && $item !== 'nullable')));
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $deadline = $this->input('deadline');
        if (is_string($deadline) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $deadline)) {
            try {
                $this->merge(['deadline' => Carbon::parse($deadline, config('sharekerja.timezone'))->utc()->toIso8601String()]);
            } catch (\Throwable) { /* The date rule reports malformed input. */
            }
        }
    }

    public static function publicationRules(): array
    {
        return ['country_code' => 'required|string|regex:/^[A-Z]{2}$/', 'province' => 'nullable|string|max:100', 'title' => 'required|string|max:150', 'category' => ['required', Rule::in(config('sharekerja.categories'))], 'employment_type' => ['required', Rule::in(config('sharekerja.employment_types'))], 'level' => ['required', Rule::in(config('sharekerja.levels'))], 'vacancies' => 'required|integer|min:1|max:10000', 'location' => 'required|string|max:150', 'work_arrangement' => 'required|in:ON_SITE,HYBRID,REMOTE', 'salary_min' => 'nullable|integer|min:0|max:1000000000', 'salary_max' => 'nullable|integer|gte:salary_min|max:1000000000', 'salary_visible' => 'required|boolean', 'salary_period' => 'required|in:bulan,tahun,jam', 'description' => 'required|string|min:30|max:20000', 'qualifications' => 'required|string|min:10|max:10000', 'responsibilities' => 'nullable|string|max:10000', 'benefits' => 'nullable|string|max:5000', 'education' => ['nullable', 'string', Rule::in(config('sharekerja.education_levels'))], 'experience' => 'nullable|string|max:150', 'skills' => 'nullable|string|max:255', 'method' => 'required|in:EXTERNAL,INTERNAL', 'external_url' => ['nullable', 'required_if:method,EXTERNAL', 'url:https', 'max:2000', function (string $attribute, mixed $value, \Closure $fail) {
            if ($value && (! is_string($value) || ! self::safeExternalUrl($value))) {
                $fail('Gunakan URL HTTPS langsung ke website rekrutmen publik, tanpa shortener atau alamat lokal.');
            }
        }], 'deadline' => 'required|date|after:now'];
    }

    public static function safeExternalUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! preg_match('/\.(?:[a-z]{2,63}|xn--[a-z0-9-]+)$/', $host) || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return false;
        }
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }
        if (! str_contains($host, '.') || preg_match('/(?:^|\.)(localhost|local|internal|test|invalid)$/', $host) || in_array($host, ['bit.ly', 'tinyurl.com', 't.co', 'shorturl.at'], true)) {
            return false;
        }
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            return false;
        }

        return (bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }
}
