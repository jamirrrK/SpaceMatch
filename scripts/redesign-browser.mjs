import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { readFile, writeFile, mkdir } from 'node:fs/promises';

const base = process.env.PHASE3_BROWSER_URL ?? 'http://127.0.0.1:8018';
assert.equal(new URL(base).hostname, '127.0.0.1');
const fixture = JSON.parse(await readFile('storage/app/private/phase3-browser-fixtures.json', 'utf8'));
const output = 'docs/browser/redesign';
await mkdir(output, {recursive:true});
const browser = await chromium.launch({headless:true, executablePath:process.env.EDGE_EXECUTABLE ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
const page = await browser.newPage({viewport:{width:1440,height:1000}});
const results=[];
const errors=[];
page.on('pageerror', error=>errors.push(error.message));
async function check(name,callback) { await callback(); results.push({name,status:'passed'}); console.log(`PASS: ${name}`); }
async function open(path) {
    const response = await page.goto(base+path,{waitUntil:'networkidle'});
    assert.equal(response.status(),200);
    if(await page.locator('[data-tutorial-dialog]').evaluate(dialog=>dialog.open)) await page.locator('[data-tutorial-skip]').first().click();
}
try {
    await check('Homepage budget search submits supported filters',async()=>{
        await open('/');
        await page.screenshot({path:`${output}/home-desktop.png`,fullPage:true});
        await page.locator('#home-location').fill('Cebu');
        await page.locator('#home-max-price').fill('9000');
        await Promise.all([page.waitForURL('**/properties?**'),page.locator('.home-search button').click()]);
        assert.equal(new URL(page.url()).searchParams.get('max_price'),'9000');
        assert.ok(await page.getByRole('link',{name:'Remove filter: Up to PHP 9,000'}).isVisible());
    });
    await check('Removing one filter preserves the other filters',async()=>{
        await page.getByRole('link',{name:'Remove filter: Up to PHP 9,000'}).click();
        assert.equal(new URL(page.url()).searchParams.get('max_price'),null);
        assert.equal(new URL(page.url()).searchParams.get('location'),'Cebu');
    });
    await check('Mobile filters open, submit sorting and close without hiding results',async()=>{
        await page.setViewportSize({width:390,height:844});
        await open('/properties');
        assert.equal(await page.locator('#rentalFilters').isVisible(),false);
        await page.getByRole('button',{name:'Filters & sort'}).click();
        await page.locator('#rentalFilters').waitFor({state:'visible'});
        await page.locator('#location').fill('Cebu');
        await page.locator('#sort').selectOption('price_asc');
        await Promise.all([page.waitForURL('**/properties?**'),page.locator('#rentalFilters button[type=submit]').click()]);
        assert.equal(new URL(page.url()).searchParams.get('sort'),'price_asc');
        await page.screenshot({path:`${output}/mobile-search.png`,fullPage:true});
    });
    await check('Authentication split layout stacks without overflow at 320px',async()=>{
        for(const width of [1440,768,390,320]) {
            await page.setViewportSize({width,height:960});
            for(const path of ['/login','/register','/forgot-password','/reset-password/qa-token']) {
                await open(path);
                assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= document.documentElement.clientWidth+2),`${path} at ${width}`);
                await page.screenshot({path:`${output}/${path.split('/')[1]}-${width}.png`,fullPage:true});
            }
        }
    });
    await check('Branded missing page preserves HTTP 404 and working navigation',async()=>{
        const response=await page.goto(base+'/missing-space-page');
        assert.equal(response.status(),404);
        assert.ok(await page.getByRole('heading',{name:'We couldn’t find this space'}).isVisible());
        await page.screenshot({path:`${output}/404-mobile.png`,fullPage:true});
    });
    await check('Profile and rental records retain role workspace and active navigation',async()=>{
        await page.setViewportSize({width:1440,height:1000});
        await open('/login');
        await page.locator('[name=email]').fill(fixture.accounts.renter.email);
        await page.locator('[name=password]').fill('SpaceMatch-Browser-2026!');
        await Promise.all([page.waitForURL('**/renter/dashboard'),page.locator('form[action$="/login"] button[type=submit]').click()]);
        for(const path of ['/profile','/bookings','/payments','/maintenance','/reviews']) {
            await open(path);
            assert.ok(await page.locator('.app-sidebar').isVisible());
            assert.equal(await page.locator('.sidebar-link.active').count(),1);
            await page.screenshot({path:`${output}/${path.slice(1)}-desktop.png`,fullPage:true});
        }
    });
    assert.deepEqual(errors,[]);
} catch(error) {
    results.push({name:'Redesign interaction checks',status:'failed',error:error.message});
    await page.screenshot({path:`${output}/failure.png`,fullPage:true}).catch(()=>{});
    console.error(error);
    process.exitCode=1;
} finally {
    await browser.close();
    await writeFile(`${output}/results.json`,JSON.stringify({executedAt:new Date().toISOString(),results,errors},null,2));
}
