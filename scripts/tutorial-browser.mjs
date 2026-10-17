import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const privateDirectory = path.join(workspace, 'storage/app/private');
const fixture = JSON.parse(await readFile(path.join(privateDirectory, 'phase3-browser-fixtures.json'), 'utf8'));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const outputDirectory = path.join(workspace, 'docs/browser');
const baseURL = process.env.TUTORIAL_BROWSER_URL ?? fixture.url;
assert.equal(new URL(baseURL).hostname, '127.0.0.1', 'Tutorial browser checks are restricted to the isolated local server.');

await mkdir(outputDirectory, { recursive: true });
const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.EDGE_EXECUTABLE ?? (process.platform === 'win32' ? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' : undefined),
});
const results = [];
const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], failedResponses: [], layouts: [] };
const pages = [];

function observe(page) {
    pages.push(page);
    page.on('console', message => {
        if (message.type() === 'error') diagnostics.consoleErrors.push({ url: page.url(), message: message.text() });
    });
    page.on('pageerror', error => diagnostics.pageErrors.push({ url: page.url(), message: error.message }));
    page.on('requestfailed', request => {
        if (request.failure()?.errorText !== 'net::ERR_ABORTED') diagnostics.failedRequests.push({ url: request.url(), error: request.failure()?.errorText });
    });
    page.on('response', response => {
        if (response.status() >= 400) diagnostics.failedResponses.push({ url: response.url(), status: response.status() });
    });
}

async function check(name, callback) {
    try {
        await callback();
        results.push({ name, status: 'passed' });
        process.stdout.write(`PASS: ${name}\n`);
    } catch (error) {
        results.push({ name, status: 'failed', error: error.message });
        process.stdout.write(`FAIL: ${name}: ${error.message}\n`);
        throw error;
    }
}

async function openWelcome(page, pathname = '/') {
    await page.goto(new URL(pathname, baseURL).href, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => document.querySelector('[data-tutorial-dialog]')?.open === true);
}

async function isOpen(page) {
    return page.locator('[data-tutorial-dialog]').evaluate(dialog => dialog.open);
}

async function login(page, role) {
    await openWelcome(page, '/login');
    await page.getByRole('button', { name: 'Skip for Now' }).click();
    await page.locator('[name="email"]').fill(fixture.accounts[role].email);
    await page.locator('[name="password"]').fill('SpaceMatch-Browser-2026!');
    await Promise.all([
        page.waitForURL(`**/${role}/dashboard`),
        page.locator('form[action$="/login"] button[type="submit"]').click(),
    ]);
}

try {
    const firstContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const firstPage = await firstContext.newPage();
    observe(firstPage);

    await check('First visit opens the honest text-tour welcome', async () => {
        await openWelcome(firstPage);
        assert.equal(await firstPage.getByRole('heading', { name: 'Welcome to SpaceMatch!' }).count(), 1);
        assert.equal(await firstPage.locator('video, audio').count(), 0, 'No nonexistent media is presented as available.');
        assert.ok(await firstPage.getByText('The narrated video is not available yet.').isVisible());
        await firstPage.screenshot({ path: path.join(outputDirectory, 'tutorial-first-visit.png') });
    });

    await check('Skip closes the welcome and suppresses it across navigation', async () => {
        await firstPage.getByRole('button', { name: 'Skip for Now' }).click();
        assert.equal(await isOpen(firstPage), false);
        await firstPage.goto(new URL('/properties', baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(await isOpen(firstPage), false);
    });

    await check('Replay opens chapters and navigation controls work', async () => {
        await firstPage.getByRole('button', { name: 'Watch tutorial' }).click();
        assert.equal(await isOpen(firstPage), true);
        assert.equal(await firstPage.locator('[data-tutorial-panel]').count(), 3);
        await firstPage.locator('[data-tutorial-select="1"]').click();
        assert.ok(await firstPage.getByRole('heading', { name: 'Compare the details' }).isVisible());
        await firstPage.locator('[data-tutorial-next]').click();
        assert.ok(await firstPage.getByRole('heading', { name: 'Choose how you use SpaceMatch' }).isVisible());
        assert.ok(await firstPage.locator('[data-tutorial-progress]').innerText().then(text => text.includes('3 of 3')));
        await firstPage.locator('[data-tutorial-finish]').click();
        assert.equal(await isOpen(firstPage), false);
    });

    await check('Following a tour link does not reopen the welcome on the destination', async () => {
        const linkContext = await browser.newContext({ viewport: { width: 1280, height: 850 } });
        const linkPage = await linkContext.newPage();
        observe(linkPage);
        await openWelcome(linkPage);
        await linkPage.getByRole('button', { name: 'Watch Tutorial', exact: true }).click();
        await linkPage.getByRole('link', { name: 'Browse available spaces' }).click();
        await linkPage.waitForLoadState('networkidle');
        assert.equal(await isOpen(linkPage), false);
        await linkContext.close();
    });

    await check('Mobile dialog fits and Escape closes it accessibly', async () => {
        await firstPage.setViewportSize({ width: 360, height: 740 });
        await firstPage.getByRole('button', { name: 'Toggle navigation' }).click();
        await firstPage.getByRole('button', { name: 'Watch tutorial' }).click();
        const layout = await firstPage.evaluate(() => ({
            width: innerWidth,
            documentWidth: document.documentElement.scrollWidth,
            dialog: document.querySelector('[data-tutorial-dialog]').getBoundingClientRect().toJSON(),
            footer: document.querySelector('.tutorial-tour-footer').getBoundingClientRect().toJSON(),
            footerControlsVisible: [...document.querySelector('.tutorial-tour-footer').querySelectorAll('button')]
                .filter(button => !button.hidden)
                .every(button => {
                    const rect = button.getBoundingClientRect();
                    const dialogRect = document.querySelector('[data-tutorial-dialog]').getBoundingClientRect();
                    return rect.top >= dialogRect.top && rect.bottom <= dialogRect.bottom;
                }),
        }));
        diagnostics.layouts.push(layout);
        assert.ok(layout.documentWidth <= layout.width + 1);
        assert.ok(layout.dialog.width <= layout.width);
        assert.equal(layout.footerControlsVisible, true, 'Tour controls remain inside the visible dialog bounds.');
        await firstPage.screenshot({ path: path.join(outputDirectory, 'tutorial-tour-mobile.png') });
        await firstPage.keyboard.press('Escape');
        assert.equal(await isOpen(firstPage), false);
        assert.equal(await firstPage.evaluate(() => document.activeElement.matches('[data-tutorial-replay]')), true);
    });

    const permanentContext = await browser.newContext({ viewport: { width: 1280, height: 850 } });
    const permanentPage = await permanentContext.newPage();
    observe(permanentPage);
    await check('Permanent opt-out survives page changes while replay remains available', async () => {
        await openWelcome(permanentPage);
        await permanentPage.getByRole('button', { name: "Don't Show Again" }).click();
        assert.equal(await isOpen(permanentPage), false);
        const returningPage = await permanentContext.newPage();
        observe(returningPage);
        await returningPage.goto(new URL('/login', baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(await isOpen(returningPage), false);
        await returningPage.getByRole('button', { name: 'Watch tutorial' }).click();
        assert.equal(await isOpen(returningPage), true);
        await returningPage.locator('[data-tutorial-close]').click();
        await permanentContext.close();
    });

    const blockedContext = await browser.newContext({ viewport: { width: 1280, height: 850 } });
    await blockedContext.addInitScript(() => {
        for (const storage of ['localStorage', 'sessionStorage']) {
            Object.defineProperty(window, storage, { configurable: true, get() { throw new Error('Storage disabled for test.'); } });
        }
    });
    const blockedPage = await blockedContext.newPage();
    observe(blockedPage);
    await check('Storage failures fall back without blocking navigation', async () => {
        await openWelcome(blockedPage);
        await blockedPage.getByRole('button', { name: 'Skip for Now' }).click();
        await blockedPage.goto(new URL('/properties', baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(await isOpen(blockedPage), false);
        await blockedContext.close();
    });

    for (const role of ['owner', 'renter', 'admin']) {
        const roleContext = await browser.newContext({ viewport: { width: 1280, height: 850 } });
        const rolePage = await roleContext.newPage();
        observe(rolePage);
        await check(`${role} sees only role-appropriate tutorial chapters`, async () => {
            await login(rolePage, role);
            await rolePage.getByRole('button', { name: 'Watch tutorial' }).click();
            const chapterCount = role === 'renter' ? 3 : 4;
            assert.equal(await rolePage.locator('[data-tutorial-panel]').count(), chapterCount);
            assert.equal(await rolePage.getByRole('button', { name: 'Manage a listing' }).count(), role === 'owner' ? 1 : 0);
            assert.equal(await rolePage.getByRole('button', { name: 'Review listings' }).count(), role === 'admin' ? 1 : 0);
            await rolePage.locator('[data-tutorial-close]').click();
            await roleContext.close();
        });
    }

    await check('No browser console, runtime, or failed-request errors', async () => {
        assert.deepEqual(diagnostics.consoleErrors, []);
        assert.deepEqual(diagnostics.pageErrors, []);
        assert.deepEqual(diagnostics.failedRequests, []);
        assert.deepEqual(diagnostics.failedResponses, []);
    });
} catch (error) {
    process.exitCode = 1;
    process.stderr.write(`${error.stack}\n`);
} finally {
    await browser.close();
    const report = {
        executedAt: new Date().toISOString(),
        baseURL,
        results,
        diagnostics,
        passed: results.filter(result => result.status === 'passed').length,
        failed: results.filter(result => result.status === 'failed').length,
    };
    await writeFile(path.join(outputDirectory, 'tutorial-results.json'), `${JSON.stringify(report, null, 2)}\n`);
    process.stdout.write(`Tutorial browser results: ${report.passed} passed, ${report.failed} failed.\n`);
}