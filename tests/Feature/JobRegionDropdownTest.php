<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobRegionDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function recruiter(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => Role::RECRUITER]));
        Company::factory()->create(['owner_user_id' => $user->id]);

        return $user;
    }

    /**
     * @return list<array{id: string, name: string, value: string, province: string}>
     */
    private function catalog(): array
    {
        return [
            ['id' => '73.71', 'name' => 'Kota Makassar', 'value' => 'Makassar', 'province' => 'Sulawesi Selatan'],
            ['id' => '73.06', 'name' => 'Kabupaten Gowa', 'value' => 'Gowa', 'province' => 'Sulawesi Selatan'],
            ['id' => '32.73', 'name' => 'Kota Bandung', 'value' => 'Bandung', 'province' => 'Jawa Barat'],
        ];
    }

    public function test_dropdown_catalog_reuses_the_city_search_cache(): void
    {
        Cache::put('regions.cities.v1', $this->catalog(), 60);
        $this->getJson(route('locations.regions'))->assertOk()->assertExactJson(['data' => $this->catalog()]);
        $this->getJson(route('locations.cities', ['q' => 'Makassar']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', 'Makassar');
        Http::assertNothingSent();
    }

    public function test_catalog_can_populate_from_the_provider_without_search_query(): void
    {
        Http::fake([
            'wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '73', 'name' => 'Sulawesi Selatan']]]),
            'wilayah.id/api/regencies/73.json' => Http::response(['data' => [['code' => '73.71', 'name' => 'Kota Makassar']]]),
        ]);
        $this->getJson(route('locations.regions'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.province', 'Sulawesi Selatan');
        Http::assertSentCount(2);
    }

    public function test_create_and_edit_forms_render_dependent_dropdowns_and_keep_existing_values(): void
    {
        $user = $this->recruiter();
        $job = JobPosting::factory()->create(['company_id' => $user->company->id, 'province' => 'Sulawesi Selatan', 'location' => 'Makassar']);
        $this->actingAs($user)->get(route('recruiter.jobs.create'))->assertOk()->assertSee('data-job-regions', false)->assertSee('data-region-province', false)->assertSee('data-region-city', false)->assertSee(route('locations.regions'), false);
        $this->get(route('recruiter.jobs.edit', $job))->assertOk()->assertSee('value="Sulawesi Selatan"', false)->assertSee('value="Makassar"', false)->assertSee('data-region-manual-toggle', false);
        Http::assertNothingSent();
    }

    public function test_selected_province_and_city_save_to_existing_columns(): void
    {
        $user = $this->recruiter();
        $this->actingAs($user)->post(route('recruiter.jobs.store'), ['title' => 'Engineer Makassar', 'country_code' => 'ID', 'province' => 'Sulawesi Selatan', 'location' => 'Makassar'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('job_postings', ['company_id' => $user->company->id, 'country_code' => 'ID', 'province' => 'Sulawesi Selatan', 'location' => 'Makassar', 'status' => 'DRAFT']);
        $job = JobPosting::firstOrFail();
        $this->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'country_code' => 'ID', 'province' => 'Jawa Barat', 'location' => 'Bandung'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('job_postings', ['id' => $job->id, 'province' => 'Jawa Barat', 'location' => 'Bandung']);
    }

    public function test_remote_international_and_partial_drafts_remain_supported(): void
    {
        $user = $this->recruiter();
        $this->actingAs($user)->post(route('recruiter.jobs.store'), ['title' => 'Draft awal'])->assertSessionHasNoErrors();
        $job = JobPosting::firstOrFail();
        $this->assertNull($job->location);
        $this->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'country_code' => 'ID', 'province' => '', 'location' => 'Seluruh Indonesia', 'work_arrangement' => 'REMOTE'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('job_postings', ['id' => $job->id, 'location' => 'Seluruh Indonesia', 'province' => null, 'work_arrangement' => 'REMOTE']);
        $this->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'country_code' => 'SG', 'province' => '', 'location' => 'Singapore', 'work_arrangement' => 'ON_SITE'])->assertSessionHasNoErrors();
        $this->get(route('recruiter.jobs.edit', $job))->assertOk()->assertSee('value="Singapore"', false)->assertSee('value="SG"', false);
    }

    public function test_provider_failure_does_not_block_manual_draft_save(): void
    {
        Http::fake(['wilayah.id/*' => Http::failedConnection()]);
        $this->getJson(route('locations.regions'))->assertStatus(503);
        $user = $this->recruiter();
        $this->actingAs($user)->get(route('recruiter.jobs.create'))->assertOk()->assertSee('data-region-manual', false);
        $this->post(route('recruiter.jobs.store'), ['title' => 'Engineer lokasi khusus', 'country_code' => 'ID', 'province' => 'Sulawesi Selatan', 'location' => 'Kawasan Industri Makassar'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('job_postings', ['location' => 'Kawasan Industri Makassar']);
    }

    public function test_location_values_are_validated_and_other_recruiters_cannot_edit(): void
    {
        $user = $this->recruiter();
        $this->actingAs($user)->post(route('recruiter.jobs.store'), ['title' => 'Invalid', 'province' => ['Sulawesi Selatan'], 'location' => str_repeat('a', 151)])->assertSessionHasErrors(['province', 'location']);
        $job = JobPosting::factory()->create(['company_id' => $user->company->id]);
        $this->actingAs($this->recruiter())->put(route('recruiter.jobs.update', $job), ['title' => $job->title, 'province' => 'Jawa Barat', 'location' => 'Bandung'])->assertForbidden();
        $this->assertSame('Makassar', $job->fresh()->location);
    }
}
