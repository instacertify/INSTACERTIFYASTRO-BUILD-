import type { Site } from './types';

/** Display order of the footer icons; keys match Site settings → Offices & social. */
export const SOCIAL_NETWORKS = [
  { key: 'linkedin', label: 'LinkedIn' },
  { key: 'youtube', label: 'YouTube' },
  { key: 'instagram', label: 'Instagram' },
  { key: 'facebook', label: 'Facebook' },
  { key: 'twitter', label: 'X (Twitter)' },
  { key: 'pinterest', label: 'Pinterest' },
] as const;

export function socialLinks(site: Site) {
  return SOCIAL_NETWORKS.flatMap(({ key, label }) => {
    const href = site.social?.[key]?.trim();
    return href && /^https?:\/\//.test(href) ? [{ key, label, href }] : [];
  });
}
