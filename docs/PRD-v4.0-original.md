# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# SaaS Job Distribution & Recruitment Platform

**Versi:** 4.0  
**Status:** Product Definition / MVP Architecture  
**Model Bisnis:** SaaS / B2B2C  
**Core Product:** Job Distribution & Recruitment Reach  
**Initial Infrastructure:** Shared Hosting / cPanel  
**Architecture:** Modular Monolith  
**Authentication:** Google OAuth / OpenID Connect  
**Roles:** Job Seeker, Recruiter, Super Admin

---

# 1. PRODUCT VISION

Membangun platform SaaS distribusi lowongan yang menghubungkan recruiter, perusahaan, dan pencari kerja melalui satu jaringan recruitment yang mudah digunakan, terukur, dan dapat berkembang sesuai pertumbuhan pengguna.

Platform memungkinkan recruiter:

1. Mempublikasikan lowongan.
2. Memperluas jangkauan recruitment.
3. Mengarahkan kandidat ke sistem recruitment milik recruiter.
4. Menerima kandidat langsung melalui platform.
5. Mengukur performa distribusi lowongan.
6. Membeli layanan publikasi sesuai kebutuhan.

Job Seeker dapat:

1. Mencari lowongan.
2. Menyimpan lowongan.
3. Melamar pekerjaan.
4. Subscribe informasi lowongan.
5. Mendapatkan email ketika lowongan relevan tersedia.

Platform dibangun dengan prinsip:

> **Distribution First — Infrastructure Ready to Scale**

MVP berjalan menggunakan shared hosting/cPanel, tetapi business logic tidak boleh bergantung secara permanen pada shared hosting.

---

# 2. PRODUCT POSITIONING

Platform diposisikan sebagai:

> **Job Distribution & Recruitment Reach Platform**

Core value:

**Publish → Distribute → Reach → Track → Apply**

Platform bukan ATS penuh.

Recruiter tidak diwajibkan mengganti recruitment system yang sudah digunakan.

---

# 3. TARGET USERS

Platform memiliki tiga role utama:

## Job Seeker

Pencari kerja yang menggunakan platform untuk menemukan dan mendapatkan informasi lowongan.

## Recruiter

Perusahaan atau recruiter yang ingin mempublikasikan dan memperluas jangkauan lowongan.

## Super Admin

Pengelola platform, transaksi, pricing, recruiter, moderation, dan konfigurasi sistem.

---

# 4. AUTHENTICATION

Platform menggunakan:

**Continue with Google**

untuk registrasi dan login.

Flow:

`Continue with Google`

→ `Google Authentication`

→ `Check Account`

→ `Login / Create User`

Pada login pertama:

`Select Account Type`

Pilihan:

**Saya Mencari Pekerjaan**

atau

**Saya Seorang Recruiter**

Super Admin tidak tersedia pada registrasi publik.

---

# 5. USER ROLE ARCHITECTURE

Sistem tidak mengunci satu user hanya memiliki satu role selamanya.

Gunakan:

`users`

`roles`

`user_roles`

Contoh:

User A:

`JOB_SEEKER`

Kemudian di masa depan dapat memiliki:

`JOB_SEEKER + RECRUITER`

Role:

`JOB_SEEKER`

`RECRUITER`

`SUPER_ADMIN`

Role Super Admin hanya dapat diberikan melalui sistem administratif.

---

# 6. SOCIAL AUTHENTICATION

Gunakan:

### users

- id
- name
- email
- avatar
- status
- last_login_at
- created_at
- updated_at

### social_accounts

- id
- user_id
- provider
- provider_user_id
- provider_email
- created_at
- updated_at

Provider MVP:

`GOOGLE`

Struktur disiapkan agar nantinya dapat mendukung:

- Microsoft.
- Apple.
- Provider lainnya.

Platform tidak pernah menyimpan password Google.

---

# 7. JOB SEEKER

Job Seeker dapat:

- Login Google.
- Browse Jobs.
- Search.
- Filter.
- View Job.
- Save Job.
- Share Job.
- Subscribe.
- Manage Preferences.
- Apply.
- View Internal Applications.
- Report Job.

---

# 8. JOB SEEKER ONBOARDING

Setelah login:

`Google Login`

→ `Job Seeker`

→ `Complete Profile`

Optional:

- Phone.
- Location.
- Preferred Category.
- Preferred Location.
- Job Level.
- Employment Type.
- CV.

Kemudian:

**Dapatkan informasi lowongan terbaru melalui email**

Pilihan:

`Subscribe`

atau

`Lewati`

Default:

`NON-SUBSCRIBED`

---

# 9. RECRUITER

Recruiter dapat:

- Login Google.
- Complete Recruiter Profile.
- Create Company.
- Submit Verification.
- Purchase Credit.
- Create Job.
- Choose Recruitment Method.
- Manage Jobs.
- View Analytics.
- Manage Internal Candidates.
- View Transactions.

---

# 10. RECRUITER VERIFICATION

Data:

### Recruiter

- Name.
- Email.
- Phone.
- Position.
- LinkedIn.

### Company

- Company Name.
- Logo.
- Industry.
- Company Email.
- Website.
- Address.
- Description.

Status:

`PENDING`

`VERIFIED`

`REJECTED`

`SUSPENDED`

Verified recruiter mendapatkan:

**Verified Recruiter Badge**

---

# 11. HYBRID RECRUITMENT

Recruiter memilih salah satu metode.

## EXTERNAL

**Perluas Jangkauan**

Untuk recruiter yang sudah memiliki recruitment system.

## INTERNAL

**Rekrutmen di Platform**

Untuk recruiter yang ingin menerima kandidat melalui platform.

---

# 12. EXTERNAL RECRUITMENT

Recruiter memasukkan:

`External Application URL`

Contoh:

`https://career.company.com/jobs/123`

Platform menangani:

- Publication.
- Search.
- SEO.
- Distribution.
- Subscription.
- Sharing.
- Tracking.
- Analytics.

Flow:

`Job Seeker`

→ `Job Detail`

→ `Lamar Sekarang`

→ `Track Apply Click`

→ `Redirect`

→ `Recruiter's Recruitment System`

---

# 13. INTERNAL RECRUITMENT

Flow:

`Job Detail`

→ `Lamar Sekarang`

→ `Application Form`

→ `Upload CV`

→ `Submit`

→ `Recruiter Candidate Dashboard`

Minimum data:

- Name.
- Email.
- Phone.
- CV.

Optional:

- Portfolio.
- LinkedIn.
- Education.
- Experience.
- Expected Salary.

---

# 14. INTERNAL CANDIDATE STATUS

Status:

`NEW`

`REVIEWED`

`SHORTLISTED`

`REJECTED`

`HIRED`

MVP tidak membangun ATS kompleks.

---

# 15. CREATE JOB

Gunakan multi-step wizard.

## Step 1 — Basic

- Job Title.
- Company.
- Category.
- Employment Type.
- Job Level.
- Vacancy.

## Step 2 — Location

- Province.
- City.
- Work Location.
- On-site / Hybrid / Remote.

## Step 3 — Compensation

- Minimum Salary.
- Maximum Salary.
- Salary Visibility.

## Step 4 — Detail

- Description.
- Responsibilities.
- Qualifications.
- Education.
- Experience.
- Skills.
- Benefits.

## Step 5 — Recruitment Method

`EXTERNAL`

atau

`INTERNAL`

## Step 6 — Deadline

- Application Deadline.
- Publish Duration.

## Step 7 — Preview & Submit

---

# 16. JOB LIFECYCLE

`DRAFT`

→ `WAITING_PAYMENT`

→ `WAITING_APPROVAL`

→ `PUBLISHED`

→ `EXPIRED`

atau:

`CLOSED`

Exception:

`REJECTED`

`SUSPENDED`

---

# 17. JOB MODERATION

Super Admin review:

- Recruiter.
- Company.
- Position.
- Description.
- Qualification.
- Salary.
- Location.
- Recruitment Method.
- External URL.
- Payment/Credit.
- Verification.

Action:

`APPROVE`

atau

`REJECT`

Reject wajib memiliki alasan.

---

# 18. JOB SEARCH

Search:

- Position.
- Company.
- Keyword.
- Skill.

Filter:

- Category.
- Location.
- Salary.
- Employment Type.
- Job Level.
- Experience.
- Education.
- Work Arrangement.
- Date Posted.

MVP menggunakan database search yang telah dioptimasi.

Tidak menggunakan dedicated search server pada fase shared hosting.

---

# 19. JOB DETAIL

Menampilkan:

- Position.
- Company.
- Verified Badge.
- Location.
- Employment Type.
- Work Arrangement.
- Salary.
- Vacancy.
- Published Date.
- Deadline.
- Description.
- Responsibilities.
- Qualifications.
- Skills.
- Benefits.

CTA:

**Lamar Sekarang**

---

# 20. APPLY TRACKING

Untuk External Recruitment:

`/jobs/{job}/apply`

Flow:

`Request`

→ `Record Apply Click`

→ `302 Redirect`

→ `External URL`

Data minimal:

- Job ID.
- Timestamp.
- Traffic Source.
- Referrer.

Gunakan istilah:

**Apply Click**

bukan:

**Application**

untuk external recruitment.

---

# 21. SUBSCRIPTION

Job Seeker dapat memilih:

`SUBSCRIBE`

atau

`NON-SUBSCRIBE`

Subscription gratis.

Preferences:

- Category.
- Location.
- Job Level.
- Employment Type.

---

# 22. EMAIL NOTIFICATION

Ketika lowongan Published:

`Job Published`

→ `Find Matching Subscribers`

→ `Create Email Batches`

→ `Queue`

→ `Cron Worker`

→ `Send Email`

Email:

- Position.
- Company.
- Location.
- Salary.
- Deadline.

CTA:

**Lihat Lowongan**

Wajib memiliki:

**Unsubscribe**

dan:

**Atur Preferensi**

---

# 23. EMAIL BATCHING

Karena MVP menggunakan shared hosting, email subscriber tidak boleh diproses sekaligus.

Contoh:

`28,450 Matching Subscribers`

dibagi menjadi:

`Batch #1`

`Batch #2`

`Batch #3`

...

Setiap batch diproses melalui queue.

Ukuran batch harus configurable oleh Super Admin/system configuration.

Contoh:

`EMAIL_BATCH_SIZE=250`

Nilai aktual disesuaikan dengan kapasitas hosting dan provider email.

---

# 24. EMAIL RATE LIMIT

Sistem harus memiliki konfigurasi:

- Maximum email per batch.
- Maximum email per minute.
- Maximum email per hour.
- Retry delay.
- Maximum retries.

Tujuannya mencegah:

- Server overload.
- Provider throttling.
- Email account suspension.
- Request timeout.

---

# 25. SAVED JOBS

Job Seeker dapat menyimpan lowongan.

Status:

- Active.
- Closing Soon.
- Expired.

---

# 26. SHARE JOB

Public URL:

`/jobs/{slug}`

Share:

- Copy Link.
- WhatsApp.
- LinkedIn.
- Facebook.
- Threads.
- Telegram.

Share selalu mengarah ke Job Detail Platform.

---

# 27. ANALYTICS MVP

Karena menggunakan shared hosting, analytics dibuat ringan.

Prioritas tracking:

- Job Views.
- Apply Clicks.
- Saves.
- Shares.
- Internal Applications.

MVP tidak wajib mencatat setiap impression secara individual.

Hal ini mengurangi jumlah write ke database.

---

# 28. TRAFFIC SOURCE

Sistem dapat menyimpan:

- Direct.
- Google.
- Email.
- WhatsApp.
- LinkedIn.
- Facebook.
- Threads.
- Telegram.
- Other.

Gunakan UTM/referrer jika tersedia.

---

# 29. RECRUITER ANALYTICS

Recruiter melihat:

- Job Views.
- Apply Clicks.
- Saves.
- Shares.
- Internal Applications.
- Shortlisted.
- Hired.

Advanced analytics dikembangkan setelah migrasi infrastruktur jika diperlukan.

---

# 30. PRICING

Harga dikontrol Super Admin.

Tidak boleh hard-coded.

Produk:

- Job Distribution.
- Hosted Recruitment.
- Featured Job.
- Job Boost.
- Extend Job.

---

# 31. PACKAGE SYSTEM

Contoh struktur:

### Starter

- Job Credits.
- Validity.
- Basic Analytics.

### Business

- More Credits.
- Longer Validity.
- Featured Credit.
- Analytics.

### Enterprise

Disiapkan untuk fase berikutnya.

Super Admin menentukan:

- Name.
- Price.
- Credits.
- Validity.
- Features.
- Status.

---

# 32. CREDIT SYSTEM

Recruiter memiliki:

`Job Credit Balance`

Contoh:

`10`

Publish Job:

`-1`

Balance:

`9`

Semua perubahan dicatat melalui Credit History.

---

# 33. PAYMENT

Flow:

`Package`

→ `Invoice`

→ `Payment Gateway`

→ `Payment`

→ `Webhook`

→ `Verification`

→ `Credit Added`

Status:

`PENDING`

`PAID`

`FAILED`

`EXPIRED`

`REFUNDED`

---

# 34. COMPANY PAGE

URL:

`/companies/{slug}`

Menampilkan:

- Logo.
- Name.
- Verified Badge.
- Industry.
- Location.
- Website.
- Description.
- Active Jobs.

---

# 35. JOB REPORT

Job Seeker dapat melaporkan:

- Scam.
- Fake Company.
- Recruitment Fee.
- Suspicious Link.
- Incorrect Information.
- Job Closed.
- Inappropriate Content.

Super Admin dapat:

`DISMISS`

`SUSPEND JOB`

`SUSPEND RECRUITER`

---

# 36. LANDING PAGE

## Hero

**Temukan Lowongan yang Sesuai untuk Anda**

Search:

`Posisi, perusahaan atau kata kunci`

`Lokasi`

CTA:

**Cari Lowongan**

## Featured Jobs

## Latest Jobs

## Categories

## Companies

## Subscription CTA

**Dapatkan Informasi Lowongan Terbaru**

## Recruiter CTA

**Perluas Jangkauan Rekrutmen Anda**

---

# 37. JOB SEEKER DASHBOARD

- Recommended Jobs.
- Latest Jobs.
- Saved Jobs.
- Applications.
- Subscription.
- Preferences.
- Profile.

---

# 38. RECRUITER DASHBOARD

- Verification Status.
- Credit Balance.
- Active Jobs.
- Draft Jobs.
- Waiting Approval.
- Expired Jobs.
- Job Performance.
- Transactions.

---

# 39. SUPER ADMIN DASHBOARD

### Revenue

- Today.
- Monthly.
- Total.

### Recruiters

- Pending.
- Verified.
- Suspended.

### Jobs

- Active.
- Waiting Approval.
- Rejected.
- Expired.

### Job Seekers

- Registered.
- Subscribers.

### Transactions

- Paid.
- Pending.
- Failed.

---

# 40. MVP TECHNOLOGY STACK

Karena fase pertama menggunakan shared hosting/cPanel:

## Backend

**Laravel**

Gunakan versi Laravel modern yang kompatibel dengan versi PHP yang disediakan hosting pada saat development/deployment.

## Frontend

**Laravel Blade**

dengan:

**Bootstrap 5**

atau Tailwind jika build workflow hosting mendukung dengan baik.

Prioritas MVP:

**Blade + Bootstrap 5**

untuk deployment yang sederhana.

## Database

**MySQL / MariaDB**

## Authentication

**Google OAuth 2.0 / OpenID Connect**

## Queue

**Laravel Database Queue**

## Scheduler

**Laravel Scheduler + cPanel Cron**

## Cache

**File / Database Cache**

## Storage

**Local Storage**

dengan abstraction Laravel Filesystem agar nantinya dapat dipindahkan ke S3-compatible object storage.

## Email

External transactional email provider melalui SMTP/API.

## CDN / DNS / Security

**Cloudflare**

## Architecture

**Modular Monolith**

---

# 41. INITIAL INFRASTRUCTURE

Arsitektur MVP:

`Internet`

↓

`Cloudflare`

↓

`Shared Hosting / cPanel`

↓

`Laravel Application`

↓

`MySQL`

Supporting process:

`Database Queue`

↓

`cPanel Cron`

↓

`Queue Worker`

External integrations:

`Google Authentication`

`Email Provider`

`Payment Gateway`

---

# 42. MODULAR MONOLITH

Struktur business domain dipisahkan secara modular.

Contoh:

`Modules/Auth`

`Modules/Users`

`Modules/Companies`

`Modules/Recruiters`

`Modules/Jobs`

`Modules/Applications`

`Modules/Subscriptions`

`Modules/Payments`

`Modules/Credits`

`Modules/Notifications`

`Modules/Analytics`

Semua masih berjalan dalam satu Laravel Application.

Tujuannya:

- Deployment sederhana.
- Maintenance mudah.
- Business domain terpisah.
- Mudah dipindahkan ke infrastruktur lebih besar.

---

# 43. DATABASE REQUIREMENTS

Database harus menggunakan:

- Primary Key.
- Foreign Key jika sesuai.
- Index.
- Unique Constraint.
- Pagination.

Field yang sering digunakan untuk filter wajib dievaluasi untuk indexing.

Contoh:

### jobs

Index:

- status
- category_id
- company_id
- published_at
- deadline

### applications

Index:

- job_id
- job_seeker_id
- status

### subscriptions

Index:

- user_id
- status

Query tidak boleh mengambil seluruh data tanpa pagination.

---

# 44. PAGINATION

Semua daftar besar wajib menggunakan server-side pagination.

Contoh:

`/jobs?page=1`

Default:

`20 jobs/page`

Termasuk:

- Job Listing.
- Candidates.
- Recruiters.
- Users.
- Transactions.
- Audit Logs.

---

# 45. CACHE STRATEGY

Cache digunakan untuk data yang jarang berubah.

Contoh:

- Job Categories.
- Locations.
- Package Information.
- Homepage sections.
- Platform Settings.

MVP:

`File Cache`

atau:

`Database Cache`

Saat pindah ke server:

`Redis`

Business logic tidak boleh bergantung langsung pada implementation cache tertentu.

---

# 46. QUEUE ARCHITECTURE

Proses berat tidak dijalankan langsung pada HTTP request.

Queue digunakan untuk:

- Email Notification.
- Email Batch.
- Invoice Email.
- Notification.
- Analytics Processing jika diperlukan.

MVP:

`QUEUE_CONNECTION=database`

Future:

`QUEUE_CONNECTION=redis`

---

# 47. CRON ARCHITECTURE

cPanel Cron menjalankan:

### Laravel Scheduler

`php artisan schedule:run`

Scheduler menangani:

- Expire Jobs.
- Subscription Processing.
- Cleanup.
- Scheduled Notification.
- Maintenance Task.

Queue dapat diproses melalui command worker yang sesuai dengan batasan shared hosting.

---

# 48. FILE STORAGE

MVP:

`Local Storage`

File yang dapat disimpan:

- Company Logo.
- Candidate CV.
- Recruiter Verification Document.

Gunakan Laravel Filesystem abstraction.

Future:

`S3-compatible Object Storage`

Sehingga tidak perlu mengubah business logic ketika storage dipindahkan.

---

# 49. SEARCH ARCHITECTURE

MVP menggunakan:

**MySQL indexed search / FULLTEXT jika sesuai.**

Hindari query berat tanpa index.

Future:

`Meilisearch / OpenSearch`

Search abstraction sebaiknya dipersiapkan sehingga engine dapat diganti.

---

# 50. CLOUDFLARE

Cloudflare ditempatkan di depan hosting.

Digunakan untuk:

- DNS.
- HTTPS.
- CDN.
- Static Asset Caching.
- WAF capabilities.
- Bot protection.
- Rate limiting jika tersedia.

Tujuan:

mengurangi request yang harus diproses origin hosting dan menambah lapisan proteksi.

---

# 51. SECURITY

Minimum requirements:

- HTTPS.
- Google OAuth validation.
- OAuth state validation.
- Secure Session.
- CSRF Protection.
- XSS Protection.
- SQL Injection Protection.
- Input Validation.
- Rate Limiting.
- Login Protection.
- Secure Upload.
- File Type Validation.
- File Size Validation.
- External URL Validation.
- Payment Webhook Verification.
- Authorization.
- Audit Logging.
- Database Backup.

---

# 52. EXTERNAL URL SECURITY

External Application URL harus divalidasi.

Minimum:

- HTTPS preferred/required according to policy.
- Valid URL format.
- Block dangerous schemes.
- Prevent `javascript:` URLs.
- Prevent malformed redirect URLs.

Super Admin melihat destination URL ketika melakukan moderation.

---

# 53. SEO REQUIREMENTS

Job Distribution membutuhkan SEO.

Setiap job memiliki:

- Unique Slug.
- Meta Title.
- Meta Description.
- Open Graph.
- Structured Job Data.
- Canonical URL.

Sistem menyediakan:

- Sitemap.
- robots.txt.
- Expired Job handling.

---

# 54. CORE DATABASE TABLES

### Authentication

`users`

`roles`

`user_roles`

`social_accounts`

### Recruiter

`companies`

`recruiter_profiles`

`recruiter_verifications`

### Jobs

`jobs`

`job_categories`

`job_locations`

`job_skills`

### Applications

`applications`

`screening_questions`

`application_answers`

### Engagement

`job_views`

`job_apply_clicks`

`job_saves`

`job_shares`

### Subscription

`subscriptions`

`subscription_preferences`

### Commercial

`products`

`packages`

`package_features`

`transactions`

`invoices`

`recruiter_credits`

`credit_histories`

### Communication

`notifications`

`email_batches`

`email_logs`

### System

`job_reports`

`audit_logs`

`settings`

---

# 55. CONFIGURATION-DRIVEN SYSTEM

Konfigurasi yang dapat berubah tidak boleh hard-coded.

Contoh:

- Email batch size.
- Email rate.
- Job duration.
- Pricing.
- Credit cost.
- File upload size.
- Featured duration.
- Boost duration.

Konfigurasi dapat berasal dari:

`.env`

untuk infrastructure configuration.

atau:

`settings`

untuk business configuration yang dikelola Super Admin.

---

# 56. MVP PERFORMANCE PRINCIPLES

Semua development harus mengikuti:

1. Server-side pagination.
2. Database indexing.
3. Avoid N+1 queries.
4. Eager loading.
5. Cache reusable data.
6. Queue heavy processes.
7. Batch email.
8. Limit uploaded files.
9. Optimize images.
10. Avoid unnecessary database writes.

---

# 57. SHARED HOSTING LIMITATIONS

MVP tidak mengasumsikan shared hosting memiliki:

- Redis.
- Supervisor.
- Docker.
- Kubernetes.
- Dedicated Queue Server.
- Node.js Runtime.
- Elasticsearch/OpenSearch.
- Persistent background processes.

Sistem harus tetap berfungsi tanpa komponen tersebut.

---

# 58. INFRASTRUCTURE MIGRATION PRINCIPLE

Aplikasi harus dapat dipindahkan tanpa mengubah core business logic.

### Initial

`Shared Hosting`

`MySQL`

`Database Queue`

`File Cache`

`Local Storage`

↓

### Growth

`VPS / Cloud`

`MySQL/PostgreSQL`

`Redis`

`Redis Queue`

`Object Storage`

↓

### Scale

`Load Balancer`

`Multiple App Servers`

`Dedicated Database`

`Read Replica`

`Redis`

`Dedicated Search`

`Object Storage`

`Autoscaling`

---

# 59. SCALING ROADMAP

## Stage 1 — MVP

Shared Hosting / cPanel.

Target:

validasi product-market fit dan memperoleh recruiter serta Job Seeker pertama.

## Stage 2 — Growth

Migrasi ke VPS/cloud ketika resource hosting menjadi bottleneck.

Tambahkan:

- Redis.
- Persistent Queue Worker.
- Object Storage.
- Dedicated Database jika diperlukan.

## Stage 3 — Scale

Tambahkan:

- Load Balancer.
- Multiple Application Servers.
- Database Optimization.
- Read Replica.
- Dedicated Search.

## Stage 4 — Large Scale

Jika traffic benar-benar membutuhkan:

- Autoscaling.
- Distributed Queue.
- Dedicated Analytics.
- Event Processing.
- Multiple Database/Service Strategy.

Microservices hanya dipertimbangkan berdasarkan kebutuhan nyata.

---

# 60. MIGRATION TRIGGERS

Migrasi dari shared hosting harus dievaluasi jika terjadi:

- Frequent CPU throttling.
- Memory limit errors.
- Database bottleneck.
- Queue backlog terus meningkat.
- Email processing terlalu lambat.
- HTTP response melambat.
- Traffic spike menyebabkan downtime.
- Storage hampir penuh.
- Kebutuhan persistent worker.
- Kebutuhan Redis.
- Kebutuhan horizontal scaling.

Migrasi dilakukan berdasarkan metric, bukan hanya jumlah user terdaftar.

---

# 61. MVP SCOPE

## Authentication

- Google Login.
- Role Selection.
- Session.
- Logout.

## Job Seeker

- Browse.
- Search.
- Filter.
- Job Detail.
- Save.
- Share.
- Subscribe.
- Preferences.
- External Apply.
- Internal Apply.

## Recruiter

- Onboarding.
- Company.
- Verification.
- Package.
- Credit.
- Create Job.
- External Recruitment.
- Internal Recruitment.
- Job Management.
- Candidate List.
- Basic Analytics.

## Super Admin

- Dashboard.
- Users.
- Recruiters.
- Verification.
- Jobs.
- Moderation.
- Pricing.
- Packages.
- Transactions.
- Subscribers.
- Reports.
- Settings.

---

# 62. OUT OF MVP

Belum dibangun:

- Complex ATS.
- Interview Scheduling.
- Psychotest.
- MCU.
- Offering.
- Contract.
- Onboarding.
- AI CV Screening.
- AI Candidate Matching.
- Mobile App.
- Enterprise API.
- ATS Sync.
- Microservices.
- Dedicated Analytics Platform.

---

# 63. PHASE 2

Setelah MVP stabil:

- Featured Jobs.
- Job Boost.
- Advanced Analytics.
- Promo Codes.
- Invoice PDF.
- Recruiter Teams.
- Advanced Screening.
- Advanced Subscription.
- Job Recommendation.
- Company Follow.

---

# 64. PHASE 3

Setelah infrastruktur memungkinkan:

- ATS Integration.
- REST API.
- Webhook.
- JSON/XML Job Feed.
- Bulk Job Import.
- Scheduled Publishing.
- Advanced Recruiter Analytics.

---

# 65. CORE SUCCESS FLOW

## External Recruitment

`Google Login`

→ `Recruiter Verification`

→ `Purchase Credit`

→ `Create Job`

→ `External URL`

→ `Admin Approval`

→ `Publish`

→ `Distribution`

→ `Job Seeker`

→ `Apply Click`

→ `Recruiter's System`

---

## Internal Recruitment

`Recruiter`

→ `Create Job`

→ `Internal Recruitment`

→ `Publish`

→ `Job Seeker`

→ `Apply`

→ `Candidate Dashboard`

---

## Subscription

`Job Seeker`

→ `Subscribe`

→ `Set Preferences`

→ `Matching Job Published`

→ `Queue`

→ `Email`

→ `Job Detail`

→ `Apply`

---

# 66. PRODUCT KPIs

### Users

- Registered Job Seekers.
- Active Job Seekers.
- Subscribers.

### Recruiters

- Registered Recruiters.
- Verified Recruiters.
- Paying Recruiters.
- Repeat Purchases.

### Jobs

- Published Jobs.
- Active Jobs.
- Job Views.
- Apply Clicks.
- Internal Applications.

### Revenue

- Monthly Revenue.
- Package Sales.
- Revenue per Recruiter.

### Subscription

- Active Subscribers.
- Email Delivery.
- Email Clicks.
- Unsubscribe Rate.

### Infrastructure

- Average Response Time.
- Error Rate.
- Queue Backlog.
- Email Processing Time.
- Database Query Performance.
- Storage Usage.

---

# 67. MVP SUCCESS CRITERIA

MVP dianggap berhasil ketika:

### External Recruiter

`Payment → Publish → Distribution → External Apply`

berjalan end-to-end.

### Internal Recruiter

`Publish → Candidate Apply → Candidate Management`

berjalan end-to-end.

### Job Seeker

`Google Login → Search → Job → Apply`

berjalan end-to-end.

### Subscription

`Subscribe → Matching Job → Email`

berjalan end-to-end.

### Super Admin

dapat mengendalikan:

`Recruiters + Jobs + Pricing + Payments + Moderation`

---

# 68. FINAL TECHNICAL DIRECTION

Teknologi MVP:

**Laravel**

+

**Blade**

+

**Bootstrap 5**

+

**MySQL/MariaDB**

+

**Database Queue**

+

**cPanel Cron**

+

**Google Authentication**

+

**External Email Provider**

+

**Payment Gateway**

+

**Cloudflare**

Deployment awal:

**Shared Hosting / cPanel**

Arsitektur:

**Modular Monolith**

Prinsip utama:

> **Mulai sederhana pada shared hosting, tetapi jangan mendesain aplikasi yang hanya dapat hidup di shared hosting.**

Ketika produk berkembang, infrastruktur dapat ditingkatkan dari:

**Shared Hosting → VPS/Cloud → Horizontal Scaling**

tanpa membuat ulang core SaaS.