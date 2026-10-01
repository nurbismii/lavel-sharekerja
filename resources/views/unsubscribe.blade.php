@extends('layouts.app')
@section('content')<div class="container section-space narrow"><div class="panel"><h1>Berhenti menerima email?</h1><p>Akun dan lamaranmu tetap tersimpan. Kamu dapat berlangganan kembali dari pengaturan preferensi.</p><form method="post" action="{{ route('unsubscribe.stop',$subscription->token) }}">@csrf<button class="btn btn-primary">Ya, hentikan langganan</button></form></div></div>@endsection

