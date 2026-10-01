# PRD ShareKerja — Job Distribution & Recruitment Platform

Versi: **4.2** · Tanggal: **27 September 2026**  
Status: **Acuan implementasi MVP**  
Arsitektur: **Satu aplikasi Laravel, domain bisnis terpisah seperlunya**  
Deployment awal: **Shared hosting / cPanel**

Dokumen ini menggantikan v4.1. Riwayat: [v4.0 asli](PRD-v4.0-original.md) dan [v4.1 arsip](PRD-v4.1-archive.md). Arsip bukan persyaratan tambahan; jika berbeda, v4.2 berlaku. Harga dan vendor belum diputuskan. Nilai default di dokumen ini adalah keputusan produk awal, bukan klaim kapasitas atau kepatuhan hukum.

## 1. Tujuan produk

Recruiter membayar untuk mempublikasikan lowongan dan menjangkau kandidat melalui pencarian, halaman publik, tautan berbagi, serta email opt-in. Kandidat dapat melamar melalui website recruiter atau langsung di ShareKerja.

**Publish → Distribute → Reach → Track → Apply.**

Produk bukan ATS penuh. Distribusi MVP tidak mencakup posting otomatis ke media sosial atau job board lain. Keberhasilan MVP dinilai dari alur pembayaran sampai publikasi, external apply, internal apply, dan email yang benar-benar berfungsi.

## 2. Keputusan penyederhanaan MVP

| Keputusan | Aturan yang berlaku |
|---|---|
| Kepemilikan | Satu recruiter memiliki maksimal satu perusahaan; satu perusahaan memiliki satu pemilik |
| Produk berbayar | Satu layanan publikasi, mendukung metode external maupun internal |
| Kredit | Satu jenis kredit; satu publikasi memakai satu kredit; kredit pembelian tidak kedaluwarsa pada MVP |
| Paket | Berbeda pada nama, harga dan jumlah kredit; semua kredit memberikan hak publikasi yang sama |
| Masa tayang | Satu durasi untuk kedua metode, default awal 30 hari; deadline dapat menutup lebih awal |
| Reservasi | Kredit diamankan saat submit, dikonsumsi saat publish, dilepas saat batal/ditolak |
| Refund | Admin menangani melalui gateway dan mencatat hasil; tanpa refund otomatis atau parsial di UI |
| Email | Database queue, daftar penerima unik, proses bertahap dan rekonsiliasi sederhana |
| Struktur kode | Laravel standar; service/action hanya untuk logic yang memang membutuhkan pemisahan |

Biaya satu kredit, masa tayang dan kredit tanpa kedaluwarsa ditampilkan sebelum pembelian. Durasi ditetapkan melalui konfigurasi sebelum penjualan pertama, lalu tidak boleh diubah retroaktif untuk kredit yang sudah dijual. Jika bisnis kelak memerlukan hak berbeda atau expiry, buat desain versi entitlement dan migrasi terlebih dahulu, bukan sekadar mengganti setting.

Penyederhanaan ini menghapus kebutuhan lot kredit, kredit per produk, expiry kredit, pemilihan entitlement, dan reservasi dengan timeout otomatis. Reservasi yang lama dapat dibatalkan recruiter atau diselesaikan admin; dashboard menampilkan usia pengajuan.

## 3. Ruang lingkup

**Wajib MVP:** Google login, multi-role, profil dan verifikasi perusahaan, paket dan pembayaran, kredit, lowongan external/internal, moderasi, search/filter, halaman perusahaan, save/share/report, lamaran dan CV privat, subscription/preferensi, email antrean, analytics dasar, SEO, dashboard tiga role, audit serta backup.

**Ditunda:** Featured Job, Boost, perpanjangan berbayar, promo, invoice PDF, recruiter teams, multi-company, screening questions, rekomendasi personal, company follow, scheduled publishing, bulk import, ATS integration, enterprise API dan advanced analytics.

**Di luar target produk awal:** interview scheduling, psikotes, MCU, offering/kontrak, onboarding karyawan, AI screening, mobile app dan microservices.

Fitur ditunda tidak boleh muncul sebagai layanan yang sudah bisa dibeli. Scope MVP tetap mencakup external dan internal recruitment; tahapan implementasi hanya menentukan urutan pengerjaan.

## 4. Akun, login, dan izin

| Pengguna | Akses |
|---|---|
| Pengunjung | Browse/search, detail publik, company page, share dan external apply |
| Job Seeker | Profil, saved jobs, internal apply, riwayat lamaran, subscription, preferences, report |
| Recruiter | Perusahaan sendiri, verifikasi, paket/kredit, lowongan sendiri, kandidat dan analytics sendiri |
| Super Admin | Pengguna, verifikasi, moderasi, paket, transaksi, laporan, settings dan audit |

- Google menjadi satu-satunya provider login MVP. Login pertama menawarkan Job Seeker atau Recruiter; onboarding role publik kedua dapat ditambahkan pada akun yang sama.
- Gunakan relasi multi-role. Super Admin hanya diberikan melalui proses administratif yang diaudit; admin aktif terakhir tidak boleh dinonaktifkan tanpa pengganti.
- Identifikasi akun Google melalui ID provider unik. Jangan menggabungkan akun otomatis hanya karena email sama; konflik perlu pembuktian kepemilikan.
- Validasi OAuth state dan identitas melalui library yang sesuai. Jika menggunakan ID token, validasi issuer, audience, expiry dan nonce yang relevan. Tidak menyimpan password Google atau token yang tidak diperlukan.
- Regenerasi session setelah login; logout membatalkan session. Error login tidak membocorkan token atau detail internal.
- Status akun ACTIVE/SUSPENDED diperiksa di server, termasuk untuk session yang sudah ada. Role pilihan dashboard tidak menggantikan authorization.
- Akses admin produksi memerlukan MFA aplikasi atau kontrol tambahan setara yang sudah diverifikasi; login Google biasa bukan bukti MFA.
- Profil seeker: nama/email akun, telepon/lokasi dan preferensi opsional. CV profil opsional. Telepon/CV diperlukan saat internal apply.
- Subscription default nonaktif. Registrasi bukan persetujuan email lowongan.

## 5. Perusahaan dan verifikasi recruiter

Profil recruiter: nama, email, telepon, jabatan, LinkedIn opsional. Perusahaan: nama, logo, industri, email kontak, alamat/lokasi, deskripsi dan website opsional. Dokumen verifikasi hanya dikumpulkan bila dibutuhkan dan disimpan privat.

Status verifikasi: NOT_SUBMITTED → PENDING → VERIFIED atau REJECTED. Pengajuan ditolak dapat diperbaiki dan diajukan ulang; alasan dan riwayat disimpan. Status perusahaan ACTIVE/SUSPENDED terpisah dari hasil verifikasi.

- Recruiter boleh membuat draft sebelum verifikasi, tetapi membeli kredit dan submit moderasi memerlukan perusahaan verified serta akun/perusahaan aktif.
- Semua lowongan terikat perusahaan milik recruiter. Invoice, kredit dan kandidat tidak dapat diakses recruiter lain meski ID diketahui.
- Badge hanya tampil selama verifikasi valid dan akun/perusahaan aktif. Nama sama atau email perusahaan saja tidak cukup sebagai bukti kepemilikan.
- Perubahan identitas penting memerlukan tinjauan admin. Transfer pemilik tidak tersedia sebagai fitur mandiri MVP.
- Suspend akun/perusahaan segera memblokir akses publik/apply lowongan terkait. Pencabutan suspend tidak otomatis menerbitkan ulang lowongan; admin meninjau ulang. Status lowongan harus tetap mencerminkan kebutuhan review setelah akun diaktifkan kembali.

## 6. Pembuatan dan pengelolaan lowongan

Wizard dengan simpan draft dan validasi setiap langkah:

1. Judul, perusahaan, kategori, employment type, level dan jumlah kebutuhan.
2. Provinsi/kota, lokasi kerja dan ON_SITE/HYBRID/REMOTE; remote wajib menjelaskan cakupan lokasi kandidat.
3. Gaji minimum/maksimum opsional, IDR, periode gaji dan visibilitas.
4. Deskripsi, tanggung jawab, kualifikasi, pendidikan, pengalaman, skills dan benefit.
5. EXTERNAL dengan URL tujuan atau INTERNAL dengan form standar.
6. Deadline dan informasi durasi publikasi.
7. Preview, informasi satu kredit dan submit.

Jumlah kebutuhan positif; minimum gaji tidak melebihi maksimum; deadline di masa depan. Semua data divalidasi ulang saat submit dan approval. Gaji tersembunyi tidak boleh muncul pada HTML, API publik, structured data, atau hasil filter gaji. Rich text harus disanitasi.

Waktu disimpan UTC. Zona waktu tampilan default Asia/Makassar dan terlihat pada input deadline. Masa tayang dimulai pada publikasi pertama. Waktu penutupan adalah yang paling awal antara deadline dan akhir masa tayang.

### Status dan transisi

| Dari → ke | Efek |
|---|---|
| DRAFT / REJECTED → WAITING_APPROVAL | Data valid, verified, saldo tersedia; reservasi satu kredit |
| WAITING_APPROVAL → DRAFT | Recruiter menarik pengajuan; reservasi dilepas |
| WAITING_APPROVAL → REJECTED | Admin memberi alasan; reservasi dilepas |
| WAITING_APPROVAL → PUBLISHED | Approval, konsumsi reservasi dan publikasi dilakukan atomik |
| PUBLISHED → CLOSED | Recruiter/admin menutup; tidak otomatis mengembalikan kredit |
| PUBLISHED → EXPIRED | Deadline/masa tayang habis |
| WAITING_APPROVAL / PUBLISHED → SUSPENDED | Admin memberi alasan; reservasi yang belum dikonsumsi dilepas |
| SUSPENDED → DRAFT | Admin mengizinkan perbaikan untuk lowongan yang belum pernah terbit |
| SUSPENDED → PUBLISHED | Admin mengizinkan pemulihan lowongan yang pernah terbit dan masih berlaku; tidak memotong kredit lagi |
| SUSPENDED → EXPIRED / CLOSED | Masa tayang habis atau admin menutup |

- Saldo kurang: lowongan tetap draft dengan CTA membeli kredit. Tidak perlu status WAITING_PAYMENT pada lowongan.
- Pengajuan yang deadlinenya sudah lewat dikembalikan ke draft dengan alasan dan reservasi dilepas; tidak boleh dipublikasikan.
- Lowongan pending dikunci; tarik ke draft untuk mengedit. Lowongan published tidak dapat diedit langsung oleh recruiter pada MVP.
- Koreksi kecil dapat ditangani admin dengan audit; perubahan posisi, perusahaan, metode, atau tujuan external membutuhkan lowongan baru dan moderasi. UI menjelaskan biaya publikasi baru sebelum penutupan.
- Closed/expired terminal; dapat disalin sebagai draft baru. Pemulihan suspended tidak mengulang durasi atau email publikasi.
- Akses listing/apply memeriksa status, deadline, masa tayang dan akun/perusahaan setiap request; keterlambatan cron tidak boleh membuka lamaran kedaluwarsa.

## 7. Lamaran external dan internal

### External

Detail → Lamar → `/jobs/{slug}/apply` → pemeriksaan lowongan → pencatatan Apply Click → redirect 302 ke URL tersimpan yang sudah dimoderasi. Login tidak wajib.

Tujuan wajib HTTPS dengan host valid, tanpa credential, scheme berbahaya, localhost atau alamat privat/reserved yang teridentifikasi. Tolak shortener yang menyembunyikan tujuan. Tampilkan domain tujuan kepada pengguna. Jangan menerima parameter redirect bebas atau mengambil preview URL di server. Jika kelak melakukan fetch URL, tambahkan perlindungan SSRF.

Endpoint memakai rate limit dan tidak dicache secara publik. Kegagalan analytics tidak menghalangi redirect valid. Platform tidak mengklaim mengetahui lamaran selesai pada website recruiter.

### Internal

Login → form → CV → submit → konfirmasi tersimpan → dashboard kandidat recruiter.

- Wajib nama, email akun, telepon dan CV PDF; portfolio, LinkedIn, pendidikan, pengalaman dan ekspektasi gaji opsional. Batas awal PDF 5 MB, configurable.
- Validasi ukuran, ekstensi, MIME/konten dan keberhasilan upload. Lamaran menyimpan snapshot data/CV; perubahan profil tidak mengubah lamaran lama.
- Satu lamaran per seeker–lowongan, ditegakkan constraint database. Retry tidak menciptakan duplikat. Status aktif diperiksa kembali ketika menyimpan.
- Status NEW → REVIEWED → SHORTLISTED → HIRED; NEW boleh langsung SHORTLISTED. REJECTED tersedia dari setiap status nonterminal. Koreksi status terminal hanya lewat tindakan eksplisit dengan alasan dan audit.
- Seeker melihat status dan waktu pembaruan. Email perubahan status kandidat belum wajib MVP.
- Lowongan ditutup tetap dapat dikelola kandidatnya oleh pemilik akun/perusahaan yang aktif selama periode retensi. Dokumen hanya diakses kandidat pemilik, recruiter berwenang, atau admin untuk kebutuhan dukungan yang diaudit.

## 8. Pembelian, kredit dan refund

### Paket dan pembayaran

Paket berisi nama, harga IDR, jumlah kredit, deskripsi dan status penjualan. Pembayaran satu kali, tanpa auto-renew. Harga dikelola admin; uang disimpan integer rupiah. Biaya/pajak, jumlah kredit dan ketentuan publikasi ditampilkan sebelum membayar dan disalin ke record pembelian. Perubahan paket tidak mengubah pembelian lama; paket lama dinonaktifkan, bukan menghapus riwayatnya.

Flow: pilih paket → order/invoice → satu payment gateway → verifikasi server → tambah kredit → receipt halaman web dan notifikasi transaksi.

- Status pembayaran PENDING, PAID, FAILED, EXPIRED; status refund dicatat terpisah sebagai NONE, PENDING, SUCCEEDED atau FAILED.
- Redirect browser tidak boleh menambah kredit. Verifikasi signature, merchant/order, nominal, mata uang dan status melalui mekanisme provider.
- Satu order memakai satu referensi pembayaran provider. Membuka ulang pembayaran menggunakan referensi yang sama; order terminal dapat diganti order baru. Jika dua order berbeda benar-benar dibayar, keduanya harus tercatat sebagai pembelian.
- Webhook berulang atau tidak berurutan tidak menggandakan kredit atau menurunkan status PAID. Gunakan identitas event provider bila tersedia dan kunci unik operasi grant per order.
- Respons sukses webhook diberikan setelah hasil/event tersimpan tahan lama. Pembayaran terlambat setelah expiry lokal diperiksa ke provider, lalu diproses atau ditandai untuk rekonsiliasi; dana diterima tidak boleh diabaikan.
- Transaksi pending/hasil ambigu dapat diperiksa ulang oleh proses terjadwal dan admin. Jika provider tidak tersedia, tampilkan pending dengan jelas, jangan menebak hasil sukses/gagal.

### Kredit

Saldo tersedia = kredit diberikan − kredit dikonsumsi − reservasi publikasi aktif − penahanan refund aktif − penarikan kredit yang sah. Simpan riwayat perubahan yang tidak diedit langsung, dengan sumber order/lowongan, actor, waktu, alasan dan operation key unik.

Reservasi terkait langsung ke satu lowongan. Reserve, release dan consume menggunakan transaksi database dan locking sehingga dua pengajuan/approval bersamaan tidak membuat saldo negatif atau potongan ganda. Admin tidak mengubah angka saldo tanpa record adjustment. Ringkasan saldo harus dapat direkonsiliasi dengan riwayat.

Penutupan, expiry atau suspend setelah publikasi tidak mengembalikan kredit otomatis. Kompensasi kesalahan platform menggunakan adjustment admin dengan alasan. Tidak perlu tabel lot kredit karena semua kredit setara dan tidak kedaluwarsa.

### Refund manual yang aman

Refund bukan tombol bebas untuk recruiter. Admin memeriksa kebijakan refund dan pembayaran terkait, lalu menangani pengembalian melalui gateway.

Pada MVP, refund penuh biasa hanya untuk pembelian terakhir yang belum pernah digunakan: tidak ada konsumsi sejak grant tersebut, belum pernah direfund untuk order tersebut dan saldo tersedia cukup untuk menarik seluruh kredit pembelian. Kasus lain, refund parsial dan chargeback ditangani sebagai sengketa manual; tidak diasumsikan didukung oleh flow biasa.

Sebelum mengirim refund melalui gateway, sistem menahan jumlah kredit terkait secara atomik agar tidak dapat dipakai saat proses berjalan. Admin mencatat referensi gateway dan memverifikasi hasil: sukses mengubah penahanan menjadi penarikan final, gagal terkonfirmasi melepaskan penahanan, hasil ambigu tetap pending. Penyelesaian berulang tidak boleh menarik/melepas kredit dua kali. Jangan menampilkan REFUNDED sebelum ada bukti provider atau membuat saldo negatif untuk memaksakan penyelesaian.

## 9. Subscription dan pengiriman email

Subscription gratis dan opt-in; simpan waktu persetujuan/pencabutan serta versi persetujuan. Preferensi kategori, lokasi, level dan employment type menggunakan OR di dalam satu dimensi dan AND antar dimensi. Dimensi kosong tidak membatasi; semua kosong memerlukan konfirmasi menerima seluruh lowongan. Remote cocok berdasarkan cakupan kandidat, bukan otomatis semua lokasi.

Lowongan yang pertama terbit menghasilkan pemberitahuan kepada subscriber yang cocok. Subscriber baru tidak menerima lowongan lama secara otomatis. Gunakan waktu mulai subscription aktif untuk pengecekan ini, termasuk setelah unsubscribe lalu subscribe ulang.

- Publikasi menyimpan penanda distribusi belum selesai dalam transaksi yang sama. Scheduler melanjutkan lowongan yang belum selesai dengan cursor/checkpoint tersimpan. Ini cukup untuk MVP tanpa framework outbox generik.
- Buat daftar penerima bertahap dengan pasangan unik lowongan–subscriber dan database queue. Penanda selesai baru ditulis setelah seluruh pencocokan selesai; kegagalan di tengah dapat dilanjutkan tanpa kehilangan atau menggandakan penerima.
- Pengiriman yang tersimpan tetapi belum berhasil masuk queue dapat ditemukan dan dijadwalkan ulang. Worker harus aman ketika menerima job duplikat.
- Default chunk 100 penerima, configurable; satu penerima satu email. Jangan menampilkan alamat subscriber lain melalui To/CC.
- Periksa ulang subscription, preferensi, suppression dan keaktifan lowongan sebelum menyerahkan email ke provider. Unsubscribe atau penutupan lowongan menghentikan email yang belum diserahkan.
- Batas kirim per menit/jam ditentukan sesuai provider sebelum pengiriman nyata dan berlaku lintas worker. Retry sementara dibatasi, memakai backoff; error permanen tidak diulang terus-menerus.
- Provider bisa menerima email sebelum timeout. Gunakan idempotency provider bila tersedia, tampilkan hasil ambigu dan batasi retry; tidak menjanjikan exactly-once delivery.
- Hard bounce/complaint memblokir pengiriman berikutnya. Integrasi event provider diverifikasi; accepted bukan bukti delivered.
- Email memuat posisi, perusahaan, lokasi, gaji publik, deadline, tautan detail, preferensi dan unsubscribe tanpa login.
- GET unsubscribe membuka konfirmasi, POST menghentikan langganan secara idempotent dengan token aman; one-click POST header mengikuti kemampuan provider.
- Dashboard admin cukup memperlihatkan pending, accepted, failed, skipped dan hasil ambigu per lowongan. Email transaksi mendapat prioritas di atas email lowongan massal.

## 10. Halaman publik, engagement dan SEO

- Landing: pencarian, lowongan terbaru, kategori, perusahaan, CTA subscribe dan CTA recruiter.
- Search posisi/perusahaan/kata kunci/skill. Filter kategori, lokasi, gaji publik, tipe kerja, level, pengalaman, pendidikan, work arrangement dan tanggal publikasi.
- Listing default terbaru, 20 item per halaman; pagination server untuk semua daftar besar. Filter gaji mengecualikan gaji tersembunyi dan menjelaskannya di UI.
- Detail `/jobs/{slug}` menampilkan seluruh informasi publik, badge valid, deadline dan CTA sesuai metode/status. Slug unik dan stabil.
- Company page `/companies/{slug}` menampilkan profil publik dan lowongan aktif, tanpa dokumen verifikasi atau kontak privat.
- Save memerlukan login dan unik per user–lowongan; status active, closing soon (default 3 hari), expired, closed atau unavailable.
- Share selalu menuju detail platform: copy link serta kanal WhatsApp, LinkedIn, Facebook, Threads, Telegram yang didukung. Verifikasi dukungan aktual saat implementasi; copy link menjadi fallback.
- Report memerlukan login, alasan dan rate limit; kategori scam, fake company, recruitment fee, suspicious link, informasi salah, job closed atau konten tidak pantas. Status OPEN, IN_REVIEW, RESOLVED, DISMISSED. Identitas pelapor tidak diberikan kepada recruiter.
- Admin memoderasi data perusahaan, isi, lokasi, gaji, metode, URL dan kredit untuk kedua metode. Reject/suspend/restore wajib alasan serta audit.
- Meta title/description, canonical, Open Graph, sitemap dan structured JobPosting sesuai data publik serta kebijakan mesin pencari yang diperiksa saat implementasi.
- Closed/expired tetap dapat menampilkan halaman informatif tanpa apply atau structured JobPosting dan dikeluarkan dari sitemap. Suspended menampilkan unavailable tanpa mempublikasikan konten bermasalah.
- Cache publik diinvalidasi saat status berubah. Dashboard, dokumen privat, pembayaran, callback dan apply redirect tidak dicache secara publik; robots.txt bukan pengamanan.

## 11. Dashboard dan metrik

| Dashboard | Isi minimum |
|---|---|
| Seeker | Lowongan terbaru/filter preferensi, saved jobs, applications, subscription, profile |
| Recruiter | Verifikasi, saldo tersedia/direservasi, jobs/status, kandidat, performa dan transaksi |
| Admin | Pengguna, verifikasi, moderasi, reports, paket/harga, transaksi/refund, subscriber, settings, audit dan kesehatan queue |

Metrik recruiter: Job Views, Apply Clicks external, Saves aktif, Share Actions, Internal Applications, Shortlisted dan Hired berdasarkan status kandidat terkini. Share Actions berarti klik share/copy, bukan bukti posting berhasil. Apply Click bukan lamaran selesai.

Views/clicks adalah estimasi interaksi, bukan orang unik. Deduplikasi per session–lowongan–event dengan jendela awal 30 menit, saring bot yang dikenali dan simpan agregat harian. Tidak wajib menyimpan event mentah permanen. Sumber memakai UTM yang diizinkan lalu domain referrer: direct, Google, email, kanal share dan other; jangan menyimpan URL lengkap berisi token/data pribadi.

KPI produk: seeker aktif dalam 30 hari, subscriber aktif, recruiter verified/paying/repeat purchase, lowongan aktif, lamaran dan apply clicks. Dashboard keuangan memisahkan pembayaran diterima, refund dan penerimaan bersih; bukan laporan pengakuan pendapatan akuntansi. Email delivered hanya ditampilkan bila provider mengonfirmasi.

Semua proses penting memiliki loading, success, error, validation dan empty state; form tidak hilang saat validasi gagal. Tampilan responsif, keyboard navigation, label, fokus error dan kontras wajib. Status pending tidak ditampilkan sebagai sukses. Hindari alert browser jika sudah memakai toast/modal aplikasi.

### Identitas visual dan warna brand

Acuan visual aplikasi adalah [logo Sharekerjaid](assets/sharekerjaid-logo.jpeg) yang diberikan pemilik produk. Gunakan identitas biru–navy–oranye secara konsisten pada halaman publik, dashboard dan email. Palet berikut merupakan pendekatan visual dari logo, bukan hasil ekstraksi warna resmi.

| Peran | Warna awal | Penggunaan |
|---|---|---|
| Primary | `#0052CC` | Tombol utama seperti Cari Lowongan/Lamar, tautan dan navigasi aktif |
| Navy | `#071D57` | Heading, teks brand dan teks di atas aksen oranye |
| Accent | `#FF7A00` | Aksen, highlight dan elemen pendukung brand |
| Background | `#F5F7FB` | Latar halaman |
| Surface | `#FFFFFF` | Card, form dan panel |
| Body text | `#24324B` | Teks utama |
| Muted text | `#526078` | Keterangan dan metadata |
| Border | `#DCE3EE` | Pemisah dan batas card dekoratif |

- Dominankan latar putih/terang; biru menandai aksi utama, oranye dipakai secukupnya agar tidak bersaing dengan isi lowongan.
- Tombol biru menggunakan teks putih. Aksen oranye menggunakan teks navy; jangan memakai teks putih kecil pada oranye terang tanpa pemeriksaan kontras.
- Target kontras teks biasa minimal 4.5:1, teks besar minimal 3:1; kontrol dan focus indicator minimal 3:1 terhadap warna di sekitarnya. Border dekoratif bukan satu-satunya penanda input.
- Success, warning dan error memakai warna semantik yang jelas disertai ikon/teks; warna brand tidak menggantikan makna status.
- Logo tidak diregangkan, dipotong atau diubah warnanya. JPEG yang tersedia memiliki latar putih; tampilkan pada permukaan putih dengan ukuran proporsional. SVG/PNG transparan dapat menggantikannya jika aset resmi tersedia, tanpa menggambar ulang logo secara otomatis.
- Gradient hanya sebagai aksen dekoratif terbatas. Teks panjang, form dan dashboard memakai warna solid agar mudah dibaca.
- Saat UI dibuat, pusatkan palet pada variabel tema/CSS dan penyesuaian Bootstrap. Periksa juga hover, disabled, error dan keyboard focus; jangan menyebarkan kode warna ke setiap view.

## 12. Keamanan dan pengelolaan data

- Authorization server per objek untuk lowongan, kandidat, file, invoice dan kredit. HTTPS, secure session, CSRF, validasi server, rate limit, sanitasi/escaping dan query terparameterisasi wajib.
- Logo boleh publik setelah validasi; CV/dokumen verifikasi berada di storage privat di luar document root. Download memeriksa izin setiap request, memakai nama internal acak serta header attachment yang aman.
- File upload tidak dieksekusi. Bersihkan upload gagal/yatim; jangan menyimpan lamaran yang menunjuk file yang gagal tersimpan. Malware scan digunakan bila tersedia; keterbatasannya dicatat tanpa menghilangkan validasi file.
- Secret/token/cookie, isi CV dan payload sensitif penuh tidak masuk source atau log. Admin sensitif, akses dokumen, perubahan status/kredit/harga memiliki audit minimum yang diperlukan.
- Baseline retensi: upload sementara 24 jam, data deduplikasi sementara maksimal 30 hari, log email tanpa isi pesan 90 hari, CV/lamaran 12 bulan setelah penutupan, dokumen verifikasi 90 hari setelah keputusan. Sengketa dengan alasan tercatat dapat menunda penghapusan terkait.
- Retensi transaksi/audit keuangan dan kebijakan final ditetapkan sebelum produksi. Ini usulan produk, bukan penetapan kewajiban hukum.
- Permintaan hapus akun ditangani admin dengan prosedur: cabut akses/subscription, hapus/anonymize data yang memenuhi syarat, pertahankan record wajib dengan alasan dan batas retensi. Jangan cascade-delete ledger/transaksi.
- Privacy notice, terms, refund policy, kontak dukungan dan prosedur permintaan data wajib sebelum menerima pengguna nyata.

## 13. Batas teknis dan operasional

Gunakan Laravel proyek yang kompatibel dengan PHP hosting, Blade + Bootstrap 5, MySQL/MariaDB, Database Queue, scheduler cPanel, file/database cache dan Laravel Filesystem. Asset dibuild lokal/CI; Node runtime tidak wajib di hosting. Penggantian dependensi Tailwind awal dilakukan saat pengerjaan UI agar tidak memelihara dua sistem CSS.

- Ikuti controller, Form Request, policy, model dan migration Laravel. Pisahkan logic bisnis ketika dibutuhkan; tidak membuat framework module, repository/interface satu implementasi, atau tabel fitur masa depan.
- Data lowongan menggunakan `job_postings`; tabel `jobs` tetap untuk antrean Laravel. ERD dan daftar migration dibuat per tahap berdasarkan fitur nyata, bukan menyalin daftar seluruh tabel dari arsip PRD.
- Gunakan constraint unik untuk social identity, user-role, save, lamaran, grant pembayaran, konsumsi kredit dan penerima email. Index mengikuti query/filter nyata; pagination, eager loading dan transaksi penting wajib.
- Admin mengatur paket/harga dan pengaturan bisnis yang memang berubah. Environment/config mengatur provider secrets, queue, storage, upload dan batas operasional. Tidak perlu UI generik untuk mengedit seluruh konfigurasi. Status domain berupa enum/konstanta terpusat.
- Verifikasi PHP web/CLI, database, ekstensi, memory, interval cron, storage dan akses keluar provider sebelum deploy. Document root menunjuk public; .env, backup dan file privat tidak dapat diakses HTTP.
- Cron menjalankan scheduler dan worker terbatas sesuai hosting, dengan anti-overlap, timeout dan retry yang konsisten. Tidak mengasumsikan Supervisor atau worker permanen tersedia.
- Pantau cron heartbeat, failed jobs, umur antrean, pembayaran pending, error rate, query/response lambat dan disk. Ada pemilik operasional dan kanal alert nyata sebelum rilis.
- Backup database/file privat terproteksi di lokasi terpisah, diuji restore. Target awal RPO 24 jam/RTO 8 jam harus dibuktikan sebelum rilis; jangan dianggap otomatis dipenuhi hosting.
- Deploy dengan backup, migration aman, asset build, cache refresh dan smoke test. Rollback kode tersedia; jangan otomatis rollback database secara destruktif.
- Cloudflare membantu TLS/DNS/cache asset/proteksi yang tersedia; origin tetap aman dan proxy terpercaya dikonfigurasi dengan benar.
- Redis, dedicated search, object storage dan VPS ditambahkan jika resource/antrean/latensi terukur membutuhkannya. Pindah engine database merupakan proyek tersendiri.

## 14. Acceptance criteria dan validasi

| Skenario uji | Hasil wajib |
|---|---|
| Manipulasi role/login | Tidak memperoleh admin melalui input publik; callback tidak sah ditolak |
| Akses lintas recruiter | Kandidat, CV, invoice dan perubahan lowongan milik akun lain ditolak |
| Dua submit/approve bersamaan | Saldo tidak negatif; satu reservasi dan satu konsumsi per publikasi |
| Reject/tarik/deadline habis saat pending | Reservasi dilepas tepat satu kali, draft/hasil review dan alasan jelas |
| Webhook duplikat/tidak berurutan | Grant satu kali; PAID tidak turun karena event lama |
| Webhook salah/pembayaran terlambat | Signature salah ditolak; uang diterima tetap direkonsiliasi |
| Refund pending/ambigu | Kredit ditahan dan tidak bisa dibelanjakan; tidak diklaim refunded tanpa bukti |
| External apply | Redirect ke tujuan disetujui; metrik bernama Apply Click |
| Internal apply diulang | Satu lamaran, snapshot stabil dan CV privat |
| Deadline lewat/akun suspended | Apply ditolak segera meski cron belum berjalan |
| Unsubscribe/lowongan tutup saat antre | Pengiriman yang belum diserahkan provider dihentikan |
| Proses distribusi mati di tengah | Dapat dilanjutkan tanpa kehilangan daftar penerima atau record duplikat |
| Gaji tersembunyi | Tidak bocor melalui halaman, filter atau structured data |
| Backup/deploy | Restore terbukti, queue/cron berjalan, file privat tidak publik |

Uji aturan bisnis, feature/authorization, concurrency pada database target, sandbox gateway/email, dan UI mobile/desktop. SQLite saja tidak membuktikan locking MySQL.

Target uji awal pada lingkungan yang diizinkan: listing/detail p95 maksimal 2 detik dengan 10.000 lowongan dan 20 pengguna virtual serentak; error server di bawah 1%. Ini target validasi, bukan janji kapasitas hosting. Waktu distribusi email mengikuti jumlah penerima dan kuota provider; jangan menjanjikan instan.

## 15. Urutan implementasi dan syarat rilis

1. Fondasi: setup sesuai AGENTS.md, desain data tahap pertama, login/role/policy, perusahaan dan verifikasi.
2. Alur external lengkap: paket, sandbox payment, kredit/reservasi, wizard, moderasi, listing/detail dan redirect.
3. Rekrutmen internal: lamaran, CV privat, status kandidat dan dashboard terkait.
4. Distribusi: save/share/report, subscription/email, SEO dan analytics dasar.
5. Rilis: pengujian transaksi/izin, integrasi provider, cron, backup restore, performa dan prosedur operasional.

Sebelum produksi, pemilik produk dan engineering harus menetapkan harga/paket/biaya, durasi publikasi sebelum penjualan pertama, gateway dan kebijakan refund, provider email/kuota/domain, hosting, OAuth produksi, retensi final, dokumen kebijakan, SOP verifikasi/scam, MFA/admin awal dan penanggung jawab alert. Development memakai sandbox sampai keputusan dan kredensial nyata tersedia.

## 16. Perubahan utama dari v4.1

- Satu kredit dan satu hak publikasi menggantikan lot serta entitlement per produk; expiry kredit dihapus secara eksplisit untuk MVP.
- Reservasi dipertahankan untuk kepastian saldo saat moderasi; timeout otomatis dan aturan expiry yang saling terkait dihapus.
- Refund tetap aman tetapi pelaksanaan gateway dilakukan admin; tidak membangun mesin refund otomatis.
- Distribusi memakai penanda proses, penerima unik dan pemulihan terjadwal; tidak mewajibkan outbox/campaign framework generik.
- Daftar tabel spekulatif dan konfigurasi berlebihan dihapus. Detail schema ditentukan saat fitur dikerjakan.
- External/internal recruitment, subscription, keamanan dokumen, audit transaksi, acceptance criteria dan kesiapan produksi tetap wajib.
