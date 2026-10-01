<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class EmailQuota
{
    public function acquire(): bool
    {
        $minute = config('sharekerja.email_per_minute');
        $hour = config('sharekerja.email_per_hour');
        if ($minute < 1 || $hour < 1) {
            return false;
        }

        return (bool) Cache::lock('email-rate', 5)->get(function () use ($minute, $hour) {
            if (RateLimiter::tooManyAttempts('job-email-minute', $minute) || RateLimiter::tooManyAttempts('job-email-hour', $hour)) {
                return false;
            }
            RateLimiter::hit('job-email-minute', 60);
            RateLimiter::hit('job-email-hour', 3600);

            return true;
        });
    }
}
