<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use UnexpectedValueException;

class CityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:100']);

        return $this->respond(Str::lower(trim($data['q'])));
    }

    public function regions(): JsonResponse
    {
        return $this->respond();
    }

    private function respond(?string $term = null): JsonResponse
    {
        try {
            $cities = Cache::get('regions.cities.v1');
            if (! is_array($cities)) {
                $cities = Cache::lock('regions.cities.load', 60)->block(5, fn (): array => Cache::remember('regions.cities.v1', now()->addDay(), fn (): array => $this->cities()));
            }
        } catch (\Throwable $exception) {
            Log::warning('Region API unavailable', ['type' => $exception::class]);

            return response()->json(['data' => [], 'message' => 'Saran kota belum tersedia. Anda tetap dapat mengetik lokasi secara manual.'], 503);
        }

        return response()->json(['data' => $term === null ? $cities : collect($cities)->filter(fn (array $city): bool => Str::contains(Str::lower($city['name']), $term))->sortBy('name')->take(10)->values()->all()]);
    }

    /**
     * @return list<array{id: string, name: string, value: string, province: string}>
     */
    private function cities(): array
    {
        $baseUrl = rtrim(config('services.wilayah.base_url'), '/');
        $provinces = $this->data(Http::acceptJson()->connectTimeout(2)->timeout(4)->get($baseUrl.'/provinces.json'), '/\A[0-9]{2}\z/');
        $responses = Http::pool(function (Pool $pool) use ($provinces, $baseUrl): void {
            foreach ($provinces as $province) {
                $pool->as($province['code'])->acceptJson()->connectTimeout(2)->timeout(4)->get($baseUrl.'/regencies/'.$province['code'].'.json');
            }
        }, concurrency: 8);
        $cities = [];
        foreach ($provinces as $province) {
            $response = $responses[$province['code']] ?? null;
            if (! $response instanceof Response) {
                throw new UnexpectedValueException('Region request failed.');
            }
            foreach ($this->data($response, '/\A[0-9]{2}\.[0-9]{2}\z/') as $city) {
                if (! str_starts_with($city['code'], $province['code'].'.')) {
                    throw new UnexpectedValueException('Region province mismatch.');
                }
                $cities[] = ['id' => $city['code'], 'name' => $city['name'], 'value' => preg_replace('/\A(?:Kota|Kabupaten)(?: Administrasi)?\s+/iu', '', $city['name']), 'province' => $province['name']];
            }
        }

        return $cities;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function data(Response $response, string $codePattern): array
    {
        $data = $response->throw()->json('data');
        if (Validator::make(['data' => $data], ['data' => 'required|array|list|min:1|max:600', 'data.*.code' => ['required', 'string', 'distinct', 'regex:'.$codePattern], 'data.*.name' => 'required|string|max:100'])->fails()) {
            throw new UnexpectedValueException('Invalid region response.');
        }

        return $data;
    }
}
