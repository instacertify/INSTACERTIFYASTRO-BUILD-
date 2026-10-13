import { CONSENT_COOKIE, CONSENT_MAX_AGE_DAYS, type Tracking } from '../lib/cookies';

type Optional = 'preferences' | 'analytics' | 'marketing';
type Choices = Record<Optional, boolean>;
type Action = 'accept_all' | 'reject_all' | 'custom' | 'withdraw';

export interface Consent {
  id: string;
  v: string;
  ts: number;
  l: 'en' | 'hi';
  c: Choices;
}

declare global {
  interface Window {
    _paq?: unknown[][];
    gtag?: (...args: unknown[]) => void;
  }
}

const OPTIONAL: Optional[] = ['preferences', 'analytics', 'marketing'];
const API_URL = (import.meta.env.PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');
const root = document.getElementById('cookie-consent');

function read(): Consent | null {
  const raw = document.cookie.split('; ').find((c) => c.startsWith(`${CONSENT_COOKIE}=`));
  if (!raw) return null;
  try {
    const consent = JSON.parse(decodeURIComponent(raw.slice(CONSENT_COOKIE.length + 1))) as Consent;
    const fresh = Date.now() - consent.ts < CONSENT_MAX_AGE_DAYS * 86_400_000;
    return fresh && consent.v === root?.dataset.version ? consent : null;
  } catch {
    return null;
  }
}

let current = read();

export const hasConsent = (category: Optional) => !!current?.c[category];

/** Local storage once preferences are allowed; otherwise nothing outlives the tab. */
export const prefStorage = (): Storage => (hasConsent('preferences') ? localStorage : sessionStorage);

export function onConsent(callback: (consent: Consent | null) => void) {
  callback(current);
  window.addEventListener('ic:consent', () => callback(current));
}

function expire(prefixes: string[]) {
  const host = location.hostname;
  const domains = ['', host, `.${host}`, `.${host.split('.').slice(-2).join('.')}`];
  for (const cookie of document.cookie.split('; ')) {
    const name = cookie.split('=')[0];
    if (!prefixes.some((p) => name.startsWith(p))) continue;
    for (const domain of domains) {
      document.cookie = `${name}=; Max-Age=0; Path=/${domain ? `; Domain=${domain}` : ''}`;
    }
  }
}

const tracking: Tracking = JSON.parse(root?.dataset.tracking || '{}');
const loaded = new Set<'google' | 'clarity' | 'meta' | 'linkedin' | 'uet'>();
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const w = window as any;

function addScript(src: string) {
  const script = document.createElement('script');
  script.async = true;
  script.src = src;
  document.head.appendChild(script);
  return script;
}

/** Runs once the page has finished loading and the browser is idle, so tags never delay what the visitor sees. */
function afterLoad(run: () => void) {
  const idle = () => ('requestIdleCallback' in window ? requestIdleCallback(run, { timeout: 4000 }) : setTimeout(run, 1500));
  if (document.readyState === 'complete') idle();
  else addEventListener('load', idle, { once: true });
}

/** Tag Manager, GA4 and Google Ads share one dataLayer and Google Consent Mode. */
function loadGoogle(c: Choices) {
  const googleIds = [tracking.ga, tracking.ads].filter(Boolean) as string[];
  if (!tracking.gtm && !googleIds.length) return;

  const state = (on: boolean) => (on ? 'granted' : 'denied');
  const update = {
    analytics_storage: state(c.analytics),
    ad_storage: state(c.marketing),
    ad_user_data: state(c.marketing),
    ad_personalization: state(c.marketing),
  };
  if ((c.analytics || c.marketing) && !loaded.has('google')) {
    loaded.add('google');
    w.dataLayer = w.dataLayer || [];
    window.gtag = function gtag() {
      // gtag.js only recognises the Arguments object, not a plain array.
      // eslint-disable-next-line prefer-rest-params
      w.dataLayer.push(arguments);
    };
    window.gtag('consent', 'default', { ...update, functionality_storage: 'granted', security_storage: 'granted' });
    if (googleIds.length) {
      window.gtag('js', new Date());
      if (tracking.ga) window.gtag('config', tracking.ga, { cookie_expires: 34_128_000, anonymize_ip: true });
      if (tracking.ads) window.gtag('config', tracking.ads);
      addScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(googleIds[0])}`);
    }
    if (tracking.gtm) {
      w.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      addScript(`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(tracking.gtm)}`);
    }
  } else if (loaded.has('google')) {
    window.gtag?.('consent', 'update', update);
  }
  if (loaded.has('google')) w.dataLayer.push({ event: 'cookie_consent_update', analytics: c.analytics, marketing: c.marketing });
  if (!c.analytics) expire(['_ga']);
  if (!c.marketing) expire(['_gcl']);
}

function loadClarity(c: Choices) {
  if (!tracking.clarity) return;
  const signal = { analytics_Storage: c.analytics ? 'granted' : 'denied', ad_Storage: c.marketing ? 'granted' : 'denied' };
  if (c.analytics && !loaded.has('clarity')) {
    loaded.add('clarity');
    w.clarity =
      w.clarity ||
      function clarity() {
        // eslint-disable-next-line prefer-rest-params
        (w.clarity.q = w.clarity.q || []).push(arguments);
      };
    addScript(`https://www.clarity.ms/tag/${encodeURIComponent(tracking.clarity)}`);
  }
  if (loaded.has('clarity')) w.clarity('consentv2', signal);
  if (!c.analytics) expire(['_clck', '_clsk']);
}

function loadMeta(c: Choices) {
  if (!tracking.meta) return;
  if (c.marketing && !loaded.has('meta')) {
    loaded.add('meta');
    type Fbq = { (...args: unknown[]): void; callMethod?: (...a: unknown[]) => void; queue: unknown[]; push: unknown; loaded: boolean; version: string };
    const fbq: Fbq = function () {
      // eslint-disable-next-line prefer-rest-params
      fbq.callMethod ? fbq.callMethod.apply(fbq, arguments as unknown as unknown[]) : fbq.queue.push(arguments);
    } as unknown as Fbq;
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];
    w.fbq = w._fbq = fbq;
    addScript('https://connect.facebook.net/en_US/fbevents.js');
    w.fbq('init', tracking.meta);
    w.fbq('track', 'PageView');
  } else if (loaded.has('meta')) {
    w.fbq('consent', c.marketing ? 'grant' : 'revoke');
  }
  if (!c.marketing) expire(['_fbp', '_fbc']);
}

function loadLinkedIn(c: Choices) {
  if (!tracking.linkedin) return;
  if (c.marketing && !loaded.has('linkedin')) {
    loaded.add('linkedin');
    w._linkedin_partner_id = tracking.linkedin;
    w._linkedin_data_partner_ids = [...(w._linkedin_data_partner_ids || []), tracking.linkedin];
    // Conversions sent before the script arrives wait in this queue.
    if (!w.lintrk) {
      w.lintrk = (a: unknown, b: unknown) => w.lintrk.q.push([a, b]);
      w.lintrk.q = [];
    }
    addScript('https://snap.licdn.com/li.lms-analytics/insight.min.js');
  }
  if (!c.marketing) expire(['li_fat_id', 'li_sugr']);
}

/** Microsoft Advertising (Bing Ads) UET tag. */
function loadMicrosoftAds(c: Choices) {
  if (!tracking.uet) return;
  if (c.marketing && !loaded.has('uet')) {
    loaded.add('uet');
    w.uetq = w.uetq || [];
    w.uetq.push('consent', 'default', { ad_storage: 'granted' });
    addScript('https://bat.bing.com/bat.js').addEventListener('load', () => {
      w.uetq = new w.UET({ ti: tracking.uet, enableAutoSpaTracking: false, q: w.uetq });
      w.uetq.push('pageLoad');
    });
  } else if (loaded.has('uet')) {
    w.uetq.push('consent', 'update', { ad_storage: c.marketing ? 'granted' : 'denied' });
  }
  if (!c.marketing) expire(['_uet']);
}

/** Conversion for every ad platform the visitor has allowed; Tag Manager picks up the dataLayer event. */
export function trackLead(form?: string) {
  if (loaded.has('google')) {
    if (tracking.ga) window.gtag?.('event', 'generate_lead', { form_name: form });
    if (tracking.ads && tracking.adsLabel && hasConsent('marketing')) window.gtag?.('event', 'conversion', { send_to: `${tracking.ads}/${tracking.adsLabel}` });
    w.dataLayer.push({ event: 'generate_lead', form_name: form });
  }
  if (loaded.has('meta') && hasConsent('marketing')) w.fbq('track', 'Lead');
  if (loaded.has('uet') && hasConsent('marketing')) w.uetq.push('event', 'submit_lead_form', {});
  if (loaded.has('linkedin') && tracking.linkedinLead && hasConsent('marketing')) w.lintrk('track', { conversion_id: Number(tracking.linkedinLead) });
  // Lets Clarity recordings be filtered to the visits that ended in an enquiry.
  if (loaded.has('clarity')) w.clarity('event', 'lead');
}

export type ContactKind = 'call' | 'whatsapp' | 'email';

/** A tap on a phone number, WhatsApp or email link: often the visitor's first contact, so counted like a lead. */
export function trackContact(kind: ContactKind, url: string) {
  const event = `click_${kind}`;
  const marketing = hasConsent('marketing');
  if (loaded.has('google')) {
    if (tracking.ga) window.gtag?.('event', event, { link_url: url });
    const label = kind === 'call' ? tracking.adsCallLabel : kind === 'whatsapp' ? tracking.adsWhatsappLabel : undefined;
    if (tracking.ads && label && marketing) window.gtag?.('event', 'conversion', { send_to: `${tracking.ads}/${label}` });
  }
  if (loaded.has('meta') && marketing) w.fbq('track', 'Contact', { content_name: kind });
  if (loaded.has('uet') && marketing) w.uetq.push('event', event, { event_label: kind });
  if (loaded.has('clarity')) w.clarity('event', event);
}

/** @param onPageLoad true on page load: tags then wait for the page; after a click in the banner they start at once. */
function apply(onPageLoad = false) {
  const c = current?.c ?? { preferences: false, analytics: false, marketing: false };

  if (!c.preferences) {
    for (const key of ['ic_popup_dismissed', 'ic_lead_sent', 'ic_fee_scale']) localStorage.removeItem(key);
  }
  if (!c.analytics) {
    for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid', 'first_referrer']) sessionStorage.removeItem(key);
  }

  if (window._paq) {
    window._paq.push([c.analytics ? 'setConsentGiven' : 'forgetConsentGiven']);
    if (!c.analytics) expire(['_pk_', 'mtm_']);
  }

  const tags = () => {
    // Read again: the visitor may have changed their choice while the page was loading.
    const now = current?.c ?? c;
    loadGoogle(now);
    loadClarity(now);
    loadMeta(now);
    loadLinkedIn(now);
    loadMicrosoftAds(now);
  };
  if (onPageLoad) afterLoad(tags);
  else tags();

  window.dispatchEvent(new CustomEvent('ic:consent', { detail: current }));
}

function save(choices: Choices, action: Action) {
  const lang = (root?.dataset.lang as 'en' | 'hi') ?? 'en';
  current = { id: current?.id ?? crypto.randomUUID(), v: root!.dataset.version!, ts: Date.now(), l: lang, c: choices };
  const secure = location.protocol === 'https:' ? '; Secure' : '';
  document.cookie = `${CONSENT_COOKIE}=${encodeURIComponent(JSON.stringify(current))}; Max-Age=${CONSENT_MAX_AGE_DAYS * 86_400}; Path=/; SameSite=Lax${secure}`;

  fetch(`${API_URL}/api/v1/consents`, {
    method: 'POST',
    keepalive: true,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ consent_id: current.id, action, choices, version: current.v, page: location.pathname, language: lang }),
  }).catch(() => {});

  apply();
}

if (root) {
  const banner = root.querySelector<HTMLElement>('[data-cookie-banner]')!;
  const dialog = root.querySelector<HTMLDialogElement>('dialog')!;
  const toggles = () => [...dialog.querySelectorAll<HTMLInputElement>('input[data-category]')];
  const all = (on: boolean) => Object.fromEntries(OPTIONAL.map((k) => [k, on])) as Choices;
  const close = () => {
    banner.hidden = true;
    if (dialog.open) dialog.close();
  };

  const openPreferences = () => {
    toggles().forEach((t) => (t.checked = hasConsent(t.dataset.category as Optional)));
    dialog.showModal();
  };

  const setLang = (lang: string) => {
    root.dataset.lang = lang;
    root.querySelectorAll<HTMLButtonElement>('[data-cookie-lang]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.cookieLang === lang)));
  };
  if (current?.l) setLang(current.l);

  root.querySelectorAll<HTMLButtonElement>('[data-cookie-lang]').forEach((b) => b.addEventListener('click', () => setLang(b.dataset.cookieLang!)));
  root.querySelectorAll('[data-cookie-accept]').forEach((b) => b.addEventListener('click', () => (save(all(true), 'accept_all'), close())));
  root.querySelectorAll('[data-cookie-reject]').forEach((b) =>
    b.addEventListener('click', () => {
      const hadConsent = OPTIONAL.some((k) => hasConsent(k));
      save(all(false), hadConsent ? 'withdraw' : 'reject_all');
      close();
    }),
  );
  root.querySelector('[data-cookie-save]')?.addEventListener('click', () => {
    const choices = Object.fromEntries(OPTIONAL.map((k) => [k, toggles().find((t) => t.dataset.category === k)?.checked ?? false])) as Choices;
    const withdrew = OPTIONAL.some((k) => hasConsent(k) && !choices[k]);
    save(choices, withdrew ? 'withdraw' : 'custom');
    close();
  });
  root.querySelector('[data-cookie-manage]')?.addEventListener('click', openPreferences);
  root.querySelector<HTMLButtonElement>('[data-cookie-more]')?.addEventListener('click', (e) => {
    const more = e.currentTarget as HTMLButtonElement;
    root.querySelector('#cookie-banner-text')?.classList.remove('max-sm:line-clamp-3');
    more.setAttribute('aria-expanded', 'true');
    more.hidden = true;
  });
  dialog.querySelector('[data-cookie-close]')?.addEventListener('click', () => dialog.close());
  document.addEventListener('click', (e) => {
    if ((e.target as Element).closest?.('[data-cookie-settings]')) {
      e.preventDefault();
      openPreferences();
    }
  });

  banner.hidden = !!current;
  apply(true);
}
