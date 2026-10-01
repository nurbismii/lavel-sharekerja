<?php

namespace App\Providers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::define('recruiter', fn (User $user) => $user->status === 'ACTIVE' && $user->hasRole(Role::RECRUITER));
        Gate::define('seeker', fn (User $user) => $user->status === 'ACTIVE' && $user->hasRole(Role::SEEKER));
        Gate::define('admin', fn (User $user) => $user->status === 'ACTIVE' && $user->hasRole(Role::ADMIN) && (! app()->isProduction() || config('sharekerja.admin_access_enabled')));
        Gate::define('manage-job', fn (User $user, JobPosting $job) => $user->status === 'ACTIVE' && $user->hasRole(Role::RECRUITER) && $job->company->owner_user_id === $user->id && $job->company->status === 'ACTIVE');
        Gate::define('view-cv', fn (User $user, JobApplication $application) => $application->user_id === $user->id || ($user->can('manage-job', $application->job)) || $user->can('admin'));
    }
}
