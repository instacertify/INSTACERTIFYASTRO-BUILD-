import type { APIRoute } from 'astro';
import { getStandardCategories } from '../../../lib/content';
import { isLabel } from '../../../lib/standards';

/** Compact index behind the search box on /resources/standards, fetched on first use. */
export const GET: APIRoute = async () => {
  const entries = (await getStandardCategories()).flatMap((c) =>
    c.standards.map((s) => ({ t: s.title, u: s.url, i: s.isNumbers.slice(0, 3).map(isLabel).join(' · '), s: s.regulationStatus, c: c.title })),
  );

  return new Response(JSON.stringify(entries), { headers: { 'Content-Type': 'application/json' } });
};
