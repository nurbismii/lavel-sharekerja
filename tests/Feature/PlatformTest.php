<?php

namespace Tests\Feature;

use App\Http\Requests\JobPostingRequest;
use App\Jobs\SendJobEmail;
use App\Jobs\SendPurchaseReceipt;
use App\Models\Company;
use App\Models\CreditEntry;
use App\Models\EmailDelivery;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransPayments;
use App\Services\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    public function test_public_pages_render_and_hidden_salary_is_not_exposed(): void
    {
        $job = JobPosting::factory()->published()->create();
        $this->get('/')->assertOk()->assertSee('Langkah berikutnya');
        $this->get(route('jobs.show', $job))->assertOk()->assertSee('Gaji tidak ditampilkan')->assertDontSee('7.000.000');
        $this->get('/jobs?salary=100')->assertOk()->assertDontSee($job->title);
        $this->get('/sitemap.xml')->assertOk()->assertSee($job->slug);
        $this->get('/login')->assertOk();
    }

    public function test_credit_reservation_publication_and_retry_are_consistent(): void
    {
        $owner = $this->account(Role::RECRUITER);
        $owner->forceFill(['credits' => 1])->save();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $job = JobPosting::factory()->create(['company_id' => $company->id]);
        $admin = $this->account(Role::ADMIN);
        $service = app(PublicationService::class);
        $service->transition($job, 'submit', $owner);
        $service->transition($job, 'submit', $owner);
        $this->assertSame(0, $owner->fresh()->availableCredits());
        $this->assertSame(1, $owner->fresh()->reserved_credits);
        $service->transition($job, 'approve', $admin);
        $service->transition($job, 'approve', $admin);
        $this->assertSame(0, $owner->fresh()->credits);
        $this->assertSame(0, $owner->fresh()->reserved_credits);
        $this->assertTrue($job->fresh()->isLive());
        $this->assertSame(1, CreditEntry::where('type', 'PUBLISH')->count());
    }

    public function test_rejection_releases_credit_and_insufficient_balance_does_not_publish(): void
    {
        $owner = $this->account(Role::RECRUITER);
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $job = JobPosting::factory()->create(['company_id' => $company->id]);
        $this->actingAs($owner)->post(route('recruiter.jobs.status', $job), ['action' => 'submit'])->assertSessionHasErrors('job');
        $this->assertSame('DRAFT', $job->fresh()->status);
        $owner->forceFill(['credits' => 1])->save();
        app(PublicationService::class)->transition($job, 'submit', $owner);
        app(PublicationService::class)->transition($job, 'reject', $this->account(Role::ADMIN), 'Perbaiki deskripsi');
        $this->assertSame(1, $owner->fresh()->availableCredits());
    }

    public function test_recruiter_cannot_access_another_company_or_candidate_cv(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cvs/test.pdf', 'test');
        $job = JobPosting::factory()->published()->create(['method' => 'INTERNAL']);
        $seeker = $this->account(Role::SEEKER);
        $application = JobApplication::create(['job_posting_id' => $job->id, 'user_id' => $seeker->id, 'name' => 'Kandidat', 'email' => $seeker->email, 'phone' => '0812', 'cv_path' => 'cvs/test.pdf']);
        $other = $this->account(Role::RECRUITER);
        $this->actingAs($other)->get(route('applications.cv', $application))->assertForbidden();
        $this->get(route('recruiter.candidates', $job))->assertForbidden();
        $this->get(route('recruiter.jobs.edit', $job))->assertForbidden();
        $this->actingAs($seeker)->get(route('applications.cv', $application))->assertOk();
    }

    public function test_internal_application_is_unique_and_expired_job_is_blocked(): void
    {
        Storage::fake('local');
        $seeker = $this->account(Role::SEEKER);
        $job = JobPosting::factory()->published()->create(['method' => 'INTERNAL']);
        $payload = ['name' => 'Kandidat', 'phone' => '08123456', 'cv' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
        $this->actingAs($seeker)->post(route('applications.store', $job), $payload)->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->post(route('applications.store', $job), $payload)->assertRedirect();
        $this->assertSame(1, JobApplication::count());
        $job->update(['deadline' => now()->subMinute()]);
        $this->get(route('applications.create', $job))->assertNotFound();
    }

    public function test_google_role_input_cannot_grant_admin_and_suspension_blocks_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), ['role' => 'SUPER_ADMIN'])->assertSessionHasErrors('role');
        $this->assertFalse($user->hasRole(Role::ADMIN));
        $user->forceFill(['status' => 'SUSPENDED'])->save();
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_midtrans_webhook_rechecks_provider_and_grants_only_once(): void
    {
        config(['services.midtrans.server_key' => 'test-key', 'services.midtrans.merchant_id' => 'test-merchant', 'services.midtrans.is_production' => false]);
        $user = $this->account(Role::RECRUITER);
        $package = Package::create(['name' => 'Test', 'price' => 100000, 'credits' => 3]);
        $purchase = Purchase::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'package_id' => $package->id, 'package_name' => 'Test', 'amount' => 100000, 'credits' => 3, 'provider' => 'midtrans', 'provider_id' => 'invoice-test']);
        $invoice = ['transaction_id' => 'invoice-test', 'order_id' => $purchase->reference, 'currency' => 'IDR', 'gross_amount' => '100000.00', 'transaction_status' => 'settlement', 'status_code' => '200', 'merchant_id' => 'test-merchant'];
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response($invoice)]);
        $body = ['order_id' => $purchase->reference, 'status_code' => '200', 'gross_amount' => '100000.00', 'signature_key' => str_repeat('0', 128)];
        $this->postJson(route('midtrans.webhook'), $body)->assertUnauthorized();
        $body['signature_key'] = hash('sha512', $purchase->reference.'200100000.00test-key');
        $this->postJson(route('midtrans.webhook'), $body)->assertOk();
        $this->postJson(route('midtrans.webhook'), $body)->assertOk();
        $this->assertSame(3, $user->fresh()->credits);
        $this->assertSame(1, CreditEntry::count());
        app(MidtransPayments::class)->apply($purchase, array_replace($invoice, ['transaction_status' => 'expire']));
        $this->assertSame('PAID', $purchase->fresh()->status);
    }

    public function test_unsubscribed_recipient_is_not_emailed_and_dispatch_is_resumable(): void
    {
        Queue::fake();
        Mail::shouldReceive('send')->never();
        config(['sharekerja.email_per_minute' => 10, 'sharekerja.email_per_hour' => 100]);
        $seeker = $this->account(Role::SEEKER);
        $job = JobPosting::factory()->published()->create();
        $subscription = Subscription::create(['user_id' => $seeker->id, 'active' => true, 'preferences' => [], 'token' => Str::random(64), 'subscribed_at' => now()->subDay()]);
        $this->artisan('jobs:distribute')->assertSuccessful();
        $job->update(['distribution_cursor' => 0, 'distributed_at' => null]);
        $this->artisan('jobs:distribute')->assertSuccessful();
        $this->assertSame(1, EmailDelivery::count());
        $subscription->update(['active' => false]);
        app()->call([new SendJobEmail(EmailDelivery::first()->id), 'handle']);
        $this->assertSame('SKIPPED', EmailDelivery::first()->status);
    }

    public function test_dashboard_admin_and_forms_render(): void
    {
        $admin = $this->account(Role::ADMIN);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $recruiter = $this->account(Role::RECRUITER);
        Company::factory()->create(['owner_user_id' => $recruiter->id]);
        foreach (['/dashboard', '/recruiter/company', '/recruiter/jobs/create', '/recruiter/packages'] as $url) {
            $this->actingAs($recruiter)->get($url)->assertOk();
        }
        $seeker = $this->account(Role::SEEKER);
        $this->actingAs($seeker)->get('/subscription')->assertOk();
        $this->get('/dashboard')->assertOk();
    }

    public function test_partial_draft_cannot_be_submitted_and_deadline_uses_display_timezone(): void
    {
        $owner = $this->account(Role::RECRUITER);
        $owner->forceFill(['credits' => 1])->save();
        Company::factory()->create(['owner_user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('recruiter.jobs.store'), ['title' => 'Draft tahap pertama'])->assertSessionHasNoErrors()->assertRedirect();
        $job = JobPosting::firstOrFail();
        $this->assertNull($job->deadline);
        $this->post(route('recruiter.jobs.status', $job), ['action' => 'submit'])->assertSessionHasErrors(['description', 'deadline']);
        $this->assertSame(0, $owner->fresh()->reserved_credits);
        $this->freezeTime();
        $local = now()->addHours(2)->timezone(config('sharekerja.timezone'))->format('Y-m-d\TH:i');
        $this->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'deadline' => $local])->assertSessionHasNoErrors();
        $this->assertSame(now()->addHours(2)->startOfMinute()->timestamp, $job->fresh()->deadline->timestamp);
        $past = now()->subHour()->timezone(config('sharekerja.timezone'))->format('Y-m-d\TH:i');
        $this->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'deadline' => $past])->assertSessionHasErrors('deadline');
    }

    public function test_pending_deadline_releases_reservation_once_and_expired_markup_is_removed(): void
    {
        $owner = $this->account(Role::RECRUITER);
        $owner->forceFill(['credits' => 1])->save();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $job = JobPosting::factory()->create(['company_id' => $company->id]);
        app(PublicationService::class)->transition($job, 'submit', $owner);
        $job->update(['deadline' => now()->subMinute()]);
        $this->artisan('platform:maintain')->assertSuccessful();
        $this->artisan('platform:maintain')->assertSuccessful();
        $this->assertSame(1, $owner->fresh()->availableCredits());
        $this->assertSame('DRAFT', $job->fresh()->status);
        $published = JobPosting::factory()->published()->create();
        $this->get(route('jobs.show', $published))->assertSee('application/ld+json', false)->assertDontSee('7000000');
        $published->update(['deadline' => now()->subMinute()]);
        $this->get(route('jobs.show', $published))->assertOk()->assertDontSee('application/ld+json', false);
        $this->get(route('jobs.external', $published))->assertNotFound();
    }

    public function test_refund_hold_prevents_credit_spend_and_completion_is_idempotent(): void
    {
        config(['services.midtrans.merchant_id' => 'test-merchant']);
        $user = $this->account(Role::RECRUITER);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = JobPosting::factory()->create(['company_id' => $company->id]);
        $package = Package::create(['name' => 'Test', 'price' => 100000, 'credits' => 1]);
        $purchase = Purchase::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'package_id' => $package->id, 'package_name' => 'Test', 'amount' => 100000, 'credits' => 1, 'provider' => 'midtrans']);
        app(MidtransPayments::class)->apply($purchase, ['transaction_id' => 'invoice-refund', 'order_id' => $purchase->reference, 'currency' => 'IDR', 'gross_amount' => '100000.00', 'transaction_status' => 'settlement', 'status_code' => '200', 'merchant_id' => 'test-merchant']);
        $admin = $this->account(Role::ADMIN);
        $this->actingAs($admin)->post(route('admin.refund', $purchase), ['action' => 'hold', 'reason' => 'Permintaan refund'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('recruiter.jobs.status', $job), ['action' => 'submit'])->assertSessionHasErrors('job');
        $this->actingAs($admin)->post(route('admin.refund', $purchase), ['action' => 'success', 'reason' => 'Hasil provider diverifikasi', 'reference' => 'refund-test'])->assertSessionHasNoErrors();
        $this->post(route('admin.refund', $purchase), ['action' => 'success', 'reason' => 'Retry', 'reference' => 'refund-test'])->assertUnprocessable();
        $this->assertSame(0, $user->fresh()->credits);
        $this->assertSame(0, $user->fresh()->reserved_credits);
        $this->assertSame(1, CreditEntry::where('type', 'REFUND')->count());
    }

    public function test_midtrans_checkout_is_created_and_wrong_amount_is_rejected(): void
    {
        config(['services.midtrans.server_key' => 'test-key', 'services.midtrans.merchant_id' => 'test-merchant', 'services.midtrans.is_production' => false]);
        $user = $this->account(Role::RECRUITER);
        $package = Package::create(['name' => 'Test', 'price' => 100000, 'credits' => 1]);
        $purchase = Purchase::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'package_id' => $package->id, 'package_name' => 'Test', 'amount' => 100000, 'credits' => 1, 'provider' => 'midtrans']);
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test']),
            'api.sandbox.midtrans.com/*' => Http::response(['transaction_id' => 'invoice-recover', 'order_id' => $purchase->reference, 'currency' => 'IDR', 'gross_amount' => '1.00', 'transaction_status' => 'settlement', 'status_code' => '200', 'merchant_id' => 'test-merchant']),
        ]);
        app(MidtransPayments::class)->invoice($purchase);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/test', $purchase->fresh()->checkout_url);
        $body = ['order_id' => $purchase->reference, 'status_code' => '200', 'gross_amount' => '100000.00', 'signature_key' => hash('sha512', $purchase->reference.'200100000.00test-key')];
        $this->postJson(route('midtrans.webhook'), $body)->assertUnprocessable();
        $this->assertSame(0, $user->fresh()->credits);
    }

    public function test_email_is_sent_once_and_receipts_share_the_delivery_quota(): void
    {
        config(['sharekerja.email_per_minute' => 1, 'sharekerja.email_per_hour' => 10]);
        $user = $this->account(Role::SEEKER);
        $subscription = Subscription::create(['user_id' => $user->id, 'active' => true, 'token' => Str::random(64), 'preferences' => [], 'subscribed_at' => now()->subDay()]);
        $job = JobPosting::factory()->published()->create();
        $delivery = EmailDelivery::create(['job_posting_id' => $job->id, 'subscription_id' => $subscription->id, 'status' => 'PENDING']);
        Mail::shouldReceive('send')->once()->with('emails.job', \Mockery::type('array'), \Mockery::type('callable'));
        app()->call([new SendJobEmail($delivery->id), 'handle']);
        app()->call([new SendJobEmail($delivery->id), 'handle']);
        $this->assertSame('ACCEPTED', $delivery->fresh()->status);
        $package = Package::create(['name' => 'Test', 'price' => 100000, 'credits' => 1]);
        $purchase = Purchase::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'package_id' => $package->id, 'package_name' => 'Test', 'amount' => 100000, 'credits' => 1, 'status' => 'PAID']);
        app()->call([new SendPurchaseReceipt($purchase->id), 'handle']);
        $this->assertSame('PENDING', $purchase->fresh()->receipt_status);
    }

    public function test_external_urls_reject_local_addresses_and_credentials(): void
    {
        foreach (['https://127.1', 'https://0177.0.0.1', 'https://2130706433', 'https://localhost', 'https://bit.ly/example', 'javascript:alert(1)', 'https://user:password@example.com', 'https://example.com\\@localhost'] as $url) {
            $this->assertFalse(JobPostingRequest::safeExternalUrl($url), $url);
        }
        $this->assertTrue(JobPostingRequest::safeExternalUrl('https://career.example.com/jobs/123'));
    }

    public function test_credit_audit_detects_unrecorded_balance_changes(): void
    {
        $user = $this->account(Role::RECRUITER);
        $this->artisan('credits:audit')->assertSuccessful();
        $user->forceFill(['credits' => 1])->save();
        $this->artisan('credits:audit')->assertFailed();
    }
}
