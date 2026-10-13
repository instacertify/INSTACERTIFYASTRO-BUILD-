export interface Link {
  label: string;
  href: string;
  /** Header menu panel only: short note under a group link that has no links of its own. */
  description?: string;
  children?: Link[];
}

export interface AutolinkRule {
  /** Comma-separated phrases; ALL-CAPS phrases (e.g. "REACH") match case-sensitively. */
  keywords: string;
  url: string;
}

export interface Office {
  name: string;
  type: 'head' | 'branch' | 'lab' | 'liaison';
  typeLabel?: string | null;
  /** Street, city, state and PIN joined for display. */
  address: string;
  street: string;
  city?: string | null;
  region?: string | null;
  postalCode?: string | null;
  country: string;
  landmark?: string | null;
  phone?: string | null;
  email?: string | null;
  contactPerson?: string | null;
  hours?: string | null;
  mapEmbed: string;
  mapLink: string;
}

/** Site settings → Tracking & verification. Every value is optional; empty switches the tool off. */
export interface Integrations {
  gtm?: string | null;
  googleTag?: string | null;
  googleAds?: string | null;
  googleAdsLeadLabel?: string | null;
  googleAdsCallLabel?: string | null;
  googleAdsWhatsappLabel?: string | null;
  clarity?: string | null;
  metaPixel?: string | null;
  linkedinPartner?: string | null;
  linkedinLeadConversion?: string | null;
  microsoftAds?: string | null;
  /** Lets the publisher tell Bing, Yandex and other IndexNow engines about new and changed pages. */
  indexNowKey?: string | null;
  matomoSiteId?: string | number | null;
  googleVerification?: string | null;
  bingVerification?: string | null;
  yandexVerification?: string | null;
  pinterestVerification?: string | null;
  facebookVerification?: string | null;
  ahrefsVerification?: string | null;
  /** Files a tool asks to find at the top of the site, e.g. google1234abcd.html. */
  verificationFiles?: { name?: string | null; content?: string | null }[] | Record<string, { name?: string | null; content?: string | null }> | null;
}

export interface Site {
  name: string;
  tagline: string;
  description: string;
  url: string;
  /** Uploaded favicon (absolute URL); the bundled /favicon.png is used when empty. */
  favicon?: string | null;
  /** Uploaded header logo (absolute URL); the bundled logo is used when empty. */
  logo?: string | null;
  contact: {
    phone: string;
    phoneE164: string;
    whatsapp: string;
    email: string;
    address: string;
    hours: string;
    responseTime: string;
  };
  privacy?: { version: string; grievanceOfficer: string; grievanceEmail: string; applicationMonths?: number; consentLogMonths?: number; ipDays?: number };
  careers?: { headline: string; intro: string; perks: { title: string; text: string }[]; generalApplications: boolean };
  integrations?: Integrations;
  offices?: Office[];
  social?: Partial<Record<'linkedin' | 'youtube' | 'instagram' | 'facebook' | 'twitter' | 'pinterest', string | null>>;
  rating?: { value?: number | string | null; count?: number | string | null; url?: string | null } | null;
  process: { title: string; text: string; icon?: string | null; iconImage?: string | null }[];
  home?: Partial<Home>;
  leadForm?: Partial<Record<
    | 'title' | 'subtitle' | 'button' | 'privacyNote'
    | 'nameLabel' | 'phoneLabel' | 'emailLabel' | 'productLabel' | 'productPlaceholder'
    | 'contactTitle' | 'contactSubtitle' | 'contactButton'
    | 'successTitle' | 'successText',
    string | null
  >>;
  popup?: {
    enabled?: boolean;
    title?: string | null;
    subtitle?: string | null;
    button?: string | null;
    delay?: number | string | null;
    image?: string | null;
    imageAlt?: string | null;
    imageFocus?: 'left' | 'center' | 'right' | null;
  };
  /** Admin → Website → Contact boxes, merged over the defaults by the API; read it through getContactBoxes(). */
  contactBoxes?: Record<string, Record<string, unknown> | undefined>;
  autolinksEnabled?: boolean;
  autolinksMax?: number | string | null;
  autolinks?: AutolinkRule[];
  taxonomy?: { groups?: Record<string, string>; testCategories?: TestCategory[]; testCategoryIconAlign?: IconAlign | null };
  nav: Link[];
  footer: {
    columns: { title: string; links: Link[] }[];
    portals: Link[];
    /** Links in the bottom bar next to "Cookie settings"; older data without it uses Privacy + Sitemap. */
    bottomLinks?: Link[];
    /** null when the admin switched the newsletter box off. */
    newsletter?: { title: string; text: string } | null;
    /** "{year}" is replaced with the current year. */
    copyright?: string;
  };
}

/** An industry on the Product testing page; a testing service can belong to several. */
export interface TestCategory {
  slug: string;
  label: string;
  icon: string;
  iconImage?: string | null;
  blurb: string;
}

/** A Google Material Symbols icon name, e.g. "verified". */
type IconName = string | null;
/** Where a section's icons sit in each card: see lib/iconAlign. */
type IconAlign = 'left' | 'center' | 'right';
/** An icon uploaded in the admin (absolute URL); replaces the named icon. */
type IconImage = string | null;

interface HomeSection {
  visible: boolean;
  eyebrow?: string | null;
  heading?: string | null;
  linkLabel?: string | null;
  linkUrl?: string | null;
  /** "Icon position" for sections with icon cards. */
  iconAlign?: IconAlign | null;
}

export interface HomeTile {
  title: string;
  text: string;
  href?: string | null;
  icon?: IconName;
  iconImage?: IconImage;
}

/** Everything on the home page, edited in Admin → Website → Home page. */
export interface Home {
  seo: { title: string; description: string } & Pick<Seo, 'schemaMode' | 'schema'>;
  hero: {
    badge?: string | null;
    title: string;
    highlight?: string | null;
    intro?: string | null;
    buttonLabel?: string | null;
    buttonLink?: string | null;
    showPhone?: boolean;
    /** Site search under the headline instead of the main button and phone number. */
    showSearch?: boolean;
    /** Shown beside the headline when the enquiry form is off. */
    pillars?: { title: string; text?: string | null; icon?: string | null; iconImage?: string | null; image?: string | null }[];
    showForm?: boolean;
    formTitle?: string | null;
    formSubtitle?: string | null;
    image?: string | null;
    imageAlt?: string | null;
    overlay?: 'light' | 'dark';
    position?: 'center' | 'top' | 'bottom' | 'left' | 'right';
  };
  clientLogosEnabled: boolean;
  /** Same strip under the hero of certification and testing service pages. */
  clientLogosOnServices?: boolean;
  clientLogosTitle?: string | null;
  clientLogos: { name: string; image: string | null; url?: string | null }[];
  stats: { visible: boolean; iconAlign?: IconAlign | null; items: { value: string; label: string; icon?: IconName; iconImage?: IconImage }[] };
  marks: { visible: boolean; title?: string | null };
  popular: HomeSection & { items: { path: string; icon?: IconName; iconImage?: IconImage }[] };
  explore: HomeSection & { items: HomeTile[] };
  industries: HomeSection;
  whatWeDo?: HomeSection & { intro?: string | null; items: HomeTile[] };
  process: HomeSection & {
    intro?: string | null;
    items: HomeTile[];
  };
  testingProcess?: { items: HomeTile[] };
  whyUs: HomeSection & { intro?: string | null; items: HomeTile[] };
  testimonials: HomeSection;
  fieldwork?: HomeSection & { intro?: string | null };
  promos: { visible: boolean; items: (HomeTile & { eyebrow?: string | null; image?: string | null; imageAlt?: string | null })[] };
  faq: HomeSection & { intro?: string | null; items: { q: string; a: string }[] };
}

export type ServiceKind = 'certification' | 'testing' | 'registration' | 'quality';

export interface Seo {
  title?: string;
  description?: string;
  keyword?: string;
  ogTitle?: string;
  ogDescription?: string;
  image?: string;
  canonical?: string;
  robots?: string;
  reviewer?: string;
  reviewerCredentials?: string;
  reviewedAt?: string;
  /** Admin "Rich snippets": add to the automatic structured data, or replace it. */
  schemaMode?: 'add' | 'replace';
  schema?: Record<string, unknown>[];
}

type SectionKey = 'body' | 'documents' | 'steps' | 'timeline' | 'costs' | 'validity' | 'faqs' | 'products';

export interface ServiceDetail {
  seoTitle?: string;
  seoDescription?: string;
  eyebrow?: string;
  headline?: string;
  lead?: string;
  keyFacts?: { label: string; value: string }[];
  includes?: string[];
  overview?: string[];
  /** Rich-text introduction (HTML, sanitised by the backend). */
  body?: string;
  sections?: { label?: string; heading: string; html: string }[];
  timeline?: { phase: string; duration: string }[];
  headings?: Partial<Record<SectionKey, string>>;
  intros?: Partial<Record<SectionKey, string>>;
  documents?: { title: string; items: string[] }[];
  steps?: { title: string; duration?: string; text: string; icon?: string | null; iconImage?: string | null }[];
  consequences?: { title: string; text: string; items: { title: string; text: string }[] };
  table?: { title: string; note?: string; columns: string[]; rows: string[][] };
  tip?: { title: string; text: string };
  costs?: { title: string; text: string }[];
  validity?: string[];
  faqs?: { q: string; a: string }[];
  related?: string[];
}

export interface Service {
  path: string;
  url: string;
  slug: string;
  title: string;
  kind: ServiceKind;
  region: string | null;
  group: string | null;
  testCategories?: string[];
  summary: string;
  featured: boolean;
  icon?: string | null;
  image?: string | null;
  detail?: ServiceDetail | null;
  updatedAt?: string | null;
  seo?: Seo;
  /** The "Trusted by" client logo strip after the content, ticked per page in the admin. */
  showClientLogos?: boolean;
}

export interface Industry {
  slug: string;
  url: string;
  name: string;
  icon: string;
  iconImage?: string | null;
  /** Uploaded photo (absolute URL); the bundled photo for the slug is used when empty. */
  image?: string | null;
  summary: string;
  intro: string;
  highlights: string[];
  /** Option 1: marks, approvals and registrations for the industry. */
  certification: { text?: string | null; services: Service[] };
  /** Option 2: lab tests, plus the product-testing industries that list every test. */
  testing: { text?: string | null; services: Service[]; categories: { slug: string; label: string; url: string; count: number }[] };
  seo?: Seo;
  /** The "Trusted by" client logo strip after the content, ticked per page in the admin. */
  showClientLogos?: boolean;
}

export interface Testimonial {
  id?: number;
  name: string;
  context: string;
  rating: number;
  quote: string;
  photo?: string | null;
}

export type PostType = 'blog' | 'news' | 'audit' | 'update';

export interface Author {
  name: string;
  slug: string;
  url: string;
  role?: string | null;
  company?: string | null;
  shortBio?: string | null;
  bio?: string | null;
  photo?: string | null;
  credentials?: string | null;
  experienceYears?: number | null;
  expertise?: string[];
  links?: Record<string, string>;
}

export interface Scheme {
  name: string;
  slug: string;
}

export type WorkType = 'audit' | 'inspection' | 'case_study';

export interface Post {
  type: PostType;
  slug: string;
  url: string;
  title: string;
  excerpt: string;
  image: string | null;
  imageAlt: string;
  imageWidth?: number | null;
  imageHeight?: number | null;
  category: { name: string; slug: string } | null;
  /** Industry the post is about; the resource lists filter by it. */
  industry?: { name: string; slug: string } | null;
  /** Certifications an audit covers (BIS ISI, MSDS…); the Audits list and landing pages filter by them. */
  schemes?: Scheme[];
  workType?: WorkType | null;
  workTypeLabel?: string | null;
  author: Author | null;
  readingTime: number | null;
  publishedAt: string | null;
  updatedAt: string | null;
  content?: string | null;
  faqs?: { q: string; a: string }[];
  seo?: Seo;
  /** The "Trusted by" client logo strip after the content, ticked per page in the admin. */
  showClientLogos?: boolean;
}

export interface EventItem {
  slug: string;
  url: string;
  title: string;
  type: string;
  typeLabel?: string;
  startsAt: string;
  endsAt: string | null;
  location: string | null;
  industry?: { name: string; slug: string } | null;
  /** Certifications exhibitors usually need; the Events list filters by them. */
  schemes?: Scheme[];
  externalUrl: string | null;
  price: string | null;
  excerpt: string | null;
  image: string | null;
  imageAlt?: string | null;
  imageWidth?: number | null;
  imageHeight?: number | null;
  updatedAt?: string | null;
  content?: string | null;
  seo?: Seo;
  /** The "Trusted by" client logo strip after the content, ticked per page in the admin. */
  showClientLogos?: boolean;
}

export type RegulationStatus = 'mandatory' | 'upcoming' | 'deferred' | 'cdsco' | 'voluntary' | 'rescinded';

/** One Indian Standard on a standards page, with the compliance data imported from the BIS / BEE library. */
export interface StandardProduct {
  is_number: string;
  name: string;
  image?: string;
  status?: RegulationStatus;
  since?: string;
  mandatory_from?: string;
  order?: string;
  hsn?: string;
  sector?: string;
  fee_large?: number;
  fee_medium?: number;
  fee_small?: number;
  fee_micro?: number;
  unit_fee?: string;
  test_min?: number;
  test_max?: number;
  labs?: number;
  lab_names?: string;
  note?: string;
}

/** One row of /resources/standards/products.json, behind the BIS fee finder. */
export interface FinderProduct {
  /** Product name */
  n: string;
  /** IS number, e.g. "IS 302 (Part 1) : 2024" */
  i: string;
  /** HSN code */
  h?: string;
  s: 'isi' | 'crs';
  /** "Mandatory since 3 Jul 2013" */
  w?: string;
  /** ISI minimum marking fee per year: micro, small, medium, large */
  f?: [number, number, number, number];
  /** ISI marking fee per unit, e.g. "₹2 per piece" */
  u?: string;
  /** Lowest lab testing charge */
  t?: number;
}

export interface Standard {
  title: string;
  slug: string;
  path: string;
  url: string;
  category?: { title: string; slug: string; url: string } | null;
  summary: string;
  scheme: 'isi' | 'crs' | 'bee' | 'iso' | null;
  regulationStatus: RegulationStatus | null;
  statusLabel: string | null;
  mandatorySince: string | null;
  mandatoryFrom: string | null;
  sector: string | null;
  isNumbers: string[];
  isKeys: string[];
  productCount: number;
  feeMin: number | null;
  feeMax: number | null;
  testMin: number | null;
  description?: string | null;
  productsTitle?: string | null;
  products?: StandardProduct[];
  facts?: { label: string; value: string }[];
  faqs?: { q: string; a: string }[];
  image?: string | null;
  imageAlt?: string | null;
  seo?: Seo;
  /** The "Trusted by" client logo strip after the content, ticked per page in the admin. */
  showClientLogos?: boolean;
  updatedAt?: string | null;
}

export interface StandardCategory {
  title: string;
  slug: string;
  group: 'mandatory' | 'voluntary' | 'bee' | 'management';
  groupLabel: string | null;
  url: string;
  description: string | null;
  image: string | null;
  seo: Seo;
  standards: Standard[];
}

export interface PageRecord {
  path: string;
  url: string;
  template: 'default' | 'policy' | 'landing' | 'hub' | 'about';
  title: string;
  excerpt: string | null;
  image: string | null;
  updatedAt: string | null;
  content?: string | null;
  blocks?: ({ sections?: Record<string, any>[] } & AboutBlocks) | null;
  seo?: Seo;
  /** The "Trusted by" client logo strip after the content, ticked per page in the admin. */
  showClientLogos?: boolean;
}

/** Sections of the "About us" page template; each is hidden when it has no items. */
export interface AboutBlocks {
  milestones?: { year: string; text: string }[];
  stats_heading?: string | null;
  stats?: { value: string; label: string }[];
  values_heading?: string | null;
  values_intro?: string | null;
  values_icon_align?: IconAlign | null;
  values?: { icon?: string | null; iconImage?: string | null; title: string; text: string }[];
  reasons_heading?: string | null;
  reasons_intro?: string | null;
  reasons_icon_align?: IconAlign | null;
  reasons?: { icon?: string | null; iconImage?: string | null; title: string; text: string }[];
}

/** A Google Ads landing page; every section is optional and omitted when empty. */
export interface LandingPageRecord {
  path: string;
  url: string;
  updatedAt: string | null;
  servicePath?: string | null;
  content?: {
    hero?: {
      headline?: string;
      subheadline?: string;
      points?: string[];
      price_note?: string;
      reply_note?: string;
      cta?: string;
      form_title?: string;
      form_subtitle?: string;
      form_note?: string;
      image?: string;
      image_alt?: string;
    };
    stats?: { value: string; label: string; icon?: string; iconImage?: string }[];
    stats_icon_align?: IconAlign;
    trust?: { logos_title?: string; hide_logos?: boolean; hide_rating?: boolean };
    intro?: { heading?: string; text?: string; points?: string[]; image?: string; image_alt?: string; image_caption?: string };
    /** `schemes` / `types`: show only audits with these certification tags and work types. */
    work?: { hidden?: boolean; heading?: string; intro?: string; schemes?: string[]; types?: WorkType[]; posts?: string[]; photos?: { image: string; alt: string; caption?: string }[] };
    video?: { url?: string; heading?: string; title?: string; text?: string };
    /** Beside the card title: `image` (a mark or logo, e.g. the ISI mark), else `iconImage`, else the named `icon`. */
    options?: {
      heading?: string;
      intro?: string;
      price_label?: string;
      icon_align?: IconAlign;
      items?: { title: string; audience?: string; timeline?: string; price?: string; points?: string[]; icon?: string; iconImage?: string; image?: string; image_alt?: string; image_caption?: string }[];
    };
    compare?: { heading?: string; intro?: string; bad_title?: string; bad?: string[]; good_title?: string; good?: string[] };
    checklist?: { heading?: string; intro?: string; groups?: { title?: string; items?: string[] }[]; note?: string; image?: string; image_alt?: string };
    disclaimer?: string;
    steps?: { heading?: string; icon_align?: IconAlign; items?: { title: string; text?: string; icon?: string; iconImage?: string }[] };
    benefits?: { heading?: string; image?: string; image_alt?: string; icon_align?: IconAlign; items?: { icon?: string; iconImage?: string; title: string; text?: string }[] };
    related?: { heading?: string; intro?: string; keywords?: string[]; ask?: string };
    /** The reviews themselves are the website-wide testimonials. */
    reviews?: { heading?: string; hidden?: boolean };
    faqs?: { heading?: string; intro?: string; items?: { question: string; answer: string }[] };
    /** The offices themselves come from Website → Offices. */
    offices?: { hidden?: boolean; heading?: string; intro?: string };
    closing?: { heading?: string; text?: string };
    finder?: {
      enabled?: boolean;
      heading?: string;
      intro?: string;
      suggestions?: string[];
      crs_fee_micro?: number | string;
      crs_fee_small?: number | string;
      crs_fee_medium?: number | string;
      crs_fee_large?: number | string;
      crs_test_from?: number | string;
      isi_application_fee?: number | string;
      isi_licence_fee?: number | string;
      isi_audit_fee?: number | string;
      consulting_note?: string;
    };
  };
  seo?: Seo;
}

export interface Vacancy {
  slug: string;
  url: string;
  title: string;
  department: string | null;
  employmentType: 'FULL_TIME' | 'PART_TIME' | 'CONTRACTOR' | 'TEMPORARY' | 'INTERN';
  employmentTypeLabel: string;
  workplace: 'onsite' | 'hybrid' | 'remote';
  workplaceLabel: string;
  city: string | null;
  region: string | null;
  country: string;
  location: string;
  experience: string | null;
  experienceMin: number | null;
  experienceMax: number | null;
  salary: { min: number | null; max: number | null; currency: 'INR'; unit: 'YEAR' } | null;
  openings: number;
  summary: string | null;
  description: string | null;
  skills: string[];
  postedAt: string | null;
  closesAt: string | null;
  updatedAt: string | null;
}