import type { IconKey } from '../components/Icon.astro';
import type { RegulationStatus, Standard, StandardCategory, StandardProduct } from './types';

export const STATUS: Record<RegulationStatus, { label: string; badge: string; dot: string }> = {
  mandatory: { label: 'Mandatory', badge: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
  upcoming: { label: 'Mandatory soon', badge: 'bg-amber-50 text-amber-800 ring-amber-200', dot: 'bg-amber-500' },
  deferred: { label: 'Date not set', badge: 'bg-orange-50 text-orange-800 ring-orange-200', dot: 'bg-orange-400' },
  cdsco: { label: 'Medical device', badge: 'bg-sky-50 text-sky-800 ring-sky-200', dot: 'bg-sky-500' },
  voluntary: { label: 'Voluntary', badge: 'bg-slate-100 text-slate-700 ring-slate-200', dot: 'bg-slate-400' },
  rescinded: { label: 'No longer mandatory', badge: 'bg-slate-100 text-slate-500 ring-slate-200', dot: 'bg-slate-300' },
};

export const SCHEME: Record<string, string> = {
  isi: 'BIS ISI mark (Scheme-I)',
  crs: 'BIS CRS registration (Scheme-II)',
  bee: 'BEE star label',
  iso: 'ISO management system',
};

export const GROUPS: Record<StandardCategory['group'], { title: string; blurb: string; icon: IconKey }> = {
  mandatory: {
    title: 'Mandatory standards',
    blurb: 'You need a BIS licence (ISI mark) or a CRS registration to make, import or sell these products in India.',
    icon: 'shield',
  },
  voluntary: {
    title: 'Voluntary standards',
    blurb: 'The ISI mark is optional for these products. Government tenders and big buyers often still ask for it.',
    icon: 'badge',
  },
  bee: {
    title: 'BEE star labelling',
    blurb: 'Energy star labels from the Bureau of Energy Efficiency (BEE). Some products must have one. For others it is optional.',
    icon: 'bulb',
  },
  management: {
    title: 'Management systems',
    blurb: 'ISO standards for quality, the environment, safety and information security.',
    icon: 'layers',
  },
};

export const GROUP_ORDER: StandardCategory['group'][] = ['mandatory', 'voluntary', 'bee', 'management'];

/** BIS charges on every ISI mark licence, on top of the marking fee (Indian manufacturers). */
export const ISI_OTHER_CHARGES = { application: 1000, licence: 1000, audit: 7000 };

export const CONSULTING_NOTE = 'Best-value quote – let’s talk';

export function rupees(value: number): string {
  return `₹${value.toLocaleString('en-IN', { maximumFractionDigits: value < 100 ? 2 : 0 })}`;
}

export function range(min?: number | null, max?: number | null): string | null {
  if (!min) return null;
  return !max || max === min ? rupees(min) : `${rupees(min)} – ${rupees(max)}`;
}

/** The date part on its own: "since 17 Feb 2003", "from 30 Sep 2026", "in stages from Oct 2024". */
export function sinceLine(status: RegulationStatus | null | undefined, since: string | null | undefined): string | null {
  if (!status || !since) return status === 'deferred' ? 'Start date not set yet' : null;
  if (since.startsWith('Phased from')) return `in stages ${since.slice(7)}`;
  if (since.startsWith('Rescinded')) return `ended ${since.slice(10)}`;
  const forms: Partial<Record<RegulationStatus, string>> = { upcoming: `from ${since}`, deferred: `order issued ${since}`, rescinded: since };
  return forms[status] ?? `since ${since}`;
}

/** "Mandatory since 17 Feb 2003", "Mandatory from 30 Sep 2026", "Mandatory in stages from Oct 2024" … */
export function statusLine(status: RegulationStatus | null | undefined, since: string | null | undefined): string | null {
  if (!status) return null;
  if (status === 'deferred') return since ? `Coming – order issued ${since}` : 'Coming – start date not set yet';
  const when = sinceLine(status, since);
  if (!when) return STATUS[status].label;
  if (status === 'rescinded') return `${STATUS[status].label} (${when})`;
  return `${status === 'upcoming' ? 'Mandatory' : STATUS[status].label} ${when}`;
}

export function rowStatusLine(p: StandardProduct): string | null {
  return statusLine(p.status, p.since);
}

/** Editorial rows store bare numbers such as "459:1992". */
export function isLabel(isNumber: string): string {
  return /^\d/.test(isNumber) ? `IS ${isNumber}` : isNumber;
}

const keyIndex = new WeakMap<StandardCategory[], Map<string, Standard[]>>();

/** Pages in other sections that cite the same Indian Standard, e.g. the ISI page and the BEE label for air conditioners. */
export function relatedStandards(categories: StandardCategory[], standard: Standard, limit = 6): Standard[] {
  let index = keyIndex.get(categories);
  if (!index) {
    index = new Map();
    for (const c of categories) {
      for (const s of c.standards) {
        for (const key of s.isKeys ?? []) index.set(key, [...(index.get(key) ?? []), s]);
      }
    }
    keyIndex.set(categories, index);
  }
  const seen = new Set([standard.path]);
  const out: Standard[] = [];
  for (const key of standard.isKeys ?? []) {
    for (const s of index.get(key) ?? []) {
      if (seen.has(s.path)) continue;
      seen.add(s.path);
      out.push(s);
    }
  }
  return out.slice(0, limit);
}
