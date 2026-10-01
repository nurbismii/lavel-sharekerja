@extends('layouts.app')
@section('title', $isHome ? 'Sharekerjaid — Langkah baru untuk kariermu' : 'Cari Lowongan — Sharekerjaid')
@section('content')
@if($isHome)
<section class="hero"><div class="container"><div class="row align-items-center g-5"><div class="col-lg-7">
<div class="eyebrow"><span></span> PELUANG BARU, MASA DEPAN BARU</div>
<h1>Langkah berikutnya<br>untuk <span>kariermu.</span></h1>
<p class="hero-description">Temukan pekerjaan yang sesuai dengan potensimu.<br class="d-none d-md-inline"> Bertemu perusahaan, mulai cerita baru.</p>
<form action="{{ route('jobs.index') }}" class="search-bar" method="get"><label class="search-input"><span aria-hidden="true">⌕</span><input name="q" aria-label="Posisi, perusahaan atau skill" placeholder="Posisi, perusahaan, atau skill"></label><label class="search-input location-input"><span aria-hidden="true">⌖</span><input name="location" aria-label="Lokasi" placeholder="Kota atau remote"></label><button class="btn btn-primary">Cari lowongan →</button></form>
<div class="popular-search"><span>Jelajahi:</span>@foreach(['Teknologi','Marketing','Desain'] as $category)<a href="{{ route('jobs.index',['category'=>$category]) }}">{{ $category }} ↗</a>@endforeach</div>
</div><div class="col-lg-5"><div class="career-visual">
<div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
<div class="visual-label"><span class="dot"></span> ARAH BARU KARIERMU</div>
<div class="career-card"><div class="career-icon">↗</div><div class="small text-secondary mb-2">Ruang untuk berkembang</div><h2>Potensimu,<br>peluang barumu.</h2><div class="visual-progress"><span></span></div><div class="d-flex justify-content-between small"><span>Mulai langkahmu</span><strong>Hari ini</strong></div></div>
<div class="floating-note"><span>✓</span><div><strong>Perusahaan terverifikasi</strong><small>Melamar dengan lebih percaya diri</small></div></div>
<div class="visual-bottom">BERTUMBUH BERSAMA SHAREKERJAID</div>
</div></div></div></div></section>
<div class="benefit-strip"><div class="container d-flex flex-wrap justify-content-between gap-3"><span>✓ Recruiter melalui verifikasi</span><span>↗ Lamar langsung atau melalui platform</span><span>✉ Info lowongan sesuai preferensi</span></div></div>
@endif
<section class="section-space"><div class="container">
<div class="section-heading"><div><div class="eyebrow">TEMUKAN YANG SESUAI</div><h2>{{ $isHome ? 'Peluang untuk langkah barumu' : 'Cari peluang kerjamu' }}</h2><p class="text-secondary">{{ $isHome ? 'Jelajahi lowongan terbaru dari perusahaan yang sedang mencari talenta.' : 'Sesuaikan pencarian dengan keahlian dan tujuan kariermu.' }}</p></div>@if($isHome)<a class="text-link" href="{{ route('jobs.index') }}">Semua lowongan →</a>@endif</div>
@if(!$isHome)
<form method="get" class="filter-panel mb-4"><div class="row g-3">
<div class="col-md-4"><x-field name="q" label="Posisi, perusahaan atau skill" :value="request('q')" placeholder="Apa yang ingin kamu kerjakan?" /></div><div class="col-md-3"><x-field name="location" label="Lokasi" :value="request('location')" placeholder="Kota atau cakupan remote" /></div>
<div class="col-md-3"><x-field name="category" label="Kategori" :value="request('category')" :options="[''=>'Semua kategori']+array_combine(config('sharekerja.categories'),config('sharekerja.categories'))" /></div><div class="col-md-2 d-flex align-items-end pb-3"><button class="btn btn-primary w-100">Cari →</button></div>
</div><details><summary>Filter lainnya</summary><div class="row g-3 mt-1"><div class="col-md-3"><x-field name="employment_type" label="Tipe pekerjaan" :value="request('employment_type')" :options="[''=>'Semua tipe']+array_combine(config('sharekerja.employment_types'),config('sharekerja.employment_types'))" /></div><div class="col-md-3"><x-field name="work_arrangement" label="Cara kerja" :value="request('work_arrangement')" :options="[''=>'Semua','ON_SITE'=>'On-site','HYBRID'=>'Hybrid','REMOTE'=>'Remote']" /></div><div class="col-md-3"><x-field name="level" label="Level" :value="request('level')" :options="[''=>'Semua level']+array_combine(config('sharekerja.levels'),config('sharekerja.levels'))" /></div><div class="col-md-3"><x-field name="salary" label="Gaji minimum (Rp)" type="number" :value="request('salary')" min="0" /></div></div><div class="row g-3 mt-1"><div class="col-md-4"><x-field name="education" label="Pendidikan" :value="request('education')" /></div><div class="col-md-4"><x-field name="experience" label="Pengalaman" :value="request('experience')" /></div><div class="col-md-4"><x-field name="posted" label="Tanggal publikasi" :value="request('posted')" :options="[''=>'Kapan saja','1'=>'24 jam terakhir','7'=>'7 hari terakhir','30'=>'30 hari terakhir']" /></div></div><small class="text-secondary">Filter gaji hanya mencakup lowongan dengan gaji yang ditampilkan.</small></details></form>
<p class="small text-secondary">{{ $jobs->total() }} lowongan ditemukan</p>
@endif
<div class="row g-4">@forelse($jobs as $job)<div class="col-md-6 col-xl-4"><x-job-card :job="$job" /></div>@empty<div class="col-12"><div class="empty-state"><div class="empty-icon">⌕</div><h3>{{ request()->hasAny(['q','category','location']) ? 'Belum ada lowongan yang cocok' : 'Peluang baru sedang dipersiapkan' }}</h3><p>Lowongan yang telah disetujui akan muncul di sini.<br>Atur preferensi agar tidak melewatkan kesempatan berikutnya.</p><a class="btn btn-outline-primary" href="{{ auth()->check() ? route('subscription.edit') : route('login') }}">Atur info lowongan <span aria-hidden="true">→</span></a></div></div>@endforelse</div>
<div class="mt-4">{{ $jobs->links() }}</div>
</div></section>
@if($isHome)
@if($companies->isNotEmpty())<section class="container mb-5"><h2 class="mb-4">Kenali perusahaan di Sharekerjaid</h2><div class="row g-3">@foreach($companies as $company)<div class="col-md-4"><a class="panel d-block h-100" href="{{ route('companies.show',$company->slug) }}"><h3 class="h5">{{ $company->name }}</h3><span class="small text-secondary">{{ $company->industry }} · {{ $company->location }}</span></a></div>@endforeach</div></section>@endif<section class="container mb-5"><div class="recruiter-banner"><div><div class="eyebrow">UNTUK RECRUITER</div><h2>Talenta yang tepat dimulai<br>dari jangkauan yang lebih luas.</h2><p>Publikasikan lowongan. Jangkau kandidat.<br>Kelola rekrutmen dengan cara yang sesuai untuk timmu.</p></div><a href="{{ auth()->check() ? route('onboarding') : route('login') }}" class="btn btn-orange">Mulai merekrut ↗</a></div></section>
@endif
@endsection

