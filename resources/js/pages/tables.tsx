import { Head, router, useForm } from '@inertiajs/react';
import {
    Ban,
    CircleCheck,
    Download,
    Edit3,
    MapPin,
    Plus,
    Printer,
    QrCode,
    RefreshCw,
    Table2,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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
    summary: {
        tables: number;
        active_tables: number;
        active_qr_tokens: number;
        zones: number;
    };
    zones: string[];
    tables: DiningTable[];
};

export default function Tables({ filters, summary, zones, tables }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingTable, setEditingTable] = useState<DiningTable | null>(null);
    const [processingTableId, setProcessingTableId] = useState<number | null>(
        null,
    );
    const form = useForm({
        name: '',
        code: '',
        zone: '',
        capacity: '4',
        is_active: true,
    });
    const subscriptionError = (
        form.errors as unknown as Record<string, string | undefined>
    ).subscription;

    function showActionError(errors: Record<string, string>, action: string) {
        const message = Object.values(errors)[0];

        toast.error(`${action} gagal`, {
            description: message ?? 'Coba lagi dalam beberapa saat.',
        });
    }

    function createTable(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                closeTableForm();
            },
        };

        if (editingTable) {
            form.patch(`/tables/${editingTable.id}`, options);
        } else {
            form.post('/tables', options);
        }
    }

    function openCreateTable() {
        setEditingTable(null);
        form.reset();
        form.clearErrors();
        setIsCreateOpen(true);
    }

    function editTable(table: DiningTable) {
        setEditingTable(table);
        form.clearErrors();
        form.setData({
            name: table.name,
            code: table.code,
            zone: table.zone ?? '',
            capacity: String(table.capacity),
            is_active: table.is_active,
        });
        setIsCreateOpen(true);
    }

    function closeTableForm() {
        form.reset();
        form.clearErrors();
        setEditingTable(null);
        setIsCreateOpen(false);
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
                onError: (errors) => showActionError(errors, 'Regenerasi QR'),
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
                onError: (errors) => showActionError(errors, 'Pencabutan QR'),
            },
        );
    }

    const qrCoverage =
        summary.active_tables > 0
            ? Math.min(
                  100,
                  Math.round(
                      (summary.active_qr_tokens / summary.active_tables) * 100,
                  ),
              )
            : 0;
    const selectedZoneLabel = filters.zone
        ? `Zona ${filters.zone}`
        : 'Semua zona';

    return (
        <>
            <Head title="Meja & QR" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="max-w-2xl">
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Operasional meja
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Meja & QR
                        </h1>
                        <p className="text-muted-foreground mt-2 text-sm sm:text-base">
                            Siapkan akses menu untuk setiap meja dan pastikan
                            tamu selalu bisa mulai memesan dengan satu pindai.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        className="min-h-12 rounded-xl shadow-[0_14px_30px_-18px_var(--primary)]"
                        onClick={openCreateTable}
                    >
                        <Plus aria-hidden="true" /> Tambah meja
                    </Button>
                </header>

                <section className="mt-8 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)]">
                    <article className="bg-foreground text-background relative isolate overflow-hidden rounded-[1.75rem] p-6 shadow-[0_28px_70px_-44px_rgba(53,44,31,0.8)] sm:p-8">
                        <div
                            className="border-primary-foreground/10 absolute -right-20 -bottom-32 size-80 rounded-full border-[46px]"
                            aria-hidden="true"
                        />
                        <div
                            className="border-primary-foreground/10 absolute -top-24 -left-24 size-56 rounded-full border-[30px]"
                            aria-hidden="true"
                        />
                        <div className="relative">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div className="text-primary-foreground/65 flex items-center gap-2 text-xs font-bold tracking-[0.16em] uppercase">
                                        <QrCode
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        QR outlet
                                    </div>
                                    <h2 className="font-display mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                                        Siap dipindai.
                                    </h2>
                                </div>
                                <span className="bg-background/10 inline-flex min-h-9 items-center rounded-full px-3 text-xs font-bold">
                                    {qrCoverage}% terpasang
                                </span>
                            </div>
                            <p className="text-background/65 mt-3 max-w-md text-sm">
                                Setiap QR terikat pada meja dan membuka menu
                                outlet aktif tanpa membagikan token rahasia.
                            </p>
                            <div className="mt-8">
                                <div className="flex items-end justify-between gap-4">
                                    <p className="text-background/65 text-xs font-bold tracking-[0.12em] uppercase">
                                        QR aktif / meja aktif
                                    </p>
                                    <p className="font-display text-2xl font-bold tabular-nums">
                                        {summary.active_qr_tokens}
                                        <span className="text-background/55 ml-1 text-sm font-normal">
                                            / {summary.active_tables}
                                        </span>
                                    </p>
                                </div>
                                <div
                                    className="bg-background/15 mt-3 h-2 overflow-hidden rounded-full"
                                    role="progressbar"
                                    aria-label="Persentase QR aktif pada meja aktif"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={qrCoverage}
                                >
                                    <div
                                        className="bg-primary h-full rounded-full transition-[width] duration-500"
                                        style={{ width: `${qrCoverage}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    </article>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            {
                                label: 'Total meja',
                                value: summary.tables,
                                detail: `${summary.zones} zona terdaftar`,
                                icon: Table2,
                                tone: 'bg-primary/10 text-primary',
                            },
                            {
                                label: 'Meja aktif',
                                value: summary.active_tables,
                                detail: 'Siap menerima pesanan',
                                icon: CircleCheck,
                                tone: 'bg-accent text-accent-foreground',
                            },
                            {
                                label: 'QR aktif',
                                value: summary.active_qr_tokens,
                                detail: 'Token tersimpan aman',
                                icon: QrCode,
                                tone: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                            },
                        ].map((metric) => (
                            <article
                                key={metric.label}
                                className="border-border/70 bg-card flex min-h-[10rem] flex-col justify-between rounded-[1.3rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)]"
                            >
                                <span
                                    className={`inline-flex size-11 items-center justify-center rounded-xl ${metric.tone}`}
                                >
                                    <metric.icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <p className="text-muted-foreground text-xs">
                                        {metric.label}
                                    </p>
                                    <p className="font-display mt-1 text-2xl font-bold tabular-nums">
                                        {metric.value}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {metric.detail}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="border-border/70 mt-10 flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Persebaran meja
                        </p>
                        <h2 className="font-display mt-2 text-2xl font-bold tracking-tight">
                            Pilih zona kerja
                        </h2>
                    </div>
                    <fieldset className="flex flex-wrap gap-2">
                        <legend className="sr-only">Filter zona meja</legend>
                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    '/tables',
                                    {},
                                    { preserveState: true, replace: true },
                                )
                            }
                            aria-pressed={filters.zone === null}
                            className={`focus-visible:ring-ring min-h-11 rounded-full px-4 text-sm font-bold transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none ${filters.zone === null ? 'bg-foreground text-background' : 'border-border/70 bg-card hover:bg-secondary border'}`}
                        >
                            Semua meja
                        </button>
                        {zones.map((zone) => (
                            <button
                                key={zone}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        '/tables',
                                        { zone },
                                        { preserveState: true, replace: true },
                                    )
                                }
                                aria-pressed={filters.zone === zone}
                                className={`focus-visible:ring-ring min-h-11 rounded-full px-4 text-sm font-bold transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none ${filters.zone === zone ? 'bg-foreground text-background' : 'border-border/70 bg-card hover:bg-secondary border'}`}
                            >
                                {zone}
                            </button>
                        ))}
                    </fieldset>
                </section>

                <div className="mt-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Inventaris meja
                        </p>
                        <h2 className="font-display mt-2 text-2xl font-bold tracking-tight">
                            QR yang siap dipakai
                        </h2>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {tables.length} meja · {selectedZoneLabel}
                    </p>
                </div>

                <section className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {tables.length === 0 ? (
                        <div className="border-border/80 bg-card col-span-full rounded-[1.5rem] border border-dashed px-6 py-14 text-center">
                            <span className="bg-primary/10 text-primary mx-auto inline-flex size-14 items-center justify-center rounded-2xl">
                                <Table2 className="size-7" aria-hidden="true" />
                            </span>
                            <p className="mt-4 font-semibold">
                                {filters.zone
                                    ? `Belum ada meja di zona ${filters.zone}.`
                                    : 'Belum ada meja.'}
                            </p>
                            <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
                                Tambahkan meja untuk mulai menyiapkan QR dan
                                membuka akses menu bagi tamu.
                            </p>
                            <Button
                                variant="outline"
                                className="mt-5 min-h-11 rounded-xl"
                                onClick={openCreateTable}
                            >
                                <Plus aria-hidden="true" /> Tambah meja
                            </Button>
                        </div>
                    ) : (
                        tables.map((table) => {
                            const isProcessing = processingTableId === table.id;

                            return (
                                <article
                                    key={table.id}
                                    className="group border-border/70 bg-card flex flex-col overflow-hidden rounded-[1.5rem] border shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)] transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-[0_24px_60px_-42px_rgba(53,44,31,0.9)]"
                                >
                                    <div className="flex items-start justify-between gap-4 p-5 pb-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-display truncate text-xl font-bold">
                                                    {table.name}
                                                </h3>
                                                <span
                                                    className={`inline-flex min-h-6 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-bold ${table.is_active ? 'bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground'}`}
                                                >
                                                    <span
                                                        className={`size-1.5 rounded-full ${table.is_active ? 'bg-emerald-600 dark:bg-emerald-300' : 'bg-muted-foreground'}`}
                                                        aria-hidden="true"
                                                    />
                                                    {table.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </span>
                                            </div>
                                            <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <MapPin
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {table.zone ?? 'Tanpa zona'}
                                                </span>
                                                <span aria-hidden="true">
                                                    ·
                                                </span>
                                                <span className="inline-flex items-center gap-1.5">
                                                    <UsersRound
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {table.capacity} kursi
                                                </span>
                                            </div>
                                        </div>
                                        <span className="bg-muted text-muted-foreground shrink-0 rounded-full px-2.5 py-1 font-mono text-[11px] font-semibold">
                                            {table.code}
                                        </span>
                                    </div>

                                    <div className="bg-accent/40 text-accent-foreground dark:bg-accent/30 mx-5 overflow-hidden rounded-[1.25rem] p-4">
                                        <div className="flex items-center justify-between gap-3 text-[11px] font-bold tracking-[0.12em] uppercase">
                                            <span>Identitas digital</span>
                                            <span className="bg-background/60 rounded-full px-2 py-1 tracking-normal normal-case">
                                                {table.has_active_qr
                                                    ? 'QR aktif'
                                                    : 'Belum aktif'}
                                            </span>
                                        </div>
                                        <div className="border-accent-foreground/10 bg-background/80 mt-3 flex min-h-40 items-center justify-center rounded-xl border p-3">
                                            {table.qr_url ? (
                                                <img
                                                    src={table.qr_url}
                                                    alt={`QR untuk ${table.name}`}
                                                    className="size-36 max-w-full object-contain"
                                                />
                                            ) : (
                                                <div className="flex flex-col items-center">
                                                    <QrCode
                                                        className="size-16"
                                                        strokeWidth={1.5}
                                                    />
                                                    <p className="mt-2 text-center text-xs font-semibold">
                                                        {table.has_active_qr
                                                            ? 'Artifact QR belum tersedia'
                                                            : 'QR belum dibuat'}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-auto p-5 pb-0">
                                        {(table.qr_download_url ||
                                            table.qr_print_url) && (
                                            <div className="flex flex-wrap gap-2">
                                                {table.qr_download_url && (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        className="min-h-11 flex-1 rounded-xl"
                                                    >
                                                        <a
                                                            href={
                                                                table.qr_download_url
                                                            }
                                                            download
                                                            aria-label={`Unduh QR ${table.name}`}
                                                        >
                                                            <Download aria-hidden="true" />{' '}
                                                            Unduh QR
                                                        </a>
                                                    </Button>
                                                )}
                                                {table.qr_print_url && (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        className="min-h-11 flex-1 rounded-xl"
                                                    >
                                                        <a
                                                            href={
                                                                table.qr_print_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            aria-label={`Cetak QR ${table.name}`}
                                                        >
                                                            <Printer aria-hidden="true" />{' '}
                                                            Cetak QR
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                className="min-h-11 flex-1 rounded-xl"
                                                onClick={() => editTable(table)}
                                            >
                                                <Edit3 aria-hidden="true" />{' '}
                                                Ubah meja
                                            </Button>
                                            <Button
                                                variant="secondary"
                                                className="min-h-11 flex-1 rounded-xl"
                                                disabled={isProcessing}
                                                onClick={() =>
                                                    regenerateQr(table)
                                                }
                                                aria-busy={isProcessing}
                                                aria-label={`${table.has_active_qr ? 'Regenerasi' : 'Buat'} QR ${table.name}`}
                                            >
                                                {isProcessing ? (
                                                    <Spinner />
                                                ) : (
                                                    <RefreshCw aria-hidden="true" />
                                                )}
                                                {table.has_active_qr
                                                    ? 'Regenerasi QR'
                                                    : 'Buat QR'}
                                            </Button>
                                            {table.has_active_qr && (
                                                <Button
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive min-h-11 flex-1 rounded-xl"
                                                    disabled={isProcessing}
                                                    onClick={() =>
                                                        revokeQr(table)
                                                    }
                                                    aria-busy={isProcessing}
                                                    aria-label={`Cabut QR ${table.name}`}
                                                >
                                                    <Ban aria-hidden="true" />{' '}
                                                    Cabut QR
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    <p className="border-border/70 text-muted-foreground mx-5 mt-4 border-t py-4 text-xs leading-relaxed">
                                        {table.has_active_qr
                                            ? 'QR ini membuka menu outlet aktif dan terikat pada meja ini.'
                                            : 'Buat QR untuk mengaktifkan akses menu meja.'}
                                    </p>
                                </article>
                            );
                        })
                    )}
                </section>
            </div>
            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) =>
                    open ? setIsCreateOpen(true) : closeTableForm()
                }
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Inventaris outlet
                        </p>
                        <DialogTitle className="font-display text-2xl">
                            {editingTable ? 'Ubah meja' : 'Tambah meja'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingTable
                                ? 'Perubahan berlaku pada meja ini tanpa mengubah histori order.'
                                : 'QR aman akan dibuat otomatis dan dapat langsung diunduh atau dicetak.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={createTable} className="grid gap-5">
                        <InputError message={subscriptionError} />
                        <div className="grid gap-2">
                            <Label htmlFor="table-name">Nama meja</Label>
                            <Input
                                id="table-name"
                                className="min-h-11 rounded-xl"
                                aria-invalid={Boolean(form.errors.name)}
                                aria-describedby={
                                    form.errors.name
                                        ? 'table-name-error'
                                        : undefined
                                }
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoFocus
                                required
                            />
                            <InputError
                                id="table-name-error"
                                message={form.errors.name}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="table-code">Kode meja</Label>
                            <Input
                                id="table-code"
                                className="min-h-11 rounded-xl"
                                aria-invalid={Boolean(form.errors.code)}
                                aria-describedby={
                                    form.errors.code
                                        ? 'table-code-error'
                                        : undefined
                                }
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                placeholder="TBL-001"
                                required
                            />
                            <InputError
                                id="table-code-error"
                                message={form.errors.code}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="table-zone">
                                    Zona{' '}
                                    <span className="text-muted-foreground font-normal">
                                        (opsional)
                                    </span>
                                </Label>
                                <Input
                                    id="table-zone"
                                    className="min-h-11 rounded-xl"
                                    aria-invalid={Boolean(form.errors.zone)}
                                    aria-describedby={
                                        form.errors.zone
                                            ? 'table-zone-error'
                                            : undefined
                                    }
                                    value={form.data.zone}
                                    onChange={(event) =>
                                        form.setData('zone', event.target.value)
                                    }
                                    placeholder="Teras"
                                />
                                <InputError
                                    id="table-zone-error"
                                    message={form.errors.zone}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="table-capacity">
                                    Kapasitas
                                </Label>
                                <Input
                                    id="table-capacity"
                                    className="min-h-11 rounded-xl"
                                    aria-invalid={Boolean(form.errors.capacity)}
                                    aria-describedby={
                                        form.errors.capacity
                                            ? 'table-capacity-error'
                                            : undefined
                                    }
                                    type="number"
                                    min="1"
                                    value={form.data.capacity}
                                    onChange={(event) =>
                                        form.setData(
                                            'capacity',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    id="table-capacity-error"
                                    message={form.errors.capacity}
                                />
                            </div>
                        </div>
                        <Label
                            htmlFor="table-active"
                            className="border-border/70 bg-background flex min-h-11 items-center gap-3 rounded-xl border px-3 text-sm"
                        >
                            <input
                                id="table-active"
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                                className="accent-primary size-4"
                            />
                            Meja aktif dan dapat menerima pesanan
                        </Label>
                        <DialogFooter className="border-border/70 mt-1 border-t pt-5">
                            <Button
                                type="submit"
                                className="min-h-11 rounded-xl"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                {editingTable
                                    ? 'Simpan perubahan'
                                    : 'Tambah meja'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Tables.layout = { breadcrumbs: [{ title: 'Meja & QR', href: '/tables' }] };
