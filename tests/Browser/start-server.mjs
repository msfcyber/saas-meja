import { createHash } from 'node:crypto';
import { createServer } from 'node:http';
import { mkdirSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn, spawnSync } from 'node:child_process';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const appPort = Number(process.env.E2E_PORT ?? 4173);
const appUrl = `http://127.0.0.1:${appPort}`;
const databasePath = join(
    root,
    'storage',
    'framework',
    'testing',
    'browser.sqlite',
);
const publicStoragePath = join(
    root,
    'storage',
    'framework',
    'testing',
    'browser-public',
);
const appKey =
    process.env.APP_KEY ??
    `base64:${Buffer.from('01234567890123456789012345678901').toString('base64')}`;
const midtransServerKey = 'e2e-midtrans-key';
const sessions = new Map();
let sessionNumber = 0;
let appProcess;
let shuttingDown = false;

function readBody(request) {
    return new Promise((resolveBody, reject) => {
        const chunks = [];

        request.on('data', (chunk) => chunks.push(chunk));
        request.on('end', () => resolveBody(Buffer.concat(chunks).toString()));
        request.on('error', reject);
    });
}

function sendJson(response, status, body) {
    response.writeHead(status, { 'content-type': 'application/json' });
    response.end(JSON.stringify(body));
}

async function handleMockRequest(request, response, mockPort) {
    const requestUrl = new URL(
        request.url ?? '/',
        `http://127.0.0.1:${mockPort}`,
    );

    if (
        request.method === 'POST' &&
        requestUrl.pathname === '/snap/v1/transactions'
    ) {
        const body = JSON.parse(await readBody(request));
        const details = body.transaction_details ?? {};
        const orderId = details.order_id;
        const grossAmount = String(details.gross_amount ?? '');
        const finishUrl = body.callbacks?.finish;

        if (
            typeof orderId !== 'string' ||
            !/^\d+$/.test(grossAmount) ||
            typeof finishUrl !== 'string'
        ) {
            sendJson(response, 422, { message: 'Invalid mock transaction.' });

            return;
        }

        const token = `e2e-snap-${++sessionNumber}`;
        sessions.set(token, { orderId, grossAmount, finishUrl });
        sendJson(response, 200, {
            token,
            redirect_url: `http://127.0.0.1:${mockPort}/payment/${token}`,
        });

        return;
    }

    if (
        request.method === 'GET' &&
        requestUrl.pathname.startsWith('/payment/')
    ) {
        const token = requestUrl.pathname.slice('/payment/'.length);
        const session = sessions.get(token);

        if (!session) {
            response.writeHead(404);
            response.end('Payment session not found.');

            return;
        }

        const grossAmount = `${session.grossAmount}.00`;
        const transactionTime = new Date().toISOString();
        const transactionStatus =
            requestUrl.searchParams.get('status') ?? 'settlement';
        const payload = {
            transaction_id: `e2e-transaction-${token}-${transactionStatus}`,
            transaction_status: transactionStatus,
            order_id: session.orderId,
            status_code: '200',
            gross_amount: grossAmount,
            transaction_time: transactionTime,
            settlement_time: transactionTime,
            payment_type: 'qris',
            signature_key: createHash('sha512')
                .update(
                    `${session.orderId}200${grossAmount}${midtransServerKey}`,
                )
                .digest('hex'),
        };

        if (transactionStatus === 'refund') {
            payload.refund_amount = grossAmount;
        }

        const webhook = await fetch(`${appUrl}/api/webhooks/midtrans`, {
            method: 'POST',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify(payload),
        });

        if (!webhook.ok) {
            const details = await webhook.text();
            response.writeHead(502, { 'content-type': 'text/plain' });
            response.end(`Mock webhook failed: ${details}`);

            return;
        }

        response.writeHead(302, { location: session.finishUrl });
        response.end();

        return;
    }

    response.writeHead(404);
    response.end();
}

async function start() {
    mkdirSync(dirname(databasePath), { recursive: true });
    rmSync(databasePath, { force: true });
    rmSync(publicStoragePath, { force: true, recursive: true });
    mkdirSync(publicStoragePath, { recursive: true });

    const mockServer = createServer((request, response) => {
        const address = mockServer.address();
        const mockPort =
            typeof address === 'object' && address ? address.port : 0;

        void handleMockRequest(request, response, mockPort).catch((error) => {
            response.writeHead(500, { 'content-type': 'text/plain' });
            response.end(
                error instanceof Error ? error.message : 'Mock error.',
            );
        });
    });

    await new Promise((resolveServer, reject) => {
        mockServer.once('error', reject);
        mockServer.listen(0, '127.0.0.1', resolveServer);
    });

    const mockAddress = mockServer.address();
    const mockPort =
        typeof mockAddress === 'object' && mockAddress ? mockAddress.port : 0;
    const env = {
        ...process.env,
        APP_ENV: 'testing',
        APP_DEBUG: 'true',
        APP_KEY: appKey,
        APP_NAME: 'Meja E2E',
        APP_URL: appUrl,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: databasePath,
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'file',
        QUEUE_CONNECTION: 'sync',
        BROADCAST_CONNECTION: 'log',
        FILESYSTEM_DISK: 'local',
        FILESYSTEM_PUBLIC_DRIVER: 'local',
        FILESYSTEM_PUBLIC_ROOT: publicStoragePath,
        FILESYSTEM_PUBLIC_URL: `${appUrl}/storage`,
        LOG_CHANNEL: 'stderr',
        MIDTRANS_SNAP_URL: `http://127.0.0.1:${mockPort}/snap/v1/transactions`,
        MIDTRANS_STATUS_URL: `http://127.0.0.1:${mockPort}/v2`,
        MIDTRANS_REFUND_URL: `http://127.0.0.1:${mockPort}/v2`,
        MIDTRANS_SERVER_KEY: midtransServerKey,
    };
    const clearConfig = spawnSync('php', ['artisan', 'config:clear'], {
        cwd: root,
        env,
        stdio: 'inherit',
    });

    if (clearConfig.status !== 0) {
        throw new Error('Unable to clear Laravel configuration.');
    }

    const migrate = spawnSync(
        'php',
        [
            'artisan',
            'migrate:fresh',
            '--seed',
            '--seeder=BrowserTestSeeder',
            '--force',
        ],
        { cwd: root, env, stdio: 'inherit' },
    );

    if (migrate.status !== 0) {
        throw new Error('Unable to prepare the browser test database.');
    }

    appProcess = spawn(
        'php',
        ['artisan', 'serve', '--host=127.0.0.1', `--port=${appPort}`],
        { cwd: root, env, stdio: 'inherit' },
    );
    appProcess.on('exit', (code) => {
        if (!shuttingDown && code !== 0) {
            void shutdown(code ?? 1, mockServer);
        }
    });

    const signalHandler = () => void shutdown(0, mockServer);
    process.on('SIGINT', signalHandler);
    process.on('SIGTERM', signalHandler);
    process.on('exit', () => {
        if (appProcess && !appProcess.killed) {
            appProcess.kill();
        }
    });
}

function shutdown(code, mockServer) {
    if (shuttingDown) {
        return Promise.resolve();
    }

    shuttingDown = true;

    if (appProcess && !appProcess.killed) {
        appProcess.kill();
    }

    return new Promise((resolveShutdown) => {
        mockServer.close(() => {
            resolveShutdown();
            process.exit(code);
        });
    });
}

start().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
