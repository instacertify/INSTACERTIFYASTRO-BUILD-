// Search Console has /sitemap.xml registered from the old site, so the page list is published under that name.
// @astrojs/sitemap writes sitemap-index.xml plus sitemap-0.xml, sitemap-1.xml… (45,000 URLs each). With a single
// chunk it becomes sitemap.xml and the index points at it; with more, sitemap.xml is a copy of the index.
// Runs after `astro build`, before precompress.
import { copyFileSync, existsSync, readdirSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const DIST = resolve('dist');
const INDEX = join(DIST, 'sitemap-index.xml');
const chunks = readdirSync(DIST).filter((f) => /^sitemap-\d+\.xml$/.test(f));

if (!existsSync(INDEX)) {
  console.log('sitemap-file: no sitemap-index.xml, skipped');
} else if (chunks.length === 1) {
  renameSync(join(DIST, chunks[0]), join(DIST, 'sitemap.xml'));
  writeFileSync(INDEX, readFileSync(INDEX, 'utf8').replace(`/${chunks[0]}</loc>`, '/sitemap.xml</loc>'));
  console.log(`sitemap-file: ${chunks[0]} -> sitemap.xml`);
} else {
  copyFileSync(INDEX, join(DIST, 'sitemap.xml'));
  console.log(`sitemap-file: ${chunks.length} chunks, sitemap.xml is the index`);
}
