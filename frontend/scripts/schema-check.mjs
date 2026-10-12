// Checks the structured data (JSON-LD) on every built page against Google's rich result requirements.
// Usage: node scripts/schema-check.mjs [--json=schema-report.json]
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const DIST = resolve('dist');
const jsonOut = process.argv.find((a) => a.startsWith('--json='))?.split('=')[1];
const decode = (s) =>
  s
    .replace(/&nbsp;/g, ' ')
    .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n)))
    .replace(/&#x([\da-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16)))
    .replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');
const squash = (s) => decode(String(s).replace(/<[^>]+>/g, ' ')).replace(/[\s\u00a0]+/g, ' ').trim().toLowerCase();

if (!existsSync(DIST)) {
  console.error('dist/ not found — run `npm run build` first.');
  process.exit(1);
}

const walk = (dir) => readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? walk(join(dir, f)) : [join(dir, f)]));
const htmlFiles = walk(DIST).filter((f) => f.endsWith('.html') && !f.includes('_astro'));
const pathOf = (file) => '/' + relative(DIST, file).replace(/\\/g, '/').replace(/\.html$/, '').replace(/(^|\/)index$/, '');

const errors = {};
const warnings = {};
const types = {};
const error = (type, page, detail = '') => (errors[type] ??= []).push(detail ? `${page} — ${detail}` : page);
const warn = (type, page, detail = '') => (warnings[type] ??= []).push(detail ? `${page} — ${detail}` : page);

const ISO = /^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/;
const absolute = (v) => typeof v === 'string' && /^https?:\/\/[^/]+/.test(v);
const text = (v) => typeof v === 'string' && v.trim() !== '';
const typeOf = (node) => [].concat(node?.['@type'] ?? []);
const is = (node, ...names) => typeOf(node).some((t) => names.includes(t));
const URL_KEYS = new Set(['url', 'item', 'logo', 'image', 'sameAs', 'hasMap', '@id', 'contentUrl', 'thumbnailUrl']);

/** Every property value that looks broken: "undefined", empty strings, relative URLs. */
function scanValues(node, page, at = '') {
  if (Array.isArray(node)) return node.forEach((v, i) => scanValues(v, page, `${at}[${i}]`));
  if (!node || typeof node !== 'object') return;
  for (const [key, value] of Object.entries(node)) {
    const where = at ? `${at}.${key}` : key;
    if (typeof value === 'string') {
      if (/^(undefined|null|NaN|\[object Object\])$/.test(value) || /\bundefined\b/.test(value) && URL_KEYS.has(key)) error('broken value', page, `${where}: "${value}"`);
      else if (value.trim() === '') error('empty value', page, where);
      else if (URL_KEYS.has(key) && key !== '@id' && !absolute(value)) error('relative URL (must start with https://)', page, `${where}: ${value}`);
      else if (key === '@id' && !absolute(value) && !value.startsWith('#')) error('relative @id', page, `${where}: ${value}`);
    } else if (value === null) error('null value', page, where);
    else scanValues(value, page, where);
  }
}

const dateOk = (v) => typeof v === 'string' && ISO.test(v) && !Number.isNaN(Date.parse(v));

function checkDate(node, key, page, label, required = true) {
  const value = node[key];
  if (value === undefined) return required ? error(`${label}: missing ${key}`, page) : undefined;
  if (!dateOk(value)) return error(`${label}: ${key} is not an ISO 8601 date`, page, String(value));
  if (value.includes('T') && !/(Z|[+-]\d{2}:?\d{2})$/.test(value)) warn(`${label}: ${key} has no time zone`, page, value);
}

function checkImage(image, page, label, required) {
  const list = [].concat(image ?? []);
  if (!list.length) return required ? error(`${label}: missing image`, page) : warn(`${label}: no image (recommended)`, page);
  for (const img of list) {
    const url = typeof img === 'string' ? img : img?.url ?? img?.contentUrl;
    if (!absolute(url)) error(`${label}: image URL is not absolute`, page, String(url));
  }
}

const checks = {
  BreadcrumbList(node, page) {
    const items = node.itemListElement ?? [];
    if (!items.length) return error('Breadcrumb: no items', page);
    items.forEach((li, i) => {
      if (li.position !== i + 1) error('Breadcrumb: positions must run 1, 2, 3…', page, `item ${i + 1} has position ${li.position}`);
      if (!text(li.name)) error('Breadcrumb: item without a name', page, `item ${i + 1}`);
      const item = typeof li.item === 'string' ? li.item : li.item?.['@id'];
      if (i < items.length - 1 && !absolute(item)) error('Breadcrumb: only the last item may leave out its URL', page, `item ${i + 1}`);
    });
  },

  FAQPage(node, page, body) {
    const questions = [].concat(node.mainEntity ?? []);
    if (!questions.length) return error('FAQ: no questions', page);
    const seen = new Set();
    for (const q of questions) {
      if (!is(q, 'Question') || !text(q.name)) error('FAQ: question without text', page);
      if (!text(q.acceptedAnswer?.text)) error('FAQ: question without an answer', page, q.name);
      const key = squash(q.name ?? '');
      if (seen.has(key)) error('FAQ: same question twice', page, q.name);
      seen.add(key);
      // Google requires the FAQ to be visible on the page.
      if (key && !body.includes(key)) error('FAQ: question in the markup is not shown on the page', page, q.name);
    }
  },

  Article(node, page, _body, label = 'Article') {
    if (!text(node.headline)) error(`${label}: missing headline`, page);
    checkImage(node.image, page, label, false);
    checkDate(node, 'datePublished', page, label);
    checkDate(node, 'dateModified', page, label, false);
    if (dateOk(node.datePublished) && dateOk(node.dateModified) && Date.parse(node.dateModified) < Date.parse(node.datePublished)) {
      error(`${label}: dateModified is before datePublished`, page);
    }
    const authors = [].concat(node.author ?? []);
    if (!authors.length) error(`${label}: missing author`, page);
    for (const a of authors) {
      if (!text(a?.name)) error(`${label}: author without a name`, page);
      if (!absolute(a?.url)) warn(`${label}: author without a profile URL (recommended)`, page);
    }
  },

  Event(node, page) {
    if (!text(node.name)) error('Event: missing name', page);
    checkDate(node, 'startDate', page, 'Event');
    checkDate(node, 'endDate', page, 'Event', false);
    if (dateOk(node.startDate) && dateOk(node.endDate) && Date.parse(node.endDate) < Date.parse(node.startDate)) error('Event: ends before it starts', page);
    const locations = [].concat(node.location ?? []);
    if (!locations.length) error('Event: missing location', page);
    for (const loc of locations) {
      if (is(loc, 'VirtualLocation')) {
        if (!absolute(loc.url)) error('Event: online location without a URL', page);
      } else if (!loc?.address || (typeof loc.address === 'object' && !text(loc.address.addressLocality) && !text(loc.address.streetAddress) && !text(loc.address.name))) {
        error('Event: location without an address', page);
      }
    }
    if (!node.eventStatus) warn('Event: no eventStatus (recommended)', page);
    if (!node.eventAttendanceMode) warn('Event: no eventAttendanceMode (recommended)', page);
    if (!text(node.description)) warn('Event: no description (recommended)', page);
    checkImage(node.image, page, 'Event', false);
  },

  JobPosting(node, page, _body, _label, indexable) {
    if (!text(node.title)) error('Job: missing title', page);
    if (!text(node.description)) error('Job: missing description', page);
    checkDate(node, 'datePosted', page, 'Job');
    checkDate(node, 'validThrough', page, 'Job', false);
    if (dateOk(node.validThrough) && Date.parse(node.validThrough) < Date.now() && indexable) error('Job: expired (validThrough has passed) but the page is still indexable', page);
    if (!text(node.hiringOrganization?.name)) error('Job: missing hiringOrganization.name', page);
    const remote = node.jobLocationType === 'TELECOMMUTE';
    if (remote && !node.applicantLocationRequirements) error('Job: remote job without applicantLocationRequirements', page);
    if (!remote) {
      const locations = [].concat(node.jobLocation ?? []);
      if (!locations.length) error('Job: missing jobLocation', page);
      for (const loc of locations) if (!text(loc?.address?.addressCountry)) error('Job: jobLocation without addressCountry', page);
    }
    if (!node.employmentType) warn('Job: no employmentType (recommended)', page);
    if (!node.validThrough) warn('Job: no validThrough (recommended)', page);
  },

  LocalBusiness(node, page) {
    if (!text(node.name)) error('Business: missing name', page);
    const a = node.address;
    if (!a) return error('Business: missing address', page);
    if (!text(a.streetAddress)) error('Business: address without streetAddress', page);
    if (!text(a.addressLocality)) warn('Business: address without addressLocality (recommended)', page);
    if (!text(a.addressCountry)) error('Business: address without addressCountry', page);
    if (!text(node.telephone)) warn('Business: no telephone (recommended)', page);
  },

  Service(node, page) {
    if (!text(node.name)) error('Service: missing name', page);
    if (!node.provider) warn('Service: no provider', page);
  },

  ItemList(node, page) {
    const items = node.itemListElement ?? [];
    items.forEach((li, i) => {
      if (li.position !== i + 1) error('ItemList: positions must run 1, 2, 3…', page);
      if (li.url !== undefined && !absolute(li.url)) error('ItemList: item URL is not absolute', page, li.url);
    });
  },

  ProfilePage(node, page) {
    if (!text(node.mainEntity?.name)) error('Profile: mainEntity without a name', page);
  },

  WebSite(node, page) {
    if (!text(node.name) || !absolute(node.url)) error('WebSite: needs name and url', page);
  },
};

const BUSINESS = ['LocalBusiness', 'ProfessionalService', 'Organization'];
const ARTICLES = ['Article', 'BlogPosting', 'NewsArticle'];

let withSchema = 0;
for (const file of htmlFiles) {
  const page = pathOf(file);
  const html = readFileSync(file, 'utf8');
  const indexable = !/<meta name="robots" content="noindex/i.test(html);
  const blocks = [...html.matchAll(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/gi)].map((m) => m[1]);
  if (!blocks.length) {
    if (indexable && page !== '/404') warn('page has no structured data', page);
    continue;
  }
  withSchema++;
  const body = squash(html.replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<style[\s\S]*?<\/style>/gi, ' '));

  const nodes = [];
  for (const raw of blocks) {
    let data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      error('JSON-LD does not parse', page, e.message);
      continue;
    }
    for (const node of [].concat(data)) {
      if (node?.['@context'] !== 'https://schema.org' && node?.['@context'] !== 'http://schema.org') error('missing @context', page, typeOf(node).join());
      nodes.push(...(node['@graph'] ?? [node]));
    }
  }

  const perType = {};
  for (const node of nodes) {
    scanValues(node, page);
    for (const t of typeOf(node)) {
      types[t] = (types[t] ?? 0) + 1;
      perType[t] = (perType[t] ?? 0) + 1;
    }
    if (is(node, 'Review', 'AggregateRating') || node.aggregateRating || node.review) {
      error('review or rating markup (self-serving reviews are against Google policy)', page);
    }
    if (is(node, ...ARTICLES)) checks.Article(node, page, body, typeOf(node)[0], indexable);
    else if (is(node, ...BUSINESS)) checks.LocalBusiness(node, page);
    else {
      const check = checks[typeOf(node).find((t) => checks[t])];
      check?.(node, page, body, typeOf(node)[0], indexable);
    }
  }
  for (const t of ['FAQPage', 'BreadcrumbList']) if (perType[t] > 1) error(`${t} appears ${perType[t]} times on one page`, page);
  if (indexable && !perType.BreadcrumbList && /<nav aria-label="Breadcrumb"/.test(html)) warn('breadcrumb shown on the page but not in the markup', page);
}

const count = (o) => Object.values(o).reduce((n, list) => n + list.length, 0);
console.log(`Pages: ${htmlFiles.length} | with structured data: ${withSchema}`);
console.log('Types: ' + Object.entries(types).sort((a, b) => b[1] - a[1]).map(([t, n]) => `${t} ${n}`).join(', '));
for (const [label, group] of [['Errors', errors], ['Warnings', warnings]]) {
  console.log(`\n${label}: ${count(group)}`);
  for (const [type, list] of Object.entries(group).sort((a, b) => b[1].length - a[1].length)) {
    console.log(`  ${type} (${list.length})`);
    for (const line of list.slice(0, 5)) console.log(`    ${line}`);
    if (list.length > 5) console.log(`    … ${list.length - 5} more`);
  }
}
if (jsonOut) writeFileSync(jsonOut, JSON.stringify({ types, errors, warnings }, null, 2));
process.exit(count(errors) ? 1 : 0);
