@extends(auth()->user()->can('admin') || auth()->user()->can('recruiter') ? 'layouts.workspace' : 'layouts.app')
@section('title', 'Invoice '.$purchase->package_name.' — Sharekerjaid')
@section(auth()->user()->can('admin') || auth()->user()->can('recruiter') ? 'workspace-content' : 'content')<div class="container section-space narrow"><a href="{{ auth()->user()->can('admin') ? route('admin', ['section' => 'transactions']) : route('dashboard').'#transactions' }}" class="text-link">← Kembali ke transaksi</a><div class="panel mt-4"><div class="eyebrow">INVOICE SHAREKERJAID</div><h1 class="h2">{{ $purchase->package_name }}</h1><p class="small text-secondary text-break">{{ $purchase->reference }}</p><x-status :value="$purchase->status" /><dl class="job-facts mt-4"><dt>Jumlah kredit</dt><dd>{{ $purchase->credits }}</dd><dt>Total pembayaran</dt><dd class="h3">Rp {{ number_format($purchase->amount,0,',','.') }}</dd><dt>Refund</dt><dd>{{ $purchase->refund_status }}</dd></dl>
@if($purchase->status === 'PAID')
    <div class="alert alert-success">Pembayaran terverifikasi. Riwayat kredit tersedia di dashboard.</div>
@elseif($purchase->provider !== 'midtrans')
    <div class="alert alert-info">Transaksi ini dibuat sebelum perpindahan ke Midtrans. Hubungi admin dengan nomor referensi di atas untuk pemeriksaan.</div>
@else
    @unless(config('services.midtrans.is_production'))
        <div class="alert alert-info">Mode Sandbox Midtrans: gunakan metode pembayaran uji coba.</div>
    @endunless
    @if($purchase->status === 'PENDING')
        @if($purchase->checkout_url)
            <a href="{{ $purchase->checkout_url }}" class="btn btn-primary" rel="noopener">Lanjutkan pembayaran di Midtrans ↗</a>
        @else
            <div class="alert alert-info">Checkout belum tersedia. Periksa status untuk mencoba kembali. Jika tetap gagal, hubungi admin dengan nomor referensi di atas.</div>
        @endif
    @elseif($purchase->status === 'EXPIRED')
        <div class="alert alert-warning">Pembayaran kedaluwarsa. Periksa status jika Anda sudah membayar, atau pilih paket untuk membuat transaksi baru.</div>
    @elseif($purchase->status === 'FAILED')
        <div class="alert alert-warning">Pembayaran gagal atau dibatalkan. Periksa status jika Anda sudah membayar sebelum membuat transaksi baru.</div>
    @endif
    <form action="{{ route('purchases.refresh',$purchase) }}" method="post" class="mt-3">
        @csrf
        <button class="btn btn-outline-primary">Periksa status</button>
    </form>
    <p class="small text-secondary mt-3">Kredit hanya ditambahkan setelah pembayaran diverifikasi di server.</p>
@endif
</div></div>@endsection

