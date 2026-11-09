<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php($siteTitle = 'Sharekerjaid — Lowongan Kerja dan Rekrutmen')
@php($siteDescription = 'Temukan lowongan kerja dari perusahaan terverifikasi dan kelola rekrutmen di Sharekerjaid.')
<title>@yield('title', $siteTitle)</title>
<meta name="description" content="@yield('description', $siteDescription)">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 64x64">
@if($indexable ?? false)
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="id_ID">
<meta property="og:site_name" content="Sharekerjaid">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:title" content="@yield('title', $siteTitle)">
<meta property="og:description" content="@yield('description', $siteDescription)">
<meta property="og:image" content="@yield('image', asset('images/sharekerjaid-logo.jpeg'))">
@else
<meta name="robots" content="noindex, follow">
@endif
@vite(['resources/css/app.css','resources/js/app.js'])
@stack('head')
</head>
<body class="@yield('body-class')">
<a href="#main" class="visually-hidden-focusable skip-link">Lewati ke konten</a>
<header class="site-header"><div class="container header-inner">
<a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/sharekerjaid-logo.jpeg') }}" alt="" width="52" height="52"><span>Sharekerja<span class="text-orange">id</span><small>INFO · TIPS · KARIER · HR</small></span></a>
<nav aria-label="Navigasi utama" class="main-nav"><a href="{{ route('jobs.index') }}" class="{{ request()->routeIs('jobs.*') ? 'active' : '' }}">Cari lowongan</a>@auth<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard', 'recruiter.*', 'packages') ? 'active' : '' }}">Dashboard</a>@can('admin')<a href="{{ route('admin') }}" class="{{ request()->routeIs('admin') ? 'active' : '' }}">Admin</a>@endcan @else<a href="{{ route('login') }}?intent=recruiter">Untuk perusahaan ↗</a>@endauth</nav>
<div class="header-actions">@auth<form action="{{ route('logout') }}" method="post">@csrf<button class="btn btn-outline-primary btn-sm">Keluar</button></form>@else<a href="{{ route('login') }}" class="btn btn-primary">Masuk / Daftar <span aria-hidden="true">→</span></a>@endauth</div>
</div></header>
<main id="main">
<div class="container">@if(session('success'))<div class="alert alert-success mt-4" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger mt-4" role="alert" tabindex="-1"><strong>Periksa kembali data Anda.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif</div>
@yield('content')
</main>
<footer class="site-footer"><div class="container d-flex justify-content-between flex-wrap gap-3"><div><strong>Sharekerja<span class="text-orange">id</span></strong><p class="small mb-0 mt-2">Peluang yang tepat. Langkah yang berarti.</p></div><div class="small">© {{ date('Y') }} Sharekerjaid<br><span>Info · Tips · Karier · HR</span></div></div></footer>
<div id="feedback" class="feedback" role="status" hidden></div>
</body></html>

