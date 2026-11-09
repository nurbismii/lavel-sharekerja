@extends(auth()->user()->can('recruiter') ? 'layouts.workspace' : 'layouts.app')
@section('title', 'Dashboard — Sharekerjaid')
@section(auth()->user()->can('recruiter') ? 'workspace-content' : 'content')
<div class="container section-space">
@can('recruiter')
<div class="workspace-page-heading"><div><div class="eyebrow">RINGKASAN REKRUTMEN</div><h1>Halo, {{ auth()->user()->name }}.</h1><p>Pantau lowongan dan temukan talenta berikutnya untuk perusahaanmu.</p></div><a class="btn btn-primary" href="{{ $company ? route('recruiter.jobs.create') : route('recruiter.company') }}">{{ $company ? '+ Buat lowongan' : 'Lengkapi perusahaan' }}</a></div>
<div class="row g-3 mb-4 workspace-stats">
<div class="col-md-4"><div class="stat-card stat-card-accent"><div class="stat-heading"><span>Kredit tersedia</span><x-workspace-icon name="credit" /></div><strong>{{ auth()->user()->availableCredits() }}</strong><small>{{ auth()->user()->reserved_credits }} kredit direservasi / ditahan</small><a class="stat-link" href="{{ route('packages') }}">Tambah kredit →</a></div></div>
<div class="col-md-4"><div class="stat-card"><div class="stat-heading"><span>Tayangan lowongan</span><x-workspace-icon name="eye" /></div><strong>{{ number_format($metrics['view'] ?? 0,0,',','.') }}</strong><small>Estimasi interaksi seluruh lowongan</small></div></div>
<div class="col-md-4"><div class="stat-card"><div class="stat-heading"><span>Klik lamaran eksternal</span><x-workspace-icon name="arrow" /></div><strong>{{ number_format($metrics['apply'] ?? 0,0,',','.') }}</strong><small>Klik menuju website, bukan lamaran selesai</small></div></div>
</div>
@if(!$company)
<div class="workspace-callout mb-4"><span class="callout-icon"><x-workspace-icon name="building" /></span><div><h2>Mulai dari profil perusahaan</h2><p>Lengkapi identitas perusahaan untuk membuat draft lowongan. Verifikasi diperlukan sebelum publikasi.</p></div><a href="{{ route('recruiter.company') }}" class="btn btn-primary">Lengkapi profil →</a></div>
@else
<div class="company-overview mb-4"><div class="company-monogram">{{ mb_substr($company->name,0,1) }}</div><div class="flex-grow-1"><strong>{{ $company->name }}</strong><div class="small text-secondary">{{ $company->industry }} · {{ $company->location }}</div></div><x-status :value="$company->verification_status" /><a href="{{ route('recruiter.company') }}" class="text-link">Lihat profil →</a></div>
@if($company->verification_status !== 'VERIFIED')<div class="alert alert-info mb-4">{{ match($company->verification_status) { 'PENDING' => 'Profil perusahaan sedang ditinjau. Kamu tetap dapat menyiapkan draft lowongan.', 'REJECTED' => 'Profil perusahaan perlu diperbaiki. Buka profil untuk melihat catatan admin dan kirim ulang.', default => 'Ajukan profil perusahaan untuk verifikasi agar lowongan dapat dipublikasikan.' } }}</div>@endif
@endif
<section id="lowongan" class="panel mb-4">
<div class="workspace-panel-heading"><div><h2>Lowongan perusahaan</h2><p>Kelola draft, pantau publikasi, dan tinjau kandidat.</p></div><span class="count-badge">{{ $jobs?->total() ?? 0 }} lowongan</span></div>
@if($jobs?->isNotEmpty())
<div class="table-responsive"><table class="table align-middle workspace-table"><thead><tr><th scope="col">Posisi</th><th scope="col">Status</th><th scope="col">Kandidat</th><th scope="col">Tindakan</th></tr></thead><tbody>
@foreach($jobs as $job)
<tr>
    <td><strong>{{ $job->title }}</strong><div class="small text-secondary">{{ $job->location ?? 'Lokasi belum diisi' }} · {{ $job->method === 'INTERNAL' ? 'Lamaran internal' : 'Lamaran eksternal' }}</div>@if($job->review_reason)<div class="review-note">{{ $job->review_reason }}</div>@endif</td>
    <td><x-status :value="$job->status" /></td>
    <td>
        @if($job->method === 'INTERNAL')
            <a class="candidate-link" href="{{ route('recruiter.candidates', $job) }}">{{ $job->applications_count }} kandidat <span aria-hidden="true">↗</span></a>
            @if($job->new_applications_count)
                <a class="status-pill status-info d-table mt-1" href="{{ route('recruiter.candidates', ['job' => $job, 'status' => 'NEW']) }}">{{ $job->new_applications_count }} lamaran baru</a>
            @endif
        @else
            <span class="small text-secondary">Website perusahaan</span>
        @endif
    </td>
    <td><div class="table-actions">
        @if(in_array($job->status, ['DRAFT', 'REJECTED']))
            <a class="btn btn-sm btn-outline-primary" href="{{ route('recruiter.jobs.edit', $job) }}">Edit / ajukan</a>
        @elseif($job->status === 'WAITING_APPROVAL')
            <form method="post" action="{{ route('recruiter.jobs.status', $job) }}">@csrf<input type="hidden" name="action" value="withdraw"><button class="btn btn-sm btn-outline-primary">Tarik pengajuan</button></form>
        @elseif($job->status === 'PUBLISHED')
            <a class="btn btn-sm btn-outline-primary" href="{{ route('jobs.show', $job) }}">Lihat lowongan ↗</a>
            <form method="post" action="{{ route('recruiter.jobs.status', $job) }}">@csrf<input type="hidden" name="action" value="close"><button class="btn btn-sm btn-outline-danger">Tutup lowongan</button></form>
        @elseif(in_array($job->status, ['CLOSED', 'EXPIRED']))
            <a class="btn btn-sm btn-outline-primary" href="{{ route('recruiter.jobs.copy', $job) }}">Salin sebagai draft</a>
        @else
            <span class="small text-secondary">{{ $job->status === 'SUSPENDED' ? 'Hubungi admin untuk peninjauan' : 'Publikasi telah selesai' }}</span>
        @endif
    </div></td>
</tr>
@endforeach
</tbody></table></div>
@else
<div class="workspace-empty"><span class="empty-icon"><x-workspace-icon name="briefcase" /></span><h3>Siap membuka peluang baru?</h3><p>Buat draft lowongan pertama. Kredit baru direservasi saat diajukan untuk review.</p><a class="btn btn-primary" href="{{ $company ? route('recruiter.jobs.create') : route('recruiter.company') }}">{{ $company ? '+ Buat lowongan pertama' : 'Lengkapi profil perusahaan' }}</a></div>
@endif
@if($jobs){{ $jobs->fragment('lowongan')->links() }}@endif
</section>
<section id="transactions" class="panel mb-4">
<div class="workspace-panel-heading"><div><h2>Transaksi dan kredit</h2><p>Pantau pembayaran paket dan perubahan saldo kredit.</p></div><a href="{{ route('packages') }}" class="btn btn-sm btn-outline-primary">Beli kredit</a></div>
<div class="table-responsive"><table class="table align-middle workspace-table"><thead><tr><th scope="col">Paket</th><th scope="col">Total pembayaran</th><th scope="col">Status</th><th scope="col">Invoice</th></tr></thead><tbody>@forelse($purchases as $purchase)<tr><td><strong>{{ $purchase->package_name }}</strong><small class="d-block text-secondary">{{ $purchase->created_at->format('d M Y') }}</small></td><td>Rp {{ number_format($purchase->amount,0,',','.') }}</td><td><x-status :value="$purchase->status" /></td><td><a class="text-link" href="{{ route('purchases.show',$purchase) }}">Lihat invoice →</a></td></tr>@empty<tr><td colspan="4" class="table-empty">Belum ada transaksi. Pembelian paket akan tercatat di sini.</td></tr>@endforelse</tbody></table></div>
{{ $purchases->fragment('transactions')->links() }}
<details class="credit-history"><summary>Riwayat perubahan kredit terbaru</summary>@forelse($entries as $entry)<div class="credit-history-row"><span class="credit-amount {{ $entry->amount > 0 ? 'text-success' : 'text-danger' }}">{{ $entry->amount > 0 ? '+' : '' }}{{ $entry->amount }}</span><div>{{ $entry->reason }}<small class="d-block text-secondary">{{ $entry->created_at->timezone(config('sharekerja.timezone'))->format('d M Y, H:i') }}</small></div></div>@empty<p class="small text-secondary mt-3">Belum ada perubahan saldo kredit.</p>@endforelse</details>
</section>
@else
<div class="section-heading"><div><div class="eyebrow">RUANG KERJAMU</div><h1>Halo, {{ auth()->user()->name }}.</h1><p class="text-secondary">Kelola peluang dan langkah berikutnya dari sini.</p></div><a class="btn btn-outline-primary" href="{{ route('onboarding') }}">Ganti peran</a></div>
@can('admin')<div class="panel mb-4"><h2 class="h4">Ruang kerja admin</h2><p class="text-secondary">Lanjutkan verifikasi, moderasi, dan pengelolaan platform.</p><a href="{{ route('admin') }}" class="btn btn-primary">Buka dashboard admin →</a></div>@endcan
@endcan
@can('seeker')<a class="text-link d-inline-block mb-3" href="{{ route('profile.edit') }}">Edit profil pribadi →</a>
<div class="d-flex gap-2 mb-4"><a href="{{ route('jobs.index') }}" class="btn btn-primary">Cari lowongan →</a><a href="{{ route('subscription.edit') }}" class="btn btn-outline-primary">Preferensi email</a></div>
<section id="lamaran" class="panel mb-4"><h2 class="h4">Lamaran saya</h2><div class="table-responsive"><table class="table"><thead><tr><th>Posisi</th><th>Perusahaan</th><th>Status</th><th>Dokumen</th></tr></thead><tbody>@forelse($applications as $application)<tr><td>{{ $application->job->title }}</td><td>{{ $application->job->company->name }}</td><td><x-status :value="$application->status" /><div class="small text-secondary">{{ $application->updated_at->format('d M Y') }}</div></td><td><a href="{{ route('applications.cv',$application) }}">CV terkirim</a></td></tr>@empty<tr><td colspan="4" class="py-4 text-secondary">Belum ada lamaran internal. Klik external apply tidak masuk daftar ini.</td></tr>@endforelse</tbody></table></div>{{ $applications->fragment('lamaran')->links() }}</section>
<h2 class="h4 mb-3">Lowongan tersimpan</h2><div class="row g-4">@forelse($saved as $job)<div class="col-md-6 col-lg-4"><div class="panel"><h3 class="h5">{{ $job->title }}</h3><p>{{ $job->company->name }}</p>@if($job->isLive())<a href="{{ route('jobs.show',$job) }}" class="text-link">Lihat lowongan →</a>@else<span class="status-pill">Tidak tersedia / sudah ditutup</span>@endif<form method="post" action="{{ route('jobs.save',$job) }}" class="mt-3">@csrf<button class="btn btn-sm btn-light">Hapus simpanan</button></form></div></div>@empty<p class="text-secondary">Simpan lowongan yang menarik untuk dilihat kembali.</p>@endforelse</div><div class="mt-4">{{ $saved->links() }}</div>
@endcan
</div>
@endsection

