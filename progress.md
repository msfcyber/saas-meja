# Progress Pengembangan

Terakhir diperbarui: 4 September 2026

## Selesai - Frontend Foundation

- [x] Landing page SaaS responsif dengan CTA register dan demo menu.
- [x] Identitas visual Meja, token warna, tipografi, dark mode, dan reduced motion.
- [x] Customer menu mobile-first: outlet/meja, kategori, pencarian, detail produk, modifier, catatan, dan cart CTA.
- [x] Checkout demo: ringkasan item, identitas meja, nama opsional, metode pembayaran, pajak, dan total.
- [x] Tracking order demo: status aktif, timeline, estimasi, detail item, dan akses struk.
- [x] Dashboard owner: metrik outlet, penjualan terverifikasi hari ini, order aktif, dan kesiapan katalog/meja aktual.
- [x] Live order board: filter status, detail item, dan transisi status order berbasis database.
- [x] Pengelolaan produk: kategori, pencarian, harga, status tersedia/habis, dan aksi produk; create, update, delete, availability, varian, modifier, serta lifecycle gambar sudah tersedia.
- [x] Pengelolaan meja: zona, status, pratinjau QR, download, cetak, dan regenerasi; create, update, toggle status, dan aksi QR sudah tersedia.
- [x] Pengelolaan outlet: identitas lokasi, status aktif/order, zona waktu, pajak default, dan limit plan.
- [x] Pengelolaan staf: membership tenant, role operasional, status aktif, audit, dan proteksi owner.
- [x] Branding dan lokalisasi halaman login/register utama.

Beberapa halaman marketing dan demo tetap menggunakan data demo; flow QR publik, checkout, tracking, live order, dan laporan sudah terhubung ke backend.

## Selesai - Static Accessibility Hardening

- [x] Menambahkan label/error association `aria-invalid` dan `aria-describedby` pada form auth, onboarding, settings, produk, meja, checkout, dan two-factor.
- [x] Menambahkan skip link, landmark utama, state navigasi aktif, live announcement, dan label kontrol ikon.
- [x] Memperbaiki target tombol mobile, dialog/sheet overflow, kontras CTA checkout, dan menghapus positive `tabIndex` pada auth.
- [x] Browser-level accessibility audit dan verifikasi viewport lulus pada desktop, tablet, dan mobile 360 px dengan Playwright/axe.

## Selesai - Backend Foundation: Data Layer

- [x] Memasang `spatie/laravel-permission` v7.4.2 dan mengaktifkan teams dengan `tenant_id`.
- [x] Model dan relasi tenant, outlet, membership user, kategori, produk, varian, modifier, opsi modifier, meja, dan QR token.
- [x] Migration dengan index, unique constraint, cascade/restrict rule, serta foreign key komposit tenant-aware.
- [x] Factory untuk seluruh model domain dan state umum seperti suspended, unavailable, inactive, serta revoked.
- [x] Seeder idempotent untuk 12 permission, empat role outlet, owner demo, katalog Kedai Sore, modifier, 12 meja, dan token QR berbentuk hash.
- [x] Test integritas factory graph, penolakan relasi lintas tenant, role/permission tenant, dan idempotensi seeder.
- [x] Migration dan seeder diterapkan pada database lokal.

## Selesai - Backend Foundation: Tenancy & Authorization

- [x] Request-scoped tenant/outlet context dengan fallback membership dan session switch.
- [x] Tenant context berjalan sebelum route model binding dan tersinkron dengan Spatie team ID.
- [x] Global tenant/outlet scopes pada seluruh model bisnis terkait.
- [x] Policy/Gate dan permission middleware pada dashboard, order, produk, serta meja.
- [x] Selector tenant/outlet dan navigasi berbasis permission pada sidebar.
- [x] Feature test tenant isolation, policy lintas tenant, permission route, dan context switching.

## Selesai - Owner Onboarding

- [x] Registrasi baru diarahkan khusus ke onboarding tanpa mengubah redirect login atau verifikasi user lama.
- [x] Onboarding tiga tahap untuk bisnis, outlet pertama, zona waktu, dan pajak opsional.
- [x] Provisioning tenant, membership owner, empat role, outlet, dan tax setting dalam satu transaction.
- [x] Menyimpan tarif pajak dalam integer basis points dan menjaga relasi tenant/outlet di database.
- [x] Feature test provisioning, hak owner, validasi pajak, redirect, dan pencegahan onboarding ulang.

## Selesai - Operational Pages

- [x] Dashboard server-rendered dengan identitas outlet, status penerimaan order, ringkasan order/penjualan hari ini, dan kesiapan katalog/meja aktual.
- [x] Produk dan meja server-rendered, difilter oleh outlet aktif, dengan empty state yang jujur.
- [x] Form Request untuk tambah, ubah, dan hapus produk/meja serta perubahan ketersediaan produk dan status meja.
- [x] Order board server-rendered dengan filter, pencarian, detail snapshot item, policy, dan aksi transisi status.
- [x] Ownership data selalu berasal dari tenant/outlet context; policy resource kini memeriksa kedua scope.
- [x] Feature test props Inertia, isolasi outlet, forged ownership, validasi kategori/kode, dan toggle ketersediaan.

## Selesai - Product Images

- [x] Upload gambar produk tenant/outlet-aware pada disk public dengan filename acak.
- [x] Validasi JPG/PNG/WebP maksimal 5 MB dan dimensi maksimal 2400 px.
- [x] Normalisasi orientasi, scale-down maksimal 1600 px, dan optimasi WebP quality 80 menggunakan Laravel Image dan `intervention/image`.
- [x] Frontend mendukung input file, progress unggah, dan menampilkan URL gambar tanpa membocorkan storage path.
- [x] Menambahkan `public/storage` link lokal serta feature test upload dan file invalid.

## Selesai - Ordering Foundation: Guest Checkout

- [x] Model, migration, factory, dan enum untuk order, payment, item, modifier snapshot, serta status history.
- [x] Public checkout terikat QR/outlet/meja dengan validasi ulang produk, varian, modifier, ketersediaan, dan outlet.
- [x] Server menghitung subtotal, pajak inclusive/exclusive, total, dan menyimpan snapshot harga tanpa mempercayai nominal browser.
- [x] Idempotency key menghasilkan satu order/payment dan access token tracking yang aman.
- [x] Menu public menampilkan varian/modifier aktif; cart browser tersimpan per token QR dan checkout/tracking memakai data server.
- [x] State machine order terpusat, timeline mencatat actor/timestamp, dan status staff dibatasi oleh permission.
- [x] Realtime order board dan customer tracking melalui Laravel Reverb dengan reconnect dan fallback polling.
- [x] Notifikasi visual/audio order baru dengan preference staf per outlet, deduplikasi event/polling, dan aksesibilitas live announcement.
- [x] Produk aktif yang habis tetap tampil pada menu QR dengan status non-interaktif; checkout tetap menolak produk yang menjadi tidak tersedia.
- [x] Integrasi Midtrans Snap Sandbox: sesi checkout setelah order tersimpan, redirect pelanggan, dan webhook signature native.

## Gap PRD Terverifikasi

- [x] Terapkan expiry payment terjadwal, blokir webhook paid yang terlambat, dan payment pengganti pada order yang sama tanpa duplikasi order.
- [x] Lengkapi CRUD kategori, varian, modifier, opsi modifier, dan penugasan modifier produk.
- [x] Simpan snapshot identitas outlet/meja pada order dan gunakan pada tracking, board order, serta struk untuk menjaga riwayat setelah data operasional berubah.
- [x] Tambahkan pengaturan pajak outlet per outlet, validasi basis points, normalisasi saat nonaktif, dan audit perubahan.
- [x] Terapkan credential Midtrans tenant-owned yang terenkripsi dan berversi, rotasi owner-only, binding payment atomik, serta verifikasi webhook dengan credential retired yang terikat pada payment.
- [x] Terapkan penugasan outlet untuk staf, termasuk kebijakan default/backfill dan enforcement pada context/policy.

## Gap PRD Ditangani

- [x] Tambahkan registrasi/login owner melalui Google sesuai PRD 8.10 dengan Socialite, verifikasi email provider, account linking, redirect onboarding, dan konfigurasi env-only.
- [x] Lengkapi halaman dan action superadmin untuk tenant, paket/harga, subscription, dan pembatalan invoice pending; semua perubahan menulis audit log.
- [x] Implementasikan event analytics minimum, privacy-safe hashing, validasi QR/product, tracking customer web, dan dashboard funnel 30 hari.
- [x] Sediakan baseline aplikasi dengan MySQL, Redis queue worker, dan deployment Docker/Nginx/PHP-FPM; monitoring queue custom digunakan karena Horizon belum kompatibel dengan baseline Laravel 13, sedangkan S3-compatible public storage, TLS, dan runtime production tetap memerlukan environment deployment.
- [x] Hubungkan backup produksi ke mysqldump/gzip, asset disk, storage S3 private terenkripsi, checksum, dan restore drill SQLite/MySQL terisolasi; target recovery production tetap memerlukan operator environment.

## Task Berikutnya - Backend Foundation

- [x] Buat model, migration, factory, dan seeder untuk tenant, outlet, tenant user, role/permission, kategori, produk, varian, modifier, meja, dan QR token.
- [x] Implementasikan tenant context, outlet scope, Policy/Gate, dan permission per role.
- [x] Buat onboarding owner: tenant, outlet pertama, pengaturan zona waktu, dan pajak.
- [x] Hubungkan dashboard, produk, dan meja ke controller Inertia serta validasi Form Request.
- [x] Implementasikan upload dan optimasi gambar produk ke storage tenant-aware.
- [x] Implementasikan QR token acak, validasi status tenant/outlet/meja, menu publik, download/cetak, pencabutan, dan regenerasi.
- [x] Validasi subscription tenant aktif atau trial pada QR, menu, checkout, dan pembuatan order publik.

## Task Berikutnya - Ordering

- [x] Definisikan kontrak props TypeScript dan Resource Laravel untuk menu publik, checkout, dan tracking dasar.
- [x] Implementasikan cart browser yang terikat token QR serta validasi ulang server-side.
- [x] Buat order, payment pending, order item, modifier, snapshot harga/pajak, nomor order, access token, dan idempotency key dalam transaction.
- [x] Terapkan state machine order dan catat setiap transisi beserta actor dan timestamp.
- [x] Hubungkan live order board dan customer tracking melalui Laravel Reverb dengan reconnect/fallback polling.
- [x] Tambahkan notifikasi visual/audio order baru dengan preferensi staf.

## Task Berikutnya - Payment dan SaaS

- [x] Buat payment adapter dan integrasi sandbox gateway Indonesia.
- [x] Implementasikan kontrak webhook generik dengan signature bertimestamp, idempotency event, nominal/currency verification, expiry, dan proteksi downgrade.
- [x] Tambahkan adapter vendor, retry, dan reconciliation gateway.
- [x] Tambahkan queued reconciliation job dengan retry/backoff serta command dispatch yang tetap synchronous pada environment test/local.
- [x] Pastikan order hanya masuk produksi setelah payment terverifikasi `paid`.
- [x] Buat struk digital dari snapshot order dan dukungan print/download.
- [x] Implementasikan plan, trial, subscription, invoice SaaS, entitlement, owner billing Midtrans, dan limit meja.
- [x] Terapkan enforcement limit outlet dan staf pada flow CRUD resource.
- [x] Tambahkan laporan penjualan tenant-scoped dengan filter tanggal/outlet, agregasi payment, produk terlaris, dan transaksi terbaru.
- [x] Tambahkan audit log tenant-aware untuk subscription, invoice, payment, role, dan tax setup.
- [x] Tambahkan dashboard superadmin platform, gate, command grant/revoke, dan action management ter-audit.
- [x] Tambahkan correlation ID, structured request/lifecycle telemetry, health summary, dan runbook backup/recovery.
- [x] Tambahkan queue backlog monitoring terjadwal dan structured `QueueBusy` telemetry.
- [x] Tambahkan opt-in SQLite backup command dengan asset archive, checksum, dan retention.
- [x] Tambahkan backup verification dan isolated restore drill untuk SQLite.
- [x] Tambahkan opt-in quarterly restore drill untuk backup SQLite terbaru.

## Selesai - Superadmin Platform

- [x] Dashboard platform lintas tenant dengan metrik tenant, subscription, invoice, payment pending, dan event webhook yang belum diproses.
- [x] Pengelolaan tenant dengan pencarian nama/slug/email owner, filter status, pagination, ringkasan outlet/member, detail workspace, dan perubahan status ter-audit.
- [x] Monitoring payment/webhook dengan filter provider/event/status, detail event aman tanpa payload sensitif, daftar payment pending, dan action rekonsiliasi Midtrans.
- [x] Audit log platform dengan pencarian event/tenant/resource/request ID, pagination, actor, dan metadata korelasi tanpa membocorkan payload payment.
- [x] Endpoint `platform.payments.reconcile` dilindungi `platform.admin`, memicu `ReconcilePaymentJob`, dan mencatat `platform.payment.reconciliation_requested`.

## Audit Gap PRD - 31 Agustus 2026 (Status Terkini)

- [x] Lengkapi product CRUD untuk mengubah atau menghapus nama, harga, deskripsi, kategori, foto, dan flag produk dengan policy serta isolasi outlet.
- [x] Lengkapi table management untuk mengubah nama/kode/zona/kapasitas dan mengaktifkan/menonaktifkan meja dengan policy serta isolasi outlet.
- [x] Sediakan action refund manual penuh yang dilindungi permission `payment.refund`, idempotency key, audit log, state lokal, dan gateway Midtrans. Refund parsial/lintas gateway tetap di luar MVP.
- [x] Gunakan background job Redis untuk reconciliation payment dengan retry/backoff; worker Docker serta queue monitoring tersedia. Flow notifikasi/laporan tambahan tetap dapat dipecah menjadi job berikutnya bila diperlukan.
- [x] Jalankan server Reverb pada deployment produksi melalui service `reverb` di `docker-compose.yml`; runtime deployment dan TLS masih menunggu environment Docker.
- [x] Jalankan restore drill MySQL pada target disposable terisolasi: backup `20260901_041139Z` valid, gzip/checksum lulus, 13 asset entries terdeteksi, dan restore drill berhasil dalam 4,995 detik; RPO produksi tetap memerlukan target recovery operator.

## Audit Gap PRD - 3 September 2026 (Status Terkini)

- [x] Tambahkan preview cart authoritative dengan fingerprint quote; checkout menolak harga/ketersediaan stale dan meminta konfirmasi ulang.
- [x] Lengkapi retry payment untuk status `failed`/`expired`, pemilihan QRIS/e-wallet/VA, status pengecualian tracking customer, serta filter table dan rentang tanggal order board.
- [x] Amankan provisioning staf dengan secret acak dan notifikasi reset password, bukan password default.
- [x] Batasi analytics publik ke event browser yang aman; event order/payment lifecycle hanya direkam server-side.
- [x] Cegah refund penuh ganda dengan mengunci satu refund pending/sukses per payment; tambahkan regression test untuk idempotency key berbeda saat refund masih berjalan.
- [x] Expire invoice subscription yang melewati jatuh tempo sebelum renewal, jadwalkan command expiry, gunakan interval billing plan untuk periode invoice, dan audit setiap expiry.
- [x] Revalidasi entitlement subscription di dalam transaction checkout; tambah `no-store` dan `no-referrer` pada tracking/token order serta JSON publik.
- [x] Kerasikan baseline produksi: cookie sesi otomatis `Secure` saat production, Compose mewajibkan credential DB/Reverb, dan seeder memerlukan credential dari config/env tanpa default password yang dikomit.
- [x] Funnel analytics browser tetap bersifat indikatif untuk guest anonymous. Event conversion kritis berasal dari server; token HMAC QR-bound dan deduplikasi event browser 60 detik sudah diterapkan.
- [ ] Partial refund initiation tetap di luar scope MVP; laporan sudah menyimpan dan menghitung nominal refund parsial, tetapi flow refund parsial melalui gateway belum tersedia.

## Verifikasi Terakhir

- [x] `php artisan test --compact`: 196 test lulus, 1.519 assertion; termasuk full flow ordering/payment, quote stale, payment retry, auth, SaaS, reports, backup, analytics, observability, CRUD operasional, refund concurrency, queue reconciliation, dan platform admin.
- [x] `composer run lint:check`: Pint lulus.
- [x] `composer run types:check`: PHPStan lulus tanpa error.
- [x] `npm run types:check`: TypeScript lulus.
- [ ] `npm run build`: tertahan `EPERM` saat Vite menghapus `public/build/assets` pada host Windows; tidak dapat diverifikasi sampai lock file/direktori dilepas.
- [x] `php artisan migrate --force`: seluruh migration lokal, termasuk refund, Google identity, dan analytics, sudah diterapkan; `migrate:fresh --force` juga lulus pada MySQL disposable.
- [x] Dependency lokal disinkronkan melalui `composer install`; `laravel/socialite` v5.30.1 terpasang di `vendor`.
- [x] `npm run check`: lulus; 102 file terformat benar dan 89 file frontend lulus tanpa warning/error.
- [ ] `docker compose config --quiet` dan runtime Compose: Docker CLI tidak tersedia pada environment ini.

## Quality Gate Berikutnya

- [x] Foundation test untuk relasi model, constraint lintas tenant, permission owner, dan seeder idempotent.
- [x] Feature test tenant isolation dan authorization.
- [x] Feature test QR invalid/revoked/expired, rotasi token, artifact, status resource, download/cetak, dan isolasi katalog.
- [x] Feature test checkout idempotency dan isolasi token order.
- [x] Feature test webhook duplicate/out-of-order dan transisi status ilegal.
- [x] Feature test agregasi laporan, timezone tenant, filter outlet, isolasi tenant, dan permission laporan.
- [x] Feature test audit log untuk setup owner, transisi payment/subscription, redaction payload, dan tenant scope.
- [x] Feature test akses platform dan agregasi tenant lintas konteks.
- [x] Feature test propagation correlation ID dan korelasi audit request.
- [x] Feature test structured telemetry, redaction atribut, dan health threshold.
- [x] Feature test CRUD produk/meja, lifecycle gambar, refund penuh idempotent/failure/permission, dan queued reconciliation.
- [x] Feature test quote checkout stale, retry payment, filter order, analytics public/server boundary, invoice expiry/interval, subscription recheck checkout, tracking cache header, dan refund in-flight.
- [x] Sediakan browser/E2E tooling lalu uji alur scan QR sampai struk pada viewport 360 px, tablet, dan desktop dengan Playwright; 5 skenario x 3 viewport (15 eksekusi) tersedia dan terakhir dilaporkan lulus.
- [x] Audit aksesibilitas axe, Core Web Vitals dasar, overflow, empty/error/loading state, dan koneksi offline/lambat lulus pada customer browser flow.
- [ ] Lengkapi responsive image variants (`srcset`) dan lazy loading pada seluruh gambar produk/customer flow.
- [x] Rapikan baseline formatting source pada `npm run check`; full check lulus tanpa warning/error.
- [ ] Jalankan validasi runtime Docker untuk Compose, Reverb, dan Redis worker; Docker CLI tidak tersedia. Restore drill MySQL disposable sudah lulus.

## Production Hardening - 4 September 2026

- [x] Analytics browser menggunakan token bertanda tangan HMAC yang berisi session acak, hash QR, dan expiry satu jam; browser tidak lagi dapat mengirim `session_id` pilihannya sendiri.
- [x] Endpoint analytics memvalidasi token terhadap QR aktif dan mendeduplikasi event browser yang identik selama satu menit; test mencakup token QR lain, token expired, dan duplicate delivery.
- [x] Reconciliation refund pending Midtrans, expiry invoice subscription, partial-refund amount reporting, health threshold operasional, dan skenario browser tambahan tersedia.
- [ ] CI workflow repository perlu ditambahkan atau diverifikasi; `.github/workflows` tidak tersedia pada checkout saat ini.
- [x] Perbaiki assertion upload image invalid agar memeriksa direktori tenant/outlet yang diuji, tidak bergantung pada isi root storage fake dari fixture lain.
- [x] `php artisan test --compact`: 196 test lulus, 1.519 assertion.
- [x] `composer run types:check`, `composer run lint:check`, dan `npm run types:check` lulus.
- [ ] `npm run build` tertahan `EPERM` saat menghapus `public/build/assets`; direktori sedang dikunci proses lain pada host lokal.

## Audit PRD - 4 September 2026 (Gap Aktif)

- [ ] **P1 - Konsistensi order/payment:** batasi pembatalan dari order `paid` dan penolakan setelah payment terverifikasi agar selalu memiliki keputusan refund yang eksplisit; selaraskan status order saat refund webhook masuk. Referensi: `app/Enums/OrderStatus.php`, `app/Services/PaymentWebhookService.php`, dan `app/Services/PaymentRefundService.php`.
- [ ] **P1 - Refund reconciliation:** jangan menandai refund `failed` hanya karena provider belum mengembalikan item refund; pertahankan idempotency key/provider refund key dan cegah retry yang berpotensi menggandakan refund. Referensi: `app/Services/PaymentRefundService.php`.
- [ ] **P1 - Status transition atomicity:** tambahkan transaction/locking yang konsisten untuk update order, history, audit, analytics, dan broadcast agar concurrent staff transition tidak menghasilkan timeline/status yang berbeda. Referensi: `app/Services/OrderStatusService.php` dan `app/Http/Controllers/OrderController.php`.
- [ ] **P1 - TLS production:** deployment saat ini expose HTTP pada port aplikasi dan Reverb; production membutuhkan TLS termination yang terdokumentasi serta konfigurasi HTTPS Reverb. Referensi: `docker-compose.yml` dan `docker/nginx.conf`.
- [ ] **P2 - Webhook protection:** tambahkan rate limiting pada `webhooks/payments/{provider}` dan `webhooks/midtrans`. Referensi: `routes/api.php`.
- [ ] **P2 - Token response headers:** response Inertia checkout yang membawa `analytics_token` belum menetapkan `Cache-Control: no-store` dan `Referrer-Policy: no-referrer`. Referensi: `app/Http/Controllers/PublicOrderController.php`.
- [ ] **P2 - Plan feature enforcement:** `Plan::hasFeature()` tersedia, tetapi pemakaian feature entitlement belum diterapkan pada flow aplikasi. Referensi: `app/Models/Plan.php` dan `app/Services/SubscriptionEntitlementService.php`.
- [ ] **P2 - Production storage/alerts:** compose dan `.env.example` masih default ke local public storage; konfigurasi S3-compatible dan tujuan alert untuk queue/webhook/error perlu diwajibkan atau didokumentasikan pada deployment production.
