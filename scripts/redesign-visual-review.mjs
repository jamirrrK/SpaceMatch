import { chromium } from '@playwright/test';
import { readdir, readFile, mkdir } from 'node:fs/promises';
const output='docs/browser/redesign';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.EDGE_EXECUTABLE ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
const page=await browser.newPage({viewport:{width:1500,height:1500},deviceScaleFactor:1});
for(const width of [1440,390]) {
    const files=(await readdir('docs/browser')).filter(name=>name.startsWith('redesign-')&&name.endsWith(`-${width}.png`)).sort();
    for(let offset=0;offset<files.length;offset+=6) {
        const cards=await Promise.all(files.slice(offset,offset+6).map(async name=>`<article><h2>${name}</h2><img src="data:image/png;base64,${(await readFile(`docs/browser/${name}`)).toString('base64')}"></article>`));
        await page.setContent(`<style>body{margin:0;padding:16px;background:#e2e8f0;font:13px Arial;display:grid;grid-template-columns:repeat(3,1fr);gap:16px}article{background:white;padding:8px;height:700px;overflow:hidden}h2{font-size:12px;height:24px;margin:0 0 8px;color:#14243a}img{display:block;width:${width===390?'390px':'100%'};max-width:100%;margin:auto}</style>${cards.join('')}`);
        await page.evaluate(()=>Promise.all([...document.images].map(img=>img.decode())));
        await page.screenshot({path:`${output}/review-${width}-${offset/6+1}.png`,fullPage:true});
    }
}
await browser.close();
console.log('Contact sheets generated for visual inspection of all 25 routed surfaces.');
