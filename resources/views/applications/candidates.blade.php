@extends('layouts.workspace')
@section('title', 'Kandidat — '.$job->title.' — Sharekerjaid')
@section('workspace-content')
<div class="container section-space">
    <a href="{{ route('dashboard') }}#lowongan" class="text-link">← Kembali ke lowongan</a>
    <div class="workspace-page-heading mt-3"><div><div class="eyebrow">KELOLA LAMARAN</div><h1>Kandidat</h1><p>{{ $job->title }} · {{ $job->company->name }}</p></div><x-status :value="$job->status" /></div>
    <section class="panel">
        <div class="workspace-panel-heading"><div><h2>Lamaran masuk</h2><p>Tinjau CV dan perbarui status untuk melanjutkan seleksi.</p></div><span class="count-badge">{{ $applications->total() }} kandidat</span></div>
        <form method="get" action="{{ route('recruiter.candidates', $job) }}" class="row align-items-end mb-3">
            <div class="col-md-5 mb-3">
                <label for="candidate-search" class="form-label">Nama kandidat</label>
                <input id="candidate-search" name="q" type="search" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="150">
            </div>
            <div class="col-md-4 mb-3">
                <label for="candidate-status" class="form-label">Status lamaran</label>
                <select id="candidate-status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach($statuses as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-center gap-2 mb-3">
                <button class="btn btn-primary">Cari</button>
                @if(isset($filters['q']) || isset($filters['status']))
                    <a class="text-link" href="{{ route('recruiter.candidates', $job) }}">Hapus filter</a>
                @endif
            </div>
        </form>
        @if($applications->isNotEmpty())
        <div class="table-responsive"><table class="table align-middle workspace-table"><thead><tr><th scope="col">Kandidat</th><th scope="col">Kontak</th><th scope="col">Status</th><th scope="col">Dokumen</th><th scope="col">Perbarui status</th></tr></thead><tbody>
        @foreach($applications as $application)
        <tr><td><strong>{{ $application->name }}</strong><small class="d-block text-secondary">Melamar {{ $application->created_at->format('d M Y') }}</small></td><td>{{ $application->email }}<small class="d-block text-secondary">{{ $application->phone }}</small></td><td><x-status :value="$application->status" /></td><td>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.cv',$application) }}">Unduh CV ↗</a>
            @if(str_starts_with(strtolower($application->portfolio ?? ''), 'https://'))
                <a class="text-link d-block mt-2" href="{{ $application->portfolio }}" target="_blank" rel="noopener noreferrer">Portofolio / LinkedIn ↗</a>
            @endif
        </td><td>
            @if(!in_array($application->status,['HIRED','REJECTED']))
            <form method="post" action="{{ route('applications.status',$application) }}" class="table-action-form">@csrf
                <select name="status" aria-label="Status kandidat {{ $application->name }}" class="form-select form-select-sm">@foreach(match($application->status){'NEW'=>['REVIEWED','SHORTLISTED','REJECTED'],'REVIEWED'=>['SHORTLISTED','REJECTED'],default=>['HIRED','REJECTED']} as $status)<option value="{{ $status }}">{{ match($status) { 'REVIEWED'=>'Sudah ditinjau', 'SHORTLISTED'=>'Kandidat pilihan', 'HIRED'=>'Diterima', 'REJECTED'=>'Ditolak' } }}</option>@endforeach</select>
                <button class="btn btn-sm btn-primary">Simpan</button>
            </form>
            @else<span class="small text-secondary">Seleksi selesai</span>@endif
        </td></tr>
        @endforeach
        </tbody></table></div>
        @elseif(isset($filters['q']) || isset($filters['status']))
        <div class="workspace-empty"><h3>Kandidat tidak ditemukan</h3><p>Coba nama atau status lamaran lain.</p></div>
        @else
        <div class="workspace-empty"><span class="empty-icon"><x-workspace-icon name="users" /></span><h3>Belum ada lamaran masuk</h3><p>Kandidat yang melamar melalui Sharekerjaid akan muncul di sini.</p>@if($job->isLive())<a href="{{ route('jobs.show',$job) }}" class="btn btn-outline-primary">Lihat halaman lowongan ↗</a>@endif</div>
        @endif
        {{ $applications->links() }}
    </section>
</div>
@endsection
