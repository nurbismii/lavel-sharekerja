@extends('layouts.app')
@section('content')
<div class="container section-space narrow"><a class="text-link" href="{{ route('dashboard') }}">← Dashboard</a><h1 class="mt-4">Profil saya</h1><form class="panel" action="{{ route('profile.update') }}" method="post">@csrf<x-field name="name" label="Nama lengkap" :value="auth()->user()->name" required /><p class="small text-secondary">Email Google: {{ auth()->user()->email }}</p><x-field name="phone" label="Nomor telepon (opsional)" :value="auth()->user()->phone" maxlength="30" /><x-field name="location" label="Lokasi (opsional)" :value="auth()->user()->location" maxlength="150" /><button class="btn btn-primary">Simpan profil</button></form></div>
@endsection
