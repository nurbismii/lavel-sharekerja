<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RecruiterController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [JobController::class, 'index'])->name('home');
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/locations/cities', CityController::class)->middleware('throttle:60,1')->name('locations.cities');
Route::get('/locations/regions', [CityController::class, 'regions'])->middleware('throttle:60,1')->name('locations.regions');
Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/jobs/{job}/apply', [JobController::class, 'external'])->middleware('throttle:30,1')->name('jobs.external');
Route::post('/jobs/{job}/share', [JobController::class, 'share'])->middleware('throttle:30,1')->name('jobs.share');
Route::get('/companies/{company:slug}', [JobController::class, 'company'])->name('companies.show');
Route::get('/companies/{company:slug}/logo', function (Company $company, Request $request) {
    abort_unless($company->canPublish() || $request->user()?->id === $company->owner_user_id || $request->user()?->can('admin'), 404);
    abort_unless($company->logo_path, 404);

    return Storage::disk('public')->response($company->logo_path, null, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
})->name('companies.logo');
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/google', [AuthController::class, 'redirect'])->middleware('throttle:10,1')->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->middleware('throttle:20,1')->name('auth.callback');
Route::get('/unsubscribe/{token}', [SubscriptionController::class, 'unsubscribe'])->name('unsubscribe');
Route::post('/unsubscribe/{token}', [SubscriptionController::class, 'stop'])->middleware('throttle:10,1')->name('unsubscribe.stop');
Route::post('/webhooks/midtrans', [PurchaseController::class, 'webhook'])->name('midtrans.webhook');
Route::get('/sitemap.xml', function () {
    return response()->view('sitemap', ['jobs' => JobPosting::live()->select('slug', 'updated_at')->limit(10000)->get()])->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/onboarding', [AuthController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [AuthController::class, 'role'])->name('onboarding.store');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/applications/{application}/cv', [ApplicationController::class, 'download'])->name('applications.cv');
    Route::middleware('can:seeker')->group(function () {
        Route::post('/jobs/{job}/save', [JobController::class, 'save'])->name('jobs.save');
        Route::post('/jobs/{job}/report', [JobController::class, 'report'])->middleware('throttle:5,60')->name('jobs.report');
        Route::get('/jobs/{job}/application', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('/jobs/{job}/application', [ApplicationController::class, 'store'])->middleware('throttle:10,1')->name('applications.store');
        Route::get('/subscription', [SubscriptionController::class, 'edit'])->name('subscription.edit');
        Route::post('/subscription', [SubscriptionController::class, 'update'])->name('subscription.update');
    });
    Route::prefix('recruiter')->middleware('can:recruiter')->group(function () {
        Route::get('/company', [RecruiterController::class, 'company'])->name('recruiter.company');
        Route::post('/company', [RecruiterController::class, 'storeCompany'])->name('recruiter.company.store');
        Route::get('/jobs/create', [RecruiterController::class, 'create'])->name('recruiter.jobs.create');
        Route::post('/jobs', [RecruiterController::class, 'store'])->name('recruiter.jobs.store');
        Route::get('/jobs/{job}/edit', [RecruiterController::class, 'edit'])->name('recruiter.jobs.edit');
        Route::put('/jobs/{job}', [RecruiterController::class, 'update'])->name('recruiter.jobs.update');
        Route::post('/jobs/{job}/status', [RecruiterController::class, 'transition'])->name('recruiter.jobs.status');
        Route::get('/jobs/{job}/candidates', [ApplicationController::class, 'candidates'])->name('recruiter.candidates');
        Route::post('/applications/{application}/status', [ApplicationController::class, 'status'])->name('applications.status');
        Route::get('/packages', [PurchaseController::class, 'index'])->name('packages');
        Route::post('/packages/{package}', [PurchaseController::class, 'store'])->middleware('throttle:5,1')->name('purchases.store');
    });
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('/purchases/{purchase}/refresh', [PurchaseController::class, 'refresh'])->middleware('throttle:5,1')->name('purchases.refresh');
    Route::prefix('admin')->middleware('can:admin')->group(function () {
        Route::post('/subscribers/{subscription}', [AdminController::class, 'subscriber'])->name('admin.subscribers');
        Route::get('/', [AdminController::class, 'index'])->name('admin');
        Route::post('/companies/{company}/verify', [AdminController::class, 'verify'])->name('admin.verify');
        Route::post('/jobs/{job}/moderate', [AdminController::class, 'job'])->name('admin.jobs');
        Route::post('/packages', [AdminController::class, 'package'])->name('admin.packages');
        Route::post('/packages/{package}/toggle', [AdminController::class, 'togglePackage'])->name('admin.packages.toggle');
        Route::post('/reports/{report}', [AdminController::class, 'report'])->name('admin.reports');
        Route::post('/users/{user}', [AdminController::class, 'user'])->name('admin.users');
        Route::post('/purchases/{purchase}/refund', [AdminController::class, 'refund'])->name('admin.refund');
    });
});
