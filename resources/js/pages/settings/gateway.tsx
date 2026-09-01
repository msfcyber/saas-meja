import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, KeyRound, ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type GatewayState = {
    provider: 'midtrans';
    configured: boolean;
    version: number | null;
    configured_at: string | null;
};

type Props = {
    gateway: GatewayState;
};

function formatTimestamp(timestamp: string | null): string {
    if (timestamp === null) {
        return 'Not configured';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(timestamp));
}

export default function Gateway({ gateway }: Props) {
    return (
        <>
            <Head title="Gateway settings" />

            <h1 className="sr-only">Gateway settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Payment gateway"
                    description="Manage the tenant-owned credential used for order payments."
                />

                <Card className="overflow-hidden rounded-2xl">
                    <CardHeader className="bg-muted/25 border-b">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    <KeyRound
                                        className="text-primary size-4"
                                        aria-hidden="true"
                                    />
                                    Midtrans
                                </CardTitle>
                                <CardDescription>
                                    Order credentials are encrypted at rest and
                                    retained by version for safe rotation.
                                </CardDescription>
                            </div>
                            <span
                                className={
                                    gateway.configured
                                        ? 'inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300'
                                        : 'bg-muted text-muted-foreground inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold'
                                }
                            >
                                {gateway.configured && (
                                    <CheckCircle2
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                )}
                                {gateway.configured
                                    ? 'Configured'
                                    : 'Not configured'}
                            </span>
                        </div>
                    </CardHeader>

                    <CardContent className="grid gap-6 pt-6">
                        <dl className="grid gap-4 text-sm sm:grid-cols-3">
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">
                                    Provider
                                </dt>
                                <dd className="font-semibold uppercase">
                                    {gateway.provider}
                                </dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">
                                    Active version
                                </dt>
                                <dd className="font-semibold">
                                    {gateway.version === null
                                        ? 'Not configured'
                                        : `v${gateway.version}`}
                                </dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">
                                    Configured at
                                </dt>
                                <dd className="font-semibold">
                                    {formatTimestamp(gateway.configured_at)}
                                </dd>
                            </div>
                        </dl>

                        <div className="border-primary/15 bg-primary/5 flex items-start gap-3 rounded-xl border p-4 text-sm">
                            <ShieldCheck
                                className="text-primary mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <p className="text-muted-foreground">
                                Entering a new Server Key retires the previous
                                version. The stored secret is never shown again.
                            </p>
                        </div>

                        <Form
                            action="/settings/gateway"
                            method="post"
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-4 border-t pt-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="gateway-secret">
                                            New Midtrans Server Key
                                        </Label>
                                        <Input
                                            id="gateway-secret"
                                            name="server_key"
                                            type="password"
                                            autoComplete="new-password"
                                            placeholder="Paste the tenant Server Key"
                                            required
                                            aria-invalid={Boolean(
                                                errors.server_key,
                                            )}
                                            aria-describedby={
                                                errors.server_key
                                                    ? 'gateway-secret-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="gateway-secret-error"
                                            message={errors.server_key}
                                        />
                                    </div>

                                    <div className="flex items-center justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Rotating...'
                                                : 'Rotate credential'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Gateway.layout = {
    breadcrumbs: [
        {
            title: 'Gateway settings',
            href: '/settings/gateway',
        },
    ],
};
