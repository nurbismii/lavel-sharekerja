# Product Requirements Document
# ShareKerja — SaaS Job Distribution & Recruitment Platform

Versi: **4.1**  
Tanggal revisi: **27 September 2026**  
Status: **Baseline rekomendasi untuk implementasi MVP**  
Model bisnis: **SaaS / B2B2C**  
Arsitektur: **Modular monolith**  
Infrastruktur awal: **Shared hosting / cPanel**

Dokumen ini menggantikan PRD v4.0 sebagai acuan perencanaan. Dokumen asli disimpan pada [PRD-v4.0-original.md](PRD-v4.0-original.md). Aturan yang sebelumnya ambigu telah diputuskan sebagai baseline rekomendasi. Harga, vendor, kapasitas hosting, serta kebijakan retensi final tetap harus ditetapkan sebelum peluncuran; jangan menganggap nilai tersebut sudah tersedia.

## 1. Visi dan batas produk

Membantu recruiter mempublikasikan lowongan, memperluas jangkauan, mengukur interaksi, serta menerima kandidat melalui sistem mereka sendiri atau melalui platform.

Alur nilai utama: **Publish → Distribute → Reach → Track → Apply**.

Platform bukan ATS penuh. Rekrutmen internal dibatasi pada penerimaan lamaran, akses CV, dan pengelolaan status kandidat sederhana. Distribusi MVP berarti halaman publik yang dapat ditemukan melalui pencarian, SEO, tautan berbagi, serta email pelanggan yang menyetujui langganan. MVP tidak mencakup posting otomatis ke jejaring sosial atau job board lain.

Prinsip: mulai sederhana, lindungi data dan transaksi, gunakan batas domain yang jelas, dan pindahkan infrastruktur berdasarkan metrik tanpa menjanjikan migrasi database lintas engine tanpa pekerjaan tambahan.

## 2. Pengguna dan hak akses

| Pengguna | Hak utama |
|---|---|
| Pengunjung | Browse, search, detail lowongan aktif, halaman perusahaan, berbagi tautan, external apply |
| Job Seeker | Seluruh hak pengunjung, profil, simpan lowongan, internal apply, riwayat lamaran, subscription, preferensi, laporan |
| Recruiter | Profil recruiter, perusahaan sendiri, verifikasi, pembelian kredit, lowongan sendiri, kandidat dan analytics milik perusahaan sendiri |
| Super Admin | Moderasi, verifikasi, pricing, transaksi, laporan, konfigurasi, audit, serta penangguhan akses |

- Gunakan `users`, `roles`, dan `user_roles`; kombinasi `(user_id, role_id)` unik.
- Pilihan onboarding awal hanya Job Seeker atau Recruiter. Pengguna dapat menambahkan role publik lainnya melalui onboarding yang sesuai.
- Role Super Admin tidak dapat diberikan melalui form publik, payload profil, atau callback OAuth.
- Pemberian/pencabutan Super Admin memakai proses administratif terbatas dan audit. Dilarang menonaktifkan admin aktif terakhir tanpa pengganti.
- Pemeriksaan izin dilakukan di server melalui policy/middleware, bukan hanya menyembunyikan menu.
- Status akun `ACTIVE` atau `SUSPENDED`. Akun suspended tidak dapat menjalankan aksi terautentikasi; pemeriksaan berlaku juga pada session yang telah aktif.
- Mode dashboard hanya mengubah navigasi, tidak menggantikan pemeriksaan izin.

## 3. Authentication dan onboarding

Provider MVP: Google OAuth / OpenID Connect melalui integrasi server yang sesuai versi Laravel terpasang.

Flow: Google login → identifikasi social account → login/buat akun → pilihan role bila belum ada → onboarding terkait.

- Identitas provider menggunakan pasangan unik `(provider, provider_user_id)`; email bukan pengganti identitas provider.
- Validasi state, redirect URI, serta identitas/token melalui library yang dipilih; apabila menggunakan ID token, validasi issuer, audience, expiry, dan nonce yang relevan.
- Jangan otomatis menggabungkan akun hanya karena email sama. Konflik email memerlukan pembuktian kepemilikan akun melalui alur linking yang aman.
- Simpan email provider sebagai metadata. Jangan menyimpan password Google atau token provider yang tidak diperlukan.
- Regenerasi session setelah login; logout membatalkan session dan memperbarui CSRF token.
- Error/cancel OAuth menampilkan pesan yang dapat ditindaklanjuti tanpa token atau detail internal.
- Tidak ada autentikasi password publik pada MVP. Provisioning admin awal memakai prosedur operasional yang diaudit.
- Akses admin memerlukan autentikasi tambahan/MFA pada aplikasi atau kontrol akses setara yang diverifikasi sebelum produksi; login Google biasa tidak dianggap bukti MFA.

Profil Job Seeker: nama dan email akun; telepon, lokasi, kategori, level, tipe pekerjaan, serta CV profil bersifat opsional. Telepon dan CV diwajibkan saat mengirim lamaran internal.

Subscription selalu default **NON-SUBSCRIBED**, dengan pilihan Subscribe atau Lewati. Pembuatan akun bukan persetujuan email lowongan.

## 4. Perusahaan, recruiter, dan verifikasi

MVP mendukung satu pemilik recruiter per perusahaan dan maksimal satu perusahaan per recruiter. Recruiter teams, transfer kepemilikan mandiri, serta multi-company management ditunda.

- `companies.owner_user_id` menjadi pemilik eksplisit dan unik pada MVP.
- Semua lowongan terikat ke perusahaan; transaksi dan kredit dimiliki akun recruiter pembeli. Akses dilakukan dengan pemeriksaan pemilik, bukan ID dari request saja.
- Nama perusahaan tidak cukup untuk membuktikan kepemilikan. Dugaan perusahaan duplikat/peniruan masuk pemeriksaan admin; email berdomain umum tidak otomatis ditolak.
- Profil recruiter: nama, email, telepon, jabatan, LinkedIn opsional.
- Profil perusahaan: nama, logo, industri, email kontak, website opsional, alamat/lokasi, deskripsi.
- Dokumen verifikasi hanya dikumpulkan jika dibutuhkan, dengan tujuan yang jelas dan akses privat.

Status verifikasi: `NOT_SUBMITTED`, `PENDING`, `VERIFIED`, `REJECTED`. Status penangguhan perusahaan terpisah: `ACTIVE`, `SUSPENDED`.

Alur: lengkapi profil → kirim verifikasi → admin approve/reject dengan alasan → jika ditolak, perbaiki dan submit kembali. Riwayat pengajuan tetap tersimpan.

- Hanya recruiter dengan perusahaan verified dan aktif yang boleh membeli kredit dan submit lowongan untuk moderasi. Draft boleh dibuat sebelumnya.
- Badge melekat pada hubungan recruiter–perusahaan yang diverifikasi; tampilkan hanya jika verifikasi masih valid dan keduanya aktif.
- Perubahan identitas penting, seperti nama legal atau pemilik, memerlukan verifikasi ulang melalui admin. Perubahan deskripsi/logo biasa tetap diaudit.
- Penangguhan akun/perusahaan langsung menutup akses publik dan apply untuk lowongan terkait melalui pemeriksaan runtime, kemudian pembaruan status dapat diselesaikan bertahap.
- Pencabutan penangguhan tidak otomatis menerbitkan ulang lowongan; admin meninjau ulang satu per satu.

## 5. Scope MVP dan setelah MVP

### Wajib MVP

- Google login, multi-role, onboarding, authorization, dan session.
- Profil perusahaan, verifikasi recruiter, serta admin moderation.
- Listing publik, search/filter, detail, company page, save, share, report.
- Lowongan external dan internal; form lamaran dan status kandidat sederhana.
- Paket, pembayaran satu kali untuk membeli kredit, ledger kredit, invoice/receipt halaman web.
- Subscription opt-in, preferensi, unsubscribe, queue email dengan batching dan batas laju.
- Analytics dasar, SEO, settings, audit, backup, serta pemantauan proses penting.

### Fase 2

Featured Job, Job Boost, perpanjangan berbayar, promo code, invoice PDF, recruiter teams, screening questions, advanced subscription, rekomendasi personal, company follow, advanced analytics.

### Fase 3

ATS integration, enterprise API, outbound webhook, job feed, bulk import, scheduled publishing.

### Di luar MVP

ATS kompleks, interview scheduling, psikotes, MCU, offering, kontrak, onboarding karyawan, AI screening/matching, mobile app, microservices, dedicated search/analytics infrastructure.

Featured section dan CTA pembelian fitur fase 2 tidak ditampilkan sebagai fitur tersedia pada MVP. Dashboard seeker menggunakan lowongan terbaru/filter preferensi sederhana, tanpa klaim rekomendasi AI.

## 6. Lowongan dan wizard

Model lowongan menggunakan **`JobPosting` / `job_postings`**. Tabel `jobs` yang sudah ada digunakan Laravel Database Queue dan tidak boleh dipakai untuk data lowongan.

Wizard:

1. Dasar: judul, perusahaan milik recruiter, kategori, tipe kerja, level, jumlah kebutuhan.
2. Lokasi: provinsi, kota, alamat/lokasi kerja, `ON_SITE`, `HYBRID`, atau `REMOTE`.
3. Kompensasi: minimum, maksimum, mata uang IDR untuk MVP, periode gaji, tampilkan/sembunyikan.
4. Detail: deskripsi, tanggung jawab, kualifikasi, pendidikan, pengalaman, skills, benefit.
5. Metode: `EXTERNAL` dengan URL tujuan atau `INTERNAL` dengan form standar.
6. Batas lamaran: tanggal/jam deadline; durasi publikasi berasal dari entitlement produk.
7. Preview dan submit: ringkasan biaya kredit, durasi, metode, tujuan external, dan persetujuan publikasi.

- Draft disimpan di server dengan validasi per langkah; pengguna dapat kembali melanjutkan.
- Submit memvalidasi seluruh data lagi. Double click/retry tidak membuat reservasi kredit ganda.
- Jumlah kebutuhan harus bilangan positif. Gaji opsional; jika diisi, minimum tidak melebihi maksimum dan periode wajib jelas.
- Gaji tersembunyi tidak boleh bocor lewat HTML, JSON, structured data, filter gaji, atau respons publik lain.
- Remote dapat tidak memiliki kota kantor, tetapi wajib menjelaskan cakupan lokasi kandidat yang diperbolehkan.
- Deskripsi menggunakan teks atau rich text yang disanitasi dengan daftar elemen yang diizinkan.
- Deadline wajib di masa depan saat submit dan diperiksa kembali saat approval.
- Timestamp disimpan dalam UTC; zona waktu tampilan default `Asia/Makassar`, configurable dan ditampilkan pada input deadline.

## 7. Siklus hidup lowongan

Status editorial: `DRAFT`, `WAITING_APPROVAL`, `PUBLISHED`, `REJECTED`, `CLOSED`, `EXPIRED`, `SUSPENDED`.

**`WAITING_PAYMENT` dihapus dari status lowongan.** Pembayaran memiliki siklus sendiri; lowongan tetap draft jika saldo tidak cukup. UI menampilkan kebutuhan membeli kredit dan memungkinkan kembali ke draft setelah pembayaran.

| Dari | Ke | Syarat dan efek |
|---|---|---|
| DRAFT / REJECTED | WAITING_APPROVAL | Data valid, recruiter verified/aktif, kredit tersedia direservasi secara atomik |
| WAITING_APPROVAL | DRAFT | Recruiter menarik pengajuan; reservasi dilepas |
| WAITING_APPROVAL | REJECTED | Admin memberi alasan; reservasi dilepas |
| WAITING_APPROVAL | PUBLISHED | Admin approve, syarat diperiksa ulang; reservasi dikonsumsi dalam transaksi yang sama |
| PUBLISHED | CLOSED | Recruiter/admin menutup lebih awal; tidak ada pengembalian kredit otomatis |
| PUBLISHED | EXPIRED | Waktu efektif penutupan tercapai |
| WAITING_APPROVAL / PUBLISHED | SUSPENDED | Pelanggaran; reservasi yang belum dikonsumsi dilepas |
| SUSPENDED | DRAFT | Admin mengizinkan perbaikan jika belum pernah terbit |
| SUSPENDED | PUBLISHED | Admin meninjau ulang lowongan yang pernah terbit dan masa tayangnya masih berlaku; tanpa konsumsi kredit kedua |
| SUSPENDED | EXPIRED / CLOSED | Masa tayang habis atau admin menutup |

- `published_at` ditetapkan pada publikasi pertama; masa tayang dimulai saat approval, bukan saat pembayaran/submit.
- `expires_at = published_at + duration_snapshot`; waktu penutupan efektif adalah nilai paling awal antara `expires_at` dan `application_deadline`.
- Restore tidak mereset tanggal publikasi, tidak memperpanjang masa tayang, dan tidak mengirim ulang campaign publikasi.
- Lowongan hanya dapat dibrowse/apply jika published, belum melewati waktu penutupan efektif, dan pemilik/perusahaan aktif. Ini berlaku walaupun cron terlambat.
- Lowongan waiting approval dikunci; tarik ke draft untuk mengedit.
- Lowongan published tidak dapat diedit langsung pada MVP. Untuk perubahan substansial, tutup lalu buat draft baru; UI wajib menjelaskan bahwa publikasi baru memerlukan kredit. Koreksi administratif terbatas harus diaudit dan URL external baru wajib dimoderasi.
- Closed/expired bersifat terminal pada MVP; duplikasi menjadi draft diperbolehkan dengan ID/slug baru.

## 8. Moderasi dan laporan

Admin memeriksa perusahaan, verifikasi, posisi, isi, gaji, lokasi, deadline, metode rekrutmen, URL tujuan, serta entitlement kredit. Approval internal dan external menggunakan aturan yang sama.

- Reject, suspend, restore, koreksi admin, dan penyesuaian kredit wajib memiliki alasan serta audit.
- Reports: scam, perusahaan palsu, biaya rekrutmen, link mencurigakan, informasi salah, lowongan ditutup, konten tidak pantas.
- Status laporan: `OPEN`, `IN_REVIEW`, `RESOLVED`, `DISMISSED`; tindakan terhadap lowongan disimpan terpisah dari status laporan.
- Batasi laporan berulang dan spam. Identitas pelapor tidak ditampilkan ke recruiter.
- Suspend bukan delete. Riwayat transaksi, moderasi, dan lamaran tetap dijaga sesuai kebijakan retensi.

## 9. External recruitment

Flow: detail lowongan → CTA Lamar → endpoint `/jobs/{slug}/apply` → validasi lowongan dan tujuan tersimpan → pencatatan apply click → redirect 302.

- Pengunjung tidak diwajibkan login untuk external apply.
- Istilah metrik adalah **Apply Click**, bukan Application atau kandidat berhasil melamar.
- Tujuan hanya diambil dari record yang disetujui admin, tidak dari parameter redirect bebas.
- Wajib HTTPS, host valid, tanpa credential dalam URL; blok scheme berbahaya, localhost, alamat IP privat/reserved dan tujuan lokal yang teridentifikasi.
- MVP tidak melakukan fetch URL, scraping preview, atau ekspansi short link di server. Jika kelak ditambahkan, wajib perlindungan SSRF terpisah.
- Link shortener yang menyamarkan tujuan ditolak untuk moderasi MVP; domain tujuan ditampilkan sebelum pengguna keluar dari platform.
- Endpoint diberi rate limit dan respons tidak dicache CDN/browser bersama agar redirect dan tracking tidak menggunakan respons lama.
- Kegagalan analytics tidak boleh menghalangi redirect yang valid; error tetap dicatat secara terbatas untuk pemantauan.
- Tidak ada jaminan mengetahui hasil lamaran pada website recruiter tanpa integrasi terpisah.

## 10. Internal recruitment

Flow: detail → login bila diperlukan → form → unggah CV → konfirmasi → lamaran tersimpan → dashboard kandidat recruiter.

- Data wajib: nama, email akun, telepon, CV. Portfolio, LinkedIn, pendidikan, pengalaman, ekspektasi gaji opsional.
- MVP menerima CV PDF maksimal 5 MB sebagai default awal configurable. Validasi ukuran, MIME/konten, ekstensi, dan hasil upload di server.
- CV profil boleh digunakan setelah konfirmasi kandidat. Lamaran menyimpan snapshot data dan referensi versi CV yang tidak berubah ketika profil diperbarui.
- Satu lamaran per `(job_posting_id, job_seeker_id)`. Pengiriman berulang mengembalikan hasil yang sama, bukan membuat duplikat.
- Validasi ulang status lowongan di transaksi pengiriman untuk menangani lowongan yang ditutup bersamaan.
- Status: `NEW → REVIEWED → SHORTLISTED → HIRED` dengan `REJECTED` tersedia dari status nonterminal. Recruiter dapat langsung shortlist dari NEW; perubahan lain/correction harus eksplisit dan diaudit.
- `HIRED` dan `REJECTED` terminal dalam alur biasa. Tidak ada hapus/ubah diam-diam untuk menyembunyikan riwayat.
- Seeker melihat status terkini dan waktu perubahan; perubahan status tersedia di aplikasi, email perubahan status belum wajib MVP.
- Penutupan lowongan menghentikan lamaran baru, tidak menghapus akses kandidat lama bagi pemilik aktif yang berwenang.
- Recruiter lain tidak boleh mengakses lamaran/CV meskipun mengetahui ID atau URL.
- Screening questions dan application answers tidak dibuat pada MVP.

## 11. Search, detail, save, share, company page

- Search posisi, perusahaan, kata kunci, dan skill melalui database; strategi index/FULLTEXT ditetapkan berdasarkan engine dan pengukuran query.
- Filter kategori, lokasi, gaji publik, employment type, level, pengalaman, pendidikan, work arrangement, dan tanggal publikasi.
- Listing hanya memuat lowongan aktif yang dapat dilamar; urutan default terbaru, pagination server default 20 dan maksimum 100 per halaman.
- Filter gaji tidak menyertakan lowongan dengan gaji tersembunyi; UI menjelaskan lowongan tanpa gaji publik tidak masuk hasil filter tersebut.
- Detail menampilkan posisi, perusahaan, badge valid, lokasi, tipe kerja, gaji publik, vacancy, tanggal, deadline, detail, skills, benefit, dan CTA sesuai metode.
- Save memerlukan login dan kombinasi user–lowongan unik. Label saved job: active, closing soon, expired, closed, atau unavailable. Closing soon memakai ambang configurable, default 3 hari.
- Share menuju URL detail platform melalui copy link dan tautan kanal yang tersedia. WhatsApp, LinkedIn, Facebook, Threads, Telegram adalah target UX; dukungan tautan aktual diverifikasi saat implementasi, dengan copy link sebagai fallback.
- Company page `/companies/{slug}` menampilkan data perusahaan yang boleh dipublikasikan serta lowongan aktif; tidak memuat dokumen verifikasi atau kontak privat.
- Landing: hero pencarian, lowongan terbaru, kategori, perusahaan, CTA subscribe, CTA recruiter. Tidak menampilkan featured berbayar pada MVP.

## 12. Produk, paket, dan harga

- Produk MVP: `JOB_DISTRIBUTION` untuk external dan `HOSTED_RECRUITMENT` untuk internal.
- Paket berisi jumlah unit kredit per produk, harga IDR, masa berlaku kredit, durasi publikasi, deskripsi, dan status penjualan.
- Kredit per produk tidak saling ditukar secara otomatis. Satu publikasi memakai biaya kredit produk yang dikonfigurasi dan ditampilkan sebelum submit.
- Tidak ada langganan berbayar berulang/auto-renew pada MVP; subscription seeker adalah layanan email gratis yang berbeda.
- Uang disimpan sebagai integer rupiah, bukan floating point. Pajak/biaya tambahan harus ditentukan dan ditampilkan sebelum pembayaran.
- Snapshot invoice mencakup nama paket, harga, mata uang, komponen biaya/pajak, produk, kredit, validitas, dan durasi. Perubahan katalog hanya memengaruhi pembelian berikutnya.
- Entitlement pembelian lama menggunakan snapshot pembelian. Satu publikasi hanya memakai lot dengan entitlement durasi yang sama; pilihan lot dan biaya diperlihatkan sebelum submit.
- Paket yang pernah dibeli dinonaktifkan dari penjualan, bukan dihapus beserta relasinya.
- Harga aktual dan susunan paket merupakan keputusan peluncuran yang belum ditetapkan oleh PRD ini.

## 13. Kredit dan konsistensi transaksi

Gunakan lot kredit dari setiap pembelian dan ledger append-only. Saldo yang dapat digunakan = unit diberikan dikurangi konsumsi, reservasi aktif, kedaluwarsa, refund/reversal yang sah.

- Pembayaran valid memberikan kredit **tepat satu kali** untuk satu order.
- Reservasi dibuat saat submit moderasi, konsumsi saat publikasi, pelepasan saat pengajuan ditarik/ditolak/dibatalkan.
- Reservasi memiliki batas waktu configurable; default 7 hari. Jika belum direview, pengajuan kembali ke draft dengan alasan timeout dan reservasi dilepas.
- Masa berlaku lot tidak diperpanjang oleh reservasi. Reservasi berakhir paling lambat saat lot kedaluwarsa; approval setelah itu ditolak dan pengajuan dikembalikan ke draft.
- Pelepasan reservasi lot yang sudah expired tidak menjadikannya kredit aktif kembali.
- Pilih kredit yang paling cepat kedaluwarsa dalam kelompok entitlement yang sama. Biaya/reservasi disnapshot dan tidak berubah akibat setting baru.
- Semua reserve/consume/release menggunakan transaksi DB, locking yang sesuai, validasi saldo, dan operation key unik. Saldo tidak boleh negatif akibat dua request bersamaan.
- Ledger menyimpan pemilik, lot, jenis perubahan, unit, sumber transaksi/lowongan, actor, alasan, waktu, dan idempotency key.
- Saldo ringkasan boleh disimpan untuk efisiensi, tetapi wajib dapat direkonsiliasi terhadap ledger. Admin tidak mengedit angka saldo langsung.
- Lowongan yang sudah dipublikasikan tidak mendapat refund kredit otomatis saat closed, expired, atau suspended. Kompensasi karena kesalahan platform melalui adjustment admin dengan alasan.

## 14. Pembayaran, webhook, dan refund

Flow: pilih paket → order/invoice pending → gateway → webhook terverifikasi → konfirmasi pembayaran → grant kredit → receipt/notifikasi.

- Gunakan satu gateway pada MVP; provider dan metode pembayaran dipilih sebelum implementasi integrasi.
- Pisahkan order pembelian, percobaan pembayaran, dan webhook events. Beberapa retry pembayaran tidak boleh menghasilkan beberapa grant untuk satu order.
- Status order: `PENDING`, `PAID`, `EXPIRED`, `CANCELLED`, `REFUND_PENDING`, `REFUNDED`. Status percobaan pembayaran mencakup `PENDING`, `SUCCEEDED`, `FAILED`, `EXPIRED` sesuai pemetaan provider.
- Redirect sukses di browser tidak boleh memberikan kredit. Hanya hasil verifikasi server yang berwenang.
- Validasi signature dan payload sesuai provider, kecocokan merchant/order, nominal, mata uang, serta status pembayaran. Kunci unik provider-event dan business-operation sama-sama diperlukan.
- Webhook diduplikasi atau datang tidak berurutan tidak boleh menggandakan kredit atau mengembalikan order paid ke pending/failed.
- Simpan penerimaan event secara tahan lama sebelum mengakui keberhasilan. Proses bisnis dapat diulang dengan aman; callback dengan signature salah ditolak.
- Jika pembayaran sukses datang terlambat setelah expiry lokal, verifikasi ke provider dan rekonsiliasi; jangan mengabaikan uang yang diterima. Pembayaran sukses ganda masuk exception admin tanpa grant ganda.
- Gateway tidak tersedia: tampilkan pending/gagal yang jelas dan sediakan retry aman. Status pending diperiksa melalui rekonsiliasi terjadwal.
- Refund MVP: permintaan admin untuk seluruh pembelian hanya jika semua kredit terkait belum terpakai, belum direservasi, belum expired, dan memenuhi kebijakan refund yang ditetapkan sebelum rilis.
- Saat refund dimulai, bekukan kredit terkait. Catat REFUNDED dan reversal final hanya setelah provider mengonfirmasi; kegagalan refund melepaskan pembekuan jika memang tidak ada refund.
- Refund parsial, chargeback, atau kredit sudah digunakan menjadi kasus manual terkontrol dengan audit; tidak boleh otomatis menghapus histori, membuat saldo negatif, atau mengklaim dana sudah kembali.

## 15. Subscription dan email

- Subscription gratis dengan status `SUBSCRIBED`/`UNSUBSCRIBED`; simpan waktu opt-in/out, sumber, dan versi persetujuan.
- Preferensi kategori, lokasi, level, employment type. Dalam satu dimensi berlaku OR; antar dimensi berlaku AND. Dimensi kosong berarti tidak membatasi.
- Jika semua preferensi kosong, pengguna harus mengonfirmasi ingin menerima seluruh lowongan. Form memperlihatkan cakupan sebelum subscribe.
- Definisi lokasi remote mengikuti cakupan kandidat pada lowongan, bukan diasumsikan cocok untuk semua kota.
- MVP mengirim pemberitahuan per lowongan yang pertama kali terbit, dengan antrean dan batas volume global. Tidak menjanjikan pengiriman instan.
- Publikasi membuat event/outbox dalam transaksi yang sama dengan publikasi; dispatcher membaca outbox dan membentuk campaign secara idempotent.
- Pencocokan memakai pagination/chunk by ID dan high-water mark penerima; tidak memuat semua subscriber ke memori. Pengguna yang subscribe setelah campaign dibuat tidak otomatis menerima lowongan lama.
- Record penerima unik `(campaign_id, user_id)`; cek ulang subscription, preferensi terkini, suppression, dan status lowongan tepat sebelum kirim.
- Lowongan yang ditutup/suspended/expired tidak dikirim dari antrean lama.
- Gunakan batch configurable default awal 100 penerima; batch adalah unit perencanaan, bukan satu email dengan banyak alamat penerima.
- Setiap penerima menerima email sendiri; tidak ada kebocoran daftar alamat melalui To/CC.
- Batas per menit/jam harus dikonfigurasi sesuai provider sebelum pengiriman produksi; jika belum tersedia, campaign tetap tertahan dengan status yang jelas.
- Shared rate limiter harus berlaku lintas worker; pembatasan per proses saja tidak cukup.
- Retry hanya untuk kegagalan sementara dengan backoff, maksimum percobaan dan batas usia pesan configurable. Kegagalan permanen/suppression tidak diulang terus-menerus.
- Gunakan idempotency key provider jika tersedia. Provider dapat menerima email sebelum timeout; karena itu tidak mengklaim exactly-once delivery. Hasil ambigu direkonsiliasi jika provider mendukung, dan retry dibatasi.
- Email memuat posisi, perusahaan, lokasi, gaji jika publik, deadline, CTA detail, pengaturan preferensi, serta unsubscribe.
- Tautan unsubscribe memakai token acak yang dapat dirotasi, tanpa login dan tanpa PII pada URL. GET membuka konfirmasi; POST melakukan unsubscribe agar link scanner tidak menghentikan langganan tanpa sengaja. One-click header menggunakan endpoint POST yang sesuai jika provider mendukung.
- Unsubscribe idempotent, tidak menghapus akun, dan segera menghentikan pengiriman berikutnya yang belum diterima provider.
- Hard bounce/complaint menambahkan suppression. Status delivered/bounced hanya ditampilkan jika ada event provider terverifikasi; SMTP accepted bukan delivered.
- Prioritas antrean untuk rekonsiliasi pembayaran dan pesan transaksi lebih tinggi daripada campaign massal agar campaign tidak menghambat pembelian.

## 16. Analytics dan KPI

| Metrik | Definisi MVP |
|---|---|
| Job Views | Pembukaan detail yang memenuhi aturan deduplikasi; bukan impression dan bukan jumlah orang unik |
| Apply Clicks | Interaksi endpoint external apply yang valid; bukan lamaran selesai |
| Saves | Jumlah penyimpanan aktif; penghapusan save mengurangi nilai |
| Share Actions | Klik tombol share/copy yang tercatat; bukan bukti share berhasil dipublikasikan |
| Internal Applications | Lamaran internal yang berhasil disimpan, dihitung dari data aplikasi |
| Shortlisted / Hired | Kandidat dengan status terkini terkait; bukan jumlah semua event perubahan |

- Deduplikasi views dan apply clicks memakai identitas pseudonim/session, lowongan, jenis event, dan jendela default 30 menit. Ini estimasi; tanpa identifier yang tersedia akurasi berbeda.
- Trafik bot yang teridentifikasi disaring; tidak menjanjikan angka bebas bot sepenuhnya. Prefetch/link scanner menjadi batasan metrik click.
- Sumber: direct, Google, email, WhatsApp, LinkedIn, Facebook, Threads, Telegram, other. UTM yang dikenali diprioritaskan lalu referrer; nilai source tidak dipakai untuk keputusan akses.
- Simpan domain referrer dan UTM yang dibatasi/diizinkan, bukan URL lengkap yang bisa berisi data pribadi/token.
- Utamakan agregat harian per lowongan/sumber. Event sementara hanya bila dibutuhkan untuk deduplikasi dan diagnosa, dengan retensi terbatas; tidak wajib tabel event permanen per view/share.
- Dashboard menyebutkan waktu pembaruan dan sifat estimasi. Kegagalan analytics tidak menggagalkan apply internal atau pembayaran.
- KPI pengguna: registered, active dengan definisi jendela 30 hari, subscribers aktif. KPI recruiter: verified, paying, repeat purchasers.
- KPI pendapatan dashboard operasional: pembayaran terkonfirmasi, refund terkonfirmasi, dan net cash receipts ditampilkan terpisah. Jangan menyebut saldo kredit belum digunakan sebagai pendapatan akuntansi yang sudah diakui.
- KPI email: accepted, delivered jika tersedia, click, bounce, complaint, unsubscribe. Open tracking tidak wajib MVP.
- KPI infrastruktur: p50/p95 response time, error rate, umur antrean tertua, jumlah failed jobs, waktu campaign, query lambat, storage.

## 17. Dashboard dan UX

- Seeker: lowongan terbaru/sesuai filter, saved jobs, applications, subscription, preferences, profile.
- Recruiter: status verifikasi, saldo per produk/expiry, draft/pending/active/expired, kandidat, analytics, pembelian dan invoice.
- Admin: antrean verifikasi/moderasi, pengguna/perusahaan, laporan, paket/harga, pembayaran exception/refund, subscription, settings, audit, kesehatan queue/email.
- Semua daftar besar memakai pagination server; filter dan sorting harus dibatasi pada kolom yang diizinkan.
- Setiap proses penting menyediakan loading, success, error, validation error, dan empty state; data form tidak hilang saat validasi gagal.
- Tombol submit menahan klik ganda di UI, tetapi konsistensi tetap ditegakkan di server.
- Flow keyboard, label form, fokus error, kontras, dan tampilan mobile wajib diperiksa. Jangan mengandalkan warna sebagai satu-satunya penanda status.
- Gunakan komponen notifikasi konsisten; jangan mencampur alert browser dengan toast/modal aplikasi.
- Proses tertunda ditampilkan sebagai pending beserta tindakan berikutnya, bukan success prematur.

## 18. Storage, privasi, dan retensi

- Logo perusahaan dapat berada pada public disk setelah validasi dan pemrosesan gambar. CV serta dokumen verifikasi wajib private disk di luar document root.
- File privat diunduh melalui controller dengan authorization setiap request; gunakan nama internal acak, safe download headers, dan hindari menampilkan PDF/upload pengguna sebagai HTML aktif.
- Jangan mengandalkan nama file sulit ditebak sebagai kontrol akses. URL storage publik untuk dokumen privat dilarang.
- Upload sementara memiliki status dan cleanup; error transaksi/penyimpanan tidak meninggalkan referensi file rusak atau file yatim tanpa pembersihan.
- Malware scanning digunakan jika tersedia pada infrastruktur/provider; jika belum tersedia, catat keterbatasan dan tetap batasi ke PDF, ukuran, content validation, akses attachment, serta tidak mengeksekusi dokumen.
- Akses CV dibatasi kandidat pemilik dan recruiter pemilik lowongan aktif; admin hanya melalui tindakan dukungan/moderasi yang dibutuhkan dan diaudit.
- Jangan mencatat CV, token OAuth, secret, session cookie, signature, atau payload sensitif penuh pada log.
- Baseline retensi yang diusulkan: upload sementara 24 jam; event analytics sementara 30 hari; log email tanpa isi pesan 90 hari; lamaran/CV snapshot 12 bulan setelah lowongan ditutup; dokumen verifikasi 90 hari setelah keputusan final.
- Retensi transaksi/audit terkait keuangan serta pengecualian sengketa harus ditetapkan sebelum rilis sesuai kebutuhan operasional dan tinjauan kewajiban yang berlaku. Angka di atas merupakan keputusan produk awal, bukan klaim kepatuhan hukum.
- Penghapusan akun menggunakan workflow terkontrol: hentikan subscription, cabut akses, hapus/anonymize data yang memenuhi syarat, pertahankan hanya record yang wajib dengan alasan dan batas retensi.
- Foreign key tidak boleh menyebabkan penghapusan berantai transaksi/ledger ketika akun dinonaktifkan. Data kandidat tidak boleh disimpan tanpa batas hanya karena akun recruiter masih ada.
- Privacy notice, terms, refund policy, kontak dukungan, dan prosedur permintaan data wajib tersedia sebelum produksi.

## 19. SEO dan halaman publik

- Slug unik dan stabil, meta title/description, canonical URL bersih, Open Graph, sitemap, robots.txt.
- Structured JobPosting hanya memuat informasi publik yang sesuai isi halaman dan kebijakan mesin pencari yang diverifikasi saat implementasi.
- Sitemap hanya berisi lowongan aktif serta halaman perusahaan yang layak dipublikasikan.
- Expired/closed tetap dapat menampilkan informasi publik dan label penutupan tanpa CTA apply; hapus structured JobPosting dan keluarkan dari sitemap.
- Suspended/removed tidak mempublikasikan kembali konten berbahaya; tampilkan halaman unavailable tanpa data sensitif, dengan respons 404/410 sesuai keputusan penghapusan.
- Perubahan status menginvalidasi cache detail, listing, homepage, dan sitemap yang relevan.
- Dashboard, dokumen privat, callback login, apply redirect, dan endpoint transaksi tidak dicache secara publik. robots.txt bukan kontrol akses.

## 20. Arsitektur dan stack

- Laravel sesuai dependency proyek dan PHP hosting yang telah diverifikasi; jangan melakukan downgrade/upgrade hanya berdasarkan asumsi versi.
- Blade + Bootstrap 5 untuk UI MVP. Proyek awal memiliki dependensi Tailwind/Vite; penggantian dilakukan terencana saat implementasi UI, tidak memelihara dua sistem CSS tanpa kebutuhan.
- Build asset dapat berjalan di mesin development/CI dan hasilnya dideploy; produksi tidak membutuhkan Node runtime.
- MySQL/MariaDB dengan transaksi, foreign key, dan index yang sesuai engine aktual.
- Database Queue, Laravel Scheduler melalui cPanel Cron, file/database cache, Laravel Filesystem, external transactional email provider, satu payment gateway, Google authentication.
- Modular monolith mengikuti struktur Laravel yang tersedia. Controller/Form Request/Policy/Model tetap konvensional; business actions/services dipisah menurut domain. Tidak wajib framework module/package untuk setiap domain.
- Domain: Identity, Companies, Job Postings, Applications, Subscriptions, Commerce/Credits, Communication, Moderation, Analytics.
- Boundary provider diperlukan untuk gateway/email/storage; hindari abstraction generik tanpa kebutuhan nyata.
- Event pembayaran/publikasi yang wajib diproses memakai outbox atau mekanisme pemulihan setara; queue after-commit saja tidak dianggap jaminan event tidak pernah hilang.
- Redis, Supervisor, Docker, dedicated search, persistent worker, dan microservices tidak menjadi prasyarat MVP.
- Migrasi ke VPS dapat tetap menggunakan database yang sama. Pergantian MySQL ke PostgreSQL adalah proyek migrasi terpisah, bukan sekadar perubahan environment variable.

## 21. Rancangan tabel konseptual

Daftar ini menjadi dasar ERD, bukan perintah membuat semua migration tanpa memeriksa struktur yang sudah ada.

| Domain | Tabel |
|---|---|
| Identity | users, roles, user_roles, social_accounts, job_seeker_profiles |
| Company | companies, recruiter_profiles, recruiter_verifications |
| Job | job_postings, job_categories, locations, skills, job_posting_skills, job_moderation_histories |
| Application | applications, application_status_histories, private_files |
| Engagement | job_saves, job_daily_metrics; event sementara sesuai desain deduplikasi |
| Subscription | subscriptions, subscription_preferences, email_suppressions |
| Commerce | products, packages, package_items, purchase_orders, purchase_order_items, invoices, payment_attempts, payment_webhook_events, refund_requests |
| Credit | credit_lots, credit_reservations, credit_ledger_entries |
| Communication | outbox_events, email_campaigns, email_batches, email_deliveries, notifications jika diperlukan |
| Governance | job_reports, audit_logs, settings |
| Infrastructure existing | jobs, job_batches, failed_jobs, cache, cache_locks, sessions sesuai konfigurasi |

- Constraints minimum: unique social identity, user-role, application user–job, saved user–job, provider event, payment reference dalam scope provider, ledger operation key, campaign recipient.
- Index berdasarkan query nyata, termasuk kombinasi status/waktu publikasi, company/status, kategori/lokasi, application job/status, kredit owner/product/expiry, dan antrean status/waktu proses.
- Hindari index seluruh kolom tanpa evaluasi biaya write. Eager loading dan pagination wajib untuk daftar besar.
- Gunakan unique reservation/consumption operation untuk mencegah publikasi ganda; batas satu aplikasi bukan hanya pemeriksaan controller.
- Screening questions, application answers, recruiter team memberships, featured/boost, dan tabel enterprise tidak masuk migration MVP.

## 22. Konfigurasi

Infrastructure config melalui environment: koneksi database, queue, cache, storage, provider credentials, URL aplikasi, konfigurasi proxy yang dipercaya. Secret tidak masuk database settings umum atau source code.

Business config melalui settings bertipe dan tervalidasi: batas upload, durasi/paket, biaya kredit, reservasi timeout, closing-soon threshold, batch email, batas kirim, retry, retensi, zona waktu tampilan.

- Perubahan setting sensitif hanya admin berwenang, tercatat dengan nilai lama/baru yang sudah disensor bila perlu.
- Batas provider/hosting tetap menjadi batas atas; admin tidak dapat memasukkan nilai sembarang tanpa validasi.
- Harga, durasi, biaya, dan hak pembelian disnapshot pada saat transaksi terkait agar perubahan konfigurasi tidak retroaktif.
- Nilai status domain didefinisikan sebagai enum/konstanta kode yang terpusat; bukan status bebas yang dapat diubah admin.
- Cache settings memiliki invalidasi yang jelas; perubahan izin/suspension tidak boleh menunggu cache lama habis.

## 23. Operasional shared hosting

- Verifikasi PHP web dan CLI, ekstensi, versi database, memory/time limit, interval cron, kemampuan menjalankan artisan, koneksi keluar SMTP/API, HTTPS, dan disk sebelum menyetujui hosting.
- Document root wajib menunjuk `public`; `.env`, vendor, storage private, dan backup tidak dapat diakses melalui HTTP.
- Cron menjalankan scheduler pada interval yang tersedia, target setiap menit. Worker dijalankan secara terbatas dengan batas waktu/jumlah job sesuai hosting, bukan diasumsikan hidup permanen.
- Scheduler/worker memerlukan lock anti-overlap dengan expiry dan pemulihan setelah proses mati. Perubahan driver lock harus diuji saat scaling.
- Timeout job, retry-after, lease, dan batas waktu worker harus konsisten agar job tidak diproses dua worker secara tak sengaja.
- Pisahkan antrean transaksi dan campaign; batasi durasi batch dan gunakan chunk agar sesuai memory limit.
- Cron menangani expiry lowongan/credit/reservasi, outbox, queue, payment reconciliation, cleanup, dan pemantauan heartbeat.
- Tidak boleh bergantung hanya pada cron untuk menolak apply pada lowongan yang sudah habis waktunya.
- Backup database dan file privat terenkripsi/terproteksi di lokasi terpisah dari hosting, dengan retensi dan uji restore. Target awal yang diusulkan: RPO 24 jam dan RTO 8 jam, wajib dibuktikan sebelum rilis.
- Deploy memakai backup, maintenance strategy bila dibutuhkan, migration kompatibel, asset build, cache refresh, smoke test, dan rollback aplikasi. Rollback database destruktif tidak dijalankan otomatis pada data produksi.
- Cloudflare untuk DNS/TLS/cache asset/proteksi yang tersedia; origin tetap memvalidasi input dan rate limit. HTTPS origin, trusted proxies, dan larangan cache data privat wajib diverifikasi.

## 24. Keamanan minimum

- HTTPS, secure session cookies, CSRF untuk aksi berbasis session, rate limit login/apply/report/upload, validasi server, escaped output/sanitasi rich text, parameterized queries.
- Authorization object-level pada setiap lowongan, lamaran, file, invoice, kredit, serta aksi admin.
- Payment webhook memakai verifikasi signature provider; pengecualian CSRF hanya pada endpoint webhook yang membutuhkan, bukan seluruh modul payment.
- Tidak mengirim kredensial, token, atau detail exception ke pengguna.
- Admin actions, akses dokumen sensitif, status changes, dan perubahan komersial diaudit dengan informasi minimum yang diperlukan.
- Restore backup, suspension, refund, dan perubahan role diuji sebagai bagian dari kesiapan produksi.

## 25. Target kualitas dan kapasitas

Target awal berikut adalah acceptance budget yang perlu dibuktikan, bukan klaim kapasitas shared hosting:

- Listing/detail publik p95 ≤ 2 detik pada dataset uji 10.000 lowongan dengan 20 pengguna virtual serentak, tanpa menghitung waktu website external.
- Error rate server < 1% pada skenario beban uji normal; jalankan beban hanya pada lingkungan yang diizinkan.
- Perubahan status/akses keamanan berlaku segera pada request berikutnya.
- Heartbeat cron yang hilang lebih dari 5 menit dan transaksi verifikasi tertunda lebih dari 15 menit menghasilkan alert operasional melalui kanal yang dikonfigurasi.
- Email campaign menampilkan queued/sent/failed/skipped serta perkiraan antrean berdasarkan batas provider; kapasitas 28.450 subscriber dari PRD awal adalah contoh volume, bukan target SLA MVP.
- Perkiraan waktu minimum pengiriman dihitung dari jumlah penerima dan kuota provider; jika kebutuhan bisnis melampaui kapasitas, sesuaikan frekuensi atau migrasikan worker/provider sebelum menjanjikan SLA.
- Pertimbangkan migrasi ketika CPU throttling, error memory, query lambat, antrean terus tumbuh, storage mendekati batas, atau target respons gagal meskipun query dan batching telah diperbaiki.

## 26. Acceptance criteria utama

| Skenario | Hasil yang wajib |
|---|---|
| Login pertama | Role publik dipilih; tidak bisa memperoleh Super Admin melalui payload |
| Akses lintas recruiter | Detail kandidat, CV, invoice dan job mutation milik recruiter lain ditolak |
| Saldo tidak cukup | Draft tetap tersimpan; tidak ada reservasi/publikasi |
| Submit/approve bersamaan | Satu reservasi dan satu konsumsi; saldo tidak negatif |
| Moderasi reject/timeout | Alasan terlihat dan reservasi dilepas tanpa menghidupkan kredit expired |
| Webhook diulang | Satu pembayaran menghasilkan satu grant kredit |
| Webhook salah/terlambat | Signature salah ditolak; pembayaran terlambat direkonsiliasi tanpa kehilangan pencatatan dana |
| Refund pending/gagal | Kredit dibekukan selama proses dan status mengikuti hasil terverifikasi |
| External apply | Redirect hanya ke tujuan tersimpan yang disetujui; metrik bernama Apply Click |
| Internal apply diulang | Hanya satu lamaran dengan CV privat dan snapshot stabil |
| Lowongan habis sebelum cron | Tidak dapat menerima lamaran/redirect baru |
| Recruiter suspended | Aksi terautentikasi dan publikasi terkait diblokir segera |
| Unsubscribe saat antre | Email yang belum diserahkan ke provider tidak dikirim lagi |
| Retry campaign/outbox | Tidak membuat penerima/event bisnis duplikat; hasil provider ambigu terlihat |
| Gaji tersembunyi | Tidak muncul pada HTML, structured data, filter gaji, atau respons publik |
| Deploy dan recovery | Scheduler/queue berfungsi, file privat tidak publik, backup berhasil direstore |

Pengujian meliputi unit untuk aturan domain, feature/integration untuk izin dan alur utama, pengujian concurrency pada engine database target, sandbox provider untuk payment/email, dan pemeriksaan UI desktop/mobile. Jangan mengandalkan SQLite saja untuk membuktikan locking/transaksi MySQL.

## 27. Tahapan implementasi

1. **Fondasi:** audit skeleton/hosting, setup development sesuai AGENTS.md, ERD, enum status, autentikasi, role, policy, company dan verifikasi.
2. **External end-to-end:** katalog/paket, sandbox payment, ledger/reservasi, draft wizard, moderasi, listing/detail, external apply.
3. **Internal recruitment:** lamaran, penyimpanan privat, snapshot CV, status kandidat, dashboard terkait.
4. **Distribusi:** save/share/report, subscription/preferences, outbox/queue/email, SEO, analytics agregat.
5. **Kesiapan produksi:** uji akses dan transaksi bersamaan, provider integration, cron, backup restore, beban, operasional admin, serta keputusan peluncuran.

Setiap tahap menghasilkan alur yang dapat diuji; tidak menunggu seluruh dashboard selesai untuk memvalidasi pembayaran, kredit, dan authorization.

## 28. Keputusan sebelum peluncuran

| Keputusan | Penanggung jawab | Batas waktu |
|---|---|---|
| Gateway, metode bayar, refund dan rekonsiliasi | Pemilik produk + engineering | Sebelum integrasi pembayaran |
| Paket, harga, biaya/pajak, masa berlaku, durasi tayang | Pemilik produk | Sebelum katalog produksi |
| Provider email, kuota, domain pengirim, bounce/complaint | Pemilik produk + engineering | Sebelum pengiriman nyata |
| Hosting, PHP web/CLI, cron, DB, storage dan backup | Engineering + pemilik hosting | Sebelum deployment |
| OAuth credentials, domain/callback produksi | Pemilik produk + engineering | Sebelum login produksi |
| Bukti verifikasi dan SOP penanganan scam | Operasional | Sebelum menerima recruiter publik |
| Retensi final, privacy/terms, refund policy | Pemilik produk + peninjau yang sesuai | Sebelum menerima data/pembayaran nyata |
| MFA admin, admin awal, kanal alert, pemilik respons insiden | Operasional + engineering | Sebelum akses admin produksi |

Pekerjaan lokal dapat berjalan dengan sandbox dan konfigurasi dummy yang aman. Keputusan yang belum tersedia tidak diisi dengan secret palsu, harga yang dianggap final, atau janji kapasitas yang belum diuji.

## 29. Ringkasan revisi dari v4.0

- Memisahkan tabel lowongan dari tabel antrean Laravel.
- Menetapkan kepemilikan perusahaan, batas akses, dan aturan multi-role.
- Memisahkan status editorial, pembayaran, verifikasi, serta penangguhan.
- Menentukan reservasi, konsumsi, expiry, reversal dan audit kredit.
- Menambahkan webhook idempotency, snapshot pembelian, rekonsiliasi, dan refund.
- Menegaskan internal recruitment tetap melalui moderasi dan kredit.
- Membatasi edit live, restore, deadline, dan publikasi ulang agar konsisten.
- Menambahkan private storage, snapshot CV, retensi, serta workflow penghapusan data.
- Mendefinisikan matching subscription, unsubscribe, outbox, deduplikasi, suppression, dan batas jaminan pengiriman.
- Mengganti klaim share/application yang tidak terukur dengan definisi metrik yang jujur.
- Memindahkan featured/boost/screening/teams dari MVP ke fase lanjutan.
- Menambahkan acceptance criteria, target uji, kesiapan hosting, dan keputusan peluncuran yang masih diperlukan.
