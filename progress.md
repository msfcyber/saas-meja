# Progress Pengembangan

Terakhir diperbarui: 1 September 2026

## Selesai - Frontend Foundation

- [x] Landing page SaaS responsif dengan CTA register dan demo menu.
- [x] Identitas visual Meja, token warna, tipografi, dark mode, dan reduced motion.
- [x] Customer menu mobile-first: outlet/meja, kategori, pencarian, detail produk, modifier, catatan, dan cart CTA.
- [x] Checkout demo: ringkasan item, identitas meja, nama opsional, metode pembayaran, pajak, dan total.
- [x] Tracking order demo: status aktif, timeline, estimasi, detail item, dan akses struk.
- [x] Dashboard owner: metrik outlet, grafik penjualan, target, dan order terkini.
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

## Gap PRD Tersisa

- [x] Tambahkan registrasi/login owner melalui Google sesuai PRD 8.10 dengan Socialite, verifikasi email provider, account linking, redirect onboarding, dan konfigurasi env-only.
- [x] Lengkapi halaman dan action superadmin untuk tenant, paket/harga, subscription, dan pembatalan invoice pending; semua perubahan menulis audit log.
- [x] Implementasikan event analytics minimum, privacy-safe hashing, validasi QR/product, tracking customer web, dan dashboard funnel 30 hari.
- [x] Selaraskan baseline produksi dengan MySQL, Redis queue worker, S3-compatible public storage, dan deployment Docker/Nginx/PHP-FPM; Horizon menunggu rilis kompatibel Laravel 13.
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

## Audit Gap PRD - 31 Agustus 2026 (Status Terkini)

- [x] Lengkapi product CRUD untuk mengubah atau menghapus nama, harga, deskripsi, kategori, foto, dan flag produk dengan policy serta isolasi outlet.
- [x] Lengkapi table management untuk mengubah nama/kode/zona/kapasitas dan mengaktifkan/menonaktifkan meja dengan policy serta isolasi outlet.
- [x] Sediakan action refund manual penuh yang dilindungi permission `payment.refund`, idempotency key, audit log, state lokal, dan gateway Midtrans. Refund parsial/lintas gateway tetap di luar MVP.
- [x] Gunakan background job Redis untuk reconciliation payment dengan retry/backoff; worker Docker serta queue monitoring tersedia. Flow notifikasi/laporan tambahan tetap dapat dipecah menjadi job berikutnya bila diperlukan.
- [x] Jalankan server Reverb pada deployment produksi melalui service `reverb` di `docker-compose.yml`; runtime deployment dan TLS masih menunggu environment Docker.
- [x] Jalankan restore drill MySQL pada target disposable terisolasi: backup `20260901_041139Z` valid, gzip/checksum lulus, 13 asset entries terdeteksi, dan restore drill berhasil dalam 4,995 detik; RPO produksi tetap memerlukan target recovery operator.

## Verifikasi Terakhir

- [x] `php artisan test --compact`: 176 test lulus, 1.378 assertion; termasuk full flow ordering/payment, auth, SaaS, reports, backup, analytics, observability, CRUD operasional, refund, dan queue reconciliation.
- [x] `composer run lint:check`: Pint lulus.
- [x] `composer run types:check`: PHPStan lulus tanpa error.
- [x] `npm run types:check`: TypeScript lulus.
- [x] `npm run build`: build produksi lulus; hanya ada warning opsional package `fontaine`.
- [x] `php artisan migrate --force`: seluruh migration lokal, termasuk refund, Google identity, dan analytics, sudah diterapkan; `migrate:fresh --force` juga lulus pada MySQL disposable.
- [x] Dependency lokal disinkronkan melalui `composer install`; `laravel/socialite` v5.30.1 sebelumnya ada di lockfile tetapi belum terpasang di `vendor`.
- [x] `npm run check`: lulus setelah baseline source diformat; 35 file frontend lama ikut dirapikan dan direktori tooling internal tetap dikecualikan.
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
- [x] Sediakan browser/E2E tooling lalu uji alur scan QR sampai struk pada viewport 360 px, tablet, dan desktop dengan Playwright; seluruh 12 test matrix lulus.
- [x] Audit aksesibilitas axe, Core Web Vitals dasar, overflow, optimasi gambar, empty/error/loading state, dan koneksi offline/lambat pada browser test lulus.
- [x] Rapikan baseline formatting source pada `npm run check`; full check lulus tanpa warning/error.
- [ ] Jalankan validasi runtime Docker untuk Compose, Reverb, dan Redis worker; Docker CLI tidak tersedia. Restore drill MySQL disposable sudah lulus.
