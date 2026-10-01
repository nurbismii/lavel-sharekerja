@props(['value'])
@php($labels=['DRAFT'=>'Draft','WAITING_APPROVAL'=>'Menunggu review','PUBLISHED'=>'Terbit','REJECTED'=>'Ditolak','CLOSED'=>'Ditutup','EXPIRED'=>'Berakhir','SUSPENDED'=>'Ditangguhkan','PENDING'=>'Menunggu','VERIFIED'=>'Terverifikasi','NOT_SUBMITTED'=>'Belum diajukan','ACTIVE'=>'Aktif','PAID'=>'Dibayar','NEW'=>'Baru','REVIEWED'=>'Ditinjau','SHORTLISTED'=>'Shortlist','HIRED'=>'Diterima','FAILED'=>'Gagal'])
<span class="status-pill {{ in_array($value,['PUBLISHED','VERIFIED','PAID','HIRED','ACTIVE']) ? 'status-good' : '' }}">{{ $labels[$value] ?? $value }}</span>

