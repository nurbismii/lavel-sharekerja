<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Http\Requests\JobPostingRequest;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\PublicationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecruiterController extends Controller
{
    public function company(Request $request): View
    {
        return view('recruiter.company', ['company' => $request->user()->company]);
    }

    public function storeCompany(CompanyRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        if ($company) {
            abort_unless(in_array($company->verification_status, ['NOT_SUBMITTED', 'REJECTED'], true) && $company->status === 'ACTIVE', 403, 'Profil sedang ditinjau atau terverifikasi. Hubungi admin untuk koreksi.');
        }
        $data = $request->safe()->except('logo');
        $newLogo = null;
        if ($request->hasFile('logo')) {
            abort_unless(function_exists('imagecreatefromstring'), 503, 'Pemrosesan gambar belum tersedia.');
            $image = imagecreatefromstring($request->file('logo')->get());
            abort_unless($image, 422, 'Gambar tidak dapat dibaca.');
            $resized = imagescale($image, min(400, imagesx($image)));
            ob_start();
            imagepng($resized);
            $bytes = ob_get_clean();
            $newLogo = 'company-logos/'.Str::uuid().'.png';
            abort_unless(Storage::disk('public')->put($newLogo, $bytes), 503, 'Logo gagal disimpan.');
            $data['logo_path'] = $newLogo;
        }
        try {
            DB::transaction(function () use ($request, $data, $company) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $current = Company::where('owner_user_id', $request->user()->id)->lockForUpdate()->first();
                abort_if($current && (! in_array($current->verification_status, ['NOT_SUBMITTED', 'REJECTED'], true) || $current->status !== 'ACTIVE'), 409);
                Company::updateOrCreate(['owner_user_id' => $request->user()->id], $data + ['slug' => $company?->slug ?? Str::slug($data['name']).'-'.Str::lower(Str::random(6)), 'verification_status' => 'PENDING', 'review_reason' => null]);
            });
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk('public')->delete($newLogo);
            } throw $exception;
        }
        if ($newLogo && $company?->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        return back()->with('success', 'Profil perusahaan dikirim untuk verifikasi.');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->company, 403, 'Lengkapi perusahaan terlebih dahulu.');

        return view('recruiter.job-form', ['job' => new JobPosting]);
    }

    public function edit(JobPosting $job): View
    {
        Gate::authorize('manage-job', $job);
        abort_unless(in_array($job->status, [JobPosting::DRAFT, JobPosting::REJECTED], true), 403);

        return view('recruiter.job-form', compact('job'));
    }

    public function store(JobPostingRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company && $company->status === 'ACTIVE', 403);
        $data = $this->data($request);
        $job = JobPosting::create($data + ['company_id' => $company->id, 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(8))]);

        return to_route('recruiter.jobs.edit', $job)->with('success', 'Draft tersimpan. Periksa kembali sebelum mengajukan publikasi.');
    }

    public function update(JobPostingRequest $request, JobPosting $job): RedirectResponse
    {
        Gate::authorize('manage-job', $job);
        DB::transaction(function () use ($request, $job) {
            $locked = JobPosting::whereKey($job->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, [JobPosting::DRAFT, JobPosting::REJECTED], true), 409);
            $locked->update($this->data($request));
        });

        return back()->with('success', 'Draft diperbarui.');
    }

    public function transition(Request $request, JobPosting $job, PublicationService $service): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:submit,withdraw,close']);
        $service->transition($job, $data['action'], $request->user());

        return back()->with('success', 'Status lowongan diperbarui.');
    }

    private function data(JobPostingRequest $request): array
    {
        $data = $request->validated();
        $data['country_code'] = $data['country_code'] ?? 'ID';
        $data['deadline'] = empty($data['deadline']) ? null : Carbon::parse($data['deadline'])->utc();
        $data['vacancies'] = $data['vacancies'] ?? 1;
        $data['salary_visible'] = $data['salary_visible'] ?? false;
        $data['salary_period'] = $data['salary_period'] ?? 'bulan';
        if (($data['method'] ?? null) !== JobPosting::EXTERNAL) {
            $data['external_url'] = null;
        }

        return $data;
    }
}
