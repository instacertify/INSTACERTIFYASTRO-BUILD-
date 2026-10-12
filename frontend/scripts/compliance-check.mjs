// Checks the static build for SEO basics, consent, form and advertising-policy rules on every page.
// Usage: node scripts/compliance-check.mjs (after `npm run build`); exits 1 when anything is found.
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const DIST = resolve('dist');
if (!existsSync(DIST)) {
  console.error('dist/ not found — run `npm run build` first.');
  process.exit(1);
}
const walk = (d) => readdirSync(d).flatMap((n) => (statSync(join(d, n)).isDirectory() ? walk(join(d, n)) : [join(d, n)]));
const pages = walk(DIST).filter((f) => f.endsWith('.html'));
const urlOf = (f) => '/' + relative(DIST, f).replaceAll('\\', '/').replace(/(^|\/)index\.html$/, '').replace(/\.html$/, '');

const issues = new Map();
const flag = (rule, url, detail = '') => {
  const list = issues.get(rule) ?? [];
  list.push(detail ? `${url} (${detail})` : url);
  issues.set(rule, list);
};

const TRACKERS = /googletagmanager\.com|google-analytics\.com|gtag\/js|connect\.facebook\.net|clarity\.ms|snap\.licdn\.com|matomo\.js|static\.hotjar/i;
const titles = new Map();
const descriptions = new Map();
const indexable = new Set();
let ratings = 0;

for (const file of pages) {
  const html = readFileSync(file, 'utf8');
  const url = urlOf(file);
  if (html.includes('http-equiv="refresh"')) continue;
  const head = html.slice(0, html.indexOf('</head>'));
  const canonical = head.match(/<link rel="canonical" href="https?:\/\/[^/"]+([^"]*)"/i)?.[1] || '/';
  // Legacy URLs that point their canonical elsewhere are duplicates by design, not indexable pages.
  const noindex = /<meta name="robots" content="[^"]*noindex/i.test(head) || canonical.replace(/\/$/, '') !== url.replace(/\/$/, '');
  if (!noindex) indexable.add(url === '/' ? '/' : url.replace(/\/$/, ''));

  if (!/<html[^>]*\slang="[a-z]{2}/i.test(html)) flag('missing <html lang>', url);
  if (!/<meta name="viewport"/i.test(head)) flag('missing viewport meta', url);
  const title = head.match(/<title>([^<]*)<\/title>/i)?.[1]?.trim();
  if (!title) flag('missing <title>', url);
  else if (!noindex) titles.set(title, [...(titles.get(title) ?? []), url]);
  const desc = head.match(/<meta name="description" content="([^"]*)"/i)?.[1]?.trim();
  if (!desc) flag('missing meta description', url);
  else if (!noindex) descriptions.set(desc, [...(descriptions.get(desc) ?? []), url]);
  if (!/<link rel="canonical"/i.test(head)) flag('missing canonical', url);
  const h1 = (html.match(/<h1[\s>]/gi) ?? []).length;
  if (h1 !== 1 && url !== '/404') flag('not exactly one <h1>', url, `${h1}`);

  // Third-party trackers may only be injected by the consent script after opt-in.
  for (const tag of html.match(/<script\b[^>]*\bsrc="[^"]+"[^>]*>/gi) ?? []) if (TRACKERS.test(tag)) flag('tracker loaded before consent', url, tag.slice(0, 90));
  for (const tag of html.match(/<(?:img|iframe)\b[^>]*>/gi) ?? []) {
    if (/<img/i.test(tag) && !/\salt(?=[\s=>/])/i.test(tag)) flag('<img> without alt', url, tag.match(/src="([^"]+)"/)?.[1]?.split('/').pop());
    if (/<iframe/i.test(tag) && !/\stitle=/i.test(tag)) flag('<iframe> without title', url);
    if (/\ssrc="http:\/\/(?!localhost|127\.0\.0\.1)/i.test(tag)) flag('insecure http:// resource', url, tag.match(/src="([^"]+)"/)?.[1]);
  }
  for (const tag of html.match(/<a\b[^>]*target="_blank"[^>]*>/gi) ?? []) if (!/rel="[^"]*noopener/i.test(tag)) flag('target=_blank without rel=noopener', url, tag.match(/href="([^"]+)"/)?.[1]);

  if (!html.includes('id="cookie-consent"')) flag('no cookie consent banner', url);
  if (!/not a government (body|agency|department)/i.test(html)) flag('no "not a government body" disclaimer', url);

  for (const form of html.match(/<form\b[^>]*data-lead-form[\s\S]*?<\/form>/gi) ?? []) {
    const kind = form.match(/data-lead-form="([^"]*)"/)?.[1];
    if (!/href="\/privacy-policy"/i.test(form)) flag('lead form without Privacy Policy link', url, kind);
    // Newsletter sign-up is itself the consent (double opt-in); enquiry forms need an explicit box.
    if (kind !== 'newsletter' && !/type="checkbox"[^>]*required|required[^>]*type="checkbox"/i.test(form)) flag('lead form without required consent checkbox', url, kind);
    if (/type="checkbox"[^>]*\schecked/i.test(form)) flag('lead form with a pre-ticked checkbox', url);
  }

  for (const block of html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/gi) ?? []) {
    const json = block.replace(/^<script[^>]*>|<\/script>$/gi, '');
    try {
      const data = JSON.parse(json);
      if (/"(AggregateRating|Review)"/.test(JSON.stringify(data))) ratings++;
    } catch (e) {
      flag('invalid JSON-LD', url, e.message.slice(0, 60));
    }
  }
}

// The BIS ad landing page must not mention FMCS or show consulting prices.
const bis = join(DIST, 'lp', 'bis-certification.html');
if (existsSync(bis)) {
  const text = readFileSync(bis, 'utf8').replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, '');
  if (/FMCS/i.test(text)) flag('BIS landing page mentions FMCS', '/lp/bis-certification');
  if (/9,999|20,999/.test(text)) flag('BIS landing page shows consulting price', '/lp/bis-certification');
}

for (const [t, urls] of titles) if (urls.length > 1) flag('duplicate title on indexable pages', urls.slice(0, 3).join(', '), `${urls.length}x "${t.slice(0, 50)}"`);
for (const [d, urls] of descriptions) if (urls.length > 1) flag('duplicate description on indexable pages', urls.slice(0, 3).join(', '), `${urls.length}x`);

const sitemapFiles = walk(DIST).filter((f) => /sitemap[^/\\]*\.xml$/.test(f));
const inSitemap = new Set();
for (const f of sitemapFiles) for (const m of readFileSync(f, 'utf8').matchAll(/<loc>https?:\/\/[^/<]+(\/[^<]*)<\/loc>/g)) if (!m[1].endsWith('.xml')) inSitemap.add(m[1] === '/' ? '/' : m[1].replace(/\/$/, ''));
for (const u of inSitemap) if (!indexable.has(u)) flag('sitemap lists a noindex/missing page', u);
const missing = [...indexable].filter((u) => !inSitemap.has(u) && u !== '/404' && !/\/page\/\d+$/.test(u));
if (missing.length) flag('indexable page not in sitemap', missing.slice(0, 5).join(', '), `${missing.length} pages`);
if (!existsSync(join(DIST, 'robots.txt'))) flag('robots.txt missing', '/');

console.log(`${pages.length} pages, ${indexable.size} indexable, ${inSitemap.size} in sitemap (${sitemapFiles.length} files), ${ratings} pages with rating/review markup`);
if (!issues.size) console.log('No issues.');
for (const [rule, list] of issues) console.log(`\n${rule}: ${list.length}\n  ${list.slice(0, 6).join('\n  ')}`);
process.exitCode = issues.size ? 1 : 0;
