<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Struk {{ $order->order_number }} - {{ $order->outlet?->name }}</title>
        <style>
            :root { color-scheme: light; font-family: Arial, sans-serif; }
            body { margin: 0; background: #f3f0e9; color: #273024; }
            main { width: min(100% - 32px, 520px); box-sizing: border-box; margin: 32px auto; padding: 32px; background: #fffdf8; border: 1px solid #ded8ca; border-radius: 24px; }
            h1, h2, p { margin: 0; }
            h1 { font-size: 26px; }
            h2 { font-size: 15px; }
            p, dt, dd { font-size: 12px; line-height: 1.5; }
            .muted { color: #6b7168; }
            header { padding-bottom: 24px; text-align: center; border-bottom: 1px dashed #cfc8ba; }
            header p { margin-top: 5px; }
            .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 20px 0; }
            .meta div:last-child { text-align: right; }
            .label { display: block; margin-bottom: 3px; color: #6b7168; font-size: 11px; }
            .items { padding: 18px 0; border-top: 1px solid #ece7dc; border-bottom: 1px solid #ece7dc; }
            .item { display: grid; grid-template-columns: 1fr auto; gap: 12px; padding: 10px 0; }
            .item:first-child { padding-top: 0; }
            .item:last-child { padding-bottom: 0; }
            .item-name { font-weight: 700; }
            .item-detail { margin-top: 3px; color: #6b7168; font-size: 11px; }
            .item-price { white-space: nowrap; font-weight: 700; }
            dl { margin: 0; padding: 18px 0 0; }
            dl div { display: flex; justify-content: space-between; gap: 16px; padding: 4px 0; }
            dd { margin: 0; text-align: right; }
            .total { margin-top: 10px; padding-top: 14px; border-top: 1px solid #273024; font-size: 16px; font-weight: 700; }
            .total dt, .total dd { font-size: 16px; font-weight: 700; }
            .payment { margin-top: 22px; padding: 12px 14px; border-radius: 12px; background: #edf2e8; color: #35513b; }
            footer { padding-top: 24px; text-align: center; }
            @media print {
                body { background: #fff; }
                main { width: 100%; margin: 0; border: 0; border-radius: 0; }
            }
        </style>
    </head>
    <body onload="window.print()">
        @php
            $money = fn (int $amount): string => $order->currency.' '.number_format($amount, 0, ',', '.');
            $paymentLabels = ['qris' => 'QRIS', 'ewallet' => 'E-wallet', 'va' => 'Virtual account'];
        @endphp
        <main>
            <header>
                <h1>{{ $order->outlet?->name }}</h1>
                @if ($order->outlet?->address)
                    <p class="muted">{{ $order->outlet->address }}</p>
                @endif
                @if ($order->outlet?->phone)
                    <p class="muted">{{ $order->outlet->phone }}</p>
                @endif
            </header>

            <section class="meta" aria-label="Informasi order">
                <div>
                    <span class="label">Nomor order</span>
                    <strong>#{{ $order->order_number }}</strong>
                </div>
                <div>
                    <span class="label">Meja</span>
                    <strong>{{ $order->table?->name ?? '-' }}</strong>
                </div>
                <div>
                    <span class="label">Waktu order</span>
                    <span>{{ $order->created_at?->format('d M Y, H:i') }}</span>
                </div>
                @if ($order->customer_name)
                    <div>
                        <span class="label">Nama</span>
                        <span>{{ $order->customer_name }}</span>
                    </div>
                @endif
            </section>

            <section class="items" aria-label="Item pesanan">
                @foreach ($order->items as $item)
                    <div class="item">
                        <div>
                            <div class="item-name">{{ $item->quantity }}x {{ $item->product_name_snapshot }}</div>
                            @if ($item->variant_name_snapshot)
                                <div class="item-detail">{{ $item->variant_name_snapshot }}</div>
                            @endif
                            @foreach ($item->modifiers as $modifier)
                                <div class="item-detail">{{ $modifier->modifier_name_snapshot }}: {{ $modifier->option_name_snapshot }}</div>
                            @endforeach
                            @if ($item->note)
                                <div class="item-detail">Catatan: {{ $item->note }}</div>
                            @endif
                        </div>
                        <div class="item-price">{{ $money((int) $item->line_total) }}</div>
                    </div>
                @endforeach
            </section>

            <dl>
                <div><dt class="muted">Subtotal</dt><dd>{{ $money((int) $order->subtotal) }}</dd></div>
                @if ($order->discount_amount > 0)
                    <div><dt class="muted">Diskon</dt><dd>-{{ $money((int) $order->discount_amount) }}</dd></div>
                @endif
                @if ($order->tax_amount > 0)
                    <div><dt class="muted">{{ $order->tax_name_snapshot ?? 'Pajak' }}</dt><dd>{{ $money((int) $order->tax_amount) }}</dd></div>
                @endif
                @if ($order->fee_amount > 0)
                    <div><dt class="muted">Biaya</dt><dd>{{ $money((int) $order->fee_amount) }}</dd></div>
                @endif
                <div class="total"><dt>Total</dt><dd>{{ $money((int) $order->grand_total) }}</dd></div>
            </dl>

            <div class="payment">
                Pembayaran {{ $paymentLabels[$payment->method] ?? $payment->method }} · {{ $payment->status->value === 'paid' ? 'Lunas' : ucfirst(str_replace('_', ' ', $payment->status->value)) }}
            </div>

            <footer>
                <p class="muted">Terima kasih sudah memesan.</p>
            </footer>
        </main>
    </body>
</html>
