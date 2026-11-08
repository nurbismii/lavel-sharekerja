@props(['value'])
@php($labels=['DRAFT'=>'Draft','WAITING_APPROVAL'=>'Menunggu review','PUBLISHED'=>'Terbit','REJECTED'=>'Ditolak','CLOSED'=>'Ditutup','EXPIRED'=>'Berakhir','SUSPENDED'=>'Ditangguhkan','PENDING'=>'Menunggu','VERIFIED'=>'Terverifikasi','NOT_SUBMITTED'=>'Belum diajukan','ACTIVE'=>'Aktif','PAID'=>'Dibayar','NEW'=>'Baru','REVIEWED'=>'Ditinjau','SHORTLISTED'=>'Kandidat pilihan','HIRED'=>'Diterima','FAILED'=>'Gagal','OPEN'=>'Terbuka','IN_REVIEW'=>'Sedang ditinjau','RESOLVED'=>'Selesai','DISMISSED'=>'Laporan ditolak'])
@php($tone = match($value) {
    'PUBLISHED','VERIFIED','PAID','HIRED','ACTIVE','RESOLVED' => 'status-good',
    'REJECTED','SUSPENDED','FAILED' => 'status-danger',
    'NEW','REVIEWED','SHORTLISTED','IN_REVIEW' => 'status-info',
    'DRAFT','CLOSED','EXPIRED','NOT_SUBMITTED','DISMISSED' => 'status-neutral',
    default => '',
})
<span class="status-pill {{ $tone }}">{{ $labels[$value] ?? $value }}</span>

