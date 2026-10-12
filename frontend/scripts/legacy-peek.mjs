// Prints headings, word count and images for live legacy pages: node scripts/legacy-peek.mjs /about /faq
const strip = (s) => s.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/&nbsp;|&amp;/g, ' ').replace(/\s+/g, ' ').trim();

for (const path of process.argv.slice(2)) {
  const res = await fetch(`https://instacertify.com${path}`, { redirect: 'manual' });
  const html = res.status === 200 ? await res.text() : '';
  const main = html.match(/<main[\s\S]*?<\/main>/i)?.[0] ?? html;
  const headings = [...main.matchAll(/<h([1-3])[^>]*>([\s\S]*?)<\/h\1>/gi)].map((m) => `h${m[1]}: ${strip(m[2])}`);
  const imgs = [...main.matchAll(/<img[^>]+src="([^"]+)"/gi)].map((m) => m[1]);
  console.log(`\n=== ${path} [${res.status}${res.headers.get('location') ? ' -> ' + res.headers.get('location') : ''}] words=${strip(main).split(' ').length} imgs=${imgs.length}`);
  console.log(`title: ${strip(html.match(/<title>([\s\S]*?)<\/title>/i)?.[1] ?? '')}`);
  headings.slice(0, 30).forEach((h) => console.log('  ' + h));
  if (process.env.FULL) console.log(strip(main).slice(0, 6000));
}
