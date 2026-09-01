import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    MapPin,
    ReceiptText,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Timezone = {
    value: string;
    label: string;
};

type Props = {
    timezones: Timezone[];
};

const steps = [
    { title: 'Bisnis', description: 'Identitas usaha', icon: Store },
    { title: 'Outlet', description: 'Lokasi pertama', icon: MapPin },
    { title: 'Pajak', description: 'Atur nanti juga bisa', icon: ReceiptText },
];

export default function CreateOnboarding({ timezones }: Props) {
    const [step, setStep] = useState(0);
    const form = useForm({
        business_name: '',
        outlet_name: '',
        address: '',
        phone: '',
        timezone: 'Asia/Jakarta',
        tax_enabled: false,
        tax_name: 'Pajak Restoran',
        tax_rate: '10',
        tax_inclusive: false,
    });

    function focusField(field: string) {
        window.setTimeout(() => document.getElementById(field)?.focus(), 0);
    }

    function stepForField(field: string): number {
        if (field.startsWith('tax_')) {
            return 2;
        }

        if (['outlet_name', 'address', 'phone', 'timezone'].includes(field)) {
            return 1;
        }

        return 0;
    }

    function validateStep(
        stepToValidate: number,
        clearErrors = true,
    ): string[] {
        if (clearErrors) {
            form.clearErrors();
        }

        const invalidFields: string[] = [];

        if (stepToValidate === 0 && form.data.business_name.trim() === '') {
            form.setError('business_name', 'Nama bisnis wajib diisi.');
            invalidFields.push('business_name');
        }

        if (stepToValidate === 1) {
            if (form.data.outlet_name.trim() === '') {
                form.setError('outlet_name', 'Nama outlet wajib diisi.');
                invalidFields.push('outlet_name');
            }

            if (form.data.timezone.trim() === '') {
                form.setError('timezone', 'Zona waktu wajib dipilih.');
                invalidFields.push('timezone');
            }
        }

        if (stepToValidate === 2 && form.data.tax_enabled) {
            if (form.data.tax_name.trim() === '') {
                form.setError('tax_name', 'Nama pajak wajib diisi.');
                invalidFields.push('tax_name');
            }

            const taxRate = Number(form.data.tax_rate);

            if (
                !form.data.tax_rate.trim() ||
                Number.isNaN(taxRate) ||
                taxRate <= 0 ||
                taxRate > 100
            ) {
                form.setError(
                    'tax_rate',
                    'Tarif pajak harus lebih besar dari 0% dan maksimal 100%.',
                );
                invalidFields.push('tax_rate');
            }
        }

        return invalidFields;
    }

    function validateAll(): boolean {
        form.clearErrors();
        const invalidFields = [0, 1, 2].flatMap((stepToValidate) =>
            validateStep(stepToValidate, false),
        );

        if (invalidFields.length === 0) {
            return true;
        }

        setStep(stepForField(invalidFields[0]));
        focusField(invalidFields[0]);

        return false;
    }

    function next() {
        const invalidFields = validateStep(step);

        if (invalidFields.length > 0) {
            focusField(invalidFields[0]);

            return;
        }

        setStep((current) => Math.min(current + 1, steps.length - 1));
    }

    function previous() {
        setStep((current) => Math.max(current - 1, 0));
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!validateAll()) {
            return;
        }

        form.post('/onboarding', {
            onError: (errors) => {
                const firstError = Object.keys(errors)[0];

                if (firstError) {
                    setStep(stepForField(firstError));
                    focusField(firstError);
                }
            },
        });
    }

    const hasErrors = Object.keys(form.errors).length > 0;

    return (
        <>
            <Head title="Siapkan bisnis" />
            <div className="w-full max-w-3xl">
                <div className="bg-card/90 mb-6 rounded-3xl border p-4 shadow-[0_28px_80px_-50px_rgba(55,42,29,0.8)] backdrop-blur sm:mb-8 sm:p-6">
                    <p className="text-primary text-xs font-semibold tracking-[0.18em] uppercase">
                        Langkah {step + 1} dari {steps.length}
                    </p>
                    <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                        Siapkan ruang layananmu.
                    </h1>
                    <p className="text-muted-foreground mt-2 max-w-xl text-sm leading-6 sm:text-base">
                        Tiga langkah singkat sebelum kamu mulai menata menu dan
                        meja.
                    </p>

                    <ol
                        className="mt-6 grid grid-cols-3 gap-2"
                        aria-label="Progress onboarding"
                    >
                        {steps.map((item, index) => {
                            const Icon = item.icon;
                            const isCurrent = index === step;
                            const isComplete = index < step;

                            return (
                                <li key={item.title} className="min-w-0">
                                    <div
                                        className={`flex items-center gap-2 rounded-xl px-2 py-2 text-left sm:px-3 ${
                                            isCurrent
                                                ? 'bg-primary text-primary-foreground'
                                                : isComplete
                                                  ? 'bg-primary/10 text-primary'
                                                  : 'text-muted-foreground'
                                        }`}
                                        aria-current={
                                            isCurrent ? 'step' : undefined
                                        }
                                    >
                                        <span className="bg-background/10 flex size-7 shrink-0 items-center justify-center rounded-full border border-current/20">
                                            {isComplete ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <Icon className="size-4" />
                                            )}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-xs font-semibold sm:text-sm">
                                                {item.title}
                                            </span>
                                            <span className="hidden truncate text-[11px] opacity-75 sm:block">
                                                {item.description}
                                            </span>
                                        </span>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </div>

                <form
                    onSubmit={submit}
                    className="bg-card rounded-3xl border p-5 shadow-[0_28px_80px_-50px_rgba(55,42,29,0.8)] sm:p-8"
                >
                    {hasErrors && (
                        <div
                            id="onboarding-errors"
                            className="border-destructive/30 bg-destructive/5 text-destructive mb-6 rounded-xl border px-4 py-3 text-sm"
                            role="alert"
                            aria-live="assertive"
                            tabIndex={-1}
                        >
                            Periksa kembali data yang ditandai sebelum
                            melanjutkan.
                        </div>
                    )}

                    {step === 0 && (
                        <section aria-labelledby="business-heading">
                            <h2
                                id="business-heading"
                                className="font-display text-2xl font-bold"
                            >
                                Tentang bisnismu
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Gunakan nama yang akan muncul di area
                                pengelolaan Meja.
                            </p>
                            <div className="mt-7 grid gap-2">
                                <Label htmlFor="business_name">
                                    Nama bisnis
                                </Label>
                                <Input
                                    id="business_name"
                                    value={form.data.business_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'business_name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Contoh: Kedai Sore Group"
                                    autoComplete="organization"
                                    autoFocus
                                    required
                                    aria-invalid={Boolean(
                                        form.errors.business_name,
                                    )}
                                    aria-describedby={
                                        form.errors.business_name
                                            ? 'business-name-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="business-name-error"
                                    message={form.errors.business_name}
                                />
                            </div>
                        </section>
                    )}

                    {step === 1 && (
                        <section aria-labelledby="outlet-heading">
                            <h2
                                id="outlet-heading"
                                className="font-display text-2xl font-bold"
                            >
                                Outlet pertamamu
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Kamu dapat menambah outlet lain setelah setup
                                selesai.
                            </p>
                            <div className="mt-7 grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="outlet_name">
                                        Nama outlet
                                    </Label>
                                    <Input
                                        id="outlet_name"
                                        value={form.data.outlet_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'outlet_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Contoh: Kedai Sore Kemang"
                                        autoComplete="organization"
                                        required
                                        aria-invalid={Boolean(
                                            form.errors.outlet_name,
                                        )}
                                        aria-describedby={
                                            form.errors.outlet_name
                                                ? 'outlet-name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="outlet-name-error"
                                        message={form.errors.outlet_name}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="address">
                                        Alamat{' '}
                                        <span className="text-muted-foreground font-normal">
                                            (opsional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="address"
                                        value={form.data.address}
                                        onChange={(event) =>
                                            form.setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Jl. Sore Hari No. 8"
                                        autoComplete="street-address"
                                        aria-invalid={Boolean(
                                            form.errors.address,
                                        )}
                                        aria-describedby={
                                            form.errors.address
                                                ? 'address-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="address-error"
                                        message={form.errors.address}
                                    />
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2 sm:gap-5">
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">
                                            Nomor telepon{' '}
                                            <span className="text-muted-foreground font-normal">
                                                (opsional)
                                            </span>
                                        </Label>
                                        <Input
                                            id="phone"
                                            value={form.data.phone}
                                            onChange={(event) =>
                                                form.setData(
                                                    'phone',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="0812 3456 7890"
                                            autoComplete="tel"
                                            inputMode="tel"
                                            aria-invalid={Boolean(
                                                form.errors.phone,
                                            )}
                                            aria-describedby={
                                                form.errors.phone
                                                    ? 'phone-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="phone-error"
                                            message={form.errors.phone}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="timezone">
                                            Zona waktu
                                        </Label>
                                        <select
                                            id="timezone"
                                            value={form.data.timezone}
                                            onChange={(event) =>
                                                form.setData(
                                                    'timezone',
                                                    event.target.value,
                                                )
                                            }
                                            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                            aria-invalid={Boolean(
                                                form.errors.timezone,
                                            )}
                                            aria-describedby={
                                                form.errors.timezone
                                                    ? 'timezone-error'
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
                                            id="timezone-error"
                                            message={form.errors.timezone}
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {step === 2 && (
                        <section aria-labelledby="tax-heading">
                            <h2
                                id="tax-heading"
                                className="font-display text-2xl font-bold"
                            >
                                Pajak penjualan
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Lewati langkah ini jika belum menerapkan pajak.
                                Pengaturan ini bisa diubah nanti.
                            </p>
                            <div className="bg-muted/30 mt-7 rounded-2xl border p-4 sm:p-5">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="tax_enabled"
                                        checked={form.data.tax_enabled}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'tax_enabled',
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5 size-5"
                                    />
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="tax_enabled"
                                            className="cursor-pointer text-sm font-semibold"
                                        >
                                            Aktifkan pajak untuk outlet ini
                                        </Label>
                                        <p className="text-muted-foreground text-sm leading-5">
                                            Total checkout akan menghitung pajak
                                            menggunakan pengaturan ini.
                                        </p>
                                    </div>
                                </div>

                                {form.data.tax_enabled && (
                                    <div className="mt-5 grid gap-5 border-t pt-5 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="tax_name">
                                                Nama pajak
                                            </Label>
                                            <Input
                                                id="tax_name"
                                                value={form.data.tax_name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'tax_name',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Pajak Restoran"
                                                required
                                                aria-invalid={Boolean(
                                                    form.errors.tax_name,
                                                )}
                                                aria-describedby={
                                                    form.errors.tax_name
                                                        ? 'tax-name-error'
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                id="tax-name-error"
                                                message={form.errors.tax_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="tax_rate">
                                                Tarif pajak (%)
                                            </Label>
                                            <Input
                                                id="tax_rate"
                                                type="number"
                                                min="0.01"
                                                max="100"
                                                step="0.01"
                                                value={form.data.tax_rate}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'tax_rate',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                aria-invalid={Boolean(
                                                    form.errors.tax_rate,
                                                )}
                                                aria-describedby={
                                                    form.errors.tax_rate
                                                        ? 'tax-rate-error'
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                id="tax-rate-error"
                                                message={form.errors.tax_rate}
                                            />
                                        </div>
                                        <div className="flex items-start gap-3 sm:col-span-2">
                                            <Checkbox
                                                id="tax_inclusive"
                                                checked={
                                                    form.data.tax_inclusive
                                                }
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'tax_inclusive',
                                                        checked === true,
                                                    )
                                                }
                                                className="mt-0.5 size-5"
                                            />
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="tax_inclusive"
                                                    className="cursor-pointer text-sm font-semibold"
                                                >
                                                    Harga menu sudah termasuk
                                                    pajak
                                                </Label>
                                                <p className="text-muted-foreground text-sm leading-5">
                                                    Nonaktifkan jika pajak ingin
                                                    ditambahkan setelah
                                                    subtotal.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>
                    )}

                    <div className="mt-8 flex items-center justify-between gap-3 border-t pt-5">
                        {step === 0 ? (
                            <span />
                        ) : (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={previous}
                            >
                                <ArrowLeft /> Kembali
                            </Button>
                        )}
                        {step < steps.length - 1 ? (
                            <Button type="button" onClick={next}>
                                Lanjut <ArrowRight />
                            </Button>
                        ) : (
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <Spinner /> : <Check />}
                                Selesaikan setup
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
