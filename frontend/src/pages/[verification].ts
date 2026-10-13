import type { APIRoute, GetStaticPaths } from 'astro';
import { getVerificationFiles } from '../lib/verification';

const TYPES: Record<string, string> = { html: 'text/html', txt: 'text/plain', xml: 'application/xml' };

export const getStaticPaths: GetStaticPaths = async () =>
  (await getVerificationFiles()).map((file) => ({ params: { verification: file.name }, props: { content: file.content } }));

export const GET: APIRoute = ({ params, props }) => {
  const extension = String(params.verification).split('.').pop()!.toLowerCase();
  return new Response(props.content as string, { headers: { 'Content-Type': `${TYPES[extension]}; charset=utf-8` } });
};
