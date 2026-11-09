@extends('layouts.app')
@section('title', 'Lowongan '.$job->title.' di '.$job->company->name.' | Sharekerjaid')
@section('description', \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish('Lowongan '.$job->title.' di '.$job->company->name.' ('.$job->location.'). '.$job->description), 160, '…'))
@if($job->company->logo_path)
@section('image', route('companies.logo', $job->company->slug))
@endif
@push('head')
@if($indexable)<script type="application/ld+json">{!! json_encode($job->structuredData(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>@endif
@endpush
@section('content')
<div class="container section-space px-3"><a class="text-link" href="{{ route('jobs.index') }}">← Kembali ke lowongan</a><div class="row g-4 mt-2"><div class="col-lg-8"><article class="panel">
<div class="d-flex gap-3 align-items-center mb-4"><div class="company-monogram">{{ mb_substr($job->company->name,0,1) }}</div><div><a href="{{ route('companies.show',$job->company->slug) }}" class="text-link">{{ $job->company->name }}</a><div class="small text-secondary">✓ Recruiter terverifikasi</div></div></div>
<h1 class="detail-title">{{ $job->title }}</h1><p class="text-secondary">{{ $job->location }}, {{ $job->country_code }} · {{ str_replace('_',' ',$job->work_arrangement) }}</p><div class="d-flex flex-wrap gap-2"><span class="tag">{{ $job->employment_type }}</span><span class="tag">{{ $job->level }}</span><span class="tag">{{ $job->category }}</span></div><hr class="my-4">
@foreach(['description'=>'Tentang posisi ini','responsibilities'=>'Tanggung jawab','qualifications'=>'Kualifikasi','benefits'=>'Benefit','education'=>'Pendidikan','experience'=>'Pengalaman'] as $field=>$heading)@if($job->$field)<section class="mb-4"><h2 class="h5">{{ $heading }}</h2><div class="prose">{{ $job->$field }}</div></section>@endif @endforeach
@if($job->skills)<h2 class="h5">Keahlian</h2><p>{{ $job->skills }}</p>@endif
</article></div><aside class="col-lg-4"><div class="panel sticky-panel"><h2 class="h5">Ringkasan pekerjaan</h2><dl class="job-facts"><dt>Kompensasi</dt><dd>{{ $job->salaryLabel() }}</dd><dt>Kebutuhan</dt><dd>{{ $job->vacancies }} posisi</dd><dt>Deadline ({{ config('sharekerja.timezone') }})</dt><dd>{{ $job->deadline->timezone(config('sharekerja.timezone'))->format('d M Y, H:i') }}</dd><dt>Metode lamaran</dt><dd>{{ $job->method === 'EXTERNAL' ? 'Website recruiter' : 'Melalui Sharekerjaid' }}</dd></dl>
@if($application)
<div class="mb-3">
    <strong class="d-block mb-1">Lamaran sudah dikirim</strong>
    <x-status :value="$application->status" />
    <p class="small text-secondary mt-2 mb-0">Diperbarui {{ $application->updated_at->timezone(config('sharekerja.timezone'))->format('d M Y, H:i') }} ({{ config('sharekerja.timezone') }})</p>
</div>
<a class="btn btn-primary w-100 mb-2" href="{{ route('dashboard') }}#lamaran">Lihat lamaran saya →</a>
@if(!$indexable)<p class="small text-secondary">Lowongan ini sudah ditutup.</p>@endif
@elseif($indexable)<a class="btn btn-primary w-100 mb-2" href="{{ $job->method === 'EXTERNAL' ? route('jobs.external',$job) : route('applications.create',$job) }}">Lamar sekarang ↗</a>
@if($job->method === 'EXTERNAL')<p class="small text-secondary">Kamu akan menuju {{ parse_url($job->external_url,PHP_URL_HOST) }}.</p>@endif
@else<div class="alert alert-secondary">Lowongan ini sudah ditutup.</div>@endif
@can('seeker')<form method="post" action="{{ route('jobs.save',$job) }}">@csrf<button class="btn btn-outline-primary w-100">{{ $saved ? 'Hapus dari tersimpan' : 'Simpan lowongan' }}</button></form>@endcan
<button type="button" class="btn btn-light w-100 mt-2" data-share="{{ route('jobs.show',$job) }}" data-track="{{ route('jobs.share',$job) }}">Salin tautan lowongan</button>
<button type="button" class="btn btn-light w-100 mt-2" data-native-share="{{ route('jobs.show',$job) }}" data-title="{{ $job->title }}" data-track="{{ route('jobs.share',$job) }}" hidden>Bagikan lewat aplikasi ↗</button><p class="small text-secondary mt-4 mb-0">Rekrutmen yang aman tidak meminta biaya dari kandidat.</p>
@can('seeker')<details class="mt-3"><summary>Laporkan lowongan</summary><form method="post" action="{{ route('jobs.report',$job) }}" class="mt-3">@csrf<x-field name="reason" label="Alasan" :options="array_combine(['Scam','Perusahaan palsu','Biaya rekrutmen','Link mencurigakan','Informasi salah','Lowongan ditutup','Konten tidak pantas'],['Scam','Perusahaan palsu','Biaya rekrutmen','Link mencurigakan','Informasi salah','Lowongan ditutup','Konten tidak pantas'])" required /><x-field name="details" label="Penjelasan" type="textarea" maxlength="2000" /><button class="btn btn-outline-danger">Kirim laporan</button></form></details>@endcan
</div></aside></div></div>
@endsection

