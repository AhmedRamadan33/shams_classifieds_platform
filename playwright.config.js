import { defineConfig } from '@playwright/test';
import { BASE_URL, PORT, appEnv, root } from './e2e/support/env.js';

// Browser tests against a throw-away database (shams_e2e) and upload folder, see e2e/support/env.js.
// Uses the Chrome installed on the machine, so no browser download is needed:  npm run e2e
export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.js',
    fullyParallel: false,
    workers: 1, // the PHP built-in server on Windows is single threaded
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    outputDir: 'test-results',
    use: {
        baseURL: BASE_URL,
        channel: 'chrome',
        locale: 'ar-EG',
        timezoneId: 'Africa/Cairo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'mobile-375', use: { viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 } },
        { name: 'desktop-1280', use: { viewport: { width: 1280, height: 800 } } },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${PORT} --no-reload`,
        cwd: root,
        env: appEnv,
        url: `${BASE_URL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
