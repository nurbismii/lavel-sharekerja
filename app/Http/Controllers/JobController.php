<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobPostingRequest;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['education' => ['nullable', 'string', Rule::in(config('sharekerja.education_levels'))], 'experience' => 'nullable|string|max:100', 'posted' => 'nullable|integer|in:1,7,30', 'q' => 'nullable|string|max:100', 'location' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100', 'employment_type' => 'nullable|string|max:100', 'work_arrangement' => 'nullable|in:ON_SITE,HYBRID,REMOTE', 'level' => 'nullable|string|max:100', 'salary' => 'nullable|integer|min:0|max:1000000000']);
        $query = JobPosting::live()->with('company');
        if ($filters['q'] ?? null) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('skills', 'like', $term)->orWhereHas('company', fn ($c) => $c->where('name', 'like', $term)));
        }
        if ($filters['location'] ?? null) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }
        foreach (['education', 'experience', 'category', 'employment_type', 'work_arrangement', 'level'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['posted'] ?? null) {
            $query->where('published_at', '>=', now()->subDays((int) $filters['posted']));
        }
        if ($filters['salary'] ?? null) {
            $query->where('salary_visible', true)->where('salary_min', '>=', $filters['salary']);
        }

        $jobs = $query->latest('published_at')->paginate(20)->withQueryString();
        $isHome = $request->routeIs('home');

        return view('jobs.index', [
            'jobs' => $jobs,
            'isHome' => $isHome,
            'companies' => $isHome ? Company::publishable()->latest()->limit(6)->get() : collect(),
            'indexable' => ! array_filter($filters) && $jobs->currentPage() <= $jobs->lastPage(),
            'canonical' => route($isHome ? 'home' : 'jobs.index', $jobs->currentPage() > 1 ? ['page' => $jobs->currentPage()] : []),
        ]);
    }

    public function show(Request $request, JobPosting $job): View
    {
        abort_unless(in_array($job->status, [JobPosting::PUBLISHED, JobPosting::CLOSED, JobPosting::EXPIRED], true) && $job->company->canPublish(), 404);
        $this->metric($request, $job, 'view');
        $user = $request->user();
        $application = $job->method === JobPosting::INTERNAL && $user?->can('seeker')
            ? $job->applications()->where('user_id', $user->id)->first(['status', 'updated_at'])
            : null;

        return view('jobs.show', [
            'job' => $job,
            'application' => $application,
            'saved' => $user ? DB::table('job_saves')->where('user_id', $user->id)->where('job_posting_id', $job->id)->exists() : false,
            'indexable' => $job->isLive(),
            'canonical' => route('jobs.show', $job),
        ]);
    }

    public function company(Company $company): View
    {
        abort_unless($company->canPublish(), 404);
        $jobs = $company->jobs()->live()->with('company')->latest('published_at')->paginate(20);

        return view('jobs.company', [
            'company' => $company,
            'jobs' => $jobs,
            'indexable' => $jobs->currentPage() <= $jobs->lastPage(),
            'canonical' => route('companies.show', ['company' => $company->slug] + ($jobs->currentPage() > 1 ? ['page' => $jobs->currentPage()] : [])),
        ]);
    }

    public function external(Request $request, JobPosting $job): RedirectResponse
    {
        abort_unless($job->isLive() && $job->method === JobPosting::EXTERNAL && JobPostingRequest::safeExternalUrl($job->external_url ?? ''), 404);
        $this->metric($request, $job, 'apply');

        return redirect()->away($job->external_url)->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, JobPosting $job): RedirectResponse
    {
        abort_unless($request->user()->can('seeker'), 403);
        $key = ['user_id' => $request->user()->id, 'job_posting_id' => $job->id];
        if (DB::table('job_saves')->where($key)->exists()) {
            DB::table('job_saves')->where($key)->delete();
        } else {
            abort_unless($job->isLive(), 404);
            DB::table('job_saves')->insertOrIgnore($key + ['created_at' => now()]);
        }

        return back()->with('success', 'Daftar tersimpan diperbarui.');
    }

    public function report(Request $request, JobPosting $job): RedirectResponse
    {
        abort_unless($request->user()->can('seeker') && $job->isLive(), 403);
        $data = $request->validate(['reason' => 'required|in:Scam,Perusahaan palsu,Biaya rekrutmen,Link mencurigakan,Informasi salah,Lowongan ditutup,Konten tidak pantas', 'details' => 'nullable|string|max:2000']);
        JobReport::create($data + ['user_id' => $request->user()->id, 'job_posting_id' => $job->id]);

        return back()->with('success', 'Laporan diterima dan akan diperiksa admin.');
    }

    public function share(Request $request, JobPosting $job): Response
    {
        abort_unless($job->isLive(), 404);
        $this->metric($request, $job, 'share');

        return response()->noContent();
    }

    private function metric(Request $request, JobPosting $job, string $event): void
    {
        if (preg_match('/bot|crawler|spider|preview/i', $request->userAgent() ?? '')) {
            return;
        }
        $key = 'metric.'.$job->id.'.'.$event;
        if ($request->session()->get($key, 0) > time() - 1800) {
            return;
        }
        try {
            $utm = $request->query('utm_source');
            $source = is_string($utm) && in_array(strtolower($utm), ['google', 'email', 'whatsapp', 'linkedin', 'facebook', 'threads', 'telegram'], true) ? strtolower($utm) : null;
            $host = strtolower(parse_url((string) $request->header('referer'), PHP_URL_HOST) ?? '');
            if (! $source && $host && $host !== $request->getHost()) {
                $source = 'other';
                foreach (['google' => 'google.com', 'linkedin' => 'linkedin.com', 'facebook' => 'facebook.com', 'threads' => 'threads.net', 'telegram' => 't.me', 'whatsapp' => 'whatsapp.com'] as $channel => $domain) {
                    if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                        $source = $channel;
                        break;
                    }
                }
            }
            if ($event === 'view') {
                $request->session()->put('source.'.$job->id, $source ?? 'direct');
            }
            $fields = ['job_posting_id' => $job->id, 'day' => now()->toDateString(), 'event' => $event, 'source' => $source ?? $request->session()->get('source.'.$job->id, 'direct')];
            DB::table('job_daily_metrics')->insertOrIgnore($fields + ['count' => 0]);
            DB::table('job_daily_metrics')->where($fields)->increment('count');
            $request->session()->put($key, time());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
