import type { APIRoute } from 'astro';
import { POST_TYPES, REGIONS, eventPlace, formatDate, getEvents, getIndustries, getPosts, getServices, getStandardCategories } from '../lib/content';
import { isLabel } from '../lib/standards';
import type { SearchEntry, SearchGroup } from '../lib/siteSearch';
import type { PostType, Seo } from '../lib/types';

const KIND_GROUP: Record<string, SearchGroup> = { certification: 'c', testing: 't', registration: 'r', quality: 'q' };
const GENERIC_PRODUCT = /^(indian standard|product|products)$/i;
// "BIS ISI Mark Certification for Air Purifier IS 302 Part 1" → "Air Purifier"
const productFromTitle = (title: string) => title.replace(/^.*?\bfor\s+/i, '').replace(/\s+IS\s*\d.*$/i, '').trim();
const listed = (seo?: Pick<Seo, 'robots'> | null) => !/noindex/i.test(seo?.robots ?? '');
const clip = (text: string | null | undefined, max = 140) => (text ?? '').replace(/\s+/g, ' ').trim().slice(0, max);

/** Everything the site search finds (home page hero, /search, 404), fetched when a visitor first uses the box. */
export const GET: APIRoute = async () => {
  const [services, categories, industries, events, posts] = await Promise.all([
    getServices(),
    getStandardCategories(),
    getIndustries(),
    getEvents(),
    Promise.all((Object.keys(POST_TYPES) as PostType[]).map((type) => getPosts(type))),
  ]);
  const entries: SearchEntry[] = [];

  for (const s of services) {
    if (!listed(s.seo)) continue;
    entries.push({ g: KIND_GROUP[s.kind] ?? 'c', t: s.title, u: s.url, d: (s.region && REGIONS[s.region]?.label) || undefined, k: clip(s.summary) });
  }

  const products = new Set<string>();
  for (const c of categories) {
    for (const s of c.standards) {
      entries.push({ g: 's', t: s.title, u: s.url, d: [s.isNumbers.slice(0, 2).map(isLabel).join(' · '), c.title].filter(Boolean).join(' · '), k: s.isKeys.join(' ') });
      for (const p of s.products ?? []) {
        const name = (GENERIC_PRODUCT.test(p.name.trim()) ? productFromTitle(s.title) : p.name).trim();
        const key = `${s.url}|${name.toLowerCase()}`;
        if (!name || products.has(key)) continue;
        products.add(key);
        entries.push({ g: 'p', t: name, u: s.url, d: [isLabel(p.is_number.trim()), p.hsn && `HSN ${p.hsn}`].filter(Boolean).join(' · ') });
      }
    }
  }

  for (const p of posts.flat()) {
    if (!listed(p.seo)) continue;
    entries.push({ g: 'a', t: p.title, u: p.url, d: [POST_TYPES[p.type].label, p.publishedAt && formatDate(p.publishedAt)].filter(Boolean).join(' · '), k: clip(p.excerpt) });
  }

  for (const i of industries) {
    if (listed(i.seo)) entries.push({ g: 'i', t: i.name, u: i.url, d: 'Industry', k: clip(i.summary) });
  }

  for (const e of events) {
    entries.push({ g: 'e', t: e.title, u: e.url, d: [formatDate(e.startsAt), eventPlace(e)].filter(Boolean).join(' · ') });
  }

  return new Response(JSON.stringify(entries), { headers: { 'Content-Type': 'application/json' } });
};
