import type { APIRoute } from 'astro';
import { getVerificationFiles } from '../lib/verification';

/**
 * Imported by infra/caddy/Caddyfile ahead of the ".html → clean URL" redirect: site verification checks
 * (e.g. Google's /google1234abcd.html) fetch the exact file name and fail on a redirect.
 */
export const GET: APIRoute = async () => {
  const lines = (await getVerificationFiles()).map((file) => `file_server /${file.name} {\n\tprecompressed br gzip\n}`);

  return new Response(lines.join('\n') + '\n', { headers: { 'Content-Type': 'text/plain' } });
};
