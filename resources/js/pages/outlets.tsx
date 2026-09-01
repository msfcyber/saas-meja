import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    MapPin,
    Pencil,
    Phone,
    Plus,
    ReceiptText,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Outlet = {
    id: number;
    name: string;
    code: string;
    slug: string;
    address: string | null;
    phone: string | null;
    timezone: string;
    currency: string;
    is_active: boolean;
    accepts_orders: boolean;
    products_count: number;
    tables_count: number;
    tax_settings: TaxSettings;
};

type TaxSettings = {
    tax_enabled: boolean;
    tax_name: string | null;
    tax_rate: string | null;
    tax_inclusive: boolean;
};

type Timezone = {
    value: string;
    label: string;
};

type OutletForm = {
    name: string;
    code: string;
    address: string;
    phone: string;
    timezone: string;
    currency: string;
    is_active: boolean;
    accepts_orders: boolean;
};

type TaxSettingsForm = {
    tax_enabled: boolean;
    tax_name: string;
    tax_rate: string;
    tax_inclusive: boolean;
};

type Props = {
    outlets: Outlet[];
    timezones: Timezone[];
    usage: { current: number; limit: number | null };
    can_add: boolean;
    can_manage_tax: boolean;
    limit_message: string | null;
};

const emptyForm: OutletForm = {
    name: '',
    code: '',
    address: '',
    phone: '',
    timezone: 'Asia/Jakarta',
    currency: 'IDR',
    is_active: true,
    accepts_orders: true,
};

const emptyTaxForm: TaxSettingsForm = {
    tax_enabled: false,
    tax_name: 'Pajak Restoran',
    tax_rate: '10',
    tax_inclusive: false,
};

export default function Outlets({
    outlets,
    timezones,
    usage,
    can_add,
    can_manage_tax,
    limit_message,
}: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingOutlet, setEditingOutlet] = useState<Outlet | null>(null);
    const [isTaxOpen, setIsTaxOpen] = useState(false);
    const [editingTaxOutlet, setEditingTaxOutlet] = useState<Outlet | null>(
        null,
    );
    const form = useForm<OutletForm>(emptyForm);
    const taxForm = useForm<TaxSettingsForm>(emptyTaxForm);
    const subscriptionError = (
        form.errors as Record<string, string | undefined>
    ).subscription;

    function openCreate() {
        setEditingOutlet(null);
        form.reset();
        form.clearErrors();
        setIsOpen(true);
    }

    function openEdit(outlet: Outlet) {
        setEditingOutlet(outlet);
        form.setData('name', outlet.name);
        form.setData('code', outlet.code);
        form.setData('address', outlet.address ?? '');
        form.setData('phone', outlet.phone ?? '');
        form.setData('timezone', outlet.timezone);
        form.setData('currency', outlet.currency);
        form.setData('is_active', outlet.is_active);
        form.setData('accepts_orders', outlet.accepts_orders);
        form.clearErrors();
        setIsOpen(true);
    }

    function closeDialog() {
        setIsOpen(false);
        setEditingOutlet(null);
        form.reset();
        form.clearErrors();
    }

    function openTaxSettings(outlet: Outlet) {
        setEditingTaxOutlet(outlet);
        taxForm.setData('tax_enabled', outlet.tax_settings.tax_enabled);
        taxForm.setData(
            'tax_name',
            outlet.tax_settings.tax_name ?? 'Pajak Restoran',
        );
        taxForm.setData('tax_rate', outlet.tax_settings.tax_rate ?? '10');
        taxForm.setData('tax_inclusive', outlet.tax_settings.tax_inclusive);
        taxForm.clearErrors();
        setIsTaxOpen(true);
    }

    function closeTaxDialog() {
        setIsTaxOpen(false);
        setEditingTaxOutlet(null);
        taxForm.reset();
        taxForm.clearErrors();
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingOutlet) {
            form.patch(`/outlets/${editingOutlet.id}`, {
                preserveScroll: true,
                onSuccess: closeDialog,
            });

            return;
        }

        form.post('/outlets', {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    }

    function submitTaxSettings(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!editingTaxOutlet) {
            return;
        }

        taxForm.patch(`/outlets/${editingTaxOutlet.id}/tax-settings`, {
            preserveScroll: true,
            onSuccess: closeTaxDialog,
        });
    }

    return (
        <>
            <Head title="Outlet" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Workspace bisnis
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Outlet
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm">
                            Kelola lokasi operasional, status penerimaan order,
                            dan identitas setiap outlet.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        className="min-h-12 rounded-full"
                        onClick={openCreate}
                        disabled={!can_add}
                        title={
                            can_add ? undefined : (limit_message ?? undefined)
                        }
                    >
                        <Plus aria-hidden="true" /> Tambah outlet
                    </Button>
                </div>

                {!can_add && limit_message && (
                    <div
                        className="mt-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200"
                        role="alert"
                    >
                        {limit_message}
                    </div>
                )}

                <section className="mt-8 grid gap-4 sm:grid-cols-3">
                    <article className="bg-card flex items-center gap-4 rounded-[1.3rem] border p-5">
                        <span className="bg-secondary text-primary flex size-11 items-center justify-center rounded-xl">
                            <Building2 className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-xs">
                                Outlet aktif
                            </p>
                            <p className="mt-1 text-xl font-bold">
                                {usage.current}
                                <span className="text-muted-foreground ml-1 text-xs font-normal">
                                    /{' '}
                                    {usage.limit === null
                                        ? 'tak terbatas'
                                        : usage.limit}
                                </span>
                            </p>
                        </div>
                    </article>
                    <article className="bg-card flex items-center gap-4 rounded-[1.3rem] border p-5">
                        <span className="bg-secondary text-primary flex size-11 items-center justify-center rounded-xl">
                            <Store className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-xs">
                                Total terdaftar
                            </p>
                            <p className="mt-1 text-xl font-bold">
                                {outlets.length}
                            </p>
                        </div>
                    </article>
                    <article className="bg-card flex items-center gap-4 rounded-[1.3rem] border p-5">
                        <span className="bg-secondary text-primary flex size-11 items-center justify-center rounded-xl">
                            <MapPin className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-xs">
                                Zona waktu
                            </p>
                            <p className="mt-1 text-xl font-bold">
                                {outlets[0]?.timezone ?? '-'}
                            </p>
                        </div>
                    </article>
                </section>

                <section className="mt-5 grid gap-4 lg:grid-cols-2">
                    {outlets.length === 0 ? (
                        <div
                            className="bg-card rounded-[1.5rem] border border-dashed p-8 text-center sm:col-span-2"
                            role="status"
                        >
                            <Store
                                className="text-primary mx-auto size-8"
                                aria-hidden="true"
                            />
                            <h2 className="font-display mt-4 text-xl font-bold">
                                Belum ada outlet
                            </h2>
                            <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                                Tambahkan lokasi pertama agar menu, meja, dan
                                order dapat dikelola.
                            </p>
                            {can_add && (
                                <Button
                                    className="mt-5 rounded-full"
                                    onClick={openCreate}
                                >
                                    <Plus aria-hidden="true" /> Tambah outlet
                                </Button>
                            )}
                        </div>
                    ) : (
                        outlets.map((outlet) => (
                            <article
                                key={outlet.id}
                                className="bg-card rounded-[1.5rem] border p-5 sm:p-6"
                            >
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="bg-secondary text-primary rounded-full px-3 py-1 text-xs font-bold tracking-wide">
                                                {outlet.code}
                                            </span>
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-bold ${outlet.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
                                            >
                                                {outlet.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <h2 className="font-display mt-3 truncate text-2xl font-bold">
                                            {outlet.name}
                                        </h2>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {outlet.timezone} ·{' '}
                                            {outlet.currency}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2 sm:justify-end">
                                        {can_manage_tax && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="min-h-10 rounded-full"
                                                onClick={() =>
                                                    openTaxSettings(outlet)
                                                }
                                            >
                                                <ReceiptText aria-hidden="true" />{' '}
                                                Pajak
                                            </Button>
                                        )}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="min-h-10 rounded-full"
                                            onClick={() => openEdit(outlet)}
                                        >
                                            <Pencil aria-hidden="true" /> Edit
                                        </Button>
                                    </div>
                                </div>

                                <div className="text-muted-foreground mt-5 grid gap-3 text-sm sm:grid-cols-3">
                                    <div className="flex items-start gap-2">
                                        <MapPin
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {outlet.address ||
                                                'Alamat belum diisi'}
                                        </span>
                                    </div>
                                    <div className="flex items-start gap-2">
                                        <Phone
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {outlet.phone ||
                                                'Nomor telepon belum diisi'}
                                        </span>
                                    </div>
                                    <div className="flex items-start gap-2">
                                        <ReceiptText
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {outlet.tax_settings.tax_enabled
                                                ? `${outlet.tax_settings.tax_name ?? 'Pajak'} ${outlet.tax_settings.tax_rate}%${outlet.tax_settings.tax_inclusive ? ' · termasuk pajak' : ''}`
                                                : 'Pajak tidak aktif'}
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-5 grid grid-cols-2 gap-3 border-t pt-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground text-xs">
                                            Produk
                                        </p>
                                        <p className="mt-1 font-bold">
                                            {outlet.products_count}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs">
                                            Meja
                                        </p>
                                        <p className="mt-1 font-bold">
                                            {outlet.tables_count}
                                        </p>
                                    </div>
                                </div>

                                <p className="text-muted-foreground mt-4 text-xs font-semibold">
                                    {outlet.accepts_orders
                                        ? 'Outlet menerima order customer.'
                                        : 'Penerimaan order sedang dimatikan.'}
                                </p>
                            </article>
                        ))
                    )}
                </section>
            </div>

            <Dialog
                open={isOpen}
                onOpenChange={(open) => !open && closeDialog()}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingOutlet ? 'Edit outlet' : 'Tambah outlet'}
                        </DialogTitle>
                        <DialogDescription>
                            Data outlet digunakan pada menu publik, checkout,
                            QR, dan laporan.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-4">
                        <InputError
                            id="outlet-subscription-error"
                            message={subscriptionError}
                        />
                        <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                            <div className="grid gap-2">
                                <Label htmlFor="outlet-name">Nama outlet</Label>
                                <Input
                                    id="outlet-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    autoFocus
                                    required
                                    aria-invalid={Boolean(form.errors.name)}
                                    aria-describedby={
                                        form.errors.name
                                            ? 'outlet-name-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="outlet-name-error"
                                    message={form.errors.name}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="outlet-code">Kode</Label>
                                <Input
                                    id="outlet-code"
                                    value={form.data.code}
                                    onChange={(event) =>
                                        form.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="OUT-002"
                                    required
                                    aria-invalid={Boolean(form.errors.code)}
                                    aria-describedby={
                                        form.errors.code
                                            ? 'outlet-code-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="outlet-code-error"
                                    message={form.errors.code}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="outlet-address">Alamat</Label>
                            <Input
                                id="outlet-address"
                                value={form.data.address}
                                onChange={(event) =>
                                    form.setData('address', event.target.value)
                                }
                                autoComplete="street-address"
                                aria-invalid={Boolean(form.errors.address)}
                                aria-describedby={
                                    form.errors.address
                                        ? 'outlet-address-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="outlet-address-error"
                                message={form.errors.address}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="outlet-phone">
                                    Nomor telepon
                                </Label>
                                <Input
                                    id="outlet-phone"
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="tel"
                                    inputMode="tel"
                                    aria-invalid={Boolean(form.errors.phone)}
                                    aria-describedby={
                                        form.errors.phone
                                            ? 'outlet-phone-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="outlet-phone-error"
                                    message={form.errors.phone}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="outlet-timezone">
                                    Zona waktu
                                </Label>
                                <select
                                    id="outlet-timezone"
                                    value={form.data.timezone}
                                    onChange={(event) =>
                                        form.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    aria-invalid={Boolean(form.errors.timezone)}
                                    aria-describedby={
                                        form.errors.timezone
                                            ? 'outlet-timezone-error'
                                            : undefined
                                    }
                                >
                                    {timezones.map((timezone) => (
                                        <option
                                            key={timezone.value}
                                            value={timezone.value}
                                        >
                                            {timezone.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    id="outlet-timezone-error"
                                    message={form.errors.timezone}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2 sm:max-w-[10rem]">
                            <Label htmlFor="outlet-currency">Mata uang</Label>
                            <select
                                id="outlet-currency"
                                value={form.data.currency}
                                onChange={(event) =>
                                    form.setData('currency', event.target.value)
                                }
                                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                aria-invalid={Boolean(form.errors.currency)}
                                aria-describedby={
                                    form.errors.currency
                                        ? 'outlet-currency-error'
                                        : undefined
                                }
                            >
                                <option value="IDR">IDR - Rupiah</option>
                            </select>
                            <InputError
                                id="outlet-currency-error"
                                message={form.errors.currency}
                            />
                        </div>

                        <div className="bg-muted/30 grid gap-3 rounded-xl border p-4">
                            <div className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    id="outlet-active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.is_active,
                                    )}
                                    aria-describedby={
                                        form.errors.is_active
                                            ? 'outlet-active-error'
                                            : undefined
                                    }
                                />
                                <div>
                                    <Label
                                        htmlFor="outlet-active"
                                        className="font-semibold"
                                    >
                                        Outlet aktif
                                    </Label>
                                    <span className="text-muted-foreground mt-1 block text-xs">
                                        Outlet aktif dapat dipilih sebagai
                                        konteks operasional.
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    id="outlet-accepts-orders"
                                    checked={form.data.accepts_orders}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'accepts_orders',
                                            checked === true,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.accepts_orders,
                                    )}
                                    aria-describedby={
                                        form.errors.accepts_orders
                                            ? 'outlet-accepts-orders-error'
                                            : undefined
                                    }
                                />
                                <div>
                                    <Label
                                        htmlFor="outlet-accepts-orders"
                                        className="font-semibold"
                                    >
                                        Terima order customer
                                    </Label>
                                    <span className="text-muted-foreground mt-1 block text-xs">
                                        QR dan checkout akan mengikuti status
                                        ini.
                                    </span>
                                </div>
                            </div>
                            <InputError
                                id="outlet-active-error"
                                message={form.errors.is_active}
                            />
                            <InputError
                                id="outlet-accepts-orders-error"
                                message={form.errors.accepts_orders}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={closeDialog}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan outlet'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isTaxOpen}
                onOpenChange={(open) => !open && closeTaxDialog()}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            {editingTaxOutlet?.name ?? 'Outlet'}
                        </p>
                        <DialogTitle>Pengaturan pajak</DialogTitle>
                        <DialogDescription>
                            Atur pajak yang digunakan pada checkout outlet ini.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitTaxSettings} className="grid gap-4">
                        <div className="bg-muted/30 grid gap-3 rounded-xl border p-4">
                            <div className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    id="outlet-tax-enabled"
                                    checked={taxForm.data.tax_enabled}
                                    onCheckedChange={(checked) =>
                                        taxForm.setData(
                                            'tax_enabled',
                                            checked === true,
                                        )
                                    }
                                    className="mt-0.5 size-5"
                                    aria-invalid={Boolean(
                                        taxForm.errors.tax_enabled,
                                    )}
                                    aria-describedby={
                                        taxForm.errors.tax_enabled
                                            ? 'outlet-tax-enabled-error'
                                            : undefined
                                    }
                                />
                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor="outlet-tax-enabled"
                                        className="cursor-pointer font-semibold"
                                    >
                                        Aktifkan pajak untuk outlet ini
                                    </Label>
                                    <p className="text-muted-foreground text-xs">
                                        Checkout akan menghitung pajak
                                        berdasarkan tarif ini.
                                    </p>
                                </div>
                            </div>
                            <InputError
                                id="outlet-tax-enabled-error"
                                message={taxForm.errors.tax_enabled}
                            />
                        </div>

                        {taxForm.data.tax_enabled && (
                            <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="outlet-tax-name">
                                        Nama pajak
                                    </Label>
                                    <Input
                                        id="outlet-tax-name"
                                        value={taxForm.data.tax_name}
                                        onChange={(event) =>
                                            taxForm.setData(
                                                'tax_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Pajak Restoran"
                                        required
                                        aria-invalid={Boolean(
                                            taxForm.errors.tax_name,
                                        )}
                                        aria-describedby={
                                            taxForm.errors.tax_name
                                                ? 'outlet-tax-name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="outlet-tax-name-error"
                                        message={taxForm.errors.tax_name}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="outlet-tax-rate">
                                        Tarif pajak (%)
                                    </Label>
                                    <Input
                                        id="outlet-tax-rate"
                                        type="text"
                                        inputMode="decimal"
                                        pattern="[0-9]+(\\.[0-9]{1,2})?"
                                        value={taxForm.data.tax_rate}
                                        onChange={(event) =>
                                            taxForm.setData(
                                                'tax_rate',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="10 atau 10.5"
                                        required
                                        aria-invalid={Boolean(
                                            taxForm.errors.tax_rate,
                                        )}
                                        aria-describedby={
                                            taxForm.errors.tax_rate
                                                ? 'outlet-tax-rate-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="outlet-tax-rate-error"
                                        message={taxForm.errors.tax_rate}
                                    />
                                </div>
                                <div className="flex items-start gap-3 sm:col-span-2">
                                    <Checkbox
                                        id="outlet-tax-inclusive"
                                        checked={taxForm.data.tax_inclusive}
                                        onCheckedChange={(checked) =>
                                            taxForm.setData(
                                                'tax_inclusive',
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5 size-5"
                                        aria-invalid={Boolean(
                                            taxForm.errors.tax_inclusive,
                                        )}
                                        aria-describedby={
                                            taxForm.errors.tax_inclusive
                                                ? 'outlet-tax-inclusive-error'
                                                : undefined
                                        }
                                    />
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="outlet-tax-inclusive"
                                            className="cursor-pointer font-semibold"
                                        >
                                            Harga menu sudah termasuk pajak
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            Nonaktifkan jika pajak ditambahkan
                                            setelah subtotal.
                                        </p>
                                    </div>
                                </div>
                                <InputError
                                    id="outlet-tax-inclusive-error"
                                    message={taxForm.errors.tax_inclusive}
                                />
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={closeTaxDialog}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={taxForm.processing}>
                                {taxForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan pajak'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Outlets.layout = { breadcrumbs: [{ title: 'Outlet', href: '/outlets' }] };
