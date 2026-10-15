<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CitySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wilayah.base_url' => 'https://wilayah.id/api']);
        Http::preventStrayRequests();
    }

    private function fakeRegions(): void
    {
        Http::fake([
            'wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '73', 'name' => 'Sulawesi Selatan'], ['code' => '32', 'name' => 'Jawa Barat']]]),
            'wilayah.id/api/regencies/73.json' => Http::response(['data' => [['code' => '73.71', 'name' => 'Kota Makassar'], ['code' => '73.06', 'name' => 'Kabupaten Gowa']]]),
            'wilayah.id/api/regencies/32.json' => Http::response(['data' => [['code' => '32.73', 'name' => 'Kota Bandung'], ['code' => '32.04', 'name' => 'Kabupaten Bandung']]]),
        ]);
    }

    public function test_city_search_is_public_case_insensitive_and_reuses_cached_catalog(): void
    {
        $this->fakeRegions();
        $this->getJson(route('locations.cities', ['q' => 'mAk']))->assertOk()->assertExactJson(['data' => [['id' => '73.71', 'name' => 'Kota Makassar', 'value' => 'Makassar', 'province' => 'Sulawesi Selatan']]]);
        $this->getJson(route('locations.cities', ['q' => 'bandung']))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Kabupaten Bandung')->assertJsonPath('data.1.name', 'Kota Bandung');
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://wilayah.id/api/regencies/73.json');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '?'));
    }

    public function test_invalid_queries_are_rejected_without_calling_provider(): void
    {
        foreach ([[], ['q' => 'a'], ['q' => ['Makassar']], ['q' => str_repeat('a', 101)]] as $query) {
            $this->getJson(route('locations.cities', $query))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
        $this->getJson(route('locations.cities', ['q' => '  ']))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_no_matching_city_returns_empty_data(): void
    {
        $this->fakeRegions();
        $this->getJson(route('locations.cities', ['q' => 'xyzxyz']))->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_suggestions_are_limited_and_administrative_prefix_is_removed(): void
    {
        $cities = [];
        for ($code = 1; $code <= 15; $code++) {
            $cities[] = ['code' => sprintf('31.%02d', $code), 'name' => 'Kota Administrasi Jakarta '.$code];
        }
        Http::fake([
            'wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '31', 'name' => 'DKI Jakarta']]]),
            'wilayah.id/api/regencies/31.json' => Http::response(['data' => $cities]),
        ]);
        $this->getJson(route('locations.cities', ['q' => 'Jakarta']))->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.value', 'Jakarta 1');
    }

    public function test_provider_outage_preserves_manual_search_and_does_not_cache_failure(): void
    {
        Http::fake(['wilayah.id/*' => Http::failedConnection()]);
        $this->getJson(route('locations.cities', ['q' => 'Makassar']))->assertStatus(503)->assertJsonPath('data', [])->assertJsonPath('message', 'Saran kota belum tersedia. Anda tetap dapat mengetik lokasi secara manual.');
        $this->assertNull(Cache::get('regions.cities.v1'));
        $job = JobPosting::factory()->published()->create(['location' => 'Makassar']);
        $this->get(route('jobs.index', ['location' => 'Makassar']))->assertOk()->assertSee($job->title);
    }

    public function test_incomplete_catalog_is_not_cached_and_next_request_can_retry(): void
    {
        Http::fake([
            'wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '73', 'name' => 'Sulawesi Selatan']]]),
            'wilayah.id/api/regencies/73.json' => Http::sequence()->push(['data' => [['code' => '../../invalid', 'name' => 'Invalid']]])->push(['data' => [['code' => '73.71', 'name' => 'Kota Makassar']]]),
        ]);
        $this->getJson(route('locations.cities', ['q' => 'Makassar']))->assertStatus(503);
        $this->assertNull(Cache::get('regions.cities.v1'));
        $this->getJson(route('locations.cities', ['q' => 'Makassar']))->assertOk()->assertJsonPath('data.0.value', 'Makassar');
    }

    public function test_city_from_another_province_is_rejected(): void
    {
        Http::fake([
            'wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '73', 'name' => 'Sulawesi Selatan']]]),
            'wilayah.id/api/regencies/73.json' => Http::response(['data' => [['code' => '32.73', 'name' => 'Kota Bandung']]]),
        ]);
        $this->getJson(route('locations.cities', ['q' => 'Bandung']))->assertStatus(503);
        $this->assertNull(Cache::get('regions.cities.v1'));
    }

    public function test_both_search_forms_render_combobox_and_selected_value_finds_existing_jobs(): void
    {
        $job = JobPosting::factory()->published()->create(['title' => 'Engineer Makassar', 'location' => 'Makassar']);
        $other = JobPosting::factory()->published()->create(['title' => 'Engineer Surabaya', 'location' => 'Surabaya']);
        $this->get('/')->assertOk()->assertSee('data-city-search', false)->assertSee('role="combobox"', false);
        $this->get(route('jobs.index', ['location' => 'Makassar']))->assertOk()->assertSee('data-city-search', false)->assertSee('value="Makassar"', false)->assertSee($job->title)->assertDontSee($other->title);
        Http::assertNothingSent();
    }
}
