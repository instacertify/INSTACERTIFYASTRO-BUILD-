import type { AutolinkRule, Site } from './types';

interface Compiled {
  pattern: RegExp;
  /** lower-cased phrase (single-spaced) → target */
  lookup: Map<string, { url: string; exact: string | null }>;
}

const SKIP_TAGS = new Set(['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'code', 'pre', 'script', 'style', 'label', 'select', 'textarea']);
const compiled = new WeakMap<AutolinkRule[], Compiled | null>();

const escape = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const normalise = (s: string) => s.trim().replace(/\s+/g, ' ');
const normalisePath = (p: string) => '/' + p.replace(/^https?:\/\/[^/]+/, '').replace(/^\/+|\/+$/g, '').replace(/\.html$/, '');

function compile(rules: AutolinkRule[]): Compiled | null {
  const lookup: Compiled['lookup'] = new Map();
  for (const rule of rules) {
    // Internal links only: normalisePath would turn another site's URL into a broken path here.
    if (!rule?.url || !rule.keywords || /^(https?:)?\/\/(?!(www\.)?instacertify\.com(\/|$))/i.test(rule.url.trim())) continue;
    for (const raw of rule.keywords.split(',')) {
      const phrase = normalise(raw);
      if (phrase.length < 2 || lookup.has(phrase.toLowerCase())) continue;
      // Acronyms like "REACH" or "WPC" must not match ordinary words ("reach").
      const exact = /[a-z]/.test(phrase) ? null : phrase;
      lookup.set(phrase.toLowerCase(), { url: normalisePath(rule.url), exact });
    }
  }
  if (!lookup.size) return null;

  const alternation = [...lookup.keys()]
    .sort((a, b) => b.length - a.length)
    .map((k) => escape(k).replace(/ /g, '\\s+'))
    .join('|');
  return { pattern: new RegExp(`(?<![\\w/-])(?:${alternation})(?![\\w-])`, 'gi'), lookup };
}

/**
 * Soft internal linking: turns the first mention of each configured keyword into a link to its page.
 * One link per target, capped per page, never inside headings/links/code, never to the current page
 * or to a page the content already links to. Call the returned function for every HTML chunk of one page.
 */
export function createAutolinker(site: Site, currentPath: string) {
  const rules = site.autolinks ?? [];
  if (site.autolinksEnabled === false || !Array.isArray(rules) || !rules.length) return (html?: string | null) => html ?? '';

  if (!compiled.has(rules)) compiled.set(rules, compile(rules));
  const c = compiled.get(rules);
  if (!c) return (html?: string | null) => html ?? '';

  const max = Number(site.autolinksMax) || 6;
  const used = new Set<string>([normalisePath(currentPath)]);
  let count = 0;

  const linkText = (text: string) => {
    let out = '';
    let last = 0;
    c.pattern.lastIndex = 0;
    for (let m = c.pattern.exec(text); m && count < max; m = c.pattern.exec(text)) {
      const phrase = normalise(m[0]);
      const target = c.lookup.get(phrase.toLowerCase());
      if (!target || used.has(target.url) || (target.exact && phrase !== target.exact)) continue;
      out += `${text.slice(last, m.index)}<a href="${target.url}">${m[0]}</a>`;
      last = m.index + m[0].length;
      used.add(target.url);
      count++;
    }
    return out + text.slice(last);
  };

  return (html?: string | null): string => {
    if (!html) return '';
    for (const href of html.matchAll(/href="([^"#?]+)/g)) {
      if (href[1].startsWith('/') || href[1].includes('instacertify.com')) used.add(normalisePath(href[1]));
    }
    if (count >= max) return html;

    let skipDepth = 0;
    return html
      .split(/(<[^>]+>)/)
      .map((token) => {
        if (token.startsWith('<')) {
          const tag = /^<(\/?)([a-z0-9]+)/i.exec(token);
          if (tag && SKIP_TAGS.has(tag[2].toLowerCase()) && !token.endsWith('/>')) {
            skipDepth = Math.max(0, skipDepth + (tag[1] ? -1 : 1));
          }
          return token;
        }
        return skipDepth === 0 && count < max && token.trim() ? linkText(token) : token;
      })
      .join('');
  };
}
