import type { LegalEntity, Site } from './types';

/** The company named in legal notices; the brand name and main address stand in until one is set up. */
export function mainEntity(site: Site) {
  const entity = site.entities?.find((e) => e.primary) ?? site.entities?.[0];

  return {
    name: entity?.name || site.name,
    registration: entity?.registrationNumber ? [entity.registrationLabel, entity.registrationNumber].filter(Boolean).join(' ') : null,
    address: entity?.address || site.contact.address,
  };
}

/** Companies switched on for the footer, main company first. */
export const shownEntities = (site: Site): LegalEntity[] => (site.entities ?? []).filter((e) => e.showOnWebsite);
