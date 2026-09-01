import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Check,
    ChevronRight,
    CircleCheck,
    Clock3,
    CreditCard,
    LayoutDashboard,
    QrCode,
    ScanLine,
    Sparkles,
    Store,
    Table2,
    UsersRound,
    UtensilsCrossed,
    WalletCards,
} from 'lucide-react';
import { BrandMark, BrandName } from '@/components/brand-mark';
import { dashboard, login, register } from '@/routes';

const navItems = [
    { href: '#cara-kerja', label: 'Cara kerja' },
    { href: '#fitur', label: 'Fitur' },
    { href: '#harga', label: 'Harga' },
];

const highlights = [
    { value: '1 ruang kerja', label: 'menu, order, pembayaran, laporan' },
    { value: 'Tanpa aplikasi', label: 'tamu langsung scan dan pesan' },
    { value: 'Real-time', label: 'tim dapur tahu apa yang masuk' },
];

const workflow = [
    {
        icon: ScanLine,
        number: '01',
        title: 'Tamu scan, lalu pesan',
        description:
            'QR unik di setiap meja membuka menu digital yang cepat dipahami. Tidak perlu download aplikasi atau membuat akun.',
    },
    {
        icon: WalletCards,
        number: '02',
        title: 'Pembayaran langsung beres',
        description:
            'QRIS, e-wallet, dan virtual account masuk dalam checkout yang singkat dengan nominal yang selalu jelas.',
    },
    {
        icon: UtensilsCrossed,
        number: '03',
        title: 'Dapur tetap sinkron',
        description:
            'Order terbayar muncul di layar staf secara real-time, lengkap dengan meja, item, dan catatan tamu.',
    },
];

const capabilities = [
    {
        icon: QrCode,
        title: 'Menu QR yang mudah dijual',
        description:
            'Atur kategori, harga, foto, dan ketersediaan dari satu tempat. Tamu melihat menu yang selalu siap dipesan.',
        tags: ['Kategori fleksibel', 'QR per meja'],
        iconClass: 'bg-primary/10 text-primary',
    },
    {
        icon: CreditCard,
        title: 'Checkout tanpa tebakan',
        description:
            'Pembayaran online dirapikan dalam satu alur supaya tamu tidak perlu mengulang order dan staf tidak perlu menebak statusnya.',
        tags: ['QRIS', 'E-wallet', 'VA'],
        iconClass: 'bg-accent text-accent-foreground',
    },
    {
        icon: LayoutDashboard,
        title: 'Live order board',
        description:
            'Satu tampilan untuk melihat order baru, yang sedang diproses, sampai yang siap diantar.',
        tags: ['Status real-time', 'Catatan meja'],
        iconClass: 'bg-secondary text-secondary-foreground',
    },
    {
        icon: BarChart3,
        title: 'Laporan yang bisa ditindaklanjuti',
        description:
            'Lihat penjualan, produk terlaris, dan metode pembayaran tanpa memindahkan data ke spreadsheet lain.',
        tags: ['Penjualan harian', 'Produk terlaris'],
        iconClass: 'bg-primary/10 text-primary',
    },
];

const planFeatures = [
    'Menu dan QR tanpa batas',
    'Live order board untuk tim',
    'Pembayaran online',
    'Laporan penjualan',
];

const chartBars = [32, 45, 38, 58, 52, 73, 62, 90, 78, 100, 82, 96];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="QR ordering yang terasa personal" />
            <div className="bg-background text-foreground min-h-screen overflow-x-hidden">
                <a
                    href="#konten"
                    className="focus:bg-background sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-full focus:px-4 focus:py-3 focus:text-sm focus:font-bold focus:shadow-lg"
                >
                    Lewati ke konten
                </a>

                <header className="border-border/70 bg-background/90 sticky top-0 z-40 border-b backdrop-blur-xl">
                    <nav
                        aria-label="Navigasi utama"
                        className="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 sm:px-8"
                    >
                        <Link
                            href="/"
                            className="flex cursor-pointer items-center gap-3"
                        >
                            <BrandMark />
                            <BrandName />
                        </Link>
                        <div className="hidden items-center gap-8 text-sm font-semibold md:flex">
                            {navItems.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className="hover:text-primary cursor-pointer transition-colors duration-200"
                                >
                                    {item.label}
                                </a>
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="bg-foreground text-background hover:bg-primary inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full px-5 text-sm font-bold transition-colors duration-200"
                                >
                                    Dashboard
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="hover:text-primary hidden min-h-11 cursor-pointer items-center px-4 text-sm font-bold transition-colors duration-200 sm:inline-flex"
                                    >
                                        Masuk
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="bg-foreground text-background hover:bg-primary inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-sm font-bold transition-colors duration-200"
                                    >
                                        Mulai gratis
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                    <div className="border-border/70 border-t md:hidden">
                        <div className="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-5 py-2 sm:px-8">
                            {navItems.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className="text-muted-foreground hover:bg-muted hover:text-foreground inline-flex min-h-10 shrink-0 cursor-pointer items-center rounded-full px-3 text-xs font-bold transition-colors duration-200"
                                >
                                    {item.label}
                                </a>
                            ))}
                        </div>
                    </div>
                </header>

                <main id="konten">
                    <section
                        aria-labelledby="hero-title"
                        className="paper-grid relative isolate scroll-mt-24"
                    >
                        <div
                            className="dark:bg-primary/10 absolute top-20 -left-32 -z-10 size-96 rounded-full bg-[#e7b582]/25 blur-3xl"
                            aria-hidden="true"
                        />
                        <div
                            className="dark:bg-accent/10 absolute right-0 bottom-0 -z-10 size-80 rounded-full bg-[#a9b888]/25 blur-3xl"
                            aria-hidden="true"
                        />
                        <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-24 lg:min-h-[calc(100vh-7.75rem)] lg:grid-cols-[1.02fr_0.98fr] lg:gap-16 lg:py-24">
                            <div className="relative z-10 max-w-3xl">
                                <div className="border-primary/20 bg-card text-primary mb-7 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-bold tracking-[0.14em] uppercase shadow-sm">
                                    <Sparkles
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Cara baru melayani meja
                                </div>
                                <h1
                                    id="hero-title"
                                    className="font-display max-w-4xl text-[clamp(3.25rem,7.5vw,7rem)] leading-[0.88] font-bold tracking-[-0.065em]"
                                >
                                    Pesanan lancar,
                                    <span className="text-primary block italic">
                                        pelayanan tetap hangat.
                                    </span>
                                </h1>
                                <p className="text-muted-foreground mt-8 max-w-xl text-lg leading-8 sm:text-xl">
                                    Meja menyatukan menu QR, pembayaran, dan
                                    operasional restoran dalam satu alur yang
                                    sederhana.
                                </p>
                                <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                                    <Link
                                        href={register()}
                                        className="bg-primary text-primary-foreground inline-flex min-h-13 cursor-pointer items-center justify-center gap-2 rounded-full px-7 text-sm font-bold shadow-[0_14px_30px_-16px_var(--primary)] transition-transform duration-200 hover:-translate-y-0.5"
                                    >
                                        Coba gratis 14 hari
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                    <Link
                                        href="/demo/menu"
                                        className="bg-card hover:bg-secondary inline-flex min-h-13 cursor-pointer items-center justify-center gap-2 rounded-full border px-7 text-sm font-bold transition-colors duration-200"
                                    >
                                        Lihat demo menu
                                        <ChevronRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </div>
                                <div className="text-muted-foreground mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm">
                                    {[
                                        'Tanpa kartu kredit',
                                        'Bisa mulai dari satu outlet',
                                    ].map((item) => (
                                        <span
                                            key={item}
                                            className="flex items-center gap-2"
                                        >
                                            <CircleCheck
                                                className="dark:text-accent size-4 text-[#66784b]"
                                                aria-hidden="true"
                                            />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-[600px] lg:mx-0">
                                <div className="border-border bg-card absolute -top-5 right-3 z-10 rotate-3 rounded-2xl border p-3 shadow-xl sm:right-6 sm:p-4">
                                    <div className="flex items-center gap-3">
                                        <QrCode
                                            className="text-primary size-10 sm:size-12"
                                            aria-hidden="true"
                                        />
                                        <div>
                                            <p className="text-primary text-[10px] font-bold tracking-widest uppercase">
                                                Meja 08
                                            </p>
                                            <p className="mt-1 text-xs font-semibold">
                                                Menu aktif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div className="text-muted-foreground mb-3 flex items-center justify-between px-1 text-xs font-bold">
                                    <span>Contoh tampilan dashboard</span>
                                    <span className="dark:text-accent inline-flex items-center gap-1.5 text-[#66784b]">
                                        <span className="dark:bg-accent size-1.5 rounded-full bg-[#66784b]" />
                                        Live
                                    </span>
                                </div>
                                <div className="border-foreground/10 bg-foreground relative overflow-hidden rounded-[2rem] border p-2.5 shadow-[0_40px_100px_-35px_rgba(55,42,29,0.55)] sm:p-5 dark:border-white/10 dark:bg-[#101510]">
                                    <div className="bg-background rounded-[1.4rem] p-4 sm:p-6">
                                        <div className="border-border flex items-center justify-between border-b pb-5">
                                            <div>
                                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                                    Operasional hari ini
                                                </p>
                                                <h2 className="font-display mt-1 text-2xl font-bold">
                                                    Kedai Sore
                                                </h2>
                                            </div>
                                            <span className="bg-accent text-accent-foreground inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold">
                                                <span className="size-1.5 rounded-full bg-current" />
                                                Buka
                                            </span>
                                        </div>
                                        <div className="mt-5 grid grid-cols-2 gap-3">
                                            <div className="bg-primary text-primary-foreground col-span-2 rounded-2xl p-5">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-primary-foreground/70 text-xs font-bold">
                                                        Penjualan hari ini
                                                    </span>
                                                    <span className="bg-primary-foreground/15 rounded-full px-2 py-1 text-[10px] font-bold">
                                                        +18.4%
                                                    </span>
                                                </div>
                                                <p className="mt-4 text-3xl font-bold tracking-tight">
                                                    Rp 4.860.000
                                                </p>
                                                <div
                                                    className="mt-5 flex h-14 items-end gap-2"
                                                    aria-label="Grafik penjualan meningkat sepanjang hari"
                                                    role="img"
                                                >
                                                    {chartBars.map((height) => (
                                                        <span
                                                            key={height}
                                                            className="bg-primary-foreground/70 flex-1 rounded-t"
                                                            style={{
                                                                height: `${height}%`,
                                                            }}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                            <div className="border-border bg-card rounded-2xl border p-4">
                                                <Clock3
                                                    className="text-primary size-5"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-5 text-2xl font-bold">
                                                    08:42
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    Rata-rata proses
                                                </p>
                                            </div>
                                            <div className="border-border bg-card rounded-2xl border p-4">
                                                <UtensilsCrossed
                                                    className="text-accent-foreground dark:text-accent size-5"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-5 text-2xl font-bold">
                                                    28
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    Order selesai
                                                </p>
                                            </div>
                                        </div>
                                        <div className="border-border bg-card mt-3 rounded-2xl border p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold">
                                                        08
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold">
                                                            Order #A-1048
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">
                                                            3 item · baru saja
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="bg-accent text-accent-foreground shrink-0 rounded-full px-3 py-1.5 text-xs font-bold">
                                                    Dibayar
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="border-border/80 mx-auto grid max-w-7xl border-t px-5 sm:grid-cols-3 sm:px-8">
                            {highlights.map((highlight) => (
                                <div
                                    key={highlight.value}
                                    className="border-border/80 border-b py-5 last:border-b-0 sm:border-r sm:border-b-0 sm:px-6 sm:first:pl-0 sm:last:border-r-0 sm:last:pr-0"
                                >
                                    <p className="font-display text-xl font-bold tracking-tight">
                                        {highlight.value}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {highlight.label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section
                        id="cara-kerja"
                        aria-labelledby="workflow-title"
                        className="bg-foreground text-background scroll-mt-32 dark:bg-[#151b15] dark:text-[#f9f4e8]"
                    >
                        <div className="mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-32">
                            <div className="grid gap-8 lg:grid-cols-2 lg:items-end">
                                <div>
                                    <p className="text-primary text-xs font-bold tracking-[0.2em] uppercase">
                                        Satu alur yang utuh
                                    </p>
                                    <h2
                                        id="workflow-title"
                                        className="font-display mt-4 max-w-2xl text-4xl leading-tight font-bold tracking-tight sm:text-6xl"
                                    >
                                        Dari duduk sampai hidangan datang.
                                    </h2>
                                </div>
                                <p className="text-background/70 max-w-lg text-base leading-7 lg:justify-self-end dark:text-[#b9bcae]">
                                    Tamu mendapat kebebasan untuk memesan. Tim
                                    Anda mendapat konteks untuk melayani dengan
                                    lebih baik.
                                </p>
                            </div>
                            <ol className="bg-background/10 mt-16 grid gap-px overflow-hidden rounded-[2rem] md:grid-cols-3 dark:bg-white/10">
                                {workflow.map((step) => (
                                    <li
                                        key={step.number}
                                        className="group bg-foreground p-7 transition-colors duration-200 hover:bg-[#30372b] sm:p-9 dark:bg-[#151b15] dark:hover:bg-[#202820]"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="bg-primary/15 text-primary flex size-12 items-center justify-center rounded-2xl">
                                                <step.icon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span className="font-display text-background/10 text-4xl font-bold dark:text-white/10">
                                                {step.number}
                                            </span>
                                        </div>
                                        <h3 className="mt-12 text-xl font-bold">
                                            {step.title}
                                        </h3>
                                        <p className="text-background/70 mt-3 leading-7 dark:text-[#b9bcae]">
                                            {step.description}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section
                        id="fitur"
                        aria-labelledby="feature-title"
                        className="scroll-mt-32 py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-8">
                            <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p className="text-primary text-xs font-bold tracking-[0.2em] uppercase">
                                        Sedikit alat, banyak kendali
                                    </p>
                                    <h2
                                        id="feature-title"
                                        className="font-display mt-4 max-w-2xl text-4xl leading-tight font-bold tracking-tight sm:text-6xl"
                                    >
                                        Yang penting untuk tim, semuanya di
                                        sini.
                                    </h2>
                                </div>
                                <p className="text-muted-foreground max-w-sm leading-7 sm:text-right">
                                    Kurangi pekerjaan yang berulang. Sisakan
                                    energi untuk tamu dan makanan yang Anda
                                    banggakan.
                                </p>
                            </div>

                            <div className="mt-14 grid gap-4 lg:grid-cols-2">
                                {capabilities.map((capability) => (
                                    <article
                                        key={capability.title}
                                        className="group border-border bg-card hover:border-primary/40 hover:bg-muted/60 rounded-[2rem] border p-7 transition-colors duration-200 sm:p-9"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <span
                                                className={`flex size-12 items-center justify-center rounded-2xl ${capability.iconClass}`}
                                            >
                                                <capability.icon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <ChevronRight
                                                className="text-muted-foreground group-hover:text-primary size-5 transition-transform duration-200 group-hover:translate-x-1"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <h3 className="font-display mt-10 text-2xl font-bold tracking-tight">
                                            {capability.title}
                                        </h3>
                                        <p className="text-muted-foreground mt-3 max-w-xl leading-7">
                                            {capability.description}
                                        </p>
                                        <div className="mt-8 flex flex-wrap gap-2">
                                            {capability.tags.map((tag) => (
                                                <span
                                                    key={tag}
                                                    className="border-border bg-background text-muted-foreground rounded-full border px-3 py-1.5 text-xs font-bold"
                                                >
                                                    {tag}
                                                </span>
                                            ))}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        aria-labelledby="teams-title"
                        className="px-5 pb-24 sm:px-8 sm:pb-32"
                    >
                        <div className="bg-accent text-accent-foreground mx-auto grid max-w-7xl gap-10 rounded-[2rem] p-7 sm:p-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:p-14">
                            <div>
                                <p className="text-xs font-bold tracking-[0.2em] uppercase opacity-70">
                                    Satu pengalaman, tiga sudut pandang
                                </p>
                                <h2
                                    id="teams-title"
                                    className="font-display mt-4 max-w-xl text-4xl leading-tight font-bold tracking-tight sm:text-5xl"
                                >
                                    Lebih ringan untuk tamu. Lebih jelas untuk
                                    tim.
                                </h2>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    {
                                        icon: UsersRound,
                                        label: 'Untuk tamu',
                                        value: 'Tanpa aplikasi',
                                    },
                                    {
                                        icon: Table2,
                                        label: 'Untuk staf',
                                        value: 'Status jelas',
                                    },
                                    {
                                        icon: Store,
                                        label: 'Untuk pemilik',
                                        value: 'Angka siap pakai',
                                    },
                                ].map((item) => (
                                    <div
                                        key={item.label}
                                        className="bg-accent-foreground/10 rounded-2xl p-5"
                                    >
                                        <item.icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        <p className="mt-8 text-sm font-bold opacity-70">
                                            {item.label}
                                        </p>
                                        <p className="font-display mt-1 text-xl font-bold">
                                            {item.value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="harga"
                        aria-labelledby="pricing-title"
                        className="bg-muted/40 scroll-mt-32 py-24 sm:py-32"
                    >
                        <div className="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-center lg:gap-20">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.2em] uppercase">
                                    Mulai lebih ringan
                                </p>
                                <h2
                                    id="pricing-title"
                                    className="font-display mt-4 max-w-xl text-4xl leading-tight font-bold tracking-tight sm:text-6xl"
                                >
                                    Satu meja hari ini, tumbuh besok.
                                </h2>
                                <p className="text-muted-foreground mt-5 max-w-lg text-lg leading-8">
                                    Semua yang dibutuhkan outlet untuk menerima
                                    pesanan mandiri. Tidak ada biaya setup
                                    tersembunyi.
                                </p>
                                <Link
                                    href="/demo/menu"
                                    className="text-primary hover:text-foreground mt-8 inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-bold transition-colors duration-200"
                                >
                                    Lihat pengalaman dari sisi tamu
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>

                            <div className="border-border bg-card rounded-[2rem] border p-7 shadow-[0_30px_80px_-50px_rgba(55,42,29,0.55)] sm:p-9">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="font-bold">
                                            Paket Tumbuh
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Untuk outlet yang siap beralih
                                            digital
                                        </p>
                                    </div>
                                    <span className="bg-accent text-accent-foreground rounded-full px-3 py-1 text-xs font-bold">
                                        Paling populer
                                    </span>
                                </div>
                                <p className="mt-8">
                                    <span className="font-display text-5xl font-bold">
                                        Rp299rb
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        / outlet / bulan
                                    </span>
                                </p>
                                <div className="bg-border my-8 h-px" />
                                <div className="grid gap-3 text-sm sm:grid-cols-2">
                                    {planFeatures.map((item) => (
                                        <span
                                            key={item}
                                            className="flex items-center gap-2"
                                        >
                                            <Check
                                                className="dark:text-accent size-4 text-[#66784b]"
                                                aria-hidden="true"
                                            />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                                <Link
                                    href={register()}
                                    className="bg-foreground text-background hover:bg-primary mt-9 inline-flex min-h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-full px-6 text-sm font-bold transition-colors duration-200"
                                >
                                    Mulai 14 hari gratis
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section
                        aria-labelledby="closing-title"
                        className="px-5 py-24 sm:px-8 sm:py-32"
                    >
                        <div className="bg-primary text-primary-foreground relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] p-8 sm:p-14 lg:p-20">
                            <div
                                className="border-primary-foreground/10 absolute -top-24 -right-12 size-72 rounded-full border-[32px]"
                                aria-hidden="true"
                            />
                            <div
                                className="border-primary-foreground/10 absolute -bottom-40 -left-12 size-96 rounded-full border-[48px]"
                                aria-hidden="true"
                            />
                            <div className="relative z-10 max-w-3xl">
                                <p className="text-xs font-bold tracking-[0.2em] uppercase opacity-75">
                                    Meja untuk operasional yang lebih manusiawi
                                </p>
                                <h2
                                    id="closing-title"
                                    className="font-display mt-4 text-4xl leading-tight font-bold tracking-tight sm:text-6xl"
                                >
                                    Buka meja baru untuk pengalaman yang lebih
                                    baik.
                                </h2>
                                <p className="text-primary-foreground/80 mt-5 max-w-2xl text-lg leading-8">
                                    Mulai dari satu outlet. Rasakan alurnya.
                                    Kembangkan saat bisnis Anda siap.
                                </p>
                                <Link
                                    href={register()}
                                    className="bg-foreground text-background mt-8 inline-flex min-h-13 cursor-pointer items-center justify-center gap-2 rounded-full px-7 text-sm font-bold transition-transform duration-200 hover:-translate-y-0.5"
                                >
                                    Mulai gratis sekarang
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="bg-card border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8">
                        <div className="text-foreground flex items-center gap-3">
                            <BrandMark className="size-8 rounded-lg" />
                            <BrandName compact />
                        </div>
                        <p>
                            Dirancang untuk keramahan yang tidak tergesa-gesa.
                        </p>
                        <p>© 2026 Meja</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
