@extends('layouts.app')
@section('body-class', 'workspace-body')
@section('content')
<div class="workspace-shell">
    <aside class="workspace-sidebar">
        <div class="workspace-sidebar-heading"><span class="workspace-indicator"></span>{{ request()->routeIs('admin') || (request()->routeIs('purchases.show') && auth()->user()->can('admin')) ? 'ADMIN CONSOLE' : 'RECRUITER SPACE' }}</div>
        <nav class="workspace-nav" aria-label="Menu ruang kerja">
            @if(request()->routeIs('admin') || (request()->routeIs('purchases.show') && auth()->user()->can('admin')))
                @foreach(['verification' => ['building', 'Verifikasi perusahaan'], 'moderation' => ['briefcase', 'Moderasi lowongan'], 'packages' => ['credit', 'Paket publikasi'], 'transactions' => ['clock', 'Transaksi & refund'], 'users' => ['users', 'Pengguna'], 'reports' => ['flag', 'Laporan lowongan'], 'subscribers' => ['mail', 'Subscriber email'], 'audit' => ['grid', 'Audit & pengiriman']] as $id => [$icon, $label])
                    <a href="{{ route('admin', ['section' => $id]) }}" @if((request()->routeIs('admin') && ($section ?? 'verification') === $id) || (request()->routeIs('purchases.show') && $id === 'transactions')) aria-current="page" @endif><x-workspace-icon :name="$icon" /><span>{{ $label }}</span>@if(isset($companies) && $id === 'verification' && $companies->total())<span class="workspace-nav-count">{{ $companies->total() }}</span>@endif</a>
                @endforeach
            @else
                <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-workspace-icon name="grid" /><span>Ringkasan</span></a>
                <a href="{{ route('dashboard') }}#lowongan" @if(request()->routeIs('recruiter.jobs.edit', 'recruiter.jobs.copy', 'recruiter.candidates')) aria-current="page" @endif><x-workspace-icon name="briefcase" /><span>Lowongan saya</span></a>
                <a href="{{ auth()->user()->company ? route('recruiter.jobs.create') : route('recruiter.company') }}" @if(request()->routeIs('recruiter.jobs.create')) aria-current="page" @endif><x-workspace-icon name="users" /><span>Buat lowongan</span></a>
                <a href="{{ route('recruiter.company') }}" @if(request()->routeIs('recruiter.company')) aria-current="page" @endif><x-workspace-icon name="building" /><span>Profil perusahaan</span></a>
                <a href="{{ route('packages') }}" @if(request()->routeIs('packages')) aria-current="page" @endif><x-workspace-icon name="credit" /><span>Paket & kredit</span></a>
                <a href="{{ route('dashboard') }}#transactions" @if(request()->routeIs('purchases.show')) aria-current="page" @endif><x-workspace-icon name="clock" /><span>Riwayat transaksi</span></a>
            @endif
            <a href="{{ route('profile.edit') }}" class="workspace-mobile-link"><x-workspace-icon name="users" /><span>Profil pribadi</span></a>
            <a href="{{ route('onboarding') }}" class="workspace-mobile-link"><x-workspace-icon name="arrow" /><span>Ganti peran</span></a>
        </nav>
        <div class="workspace-sidebar-bottom">
            <a href="{{ route('profile.edit') }}" class="workspace-account"><span class="workspace-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>Kelola profil pribadi ↗</small></span></a>
            <a href="{{ route('onboarding') }}" class="workspace-switch">Ganti peran akun <span aria-hidden="true">→</span></a>
        </div>
    </aside>
    <div class="workspace-main">@yield('workspace-content')</div>
</div>
@endsection
