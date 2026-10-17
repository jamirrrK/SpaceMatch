import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { readFile, writeFile } from 'node:fs/promises';
const base=process.env.PHASE3_BROWSER_URL ?? 'http://127.0.0.1:8018';
assert.equal(new URL(base).hostname,'127.0.0.1');
const fixture=JSON.parse(await readFile('storage/app/private/phase3-browser-fixtures.json','utf8'));
const workflow=JSON.parse(await readFile('docs/browser/results.json','utf8'));
const browser=await chromium.launch({headless:true,executablePath:process.env.EDGE_EXECUTABLE ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
const captures=[];
async function open(page,path) {
    const response=await page.goto(new URL(path,base).href,{waitUntil:'networkidle'});
    assert.equal(response.status(),200,path);
    if(await page.locator('[data-tutorial-dialog]').evaluate(dialog=>dialog.open)) await page.locator('[data-tutorial-skip]').first().click();
}
async function capture(page,name,path) {
    for(const width of [1440,390]) {
        await page.setViewportSize({width,height:960});
        await open(page,path);
        await page.evaluate(async()=>{
            document.documentElement.style.setProperty('scroll-behavior','auto','important');
            document.activeElement?.blur();
            for(const image of document.images) image.loading='eager';
            await Promise.all([...document.images].map(image=>image.decode().catch(()=>{})));
            window.scrollTo(0,0);
            await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
        });
        const state=await page.evaluate(()=>({scrollY,documentWidth:document.documentElement.scrollWidth,clientWidth:document.documentElement.clientWidth,missing:[...document.images].filter(i=>!i.complete||i.naturalWidth===0).map(i=>i.src)}));
        assert.equal(state.scrollY,0);
        assert.ok(state.documentWidth<=state.clientWidth+2);
        assert.deepEqual(state.missing,[]);
        const file=`redesign-${name}-${width}.png`;
        await page.screenshot({path:`docs/browser/${file}`,fullPage:true});
        captures.push({file,...state});
    }
}
try {
    for(const role of ['visitor','owner','admin','renter']) {
        const context=await browser.newContext();
        const page=await context.newPage();
        page.setDefaultTimeout(60000);
        if(role!=='visitor') {
            await open(page,'/login');
            await page.locator('[name=email]').fill(fixture.accounts[role].email);
            await page.locator('[name=password]').fill('SpaceMatch-Browser-2026!');
            await Promise.all([page.waitForURL(`**/${role}/dashboard`),page.locator('form[action$="/login"] button[type=submit]').click()]);
        }
        let surfaces=[];
        if(role==='visitor') surfaces=[['homepage','/'],['login','/login'],['registration','/register'],['forgot-password','/forgot-password'],['reset-password','/reset-password/qa-token'],['property-search','/properties'],['property-detail',`/properties/${fixture.properties.published.id}`]];
        if(role==='owner') {
            await open(page,`/owner/properties/${workflow.propertyId}`);
            const edit=await page.locator('#spaces a').filter({hasText:'Edit space'}).first().getAttribute('href');
            surfaces=[['owner-dashboard','/owner/dashboard'],['owner-properties','/owner/properties'],['owner-property-detail',`/owner/properties/${workflow.propertyId}`],['owner-property-create-form','/owner/properties/create'],['owner-property-form',`/owner/properties/${workflow.propertyId}/edit`],['rental-spaces','/owner/spaces'],['rental-space-create-form',`/owner/spaces/create?property_id=${workflow.propertyId}`],['rental-space-edit-form',edit],['owner-profile','/profile']];
        }
        if(role==='admin') surfaces=[['admin-dashboard','/admin/dashboard'],['admin-properties','/admin/properties'],['admin-property-detail',`/admin/properties/${workflow.propertyId}`]];
        if(role==='renter') surfaces=[['renter-dashboard','/renter/dashboard'],['renter-profile','/profile'],['bookings','/bookings'],['payments','/payments'],['maintenance','/maintenance'],['reviews','/reviews']];
        for(const [name,path] of surfaces) await capture(page,name,path);
        console.log(`Captured ${role} surfaces with all images loaded.`);
        await context.close();
    }
} finally {
    await browser.close();
    await writeFile('docs/browser/redesign/captures.json',JSON.stringify({executedAt:new Date().toISOString(),captures},null,2));
}
