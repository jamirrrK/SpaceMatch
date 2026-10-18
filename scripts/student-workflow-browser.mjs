import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const privateRoot = path.join(workspace, 'storage/app/private');
await mkdir(privateRoot, { recursive: true });
const runRoot = await mkdtemp(path.join(privateRoot, 'student-workflow-browser-'));
await writeFile(path.join(runRoot, '.isolated-student-browser'), 'isolated browser fixtures only');
await writeFile(path.join(runRoot, 'clock.txt'), '2026-10-18 12:00:00');
const env = { ...process.env, SPACEMATCH_STUDENT_BROWSER_ROOT: runRoot, SPACEMATCH_STUDENT_BROWSER_KEY: `base64:${randomBytes(32).toString('base64')}` };
const output = process.env.SPACEMATCH_STUDENT_BROWSER_OUTPUT
    ? path.resolve(process.env.SPACEMATCH_STUDENT_BROWSER_OUTPUT)
    : path.join(workspace, 'docs/student-browser');
await mkdir(output, { recursive: true });
const results = [], diagnostics = { pageErrors: [], consoleErrors: [], failedResponses: [], blockedExternalRequests: [], layout: [] };
const base = 'http://127.0.0.1:8027';
const php = process.env.PHP_BINARY ?? 'php';
function run(command, args) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, { cwd: workspace, env, windowsHide: true });
        let text = '';
        child.stdout.on('data', chunk => { text += chunk; });
        child.stderr.on('data', chunk => { text += chunk; });
        child.on('error', reject);
        child.on('exit', code => code === 0 ? resolve(text) : reject(new Error(text)));
    });
}
await run(php, ['scripts/student-browser-fixtures.php']);
const fixtures = JSON.parse(await readFile(path.join(runRoot, 'fixtures.json'), 'utf8'));
const server = spawn(php, ['-S', '127.0.0.1:8027', 'scripts/student-browser-router.php'], { cwd: workspace, env, windowsHide: true });
let serverLog = '';
server.stdout.on('data', b => { serverLog += b; });
server.stderr.on('data', b => { serverLog += b; });
let browser;
async function check(name, callback) { process.stdout.write(`RUN ${name}\n`); await callback(); results.push({ name, status: 'passed' }); process.stdout.write(`PASS ${name}\n`); }
async function dismiss(page) {
    const dialog = page.locator('[data-tutorial-dialog]');
    if (await dialog.count() && await dialog.evaluate(x => x.open)) await page.locator('[data-tutorial-skip]').first().click();
}
async function open(page, uri) {
    const response = await page.goto(base + uri, { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200, uri);
    await dismiss(page);
}
async function submit(page, form) {
    const button = form.locator('button:not([type="button"])').last();
    if (await form.getAttribute('data-inquiry-reply-form') !== null) {
        const message = await form.locator('[name=message]').inputValue();
        await button.click();
        await page.locator('[data-inquiry-live-status]').getByText('Message sent.', { exact: true }).waitFor();
        const sent = page.locator('[data-inquiry-messages] li').filter({ hasText: message }).first();
        await sent.waitFor({ state: 'visible' });
        assert.equal(await sent.locator('p').textContent(), message);
        assert.equal(await form.locator('[name=message]').inputValue(), '');
        return;
    }
    if (await form.getAttribute('data-confirm')) {
        await button.click();
        await page.locator('#confirmation-dialog').waitFor({ state: 'visible' });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('[data-confirm-accept]').click()]);
    } else {
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), button.click()]);
    }
    assert.equal(await page.locator('.alert-danger:visible').count(), 0, await page.locator('.alert-danger').allTextContents().then(x => x.join('\n')));
    await dismiss(page);
}
async function login(page, email) {
    await open(page, '/login');
    await page.locator('[name=email]').fill(email);
    await page.locator('[name=password]').fill(fixtures.password);
    await submit(page, page.locator('form[action$="/login"]'));
    assert.ok(page.url().includes('/dashboard'), 'Login must reach the authenticated workspace');
}
async function screenshot(page, name) { await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true }); }
try {
    for (let attempt = 0; attempt < 40; attempt++) {
        try { const response = await fetch(base + '/login', { signal: AbortSignal.timeout(3000) }); if (response.ok) break; } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
        if (attempt === 39) throw new Error('Local test server did not start');
    }
    browser = await chromium.launch({ headless: true, executablePath: process.platform === 'win32' ? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' : undefined });
    async function pageFor() {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        await context.route('**/*', route => {
            const host = new URL(route.request().url()).hostname;
            if (host === '127.0.0.1' || route.request().url().startsWith('data:')) return route.continue();
            diagnostics.blockedExternalRequests.push(route.request().url());
            return route.fulfill({ status: 200, contentType: 'text/plain', body: '' });
        });
        const page = await context.newPage();
        page.on('pageerror', error => diagnostics.pageErrors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') diagnostics.consoleErrors.push(message.text()); });
        page.on('response', response => { if (response.status() >= 400) diagnostics.failedResponses.push({ url: response.url(), status: response.status() }); });
        return page;
    }
    const student = await pageFor(), owner = await pageFor(), admin = await pageFor(), masterAdmin = await pageFor();
    const property = `/properties/${fixtures.property_id}`, space = fixtures.space_ids[0];
    let inquiryId, bookingId;
    await check('owner maintains real student listing metadata', async () => {
        await login(owner, fixtures.owner_email);
        await open(owner, `/owner/spaces/${space}/edit`);
        const form = owner.locator(`form[action$="/owner/spaces/${space}"]`);
        await form.locator('[name=student_friendly][type=checkbox]').check();
        await form.locator('[name=nearby_school]').fill('Browser Test University Main');
        await form.locator('[name=campus_distance_km]').fill('1.5');
        await form.locator('[name=transport_notes]').fill('Owner reports access to the main road; confirm local routes.');
        await submit(owner, form);
    });
    await check('student registration and real single-use OTP through isolated mail sink', async () => {
        await open(student, '/register/student');
        await screenshot(student, '01-student-registration');
        const form = student.locator('form[action$="/register/student"]');
        for (const [name, value] of Object.entries({ name: 'Browser Student Renter', email: 'student@student-browser.example.test', password: fixtures.password, password_confirmation: fixtures.password, school_or_campus: 'Browser Test University', campus: 'Main', preferred_location: 'Cebu City' })) await form.locator(`[name=${name}]`).fill(value);
        await form.locator('[name=terms]').check();
        await submit(student, form);
        assert.ok(student.url().endsWith('/verify-contact'));
        const { code } = JSON.parse(await readFile(path.join(runRoot, 'otp.json'), 'utf8'));
        assert.match(code, /^\d{6}$/);
        await student.locator('[name=code]').fill(code);
        await submit(student, student.locator('form[action$="/verify-contact"]'));
        assert.ok(student.url().endsWith('/renter/dashboard'));
        await screenshot(student, '02-student-dashboard');
    });
    await check('student preferences drive filters and responsive search', async () => {
        await open(student, '/renter/preferences');
        const form = student.locator('form[action$="/renter/preferences"]');
        await form.locator('[name=budget_max]').fill('7000');
        await form.locator('[name=max_distance_km]').fill('2');
        await form.locator('[name="preferred_types[]"][value=private_room]').check();
        await submit(student, form);
        await student.getByRole('link', { name: 'Search with my preferences' }).click();
        await student.waitForLoadState('networkidle');
        assert.equal(await student.locator('.card-listing').count(), 2);
        assert.ok((await student.locator('body').innerText()).includes('owner-reported'));
        await screenshot(student, '03-filtered-housing');
    });
    await check('save and compare database listings', async () => {
        await submit(student, student.locator(`form[action$="/renter/saved-spaces/${space}"]`).first());
        for (const id of fixtures.space_ids) await submit(student, student.locator(`form[action$="/compare/${id}"]`).first());
        await open(student, '/compare');
        assert.ok((await student.locator('body').innerText()).toLowerCase().includes('study room alpha'));
        assert.ok((await student.locator('body').innerText()).toLowerCase().includes('study room beta'));
        await screenshot(student, '04-property-comparison');
    });
    await check('student inquiry reaches owner and reply stays in same thread', async () => {
        await open(student, property);
        const form = student.locator(`form[action$="/renter/spaces/${space}/inquiries"]`);
        await form.locator('[name=message]').fill('Is this room available for my student move-in?');
        await submit(student, form);
        inquiryId = student.url().split('/').at(-1);
        await open(owner, `/owner/inquiries/${inquiryId}`);
        const reply = owner.locator(`form[action$="/owner/inquiries/${inquiryId}/replies"]`);
        await reply.locator('[name=message]').fill('Yes, please review the terms and send your preferred dates.');
        await reply.locator('button[type=submit]').click();
        await owner.waitForFunction(() => document.querySelector('[data-inquiry-live-status]')?.textContent === 'Message sent.');
        await student.locator('[data-activity-id]').filter({ hasText: 'please review the terms' }).waitFor({ timeout: 12000 });
        assert.ok((await student.locator('body').innerText()).includes('please review the terms'));
    });
    await check('viewing request and owner scheduling', async () => {
        await open(student, property);
        const form = student.locator(`form[action$="/renter/spaces/${space}/viewings"]`);
        await form.locator('[name=requested_start_at]').fill('2026-10-19T10:00');
        await submit(student, form);
        await open(owner, '/owner/viewings');
        await owner.locator('a[href*="/owner/viewings/"]').first().click();
        await owner.waitForLoadState('networkidle');
        await submit(owner, owner.locator('form[action$="/accept"]'));
    });
    async function requestRental() {
        await open(student, property);
        const form = student.locator(`form[action$="/renter/requests/${space}"]`);
        await form.locator('[name=move_in_date]').fill('2026-10-19');
        await form.locator('[name=duration_months]').selectOption('1');
        await form.locator('[name=contact_phone]').fill('09171234567');
        await form.locator('[name=terms_accepted]').check();
        await submit(student, form);
    }
    async function ownerDecision(status, response) {
        await open(owner, '/owner/rental-requests');
        const form = owner.locator('form[action*="/owner/rental-requests/"]').first();
        await form.locator('[name=status]').selectOption(status);
        await form.locator('[name=response_message]').fill(response);
        await submit(owner, form);
    }
    await check('rental request additional-information and rejection branches', async () => {
        await requestRental();
        await ownerDecision('more_information_requested', 'Please confirm your planned arrival time.');
        await open(student, '/renter/requests');
        const reply = student.locator('form[action$="/response"]').first();
        await reply.locator('[name=renter_response]').fill('I will arrive after my classes on the agreed move-in date.');
        await submit(student, reply);
        await ownerDecision('rejected', 'Please submit a new request after checking your plans.');
        await open(student, '/renter/requests');
        assert.ok((await student.locator('body').innerText()).toLowerCase().includes('rejected'));
    });
    await check('accepted terms approval creates booking and unpaid schedule', async () => {
        await requestRental();
        await ownerDecision('approved', 'Your dates are approved. Coordinate key collection through our conversation.');
        await open(student, '/bookings');
        const link = student.locator('a[href*="/bookings/"]').filter({ hasText: 'Study Room Alpha' }).first();
        const href = await link.getAttribute('href');
        bookingId = href.split('/').at(-1);
        await link.click(); await student.waitForLoadState('networkidle');
        const text = await student.locator('body').innerText();
        assert.ok(text.includes('Terms accepted') && text.includes('Quiet hours after 10 PM.'));
        assert.ok(text.includes('SpaceMatch has not processed a payment'));
        await screenshot(student, '05-booking-terms-payments');
    });
    await check('move-in coordination and supported active-rental transition', async () => {
        await student.getByRole('link', { name: 'Open rental conversation' }).click();
        await student.waitForLoadState('networkidle');
        const form = student.locator(`form[action$="/renter/inquiries/${inquiryId}/replies"]`);
        await form.locator('[name=message]').fill('Please meet me at the entrance for the agreed key handover.');
        await submit(student, form);
        await writeFile(path.join(runRoot, 'clock.txt'), '2026-10-19 12:00:00');
        await open(owner, '/bookings');
        assert.ok(owner.url().endsWith('/bookings'), `Owner was redirected to ${owner.url()} instead of the bookings list`);
        const moveInForm = owner.locator(`form[action$="/owner/bookings/${bookingId}/status"]`);
        assert.equal(await moveInForm.count(), 1, `Expected one owner move-in action for booking ${bookingId}`);
        process.stdout.write(`Move-in page ${owner.url()} action=${await moveInForm.getAttribute('action')} confirmation=${await moveInForm.getAttribute('data-confirm')}\n`);
        await submit(owner, moveInForm);
    });
    await check('owner-recorded partial payment updates existing ledger', async () => {
        await open(owner, '/payments');
        const form = owner.locator('form[action*="/owner/payments/"]').first();
        const paymentId = (await form.getAttribute('action')).match(/\/owner\/payments\/(\d+)\/confirm$/)[1];
        await form.locator('[name=payment_date]').fill('2026-10-19');
        await form.locator('[name=payment_method]').selectOption('cash');
        await form.locator('[name=amount]').fill('1000');
        await form.locator('[name=reference]').fill('Isolated browser manual receipt fixture');
        await submit(owner, form);
        await open(student, '/payments');
        assert.ok((await student.locator('body').innerText()).includes('1,000.00'));

        const proof = student.locator(`form[action$="/renter/payments/${paymentId}/submissions"]`);
        await proof.locator('[name=amount]').fill('500');
        await proof.locator('[name=payment_date]').fill('2026-10-19');
        await proof.locator('[name=payment_method]').selectOption('gcash');
        await proof.locator('[name=reference]').fill('Isolated browser proof reference');
        await proof.locator('[name=evidence]').setInputFiles({
            name: 'payment-proof.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF'),
        });
        await submit(student, proof);
        assert.ok((await student.locator('body').innerText()).includes('waiting for the property owner to review'));

        await open(owner, '/owner/payment-submissions');
        await submit(owner, owner.locator('form[action*="/owner/payment-submissions/"]').first());
        await open(student, '/payments');
        assert.ok((await student.locator('body').innerText()).includes('Proof Verified'));
        assert.ok((await student.locator('body').innerText()).includes('500.00'));
    });
    await check('maintenance submission evidence and owner resolution', async () => {
        await open(student, '/maintenance');
        const form = student.locator('form[action$="/maintenance"]');
        await form.locator('[name=description]').fill('The study-room sink is leaking. Please inspect the pipe.');
        await form.locator('[name=evidence]').setInputFiles({ name: 'maintenance.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF') });
        await submit(student, form);
        await open(owner, '/maintenance');
        await submit(owner, owner.locator('form[action*="/owner/maintenance/"]').first());
        const resolve = owner.locator('form[action*="/owner/maintenance/"]').first();
        await resolve.locator('[name=notes]').fill('Replaced the leaking pipe and tested the sink.');
        await submit(owner, resolve);
        await open(student, '/maintenance');
        await student.locator('summary').filter({ hasText: 'Update history' }).click();
        assert.ok((await student.locator('body').innerText()).includes('Replaced the leaking pipe'));
        await screenshot(student, '06-maintenance-resolved');
    });
    await check('date-eligible rental completion and student review', async () => {
        await writeFile(path.join(runRoot, 'clock.txt'), '2026-11-19 12:00:00');
        await open(owner, '/bookings');
        await submit(owner, owner.locator(`form[action$="/owner/bookings/${bookingId}/status"]`));
        await open(student, '/reviews');
        const form = student.locator(`form[action$="/bookings/${bookingId}/review"]`);
        await form.locator('[name=rating]').selectOption('5');
        await form.locator('[name=comment]').fill('Helpful owner and a comfortable place to study. Browser test review.');
        await submit(student, form);
        await screenshot(student, '07-completed-rental-review');
    });
    await check('administrator moderates review with retained audit history', async () => {
        await login(admin, fixtures.admin_email);
        await open(admin, '/reviews');
        const form = admin.locator('form[action*="/admin/reviews/"]').first();
        await form.locator('[name=reason]').fill('Isolated test review removed from public display.');
        await submit(admin, form);
        await open(admin, '/admin/audit-logs');
        assert.ok((await admin.locator('body').innerText()).includes('review'));
        await open(student, property);
        assert.ok(!(await student.locator('body').innerText()).includes('Browser test review.'));
    });
    await check('master administrator can grant access only through the restricted workspace', async () => {
        await login(masterAdmin, fixtures.master_admin_email);
        await open(masterAdmin, '/admin/master-administrators');
        assert.ok((await masterAdmin.locator('body').innerText()).includes(fixtures.target_admin_email));
        const grant = masterAdmin.locator(`form[action$="/admin/master-administrators/${fixtures.target_admin_id}"]`);
        await submit(masterAdmin, grant);
        assert.ok((await masterAdmin.locator('body').innerText()).includes('Master administrator'));
    });
    await check('published listing carousel responds to controls with reduced-motion preference', async () => {
        await student.emulateMedia({ reducedMotion: 'reduce' });
        await open(student, '/');
        const carousel = student.locator('[data-home-carousel]');
        const active = carousel.locator('[data-carousel-slide].is-active');
        const firstHref = await active.getAttribute('href');
        await carousel.locator('[data-carousel-next]').click();
        await student.waitForFunction((href) => document.querySelector('[data-home-carousel] [data-carousel-slide].is-active')?.getAttribute('href') !== href, firstHref);
        assert.notEqual(await carousel.locator('[data-carousel-slide].is-active').getAttribute('href'), firstHref);
    });
    await check('mobile tablet desktop layout and keyboard navigation', async () => {
        for (const [name, width, height] of [['mobile', 375, 812], ['tablet', 768, 1024], ['desktop', 1366, 900], ['wide', 1920, 1080]]) {
            await student.setViewportSize({ width, height });
            await open(student, `/renter/inquiries/${inquiryId}`);
            const overflow = await student.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
            diagnostics.layout.push({ name, width, overflow });
            assert.equal(overflow, false, `${name} horizontal document overflow`);
            await screenshot(student, '08-messages-' + name);
        }
        await student.keyboard.press('Tab');
        assert.notEqual(await student.evaluate(() => document.activeElement.tagName), 'BODY');
    });
    assert.deepEqual(diagnostics.pageErrors, []);
    assert.deepEqual(diagnostics.failedResponses, []);
} catch (error) {
    results.push({ name: 'journey', status: 'failed', error: error.message });
    process.exitCode = 1;
    process.stderr.write(error.stack + '\n');
    if (browser) for (const context of browser.contexts()) for (const page of context.pages()) await page.screenshot({ path: path.join(output, 'failure-' + browser.contexts().indexOf(context) + '.png'), fullPage: true }).catch(() => {});
} finally {
    if (browser) await browser.close();
    server.kill();
    await writeFile(path.join(runRoot, 'server.log'), serverLog);
    await writeFile(path.join(output, 'results.json'), JSON.stringify({ results, diagnostics, runRoot }, null, 2));
    process.stdout.write(`Browser results: ${results.filter(x => x.status === 'passed').length} passed; ${results.filter(x => x.status === 'failed').length} failed.\n`);
}
