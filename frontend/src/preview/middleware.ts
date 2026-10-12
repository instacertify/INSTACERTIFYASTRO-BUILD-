import { defineMiddleware } from 'astro:middleware';
import { setOverrides } from '../lib/content';
import { overridesFor, type Preview } from './overrides';

const API_URL = (import.meta.env.API_URL ?? '').replace(/\/$/, '');

// lib/content holds one set of overrides at a time, so drafts render one after another.
let queue: Promise<unknown> = Promise.resolve();

const BANNER = `<div role="status" style="background:#111827;color:#fff;font:600 14px/1.5 system-ui,sans-serif;padding:10px 16px;text-align:center">
Preview – not live. This is how the page will look once saved and published. Forms are switched off.
</div>`;

const NO_FORMS = `<script>document.addEventListener('submit',function(e){e.preventDefault();e.stopImmediatePropagation();alert('Forms are switched off in preview.');},true);</script>`;

function message(status: number, text: string) {
  return new Response(`<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Preview</title><p style="font:16px system-ui;margin:3rem auto;max-width:32rem">${text}</p>`, {
    status,
    headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Robots-Tag': 'noindex, nofollow', 'Cache-Control': 'no-store' },
  });
}

export const onRequest = defineMiddleware(async (context, next) => {
  const { pathname } = context.url;
  if (pathname.startsWith('/_preview/')) return next();
  const token = pathname.match(/^\/preview\/([A-Za-z0-9]{20,80})$/)?.[1];
  // Only admin previews are served here; the public site is the static build.
  if (!token) return message(404, 'Page not found.');

  const res = await fetch(`${API_URL}/api/v1/preview/${token}`, { headers: { Accept: 'application/json' } }).catch(() => null);
  if (!res?.ok) return message(410, 'This preview link has expired. Click Preview in the admin again for a fresh one.');
  const preview = (await res.json()).data as Preview;

  const render = queue.then(async () => {
    setOverrides(overridesFor(preview));
    try {
      context.locals.preview = preview;
      const page = await next();
      const html = (await page.text())
        .replace(/<meta name="robots"[^>]*>/, '')
        .replace('</head>', '<meta name="robots" content="noindex, nofollow"></head>')
        .replace(/<body([^>]*)>/, `<body$1>${BANNER}`)
        .replace('</body>', `${NO_FORMS}</body>`);
      return new Response(html, {
        status: page.status,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Robots-Tag': 'noindex, nofollow', 'Cache-Control': 'no-store' },
      });
    } finally {
      setOverrides(null);
    }
  });
  queue = render.catch(() => undefined);
  return render;
});
