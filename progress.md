# Progress Pengembangan

Terakhir diperbarui: 29 Agustus 2026

## Selesai - Frontend Foundation

- [x] Landing page SaaS responsif dengan CTA register dan demo menu.
- [x] Identitas visual Meja, token warna, tipografi, dark mode, dan reduced motion.
- [x] Customer menu mobile-first: outlet/meja, kategori, pencarian, detail produk, modifier, catatan, dan cart CTA.
- [x] Checkout demo: ringkasan item, identitas meja, nama opsional, metode pembayaran, pajak, dan total.
- [x] Tracking order demo: status aktif, timeline, estimasi, detail item, dan akses struk.
- [x] Dashboard owner: metrik outlet, grafik penjualan, target, dan order terkini.
- [x] Live order board: filter status, detail item, dan transisi status order berbasis database.
- [x] Pengelolaan produk: kategori, pencarian, harga, status tersedia/habis, dan aksi produk.
- [x] Pengelolaan meja: zona, status, pratinjau QR, download, cetak, dan regenerasi.
- [x] Branding dan lokalisasi halaman login/register utama.

Beberapa halaman marketing dan fitur realtime/notifikasi masih menggunakan data demo atau fallback prototype sampai backend domainnya tersedia.

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

- [x] Dashboard server-rendered dengan identitas outlet, status penerimaan order, dan ringkasan katalog/meja aktual.
- [x] Produk dan meja server-rendered, difilter oleh outlet aktif, dengan empty state yang jujur.
- [x] Form Request untuk tambah produk/meja dan perubahan ketersediaan produk.
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

## Task Berikutnya - Backend Foundation

- [x] Buat model, migration, factory, dan seeder untuk tenant, outlet, tenant user, role/permission, kategori, produk, varian, modifier, meja, dan QR token.
- [x] Implementasikan tenant context, outlet scope, Policy/Gate, dan permission per role.
- [x] Buat onboarding owner: tenant, outlet pertama, pengaturan zona waktu, dan pajak.
- [x] Hubungkan dashboard, produk, dan meja ke controller Inertia serta validasi Form Request.
- [x] Implementasikan upload dan optimasi gambar produk ke storage tenant-aware.
- [x] Implementasikan QR token acak, validasi status tenant/outlet/meja, menu publik, download/cetak, pencabutan, dan regenerasi.
- Validasi subscription masih menunggu domain subscription; checkout publik dasar sudah tersedia dengan payment `pending`.

## Task Berikutnya - Ordering

- [x] Definisikan kontrak props TypeScript dan Resource Laravel untuk menu publik, checkout, dan tracking dasar.
- [x] Implementasikan cart browser yang terikat token QR serta validasi ulang server-side.
- [x] Buat order, payment pending, order item, modifier, snapshot harga/pajak, nomor order, access token, dan idempotency key dalam transaction.
- [x] Terapkan state machine order dan catat setiap transisi beserta actor dan timestamp.
- [ ] Hubungkan live order board dan customer tracking melalui Laravel Reverb dengan reconnect/fallback polling.
- [ ] Tambahkan notifikasi visual/audio order baru dengan preferensi staf.

## Task Berikutnya - Payment dan SaaS

- [ ] Buat payment adapter dan integrasi sandbox gateway Indonesia.
- [x] Implementasikan kontrak webhook generik dengan signature bertimestamp, idempotency event, nominal/currency verification, expiry, dan proteksi downgrade.
- [ ] Tambahkan adapter vendor, retry, dan reconciliation gateway.
- [ ] Pastikan order hanya masuk produksi setelah payment terverifikasi `paid`.
- [ ] Buat struk digital dari snapshot order dan dukungan print/download.
- [ ] Implementasikan plan, trial, subscription, invoice SaaS, entitlement, dan limit outlet/meja/staf.
- [ ] Tambahkan laporan penjualan, transaksi, superadmin, audit log, Horizon, observability, backup, dan recovery procedure.

## Quality Gate Berikutnya

- [x] Foundation test untuk relasi model, constraint lintas tenant, permission owner, dan seeder idempotent.
- [x] Feature test tenant isolation dan authorization.
- [x] Feature test QR invalid/revoked/expired, rotasi token, artifact, status resource, download/cetak, dan isolasi katalog.
- [x] Feature test checkout idempotency dan isolasi token order.
- [x] Feature test webhook duplicate/out-of-order dan transisi status ilegal.
- [ ] Browser test alur scan QR sampai struk pada viewport 360 px, tablet, dan desktop.
- [ ] Audit aksesibilitas, Core Web Vitals, optimasi gambar, empty/error/loading state, dan koneksi lambat.
