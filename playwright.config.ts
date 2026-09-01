import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 4173);
const baseURL = `http://127.0.0.1:${port}`;

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? 'line' : 'list',
    use: {
        baseURL,
        locale: 'id-ID',
        colorScheme: 'light',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'desktop',
            use: {
                ...devices['Desktop Chrome'],
                browserName: 'chromium',
                viewport: { width: 1440, height: 900 },
            },
        },
        {
            name: 'tablet',
            use: {
                ...devices['iPad (gen 9)'],
                browserName: 'chromium',
                viewport: { width: 1024, height: 768 },
            },
        },
        {
            name: 'mobile-360',
            use: {
                ...devices['iPhone 13'],
                browserName: 'chromium',
                viewport: { width: 360, height: 800 },
            },
        },
    ],
    webServer: {
        command: 'node tests/Browser/start-server.mjs',
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
