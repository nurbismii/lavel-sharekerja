@extends('layouts.app')
@section('content')
<div class="container section-space narrow"><a href="{{ route('dashboard') }}" class="text-link">← Dashboard</a><div class="section-heading mt-3"><div><div class="eyebrow">JANGKAU TALENTA BARU</div><h1>{{ $job->exists ? 'Edit draft lowongan' : 'Buat lowongan' }}</h1></div>@if($job->exists)<x-status :value="$job->status" />@endif</div>
@if($job->review_reason)<div class="alert alert-info">{{ $job->review_reason }}</div>@endif
<form action="{{ $job->exists ? route('recruiter.jobs.update',$job) : route('recruiter.jobs.store') }}" method="post" class="panel" data-wizard>@csrf @if($job->exists)@method('PUT')@endif
<div class="wizard-progress mb-4" aria-live="polite">Lengkapi informasi lowongan</div>
<button type="submit" formnovalidate class="btn btn-sm btn-outline-primary mb-4">Simpan draft sementara</button>
<fieldset data-step><legend>1. Informasi dasar</legend><x-field name="title" label="Judul posisi" :value="$job->title" required maxlength="150" /><div class="row"><div class="col-md-6"><x-field name="category" label="Kategori" :value="$job->category" :options="array_combine(config('sharekerja.categories'),config('sharekerja.categories'))" required /></div><div class="col-md-6"><x-field name="employment_type" label="Tipe pekerjaan" :value="$job->employment_type" :options="array_combine(config('sharekerja.employment_types'),config('sharekerja.employment_types'))" required /></div><div class="col-md-6"><x-field name="level" label="Level" :value="$job->level" :options="array_combine(config('sharekerja.levels'),config('sharekerja.levels'))" required /></div><div class="col-md-6"><x-field name="vacancies" label="Jumlah kebutuhan" type="number" :value="$job->vacancies ?? 1" min="1" required /></div></div></fieldset>
<fieldset data-step data-job-regions data-url="{{ route('locations.regions') }}">
    <legend>2. Lokasi</legend>
    <x-field name="country_code" label="Negara (kode ISO, contoh ID untuk Indonesia)" :value="$job->country_code ?? 'ID'" maxlength="2" pattern="[A-Z]{2}" required />
    <p class="small text-secondary" role="status" aria-live="polite" data-region-status hidden></p>
    <div data-region-dropdown hidden>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="region-province">Provinsi <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="region-province" class="form-select" data-region-province disabled>
                    <option value="">Pilih provinsi</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="region-city">Kota / kabupaten <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="region-city" class="form-select" data-region-city disabled>
                    <option value="">Pilih provinsi terlebih dahulu</option>
                </select>
            </div>
        </div>
    </div>
    <div class="form-check mb-3" data-region-mode hidden>
        <input id="region-manual" class="form-check-input" type="checkbox" data-region-manual-toggle>
        <label class="form-check-label small" for="region-manual">Isi lokasi secara manual untuk remote, luar negeri, atau lokasi khusus</label>
    </div>
    <div data-region-manual>
        <x-field name="province" label="Provinsi (opsional untuk remote)" :value="$job->province" maxlength="100" />
        <x-field name="location" label="Kota / cakupan kandidat untuk remote" :value="$job->location" maxlength="150" required />
    </div>
    <x-field name="work_arrangement" label="Cara kerja" :value="$job->work_arrangement" :options="['ON_SITE'=>'On-site','HYBRID'=>'Hybrid','REMOTE'=>'Remote']" required />
</fieldset>
<fieldset data-step><legend>3. Kompensasi</legend><div class="row"><div class="col-md-6"><x-field name="salary_min" label="Minimum (Rp, opsional)" type="number" :value="$job->salary_min" min="0" /></div><div class="col-md-6"><x-field name="salary_max" label="Maksimum (Rp, opsional)" type="number" :value="$job->salary_max" min="0" /></div></div><x-field name="salary_period" label="Periode" :value="$job->salary_period" :options="['bulan'=>'Per bulan','tahun'=>'Per tahun','jam'=>'Per jam']" /><x-field name="salary_visible" label="Tampilkan gaji?" :value="(int)$job->salary_visible" :options="['0'=>'Tidak, sembunyikan','1'=>'Ya, tampilkan']" /></fieldset>
<fieldset data-step><legend>4. Detail pekerjaan</legend><x-field name="description" label="Deskripsi posisi" type="textarea" :value="$job->description" required minlength="30" maxlength="20000" /><x-field name="responsibilities" label="Tanggung jawab" type="textarea" :value="$job->responsibilities" /><x-field name="qualifications" label="Kualifikasi" type="textarea" :value="$job->qualifications" required minlength="10" /><div class="row"><div class="col-md-6"><x-field name="education" label="Pendidikan" :value="$job->education" :options="[''=>'Tidak ditentukan']+array_combine(config('sharekerja.education_levels'),config('sharekerja.education_levels'))" /></div><div class="col-md-6"><x-field name="experience" label="Pengalaman" :value="$job->experience" /></div></div><x-field name="skills" label="Keahlian (pisahkan dengan koma)" :value="$job->skills" /><x-field name="benefits" label="Benefit" type="textarea" :value="$job->benefits" /></fieldset>
<fieldset data-step><legend>5. Metode rekrutmen</legend><x-field name="method" label="Kandidat melamar melalui" :value="$job->method" :options="['EXTERNAL'=>'Website recruiter','INTERNAL'=>'Sharekerjaid']" /><x-field name="external_url" label="URL lamaran external (HTTPS)" type="url" :value="$job->external_url" placeholder="https://career.perusahaan.com/posisi" /><p class="small text-secondary">Untuk internal, kandidat mengirim data dan CV melalui platform. Biaya kedua metode sama.</p></fieldset>
<fieldset data-step><legend>6. Deadline</legend><x-field name="deadline" :label="'Batas lamaran ('.config('sharekerja.timezone').')'" type="datetime-local" :value="$job->deadline?->timezone(config('sharekerja.timezone'))->format('Y-m-d\TH:i')" required /><div class="alert alert-info">Masa tayang {{ config('sharekerja.publication_days') }} hari sejak publikasi, atau sampai deadline jika lebih awal.</div></fieldset>
<fieldset data-step><legend>7. Periksa dan simpan</legend><p>Draft tidak menggunakan kredit. Setelah disimpan, ajukan untuk review admin dengan reservasi 1 kredit.</p><dl data-preview class="job-facts"></dl><button type="submit" class="btn btn-primary">Simpan draft</button></fieldset>
<div class="wizard-controls mt-4" hidden><button type="button" class="btn btn-outline-primary" data-previous>Kembali</button><button type="button" class="btn btn-primary" data-next>Lanjut →</button></div>
</form>
@if($job->exists)<form action="{{ route('recruiter.jobs.status',$job) }}" method="post" class="panel mt-3">@csrf<input name="action" value="submit" type="hidden"><h2 class="h5">Siap menjangkau kandidat?</h2><p class="text-secondary">Pastikan perubahan sudah disimpan. Satu kredit akan direservasi hingga hasil review.</p><button class="btn btn-primary">Ajukan publikasi · 1 kredit</button></form>@endif
</div>
@endsection

