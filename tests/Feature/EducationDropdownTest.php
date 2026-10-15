<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationDropdownTest extends TestCase
{
    use RefreshDatabase;

    private function recruiter(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => Role::RECRUITER]));
        Company::factory()->create(['owner_user_id' => $user->id]);

        return $user;
    }

    public function test_requested_education_options_render_and_save(): void
    {
        $user = $this->recruiter();
        $form = $this->actingAs($user)->get(route('recruiter.jobs.create'))->assertOk()->assertSee('<select id="education" name="education"', false)->assertSee('<option value="" selected>Tidak ditentukan</option>', false);
        foreach (['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'] as $education) {
            $form->assertSee('<option value="'.$education.'"', false);
            $this->post(route('recruiter.jobs.store'), ['title' => 'Lowongan '.$education, 'education' => $education])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('job_postings', ['company_id' => $user->company->id, 'title' => 'Lowongan '.$education, 'education' => $education]);
        }
    }

    public function test_edit_selection_and_validation_old_input_are_preserved(): void
    {
        $user = $this->recruiter();
        $job = JobPosting::factory()->create(['company_id' => $user->company->id, 'education' => 'SMA']);
        $this->actingAs($user)->get(route('recruiter.jobs.edit', $job))->assertOk()->assertSee('<option value="SMA" selected>', false);
        $this->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'education' => 'S1'])->assertSessionHasNoErrors();
        $this->assertSame('S1', $job->fresh()->education);
        $response = $this->from(route('recruiter.jobs.edit', $job))->put(route('recruiter.jobs.update', $job), ['title' => '', 'education' => 'S2'])->assertSessionHasErrors('title');
        $this->followRedirects($response)->assertOk()->assertSee('<option value="S2" selected>', false);
        $this->assertSame('S1', $job->fresh()->education);
    }

    public function test_invalid_values_are_rejected_and_education_remains_optional(): void
    {
        $user = $this->recruiter();
        $this->actingAs($user);
        foreach (['S4', ['S1'], 'Sarjana'] as $education) {
            $this->post(route('recruiter.jobs.store'), ['title' => 'Invalid education', 'education' => $education])->assertSessionHasErrors('education');
        }
        $this->assertSame(0, JobPosting::count());
        $this->post(route('recruiter.jobs.store'), ['title' => 'Tanpa syarat pendidikan', 'education' => ''])->assertSessionHasNoErrors();
        $this->assertNull(JobPosting::firstOrFail()->education);
    }

    public function test_search_education_dropdown_filters_exact_level(): void
    {
        $sma = JobPosting::factory()->published()->create(['title' => 'Staff lulusan SMA', 'education' => 'SMA']);
        $smk = JobPosting::factory()->published()->create(['title' => 'Teknisi lulusan SMK', 'education' => 'SMK']);
        $this->get(route('jobs.index', ['education' => 'SMA']))->assertOk()->assertSee('<select id="education" name="education"', false)->assertSee('<option value="SMA" selected>', false)->assertSee($sma->title)->assertDontSee($smk->title);
        $this->getJson(route('jobs.index', ['education' => 'S4']))->assertUnprocessable()->assertJsonValidationErrors('education');
        $this->get(route('jobs.index'))->assertOk()->assertSee($sma->title)->assertSee($smk->title);
    }

    public function test_publication_rejects_invalid_stored_education_without_reserving_credit(): void
    {
        $user = $this->recruiter();
        $user->forceFill(['credits' => 1])->save();
        $job = JobPosting::factory()->create(['company_id' => $user->company->id, 'education' => 'S4']);
        $this->actingAs($user)->post(route('recruiter.jobs.status', $job), ['action' => 'submit'])->assertSessionHasErrors('education');
        $this->assertSame(0, $user->fresh()->reserved_credits);
        $this->assertSame(JobPosting::DRAFT, $job->fresh()->status);
        $job->update(['education' => 'S1']);
        $this->post(route('recruiter.jobs.status', $job), ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->assertSame(JobPosting::PENDING, $job->fresh()->status);
    }
}
