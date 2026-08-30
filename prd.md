# Product Requirements Document (PRD)

## SaaS F&B — QR Table Ordering & Payment

**Versi:** 1.2  
**Tanggal:** 26 Agustus 2026  
**Status:** Draft MVP  

---

## 1. Ringkasan Produk

SaaS F&B adalah web application multi-tenant untuk restoran, kafe, dan usaha kuliner. Produk membantu outlet menerima pesanan dine-in melalui QR unik di setiap meja.

Customer tidak perlu mengunduh aplikasi atau membuat akun. Customer cukup duduk, memindai QR meja, memilih produk, membayar, lalu memantau status pesanan hingga selesai.

Alur utama:

```text
Customer datang dan duduk
→ Scan QR meja
→ Lihat dan pilih produk
→ Review pesanan
→ Bayar
→ Pembayaran terverifikasi
→ Pesanan diproses
→ Pesanan siap/disajikan
→ Pesanan selesai
```

---

## 2. Masalah yang Diselesaikan

### Masalah customer

- harus menunggu pelayan untuk melihat menu atau memesan;
- antre di kasir untuk melakukan pembayaran;
- tidak mengetahui status pesanannya;
- risiko salah catat pesanan dan catatan khusus.

### Masalah outlet

- pencatatan pesanan masih manual;
- pesanan dapat salah meja, salah produk, atau terlewat;
- status pembayaran dan proses pesanan tidak terhubung;
- sulit memantau transaksi secara real-time;
- perubahan menu dan harga membutuhkan cetak ulang menu;
- laporan penjualan harus direkap manual.

---

## 3. Tujuan Produk

1. Memungkinkan customer melakukan pemesanan mandiri dari meja tanpa akun.
2. Memastikan pesanan diteruskan ke outlet setelah pembayaran terverifikasi.
3. Memberikan antrean pesanan real-time kepada staf outlet.
4. Mengurangi kesalahan pencatatan pesanan dan pembayaran.
5. Memberikan pengelolaan produk, meja, transaksi, dan laporan dalam satu aplikasi.
6. Menyediakan model SaaS multi-tenant yang dapat digunakan banyak bisnis dan outlet.

### Indikator keberhasilan MVP

- minimal 90% customer berhasil membuka menu setelah scan QR;
- minimal 85% checkout yang dibayar berhasil menjadi order aktif tanpa intervensi staf;
- order tampil pada dashboard outlet maksimal 5 detik setelah pembayaran terverifikasi;
- tidak ada order ganda akibat webhook pembayaran berulang;
- selisih antara nominal checkout, pembayaran, dan laporan adalah 0;
- tingkat kegagalan QR valid membuka meja kurang dari 1%.

---

## 4. Ruang Lingkup MVP

### Termasuk

- landing page SaaS;
- pendaftaran dan login owner;
- onboarding bisnis dan outlet;
- multi-tenant dan multi-outlet;
- pengelolaan user/staf dan hak akses;
- kategori, produk, varian, modifier, harga, dan ketersediaan;
- pengelolaan meja dan QR unik;
- menu digital mobile-first;
- keranjang dan checkout tanpa akun customer;
- catatan per item;
- pembayaran online;
- verifikasi pembayaran server-side;
- antrean dan status pesanan outlet;
- halaman tracking pesanan customer;
- riwayat transaksi dan struk;
- pengaturan pajak opsional;
- dashboard dan laporan dasar;
- paket dan langganan SaaS;
- audit log untuk aktivitas sensitif.

### Tidak termasuk MVP

- delivery dan takeaway;
- reservasi meja;
- akun, membership, loyalty, dan poin customer;
- split bill kompleks;
- inventory bahan baku dan resep/BOM;
- integrasi marketplace delivery;
- native mobile app;
- POS offline;
- refund otomatis lintas semua payment gateway;
- settlement dana oleh platform;
- multi-negara dan multi-mata uang.

---

## 5. Target Pengguna dan Peran

| Peran | Tanggung jawab utama |
|---|---|
| Superadmin | Mengelola tenant, paket, langganan, dan kesehatan platform |
| Owner | Mengelola bisnis, outlet, staf, menu, pembayaran, dan laporan |
| Admin/Manager | Mengelola operasional outlet sesuai hak akses |
| Kasir | Memantau order, mengonfirmasi tindakan operasional, dan mencetak struk |
| Kitchen/Bar | Melihat antrean dan memperbarui status proses pesanan |
| Customer Guest | Scan QR, memesan, membayar, dan memantau status tanpa login |

Customer guest tidak disimpan sebagai user aplikasi. Sistem hanya menggunakan sesi meja dan token order yang aman.

---

## 6. Customer Journey Utama

### 6.1 Datang dan duduk

Customer memilih meja yang tersedia. Pada meja terdapat QR yang merepresentasikan tenant, outlet, dan meja.

### 6.2 Scan QR meja

Customer memindai QR melalui kamera ponsel. Sistem memvalidasi:

- tenant aktif;
- subscription tenant aktif atau trial;
- outlet aktif dan sedang menerima order;
- meja aktif;
- QR valid dan tidak dicabut.

Jika valid, halaman menu menampilkan nama outlet dan nomor/nama meja. Customer harus dapat memastikan bahwa meja yang tampil sesuai dengan tempat duduknya.

### 6.3 Pilih produk

Customer dapat:

- melihat kategori dan produk;
- mencari produk;
- melihat foto, deskripsi, harga, dan status tersedia;
- memilih varian dan modifier;
- menentukan jumlah;
- menulis catatan khusus;
- menambahkan produk ke keranjang.

### 6.4 Review dan checkout

Halaman checkout menampilkan:

- identitas outlet dan meja;
- rincian produk, varian, modifier, jumlah, dan catatan;
- subtotal;
- diskon jika ada;
- pajak jika diaktifkan outlet;
- biaya tambahan jika berlaku;
- grand total;
- nama customer opsional;
- metode pembayaran.

Harga dan total dihitung ulang oleh server. Nilai dari browser tidak boleh menjadi sumber nominal final.

### 6.5 Bayar

Customer memilih metode pembayaran online yang tersedia, misalnya QRIS, virtual account, atau e-wallet sesuai kemampuan gateway.

Sistem membuat order dengan status `awaiting_payment` dan payment dengan status `pending`. Order belum boleh masuk antrean produksi.

### 6.6 Verifikasi pembayaran

Status `paid` hanya ditetapkan setelah sistem menerima webhook yang valid atau melakukan verifikasi server-to-server ke payment gateway.

Halaman sukses/redirect pada browser tidak cukup untuk menandai pembayaran berhasil.

### 6.7 Diproses

Setelah payment menjadi `paid`:

1. order berubah menjadi `paid`;
2. order masuk antrean outlet;
3. staf menerima notifikasi visual/audio;
4. staf menerima order;
5. status berubah menjadi `accepted`, lalu `preparing`.

### 6.8 Siap dan disajikan

Setelah selesai dibuat, staf mengubah status menjadi `ready`. Saat makanan/minuman diberikan ke meja, status menjadi `served`.

### 6.9 Selesai

Staf atau aturan outlet mengubah status menjadi `completed`. Customer dapat melihat ringkasan dan mengunduh/mencetak struk digital.

---

## 7. State Machine

### 7.1 Status order

```text
draft
→ awaiting_payment
→ paid
→ accepted
→ preparing
→ ready
→ served
→ completed
```

Status pengecualian:

```text
payment_expired
cancelled
rejected
refunded
```

Aturan:

- `draft` dibuat saat checkout mulai diproses;
- `awaiting_payment` menunggu pembayaran;
- hanya payment terverifikasi yang dapat mengubah order menjadi `paid`;
- order `paid` tidak boleh kembali ke `awaiting_payment`;
- pembatalan order berbayar harus mempertimbangkan refund;
- setiap perubahan status menyimpan waktu dan actor;
- perubahan status ilegal harus ditolak server.

### 7.2 Status pembayaran

```text
pending
→ paid
```

Status pengecualian:

```text
failed
expired
refunded
partially_refunded
```

Order dan payment adalah entitas serta state machine yang terpisah.

---

## 8. Functional Requirements

### 8.1 QR dan meja

- Owner/admin dapat membuat meja per outlet.
- Setiap meja memiliki kode unik dan QR yang dapat diunduh/cetak.
- QR tidak menyimpan ID database yang mudah ditebak.
- QR dapat dinonaktifkan atau diregenerasi.
- QR lama tidak berlaku setelah diregenerasi.
- Meja yang tidak aktif tidak dapat menerima order baru.
- Perubahan nomor/nama meja tidak mengubah histori order.

### 8.2 Menu digital

- Tampilan mobile-first dan dapat dibuka tanpa login.
- Hanya produk aktif dan tersedia yang ditampilkan.
- Produk dapat memiliki foto, deskripsi, harga dasar, varian, dan modifier.
- Produk habis harus terlihat sebagai tidak tersedia dan tidak dapat ditambahkan.
- Harga yang digunakan pada order disimpan sebagai snapshot.
- Perubahan harga tidak memengaruhi order historis.

### 8.3 Keranjang

- Keranjang terikat pada outlet dan meja hasil QR.
- Keranjang tidak boleh berisi produk dari outlet lain.
- Customer dapat mengubah jumlah dan menghapus item.
- Sistem memvalidasi ulang produk, harga, dan ketersediaan saat checkout.
- Keranjang dapat disimpan sementara pada browser, tetapi keputusan final berada di server.

### 8.4 Checkout

- Customer dapat checkout tanpa akun.
- Sistem membuat nomor order yang unik dan mudah dibaca staf.
- Request checkout menggunakan idempotency key untuk mencegah order ganda.
- Server menghitung subtotal, diskon, pajak, biaya, dan grand total.
- Jika harga atau ketersediaan berubah, customer diminta mengonfirmasi ulang.
- Checkout ditolak jika outlet tutup atau tidak menerima order.

### 8.5 Payment

- Payment gateway menggunakan adapter agar vendor dapat diganti.
- Credential gateway disimpan terenkripsi.
- Webhook wajib diverifikasi signature-nya.
- Webhook harus idempotent dan aman terhadap event duplikat/out-of-order.
- Nominal payment harus sama dengan grand total order.
- Payment pending memiliki masa kedaluwarsa.
- Setelah payment kedaluwarsa, order tidak masuk proses.
- Customer dapat mencoba pembayaran baru dengan membuat payment pengganti pada order yang sama; transisi `payment_expired → awaiting_payment` hanya diizinkan untuk flow ini.

### 8.6 Dashboard order outlet

- Menampilkan order baru secara real-time atau near real-time.
- Dapat difilter berdasarkan status, meja, dan waktu.
- Menampilkan nomor order, meja, durasi menunggu, item, catatan, dan status pembayaran.
- Staf dapat memperbarui status sesuai urutan yang diizinkan.
- Aksi sensitif menyimpan actor dan waktu.
- Order baru memberikan notifikasi visual dan audio yang dapat dikonfigurasi.

### 8.7 Tracking customer

- Customer melihat status order tanpa login menggunakan token akses acak.
- Halaman menampilkan nomor order, meja, detail item, pembayaran, dan timeline status.
- Customer dapat membuka kembali halaman selama token masih berlaku.
- Token tidak boleh memberikan akses ke order lain.

### 8.8 Struk

- Struk tersedia setelah pembayaran berhasil.
- Customer dan staf dapat mencetak atau menyimpan struk.
- Struk berisi identitas outlet, nomor order, meja, waktu, item, subtotal, diskon, pajak, total, dan metode pembayaran.
- Data struk berasal dari snapshot order, bukan data produk terbaru.

### 8.9 Pajak opsional

Outlet dapat mengatur:

```text
tax_enabled
tax_name
tax_rate
tax_inclusive
```

Aturan:

- jika pajak tidak aktif, `tax_amount = 0`;
- pajak dihitung server-side;
- rate dan nominal pajak disimpan pada snapshot order;
- pembulatan konsisten pada checkout, gateway, struk, dan laporan;
- perubahan pengaturan pajak tidak mengubah transaksi historis.

### 8.10 Langganan SaaS

- Owner dapat mendaftar dengan email/password atau Google.
- Tenant dapat menggunakan trial sesuai konfigurasi platform.
- Paket membatasi fitur, jumlah outlet, meja aktif, dan user/staf.
- Tenant tidak dapat menerima order baru jika subscription suspended/expired.
- Order dan laporan historis tetap aman sesuai kebijakan retensi.
- Aktivasi subscription berbayar hanya setelah payment subscription terverifikasi.

---

## 9. Halaman MVP

### Customer

1. QR validation/loading
2. Menu outlet
3. Detail produk
4. Keranjang
5. Checkout
6. Instruksi pembayaran
7. Status pembayaran
8. Tracking pesanan
9. Struk digital
10. Error/QR tidak valid/outlet tutup

### Owner/Admin/Staff

1. Login
2. Onboarding tenant dan outlet
3. Dashboard ringkas
4. Live order board
5. Detail order
6. Kategori dan produk
7. Varian dan modifier
8. Meja dan QR
9. User, role, dan permission
10. Pengaturan outlet
11. Payment gateway
12. Pajak
13. Transaksi dan struk
14. Laporan penjualan
15. Paket, invoice, dan subscription

### Superadmin

1. Dashboard platform
2. Tenant
3. Paket dan harga
4. Subscription dan invoice
5. Monitoring payment/webhook
6. Audit log

---

## 10. Data Model Konseptual

Entitas minimum:

```text
users
tenants
tenant_users
roles
permissions
outlets
tables
table_qr_tokens
categories
products
product_variants
modifiers
modifier_options
orders
order_items
order_item_modifiers
payments
payment_events
order_status_histories
tax_settings
plans
subscriptions
saas_invoices
audit_logs
```

### Field penting order

```text
id
tenant_id
outlet_id
table_id
outlet_name_snapshot
outlet_address_snapshot
outlet_phone_snapshot
table_name_snapshot
table_code_snapshot
order_number
customer_name_optional
status
subtotal
discount_amount
tax_name_snapshot
tax_rate_snapshot
tax_amount
fee_amount
grand_total
currency
access_token_hash
paid_at
completed_at
created_at
updated_at
```

### Prinsip data

- Semua data bisnis wajib memiliki `tenant_id`.
- Query operasional wajib dibatasi tenant dan outlet sesuai permission.
- Item order menyimpan snapshot nama, varian, modifier, harga, dan pajak.
- Uang disimpan sebagai integer dalam satuan terkecil, bukan floating point.
- Waktu disimpan dalam UTC dan ditampilkan sesuai zona waktu outlet.
- Foreign key, unique constraint, dan index harus ditentukan pada level database.

---

## 11. Multi-Tenant dan Hak Akses

### Isolasi tenant

- User tenant A tidak dapat membaca atau mengubah data tenant B.
- Tenant scope diterapkan pada service/query layer, bukan hanya filter UI.
- File dan foto menggunakan path/ownership tenant.
- Cache dan job queue menyertakan tenant context.

### Permission minimum

```text
outlet.manage
staff.manage
menu.manage
table.manage
order.view
order.update_status
payment.view
payment.refund
report.view
tax.manage
gateway.manage
subscription.manage
```

Owner memperoleh seluruh permission tenant. Admin dan staf diberikan permission sesuai tugas dan outlet.

---

## 12. Business Rules Utama

1. Satu QR hanya mengarah ke satu meja aktif pada satu outlet.
2. Customer tidak dapat mengubah outlet atau meja melalui parameter yang tidak tervalidasi.
3. Produk dari outlet lain tidak dapat dimasukkan ke keranjang.
4. Harga final selalu dihitung server.
5. Order hanya masuk antrean produksi setelah payment terverifikasi `paid`.
6. Webhook yang sama tidak boleh memicu side effect dua kali.
7. Pembayaran `paid` tidak boleh diturunkan menjadi `pending` oleh webhook terlambat.
8. Perubahan produk, pajak, atau meja tidak mengubah histori transaksi.
9. Semua perubahan status order tercatat pada timeline.
10. Data tenant yang subscription-nya berakhir tidak boleh tercampur atau hilang tanpa kebijakan retensi yang jelas.

---

## 13. Acceptance Criteria Alur Utama

### Scan QR

```gherkin
Given tenant, outlet, dan meja aktif
When customer membuka QR meja
Then sistem menampilkan menu outlet dan identitas meja yang benar
And customer tidak diminta login
```

### Tambah produk

```gherkin
Given produk tersedia
When customer memilih varian, modifier, jumlah, dan catatan
Then item masuk keranjang dengan konfigurasi dan harga yang benar
```

### Checkout

```gherkin
Given keranjang valid
When customer melanjutkan checkout
Then server memvalidasi ulang produk dan menghitung total
And order dibuat satu kali walaupun request dikirim ulang dengan idempotency key sama
```

### Pembayaran

```gherkin
Given payment masih pending
When webhook pembayaran valid diterima
Then payment menjadi paid
And order menjadi paid
And order muncul satu kali pada dashboard outlet
```

### Webhook duplikat

```gherkin
Given event pembayaran sudah diproses
When webhook yang sama diterima kembali
Then tidak ada payment, order, atau notifikasi ganda
```

### Proses pesanan

```gherkin
Given order berstatus paid
When staf menerima dan memproses order
Then status mengikuti paid → accepted → preparing → ready → served → completed
And customer melihat perubahan pada halaman tracking
```

### QR tidak valid

```gherkin
Given QR meja sudah dicabut atau meja tidak aktif
When customer membuka QR
Then sistem tidak menampilkan menu untuk checkout
And sistem menampilkan pesan yang mudah dipahami
```

---

## 14. Non-Functional Requirements

### Performa

- halaman menu usable pada jaringan seluler lambat;
- Largest Contentful Paint target kurang dari 2,5 detik pada kondisi wajar;
- API katalog dan cart target p95 kurang dari 500 ms di luar layanan pihak ketiga;
- order baru muncul pada dashboard maksimal 5 detik setelah payment terverifikasi;
- gambar produk menggunakan kompresi, ukuran responsif, dan lazy loading.

### Availability dan reliabilitas

- target availability MVP 99,5% per bulan;
- retry untuk job dan webhook menggunakan backoff;
- proses checkout dan webhook wajib idempotent;
- database memiliki backup otomatis dan prosedur restore yang diuji;
- kegagalan notifikasi tidak boleh membatalkan order yang sudah dibayar.

### Keamanan

- TLS wajib;
- password di-hash menggunakan algoritma modern;
- session cookie `Secure`, `HttpOnly`, dan kebijakan `SameSite` yang tepat;
- proteksi CSRF, XSS, SQL injection, rate limiting, dan brute force;
- credential payment gateway dienkripsi at-rest;
- webhook diverifikasi signature dan timestamp bila tersedia;
- token QR/order memiliki entropy tinggi dan disimpan sebagai hash bila relevan;
- log tidak boleh menyimpan secret, token pembayaran, atau data sensitif penuh;
- audit log untuk perubahan payment, gateway, pajak, role, dan subscription.

### Responsif dan aksesibilitas

- customer flow diprioritaskan untuk layar mobile 360 px ke atas;
- area sentuh minimal nyaman;
- warna status tidak menjadi satu-satunya indikator;
- label form, pesan error, dan loading state harus jelas;
- UI admin mendukung desktop dan tablet.

### Observability

- structured logging dengan request/correlation ID;
- metrik checkout, payment success/failure, webhook latency, dan order latency;
- alert untuk webhook gagal berulang, antrean job menumpuk, dan error rate meningkat;
- error tracking tidak merekam data pembayaran sensitif.

---

## 15. API Konseptual

### Customer/public

```text
GET  /q/{qr_token}
GET  /api/public/outlets/{outlet}/menu
POST /api/public/carts/validate
POST /api/public/orders
POST /api/public/orders/{order}/payments
GET  /api/public/orders/{access_token}
GET  /api/public/orders/{access_token}/receipt
```

### Outlet/staff

```text
GET   /api/orders
GET   /api/orders/{order}
PATCH /api/orders/{order}/status
CRUD  /api/products
CRUD  /api/categories
CRUD  /api/tables
POST  /api/tables/{table}/regenerate-qr
GET   /api/reports/sales
```

### Payment

```text
POST /api/webhooks/payments/{provider}
GET  /api/payments/{payment}/status
```

Nama endpoint bersifat konseptual. Implementasi final harus menggunakan authorization, validation, rate limit, idempotency, dan tenant scope.

---

## 16. Rekomendasi Arsitektur MVP

### Stack utama

- **Backend dan web framework:** Laravel 13
- **Runtime:** PHP 8.4
- **Frontend:** React 19 + TypeScript
- **Server-driven SPA:** Inertia.js 3
- **UI:** Tailwind CSS 4 + shadcn/ui
- **Database:** MySQL
- **ORM:** Eloquent ORM
- **Cache, session, dan queue:** Redis
- **Queue monitoring:** Laravel Horizon
- **Realtime:** Laravel Reverb
- **Social login:** Laravel Socialite untuk Google
- **Role dan permission:** Spatie Laravel Permission
- **Background job:** Laravel Queue dengan worker Redis
- **Object storage:** S3-compatible storage
- **Payment:** payment gateway Indonesia melalui adapter internal
- **Deployment:** Docker, Nginx, PHP-FPM, Redis, MySQL, queue worker, scheduler, dan automated backup

Laravel, React, dan Inertia.js ditempatkan dalam satu codebase. Pendekatan ini mempercepat pengembangan karena routing, autentikasi, authorization, validasi, dan rendering halaman dikelola dalam satu aplikasi tanpa kewajiban membuat REST API penuh untuk seluruh dashboard.

Versi framework dikunci pada Laravel 13 untuk implementasi awal. Upgrade minor dan patch dilakukan secara berkala, sedangkan upgrade major harus melalui pengujian kompatibilitas package, migration, queue worker, payment gateway, dan automated test. PHP 8.4 digunakan sebagai runtime utama; PHP 8.3 tetap merupakan minimum yang didukung Laravel 13, tetapi bukan target deployment proyek.

Customer menu, cart, checkout, tracking order, dashboard staff, dashboard owner, dan superadmin menggunakan React. Laravel tetap menjadi sumber kebenaran untuk harga, stok/ketersediaan, tenant scope, status order, status pembayaran, dan permission.

### Struktur modular monolith

```text
Laravel Application
├── Tenant & Outlet
├── Identity & Permission
├── Menu & Product
├── Table & QR
├── Cart & Checkout
├── Order
├── Payment
├── Subscription & Billing
├── Report
└── Audit & Notification

React + Inertia.js
├── Customer Mobile Web
├── Staff Order Board
├── Owner Dashboard
└── Superadmin Dashboard
```

Setiap domain dipisahkan pada layer controller/action, service, model, policy, event, listener, job, dan test. Pemisahan ini bersifat logis dalam satu aplikasi dan database, bukan microservices.

### Aturan implementasi stack

- Gunakan Inertia.js untuk halaman web customer, staff, owner, dan superadmin.
- Gunakan Laravel route dan controller sebagai entry point utama.
- Gunakan Form Request untuk validasi input.
- Gunakan Policy/Gate dan Spatie Permission untuk authorization.
- Terapkan global tenant scope secara hati-hati serta pemeriksaan tenant pada service dan policy.
- Gunakan database transaction untuk pembuatan order, perubahan status kritis, dan pencatatan payment.
- Gunakan Redis queue untuk webhook lanjutan, notifikasi, pembuatan laporan, dan pekerjaan berat.
- Gunakan Laravel Reverb untuk order baru dan pembaruan status secara real-time.
- Gunakan Horizon untuk monitoring antrean dan kegagalan job.
- Gunakan scheduler Laravel untuk payment reconciliation, order expiry, subscription, dan housekeeping.
- API publik hanya dibuat untuk kebutuhan QR/menu, checkout, payment webhook, tracking, atau integrasi eksternal.
- Payment webhook tidak menggunakan session browser dan wajib memiliki signature verification serta idempotency.
- Business logic tidak ditempatkan langsung di React component atau controller yang besar.

### Alasan pemilihan

- Laravel kuat untuk transaksi database, queue, scheduler, webhook pembayaran, authorization, dan audit.
- React sesuai untuk cart interaktif, live order board, tracking status, dan dashboard operasional.
- Inertia.js mengurangi kompleksitas dua aplikasi terpisah dan duplikasi kontrak API pada tahap MVP.
- Satu codebase memudahkan deployment, testing, dan troubleshooting.
- Modular monolith masih dapat dikembangkan menjadi service terpisah jika volume dan kebutuhan organisasi sudah membenarkannya.

Microservices, pemisahan frontend Next.js, dan database per service tidak digunakan pada MVP. Pemisahan baru dilakukan berdasarkan bukti bottleneck atau kebutuhan tim, bukan sejak awal.

---

## 17. Analytics dan Event

Event minimum:

```text
qr_opened
menu_viewed
product_viewed
add_to_cart
checkout_started
order_created
payment_started
payment_paid
payment_failed
order_accepted
order_preparing
order_ready
order_served
order_completed
```

Dashboard funnel:

```text
QR dibuka
→ tambah ke keranjang
→ checkout
→ payment dimulai
→ payment berhasil
→ order selesai
```

Analytics tidak boleh menyimpan data pembayaran sensitif.

---

## 18. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| QR dipindah ke meja lain | Tampilkan identitas meja dengan jelas dan sediakan regenerasi QR |
| Customer kehilangan halaman tracking | Simpan token aman pada browser dan tampilkan nomor order |
| Webhook terlambat/duplikat | Idempotency, event store, retry, dan status verification |
| Harga berubah saat checkout | Validasi server dan minta konfirmasi ulang |
| Produk habis setelah masuk cart | Validasi ketersediaan saat checkout |
| Internet outlet terganggu | Dashboard reconnect otomatis dan histori order dapat dimuat ulang |
| Staf tidak melihat order baru | Notifikasi audio/visual, indikator unread, dan monitoring antrean |
| Data tenant bocor | Tenant scoping, authorization test, audit, dan review keamanan |

---

## 19. Tahapan Pengembangan

### Fase 1 — Fondasi

- authentication owner/staff;
- tenant, outlet, role, dan permission;
- onboarding;
- kategori, produk, varian, dan modifier;
- meja dan QR.

### Fase 2 — Customer ordering

- menu mobile-first;
- detail produk;
- cart;
- checkout;
- order snapshot;
- halaman tracking.

### Fase 3 — Payment dan operasional

- payment adapter;
- webhook dan idempotency;
- live order dashboard;
- status workflow;
- struk.

### Fase 4 — SaaS dan pelaporan

- plan, trial, subscription, dan invoice;
- entitlement/limit;
- laporan penjualan;
- superadmin dashboard;
- audit dan observability.

---

## 20. Definition of Done MVP

MVP dinyatakan siap diuji pengguna apabila:

1. owner dapat mendaftar, membuat outlet, menu, dan meja;
2. QR unik dapat dicetak dan membuka menu meja yang benar;
3. customer dapat memilih produk dan checkout tanpa login;
4. pembayaran dapat dibuat dan diverifikasi secara server-side;
5. order berbayar muncul satu kali pada dashboard staf;
6. staf dapat menjalankan seluruh status hingga `completed`;
7. customer dapat memantau status dan membuka struk;
8. laporan transaksi sesuai dengan data order dan payment;
9. tenant isolation, authorization, dan webhook idempotency lulus pengujian;
10. backup, monitoring, logging, dan prosedur recovery dasar tersedia.

---

## 21. Keputusan Produk MVP

- Fokus transaksi: **dine-in**.
- Customer: **guest tanpa login**.
- Identifikasi lokasi: **QR unik per meja**.
- Urutan: **bayar terlebih dahulu, kemudian diproses**.
- Pembayaran customer MVP: **online payment**.
- Order masuk produksi: **hanya setelah pembayaran terverifikasi**.
- Pajak: **opsional dan dapat diatur per outlet**.
- Arsitektur: **multi-tenant modular monolith**.
- Platform: **responsive web application, mobile-first untuk customer**.
