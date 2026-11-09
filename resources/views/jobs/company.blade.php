@extends('layouts.app')
@section('title', 'Lowongan Kerja '.$company->name.($jobs->currentPage() > 1 ? ' — Halaman '.$jobs->currentPage() : '').' | Sharekerjaid')
@section('description', \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish('Lihat profil dan lowongan kerja di '.$company->name.' ('.$company->location.'). '.$company->description), 160, '…'))
@if($company->logo_path)
@section('image', route('companies.logo', $company->slug))
@endif
@section('content')<div class="container section-space"><div class="panel mb-5"><div class="eyebrow">PERUSAHAAN TERVERIFIKASI</div><h1>{{ $company->name }}</h1><p class="text-secondary">{{ $company->industry }} · {{ $company->location }}</p><div class="prose">{{ $company->description }}</div>@if($company->website)<a class="text-link mt-3 d-inline-block" href="{{ $company->website }}" rel="noopener noreferrer">Website perusahaan ↗</a>@endif</div><h2 class="mb-4">Lowongan aktif</h2><div class="row g-4">@forelse($jobs as $job)<div class="col-md-6 col-lg-4"><x-job-card :job="$job" /></div>@empty<p class="text-secondary">Belum ada lowongan aktif.</p>@endforelse</div><div class="mt-4">{{ $jobs->links() }}</div></div>@endsection

