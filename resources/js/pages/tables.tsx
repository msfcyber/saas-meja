import { Head, router, useForm } from "@inertiajs/react";
import { Ban, Download, Plus, Printer, QrCode, RefreshCw, Table2 } from "lucide-react";
import { useState } from "react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

type DiningTable = {
    id: number;
    name: string;
    code: string;
    zone: string | null;
    capacity: number;
    is_active: boolean;
    has_active_qr: boolean;
    qr_url: string | null;
    qr_download_url: string | null;
    qr_print_url: string | null;
};
type Props = {
    filters: { zone: string | null };
    summary: { tables: number; active_tables: number; active_qr_tokens: number; zones: number };
    zones: string[];
    tables: DiningTable[];
};

export default function Tables({ filters, summary, zones, tables }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [processingTableId, setProcessingTableId] = useState<number | null>(null);
    const form = useForm({ name: "", code: "", zone: "", capacity: "4", is_active: true });

    function createTable(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post("/tables", {
            onSuccess: () => {
                form.reset();
                setIsCreateOpen(false);
            },
        });
    }

    function regenerateQr(table: DiningTable) {
        if (
            !window.confirm(
                `Buat QR baru untuk ${table.name}? QR lama akan langsung tidak berlaku.`,
            )
        ) {
            return;
        }

        setProcessingTableId(table.id);
        router.post(
            `/tables/${table.id}/regenerate-qr`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingTableId(null),
            },
        );
    }

    function revokeQr(table: DiningTable) {
        if (
            !window.confirm(
                `Cabut QR ${table.name}? Customer tidak dapat membuka menu dari QR ini.`,
            )
        ) {
            return;
        }

        setProcessingTableId(table.id);
        router.post(
            `/tables/${table.id}/revoke-qr`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingTableId(null),
            },
        );
    }

    return (
        <>
            <Head title="Meja & QR" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                            Area makan
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Meja & QR
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Kelola identitas meja pada outlet aktif.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        className="min-h-12 rounded-full"
                        onClick={() => setIsCreateOpen(true)}
                    >
                        <Plus aria-hidden="true" /> Tambah meja
                    </Button>
                </div>
                <section className="mt-8 grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: "Total meja",
                            value: summary.tables,
                            detail: `${summary.zones} zona`,
                        },
                        {
                            label: "Meja aktif",
                            value: summary.active_tables,
                            detail: "Siap menerima pesanan",
                        },
                        {
                            label: "QR aktif",
                            value: summary.active_qr_tokens,
                            detail: "Token tersimpan aman",
                        },
                    ].map((metric) => (
                        <article key={metric.label} className="rounded-[1.3rem] border bg-card p-5">
                            <p className="text-xs text-muted-foreground">{metric.label}</p>
                            <div className="mt-2 flex items-end justify-between">
                                <p className="font-display text-3xl font-bold">{metric.value}</p>
                                <span className="text-xs font-medium text-muted-foreground">
                                    {metric.detail}
                                </span>
                            </div>
                        </article>
                    ))}
                </section>
                <div className="mt-6 flex gap-2 overflow-x-auto">
                    <button
                        type="button"
                        onClick={() =>
                            router.get("/tables", {}, { preserveState: true, replace: true })
                        }
                        className={`min-h-11 shrink-0 rounded-full px-4 text-sm font-bold ${filters.zone === null ? "bg-foreground text-background" : "border bg-card hover:bg-secondary"}`}
                    >
                        Semua meja
                    </button>
                    {zones.map((zone) => (
                        <button
                            key={zone}
                            type="button"
                            onClick={() =>
                                router.get(
                                    "/tables",
                                    { zone },
                                    { preserveState: true, replace: true },
                                )
                            }
                            className={`min-h-11 shrink-0 rounded-full px-4 text-sm font-bold ${filters.zone === zone ? "bg-foreground text-background" : "border bg-card hover:bg-secondary"}`}
                        >
                            {zone}
                        </button>
                    ))}
                </div>
                <section className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {tables.length === 0 ? (
                        <div className="col-span-full rounded-[1.5rem] border border-dashed p-10 text-center">
                            <Table2 className="mx-auto size-8 text-muted-foreground" />
                            <p className="mt-3 font-semibold">Belum ada meja.</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Tambahkan meja untuk mulai menyiapkan QR.
                            </p>
                        </div>
                    ) : (
                        tables.map((table) => (
                            <article
                                key={table.id}
                                className="overflow-hidden rounded-[1.5rem] border bg-card"
                            >
                                <div className="flex items-start justify-between p-5">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="font-display text-xl font-bold">
                                                {table.name}
                                            </h2>
                                            <span
                                                className={`size-2 rounded-full ${table.is_active ? "bg-emerald-500" : "bg-slate-300"}`}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {table.zone ?? "Tanpa zona"} · kapasitas{" "}
                                            {table.capacity}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-muted px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                                        {table.code}
                                    </span>
                                </div>
                                <div className="mx-5 flex min-h-32 items-center justify-center overflow-hidden rounded-2xl bg-[#e9e1cf] p-4 text-[#273024]">
                                    {table.qr_url ? (
                                        <img
                                            src={table.qr_url}
                                            alt={`QR untuk ${table.name}`}
                                            className="size-36 max-w-full object-contain"
                                        />
                                    ) : (
                                        <div className="flex flex-col items-center">
                                            <QrCode className="size-16" strokeWidth={1.5} />
                                            <p className="mt-2 text-center text-xs font-semibold">
                                                {table.has_active_qr
                                                    ? "Artifact QR belum tersedia"
                                                    : "QR belum dibuat"}
                                            </p>
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2 p-5 pb-0">
                                    {table.qr_download_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="min-h-10 rounded-full"
                                        >
                                            <a href={table.qr_download_url} download>
                                                <Download aria-hidden="true" /> Unduh
                                            </a>
                                        </Button>
                                    )}
                                    {table.qr_print_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="min-h-10 rounded-full"
                                        >
                                            <a
                                                href={table.qr_print_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Printer aria-hidden="true" /> Cetak
                                            </a>
                                        </Button>
                                    )}
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        className="min-h-10 rounded-full"
                                        disabled={processingTableId === table.id}
                                        onClick={() => regenerateQr(table)}
                                    >
                                        <RefreshCw aria-hidden="true" />
                                        {table.has_active_qr ? "Regenerasi" : "Buat QR"}
                                    </Button>
                                    {table.has_active_qr && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="min-h-10 rounded-full text-destructive hover:text-destructive"
                                            disabled={processingTableId === table.id}
                                            onClick={() => revokeQr(table)}
                                        >
                                            <Ban aria-hidden="true" /> Cabut
                                        </Button>
                                    )}
                                </div>
                                <div className="p-5 text-xs text-muted-foreground">
                                    {table.has_active_qr
                                        ? "QR ini membuka menu outlet aktif dan terikat pada meja ini."
                                        : "Buat QR untuk mengaktifkan akses menu meja."}
                                </div>
                            </article>
                        ))
                    )}
                </section>
            </div>
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah meja</DialogTitle>
                        <DialogDescription>
                            QR aman akan dibuat otomatis dan dapat langsung diunduh atau dicetak.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={createTable} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="table-name">Nama meja</Label>
                            <Input
                                id="table-name"
                                value={form.data.name}
                                onChange={(event) => form.setData("name", event.target.value)}
                                autoFocus
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="table-code">Kode meja</Label>
                            <Input
                                id="table-code"
                                value={form.data.code}
                                onChange={(event) => form.setData("code", event.target.value)}
                                placeholder="TBL-001"
                                required
                            />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="table-zone">
                                    Zona{" "}
                                    <span className="font-normal text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>
                                <Input
                                    id="table-zone"
                                    value={form.data.zone}
                                    onChange={(event) => form.setData("zone", event.target.value)}
                                    placeholder="Teras"
                                />
                                <InputError message={form.errors.zone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="table-capacity">Kapasitas</Label>
                                <Input
                                    id="table-capacity"
                                    type="number"
                                    min="1"
                                    value={form.data.capacity}
                                    onChange={(event) =>
                                        form.setData("capacity", event.target.value)
                                    }
                                    required
                                />
                                <InputError message={form.errors.capacity} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />} Tambah meja
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Tables.layout = { breadcrumbs: [{ title: "Meja & QR", href: "/tables" }] };
