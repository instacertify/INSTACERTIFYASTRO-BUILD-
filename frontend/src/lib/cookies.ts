import type { Site } from './types';

export type ConsentCategory = 'necessary' | 'preferences' | 'analytics' | 'marketing';
export type Lang = 'en' | 'hi';

export interface CookieItem {
  name: string;
  provider: string;
  storage: 'Cookie' | 'Local storage' | 'Session storage';
  duration: string;
  purpose: string;
}

export interface CategoryInfo {
  id: ConsentCategory;
  required: boolean;
  title: Record<Lang, string>;
  description: Record<Lang, string>;
  items: CookieItem[];
}

export const CONSENT_COOKIE = 'ic_consent';
export const CONSENT_MAX_AGE_DAYS = 365;

/** IDs handed to the browser; each tool loads only after its consent category is granted. */
export interface Tracking {
  gtm?: string;
  ga?: string;
  ads?: string;
  adsLabel?: string;
  adsCallLabel?: string;
  adsWhatsappLabel?: string;
  clarity?: string;
  meta?: string;
  linkedin?: string;
  linkedinLead?: string;
  uet?: string;
  /** GA4 measurement ID the server sends generate_lead to; the browser then leaves that event out. */
  gaServer?: string;
}

export function trackingConfig(site: Site): Tracking {
  const i = site.integrations ?? {};
  const config: Tracking = {
    gtm: i.gtm ?? undefined,
    ga: i.googleTag ?? undefined,
    ads: i.googleAds ?? undefined,
    adsLabel: i.googleAds ? (i.googleAdsLeadLabel ?? undefined) : undefined,
    adsCallLabel: i.googleAds ? (i.googleAdsCallLabel ?? undefined) : undefined,
    adsWhatsappLabel: i.googleAds ? (i.googleAdsWhatsappLabel ?? undefined) : undefined,
    clarity: i.clarity ?? undefined,
    meta: i.metaPixel ?? undefined,
    linkedin: i.linkedinPartner ?? undefined,
    linkedinLead: i.linkedinPartner ? (i.linkedinLeadConversion ?? undefined) : undefined,
    uet: i.microsoftAds ?? undefined,
    gaServer: i.serverSide?.ga4 ?? undefined,
  };
  return Object.fromEntries(Object.entries(config).filter(([, v]) => v)) as Tracking;
}

/** Every cookie / storage key the public site can set. Banner and Cookie Policy both render from this list. */
export function cookieCatalog(site: Site, matomo: boolean): CategoryInfo[] {
  const t = trackingConfig(site);
  // Tag Manager containers almost always carry Google Analytics and Google Ads tags.
  const google = !!(t.ga || t.gtm);
  const googleAds = !!(t.ga || t.gtm || t.ads);
  const ga = t.ga?.replace(/^G-/, '') ?? '<ID>';
  const adPlatforms = [googleAds && 'Google', t.uet && 'Microsoft Bing', t.linkedin && 'LinkedIn', t.meta && 'Facebook / Instagram'].filter(Boolean) as string[];
  const platforms = adPlatforms.length > 1 ? `${adPlatforms.slice(0, -1).join(', ')} and ${adPlatforms.at(-1)}` : (adPlatforms[0] ?? '');

  const categories: CategoryInfo[] = [
    {
      id: 'necessary',
      required: true,
      title: { en: 'Strictly necessary', hi: 'अत्यावश्यक' },
      description: {
        en: 'Needed for the site to work and to remember your cookie choice. These cannot be switched off.',
        hi: 'वेबसाइट चलाने और आपकी कुकी पसंद याद रखने के लिए आवश्यक। इन्हें बंद नहीं किया जा सकता।',
      },
      items: [
        { name: CONSENT_COOKIE, provider: 'instacertify.com', storage: 'Cookie', duration: '12 months', purpose: 'Stores your cookie choices and an anonymous consent ID so we can honour and prove them.' },
      ],
    },
    {
      id: 'preferences',
      required: false,
      title: { en: 'Preferences', hi: 'पसंद' },
      description: {
        en: 'Remember small choices across visits, such as not showing the enquiry pop-up again after you close it.',
        hi: 'आपकी छोटी पसंद याद रखती हैं, जैसे पूछताछ पॉप-अप बंद करने के बाद उसे दोबारा न दिखाना।',
      },
      items: [
        { name: 'ic_popup_dismissed', provider: 'instacertify.com', storage: 'Local storage', duration: '7 days', purpose: 'Remembers that you closed the enquiry pop-up.' },
        { name: 'ic_lead_sent', provider: 'instacertify.com', storage: 'Local storage', duration: '30 days', purpose: 'Stops the pop-up asking again after you have sent us an enquiry.' },
        { name: 'ic_fee_scale', provider: 'instacertify.com', storage: 'Local storage', duration: '30 days', purpose: 'Remembers your business size in the BIS fee finder, so you are not asked for your details again.' },
      ],
    },
    {
      id: 'analytics',
      required: false,
      title: { en: 'Analytics', hi: 'विश्लेषण' },
      description: {
        en: 'Help us understand which pages are useful and where visitors come from, so we can improve the site. Reports are aggregated.',
        hi: 'हमें यह समझने में मदद करती हैं कि कौन-से पेज उपयोगी हैं और विज़िटर कहाँ से आते हैं, ताकि हम साइट बेहतर बना सकें।',
      },
      items: [
        { name: 'utm_source, utm_medium, utm_campaign, utm_term, gclid, first_referrer', provider: 'instacertify.com', storage: 'Session storage', duration: 'Until you close the tab', purpose: 'Remembers which campaign, ad or website brought you here, sent with your enquiry.' },
        ...(matomo
          ? [
              { name: '_pk_id.*', provider: 'Matomo (self-hosted)', storage: 'Cookie' as const, duration: '13 months', purpose: 'Distinguishes visits by the same browser for visit statistics.' },
              { name: '_pk_ses.*', provider: 'Matomo (self-hosted)', storage: 'Cookie' as const, duration: '30 minutes', purpose: 'Groups page views into a single visit.' },
            ]
          : []),
        ...(google
          ? [
              { name: '_ga', provider: 'Google Analytics', storage: 'Cookie' as const, duration: '13 months', purpose: 'Distinguishes visitors for usage statistics.' },
              { name: t.ga ? `_ga_${ga}` : '_ga_*', provider: 'Google Analytics', storage: 'Cookie' as const, duration: '13 months', purpose: 'Keeps the state of your visit for usage statistics.' },
            ]
          : []),
        ...(t.clarity
          ? [
              { name: '_clck', provider: 'Microsoft Clarity', storage: 'Cookie' as const, duration: '12 months', purpose: 'Recognises the browser across visits so heatmaps and anonymised session replays can be grouped.' },
              { name: '_clsk', provider: 'Microsoft Clarity', storage: 'Cookie' as const, duration: '1 day', purpose: 'Joins the pages of one visit into a single session replay.' },
              { name: 'CLID, MUID, ANONCHK, SM', provider: 'Microsoft (clarity.ms, bing.com)', storage: 'Cookie' as const, duration: 'Up to 12 months', purpose: 'Set by Microsoft on its own domains to recognise the browser across sites that use Clarity.' },
            ]
          : []),
      ],
    },
  ];

  if (adPlatforms.length) {
    categories.push({
      id: 'marketing',
      required: false,
      title: { en: 'Marketing', hi: 'मार्केटिंग' },
      description: {
        en: `Measure whether our ads on ${platforms} lead to enquiries. We do not sell your data.`,
        hi: `यह मापने के लिए कि ${platforms} पर हमारे विज्ञापनों से पूछताछ आती है या नहीं। हम आपका डेटा नहीं बेचते।`,
      },
      items: [
        ...(googleAds
          ? [{ name: '_gcl_au', provider: 'Google Ads', storage: 'Cookie' as const, duration: '90 days', purpose: 'Links an ad click to a later enquiry (conversion measurement).' }]
          : []),
        ...(t.uet
          ? [
              { name: '_uetsid, _uetvid', provider: 'Microsoft Advertising', storage: 'Cookie' as const, duration: '1 day to 13 months', purpose: 'Links a Bing ad click to a later enquiry (conversion measurement).' },
              { name: 'MUID', provider: 'Microsoft (bing.com)', storage: 'Cookie' as const, duration: '13 months', purpose: 'Set by Microsoft on its own domain to recognise the browser and report ad results.' },
            ]
          : []),
        ...(t.linkedin
          ? [
              { name: 'li_fat_id, li_sugr', provider: 'LinkedIn', storage: 'Cookie' as const, duration: 'Up to 90 days', purpose: 'Links a LinkedIn ad click to a later enquiry and measures ad audiences.' },
              { name: 'bcookie, lidc, UserMatchHistory, AnalyticsSyncHistory', provider: 'LinkedIn (linkedin.com)', storage: 'Cookie' as const, duration: '1 day to 12 months', purpose: 'Set by LinkedIn on its own domain to recognise the browser and report ad results.' },
            ]
          : []),
        ...(t.meta
          ? [
              { name: '_fbp', provider: 'Meta (Facebook)', storage: 'Cookie' as const, duration: '90 days', purpose: 'Identifies the browser to measure Facebook and Instagram ads.' },
              { name: 'fr', provider: 'Meta (facebook.com)', storage: 'Cookie' as const, duration: '90 days', purpose: 'Set by Meta on its own domain for ad delivery and measurement.' },
            ]
          : []),
      ],
    });
  }

  return categories;
}
