/** Pulls each top-level list and table out of the HTML (nesting-aware), leaving a numbered placeholder on its own line. */
function holdBlocks(html: string) {
  const held: string[] = [];
  const open = /<(ul|ol|table)[\s>]/gi;
  let out = '';
  let from = 0;
  let m: RegExpExecArray | null;
  while ((m = open.exec(html))) {
    const tags = new RegExp(`<(/?)${m[1]}[\\s>]`, 'gi');
    tags.lastIndex = m.index;
    let depth = 0;
    let end = -1;
    let t: RegExpExecArray | null;
    while ((t = tags.exec(html))) {
      depth += t[1] ? -1 : 1;
      if (depth === 0) {
        end = html.indexOf('>', t.index) + 1;
        break;
      }
    }
    if (end <= 0) break;
    out += `${html.slice(from, m.index)}\n\n\u0000${held.length}\u0000\n\n`;
    held.push(html.slice(m.index, end));
    from = open.lastIndex = end;
  }
  return { text: out + html.slice(from), held };
}

/**
 * Older policy pages were imported with their headings as bold text inside one long paragraph
 * ("… <strong>2. Information We Collect</strong> We collect …"). This turns numbered bold runs into
 * H2s, lettered ones ("A. Initial Submission") into H3s and starts a new paragraph at each bold
 * "Label:", without changing any wording. Pages that already have H2s are returned as they are.
 */
export function structureHeadings(html: string): string {
  if (/<h2[\s>]/i.test(html) || !/<strong>\s*\d{1,2}\.\s+[^<\s]/.test(html)) return html;
  const { text, held } = holdBlocks(html);
  return text
    .replace(/<\/?p(\s[^>]*)?>/gi, '\n\n')
    .replace(/<strong>\s*(\d{1,2}\.\s+[^<]+?)\s*<\/strong>/g, '\n\n<h2>$1</h2>\n\n')
    .replace(/<strong>\s*(Introduction|Overview)\s*<\/strong>/g, '\n\n<h2>$1</h2>\n\n')
    .replace(/<strong>\s*([A-H]\.\s+[^<]+?)\s*<\/strong>/g, '\n\n<h3>$1</h3>\n\n')
    .replace(/(<strong>[^<]{2,60}:\s*<\/strong>)/g, '\n\n$1')
    .split(/\n{2,}/)
    .map((c) => c.trim())
    .filter(Boolean)
    .map((c) => (/^\u0000\d+\u0000$/.test(c) ? held[Number(c.slice(1, -1))] : /^<h[23]>/.test(c) ? c : `<p>${c}</p>`))
    .join('\n');
}

const ENTITIES: Record<string, string> = { amp: '&', quot: '"', apos: "'", '#39': "'", nbsp: ' ', rsquo: '’', lsquo: '‘', ndash: '–', mdash: '—' };
const plain = (html: string) =>
  html
    .replace(/<[^>]+>/g, '')
    .replace(/&(#?\w+);/g, (all, name: string) => ENTITIES[name] ?? all)
    .replace(/\s+/g, ' ')
    .trim();

/**
 * Editor HTML often jumps levels (an H4 straight under an H2) or leaves empty headings behind.
 * Drops the empty ones (image-only ones become paragraphs) and lifts each heading to at most one level below its parent, never above H2,
 * so screen readers and search engines get an unbroken outline. `base` is the heading the HTML sits under.
 */
export function fixHeadings(html: string | null | undefined, base = 1): string {
  if (!html) return '';
  const stack: { from: number; to: number }[] = [];
  return html.replace(/<h([1-6])(\s[^>]*)?>([\s\S]*?)<\/h\1>/gi, (all, n: string, attrs: string | undefined, inner: string) => {
    if (!plain(inner)) return /<img\b/i.test(inner) ? `<p>${inner}</p>` : '';
    const from = Number(n);
    while (stack.length && stack[stack.length - 1].from >= from) stack.pop();
    const parent = stack.at(-1)?.to ?? base;
    const to = Math.min(Math.max(from, 2), parent + 1, 6);
    stack.push({ from, to });
    return to === from ? all : `<h${to}${attrs ?? ''}>${inner}</h${to}>`;
  });
}

/** Gives every H2 an id (keeping existing ones) and returns the headings for an "On this page" list. */
export function outline(html: string) {
  const used = new Set<string>();
  const headings: { id: string; text: string }[] = [];
  const out = html.replace(/<h2(\s[^>]*)?>([\s\S]*?)<\/h2>/gi, (all, attrs: string | undefined, inner: string) => {
    const text = plain(inner);
    if (!text) return all;
    const existing = attrs && /\sid="([^"]+)"/.exec(attrs)?.[1];
    let id = existing || text.toLowerCase().replace(/^\d+\.\s*/, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'section';
    if (!existing) {
      const base = id;
      for (let n = 2; used.has(id); n++) id = `${base}-${n}`;
    }
    used.add(id);
    headings.push({ id, text });
    return existing ? all : `<h2${attrs ?? ''} id="${id}">${inner}</h2>`;
  });
  return { html: out, headings };
}
