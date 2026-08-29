import { Head, Link, usePage } from "@inertiajs/react";
import {
    ArrowRight,
    Check,
    ChevronRight,
    CircleCheck,
    Clock3,
    QrCode,
    ScanLine,
    Sparkles,
    UtensilsCrossed,
    WalletCards,
} from "lucide-react";
import { BrandMark, BrandName } from "@/components/brand-mark";
import { dashboard, login, register } from "@/routes";

const features = [
    {
        icon: ScanLine,
        number: "01",
        title: "Scan, pilih, pesan",
        description:
            "Tamu membuka menu langsung dari QR unik di meja. Tanpa aplikasi dan tanpa membuat akun.",
    },
    {
        icon: WalletCards,
        number: "02",
        title: "Bayar tanpa antre",
        description:
            "Checkout singkat dengan QRIS, e-wallet, atau virtual account. Nominal selalu terverifikasi.",
    },
    {
        icon: UtensilsCrossed,
        number: "03",
        title: "Dapur tetap sinkron",
        description:
            "Order terbayar masuk ke layar staf secara real-time, lengkap dengan meja dan catatan.",
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="QR ordering yang terasa personal" />
            <div className="min-h-screen overflow-hidden bg-background text-foreground">
                <header className="relative z-30 border-b border-border/70 bg-background/90 backdrop-blur-xl">
                    <nav className="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 sm:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <BrandMark />
                            <BrandName />
                        </Link>
                        <div className="hidden items-center gap-8 text-sm font-semibold md:flex">
                            <a href="#cara-kerja" className="transition-colors hover:text-primary">
                                Cara kerja
                            </a>
                            <a href="#fitur" className="transition-colors hover:text-primary">
                                Fitur
                            </a>
                            <a href="#harga" className="transition-colors hover:text-primary">
                                Harga
                            </a>
                        </div>
                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex min-h-11 items-center gap-2 rounded-full bg-foreground px-5 text-sm font-bold text-background transition-colors hover:bg-primary"
                                >
                                    Dashboard
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="hidden min-h-11 items-center px-4 text-sm font-bold sm:inline-flex"
                                    >
                                        Masuk
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-flex min-h-11 items-center rounded-full bg-foreground px-5 text-sm font-bold text-background transition-colors hover:bg-primary"
                                    >
                                        Mulai gratis
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <main>
                    <section className="paper-grid relative isolate">
                        <div className="absolute top-20 -left-32 size-96 rounded-full bg-[#e7b582]/25 blur-3xl" />
                        <div className="absolute right-0 bottom-0 size-80 rounded-full bg-[#a9b888]/25 blur-3xl" />
                        <div className="mx-auto grid min-h-[calc(100vh-4.5rem)] max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[1.02fr_0.98fr] lg:py-24">
                            <div className="relative z-10 max-w-3xl">
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-card px-4 py-2 text-xs font-bold tracking-[0.14em] text-primary uppercase shadow-sm">
                                    <Sparkles className="size-3.5" aria-hidden="true" />
                                    Cara baru melayani meja
                                </div>
                                <h1 className="font-display max-w-4xl text-[clamp(3.25rem,7.5vw,7rem)] leading-[0.88] font-bold tracking-[-0.065em]">
                                    Lebih cepat pesan,
                                    <span className="block text-primary italic">
                                        lebih hangat melayani.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-xl text-lg leading-8 text-muted-foreground sm:text-xl">
                                    Meja menyatukan menu QR, pembayaran, dan operasional restoran
                                    dalam satu alur yang sederhana.
                                </p>
                                <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                                    <Link
                                        href={register()}
                                        className="inline-flex min-h-13 items-center justify-center gap-2 rounded-full bg-primary px-7 text-sm font-bold text-primary-foreground shadow-[0_14px_30px_-16px_var(--primary)] transition-transform hover:-translate-y-0.5"
                                    >
                                        Coba gratis 14 hari
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </Link>
                                    <Link
                                        href="/demo/menu"
                                        className="inline-flex min-h-13 items-center justify-center gap-2 rounded-full border bg-card px-7 text-sm font-bold transition-colors hover:bg-secondary"
                                    >
                                        Lihat demo menu
                                        <ChevronRight className="size-4" aria-hidden="true" />
                                    </Link>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-muted-foreground">
                                    {["Tanpa kartu kredit", "Setup kurang dari 15 menit"].map(
                                        (item) => (
                                            <span key={item} className="flex items-center gap-2">
                                                <CircleCheck
                                                    className="size-4 text-[#66784b]"
                                                    aria-hidden="true"
                                                />
                                                {item}
                                            </span>
                                        ),
                                    )}
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-[580px] lg:mx-0">
                                <div className="absolute -top-8 -right-4 z-10 rotate-3 rounded-2xl bg-[#d9e0c6] p-4 shadow-xl sm:right-4">
                                    <QrCode className="size-14 text-[#344125]" aria-hidden="true" />
                                    <p className="mt-2 text-[10px] font-bold tracking-widest uppercase">
                                        Meja 08
                                    </p>
                                </div>
                                <div className="relative overflow-hidden rounded-[2rem] border border-white/70 bg-[#252b22] p-3 shadow-[0_40px_100px_-35px_rgba(55,42,29,0.55)] sm:p-5">
                                    <div className="rounded-[1.4rem] bg-[#f9f4e8] p-4 sm:p-6">
                                        <div className="flex items-center justify-between border-b border-[#ded7c7] pb-5">
                                            <div>
                                                <p className="text-xs font-bold tracking-[0.16em] text-[#b64a2e] uppercase">
                                                    Hari ini
                                                </p>
                                                <h2 className="font-display mt-1 text-2xl font-bold text-[#302c25]">
                                                    Kedai Sore
                                                </h2>
                                            </div>
                                            <span className="rounded-full bg-[#dbe4c9] px-3 py-1.5 text-xs font-bold text-[#40512d]">
                                                Buka
                                            </span>
                                        </div>
                                        <div className="mt-5 grid grid-cols-2 gap-3">
                                            <div className="col-span-2 rounded-2xl bg-[#b64a2e] p-5 text-white">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs font-bold text-white/70">
                                                        Penjualan hari ini
                                                    </span>
                                                    <span className="rounded-full bg-white/15 px-2 py-1 text-[10px] font-bold">
                                                        +18.4%
                                                    </span>
                                                </div>
                                                <p className="mt-4 text-3xl font-bold tracking-tight">
                                                    Rp 4.860.000
                                                </p>
                                                <div
                                                    className="mt-5 flex h-14 items-end gap-2"
                                                    aria-hidden="true"
                                                >
                                                    {[
                                                        32, 45, 38, 58, 52, 73, 62, 90, 78, 100, 82,
                                                        96,
                                                    ].map((height) => (
                                                        <span
                                                            key={height}
                                                            className="flex-1 rounded-t bg-white/70"
                                                            style={{ height: `${height}%` }}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                            <div className="rounded-2xl bg-white p-4 text-[#302c25] shadow-sm">
                                                <Clock3
                                                    className="size-5 text-[#b64a2e]"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-5 text-2xl font-bold">08:42</p>
                                                <p className="text-xs text-[#746f64]">
                                                    Rata-rata proses
                                                </p>
                                            </div>
                                            <div className="rounded-2xl bg-[#e8ddbe] p-4 text-[#302c25]">
                                                <UtensilsCrossed
                                                    className="size-5 text-[#657247]"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-5 text-2xl font-bold">28</p>
                                                <p className="text-xs text-[#746f64]">
                                                    Order selesai
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-3 rounded-2xl bg-white p-4 shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <span className="flex size-10 items-center justify-center rounded-xl bg-[#f4d7ca] text-sm font-bold text-[#a83f27]">
                                                        08
                                                    </span>
                                                    <div>
                                                        <p className="text-sm font-bold text-[#302c25]">
                                                            Order #A-1048
                                                        </p>
                                                        <p className="text-xs text-[#746f64]">
                                                            3 item · baru saja
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-full bg-[#dbe4c9] px-3 py-1.5 text-xs font-bold text-[#40512d]">
                                                    Dibayar
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="cara-kerja" className="bg-[#242820] py-24 text-[#f9f4e8] sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8">
                            <div className="grid gap-8 lg:grid-cols-2 lg:items-end">
                                <div>
                                    <p className="text-xs font-bold tracking-[0.2em] text-[#d98d72] uppercase">
                                        Satu alur yang utuh
                                    </p>
                                    <h2 className="font-display mt-4 max-w-2xl text-4xl leading-tight font-bold tracking-tight sm:text-6xl">
                                        Dari duduk sampai hidangan datang.
                                    </h2>
                                </div>
                                <p className="max-w-lg text-base leading-7 text-[#b9bcae] lg:justify-self-end">
                                    Tamu mendapat kebebasan untuk memesan. Tim Anda mendapat konteks
                                    untuk melayani dengan lebih baik.
                                </p>
                            </div>
                            <div
                                id="fitur"
                                className="mt-16 grid gap-px overflow-hidden rounded-[2rem] bg-white/10 md:grid-cols-3"
                            >
                                {features.map((feature) => (
                                    <article
                                        key={feature.number}
                                        className="group bg-[#242820] p-7 transition-colors hover:bg-[#2d3328] sm:p-9"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="flex size-12 items-center justify-center rounded-2xl bg-[#d98d72]/15 text-[#e7a78e]">
                                                <feature.icon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span className="font-display text-4xl font-bold text-white/10">
                                                {feature.number}
                                            </span>
                                        </div>
                                        <h3 className="mt-12 text-xl font-bold">{feature.title}</h3>
                                        <p className="mt-3 leading-7 text-[#b9bcae]">
                                            {feature.description}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="harga" className="relative py-24 sm:py-32">
                        <div className="mx-auto max-w-4xl px-5 text-center sm:px-8">
                            <p className="text-xs font-bold tracking-[0.2em] text-primary uppercase">
                                Mulai lebih ringan
                            </p>
                            <h2 className="font-display mt-4 text-4xl font-bold tracking-tight sm:text-6xl">
                                Satu meja hari ini, tumbuh besok.
                            </h2>
                            <p className="mx-auto mt-5 max-w-2xl text-lg leading-8 text-muted-foreground">
                                Semua yang dibutuhkan outlet untuk menerima pesanan mandiri. Tidak
                                ada biaya setup tersembunyi.
                            </p>
                            <div className="mx-auto mt-10 max-w-xl rounded-[2rem] border bg-card p-7 text-left shadow-[0_30px_80px_-50px_rgba(55,42,29,0.55)] sm:p-9">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="font-bold">Paket Tumbuh</p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Untuk outlet yang siap beralih digital
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-accent px-3 py-1 text-xs font-bold text-accent-foreground">
                                        Paling populer
                                    </span>
                                </div>
                                <p className="mt-8">
                                    <span className="font-display text-5xl font-bold">Rp299rb</span>
                                    <span className="text-muted-foreground"> / outlet / bulan</span>
                                </p>
                                <div className="my-8 h-px bg-border" />
                                <div className="grid gap-3 text-sm sm:grid-cols-2">
                                    {[
                                        "Menu dan QR tanpa batas",
                                        "Live order board",
                                        "Pembayaran online",
                                        "Laporan penjualan",
                                    ].map((item) => (
                                        <span key={item} className="flex items-center gap-2">
                                            <Check
                                                className="size-4 text-[#66784b]"
                                                aria-hidden="true"
                                            />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                                <Link
                                    href={register()}
                                    className="mt-9 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-bold text-background transition-colors hover:bg-primary"
                                >
                                    Mulai 14 hari gratis
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t bg-card">
                    <div className="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-8">
                        <div className="flex items-center gap-3 text-foreground">
                            <BrandMark className="size-8 rounded-lg" />
                            <BrandName compact />
                        </div>
                        <p>Dirancang untuk keramahan yang tidak tergesa-gesa.</p>
                        <p>© 2026 Meja</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
