// Ad landing pages are almost always a first visit, so the shared stylesheet is inlined there to save the
// render-blocking request; every other page keeps the cached file. Runs after `astro build`, before precompress.
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const DIST = resolve('dist');
const LANDING = join(DIST, 'lp');

let count = 0;
if (existsSync(LANDING)) {
  for (const name of readdirSync(LANDING).filter((n) => n.endsWith('.html'))) {
    const file = join(LANDING, name);
    const html = readFileSync(file, 'utf8');
    const out = html.replace(/<link rel="stylesheet" href="(\/_astro\/[^"]+\.css)">/g, (tag, href) => {
      const css = join(DIST, href);
      return existsSync(css) ? `<style>${readFileSync(css, 'utf8').replace(/<\/style/gi, '<\\/style')}</style>` : tag;
    });
    if (out !== html) {
      writeFileSync(file, out);
      count++;
    }
  }
}
console.log(`inline-landing-css: ${count} landing pages`);
