// @ts-check
import { defineConfig } from 'astro/config';
import { existsSync, readFileSync } from 'node:fs';

import tailwindcss from '@tailwindcss/vite';
import sitemap from '@astrojs/sitemap';

const SITE = 'https://instacertify.com';

/**
 * The sitemap integration runs after pages are written, so each URL's built HTML can be inspected.
 * @param {string} url
 */
function builtHead(url) {
  const path = new URL(url).pathname.replace(/\/$/, '') || '/index';
  const file = `./dist${path}.html`;
  return existsSync(file) ? readFileSync(file, 'utf8').split('</head>')[0] : '';
}

export default defineConfig({
  site: SITE,
  trailingSlash: 'never',
  build: {
    format: 'file',
    // The shared stylesheet (~90 KB) is cached once instead of being repeated in every page.
    inlineStylesheets: 'auto',
  },
  prefetch: {
    prefetchAll: true,
    defaultStrategy: 'hover',
  },
  image: {
    responsiveStyles: true,
    // CMS uploads (hero etc.) are optimised at build time from the API's media host.
    remotePatterns: [{ protocol: 'https' }, { protocol: 'http', hostname: 'localhost' }, { protocol: 'http', hostname: '127.0.0.1' }],
  },
  vite: {
    plugins: [tailwindcss()],
  },
  integrations: [
    sitemap({
      // Only pages that are their own canonical URL: duplicates such as the old root-level industry pages are left out.
      filter: (url) => {
        if (/\/(404|redirects\.caddy)$|\/page\/\d+$|\.(html|txt|xml|json)$/.test(url)) return false;
        const head = builtHead(url);
        const canonical = head.match(/<link rel="canonical" href="([^"]+)"/)?.[1];
        return !/<meta name="robots" content="noindex/.test(head) && (!canonical || canonical.replace(/\/$/, '') === url.replace(/\/$/, ''));
      },
      serialize(item) {
        const modified = builtHead(item.url).match(/<meta property="article:modified_time" content="([^"]+)"/)?.[1];
        return modified ? { ...item, lastmod: new Date(modified).toISOString() } : item;
      },
    }),
  ],
});
