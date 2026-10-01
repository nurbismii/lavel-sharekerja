<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function create(JobPosting $job): View
    {
        abort_unless($job->isLive() && $job->method === JobPosting::INTERNAL, 404);

        return view('applications.create', compact('job'));
    }

    public function store(ApplicationRequest $request, JobPosting $job): RedirectResponse
    {
        abort_unless($job->isLive() && $job->method === JobPosting::INTERNAL, 404);
        if (JobApplication::where('job_posting_id', $job->id)->where('user_id', $request->user()->id)->exists()) {
            return to_route('dashboard')->with('success', 'Lamaran Anda sudah tersimpan.');
        }
        $path = $request->file('cv')->store('cvs', 'local');
        abort_unless($path, 503, 'Dokumen gagal disimpan. Silakan coba lagi.');
        try {
            DB::transaction(function () use ($request, $job, $path) {
                $locked = JobPosting::whereKey($job->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->isLive() && $locked->method === JobPosting::INTERNAL, 409, 'Lowongan sudah ditutup.');
                if (JobApplication::where('job_posting_id', $job->id)->where('user_id', $request->user()->id)->exists()) {
                    Storage::disk('local')->delete($path);

                    return;
                }
                JobApplication::create($request->safe()->except('cv') + ['user_id' => $request->user()->id, 'job_posting_id' => $job->id, 'email' => $request->user()->email, 'cv_path' => $path]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return to_route('dashboard')->with('success', 'Lamaran terkirim. Pantau statusnya di dashboard.');
    }

    public function candidates(JobPosting $job): View
    {
        Gate::authorize('manage-job', $job);

        return view('applications.candidates', ['job' => $job, 'applications' => $job->applications()->latest()->paginate(20)]);
    }

    public function status(Request $request, JobApplication $application): RedirectResponse
    {
        Gate::authorize('manage-job', $application->job);
        $data = $request->validate(['status' => 'required|in:REVIEWED,SHORTLISTED,REJECTED,HIRED']);
        DB::transaction(function () use ($request, $application, $data) {
            $application = JobApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $allowed = ['NEW' => ['REVIEWED', 'SHORTLISTED', 'REJECTED'], 'REVIEWED' => ['SHORTLISTED', 'REJECTED'], 'SHORTLISTED' => ['HIRED', 'REJECTED']];
            abort_unless(in_array($data['status'], $allowed[$application->status] ?? [], true), 422, 'Transisi status tidak valid.');
            $application->update($data);
            DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => 'candidate.status', 'subject' => 'application:'.$application->id, 'reason' => $data['status'], 'created_at' => now()]);
        });

        return back()->with('success', 'Status kandidat diperbarui.');
    }

    public function download(Request $request, JobApplication $application): StreamedResponse
    {
        Gate::authorize('view-cv', $application);
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => 'cv.download', 'subject' => 'application:'.$application->id, 'created_at' => now()]);

        return Storage::disk('local')->download($application->cv_path, 'CV-kandidat-'.$application->id.'.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
