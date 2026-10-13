// Writes .br and .gz copies of text files in dist/ so Caddy can serve them without compressing per request
// (Caddy only compresses on the fly with gzip/zstd; Brotli is about 20% smaller for HTML).
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { readFile, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { promisify } from 'node:util';
import { brotliCompress, constants, gzip } from 'node:zlib';

const DIST = resolve('dist');
const TEXT = /\.(html|css|js|mjs|json|xml|svg|txt|webmanifest)$/;
const MIN_BYTES = 1024;

const br = promisify(brotliCompress);
const gz = promisify(gzip);
const walk = (dir) => readdirSync(dir).flatMap((n) => (statSync(join(dir, n)).isDirectory() ? walk(join(dir, n)) : [join(dir, n)]));
const all = walk(DIST);

// A page whose API data failed to load renders as a bare doctype; never ship those. Small files with text in them
// are site verification files from the admin (e.g. google1234abcd.html).
const blank = all.filter((f) => f.endsWith('.html') && statSync(f).size < 100 && !readFileSync(f, 'utf8').replace(/<[^>]*>/g, '').trim());
if (blank.length) {
  console.error(`precompress: ${blank.length} blank pages, the API probably failed during the build:\n  ${blank.join('\n  ')}`);
  process.exit(1);
}

const files = all.filter((f) => TEXT.test(f) && statSync(f).size >= MIN_BYTES);

const started = Date.now();
let saved = 0;
const queue = [...files];
const worker = async () => {
  for (let file = queue.pop(); file; file = queue.pop()) {
    const data = await readFile(file);
    const [brotli, gzipped] = await Promise.all([
      br(data, { params: { [constants.BROTLI_PARAM_QUALITY]: 10, [constants.BROTLI_PARAM_SIZE_HINT]: data.length } }),
      gz(data, { level: 9 }),
    ]);
    if (brotli.length < data.length) await writeFile(`${file}.br`, brotli);
    if (gzipped.length < data.length) await writeFile(`${file}.gz`, gzipped);
    saved += data.length - brotli.length;
  }
};
await Promise.all(Array.from({ length: 8 }, worker));
console.log(`precompress: ${files.length} files, Brotli saves ${Math.round(saved / 1048576)} MB, ${((Date.now() - started) / 1000).toFixed(1)}s`);
