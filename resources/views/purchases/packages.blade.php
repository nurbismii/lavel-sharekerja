@extends('layouts.workspace')
@section('title', 'Paket & kredit — Sharekerjaid')
@section('workspace-content')
<div class="container section-space">
    <div class="workspace-page-heading"><div><div class="eyebrow">INVESTASI UNTUK TALENTA BERIKUTNYA</div><h1>Paket publikasi</h1><p>Pilih kebutuhan rekrutmenmu. Kredit tidak kedaluwarsa.</p></div><div class="credit-balance"><x-workspace-icon name="credit" /><span>Saldo tersedia<strong>{{ auth()->user()->availableCredits() }} kredit</strong></span></div></div>
    <div class="package-benefits mb-4"><span>✓ 1 kredit untuk 1 publikasi</span><span>✓ {{ config('sharekerja.publication_days') }} hari masa tayang</span><span>✓ Lamaran internal atau eksternal</span></div>
    @if(!auth()->user()->company?->canPublish())<div class="alert alert-info">Pembelian kredit tersedia setelah perusahaan aktif dan terverifikasi. <a href="{{ route('recruiter.company') }}">Periksa profil perusahaan →</a></div>@endif
    <div class="row g-4">
    @forelse($packages as $package)
        <div class="col-md-6 col-xl-4"><article class="panel package-card h-100"><span class="package-icon"><x-workspace-icon name="briefcase" /></span><h2>{{ $package->name }}</h2><p class="text-secondary">{{ $package->description }}</p><div class="package-price">Rp {{ number_format($package->price,0,',','.') }}</div><p class="package-credits">{{ $package->credits }} kredit publikasi</p><div class="package-checks"><p>✓ Kredit tanpa batas kedaluwarsa</p><p>✓ Rekrut melalui metode pilihanmu</p></div>@if(auth()->user()->company?->canPublish())<form action="{{ route('purchases.store',$package) }}" method="post" class="mt-auto">@csrf<button class="btn btn-primary w-100">Beli melalui Midtrans →</button></form>@else<a href="{{ route('recruiter.company') }}" class="btn btn-outline-primary mt-auto">Periksa profil perusahaan →</a>@endif</article></div>
    @empty
        <div class="col-12"><div class="panel workspace-empty"><span class="empty-icon"><x-workspace-icon name="credit" /></span><h2 class="h4">Paket belum tersedia</h2><p>Harga dan paket sedang disiapkan oleh pengelola. Tidak ada pembayaran yang akan diproses saat ini.</p><a href="{{ route('dashboard') }}" class="btn btn-outline-primary">Kembali ke dashboard</a></div></div>
    @endforelse
    </div>
</div>
@endsection
