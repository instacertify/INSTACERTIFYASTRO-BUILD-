import type { APIRoute } from 'astro';
import { getStandardCategories } from '../../../lib/content';
import { isLabel, statusLine } from '../../../lib/standards';
import type { FinderProduct } from '../../../lib/types';

const GENERIC = /^(indian standard|product|products)$/i;
// "BIS ISI Mark Certification for Air Purifier IS 302 Part 1" → "Air Purifier"
const fromTitle = (title: string) => title.replace(/^.*?\bfor\s+/i, '').replace(/\s+IS\s*\d.*$/i, '').trim();

/** Every ISI and CRS product with its BIS fees, behind the fee finder on BIS landing pages. Fetched on first use. */
export const GET: APIRoute = async () => {
  const seen = new Set<string>();
  const rows: FinderProduct[] = [];

  for (const c of await getStandardCategories()) {
    for (const s of c.standards) {
      if (s.scheme !== 'isi' && s.scheme !== 'crs') continue;
      for (const p of s.products ?? []) {
        const name = (GENERIC.test(p.name.trim()) ? fromTitle(s.title) : p.name).trim();
        const is = isLabel(p.is_number.trim());
        const key = `${s.scheme}|${is.toLowerCase().replace(/[^a-z0-9]/g, '')}|${name.toLowerCase()}`;
        if (!name || seen.has(key)) continue;
        seen.add(key);

        const status = p.status ?? s.regulationStatus;
        const fees = s.scheme === 'isi' && p.fee_micro && p.fee_small && p.fee_medium && p.fee_large ? ([p.fee_micro, p.fee_small, p.fee_medium, p.fee_large] as FinderProduct['f']) : undefined;
        rows.push({
          n: name,
          i: is,
          h: p.hsn || undefined,
          s: s.scheme,
          w: statusLine(status, p.status ? p.since : s.mandatorySince) ?? undefined,
          f: fees,
          u: s.scheme === 'isi' ? p.unit_fee || undefined : undefined,
          t: p.test_min || undefined,
          r: p.replaces || undefined,
        });
      }
    }
  }

  return new Response(JSON.stringify(rows), { headers: { 'Content-Type': 'application/json' } });
};
