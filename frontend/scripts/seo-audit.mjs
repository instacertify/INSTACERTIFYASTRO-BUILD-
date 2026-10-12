// Audits the static build: legacy sitemap coverage, per-page SEO tags, content depth and images.
// Usage: node scripts/seo-audit.mjs [legacy-sitemap.xml|url] [--media-root=../backend/storage/app/public]
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const DIST = resolve('dist');
const args = process.argv.slice(2);
const legacySource = args.find((a) => !a.startsWith('--')) ?? '../backend/storage/app/legacy/sitemap.xml';
const mediaRoot = resolve(args.find((a) => a.startsWith('--media-root='))?.split('=')[1] ?? '../backend/storage/app/public');
const norm = (u) => '/' + u.replace(/^https?:\/\/[^/]+/i, '').replace(/[?#].*$/, '').replace(/^\/+|\/+$/g, '');
const locs = (xml) => [...xml.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/g)].map((m) => m[1].replace(/&amp;/g, '&'));
const decode = (s) => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
const attr = (tag, name) => decode(tag.match(new RegExp(`\\s${name}=(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'))?.slice(1).find((v) => v !== undefined) ?? '');

if (!existsSync(DIST)) {
  console.error('dist/ not found — run `npm run build` first.');
  process.exit(1);
}

// ---------------------------------------------------------------- inventory
const walk = (dir) => readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? walk(join(dir, f)) : [join(dir, f)]));
const htmlFiles = walk(DIST).filter((f) => f.endsWith('.html') && !f.includes(`${'_astro'}`));
const pathOf = (file) => {
  const rel = relative(DIST, file).replace(/\\/g, '/').replace(/\.html$/, '').replace(/(^|\/)index$/, '');
  return '/' + rel;
};
const pages = new Map(htmlFiles.map((f) => [norm(pathOf(f)), f]));

const redirects = new Map();
// "rewrite" lines: old URLs that show another page without redirecting.
const samePage = new Map();
if (existsSync(join(DIST, 'redirects.caddy'))) {
  for (const line of readFileSync(join(DIST, 'redirects.caddy'), 'utf8').split('\n')) {
    const [kind, from, to] = line.trim().split(/\s+/);
    if (from && to) (kind === 'rewrite' ? samePage : redirects).set(norm(from), to);
  }
}

const sitemapFiles = readdirSync(DIST).filter((f) => /^sitemap(-\d+)?\.xml$/.test(f));
const sitemapUrls = new Set(sitemapFiles.flatMap((f) => locs(readFileSync(join(DIST, f), 'utf8'))).map(norm));

// ------------------------------------------------------------ legacy coverage
const legacyXml = /^https?:/.test(legacySource) ? await (await fetch(legacySource)).text() : readFileSync(legacySource, 'utf8');
const legacy = [...new Set(locs(legacyXml).map(norm))];
const coverage = { page: [], redirect: [], missing: [], redirectToMissing: [] };
for (const path of legacy) {
  if (pages.has(path) || (samePage.has(path) && pages.has(norm(samePage.get(path))))) coverage.page.push(path);
  else if (redirects.has(path)) {
    const target = redirects.get(path);
    (target.startsWith('http') || pages.has(norm(target)) ? coverage.redirect : coverage.redirectToMissing).push(`${path} -> ${target}`);
  } else coverage.missing.push(path);
}

// ------------------------------------------------------------- page checks
const issues = {};
const flag = (type, path, detail = '') => (issues[type] ??= []).push(detail ? `${path} — ${detail}` : path);
const titles = new Map();
const descriptions = new Map();
const imageStats = { total: 0, migrated: 0, legacyHost: 0, missingFile: 0, external: 0, local: 0 };

for (const [path, file] of pages) {
  if (path === '/404') continue;
  const html = readFileSync(file, 'utf8');
  const head = html.split('</head>')[0];
  const body = html.slice(head.length);
  const main = body.match(/<main[\s\S]*?<\/main>/i)?.[0] ?? body;

  const title = decode(head.match(/<title>([\s\S]*?)<\/title>/i)?.[1]?.trim() ?? '');
  const desc = attr(head.match(/<meta[^>]+name=["']description["'][^>]*>/i)?.[0] ?? '', 'content');
  const canonical = attr(head.match(/<link[^>]+rel=["']canonical["'][^>]*>/i)?.[0] ?? '', 'href');
  const robots = attr(head.match(/<meta[^>]+name=["']robots["'][^>]*>/i)?.[0] ?? '', 'content');
  const noindex = /noindex/i.test(robots);
  const h1s = [...main.matchAll(/<h1[\s>]/gi)].length;
  const words = main.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ').split(/\s+/).filter((w) => /\w/.test(w)).length;

  if (!title) flag('missing title', path);
  else if (title.length > 70) flag('title too long (>70)', path, `${title.length}: ${title}`);
  if (!desc) flag('missing meta description', path);
  else if (desc.length < 50 || desc.length > 170) flag('description length outside 50-170', path, `${desc.length}`);
  if (!canonical) flag('missing canonical', path);
  else if (norm(canonical) !== path && !noindex) flag('canonical points elsewhere', path, canonical);
  if (h1s !== 1) flag('h1 count not 1', path, String(h1s));
  if (words < 150 && !noindex && !/\/page\/\d+$/.test(path)) flag('thin content (<150 words)', path, String(words));
  if (!/property=["']og:image["']/i.test(head)) flag('missing og:image', path);

  for (const block of head.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/gi)) {
    try { JSON.parse(block[1]); } catch { flag('invalid JSON-LD', path); }
  }

  if (!noindex) {
    if (title) titles.set(title, [...(titles.get(title) ?? []), path]);
    if (desc) descriptions.set(desc, [...(descriptions.get(desc) ?? []), path]);
    if (!sitemapUrls.has(path) && !/\/page\/\d+$/.test(path)) flag('indexable page missing from sitemap', path);
  } else if (sitemapUrls.has(path)) flag('noindex page listed in sitemap', path);

  for (const [tag] of main.matchAll(/<img\b[^>]*>/gi)) {
    imageStats.total++;
    const src = attr(tag, 'src');
    if (!/\salt[\s=>]/i.test(tag)) flag('image without alt attribute', path, src);
    if (/^https?:\/\/(www\.|api\.)?instacertify\.com\//i.test(src)) {
      imageStats.legacyHost++;
      flag('image still on legacy server', path, src);
    } else if (/\/storage\//.test(src) && /^https?:\/\/(localhost|127\.0\.0\.1)/.test(src)) {
      const file = join(mediaRoot, decodeURIComponent(src.split('/storage/')[1]));
      if (existsSync(file)) imageStats.migrated++;
      else { imageStats.missingFile++; flag('image file missing', path, src); }
    } else if (/^https?:/.test(src)) imageStats.external++;
    else imageStats.local++;
  }

  for (const [tag] of main.matchAll(/<a\b[^>]*href=["']\/[^"'#]*["'][^>]*>/gi)) {
    const href = norm(attr(tag, 'href'));
    if (href !== '/' && !pages.has(href) && !redirects.has(href) && !samePage.has(href) && !/\.(pdf|png|jpe?g|webp|svg|xml|txt)$/i.test(href)) {
      flag('internal link to missing page', path, href);
    }
  }
}

for (const [t, list] of titles) if (list.length > 1) flag('duplicate title', list.join(', '), t);
for (const [d, list] of descriptions) if (list.length > 1) flag('duplicate description', list.slice(0, 5).join(', ') + (list.length > 5 ? ` (+${list.length - 5})` : ''), d.slice(0, 60));

// ----------------------------------------------------------------- report
const report = {
  builtPages: pages.size,
  sitemapUrls: sitemapUrls.size,
  redirects: redirects.size,
  legacy: { total: legacy.length, servedAsPage: coverage.page.length, redirected: coverage.redirect.length, redirectToMissing: coverage.redirectToMissing, missing: coverage.missing },
  images: imageStats,
  issues,
};
writeFileSync('seo-report.json', JSON.stringify(report, null, 2));

console.log(`Built pages: ${pages.size} | sitemap URLs: ${sitemapUrls.size} | redirects: ${redirects.size}`);
console.log(`Legacy sitemap: ${legacy.length} URLs -> ${coverage.page.length} pages, ${coverage.redirect.length} redirects, ${coverage.redirectToMissing.length} redirects to missing pages, ${coverage.missing.length} MISSING`);
console.log(`Images in content: ${JSON.stringify(imageStats)}`);
for (const [type, list] of Object.entries(issues).sort((a, b) => b[1].length - a[1].length)) {
  console.log(`\n${type}: ${list.length}`);
  list.slice(0, 6).forEach((l) => console.log(`  ${l}`));
}
if (coverage.missing.length) {
  console.log(`\nMissing legacy URLs (first 40 of ${coverage.missing.length}):`);
  coverage.missing.slice(0, 40).forEach((p) => console.log(`  ${p}`));
}
console.log('\nFull report: seo-report.json');
