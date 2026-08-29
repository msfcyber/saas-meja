<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QR {{ $tableName }} - {{ $outletName }}</title>
        <style>
            :root { color-scheme: light; font-family: Arial, sans-serif; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f0e9; color: #273024; }
            main { width: min(90vw, 560px); box-sizing: border-box; padding: 48px; text-align: center; background: #fffdf8; border: 1px solid #ded8ca; border-radius: 28px; }
            h1 { margin: 0; font-size: 30px; }
            p { margin: 8px 0 0; color: #6b7168; }
            .qr { width: min(100%, 420px); margin: 32px auto 0; }
            .code { margin-top: 22px; font-weight: 700; letter-spacing: .08em; }
            @media print {
                body { background: #fff; }
                main { border: 0; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <main>
            <h1>{{ $tableName }}</h1>
            <p>{{ $outletName }}</p>
            <div class="qr">{!! $qrSvg !!}</div>
            <p class="code">{{ $tableCode }}</p>
        </main>
    </body>
</html>
