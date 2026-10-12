import type { Testimonial } from './types';

/** Shared by the build and by the browser, which redraws review cards when the admin's list has changed since the build. */
export type Review = Pick<Testimonial, 'name' | 'rating' | 'quote'> & { context?: string | null };

const mentions = (t: Review, terms: Set<string>) => (`${t.context ?? ''} ${t.quote}`.toLowerCase().split(/[^a-z0-9]+/).some((w) => terms.has(w)) ? 1 : 0);

/** Reviews that mention one of the terms (a page's service) come first; otherwise the admin's order is kept. */
export function orderReviews<T extends Review>(items: T[], terms: string[] = []): T[] {
  if (!terms.length) return items;
  const set = new Set(terms);
  return [...items].sort((a, b) => mentions(b, set) - mentions(a, set));
}

/** Short fingerprint of what a review block shows, to skip redrawing when nothing changed. */
export function reviewsKey(items: Review[]): string {
  let hash = 0;
  for (const t of items) {
    for (const ch of `${t.name}|${t.context ?? ''}|${t.rating}|${t.quote}\n`) hash = (Math.imul(31, hash) + ch.charCodeAt(0)) | 0;
  }
  return `${items.length}-${(hash >>> 0).toString(36)}`;
}

/** A scrolling row repeats short lists so it never runs out of cards. */
export function loopTrack<T>(items: T[]): T[] {
  return items.length ? Array.from({ length: Math.ceil(6 / items.length) }, () => items).flat() : items;
}

export const scrollDuration = (cards: number) => `${Math.max(40, cards * 13)}s`;

export const initials = (name: string) =>
  name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2);
