<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobReport;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    public function test_admin_navigation_displays_only_the_selected_section(): void
    {
        $admin = $this->account(Role::ADMIN);
        Company::factory()->count(11)->create(['verification_status' => 'PENDING']);
        $job = JobPosting::factory()->published()->create();
        $package = Package::create(['name' => 'Starter', 'price' => 100000, 'credits' => 3, 'active' => true]);
        $purchase = Purchase::create(['reference' => (string) Str::uuid(), 'user_id' => $job->company->owner_user_id, 'package_id' => $package->id, 'package_name' => $package->name, 'amount' => $package->price, 'credits' => $package->credits, 'status' => 'PAID']);
        JobReport::create(['job_posting_id' => $job->id, 'user_id' => $admin->id, 'reason' => 'Informasi tidak sesuai']);
        Subscription::create(['user_id' => $admin->id, 'active' => true, 'token' => Str::random(64)]);
        $this->actingAs($admin);

        foreach (['verification', 'moderation', 'packages', 'transactions', 'users', 'reports', 'subscribers', 'audit'] as $section) {
            $response = $this->get(route('admin', ['section' => $section]))
                ->assertOk()
                ->assertSee('ADMIN CONSOLE')
                ->assertSee('id="'.$section.'"', false);

            foreach (array_diff(['verification', 'moderation', 'packages', 'transactions', 'users', 'reports', 'subscribers', 'audit'], [$section]) as $hiddenSection) {
                $response->assertDontSee('id="'.$hiddenSection.'"', false);
            }
        }

        $this->get(route('admin', ['section' => 'verification', 'companies_page' => 2]))
            ->assertOk()->assertSee('section=verification&amp;companies_page=1', false);
        $this->get(route('purchases.show', $purchase))->assertOk()->assertSee('ADMIN CONSOLE');
    }

    public function test_unknown_admin_sections_fall_back_to_verification_and_remain_protected(): void
    {
        $this->get(route('admin'))->assertRedirect(route('login'));
        $this->actingAs($this->account(Role::RECRUITER))->get(route('admin'))->assertForbidden();
        $this->actingAs($this->account(Role::ADMIN));

        foreach (['unknown', ['users']] as $section) {
            $this->get(route('admin', ['section' => $section]))->assertOk()
                ->assertSee('id="verification"', false)->assertDontSee('id="users"', false);
        }
    }

    public function test_recruiter_without_a_company_is_guided_to_complete_their_profile(): void
    {
        $package = Package::create(['name' => 'Starter', 'price' => 100000, 'credits' => 3, 'active' => true]);
        $this->actingAs($this->account(Role::RECRUITER))->get(route('dashboard'))->assertOk()
            ->assertSee('RECRUITER SPACE')->assertSee('Mulai dari profil perusahaan')
            ->assertDontSee('href="'.route('recruiter.jobs.create').'"', false);
        $this->get(route('packages'))->assertOk()->assertSee('Periksa profil perusahaan')
            ->assertDontSee('action="'.route('purchases.store', $package).'"', false);
    }

    public function test_recruiter_dashboard_keeps_jobs_private_and_shows_relevant_actions(): void
    {
        $recruiter = $this->account(Role::RECRUITER);
        $company = Company::factory()->create(['owner_user_id' => $recruiter->id, 'name' => 'Talenta Nusantara']);
        $draft = JobPosting::factory()->create(['company_id' => $company->id, 'title' => 'Product Designer', 'method' => 'INTERNAL']);
        $published = JobPosting::factory()->published()->create(['company_id' => $company->id, 'title' => 'Frontend Engineer']);
        $other = JobPosting::factory()->create(['title' => 'Lowongan perusahaan lain']);

        $this->actingAs($recruiter)->get(route('dashboard'))->assertOk()
            ->assertSee('Talenta Nusantara')->assertSee($draft->title)->assertSee($published->title)
            ->assertSee(route('recruiter.jobs.edit', $draft))->assertSee(route('recruiter.candidates', $draft))
            ->assertSee(route('jobs.show', $published))->assertDontSee($other->title);
    }

    public function test_recruiter_forms_and_candidate_statuses_use_the_workspace(): void
    {
        $recruiter = $this->account(Role::RECRUITER);
        $company = Company::factory()->create(['owner_user_id' => $recruiter->id]);
        $job = JobPosting::factory()->create(['company_id' => $company->id, 'method' => 'INTERNAL']);
        $candidate = $this->account(Role::SEEKER);
        JobApplication::create(['user_id' => $candidate->id, 'job_posting_id' => $job->id, 'name' => $candidate->name, 'email' => $candidate->email, 'phone' => '081234567890', 'cv_path' => 'cvs/test.pdf', 'status' => 'NEW']);
        Package::create(['name' => 'Paket Rekrutmen', 'price' => 100000, 'credits' => 3, 'active' => true]);
        $this->actingAs($recruiter);

        foreach ([route('recruiter.company'), route('recruiter.jobs.create'), route('recruiter.jobs.edit', $job), route('packages')] as $url) {
            $this->get($url)->assertOk()->assertSee('RECRUITER SPACE');
        }

        $this->get(route('recruiter.candidates', $job))->assertOk()->assertSee($candidate->name)
            ->assertSee('value="REVIEWED">', false)->assertSee('value="SHORTLISTED">', false)
            ->assertDontSee('value="HIRED">', false);
        $this->actingAs($this->account(Role::RECRUITER))->get(route('recruiter.candidates', $job))->assertForbidden();
    }

    public function test_recruiter_can_filter_new_applications_and_view_portfolios(): void
    {
        $recruiter = $this->account(Role::RECRUITER);
        $company = Company::factory()->create(['owner_user_id' => $recruiter->id]);
        $job = JobPosting::factory()->published()->create(['company_id' => $company->id, 'method' => 'INTERNAL']);
        foreach (['Dara_Safitri' => 'NEW', 'DaraXPutri' => 'NEW', 'Dara_Aulia' => 'REVIEWED'] as $name => $status) {
            $seeker = $this->account(Role::SEEKER);
            $job->applications()->create([
                'user_id' => $seeker->id, 'name' => $name, 'email' => $seeker->email, 'phone' => '081234567890',
                'cv_path' => 'cvs/test.pdf', 'status' => $status, 'portfolio' => 'https://example.com/'.$name,
            ]);
        }

        $this->actingAs($recruiter)->get(route('dashboard'))->assertOk()->assertSee('2 lamaran baru')
            ->assertSee(route('recruiter.candidates', ['job' => $job, 'status' => 'NEW']));
        $this->get(route('recruiter.candidates', ['job' => $job, 'q' => 'dara_', 'status' => 'NEW']))
            ->assertOk()->assertSee('Dara_Safitri')->assertSee('href="https://example.com/Dara_Safitri"', false)
            ->assertDontSee('DaraXPutri')->assertDontSee('Dara_Aulia');
        $this->get(route('recruiter.candidates', ['job' => $job, 'status' => 'HIRED']))
            ->assertOk()->assertSee('Kandidat tidak ditemukan');
        $this->get(route('recruiter.candidates', ['job' => $job, 'status' => 'INVALID']))
            ->assertSessionHasErrors('status');
    }

    public function test_seeker_dashboard_retains_its_own_navigation(): void
    {
        $this->actingAs($this->account(Role::SEEKER))->get(route('dashboard'))->assertOk()
            ->assertSee('Lamaran saya')->assertDontSee('RECRUITER SPACE')->assertDontSee('ADMIN CONSOLE');
    }
}
