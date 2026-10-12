// Checks every link, button and asset reference in the static build.
// Usage: node scripts/link-check.mjs [--external] [--json=link-report.json] [--media-root=../backend/storage/app/public]
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const DIST = resolve('dist');
const args = process.argv.slice(2);
const checkExternal = args.includes('--external');
const reportFile = args.find((a) => a.startsWith('--json='))?.split('=')[1] ?? 'link-report.json';
const mediaRoot = resolve(args.find((a) => a.startsWith('--media-root='))?.split('=')[1] ?? '../backend/storage/app/public');

if (!existsSync(DIST)) {
  console.error('dist/ not found — run `npm run build` first.');
  process.exit(1);
}

const decode = (s) => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&#x27;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
const attr = (tag, name) => {
  const m = tag.match(new RegExp(`\\s${name}=(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
  return m ? decode(m.slice(1).find((v) => v !== undefined) ?? '') : null;
};
const norm = (p) => '/' + p.replace(/[?#].*$/, '').replace(/^\/+|\/+$/g, '');

const walk = (dir) => readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? walk(join(dir, f)) : [join(dir, f)]));
const files = walk(DIST);
const fileSet = new Set(files.map((f) => '/' + relative(DIST, f).replace(/\\/g, '/')));
const htmlFiles = files.filter((f) => f.endsWith('.html'));
const pathOf = (file) => norm('/' + relative(DIST, file).replace(/\\/g, '/').replace(/\.html$/, '').replace(/(^|\/)index$/, ''));
const pages = new Map(htmlFiles.map((f) => [pathOf(f), f]));

const redirects = new Map();
// "rewrite" lines: old URLs that show another page without redirecting.
const samePage = new Map();
if (existsSync(join(DIST, 'redirects.caddy'))) {
  for (const line of readFileSync(join(DIST, 'redirects.caddy'), 'utf8').split('\n')) {
    const [kind, from, to] = line.trim().split(/\s+/);
    if (from && to) (kind === 'rewrite' ? samePage : redirects).set(norm(from), to);
  }
}

const idCache = new Map();
const idsOf = (path) => {
  if (!idCache.has(path)) {
    const html = readFileSync(pages.get(path), 'utf8');
    idCache.set(path, new Set([...html.matchAll(/\s(?:id|name)=["']?([^"'\s>]+)/g)].map((m) => decode(m[1]))));
  }
  return idCache.get(path);
};

const issues = {};
const flag = (type, page, detail) => {
  const list = (issues[type] ??= new Map());
  const key = detail;
  if (!list.has(key)) list.set(key, new Set());
  list.get(key).add(page);
};
const external = new Map();

/** Resolves a same-site path; returns null when it is served. */
const missingReason = (path) => {
  const clean = norm(path);
  if (pages.has(clean) || fileSet.has(clean) || fileSet.has(decodeURIComponent(clean))) return null;
  if (samePage.has(clean)) return pages.has(norm(samePage.get(clean))) ? null : 'missing';
  if (redirects.has(clean)) {
    const to = redirects.get(clean);
    return /^https?:/.test(to) || pages.has(norm(to)) ? 'redirect' : 'redirect to missing page';
  }
  return 'missing';
};

const checkLocal = (page, raw, kind) => {
  const [pathPart, frag] = raw.split('#');
  const path = pathPart.split('?')[0];
  if (!path) return;
  if (/\.html$/.test(path) || (path.length > 1 && path.endsWith('/'))) flag('link answered by a redirect (.html or trailing slash)', page, raw);
  const reason = missingReason(path);
  if (reason === 'missing') flag(`${kind} to missing file/page`, page, raw);
  else if (reason === 'redirect to missing page') flag(`${kind} via redirect to missing page`, page, raw);
  else if (reason === 'redirect' && kind === 'link') flag('link goes through a redirect', page, `${raw} -> ${redirects.get(norm(path))}`);
  else if (!reason && frag && pages.has(norm(path)) && !idsOf(norm(path)).has(decodeURIComponent(frag))) flag('link to missing #anchor on another page', page, raw);
};

for (const [page, file] of pages) {
  const html = readFileSync(file, 'utf8');
  const body = html.replace(/<script\b(?![^>]*type="application\/ld\+json")[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<template\b[\s\S]*?<\/template>/gi, '');
  const ids = idsOf(page);

  for (const [tag] of body.matchAll(/<a\b[^>]*>/gi)) {
    const href = attr(tag, 'href');
    if (href === null) {
      if (!/\srole=|\sdata-|\sid=/i.test(tag)) flag('<a> without href (dead click)', page, tag.slice(0, 120));
      continue;
    }
    const h = href.trim();
    if (!h || h === '#' || /^javascript:/i.test(h)) {
      if (!/\sdata-[\w-]+/i.test(tag)) flag('empty or "#" href (dead click)', page, tag.slice(0, 120));
    } else if (h.startsWith('#')) {
      if (!ids.has(decodeURIComponent(h.slice(1)))) flag('link to missing #anchor on same page', page, h);
    } else if (/^tel:/i.test(h)) {
      const digits = h.slice(4).replace(/[^\d+]/g, '');
      if (!/^\+?\d{10,15}$/.test(digits) || /[^\d+\-\s().]/.test(decodeURIComponent(h.slice(4)))) flag('malformed tel: link', page, h);
    } else if (/^mailto:/i.test(h)) {
      const addr = decodeURIComponent(h.slice(7).split('?')[0]);
      if (!/^[^\s@,]+@[^\s@,]+\.[a-z]{2,}$/i.test(addr)) flag('malformed mailto: link', page, h);
    } else if (/^https?:\/\/(wa\.me|api\.whatsapp\.com)\//i.test(h)) {
      const num = new URL(h).pathname.replace(/\D/g, '') || new URL(h).searchParams.get('phone') || '';
      if (!/^\d{10,15}$/.test(num)) flag('malformed WhatsApp link', page, h);
    } else if (/^https?:\/\/(www\.)?instacertify\.com(\/|$)/i.test(h)) {
      checkLocal(page, new URL(h).pathname + new URL(h).hash, 'absolute self link');
    } else if (/^https?:\/\//i.test(h)) {
      if (!external.has(h)) external.set(h, new Set());
      external.get(h).add(page);
    } else if (h.startsWith('/')) {
      checkLocal(page, h, 'link');
    } else if (!/^(sms|whatsapp|viber|skype):/i.test(h)) {
      flag('relative or unknown link', page, h);
    }
  }

  for (const [tag] of body.matchAll(/<(?:img|source|script|link|video|audio|iframe)\b[^>]*>/gi)) {
    const refs = [attr(tag, 'src'), /<link/i.test(tag) && !/rel=["']?(canonical|alternate|preconnect|dns-prefetch)/i.test(tag) ? attr(tag, 'href') : null, ...(attr(tag, 'srcset') ?? '').split(',').map((s) => s.trim().split(/\s+/)[0])];
    for (const ref of refs) {
      if (!ref || ref.startsWith('data:')) continue;
      if (ref.startsWith('/') && !ref.startsWith('//')) {
        if (missingReason(ref.split('?')[0]) === 'missing') flag('asset reference to missing file', page, ref);
      } else if (/^https?:\/\/(localhost|127\.0\.0\.1)[^/]*\/storage\//.test(ref)) {
        // Local builds point CMS uploads at the local API; the file must still exist there.
        if (!existsSync(join(mediaRoot, decodeURIComponent(ref.split('/storage/')[1].split('?')[0])))) flag('CMS image file missing', page, ref);
      }
    }
  }

  for (const [tag, inner] of body.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/gi)) {
    const type = attr(tag, 'type');
    if (type === 'submit' || type === null) {
      // Submit buttons are dead only outside a form.
      const before = body.slice(0, body.indexOf(tag));
      const open = before.lastIndexOf('<form');
      const close = before.lastIndexOf('</form>');
      if (open > close || attr(tag, 'form')) continue;
      if (type === 'submit') flag('submit button outside a form', page, tag.slice(0, 120));
    }
    if (!/\s(data-[\w-]+|id|aria-controls|aria-expanded|popovertarget|commandfor|command|onclick|form|name)(=|\s|>)/i.test(tag)) {
      flag('button with no hook (possible dead click)', page, (tag + inner.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').slice(0, 140));
    }
  }

  for (const [tag] of body.matchAll(/<form\b[^>]*>/gi)) {
    const action = attr(tag, 'action');
    if (action && action.startsWith('/')) checkLocal(page, action, 'form action');
  }
}

// Links inside the JSON indexes that page scripts render (search, fee finder).
for (const file of files.filter((f) => f.endsWith('.json'))) {
  const rel = '/' + relative(DIST, file).replace(/\\/g, '/');
  const json = readFileSync(file, 'utf8');
  for (const m of json.matchAll(/"(?:url|path|href|link)"\s*:\s*"(\/[^"]*)"/g)) checkLocal(`${rel} (index)`, m[1], 'link');
}

// ---------------------------------------------------------- external links
const externalBad = [];
if (checkExternal && external.size) {
  const urls = [...external.keys()];
  const blocked = /linkedin\.com|instagram\.com|facebook\.com|x\.com|twitter\.com/i;
  let i = 0;
  const fetchOne = async (url, method) => {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 20000);
    try {
      const res = await fetch(url, { method, redirect: 'follow', signal: ctrl.signal, headers: { 'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36', accept: 'text/html,*/*' } });
      res.body?.cancel?.();
      return res.status;
    } catch (e) {
      return e.name === 'AbortError' ? 'timeout' : e.cause?.code ?? e.message;
    } finally {
      clearTimeout(timer);
    }
  };
  await Promise.all(Array.from({ length: 10 }, async () => {
    while (i < urls.length) {
      const url = urls[i++];
      let status = await fetchOne(url, 'HEAD');
      if (status !== 200 && !(typeof status === 'number' && status < 400)) status = await fetchOne(url, 'GET');
      const ok = typeof status === 'number' && (status < 400 || (blocked.test(url) && [403, 429, 999].includes(status)));
      if (!ok) externalBad.push({ url, status, pages: [...external.get(url)].slice(0, 5), count: external.get(url).size });
    }
  }));
}

// ----------------------------------------------------------------- report
const out = Object.fromEntries(Object.entries(issues).map(([type, map]) => [type, [...map].map(([detail, pagesSet]) => ({ detail, count: pagesSet.size, pages: [...pagesSet].slice(0, 5) }))]));
writeFileSync(reportFile, JSON.stringify({ pages: pages.size, externalLinks: external.size, issues: out, externalBad }, null, 2));

console.log(`Pages: ${pages.size} | redirects: ${redirects.size} | unique external links: ${external.size}${checkExternal ? '' : ' (not fetched; pass --external)'}`);
for (const [type, list] of Object.entries(out).sort((a, b) => b[1].length - a[1].length)) {
  console.log(`\n${type}: ${list.length} unique`);
  list.slice(0, 8).forEach((l) => console.log(`  ${l.detail}  [${l.count} page(s), e.g. ${l.pages[0]}]`));
}
if (checkExternal) {
  console.log(`\nBroken external links: ${externalBad.length}`);
  externalBad.slice(0, 40).forEach((b) => console.log(`  ${b.status}  ${b.url}  [${b.count} page(s), e.g. ${b.pages[0]}]`));
}
console.log(`\nFull report: ${reportFile}`);
