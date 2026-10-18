import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const fixture = JSON.parse(await readFile(path.join(workspace, 'storage/app/private/phase3-browser-fixtures.json'), 'utf8'));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const outputDirectory = path.join(workspace, 'docs/browser');
await mkdir(outputDirectory, { recursive: true });
const baseURL = process.env.PHASE3_BROWSER_URL ?? fixture.url;
assert.equal(new URL(baseURL).hostname, '127.0.0.1', 'Browser QA is restricted to the isolated local server.');
const password = 'SpaceMatch-Browser-2026!';
const seededOtpCode = '613904';
const runId = Date.now().toString();
const propertyName = `Browser Workflow ${runId}`;
const spaceName = `Studio QA ${runId}`;
const results = [];
const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], failedResponses: [], layout: [] };
const screenshots = [];
const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.EDGE_EXECUTABLE ?? (process.platform === 'win32' ? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' : undefined),
});
let currentPage;
let propertyId;
let spaceEditPath;
let imageURL;

function observe(page) {
    page.on('console', message => { if (message.type() === 'error') diagnostics.consoleErrors.push({ url: page.url(), message: message.text() }); });
    page.on('pageerror', error => diagnostics.pageErrors.push({ url: page.url(), message: error.message }));
    page.on('requestfailed', request => {
        if (request.failure()?.errorText !== 'net::ERR_ABORTED') diagnostics.failedRequests.push({ url: request.url(), error: request.failure()?.errorText });
    });
    page.on('response', response => { if (response.status() >= 400) diagnostics.failedResponses.push({ url: response.url(), status: response.status() }); });
}

async function test(name, callback) {
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

async function open(page, pathname) {
    currentPage = page;
    const response = await page.goto(new URL(pathname, baseURL).href, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, `GET ${pathname}`);
}

async function submit(page, form, confirmation = false) {
    if (confirmation) {
        await form.locator('button[type="submit"]').first().click();
        await page.locator('#confirmation-dialog').waitFor({ state: 'visible' });
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.locator('[data-confirm-accept]').click(),
        ]);
    } else {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            form.locator('button[type="submit"]').first().click(),
        ]);
    }
    assert.equal(await page.locator('.alert-danger:visible').count(), 0, (await page.locator('.alert-danger:visible').allTextContents()).join('\n'));
}

async function login(page, account) {
    await open(page, '/login');
    await page.locator('[name="email"]').fill(fixture.accounts[account].email);
    await page.locator('[name="password"]').fill(password);
    await submit(page, page.locator('form[action$="/login"]'));
    assert.equal(new URL(page.url()).pathname, `/${account}/dashboard`);
}

async function capture(page, filename) {
    // Scroll through the page so lazy-loaded images are checked as well.
    await page.evaluate(async () => {
        document.documentElement.style.setProperty('scroll-behavior', 'auto', 'important');
        document.activeElement?.blur();
        document.querySelectorAll('img[loading="lazy"]').forEach(image => { image.loading = 'eager'; });
        for (let position = 0; position < document.body.scrollHeight; position += 700) {
            window.scrollTo(0, position);
            await new Promise(resolve => setTimeout(resolve, 25));
        }
        window.scrollTo(0, 0);
        await Promise.all(Array.from(document.images).map(image => image.complete ? null : new Promise(resolve => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
            setTimeout(resolve, 2000);
        })));
        window.scrollTo(0, 0);
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    });
    const layout = await page.evaluate(() => ({
        width: window.innerWidth,
        clientWidth: document.documentElement.clientWidth,
        documentWidth: document.documentElement.scrollWidth,
        brokenImages: Array.from(document.images).filter(image => image.complete && image.naturalWidth === 0).map(image => image.src),
    }));
    diagnostics.layout.push({ url: page.url(), filename, ...layout });
    assert.ok(layout.documentWidth <= layout.clientWidth + 2, `Horizontal overflow at ${page.url()}: ${layout.documentWidth}px > ${layout.clientWidth}px client width`);
    assert.deepEqual(layout.brokenImages, [], `Broken images at ${page.url()}`);
    await page.screenshot({ path: path.join(outputDirectory, filename), fullPage: true });
    await page.evaluate(() => document.documentElement.style.removeProperty('scroll-behavior'));
    screenshots.push(filename);
}

try {
    const ownerContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const ownerPage = await ownerContext.newPage();
    observe(ownerPage);
    ownerPage.setDefaultTimeout(60000);
    const visitorContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const visitorPage = await visitorContext.newPage();
    observe(visitorPage);

    await test('Registration does not offer unconfigured email or SMS delivery', async () => {
        await open(visitorPage, '/register');
        assert.equal(await visitorPage.locator('form[action$="/register"] button[type="submit"]').isDisabled(), true);
        assert.ok((await visitorPage.locator('main').innerText()).includes('Registration is temporarily unavailable'));
        assert.equal(await visitorPage.locator('[name="verification_method"][value="sms"]').isDisabled(), true);
    });

    await test('Pending renter verifies through the OTP page and can log in afterward', async () => {
        const otpContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
        const otpPage = await otpContext.newPage();
        observe(otpPage);
        await open(otpPage, '/login');
        await otpPage.locator('[name="email"]').fill(fixture.otp_account.email);
        await otpPage.locator('[name="password"]').fill(password);
        await submit(otpPage, otpPage.locator('form[action$="/login"]'));
        assert.equal(new URL(otpPage.url()).pathname, '/verify-contact');
        assert.ok(await otpPage.getByRole('heading', { name: 'Verify Your SpaceMatch Account' }).isVisible());
        assert.equal(await otpPage.locator('[name="code"]').getAttribute('inputmode'), 'numeric');
        assert.ok((await otpPage.locator('main').innerText()).includes('browser.example.test'));
        assert.ok(!(await otpPage.locator('main').innerText()).includes(fixture.otp_account.email));
        await capture(otpPage, 'otp-verification-mobile.png');

        await otpPage.locator('[name="code"]').fill(seededOtpCode);
        await submit(otpPage, otpPage.locator('form[action$="/verify-contact"]'));
        assert.equal(new URL(otpPage.url()).pathname, '/renter/dashboard');
        await otpPage.setViewportSize({ width: 1280, height: 900 });
        await otpPage.locator('.account-menu-toggle').click();
        assert.ok(await otpPage.locator('.account-menu .dropdown-menu').getByText('Account settings').isVisible());
        await Promise.all([
            otpPage.waitForURL(url => url.pathname === '/'),
            otpPage.locator('.account-menu form[action$="/logout"] button[type="submit"]').click(),
        ]);
        await open(otpPage, '/login');
        await otpPage.locator('[name="email"]').fill(fixture.otp_account.email);
        await otpPage.locator('[name="password"]').fill(password);
        await submit(otpPage, otpPage.locator('form[action$="/login"]'));
        assert.equal(new URL(otpPage.url()).pathname, '/renter/dashboard');
        await otpContext.close();
    });

    await test('Owner login and desktop dashboard', async () => {
        await login(ownerPage, 'owner');
        await capture(ownerPage, 'owner-dashboard-desktop.png');
    });

    await test('Owner creates a property through the form', async () => {
        await open(ownerPage, '/owner/properties/create');
        for (const [name, value] of Object.entries({ property_name: propertyName, description: 'A browser-tested studio property in Cebu.', address: '88 Browser Workflow Street', barangay: 'Lahug', city: 'Cebu City', province: 'Cebu', postal_code: '6000' })) {
            await ownerPage.locator(`form [name="${name}"]`).fill(value);
        }
        await ownerPage.locator('[name="property_type"]').selectOption('apartment');
        await submit(ownerPage, ownerPage.locator('form[action$="/owner/properties"]'));
        propertyId = Number(new URL(ownerPage.url()).pathname.match(/\/owner\/properties\/(\d+)$/)?.[1]);
        assert.ok(propertyId > 0, 'New property persisted and redirected to its detail page.');
        assert.ok((await ownerPage.locator('h1').innerText()).includes(propertyName));
    });

    await test('Owner previews and uploads two valid images', async () => {
        const picturePage = await browser.newPage({ viewport: { width: 640, height: 420 } });
        await picturePage.setContent('<html><body style="margin:0;background:#e7f2ef;font:24px Arial;color:#122644;display:grid;place-items:center;height:420px"><div style="padding:40px;border:3px solid #16887c;border-radius:20px;background:white">SpaceMatch<br><strong>Browser QA property photo</strong></div></body></html>');
        const image = await picturePage.screenshot({ type: 'png' });
        await picturePage.close();
        await ownerPage.locator('input[type="file"]').setInputFiles([
            { name: 'browser-photo-one.png', mimeType: 'image/png', buffer: image },
            { name: 'browser-photo-two.png', mimeType: 'image/png', buffer: image },
        ]);
        assert.equal(await ownerPage.locator('#image-previews img').count(), 2, 'Upload previews appear before submission.');
        await submit(ownerPage, ownerPage.locator(`form[action$="/owner/properties/${propertyId}/images"]`));
        assert.equal(await ownerPage.locator('.image-manager-thumb').count(), 2, 'Uploaded images are associated with the property.');
        imageURL = await ownerPage.locator('.image-manager-thumb').first().getAttribute('src');
        assert.equal((await ownerContext.request.get(imageURL)).status(), 200, 'Owner can read draft property images.');
        assert.equal((await visitorContext.request.get(imageURL)).status(), 404, 'Draft images are hidden from visitors.');
    });

    await test('Owner creates a rental space with amenities', async () => {
        await open(ownerPage, `/owner/spaces/create?property_id=${propertyId}`);
        await ownerPage.locator('[name="property_id"]').selectOption(String(propertyId));
        await ownerPage.locator('[name="space_name"]').fill(spaceName);
        await ownerPage.locator('[name="space_type"]').selectOption('studio_unit');
        await ownerPage.locator('textarea[name="description"]').fill('Bright studio verified by a real browser workflow.');
        await ownerPage.locator('[name="price"]').fill('8500');
        await ownerPage.locator('[name="capacity"]').fill('3');
        await ownerPage.locator('[name="floor_area"]').fill('28.5');
        await ownerPage.locator('[name="floor_number"]').fill('2');
        await ownerPage.locator(`[name="amenity_ids[]"][value="${fixture.wifi_id}"]`).check();
        await submit(ownerPage, ownerPage.locator('form[action$="/owner/spaces"]'));
        assert.ok((await ownerPage.locator('#spaces').innerText()).includes(spaceName));
        const editHref = await ownerPage.locator('#spaces a').filter({ hasText: 'Edit space' }).first().getAttribute('href');
        spaceEditPath = new URL(editHref, baseURL).pathname;
    });

    await test('Owner edits property information and image order/cover', async () => {
        await open(ownerPage, `/owner/properties/${propertyId}/edit`);
        await ownerPage.locator('textarea[name="description"]').fill('Property information updated and verified in the browser.');
        await submit(ownerPage, ownerPage.locator(`form[action$="/owner/properties/${propertyId}"]`));
        assert.ok((await ownerPage.locator('main').innerText()).includes('Property information updated and verified in the browser.'));
        const orderForm = ownerPage.locator('form').filter({ has: ownerPage.locator('[name="display_order"]') }).last();
        await orderForm.locator('[name="display_order"]').fill('0');
        await submit(ownerPage, orderForm);
        const coverForm = ownerPage.locator('form').filter({ has: ownerPage.locator('[name="is_primary"]') }).first();
        await submit(ownerPage, coverForm);
        assert.equal(await ownerPage.locator('#images .status-badge').filter({ hasText: 'Cover' }).count(), 1);
    });

    await test('Owner edits and publishes the rental space, then the property', async () => {
        const editURL = await ownerPage.locator('#spaces a').filter({ hasText: 'Edit space' }).first().getAttribute('href');
        await open(ownerPage, editURL);
        await ownerPage.locator('[name="price"]').fill('8750');
        await submit(ownerPage, ownerPage.locator('form').filter({ has: ownerPage.locator('[name="space_name"]') }));
        await submit(ownerPage, ownerPage.locator('form[action*="/owner/spaces/"][action$="/publish"]').first(), true);
        await submit(ownerPage, ownerPage.locator(`form[action$="/owner/properties/${propertyId}/publish"]`), true);
        assert.ok((await ownerPage.locator('.alert-success').allTextContents()).join(' ').includes('publication updated'));
        await capture(ownerPage, 'owner-property-desktop.png');
    });

    await test('Homepage search submits the supported location and property type filters', async () => {
        await open(visitorPage, '/');
        await visitorPage.locator('[name="location"]').fill('Cebu');
        await visitorPage.locator('[name="property_type"]').selectOption('apartment');
        await submit(visitorPage, visitorPage.locator('form.home-search'));
        assert.equal(new URL(visitorPage.url()).pathname, '/properties');
        assert.equal(new URL(visitorPage.url()).searchParams.get('location'), 'Cebu');
        assert.equal(new URL(visitorPage.url()).searchParams.get('property_type'), 'apartment');
        assert.ok((await visitorPage.locator('main').innerText()).includes(propertyName));
    });

    await test('Public search combines location, price, amenities, capacity, and sorting', async () => {
        await open(visitorPage, '/properties');
        await visitorPage.locator('[name="q"]').fill(propertyName);
        await visitorPage.locator('[name="location"]').fill('Cebu');
        await visitorPage.locator('[name="property_type"]').selectOption('apartment');
        await visitorPage.locator('[name="min_price"]').fill('8000');
        await visitorPage.locator('[name="max_price"]').fill('9000');
        await visitorPage.locator('[name="capacity"]').fill('3');
        await visitorPage.locator('details summary').click();
        await visitorPage.locator(`[name="amenities[]"][value="${fixture.wifi_id}"]`).check();
        await visitorPage.locator('[name="sort"]').selectOption('price_asc');
        await submit(visitorPage, visitorPage.locator('form[action$="/properties"]'));
        assert.equal(await visitorPage.locator('.card-listing').count(), 1);
        assert.ok((await visitorPage.locator('.card-listing').innerText()).includes(spaceName));
        await capture(visitorPage, 'public-search-desktop.png');
        for (const label of ['draft', 'archived']) {
            assert.equal((await visitorContext.request.get(`${baseURL}/properties/${fixture.properties[label].id}`)).status(), 404);
        }
    });

    await test('Public detail gallery, monthly rent, and tablet layout', async () => {
        await visitorPage.setViewportSize({ width: 768, height: 1024 });
        await open(visitorPage, `/properties/${propertyId}`);
        assert.ok((await visitorPage.locator('main').innerText()).includes('8,750'));
        assert.ok((await visitorPage.locator('main').innerText()).includes('Wi-Fi'));
        assert.equal((await visitorContext.request.get(imageURL)).status(), 200, 'Published property image is publicly accessible.');
        const thumbnails = visitorPage.locator('[data-gallery-target]');
        if (await thumbnails.count() > 1) await thumbnails.nth(1).click();
        await capture(visitorPage, 'public-detail-tablet.png');
    });

    await test('Mobile public navigation and property cards', async () => {
        await visitorPage.setViewportSize({ width: 390, height: 844 });
        await open(visitorPage, `/properties?q=${encodeURIComponent(propertyName)}`);
        await visitorPage.getByRole('button', { name: 'Toggle navigation' }).click();
        await visitorPage.locator('#siteNav').waitFor({ state: 'visible' });
        assert.ok(await visitorPage.locator('#siteNav').getByRole('link', { name: 'Browse rentals' }).isVisible());
        await capture(visitorPage, 'public-search-mobile.png');
    });

    await test('Mobile owner menu and cross-owner protection', async () => {
        await ownerPage.setViewportSize({ width: 390, height: 844 });
        await open(ownerPage, '/owner/dashboard');
        await ownerPage.getByRole('button', { name: 'Management menu' }).click();
        assert.ok(await ownerPage.locator('#managementNav').getByRole('link', { name: 'My properties' }).isVisible());
        await capture(ownerPage, 'owner-dashboard-mobile.png');
        assert.equal((await ownerContext.request.get(`${baseURL}/owner/properties/${fixture.properties.other_owner.id}/edit`)).status(), 403);
    });

    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const adminPage = await adminContext.newPage();
    observe(adminPage);
    await test('Administrator overview, owner filters, and property details', async () => {
        await login(adminPage, 'admin');
        await open(adminPage, `/admin/properties?q=${encodeURIComponent(propertyName)}&owner_id=${fixture.accounts.owner.id}&status=active`);
        assert.ok((await adminPage.locator('main').innerText()).includes(propertyName));
        await capture(adminPage, 'admin-properties-desktop.png');
        await open(adminPage, `/admin/properties/${propertyId}`);
        assert.ok((await adminPage.locator('main').innerText()).includes(fixture.accounts.owner.email));
    });

    await test('Administrator deactivation writes audit and hides listing/images', async () => {
        const form = adminPage.locator(`form[action$="/admin/properties/${propertyId}/status"]`);
        await form.locator('[name="status"]').selectOption('inactive');
        await form.locator('[name="reason"]').fill('Browser QA review: property information requires verification.');
        await submit(adminPage, form, Boolean(await form.getAttribute('data-confirm')));
        assert.ok((await adminPage.locator('main').innerText()).includes('Browser QA review: property information requires verification.'));
        await capture(adminPage, 'admin-property-audit-desktop.png');
        await open(visitorPage, `/properties?q=${encodeURIComponent(propertyName)}`);
        assert.equal(await visitorPage.locator('.card-listing').count(), 0);
        assert.equal((await visitorContext.request.get(`${baseURL}/properties/${propertyId}`)).status(), 404);
        assert.equal((await visitorContext.request.get(imageURL)).status(), 404);
    });

    const renterContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const renterPage = await renterContext.newPage();
    observe(renterPage);
    await test('Renter login, browsing, and owner/admin restrictions', async () => {
        await login(renterPage, 'renter');
        await open(renterPage, '/properties?location=Cebu');
        assert.ok(await renterPage.locator('.card-listing').count() >= 1);
        assert.equal((await renterContext.request.get(`${baseURL}/owner/properties/create`)).status(), 403);
        assert.equal((await renterContext.request.get(`${baseURL}/admin/properties`)).status(), 403);
        await capture(renterPage, 'renter-browse-mobile.png');
    });

    await test('Responsive page matrix at all requested viewport widths', async () => {
        const widths = [1920, 1440, 1280, 1024, 768, 430, 390, 375, 360, 320];
        const surfaces = [
            { name: 'Homepage', page: visitorPage, path: '/' },
            { name: 'Login', page: visitorPage, path: '/login' },
            { name: 'Registration', page: visitorPage, path: '/register' },
            { name: 'Forgot password', page: visitorPage, path: '/forgot-password' },
            { name: 'Reset password', page: visitorPage, path: '/reset-password/browser-fixture-token' },
            { name: 'Property search', page: visitorPage, path: '/properties' },
            { name: 'Property detail', page: visitorPage, path: `/properties/${fixture.properties.published.id}` },
            { name: 'Owner dashboard', page: ownerPage, path: '/owner/dashboard' },
            { name: 'Owner properties', page: ownerPage, path: '/owner/properties' },
            { name: 'Owner property detail', page: ownerPage, path: `/owner/properties/${propertyId}` },
            { name: 'Owner property create form', page: ownerPage, path: '/owner/properties/create' },
            { name: 'Owner property form', page: ownerPage, path: `/owner/properties/${propertyId}/edit` },
            { name: 'Rental spaces', page: ownerPage, path: '/owner/spaces' },
            { name: 'Rental space create form', page: ownerPage, path: `/owner/spaces/create?property_id=${propertyId}` },
            { name: 'Rental space edit form', page: ownerPage, path: spaceEditPath },
            { name: 'Owner profile', page: ownerPage, path: '/profile' },
            { name: 'Admin dashboard', page: adminPage, path: '/admin/dashboard' },
            { name: 'Admin properties', page: adminPage, path: '/admin/properties' },
            { name: 'Admin property detail', page: adminPage, path: `/admin/properties/${propertyId}` },
            { name: 'Renter dashboard', page: renterPage, path: '/renter/dashboard' },
            { name: 'Renter profile', page: renterPage, path: '/profile' },
            { name: 'Bookings', page: renterPage, path: '/bookings' },
            { name: 'Payments', page: renterPage, path: '/payments' },
            { name: 'Maintenance', page: renterPage, path: '/maintenance' },
            { name: 'Reviews', page: renterPage, path: '/reviews' },
        ];

        for (const surface of surfaces) {
            await open(surface.page, surface.path);
            for (const width of widths) {
                await surface.page.setViewportSize({ width, height: 960 });
                const layout = await surface.page.evaluate(() => ({
                    width: innerWidth,
                    clientWidth: document.documentElement.clientWidth,
                    documentWidth: document.documentElement.scrollWidth,
                }));
                diagnostics.layout.push({ surface: surface.name, url: surface.page.url(), ...layout });
                assert.ok(layout.documentWidth <= layout.clientWidth + 2, `${surface.name} overflows at ${width}px: ${layout.documentWidth}px > ${layout.clientWidth}px`);

                if (width === 1440 || width === 390) {
                    await capture(surface.page, `redesign-${surface.name.toLowerCase().replaceAll(' ', '-')}-${width}.png`);
                }

                if (surface.name === 'Homepage' && width === 1440) await capture(surface.page, 'stage2-home-desktop.png');
                if (surface.name === 'Homepage' && width === 390) await capture(surface.page, 'stage2-home-mobile.png');
                if (surface.name === 'Login' && width === 1440) await capture(surface.page, 'ultimate-login-desktop.png');
                if (surface.name === 'Registration' && width === 360) await capture(surface.page, 'stage2-registration-mobile.png');
                if (surface.name === 'Forgot password' && width === 1440) await capture(surface.page, 'ultimate-forgot-password-desktop.png');
                if (surface.name === 'Reset password' && width === 1440) await capture(surface.page, 'ultimate-reset-password-desktop.png');
                if (surface.name === 'Property detail' && width === 390) await capture(surface.page, 'stage2-property-detail-mobile.png');
                if (surface.name === 'Owner dashboard' && width === 390) await capture(surface.page, 'stage2-owner-dashboard-mobile.png');
                if (surface.name === 'Owner property detail' && width === 1440) await capture(surface.page, 'ultimate-owner-property-desktop.png');
                if (surface.name === 'Owner property create form' && width === 1440) await capture(surface.page, 'ultimate-owner-property-form-desktop.png');
                if (surface.name === 'Rental space edit form' && width === 1440) await capture(surface.page, 'ultimate-rental-space-form-desktop.png');
                if (surface.name === 'Owner profile' && width === 1440) await capture(surface.page, 'ultimate-owner-profile-desktop.png');
                if (surface.name === 'Admin dashboard' && width === 1440) await capture(surface.page, 'stage2-admin-dashboard-desktop.png');
                if (surface.name === 'Admin property detail' && width === 1440) await capture(surface.page, 'ultimate-admin-property-detail-desktop.png');
                if (surface.name === 'Renter dashboard' && width === 390) await capture(surface.page, 'stage2-renter-dashboard-mobile.png');
                if (surface.name === 'Renter profile' && width === 390) await capture(surface.page, 'ultimate-renter-profile-mobile.png');
                if (surface.name === 'Bookings' && width === 390) await capture(surface.page, 'ultimate-bookings-mobile.png');
                if (surface.name === 'Payments' && width === 390) await capture(surface.page, 'ultimate-payments-mobile.png');
                if (surface.name === 'Maintenance' && width === 390) await capture(surface.page, 'ultimate-maintenance-mobile.png');
                if (surface.name === 'Reviews' && width === 390) await capture(surface.page, 'ultimate-reviews-mobile.png');
            }
        }
    });

    await test('Console, requests, responses, images, and responsive overflow remain clean', async () => {
        assert.deepEqual(diagnostics.consoleErrors, []);
        assert.deepEqual(diagnostics.pageErrors, []);
        assert.deepEqual(diagnostics.failedRequests, []);
        assert.deepEqual(diagnostics.failedResponses, []);
    });
} catch (error) {
    process.exitCode = 1;
    process.stderr.write(`${error.stack}\n`);
    if (currentPage && ! currentPage.isClosed()) {
        await currentPage.screenshot({ path: path.join(outputDirectory, 'failure.png'), fullPage: true }).catch(() => {});
    }
} finally {
    await browser.close();
    const report = {
        executedAt: new Date().toISOString(), baseURL, database: fixture.database, imageRoot: fixture.image_root,
        runId, propertyId, results, diagnostics, screenshots,
        passed: results.filter(result => result.status === 'passed').length,
        failed: results.filter(result => result.status === 'failed').length,
    };
    await writeFile(path.join(outputDirectory, 'results.json'), `${JSON.stringify(report, null, 2)}\n`);
    await writeFile(path.join(outputDirectory, 'README.md'), [
        '# Phase 3 browser validation', '',
        `Executed: ${report.executedAt}`, `Server: ${baseURL}`, '',
        'These checks use dedicated browser fixture accounts, a separate SQLite database, and a separate image directory. Development MySQL accounts and uploads are untouched.', '',
        `Results: ${report.passed} passed; ${report.failed} failed.`, '',
        ...results.map(result => `- ${result.status === 'passed' ? 'PASS' : 'FAIL'}: ${result.name}${result.error ? ` — ${result.error}` : ''}`), '',
        'Screenshots:', '', ...screenshots.map(filename => `- [${filename}](${filename})`), '',
        'Machine-readable diagnostics, image checks, and viewport widths are recorded in [results.json](results.json).', '',
        'To repeat: run `php scripts/phase3-browser-fixtures.php`, start the app on port 8013 with the same testing SQLite and PROPERTY_IMAGE_ROOT environment variables, then run `node scripts/phase3-browser.mjs`. Edge is used on Windows; EDGE_EXECUTABLE and PLAYWRIGHT_MODULE can override the local browser/tool paths.', '',
    ].join('\n'));
    process.stdout.write(`Browser results: ${report.passed} passed, ${report.failed} failed. See docs/browser/results.json.\n`);
}
