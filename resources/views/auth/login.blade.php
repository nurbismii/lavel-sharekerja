@extends('layouts.app')
@section('title','Masuk — Sharekerjaid')
@section('content')
<div class="container section-space"><div class="auth-card"><span class="eyebrow">SATU AKUN, BANYAK PELUANG</span><h1>Langkah barumu<br>dimulai di sini.</h1><p class="text-secondary mb-4">Masuk untuk menyimpan lowongan, melamar, atau menemukan talenta untuk perusahaanmu.</p><a href="{{ route('auth.google') }}" class="btn btn-outline-primary w-100 py-3"><strong class="me-2">G</strong> Lanjutkan dengan Google</a><p class="small text-secondary mt-4 mb-0">Tidak perlu membuat password baru.<br>Langganan email hanya aktif jika kamu memilihnya.</p></div></div>
@endsection

