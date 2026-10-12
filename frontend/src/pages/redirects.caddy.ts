import type { APIRoute } from 'astro';
import { getRedirects } from '../lib/content';

/**
 * Redirects managed in the admin, emitted as Caddy directives and imported by infra/caddy/Caddyfile.
 * "Same page" entries (status 200) become internal rewrites: the old URL answers with the target page, no redirect.
 */
export const GET: APIRoute = async () => {
  const lines = (await getRedirects())
    .filter((r) => r.from !== r.to && /^\/[\w\-./]*$/.test(r.from) && /^(\/|https?:\/\/)\S*$/.test(r.to))
    .filter((r) => r.status !== 200 || r.to.startsWith('/'))
    .map((r) =>
      r.status === 200
        ? `rewrite ${r.from} ${r.to.split(/[?#]/)[0]}`
        : `redir ${r.from} ${r.to} ${r.status === 302 ? 'temporary' : 'permanent'}`,
    );

  return new Response(lines.join('\n') + '\n', { headers: { 'Content-Type': 'text/plain' } });
};
