// @ts-check
// Admin preview server: renders unsaved drafts with the site's own components, on request.
// The public site stays the static build from astro.config.mjs; Caddy only sends /preview/* and /_preview/* here.
import { defineConfig } from 'astro/config';
import node from '@astrojs/node';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  site: 'https://instacertify.com',
  trailingSlash: 'never',
  output: 'server',
  adapter: node({ mode: 'standalone' }),
  outDir: './dist-preview',
  build: {
    // Kept apart from the static site's /_astro files, which Caddy serves from the static build.
    assets: '_preview',
    inlineStylesheets: 'auto',
  },
  image: {
    endpoint: { route: '/_preview/image' },
    responsiveStyles: true,
    remotePatterns: [{ protocol: 'https' }, { protocol: 'http', hostname: 'localhost' }, { protocol: 'http', hostname: '127.0.0.1' }],
  },
  vite: {
    plugins: [tailwindcss()],
    define: { 'import.meta.env.PREVIEW_SERVER': 'true' },
  },
  integrations: [
    {
      name: 'instacertify-preview',
      hooks: {
        'astro:config:setup': ({ injectRoute, addMiddleware }) => {
          injectRoute({ pattern: '/preview/[token]', entrypoint: new URL('./src/preview/Preview.astro', import.meta.url) });
          addMiddleware({ entrypoint: new URL('./src/preview/middleware.ts', import.meta.url), order: 'pre' });
        },
      },
    },
  ],
});
