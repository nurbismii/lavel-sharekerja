@props(['job'])
<article class="job-card">
<div class="d-flex justify-content-between gap-3 mb-3">@if($job->company->logo_path)<img class="company-monogram" style="object-fit:contain" src="{{ route('companies.logo',$job->company->slug) }}" alt="" width="47" height="47">@else<div class="company-monogram">{{ mb_substr($job->company->name,0,1) }}</div>@endif<span class="small text-secondary">{{ $job->published_at?->diffForHumans() }}</span></div>
<div class="small company-name mb-2">{{ $job->company->name }} @if($job->company->verification_status === 'VERIFIED')<span class="verified" title="Recruiter terverifikasi">✓</span>@endif</div>
<h3><a href="{{ route('jobs.show',$job) }}">{{ $job->title }}</a></h3>
<p class="text-secondary small mb-3">⌖ {{ $job->location }} · {{ str_replace('_',' ',$job->work_arrangement) }}</p>
<div class="d-flex gap-2 flex-wrap mb-4"><span class="tag">{{ $job->employment_type }}</span><span class="tag">{{ $job->level }}</span></div>
<div class="job-card-bottom"><span>{{ $job->salaryLabel() }}</span><a href="{{ route('jobs.show',$job) }}" aria-label="Lihat {{ $job->title }}">↗</a></div>
</article>

