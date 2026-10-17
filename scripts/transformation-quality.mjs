import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
const base=process.env.PHASE3_BROWSER_URL ?? 'http://127.0.0.1:8024';
assert.equal(new URL(base).hostname,'127.0.0.1');
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
const results=[];
await mkdir('docs/transformation/after',{recursive:true});
async function check(name, work){await work();results.push({name,status:'passed'});console.log(`PASS: ${name}`);}
function contrast(a,b){
    const light=color=>{const rgb=color.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;});return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;};
    const x=light(a),y=light(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);
}
try{
    await page.goto(base,{waitUntil:'networkidle'});
    if(await page.locator('[data-tutorial-dialog]').evaluate(n=>n.open))await page.locator('[data-tutorial-skip]:visible').first().click();
    await check('Local Manrope font loads without a remote font service',async()=>{
        await page.evaluate(()=>document.fonts.ready);
        assert.ok(await page.evaluate(()=>[...document.fonts].some(f=>f.family==='Manrope'&&f.status==='loaded')));
        assert.ok(await page.evaluate(()=>getComputedStyle(document.body).fontFamily.includes('Manrope')));
    });
    await check('Desktop search is one horizontal panel below both hero columns',async()=>{
        const search=await page.locator('.home-search-wrap').boundingBox(),hero=await page.locator('.home-hero-grid').boundingBox();
        assert.ok(search.y>=hero.y+hero.height-2);assert.ok(search.width>hero.width*.9);
        const fields=await page.locator('.home-search-field').all();const boxes=await Promise.all(fields.map(f=>f.boundingBox()));
        assert.ok(Math.max(...boxes.map(b=>b.y))-Math.min(...boxes.map(b=>b.y))<2);
    });
    await check('Primary button remains readable on keyboard focus and hover',async()=>{
        const button=page.locator('.home-search button');
        for(const state of ['focus','hover']){
            await button[state]();await page.waitForTimeout(220);
            const colors=await button.evaluate(n=>({fg:getComputedStyle(n).color,bg:getComputedStyle(n).backgroundColor}));
            assert.ok(contrast(colors.fg,colors.bg)>=4.5,JSON.stringify(colors));
        }
    });
    await check('New hero, dark explainer and mint owner callout meet text contrast',async()=>{
        for(const [selector,back] of [['.home-hero-description','.home-hero'],['.home-how-section .text-muted-soft','.home-how-section'],['.home-closing-band p','.home-closing-band']]){
            const fg=await page.locator(selector).first().evaluate(n=>getComputedStyle(n).color);
            const bg=await page.locator(back).evaluate(n=>getComputedStyle(n).backgroundColor);
            assert.ok(contrast(fg,bg)>=4.5,`${selector}: ${contrast(fg,bg)}`);
        }
    });
    await check('All categories remain keyboard reachable in the mobile rail',async()=>{
        await page.setViewportSize({width:375,height:900});
        const rail=page.locator('.category-rail');assert.ok(await rail.isVisible());
        const last=rail.locator('a').last();await last.focus();
        assert.ok(await last.evaluate(n=>document.activeElement===n));
        const b=await last.boundingBox();assert.ok(b.x>=0&&b.x+b.width<=375);
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
    });
    await check('Registration honestly shows unavailable delivery without exposing codes',async()=>{
        await page.goto(base+'/register',{waitUntil:'networkidle'});
        assert.equal(await page.locator('[name=verification_method]:enabled').count(),0);
        assert.ok(await page.getByText('Email delivery is not configured for this app.',{exact:true}).isVisible());
        assert.ok(await page.locator('button[type=submit]:disabled').count()>0);
    });
    await check('Authentication uses browser autofill hints and labeled password visibility',async()=>{
        await page.goto(base+'/login',{waitUntil:'networkidle'});
        assert.equal(await page.locator('#email').getAttribute('autocomplete'),'email');
        assert.equal(await page.locator('#password').getAttribute('autocomplete'),'current-password');
        await page.getByRole('button',{name:'Show password',exact:true}).click();
        assert.equal(await page.locator('#password').getAttribute('type'),'text');
        await page.getByRole('button',{name:'Hide password',exact:true}).click();
        assert.equal(await page.locator('#password').getAttribute('type'),'password');
    });
    await check('Fresh homepage and authentication screenshots contain decoded media',async()=>{
        for(const width of [1440,390])for(const route of ['/','/login']){
            await page.setViewportSize({width,height:1000});await page.goto(base+route,{waitUntil:'networkidle'});
            await page.evaluate(async()=>{await document.fonts.ready;for(const img of document.images)img.loading='eager';await Promise.all([...document.images].map(i=>i.decode()));document.documentElement.style.scrollBehavior='auto';window.scrollTo(0,0);});
            await page.screenshot({path:`docs/transformation/after/${route==='/'?'home':'login'}-${width}.png`,fullPage:true});
        }
    });
}catch(error){results.push({name:'Transformation quality',status:'failed',error:error.message});console.error(error);process.exitCode=1;}
finally{await browser.close();await writeFile('docs/transformation/quality.json',JSON.stringify({executedAt:new Date().toISOString(),results},null,2));}
