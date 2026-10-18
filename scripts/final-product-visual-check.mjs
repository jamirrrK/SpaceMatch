import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';

const base = process.env.SPACEMATCH_PREVIEW_URL ?? 'http://127.0.0.1:8014';
assert.match(base, /^http:\/\/127\.0\.0\.1:\d{4,5}$/);
const output = 'docs/final-product-browser/visual';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.platform === 'win32' ? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' : undefined });
const errors = [], failures = [], results = [];
try {
    const context = await browser.newContext();
    context.setDefaultNavigationTimeout(120000);
    context.setDefaultTimeout(60000);
    await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 400 && !response.url().endsWith('/missing-preview-page')) failures.push(response.url()); });
    async function inspect(uri, label) {
        console.log(`RUN ${label}`);
        const response = await page.goto(base + uri, { waitUntil: 'networkidle' });
        assert.equal(response.status(), uri === '/missing-preview-page' ? 404 : 200);
        const dialog = page.locator('[data-tutorial-dialog]');
        if (await dialog.count() && await dialog.evaluate(node => node.open)) await page.locator('[data-tutorial-skip]').first().click();
        for (const image of await page.locator('img').all()) {
            if (!(await image.isVisible())) continue;
            await image.scrollIntoViewIfNeeded();
            await image.evaluate(node => node.decode());
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, label);
        await page.evaluate(() => scrollTo(0, 0));
        await page.screenshot({ path: `${output}/${label}.png`, fullPage: true });
        results.push(label);
    }
    for (const width of [375, 768, 1366, 1920]) {
        await page.setViewportSize({ width, height: 900 });
        await inspect('/', `home-${width}`);
        await inspect('/login', `login-${width}`);
        await inspect('/properties', `search-${width}`);
    }
    await inspect('/missing-preview-page', 'error-404');
    for (const [account, target] of [['student', '/renter/dashboard'], ['other', '/renter/dashboard'], ['owner', '/owner/dashboard'], ['admin', '/admin/dashboard'], ['master-admin', '/admin/dashboard']]) {
        await page.setViewportSize({ width: 1366, height: 900 });
        await inspect('/login', `before-${account}`);
        await page.locator('[name=email]').fill(`${account}@student-browser.example.test`);
        await page.locator('[name=password]').fill('Browser-Student-2026!');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('form[action$="/login"] button[type=submit]').click()]);
        await inspect(target, `dashboard-${account}`);
        await page.setViewportSize({ width: 375, height: 812 });
        await inspect(target, `dashboard-${account}-mobile`);
        await page.context().clearCookies();
    }
    // A 404 produces one expected console network diagnostic in Chromium.
    assert.deepEqual(errors.filter(message => !message.includes('404 (Not Found)')), []);
    assert.deepEqual(failures, []);
    console.log(`PASS ${results.length} visual checks; images decoded, no overflow, no unexpected console or HTTP failures.`);
} finally {
    await writeFile(`${output}/results.json`, JSON.stringify({ results, errors, failures }, null, 2));
    await browser.close();
}
