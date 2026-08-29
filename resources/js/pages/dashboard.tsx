import { Head } from "@inertiajs/react";
import { ClipboardList, PackageOpen, Table2, UtensilsCrossed } from "lucide-react";
import { dashboard } from "@/routes";

type Props = {
    outlet: { name: string; timezone: string; accepts_orders: boolean };
    today: string;
    viewerName: string;
    catalogSummary: {
        products: number;
        available_products: number;
        active_tables: number;
        total_tables: number;
    };
};

export default function Dashboard({ outlet, today, viewerName, catalogSummary }: Props) {
    const metrics = [
        {
            label: "Produk tersedia",
            value: catalogSummary.available_products,
            detail: `${catalogSummary.products} produk tercatat`,
            icon: PackageOpen,
        },
        {
            label: "Meja aktif",
            value: catalogSummary.active_tables,
            detail: `dari ${catalogSummary.total_tables} meja`,
            icon: Table2,
        },
    ];

    return (
        <>
            <Head title="Ringkasan outlet" />
            <div className="flex flex-1 flex-col bg-background">
                <div className="mx-auto w-full max-w-[1500px] p-4 sm:p-6 lg:p-8">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                {today}
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                Selamat datang, {viewerName}.
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {outlet.name} · {outlet.timezone}
                            </p>
                        </div>
                        <div className="flex min-h-11 items-center gap-2 rounded-full border bg-card px-4 text-sm font-bold">
                            <span
                                className={`size-2 rounded-full ${outlet.accepts_orders ? "bg-emerald-600" : "bg-amber-500"}`}
                            />
                            {outlet.accepts_orders
                                ? "Outlet menerima order"
                                : "Outlet tidak menerima order"}
                        </div>
                    </div>

                    <section className="mt-8 grid gap-4 sm:grid-cols-2">
                        {metrics.map((metric) => (
                            <article
                                key={metric.label}
                                className="rounded-[1.4rem] border bg-card p-5 shadow-[0_14px_40px_-35px_rgba(53,44,31,0.65)]"
                            >
                                <span className="flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                                    <metric.icon className="size-5" aria-hidden="true" />
                                </span>
                                <p className="mt-6 text-sm text-muted-foreground">{metric.label}</p>
                                <p className="mt-1 text-2xl font-bold tracking-tight">
                                    {metric.value}{" "}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        · {metric.detail}
                                    </span>
                                </p>
                            </article>
                        ))}
                    </section>

                    <section className="mt-5 rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-primary">
                            <ClipboardList className="size-6" aria-hidden="true" />
                        </div>
                        <h2 className="font-display mt-5 text-2xl font-bold">
                            Order dan penjualan akan tampil di sini.
                        </h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Domain order dan pembayaran belum diaktifkan, jadi ringkasan ini hanya
                            menampilkan kesiapan katalog dan meja aktual. Tambahkan produk serta
                            meja untuk menyiapkan outlet menerima pesanan.
                        </p>
                        <div className="mt-7 flex items-center gap-2 rounded-2xl bg-[#293025] p-4 text-[#fffaf0]">
                            <UtensilsCrossed className="size-5 text-[#dfa281]" aria-hidden="true" />
                            <p className="text-sm font-semibold">
                                Setelah ordering aktif, metrik penjualan dan antrean akan dihitung
                                sepenuhnya di server.
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: "Ringkasan", href: dashboard() }] };
