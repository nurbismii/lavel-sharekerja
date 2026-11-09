<?php

namespace App\Http\Controllers;

use App\Models\CreditEntry;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->roles()->exists()) {
            return to_route('onboarding');
        }
        $company = $user->company;
        $jobs = $company?->jobs()->withCount([
            'applications',
            'applications as new_applications_count' => fn ($query) => $query->where('status', 'NEW'),
        ])->latest()->paginate(15, ['*'], 'jobs_page');

        return view('dashboard', ['company' => $company, 'jobs' => $jobs, 'applications' => JobApplication::with('job.company')->where('user_id', $user->id)->latest()->paginate(15, ['*'], 'applications_page'), 'saved' => JobPosting::with('company')->whereIn('id', DB::table('job_saves')->where('user_id', $user->id)->select('job_posting_id'))->paginate(15, ['*'], 'saved_page'), 'purchases' => Purchase::where('user_id', $user->id)->latest()->paginate(15, ['*'], 'purchases_page'), 'entries' => CreditEntry::where('user_id', $user->id)->latest()->limit(20)->get(), 'metrics' => $company ? DB::table('job_daily_metrics')->whereIn('job_posting_id', $company->jobs()->select('id'))->selectRaw('event, SUM(count) as total')->groupBy('event')->pluck('total', 'event') : collect()]);
    }
}
