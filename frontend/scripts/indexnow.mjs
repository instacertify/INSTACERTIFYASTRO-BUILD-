/**
 * IndexNow: tells Bing, Yandex and the other IndexNow search engines which pages a publish added or changed, so they
 * are crawled within minutes instead of on the engine's own schedule. Google does not take part; it reads the sitemap.
 * Used by publish-server.mjs after each release; does nothing until an IndexNow key is set in the admin.
 */
const ENDPOINT = 'https://api.indexnow.org/indexnow';
const BATCH = 10_000;

const decode = (s) => s.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&apos;/g, "'");

/** @returns {Map<string, string>} page URL → lastmod ('' when the sitemap gives none) */
export function sitemapEntries(xml) {
  const entries = new Map();
  for (const [, block] of xml.matchAll(/<url>([\s\S]*?)<\/url>/g)) {
    const loc = block.match(/<loc>([^<]+)<\/loc>/)?.[1];
    if (loc) entries.set(decode(loc.trim()), block.match(/<lastmod>([^<]+)<\/lastmod>/)?.[1]?.trim() ?? '');
  }
  return entries;
}

/** Pages that are new in the next sitemap, or whose lastmod moved. */
export function changedUrls(previousXml, nextXml) {
  const before = sitemapEntries(previousXml);
  return [...sitemapEntries(nextXml)].filter(([url, lastmod]) => before.get(url) !== lastmod).map(([url]) => url);
}

export async function submit({ key, siteUrl, urls }) {
  const origin = new URL(siteUrl).origin;
  const own = urls.filter((url) => url.startsWith(`${origin}/`) || url === origin);
  for (let i = 0; i < own.length; i += BATCH) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({ host: new URL(origin).host, key, keyLocation: `${origin}/${key}.txt`, urlList: own.slice(i, i + BATCH) }),
    });
    await response.text();
    if (!response.ok) throw new Error(`IndexNow answered ${response.status}`);
  }
  return own.length;
}
