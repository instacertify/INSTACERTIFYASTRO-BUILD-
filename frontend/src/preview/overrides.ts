import type { EventItem, Home, Industry, LandingPageRecord, PageRecord, Post, Service, Site, StandardCategory, Vacancy } from '../lib/types';

export type Preview =
  | { kind: 'ad'; data: LandingPageRecord }
  | { kind: 'page'; data: PageRecord }
  | { kind: 'service'; data: Service }
  | { kind: 'post'; data: Post }
  | { kind: 'event'; data: EventItem }
  | { kind: 'standard'; data: StandardCategory['standards'][number] & { category?: { slug: string } } }
  | { kind: 'industry'; data: Industry }
  | { kind: 'vacancy'; data: Vacancy }
  | { kind: 'home'; data: { home: Home; process: Site['process'] } };

type Override = (data: unknown) => unknown;

/** Puts the draft in place of the saved item in a list, or adds it when it has never been published. */
function swap<T>(list: unknown, item: T, same: (a: T) => boolean, atStart = false): T[] {
  const items = (Array.isArray(list) ? list : []) as T[];
  const index = items.findIndex(same);
  if (index >= 0) return items.map((existing, i) => (i === index ? item : existing));
  return atStart ? [item, ...items] : [...items, item];
}

/** API endpoints (as requested by lib/content) whose responses the draft replaces while it renders. */
export function overridesFor(preview: Preview): Map<string, Override> {
  const map = new Map<string, Override>();
  // Previews must not report visits or conversions to the tracking tools.
  map.set('site', (site) => {
    const base = { ...(site as Site), integrations: {} };
    return preview.kind === 'home' ? { ...base, home: preview.data.home, process: preview.data.process } : base;
  });

  switch (preview.kind) {
    case 'ad': {
      const { data } = preview;
      map.set(`landing-pages/${data.path}`, () => data);
      map.set('landing-pages', (list) => swap<LandingPageRecord>(list, data, (p) => p.path === data.path));
      break;
    }
    case 'page': {
      const { data } = preview;
      map.set(`pages/${data.path}`, () => data);
      map.set('pages', (list) => swap<PageRecord>(list, data, (p) => p.path === data.path));
      break;
    }
    case 'service': {
      const { data } = preview;
      map.set('services?detail=1', (list) => swap<Service>(list, data, (s) => s.path === data.path));
      break;
    }
    case 'post': {
      const { data } = preview;
      map.set(`posts?type=${data.type}&per_page=500&content=1`, (list) => swap<Post>(list, data, (p) => p.slug === data.slug, true));
      break;
    }
    case 'event': {
      const { data } = preview;
      map.set(`events/${data.slug}`, () => data);
      map.set('events', (list) => swap<EventItem>(list, data, (e) => e.slug === data.slug, true));
      break;
    }
    case 'standard': {
      const { data } = preview;
      map.set('standards?detail=1', (list) => {
        const categories = ((list ?? []) as StandardCategory[]).map((c) => ({ ...c, standards: c.standards.filter((s) => s.path !== data.path) }));
        const home = categories.find((c) => c.slug === data.category?.slug) ?? categories[0];
        if (home) home.standards = [data, ...home.standards];
        return categories;
      });
      break;
    }
    case 'industry': {
      const { data } = preview;
      map.set('industries', (list) => swap<Industry>(list, data, (i) => i.slug === data.slug));
      break;
    }
    case 'vacancy': {
      const { data } = preview;
      map.set('vacancies', (list) => swap<Vacancy>(list, data, (v) => v.slug === data.slug, true));
      break;
    }
  }
  return map;
}
