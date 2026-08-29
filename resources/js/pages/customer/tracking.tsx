import { Head, Link } from "@inertiajs/react";
import {
    Check,
    ChevronRight,
    Clock3,
    Download,
    MapPin,
    ReceiptText,
    UtensilsCrossed,
} from "lucide-react";
import { CustomerHeader } from "@/components/customer-header";
import { formatCurrency, menuItems } from "@/data/demo";

const steps = [
    { label: "Pembayaran diterima", time: "19:42", done: true },
    { label: "Pesanan diterima dapur", time: "19:43", done: true },
    { label: "Sedang disiapkan", time: "19:46", done: true, active: true },
    { label: "Siap disajikan", time: "Estimasi 19:55", done: false },
];

export default function Tracking() {
    return (
        <>
            <Head title="Pesanan #A-1048" />
            <div className="min-h-screen bg-background">
                <CustomerHeader minimal />
                <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-14">
                    <section className="relative overflow-hidden rounded-[2rem] bg-[#283025] p-7 text-[#fffaf0] sm:p-10">
                        <div
                            className="absolute -top-16 -right-12 size-52 rounded-full border-[30px] border-white/5"
                            aria-hidden="true"
                        />
                        <div className="relative">
                            <div className="flex items-center justify-between gap-4">
                                <span className="rounded-full bg-[#d87655]/20 px-3 py-1.5 text-xs font-bold text-[#eda98f]">
                                    #A-1048
                                </span>
                                <span className="flex items-center gap-2 text-xs text-[#cbd1c3]">
                                    <MapPin className="size-3.5" aria-hidden="true" /> Meja 08
                                </span>
                            </div>
                            <span className="mt-10 flex size-14 items-center justify-center rounded-2xl bg-[#d87655] text-white">
                                <UtensilsCrossed className="size-6" aria-hidden="true" />
                            </span>
                            <p className="mt-6 text-sm font-bold tracking-[0.14em] text-[#eda98f] uppercase">
                                Sedang disiapkan
                            </p>
                            <h1 className="font-display mt-2 text-4xl font-bold tracking-tight sm:text-5xl">
                                Dapur sedang meracik pesananmu.
                            </h1>
                            <p className="mt-4 max-w-lg leading-7 text-[#cbd1c3]">
                                Duduk santai, ya. Pesanan akan diantar langsung ke meja setelah
                                semuanya siap.
                            </p>
                            <div className="mt-8 flex w-fit items-center gap-3 rounded-full bg-white/8 px-4 py-3 text-sm">
                                <Clock3 className="size-4 text-[#eda98f]" aria-hidden="true" />
                                <span>
                                    Estimasi siap <strong>7-10 menit lagi</strong>
                                </span>
                            </div>
                        </div>
                    </section>

                    <section className="mt-6 rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <h2 className="font-display text-2xl font-bold">Perjalanan pesanan</h2>
                        <ol className="mt-7">
                            {steps.map((step, index) => (
                                <li key={step.label} className="relative flex gap-4 pb-8 last:pb-0">
                                    {index < steps.length - 1 && (
                                        <span
                                            className={`absolute top-7 bottom-0 left-[13px] w-px ${step.done ? "bg-primary" : "bg-border"}`}
                                            aria-hidden="true"
                                        />
                                    )}
                                    <span
                                        className={`relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full border ${step.done ? "border-primary bg-primary text-white" : "bg-card text-transparent"}`}
                                    >
                                        {step.done && (
                                            <Check className="size-3.5" aria-hidden="true" />
                                        )}
                                    </span>
                                    <div className="flex flex-1 items-start justify-between gap-4">
                                        <div>
                                            <p
                                                className={`text-sm font-bold ${!step.done ? "text-muted-foreground" : ""}`}
                                            >
                                                {step.label}
                                            </p>
                                            {step.active && (
                                                <p className="mt-1 text-xs text-primary">
                                                    Diperbarui otomatis
                                                </p>
                                            )}
                                        </div>
                                        <time className="text-xs text-muted-foreground">
                                            {step.time}
                                        </time>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </section>

                    <section className="mt-6 rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex items-center justify-between">
                            <h2 className="font-display text-2xl font-bold">Detail pesanan</h2>
                            <span className="text-xs text-muted-foreground">3 item</span>
                        </div>
                        <div className="mt-6 space-y-5">
                            {[
                                { item: menuItems[0], quantity: 1 },
                                { item: menuItems[4], quantity: 2 },
                            ].map(({ item, quantity }) => (
                                <div key={item.id} className="flex items-center gap-4">
                                    <img
                                        src={item.image}
                                        alt=""
                                        className="size-14 rounded-xl object-cover"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-bold">
                                            {quantity}x {item.name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {item.id === 1
                                                ? "Pedas sedang · tanpa bawang"
                                                : "Es normal"}
                                        </p>
                                    </div>
                                    <p className="text-sm font-bold">
                                        {formatCurrency(item.price * quantity)}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <div className="my-6 border-t" />
                        <div className="flex items-center justify-between">
                            <span className="font-bold">Total dibayar</span>
                            <span className="font-display text-2xl font-bold">
                                {formatCurrency(114400)}
                            </span>
                        </div>
                    </section>

                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            className="flex min-h-12 items-center justify-between rounded-full border bg-card px-5 text-sm font-bold transition-colors hover:bg-secondary"
                        >
                            <span className="flex items-center gap-2">
                                <ReceiptText className="size-4" aria-hidden="true" /> Lihat struk
                                digital
                            </span>
                            <ChevronRight className="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            className="flex min-h-12 items-center justify-center gap-2 rounded-full border bg-card px-5 text-sm font-bold transition-colors hover:bg-secondary"
                        >
                            <Download className="size-4" aria-hidden="true" /> Simpan detail pesanan
                        </button>
                    </div>
                    <p className="mt-8 text-center text-xs leading-5 text-muted-foreground">
                        Simpan halaman ini untuk kembali melihat status pesanan.{" "}
                        <Link href="/demo/menu" className="font-bold text-primary">
                            Kembali ke menu
                        </Link>
                    </p>
                </main>
            </div>
        </>
    );
}
