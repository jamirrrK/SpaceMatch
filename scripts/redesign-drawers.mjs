import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const base = process.env.PHASE3_BROWSER_URL ?? 'http://127.0.0.1:8024';
assert.equal(new URL(base).hostname, '127.0.0.1');
const fixture = JSON.parse(await readFile('storage/app/private/phase3-browser-fixtures.json', 'utf8'));
const output = 'docs/browser/drawers';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
const page = await browser.newPage({viewport:{width:390,height:844}});
const results = [], errors = [];
page.on('pageerror', error => errors.push(error.message));
async function check(name, work) { await work(); results.push({name,status:'passed'}); console.log(`PASS: ${name}`); }
async function open(path) {
    assert.equal((await page.goto(base+path)).status(),200);
    if (await page.locator('[data-tutorial-dialog]').evaluate(node=>node.open)) await page.locator('[data-tutorial-skip]:visible').first().click();
}
async function settled(id, shown) {
    await page.waitForFunction(({id,shown})=>{
        const node=document.getElementById(id);
        return node.classList.contains('show')===shown && !node.classList.contains('showing') && !node.classList.contains('hiding');
    },{id,shown});
}
async function openDrawer(id) {
    await page.locator(`[data-bs-toggle=offcanvas][data-bs-target="#${id}"]`).click();
    await settled(id,true);
    assert.equal(await page.locator(`#${id}`).getAttribute('role'),'dialog');
    assert.equal(await page.locator(`#${id}`).getAttribute('aria-modal'),'true');
}
async function unlocked() {
    await page.waitForFunction(()=>!document.querySelector('.offcanvas-backdrop'));
    assert.notEqual(await page.evaluate(()=>document.body.style.overflow),'hidden');
}
try {
    await check('Mobile filters trap focus, dismiss with Escape and return focus',async()=>{
        await open('/properties');
        const before=await page.locator('[aria-label="Search results"]').boundingBox();
        await openDrawer('rentalFilters');
        const after=await page.locator('[aria-label="Search results"]').boundingBox();
        assert.equal(before.y,after.y);
        for(let i=0;i<24;i++) {
            await page.keyboard.press('Tab');
            assert.ok(await page.evaluate(()=>document.querySelector('#rentalFilters').contains(document.activeElement)));
        }
        await page.screenshot({path:`${output}/filters-390.png`});
        await page.keyboard.press('Escape'); await settled('rentalFilters',false); await unlocked();
        assert.equal(await page.locator('.filter-toggle').getAttribute('aria-expanded'),'false');
        assert.ok(await page.locator('.filter-toggle').evaluate(node=>node===document.activeElement));
    });
    await check('Filter drawer supports close button, backdrop and desktop resize cleanup',async()=>{
        await openDrawer('rentalFilters');
        await page.getByRole('button',{name:'Close filters',exact:true}).click(); await settled('rentalFilters',false);
        await openDrawer('rentalFilters');
        await page.locator('.offcanvas-backdrop').click({position:{x:4,y:100}}); await settled('rentalFilters',false);
        await openDrawer('rentalFilters');
        await page.setViewportSize({width:1440,height:1000}); await unlocked();
        assert.ok(await page.locator('#location').isVisible());
        assert.equal(await page.locator('#rentalFilters').getAttribute('aria-modal'),null);
        await page.setViewportSize({width:320,height:800});
        assert.equal(await page.locator('#rentalFilters').isVisible(),false);
    });
    await check('Public menu works at 320px and opens tutorial after releasing drawer focus',async()=>{
        await open('/'); await openDrawer('siteNav');
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        await page.screenshot({path:`${output}/navigation-320.png`});
        await page.locator('#siteNav [data-tutorial-replay]').click();
        await settled('siteNav',false); await unlocked();
        await page.waitForFunction(()=>document.querySelector('[data-tutorial-dialog]').open);
        assert.ok(await page.locator('[data-tutorial-dialog]').evaluate(node=>node.contains(document.activeElement)));
        await page.locator('[data-tutorial-skip]:visible').first().click();
    });
    await check('Same-page navigation closes menu without leaving scroll locked',async()=>{
        await openDrawer('siteNav');
        await page.locator('#siteNav a[href$="#how-it-works"]').click();
        await settled('siteNav',false); await unlocked();
        assert.equal(new URL(page.url()).hash,'#how-it-works');
    });
    await check('Workspace drawer retains role links and navigates to account settings',async()=>{
        await open('/login');
        await page.locator('form [name=email]').fill(fixture.accounts.renter.email);
        await page.locator('form [name=password]').fill('SpaceMatch-Browser-2026!');
        await Promise.all([page.waitForURL('**/renter/dashboard'),page.locator('form[action$="/login"] button[type=submit]').click()]);
        await openDrawer('managementNav');
        assert.equal(await page.locator('#managementNav a[href*="/admin/"]').count(),0);
        assert.equal(await page.locator('#managementNav a[href*="/owner/"]').count(),0);
        await page.screenshot({path:`${output}/workspace-320.png`});
        await page.locator('#managementNav a[href$="/profile"]').click(); await page.waitForURL('**/profile');
        await unlocked();
        await page.setViewportSize({width:1440,height:1000});
        assert.ok(await page.locator('#managementNav .sidebar-link.active').isVisible());
    });
    await check('Reduced-motion drawers remain usable',async()=>{
        await page.emulateMedia({reducedMotion:'reduce'}); await page.setViewportSize({width:390,height:844});
        await openDrawer('managementNav');
        await page.getByRole('button',{name:'Close workspace navigation'}).click(); await settled('managementNav',false); await unlocked();
    });
    assert.deepEqual(errors,[]);
} catch(error) {
    results.push({name:'Drawer regression',status:'failed',error:error.message});
    await page.screenshot({path:`${output}/failure.png`}).catch(()=>{});
    console.error(error); process.exitCode=1;
} finally {
    await browser.close();
    await writeFile(`${output}/results.json`,JSON.stringify({executedAt:new Date().toISOString(),results,errors},null,2));
}
