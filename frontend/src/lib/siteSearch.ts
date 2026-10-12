/** Site search: the index built by /search.json and the matching shared by the search box and /search. */

export type SearchGroup = 'c' | 't' | 'r' | 'q' | 's' | 'p' | 'a' | 'i' | 'e';

export interface SearchEntry {
  g: SearchGroup;
  /** Title */
  t: string;
  u: string;
  /** Detail line shown under the title */
  d?: string;
  /** Extra words that match but are not shown (summary, IS number keys) */
  k?: string;
}

export const SEARCH_GROUPS: Record<SearchGroup, { label: string; one: string }> = {
  c: { label: 'Certifications', one: 'certification' },
  r: { label: 'Registrations & licences', one: 'registration' },
  t: { label: 'Testing', one: 'testing service' },
  q: { label: 'Quality assurance', one: 'quality service' },
  s: { label: 'Standards', one: 'standard' },
  p: { label: 'Products & HSN codes', one: 'product' },
  a: { label: 'Guides & news', one: 'article' },
  i: { label: 'Industries', one: 'industry' },
  e: { label: 'Events', one: 'event' },
};

/** Example searches linked under the box; each must find something on the site. */
export const SEARCH_SUGGESTIONS = ['Power bank', 'IS 13252', 'HSN 8504', 'BIS CRS', 'WPC ETA', 'CE certification'];

/** Services first, then the reference material, so equal matches favour what a visitor can act on. */
const WEIGHT: Record<SearchGroup, number> = { c: 6, r: 5, t: 5, q: 3, s: 4, i: 3, p: 2, a: 1, e: 0 };

interface Prepared extends SearchEntry {
  title: string;
  titleWords: string[];
  hay: string;
  compact: string;
}

export const normalize = (text: string) =>
  text.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();

let index: Promise<Prepared[]> | null = null;

export function loadSearchIndex(): Promise<Prepared[]> {
  index ??= fetch('/search.json')
    .then((r) => {
      if (!r.ok) throw new Error(`search index: ${r.status}`);
      return r.json() as Promise<SearchEntry[]>;
    })
    .then((list) =>
      list.map((e) => {
        const title = normalize(e.t);
        const hay = ` ${normalize(`${e.t} ${e.d ?? ''} ${e.k ?? ''}`)}`;
        return { ...e, title, titleWords: title.split(' '), hay, compact: hay.replace(/ /g, '') };
      }),
    )
    .catch((error) => {
      index = null;
      throw error;
    });
  return index;
}

/** Short words must start a word ("ce" is not every "certification"); codes also match without spaces ("is13252"). */
function matches(e: Prepared, word: string): boolean {
  if (word.length <= 3 ? e.hay.includes(` ${word}`) : e.hay.includes(word)) return true;
  return word.length >= 4 && /\d/.test(word) && e.compact.includes(word);
}

/** Every entry containing all the words, best first. */
export function searchEntries(list: Prepared[], query: string): SearchEntry[] {
  const q = normalize(query);
  if (!q) return [];
  const words = q.split(' ');

  const hits: { e: Prepared; score: number }[] = [];
  for (const e of list) {
    if (!words.every((w) => matches(e, w))) continue;
    let score = e.title === q ? 100 : e.title.startsWith(q) ? 60 : e.title.includes(q) ? 40 : 0;
    for (const w of words) {
      if (e.titleWords.includes(w)) score += 12;
      else if (w.length >= 3 && e.titleWords.some((tw) => tw.startsWith(w))) score += 8;
      else if (e.title.includes(w)) score += 4;
    }
    hits.push({ e, score: score + WEIGHT[e.g] - Math.min(e.title.length, 120) / 40 });
  }

  return hits.sort((a, b) => b.score - a.score).map((h) => h.e);
}

/** Hits split by group, groups ordered by their best hit. */
export function groupEntries(hits: SearchEntry[]): [SearchGroup, SearchEntry[]][] {
  const groups = new Map<SearchGroup, SearchEntry[]>();
  for (const hit of hits) {
    const list = groups.get(hit.g);
    if (list) list.push(hit);
    else groups.set(hit.g, [hit]);
  }
  return [...groups];
}

/** The text with the searched words in <mark>, built as nodes so titles are never parsed as HTML. */
export function highlight(text: string, query: string): DocumentFragment {
  const fragment = document.createDocumentFragment();
  const words = normalize(query).split(' ').filter((w) => w.length > 1);
  const pattern = words.length ? new RegExp(`(${words.map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'gi') : null;
  for (const part of pattern ? text.split(pattern) : [text]) {
    if (!part) continue;
    if (pattern && words.includes(part.toLowerCase())) {
      const mark = document.createElement('mark');
      mark.className = 'bg-transparent font-semibold text-inherit underline decoration-accent-400 decoration-2 underline-offset-2';
      mark.textContent = part;
      fragment.append(mark);
    } else {
      fragment.append(part);
    }
  }
  return fragment;
}
