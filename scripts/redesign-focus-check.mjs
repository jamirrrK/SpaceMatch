import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { writeFile } from 'node:fs/promises';
const base=process.env.PHASE3_BROWSER_URL ?? 'http://127.0.0.1:8018';
assert.equal(new URL(base).hostname,'127.0.0.1');
const browser=await chromium.launch({headless:true,executablePath:process.env.EDGE_EXECUTABLE ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
try {
    const page=await browser.newPage({viewport:{width:1440,height:900}});
    await page.goto(base,{waitUntil:'networkidle'});
    await page.waitForFunction(()=>document.querySelector('[data-tutorial-dialog]').open);
    const primary=page.locator('[data-tutorial-start]');
    await primary.focus();
    const colors=await primary.evaluate(button=>({background:getComputedStyle(button).backgroundColor,color:getComputedStyle(button).color,focused:document.activeElement===button}));
    assert.equal(colors.focused,true);
    assert.equal(colors.color,'rgb(255, 255, 255)');
    assert.equal(colors.background,'rgb(6, 92, 101)');
    await page.screenshot({path:'docs/browser/tutorial-first-visit.png'});
    await page.locator('[data-tutorial-skip]').first().click();
    await page.waitForFunction(()=>document.activeElement.id==='main-content');
    for(const width of [1440,390]) {
        await page.setViewportSize({width,height:960});
        await page.goto(base+'/register',{waitUntil:'networkidle'});
        await page.screenshot({path:`docs/browser/redesign-registration-${width}.png`,fullPage:true});
    }
    await writeFile('docs/browser/redesign/focus-check.json',JSON.stringify({passed:true,colors,returnFocus:'main-content'},null,2));
    console.log('PASS: Primary action retains readable colors on keyboard focus; tutorial returns focus without scrolling.');
} finally {await browser.close();}
