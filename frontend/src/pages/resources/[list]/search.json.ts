import type { APIRoute, GetStaticPaths } from 'astro';
import { POST_TYPES, formatDate, getPosts, workTag } from '../../../lib/content';
import { responsive } from '../../../lib/images';
import type { PostType } from '../../../lib/types';

export const getStaticPaths = (() =>
  (Object.keys(POST_TYPES) as PostType[]).map((type) => ({ params: { list: POST_TYPES[type].listPath.split('/').pop()! }, props: { type } }))) satisfies GetStaticPaths;

/**
 * Compact index behind the search box on each resource list, fetched on first use.
 * Audits also carry their certification tags (k), work type (w) and a thumbnail (i) for the filters.
 */
export const GET: APIRoute = async ({ props }) => {
  const audit = props.type === 'audit';
  const entries = await Promise.all((await getPosts(props.type as PostType)).map(async (p) => ({
    t: p.title,
    u: p.url,
    e: p.excerpt,
    c: (audit && workTag(p)) || p.industry?.name || p.category?.name || '',
    s: p.industry?.slug ?? '',
    d: p.publishedAt ? formatDate(p.publishedAt) : '',
    ...(audit && {
      k: p.schemes?.map((s) => s.slug) ?? [],
      w: p.workType ?? '',
      i: (await responsive(p.image, [240]))?.src ?? '',
    }),
  })));

  return new Response(JSON.stringify(entries), { headers: { 'Content-Type': 'application/json' } });
};
