import { GROUPS, REGIONS } from './content';

const SEGMENT_LABELS: Record<string, string> = {
  certification: 'Certifications',
  services: 'Services',
  testing: 'Product testing',
  'indian-registration-compliances': 'Indian registrations',
  qualitiy: 'Quality assurance',
  'global-certifications': 'Global',
  industries: 'Industries',
  ...Object.fromEntries(Object.entries(REGIONS).map(([k, r]) => [k, r.label])),
  ...Object.fromEntries(Object.entries(GROUPS).map(([k, g]) => [k, g.label])),
};

export function segmentLabel(segment: string): string {
  return SEGMENT_LABELS[segment] ?? segment;
}

export function trailFor(path: string, currentLabel: string) {
  const parts = path.split('/');
  return parts.map((part, i) => ({
    label: i === parts.length - 1 ? currentLabel : (SEGMENT_LABELS[part] ?? part),
    href: i === parts.length - 1 ? undefined : '/' + parts.slice(0, i + 1).join('/'),
  }));
}

export function breadcrumbSchema(siteUrl: string, trail: { label: string; href?: string }[], currentPath: string) {
  const items = [{ label: 'Home', href: '/' }, ...trail];
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: item.label,
      item: `${siteUrl}${item.href ?? '/' + currentPath}`.replace(/\/$/, ''),
    })),
  };
}
