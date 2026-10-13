// Ad landing pages and the home page are almost always a first visit, so the shared stylesheet is inlined there to
// save the render-blocking request; every other page keeps the cached file. On the home page the file is still
// prefetched at low priority so the next page finds it in the cache. Runs after `astro build`, before precompress.
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const DIST = resolve('dist');
const LANDING = join(DIST, 'lp');

const inline = (file, prefetch) => {
  const html = readFileSync(file, 'utf8');
  const out = html.replace(/<link rel="stylesheet" href="(\/_astro\/[^"]+\.css)">/g, (tag, href) => {
    const css = join(DIST, href);
    if (!existsSync(css)) return tag;
    const style = `<style>${readFileSync(css, 'utf8').replace(/<\/style/gi, '<\\/style')}</style>`;
    return prefetch ? `${style}<link rel="prefetch" href="${href}" as="style">` : style;
  });
  if (out === html) return false;
  writeFileSync(file, out);
  return true;
};

let count = 0;
if (existsSync(LANDING)) {
  for (const name of readdirSync(LANDING).filter((n) => n.endsWith('.html'))) {
    if (inline(join(LANDING, name), false)) count++;
  }
}
const home = join(DIST, 'index.html');
const homeDone = existsSync(home) && inline(home, true);
console.log(`inline-landing-css: ${count} landing pages${homeDone ? ' + home page' : ''}`);
