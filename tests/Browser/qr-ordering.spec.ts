import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

const qrToken = 'a'.repeat(64);
const productName = 'Nasi Bakar E2E';

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
        const target = window as Window & {
            __mejaWebVitals?: { cls: number; lcp: number | null };
        };
        const metrics = { cls: 0, lcp: null as number | null };
        target.__mejaWebVitals = metrics;

        if (typeof PerformanceObserver === 'undefined') {
            return;
        }

        try {
            new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];

                if (lastEntry) {
                    metrics.lcp = lastEntry.startTime;
                }
            }).observe({ type: 'largest-contentful-paint', buffered: true });
        } catch {
            // The browser may not expose LCP in every execution environment.
        }

        try {
            new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    const layoutShift = entry as PerformanceEntry & {
                        hadRecentInput?: boolean;
                        value?: number;
                    };

                    if (!layoutShift.hadRecentInput) {
                        metrics.cls += layoutShift.value ?? 0;
                    }
                }
            }).observe({ type: 'layout-shift', buffered: true });
        } catch {
            // The browser may not expose CLS in every execution environment.
        }
    });
});

async function expectNoAccessibilityViolations(page: Page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    expect(
        results.violations,
        results.violations
            .map(
                (violation) =>
                    `${violation.id}: ${violation.nodes.map((node) => node.html).join(', ')}`,
            )
            .join('\n'),
    ).toEqual([]);
}

async function expectNoHorizontalOverflow(page: Page) {
    await expect
        .poll(() =>
            page.evaluate(
                () =>
                    document.documentElement.scrollWidth <=
                    document.documentElement.clientWidth + 1,
            ),
        )
        .toBe(true);
}

async function expectPerformanceBudget(page: Page, testInfo: TestInfo) {
    const metrics = await page.evaluate(() => {
        const navigation = performance.getEntriesByType('navigation')[0] as
            | PerformanceNavigationTiming
            | undefined;
        const vitals = (
            window as Window & {
                __mejaWebVitals?: { cls: number; lcp: number | null };
            }
        ).__mejaWebVitals;

        return {
            dom_content_loaded_ms: navigation?.domContentLoadedEventEnd ?? 0,
            load_event_ms: navigation?.loadEventEnd ?? 0,
            lcp_ms: vitals?.lcp ?? null,
            cls: vitals?.cls ?? 0,
        };
    });

    await testInfo.attach('web-vitals.json', {
        body: JSON.stringify(metrics, null, 2),
        contentType: 'application/json',
    });
    expect(metrics.dom_content_loaded_ms).toBeGreaterThan(0);
    expect(metrics.dom_content_loaded_ms).toBeLessThan(2500);
    expect(metrics.load_event_ms).toBeLessThan(5000);
    expect(metrics.cls).toBeLessThan(0.1);

    if (metrics.lcp_ms !== null) {
        expect(metrics.lcp_ms).toBeLessThan(2500);
    }
}

test('scan QR, complete checkout, track payment, and open receipt', async ({
    page,
}, testInfo) => {
    await page.goto(`/q/${qrToken}`, { waitUntil: 'domcontentloaded' });
    await expect(
        page.getByRole('heading', { name: /Selamat datang di Kedai E2E/ }),
    ).toBeVisible();
    // Exclude one-time Laravel process boot from the page performance budget.
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(
        page.getByRole('heading', { name: /Selamat datang di Kedai E2E/ }),
    ).toBeVisible();
    await expect(page.getByText('Meja E2E', { exact: true })).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await expectPerformanceBudget(page, testInfo);
    await expectNoAccessibilityViolations(page);

    await page
        .getByRole('region', { name: 'Pilih hidangan favorit' })
        .getByRole('button', { name: `Lihat detail ${productName}` })
        .click();
    const productDialog = page.getByRole('dialog');
    await expect(productDialog).toBeVisible();
    await expect(
        productDialog.getByRole('heading', { name: productName }),
    ).toBeVisible();
    await productDialog.getByRole('button', { name: /Tambahkan/ }).click();

    const cartLink = page.getByRole('link', { name: /Lihat pesanan/ });
    await expect(cartLink).toContainText(/Rp\s*28\.000/);
    await cartLink.click();
    await expect(page).toHaveURL(/\/q\/[a-f0-9]{64}\/checkout$/);
    await expect(
        page.getByRole('heading', { name: 'Periksa pesananmu.' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await expectNoAccessibilityViolations(page);

    await page
        .getByRole('textbox', { name: /Nama pemesan/ })
        .fill('Penguji E2E');
    await page.getByRole('button', { name: 'Lanjutkan pembayaran' }).click();
    await expect(page).toHaveURL(/\/o\/[a-f0-9]{64}$/);
    await expect(
        page.getByRole('heading', { name: 'Pesananmu sudah diterima.' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await expectNoAccessibilityViolations(page);

    const receiptLink = page.getByRole('link', {
        name: 'Lihat struk digital',
    });
    await expect(receiptLink).toHaveAttribute(
        'href',
        /\/o\/[a-f0-9]{64}\/receipt$/,
    );
    const receiptPopup = page.waitForEvent('popup');
    await receiptLink.click();
    const receipt = await receiptPopup;
    await receipt.waitForLoadState('domcontentloaded');
    await expect(
        receipt.getByRole('heading', { name: 'Kedai E2E' }),
    ).toBeVisible();
    await expect(receipt.getByText(productName)).toBeVisible();
    await expect(receipt.getByText('Lunas')).toBeVisible();
    await expect(receipt.getByText('IDR 28.000')).toHaveCount(3);
    await expectNoHorizontalOverflow(receipt);
    await expectNoAccessibilityViolations(receipt);
    await receipt.close();
});

test('checkout keeps a clear offline error and does not submit', async ({
    page,
    context,
}) => {
    await page.goto(`/q/${qrToken}`, { waitUntil: 'domcontentloaded' });
    await page
        .getByRole('region', { name: 'Pilih hidangan favorit' })
        .getByRole('button', { name: `Lihat detail ${productName}` })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /Tambahkan/ })
        .click();
    await page.getByRole('link', { name: /Lihat pesanan/ }).click();
    await expect(
        page.getByRole('heading', { name: 'Periksa pesananmu.' }),
    ).toBeVisible();

    await context.setOffline(true);
    await page.getByRole('button', { name: 'Lanjutkan pembayaran' }).click();
    await expect(page.locator('#checkout-error')).toContainText(
        'Tidak ada koneksi internet. Periksa jaringanmu lalu coba lagi.',
    );
    await expect(page).toHaveURL(/\/q\/[a-f0-9]{64}\/checkout$/);
    await expectNoHorizontalOverflow(page);
    await context.setOffline(false);
});

test('checkout exposes its loading state while the network is slow', async ({
    page,
}) => {
    await page.goto(`/q/${qrToken}`, { waitUntil: 'domcontentloaded' });
    await page
        .getByRole('region', { name: 'Pilih hidangan favorit' })
        .getByRole('button', { name: `Lihat detail ${productName}` })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /Tambahkan/ })
        .click();
    await page.getByRole('link', { name: /Lihat pesanan/ }).click();
    await expect(
        page.getByRole('heading', { name: 'Periksa pesananmu.' }),
    ).toBeVisible();

    let releaseRequest: () => void = () => {};
    const requestPaused = new Promise<void>((resolve) => {
        void page.route('**/api/public/orders', async (route) => {
            resolve();
            await new Promise<void>((release) => {
                releaseRequest = release;
            });
            await route.abort();
        });
    });

    await page.getByRole('button', { name: 'Lanjutkan pembayaran' }).click();
    await requestPaused;
    await expect(
        page.getByRole('button', { name: 'Memproses...' }),
    ).toBeVisible();
    releaseRequest();
    await expect(page.locator('#checkout-error')).toBeVisible();
    await expect(page).toHaveURL(/\/q\/[a-f0-9]{64}\/checkout$/);
    await page.unroute('**/api/public/orders');
});

test('invalid QR and empty search states remain actionable', async ({
    page,
}) => {
    await page.goto(`/q/${'b'.repeat(64)}`, { waitUntil: 'domcontentloaded' });
    await expect(
        page.getByRole('heading', { name: 'QR tidak tersedia' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Kembali ke beranda' }),
    ).toHaveAttribute('href', '/');
    await expectNoHorizontalOverflow(page);
    await expectNoAccessibilityViolations(page);

    await page.goto(`/q/${qrToken}`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('textbox', { name: 'Cari menu' }).fill('tidak ada');
    await expect(page.getByText('Menu tidak ditemukan')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Tampilkan semua menu' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await expectNoAccessibilityViolations(page);
});
