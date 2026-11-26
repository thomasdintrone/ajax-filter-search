// Renders the static prototypes to PNG next to the HTML: node design/prototypes/render.mjs (needs the repo devDependency playwright).
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
const dir = fileURLToPath( new URL( '.', import.meta.url ) ).replace( /\/$/, '' );
const b=await chromium.launch({channel:'chrome'});
for(const page of ['editorial','library'])for(const [vp,w,h] of [['desktop',1200,900],['mobile',390,844]])for(const th of ['light','dark']){
 const c=await b.newContext({viewport:{width:w,height:h},deviceScaleFactor:1});const p=await c.newPage();
 await p.goto(`file://${dir}/${page}.html${th==='dark'?'#dark':''}`);await p.screenshot({path:`${dir}/${page}-${vp}-${th}.png`,fullPage:true});await c.close();}
await b.close();
