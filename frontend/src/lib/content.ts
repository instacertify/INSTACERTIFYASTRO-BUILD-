import siteJson from '../../../content/site.json';
import homeJson from '../../../content/home.json';
import catalogJson from '../../../content/catalog.json';
import industriesJson from '../../../content/industries.json';
import testimonialsJson from '../../../content/testimonials.json';
import type {
  Author,
  EventItem,
  Home,
  Industry,
  LandingPageRecord,
  PageRecord,
  Post,
  PostType,
  Service,
  ServiceDetail,
  Site,
  StandardCategory,
  TestCategory,
  Testimonial,
  Vacancy,
} from './types';

const API_URL = (import.meta.env.API_URL ?? '').replace(/\/$/, '');

const detailFiles = import.meta.glob<{ default: ServiceDetail & { path: string } }>(
  '../../../content/services/*.json',
  { eager: true },
);

const cache = new Map<string, { at: number; data: Promise<unknown> }>();
// Memoised for the whole build; in dev and on the preview server, expire so admin edits show on reload.
const CACHE_MS = import.meta.env.DEV ? 30_000 : import.meta.env.PREVIEW_SERVER ? 60_000 : Infinity;

/** Endpoint → change applied to its response; set by the preview server while it renders one admin draft. */
let overrides: Map<string, (data: unknown) => unknown> | null = null;

export function setOverrides(next: typeof overrides) {
  overrides = next;
}

/** Build-time fetch from the Laravel API; returns null so callers can fall back to local content. */
function api<T>(endpoint: string): Promise<T | null> {
  const override = overrides?.get(endpoint);
  if (override) return fetchApi<T>(endpoint).then((data) => override(data) as T | null);
  return fetchApi<T>(endpoint);
}

function fetchApi<T>(endpoint: string): Promise<T | null> {
  if (!API_URL) return Promise.resolve(null);
  const hit = cache.get(endpoint);
  if (hit && Date.now() - hit.at < CACHE_MS) return hit.data as Promise<T | null>;

  const data = fetchWithRetry<T>(endpoint);
  cache.set(endpoint, { at: Date.now(), data });
  return data;
}

/** The build fires hundreds of requests at once; a dropped one would otherwise ship as a blank page. */
async function fetchWithRetry<T>(endpoint: string, attempt = 1): Promise<T | null> {
  let reason: string;
  try {
    const r = await fetch(`${API_URL}/api/v1/${endpoint}`, { headers: { Accept: 'application/json' } });
    if (r.ok) return (await r.json()).data as T;
    if (r.status < 500 && r.status !== 429) return null;
    reason = `HTTP ${r.status}`;
  } catch (e) {
    reason = e instanceof Error ? e.message : String(e);
  }
  if (attempt >= 4) {
    // The publishing server must not put a site live with pages silently built from the fallback content.
    if (process.env.REQUIRE_API) throw new Error(`[api] ${endpoint} failed after ${attempt} attempts: ${reason}`);
    console.warn(`[api] ${endpoint} failed after ${attempt} attempts: ${reason}`);
    return null;
  }
  await new Promise((done) => setTimeout(done, 1000 * attempt));
  return fetchWithRetry<T>(endpoint, attempt + 1);
}

function localDetail(path: string): ServiceDetail | null {
  const match = Object.values(detailFiles).find((m) => m.default.path === path);
  return match ? match.default : null;
}

function localService(item: (typeof catalogJson)[number]): Service {
  return {
    path: item.path,
    url: `/${item.path}`,
    slug: item.path.split('/').pop()!,
    title: item.title,
    kind: item.kind as Service['kind'],
    region: (item as { region?: string }).region ?? null,
    group: (item as { group?: string }).group ?? null,
    summary: item.summary,
    featured: (item as { featured?: boolean }).featured ?? false,
    detail: localDetail(item.path),
  };
}

export async function getSite(): Promise<Site> {
  return (await api<Site>('site')) ?? (siteJson as Site);
}

/** Home page content: saved admin settings layered over content/home.json, section by section. */
export async function getHome(): Promise<Home> {
  const saved = (await getSite()).home ?? {};
  const defaults = homeJson as unknown as Home;
  const home = { ...defaults } as Record<string, unknown>;
  for (const [key, value] of Object.entries(saved)) {
    const base = home[key];
    home[key] = value && base && typeof value === 'object' && !Array.isArray(value) ? { ...(base as object), ...value } : value;
  }
  return home as unknown as Home;
}

/** Industries on the Product testing page; empty when built without the API (the page then lists every test). */
export async function getTestCategories(): Promise<TestCategory[]> {
  return (await getSite()).taxonomy?.testCategories ?? [];
}

const faqKey = (q: string) => q.toLowerCase().replace(/['"`‘’“”]/g, '').replace(/\s+/g, ' ').replace(/[\s?.!]+$/, '').trim();

/**
 * Some imported pages list the same question twice (once with different capitals or quotes).
 * Shown once, so the page reads cleanly and Google accepts the FAQ markup.
 */
export function uniqueFaqs<T extends { q: string }>(faqs: T[] | null | undefined): T[] {
  const seen = new Set<string>();
  return (faqs ?? []).filter((f) => {
    const key = faqKey(f.q ?? '');
    if (!key || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

const cleaned = new WeakMap<object, unknown>();
function cleanOnce<T extends object>(data: T, clean: (data: T) => T): T {
  if (!cleaned.has(data)) cleaned.set(data, clean(data));
  return cleaned.get(data) as T;
}

export async function getServices(): Promise<Service[]> {
  const remote = await api<Service[]>('services?detail=1');
  if (!remote) return catalogJson.map(localService);
  return cleanOnce(remote, (list) =>
    list.map((s) => (s.detail?.faqs?.length ? { ...s, detail: { ...s.detail, faqs: uniqueFaqs(s.detail.faqs) } } : s)),
  );
}

export async function getService(path: string): Promise<Service | null> {
  return (await getServices()).find((s) => s.path === path) ?? null;
}

export async function getIndustries(): Promise<Industry[]> {
  const remote = await api<Industry[]>('industries');
  if (remote) return remote;

  const services = await getServices();
  return industriesJson.map(({ services: paths, ...i }) => {
    const linked = paths.map((p) => services.find((s) => s.path === p)).filter((s): s is Service => Boolean(s));
    return {
      ...i,
      url: `/industries/${i.slug}`,
      certification: { services: linked.filter((s) => s.kind !== 'testing') },
      testing: { services: linked.filter((s) => s.kind === 'testing'), categories: [] },
    };
  });
}

export async function getTestimonials(): Promise<Testimonial[]> {
  return (await api<Testimonial[]>('testimonials')) ?? testimonialsJson;
}

/* Editorial content only exists in the backend; without API_URL these return empty lists and the pages are skipped. */

export const POST_TYPES: Record<PostType, { label: string; plural: string; prefix: string; listPath: string; blurb: string; seoTitle: string }> = {
  blog: { label: 'Blog', plural: 'Blogs', prefix: 'resources/blogs', listPath: 'resources/blogs', blurb: 'Practical guides to Indian and global certification, written by our compliance team.', seoTitle: 'Certification blog: BIS, WPC & product testing guides' },
  news: { label: 'News', plural: 'Regulatory news', prefix: 'news-item', listPath: 'resources/news', blurb: 'New Quality Control Orders, BIS notifications and deadlines that affect your products.', seoTitle: 'Regulatory news: BIS, QCO & certification updates' },
  audit: { label: 'Audit', plural: 'Audits & inspections', prefix: 'resources/audits', listPath: 'resources/audits', blurb: 'Factory audits, pre-shipment inspections and supplier assessments explained.', seoTitle: 'Audits & inspections: BIS factory audit case studies' },
  update: { label: 'Update', plural: 'Regulatory updates', prefix: 'resources/update', listPath: 'resources/update', blurb: 'Short updates on changing BIS, WPC and QCO rules, fees and procedures that affect your products.', seoTitle: 'Regulatory updates: rule, fee & procedure changes' },
};

export const POSTS_PER_PAGE = 24;

/** Categories used by a list of posts, biggest first, with the catch-all "Others" last. */
/** Industries present in a list of posts or events, A–Z, with how many items each has. */
export function industriesOf(items: { industry?: { name: string; slug: string } | null }[]): { name: string; slug: string; count: number }[] {
  const counts = new Map<string, { name: string; slug: string; count: number }>();
  items.forEach(({ industry: c }) => c && counts.set(c.slug, { name: c.name, slug: c.slug, count: (counts.get(c.slug)?.count ?? 0) + 1 }));
  return [...counts.values()].sort((a, b) => a.name.localeCompare(b.name));
}

/** "Factory audit · BIS ISI mark" — what kind of work an audit post is and which certifications it covers. */
export function workTag(post: Pick<Post, 'workTypeLabel' | 'schemes'>): string {
  return [post.workTypeLabel, ...(post.schemes ?? []).map((s) => s.name)].filter(Boolean).join(' · ');
}

/** Certification tags used by a list of audits or events, most used first, with how many items each has. */
export function schemesOf(items: { schemes?: { name: string; slug: string }[] }[]): { name: string; slug: string; count: number }[] {
  const counts = new Map<string, { name: string; slug: string; count: number }>();
  items.forEach(({ schemes }) => schemes?.forEach((s) => counts.set(s.slug, { ...s, count: (counts.get(s.slug)?.count ?? 0) + 1 })));
  return [...counts.values()].sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
}

export async function getPosts(type: PostType): Promise<Post[]> {
  const posts = await api<Post[]>(`posts?type=${type}&per_page=500&content=1`);
  return posts ? cleanOnce(posts, (list) => list.map((p) => (p.faqs?.length ? { ...p, faqs: uniqueFaqs(p.faqs) } : p))) : [];
}

/** Newest posts of one type, without their body text. */
export async function getLatestPosts(type: PostType, limit: number): Promise<Post[]> {
  return (await api<Post[]>(`posts?type=${type}&per_page=${limit}`)) ?? [];
}

export async function getEvents(): Promise<EventItem[]> {
  return (await api<EventItem[]>('events')) ?? [];
}

export async function getEvent(slug: string): Promise<EventItem | null> {
  return api<EventItem>(`events/${slug}`);
}

export async function getVacancies(): Promise<Vacancy[]> {
  return (await api<Vacancy[]>('vacancies')) ?? [];
}

export async function getStandardCategories(): Promise<StandardCategory[]> {
  const categories = await api<StandardCategory[]>('standards?detail=1');
  return categories
    ? cleanOnce(categories, (list) => list.map((c) => ({ ...c, standards: c.standards.map((s) => (s.faqs?.length ? { ...s, faqs: uniqueFaqs(s.faqs) } : s)) })))
    : [];
}

export async function getPages(): Promise<PageRecord[]> {
  return (await api<PageRecord[]>('pages')) ?? [];
}

export async function getPage(path: string): Promise<PageRecord | null> {
  return api<PageRecord>(`pages/${path}`);
}

export async function getLandingPages(): Promise<LandingPageRecord[]> {
  return (await api<LandingPageRecord[]>('landing-pages')) ?? [];
}

export async function getLandingPage(path: string): Promise<LandingPageRecord | null> {
  return api<LandingPageRecord>(`landing-pages/${path}`);
}

export async function getAuthor(slug: string): Promise<(Author & { posts: Post[]; seo?: Post['seo'] }) | null> {
  return api(`authors/${slug}`);
}

export async function getFaqs(): Promise<{ question: string; answer: string; category: string }[]> {
  return (await api<{ question: string; answer: string; category: string }[]>('faqs')) ?? [];
}

export async function getRedirects(): Promise<{ from: string; to: string; status: number }[]> {
  return (await api<{ from: string; to: string; status: number }[]>('redirects')) ?? [];
}

const redirectTargets = new WeakMap<object, Map<string, string>>();
const trimPath = (path: string) => path.replace(/\/+$/, '') || '/';

/**
 * Returns a function that points an internal link straight at the page the admin's Redirects
 * screen sends it to, in the site's one URL form (relative, no trailing slash), so saved content
 * never links through a redirect or to a retired URL.
 */
export async function linkResolver(): Promise<(href: string | null | undefined) => string | undefined> {
  const list = await getRedirects();
  const map = redirectTargets.get(list) ?? new Map(list.filter((r) => r.from !== r.to).map((r) => [trimPath(r.from), r.to]));
  redirectTargets.set(list, map);
  return (href) => {
    if (!href) return undefined;
    const match = href.match(/^(?:https?:\/\/(?:www\.)?instacertify\.com)?(\/(?!\/)[^#?]*)(.*)$/i);
    if (!match) return href;
    const path = trimPath(match[1]);
    return `${map.get(path) ?? path}${match[2]}`;
  };
}

export function formatDate(iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }): string {
  return iso ? new Date(iso).toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', ...opts }) : '';
}

/** "Hanover (Hannover) / Germany / Europe・CIS" → "Hanover (Hannover), Germany". */
export function eventPlace(e: Pick<EventItem, 'location'>): string {
  return (e.location ?? '').split('/').map((p) => p.trim()).filter(Boolean).slice(0, 2).join(', ');
}

export function servicesByPaths(all: Service[], paths: string[] = []): Service[] {
  return paths.map((p) => all.find((s) => s.path === p)).filter((s): s is Service => Boolean(s));
}

export const KINDS: Record<string, { label: string; short: string; href: string }> = {
  certification: { label: 'Certifications', short: 'Certification', href: '/certification' },
  testing: { label: 'Product testing', short: 'Testing', href: '/services/testing' },
  registration: { label: 'Indian registrations & licences', short: 'Registration', href: '/services/indian-registration-compliances' },
  quality: { label: 'Quality assurance', short: 'Quality', href: '/services/qualitiy' },
};

const SERVICE_ICONS: [string[], string][] = [
  [['wpc', 'wireless', 'bluetooth'], 'wifi'],
  [['tec', 'mtcte', 'telecom'], 'cell_tower'],
  [['bee', 'energy'], 'energy_savings_leaf'],
  [['cdsco', 'medical'], 'medical_services'],
  [['epr', 'waste', 'plastic', 'battery', 'environment'], 'recycling'],
  [['crs'], 'memory'],
  [['isi', 'fmcs'], 'verified'],
  [['ce', 'ukca'], 'euro'],
  [['fcc'], 'flag'],
  [['peso'], 'local_fire_department'],
  [['imei'], 'smartphone'],
  [['fssai', 'food', 'haccp', 'halal'], 'restaurant'],
  [['iso'], 'workspace_premium'],
];

const KIND_ICONS: Record<string, string> = { certification: 'verified', testing: 'science', registration: 'app_registration', quality: 'fact_check' };

/** A fitting Material Symbols icon for a service when the admin has not chosen one. */
export function serviceIcon(service: Service): string {
  const words = service.slug.toLowerCase().split('-');
  return SERVICE_ICONS.find(([keys]) => keys.some((k) => words.includes(k)))?.[1] ?? KIND_ICONS[service.kind] ?? 'verified';
}

export const REGIONS: Record<string, { label: string; path: string; blurb: string }> = {
  india: { label: 'India', path: 'certification/india', blurb: 'BIS CRS, ISI, WPC, TEC, BEE, CDSCO and every mandatory Indian approval.' },
  emea: { label: 'Europe & Middle East', path: 'certification/emea', blurb: 'CE, UKCA, SABER, SFDA, G-Mark and SONCAP for EU, UK, GCC and Africa.' },
  asia: { label: 'Asia', path: 'certification/asia', blurb: 'CCC for China, PSE and MIC for Japan, SNI for Indonesia.' },
  americas: { label: 'Americas', path: 'certification/americas', blurb: 'FCC for the United States and NOM for Mexico.' },
  global: { label: 'Global', path: 'certification/global-certifications', blurb: 'ISO, CB Scheme, HACCP, Halal, IMEI and other internationally recognised programmes.' },
};

export const GROUPS: Record<string, { label: string; blurb: string }> = {
  'environment-compliance': { label: 'EPR & environment', blurb: 'CPCB EPR for e-waste, plastic, battery, tyre and used oil, plus pollution consents.' },
  'import-export': { label: 'Import & export', blurb: 'IEC, ICEGATE, RCMC, CHIMS and other trade registrations.' },
  'business-licenses': { label: 'Business licences', blurb: 'FSSAI, LMPC, barcode, MSME, Startup India and sector licences.' },
  'man-power': { label: 'Manpower & labour', blurb: 'PF, ESIC, BOCW and employee insurance.' },
  'factories-and-building': { label: 'Factories & building', blurb: 'Factory licence, building approvals and Factories Act compliance.' },
  others: { label: 'Other solutions', blurb: 'GeM, marketplace listing, sample import and assembly support.' },
};

export interface Listing {
  path: string;
  title: string;
  description: string;
  filter: (s: Service) => boolean;
  groupBy?: 'region' | 'group';
}

export function listings(): Listing[] {
  return [
    {
      path: 'certification',
      title: 'Product certifications for India and 40+ markets',
      description: 'Mandatory and voluntary product approvals — mapped to the right standard, tested at recognised labs and filed for you.',
      filter: (s) => s.kind === 'certification',
      groupBy: 'region',
    },
    ...Object.entries(REGIONS).map(([key, r]) => ({
      path: r.path,
      title: `${r.label} certifications`,
      description: r.blurb,
      filter: (s: Service) => s.kind === 'certification' && s.region === key,
    })),
    {
      path: 'services/testing',
      title: 'Product testing at accredited labs',
      description: 'Safety, EMC, RF, chemical and performance testing through NABL and BIS-recognised partner laboratories.',
      filter: (s) => s.kind === 'testing',
    },
    {
      path: 'services/indian-registration-compliances',
      title: 'Indian registrations & business licences',
      description: 'Every licence an importer, manufacturer or brand owner needs to operate in India.',
      filter: (s) => s.kind === 'registration',
      groupBy: 'group',
    },
    ...Object.entries(GROUPS).map(([key, g]) => ({
      path: `services/indian-registration-compliances/${key}`,
      title: g.label,
      description: g.blurb,
      filter: (s: Service) => s.group === key,
    })),
    {
      path: 'services/qualitiy',
      title: 'Quality assurance & inspection',
      description: 'Pre-shipment inspection, factory audits and post-certification market surveillance.',
      filter: (s) => s.kind === 'quality',
    },
  ];
}
