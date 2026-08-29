import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    Check,
    LockKeyhole,
    Minus,
    Plus,
    QrCode,
    Smartphone,
    WalletCards,
} from "lucide-react";
import { useState } from "react";
import { CustomerHeader } from "@/components/customer-header";
import { formatCurrency, menuItems } from "@/data/demo";

const subtotal = menuItems[0].price + menuItems[4].price * 2;
const tax = Math.round(subtotal * 0.1);

export default function Checkout() {
    const [payment, setPayment] = useState("qris");

    return (
        <>
            <Head title="Konfirmasi pesanan" />
            <div className="min-h-screen bg-background pb-28">
                <CustomerHeader minimal />
                <main className="mx-auto max-w-5xl px-4 py-7 sm:px-6 sm:py-12">
                    <Link
                        href="/demo/menu"
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" /> Kembali ke menu
                    </Link>
                    <div className="mt-5 grid gap-8 lg:grid-cols-[1fr_0.72fr] lg:items-start">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                Langkah terakhir
                            </p>
                            <h1 className="font-display mt-2 text-4xl font-bold tracking-tight sm:text-5xl">
                                Periksa pesananmu.
                            </h1>
                            <p className="mt-3 text-muted-foreground">Kedai Sore · Meja 08</p>

                            <section className="mt-8 overflow-hidden rounded-[1.5rem] border bg-card">
                                <div className="border-b px-5 py-4">
                                    <h2 className="font-bold">Pesanan kamu</h2>
                                </div>
                                {[
                                    { item: menuItems[0], quantity: 1 },
                                    { item: menuItems[4], quantity: 2 },
                                ].map(({ item, quantity }) => (
                                    <div
                                        key={item.id}
                                        className="flex gap-4 border-b p-5 last:border-b-0"
                                    >
                                        <img
                                            src={item.image}
                                            alt=""
                                            className="size-20 rounded-2xl object-cover sm:size-24"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex justify-between gap-3">
                                                <div>
                                                    <h3 className="font-bold">{item.name}</h3>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {item.id === 1
                                                            ? "Pedas sedang · tanpa bawang"
                                                            : "Es normal"}
                                                    </p>
                                                </div>
                                                <p className="shrink-0 text-sm font-bold">
                                                    {formatCurrency(item.price * quantity)}
                                                </p>
                                            </div>
                                            <div className="mt-4 flex w-fit items-center rounded-full border p-0.5">
                                                <button
                                                    type="button"
                                                    className="flex size-9 items-center justify-center rounded-full hover:bg-secondary"
                                                    aria-label={`Kurangi ${item.name}`}
                                                >
                                                    <Minus className="size-3.5" />
                                                </button>
                                                <span className="w-7 text-center text-sm font-bold">
                                                    {quantity}
                                                </span>
                                                <button
                                                    type="button"
                                                    className="flex size-9 items-center justify-center rounded-full hover:bg-secondary"
                                                    aria-label={`Tambah ${item.name}`}
                                                >
                                                    <Plus className="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </section>

                            <section className="mt-6 rounded-[1.5rem] border bg-card p-5 sm:p-6">
                                <h2 className="font-bold">
                                    Nama pemesan{" "}
                                    <span className="font-normal text-muted-foreground">
                                        (opsional)
                                    </span>
                                </h2>
                                <input
                                    className="mt-4 min-h-12 w-full rounded-xl border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-ring"
                                    placeholder="Agar staf mudah memanggilmu"
                                />
                            </section>

                            <section className="mt-6 rounded-[1.5rem] border bg-card p-5 sm:p-6">
                                <h2 className="font-bold">Pilih pembayaran</h2>
                                <div className="mt-4 grid gap-3">
                                    {[
                                        {
                                            id: "qris",
                                            label: "QRIS",
                                            detail: "Scan dari semua aplikasi pembayaran",
                                            icon: QrCode,
                                        },
                                        {
                                            id: "ewallet",
                                            label: "E-wallet",
                                            detail: "GoPay, ShopeePay, atau DANA",
                                            icon: Smartphone,
                                        },
                                        {
                                            id: "va",
                                            label: "Virtual account",
                                            detail: "BCA, Mandiri, BNI, dan bank lainnya",
                                            icon: WalletCards,
                                        },
                                    ].map((method) => (
                                        <button
                                            key={method.id}
                                            type="button"
                                            onClick={() => setPayment(method.id)}
                                            aria-pressed={payment === method.id}
                                            className={`flex min-h-16 items-center gap-3 rounded-2xl border p-3 text-left transition-colors ${payment === method.id ? "border-primary bg-primary/6" : "hover:bg-secondary/60"}`}
                                        >
                                            <span
                                                className={`flex size-11 items-center justify-center rounded-xl ${payment === method.id ? "bg-primary text-primary-foreground" : "bg-secondary"}`}
                                            >
                                                <method.icon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span className="flex-1">
                                                <span className="block text-sm font-bold">
                                                    {method.label}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    {method.detail}
                                                </span>
                                            </span>
                                            <span
                                                className={`flex size-5 items-center justify-center rounded-full border ${payment === method.id ? "border-primary bg-primary text-white" : ""}`}
                                            >
                                                {payment === method.id && (
                                                    <Check className="size-3" aria-hidden="true" />
                                                )}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            </section>
                        </div>

                        <aside className="rounded-[1.75rem] bg-[#283025] p-6 text-[#fffaf0] shadow-[0_30px_80px_-50px_rgba(40,48,37,0.8)] lg:sticky lg:top-24 sm:p-7">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#dfa281] uppercase">
                                Ringkasan pembayaran
                            </p>
                            <dl className="mt-6 space-y-4 text-sm">
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>Subtotal</dt>
                                    <dd>{formatCurrency(subtotal)}</dd>
                                </div>
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>Pajak restoran (10%)</dt>
                                    <dd>{formatCurrency(tax)}</dd>
                                </div>
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>Biaya layanan</dt>
                                    <dd>Rp0</dd>
                                </div>
                            </dl>
                            <div className="my-6 border-t border-white/15" />
                            <div className="flex items-end justify-between gap-4">
                                <span className="font-bold">Total</span>
                                <span className="font-display text-3xl font-bold">
                                    {formatCurrency(subtotal + tax)}
                                </span>
                            </div>
                            <Link
                                href="/demo/tracking"
                                className="mt-7 flex min-h-13 items-center justify-between rounded-full bg-[#d87655] px-5 text-sm font-bold text-white transition-colors hover:bg-[#c96546]"
                            >
                                Bayar sekarang <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                            <p className="mt-5 flex items-center justify-center gap-2 text-center text-xs text-[#bfc5b7]">
                                <LockKeyhole className="size-3.5" aria-hidden="true" /> Pembayaran
                                aman dan terenkripsi
                            </p>
                        </aside>
                    </div>
                </main>
            </div>
        </>
    );
}
