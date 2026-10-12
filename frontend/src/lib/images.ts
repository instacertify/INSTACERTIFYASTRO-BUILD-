import { getImage, inferRemoteSize } from 'astro:assets';
import { linkResolver } from './content';

export interface Responsive {
  src: string;
  srcset?: string;
  width?: number;
  height?: number;
}

const RASTER = /\.(jpe?g|png|webp|avif)(?:[?#]|$)/i;
const done = new Map<string, Promise<Responsive>>();
const natural = new Map<string, ReturnType<typeof inferRemoteSize>>();

async function build(src: string, widths: number[], quality: number): Promise<Responsive> {
  if (!natural.has(src)) natural.set(src, inferRemoteSize(src));
  const size = await natural.get(src)!;
  // A width above the original would only be labelled bigger, not made bigger.
  const fitting = widths.filter((w) => w < size.width);
  if (fitting.length < widths.length) fitting.push(size.width);
  const width = Math.max(...fitting);
  const height = Math.round((width / size.width) * size.height);
  const image = await getImage({ src, width, height, widths: fitting, format: 'webp', quality });
  return { src: image.src, srcset: image.srcSet.attribute || undefined, width, height };
}

/**
 * Resized WebP copies of a CMS image, made once per build however many pages use it.
 * Vector/animated images and images that can't be fetched keep their original URL.
 */
export function responsive(src: string | null | undefined, widths: number[], quality = 72): Promise<Responsive | null> {
  if (!src) return Promise.resolve(null);
  if (!RASTER.test(src)) return Promise.resolve({ src });

  const key = `${src}|${widths.join(',')}|${quality}`;
  if (!done.has(key)) {
    done.set(
      key,
      build(src, widths, quality)
        .catch((error) => {
          console.warn(`Image not optimised (${src}): ${error}`);
          return { src };
        }),
    );
  }
  return done.get(key)!;
}

/**
 * A CMS image shown at a fixed CSS height (logos, icons), stored at twice that height for sharp screens.
 * Vector images and images that can't be fetched keep their original URL.
 */
export async function atHeight(src: string | null | undefined, height: number, format: 'webp' | 'png' = 'webp'): Promise<Responsive | null> {
  if (!src) return null;
  if (!RASTER.test(src)) return { src, height };
  try {
    if (!natural.has(src)) natural.set(src, inferRemoteSize(src));
    const size = await natural.get(src)!;
    const stored = Math.min(height * 2, size.height);
    const image = await getImage({ src, height: stored, width: Math.round((size.width * stored) / size.height), format, quality: 80 });
    return { src: image.src, width: Math.round((size.width * height) / size.height), height };
  } catch (error) {
    console.warn(`Image not optimised (${src}): ${error}`);
    return { src, height };
  }
}

/**
 * A logo sized by area instead of height, so long wordmarks and square badges carry the same visual weight.
 * The height is kept between min and max; vector logos use the middle of that range.
 */
export async function atArea(src: string | null | undefined, area: number, min: number, max: number): Promise<Responsive | null> {
  if (!src) return null;
  if (!RASTER.test(src)) return { src, height: Math.round((min + max) / 2) };
  try {
    if (!natural.has(src)) natural.set(src, inferRemoteSize(src));
    const size = await natural.get(src)!;
    const height = Math.round(Math.min(max, Math.max(min, Math.sqrt(area / (size.width / size.height)))));
    return atHeight(src, height);
  } catch {
    return atHeight(src, Math.round((min + max) / 2));
  }
}

const BODY_WIDTHS = [400, 760, 1140];

/** Points rich-text links straight at the page the admin's Redirects screen sends them to. */
async function resolveLinks(html: string): Promise<string> {
  if (!html.includes('href="')) return html;
  const resolve = await linkResolver();
  return html.replace(/(\shref=")((?:https?:\/\/(?:www\.)?instacertify\.com)?\/[^"]*)"/gi, (all, open: string, href: string) => {
    const to = resolve(href);
    return to === href ? all : `${open}${to}"`;
  });
}

/** Adds srcset/sizes to the uploaded images inside rich-text HTML and resolves redirected links; everything else is kept. */
export async function responsiveHtml(input: string | null | undefined, sizes: string): Promise<string> {
  if (!input) return '';
  const html = await resolveLinks(input);
  if (!html.includes('<img')) return html;
  const tags = [...new Set(html.match(/<img\b[^>]*>/gi) ?? [])];
  const rewritten = await Promise.all(
    tags.map(async (tag) => {
      if (/\ssrcset=/i.test(tag)) return tag;
      const src = tag.match(/\ssrc="([^"]+)"/i)?.[1];
      if (!src || !src.includes('/storage/')) return tag;
      const image = await responsive(src.replace(/&amp;/g, '&'), BODY_WIDTHS);
      if (!image?.srcset) return tag;
      const declared = Number(tag.match(/\swidth="(\d+)"/i)?.[1]) || 0;
      const fit = declared && declared < 760 ? `min(${declared}px, 100vw)` : sizes;
      let out = tag.replace(/\ssrc="[^"]+"/i, ` src="${image.src}" srcset="${image.srcset}" sizes="${fit}"`);
      if (!/\sloading=/i.test(out)) out = out.replace(/^<img/i, '<img loading="lazy"');
      if (!/\sdecoding=/i.test(out)) out = out.replace(/^<img/i, '<img decoding="async"');
      return out;
    }),
  );
  return tags.reduce((acc, tag, i) => (tag === rewritten[i] ? acc : acc.split(tag).join(rewritten[i])), html);
}
