import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';

const base = 'http://127.0.0.1:8014';
const output = 'docs/final-product-browser/hero';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.platform === 'win32' ? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' : undefined });
const results = [], errors = [], failed = [];
try {
    const context = await browser.newContext({ hasTouch: true, viewport: { width: 1366, height: 900 } });
    await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    const page = await context.newPage();
    page.setDefaultNavigationTimeout(90000);
    await page.clock.install();
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 400) failed.push(response.url()); });
    await page.goto(base, { waitUntil: 'networkidle' });
    const carousel = page.locator('[data-home-carousel]');
    const slides = carousel.locator('[data-carousel-slide]');
    const active = () => carousel.locator('[data-carousel-slide].is-active').getAttribute('href');
    async function check(name, callback) { await callback(); results.push(name); console.log(`PASS ${name}`); }
    await check('real listing media, captions and access-controlled image MIME', async () => {
        assert.ok(await slides.count() >= 2);
        for (const slide of await slides.all()) {
            const image = slide.locator('[data-carousel-photo]');
            const response = await context.request.get(await image.getAttribute('src'));
            assert.equal(response.status(), 200);
            assert.match(response.headers()['content-type'], /^image\/(png|jpeg|webp)/);
            assert.ok((await response.body()).length > 100);
            assert.match(await slide.innerText(), /Browser Student Residences/);
        }
        await carousel.locator('[data-carousel-photo]').first().evaluate(image => image.decode());
    });
    await check('all 17 image-pack illustrations are accessible', async () => {
        const { default: manifest } = await import('../resources/data/marketing-images.json', { with: { type: 'json' } });
        assert.equal(Object.keys(manifest).length, 17);
        for (const item of Object.values(manifest)) {
            for (const format of ['jpg', 'webp']) {
                const response = await context.request.get(`${base}/${item.variants.at(-1)[format]}`);
                assert.equal(response.status(), 200);
                assert.match(response.headers()['content-type'], /^image\//);
            }
        }
    });
    await check('automatic transition and pause on hover', async () => {
        await page.locator('h1').click(); await page.mouse.move(1, 1);
        const first = await active(); await page.clock.fastForward(6100); assert.notEqual(await active(), first);
        await carousel.hover(); const hovered = await active(); await page.clock.fastForward(12100); assert.equal(await active(), hovered);
        await page.mouse.move(1, 1); await page.clock.fastForward(6100); assert.notEqual(await active(), hovered);
    });
    await check('previous next indicators and keyboard preserve accessibility', async () => {
        await carousel.locator('[data-carousel-indicator]').first().click();
        const first = await active();
        await carousel.locator('[data-carousel-next]').click(); assert.notEqual(await active(), first);
        await carousel.locator('[data-carousel-previous]').click(); assert.equal(await active(), first);
        await carousel.locator('[data-carousel-indicator]').last().click();
        assert.equal(await carousel.locator('[data-carousel-indicator][aria-current=true]').count(), 1);
        await carousel.locator('[data-carousel-next]').focus();
        await page.keyboard.press('Home'); assert.equal(await active(), first);
        await page.keyboard.press('ArrowRight'); assert.notEqual(await active(), first);
        await page.keyboard.press('End');
        assert.equal(await slides.filter({ visible: true }).count(), 1);
        assert.equal(await carousel.locator('[data-carousel-slide][aria-hidden=false]').count(), 1);
        assert.equal(await carousel.locator('[data-carousel-slide][tabindex="-1"]').count(), (await slides.count()) - 1);
    });
    await check('focus pause recovers after focus leaves and manual pause works', async () => {
        const focused = await active(); await page.clock.fastForward(12100); assert.equal(await active(), focused);
        await page.locator('h1').click(); await page.mouse.move(1, 1); await page.clock.fastForward(6100); assert.notEqual(await active(), focused);
        await carousel.locator('[data-carousel-pause]').click(); await page.locator('h1').click(); await page.mouse.move(1, 1);
        const paused = await active(); await page.clock.fastForward(12100); assert.equal(await active(), paused);
    });
    await check('touch swipe navigates without following property link', async () => {
        const first = await active();
        await carousel.dispatchEvent('touchstart', { touches: [{ identifier: 1, clientX: 300, clientY: 100 }] });
        await carousel.dispatchEvent('touchend', { changedTouches: [{ identifier: 1, clientX: 100, clientY: 105 }] });
        assert.notEqual(await active(), first); assert.equal(new URL(page.url()).pathname, '/');
        await carousel.dispatchEvent('click');
    });
    await check('reduced motion disables automatic transitions', async () => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.locator('h1').click(); await page.mouse.move(1, 1);
        const first = await active(); await page.clock.fastForward(12100); assert.equal(await active(), first);
        assert.equal(await carousel.locator('[data-carousel-pause]').getAttribute('aria-pressed'), 'true');
    });
    await check('desktop and mobile images fill frame without document overflow', async () => {
        for (const width of [375, 768, 1366]) {
            await page.setViewportSize({ width, height: 900 });
            const slide = carousel.locator('.is-active');
            await slide.locator('img').first().evaluate(image => image.decode());
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false);
            const box = await slide.boundingBox(), photo = await slide.locator('img').first().boundingBox();
            assert.ok(Math.abs(box.height - photo.height) < 2);
            await carousel.screenshot({ path: `${output}/carousel-${width}.png` });
        }
    });
    await check('decode failure uses labeled illustration without broken icon', async () => {
        const slide = carousel.locator('.is-active');
        await slide.locator('[data-carousel-photo]').dispatchEvent('error');
        assert.equal(await slide.locator('[data-carousel-photo]').isVisible(), false);
        await slide.locator('[data-carousel-fallback] img').evaluate(image => { image.loading = 'eager'; return image.decode(); });
        assert.match(await slide.locator('[data-carousel-image-label]').innerText(), /Illustrative image/);
        await carousel.screenshot({ path: `${output}/illustration-fallback.png` });
    });
    await check('listing link navigates to matching property and room anchor', async () => {
        const link = carousel.locator('.is-active'); const href = await link.getAttribute('href');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), link.click()]);
        assert.equal(page.url(), href);
        assert.match(await page.locator('body').innerText(), /Browser Student Residences/);
    });
    assert.deepEqual(errors, []); assert.deepEqual(failed, []);
    console.log(`${results.length} carousel checks passed; no console/page/HTTP errors.`);
} finally {
    await writeFile(`${output}/results.json`, JSON.stringify({ results, errors, failed }, null, 2));
    await browser.close();
}
