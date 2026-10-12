/**
 * Publishes the website: rebuilds the static site from the admin's content and swaps it in, so saved edits and
 * uploaded images go live without a server deploy. Runs in the "builder" container; Laravel calls POST /publish.
 *
 * Releases are written to SITE_DIR/releases/<id> and SITE_DIR/current is switched to the new one in a single
 * rename, so visitors never see a half-copied site. A build that fails, or comes out much smaller than the live
 * one (e.g. the API dropped requests), is not published and the live site stays as it is.
 */
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { timingSafeEqual } from 'node:crypto';
import { cp, mkdir, readdir, readFile, readlink, rename, rm, symlink, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { basename, join } from 'node:path';

const SITE_DIR = process.env.SITE_DIR ?? '/site';
const TOKEN = process.env.PUBLISH_TOKEN ?? '';
const API_URL = (process.env.API_URL ?? '').replace(/\/$/, '');
const PORT = Number(process.env.PORT ?? 8787);
const MIN_SHARE_OF_LIVE_PAGES = 0.8;

const state = { status: 'idle', queued: false, startedAt: null, finishedAt: null, publishedAt: null, pages: null, error: null };

const log = (message) => console.log(`[publish] ${new Date().toISOString()} ${message}`);
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));

async function waitForApi() {
  for (let attempt = 1; attempt <= 60; attempt++) {
    try {
      const response = await fetch(`${API_URL}/api/v1/site`, { headers: { Accept: 'application/json' } });
      // Reading the body releases the connection; an unread one can crash Node's fetch when the server closes it.
      const body = await response.text();
      if (response.ok && body.includes('"data"')) return;
    } catch {}
    await sleep(10_000);
  }
  throw new Error(`The content API (${API_URL}) did not answer for 10 minutes.`);
}

function run(command, args) {
  return new Promise((resolve, reject) => {
    const tail = [];
    const child = spawn(command, args, {
      env: { ...process.env, REQUIRE_API: '1' },
      stdio: ['ignore', 'pipe', 'pipe'],
      shell: process.platform === 'win32',
    });
    child.on('error', reject);
    const keep = (chunk) => {
      process.stdout.write(chunk);
      tail.push(...chunk.toString().split('\n').filter(Boolean));
      tail.splice(0, Math.max(0, tail.length - 15));
    };
    child.stdout.on('data', keep);
    child.stderr.on('data', keep);
    child.on('close', (code) => (code === 0 ? resolve() : reject(new Error(`Build failed (exit ${code}):\n${tail.join('\n')}`))));
  });
}

async function countPages(dir) {
  let count = 0;
  for (const entry of await readdir(dir, { withFileTypes: true, recursive: true })) {
    if (entry.isFile() && entry.name.endsWith('.html')) count++;
  }
  return count;
}

async function liveRelease() {
  try {
    return JSON.parse(await readFile(join(SITE_DIR, 'current', 'release.json'), 'utf8'));
  } catch {
    return {};
  }
}

const livePages = async () => (await liveRelease()).pages ?? 0;

async function publish() {
  await waitForApi();
  await rm('dist', { recursive: true, force: true });
  await run('npm', ['run', 'build']);

  if (!existsSync('dist/index.html') || !existsSync('dist/404.html')) throw new Error('The build has no home page or 404 page.');
  const pages = await countPages('dist');
  const livePageCount = await livePages();
  if (pages < livePageCount * MIN_SHARE_OF_LIVE_PAGES) {
    throw new Error(`The build has ${pages} pages but the live site has ${livePageCount}; not published in case content failed to load. Publish again, or check the API.`);
  }

  // Only the live release is kept while the new one is copied; it stays as the previous version afterwards.
  const liveId = await currentRelease();
  await mkdir(join(SITE_DIR, 'releases'), { recursive: true });
  for (const old of await readdir(join(SITE_DIR, 'releases'))) {
    if (old !== liveId) await rm(join(SITE_DIR, 'releases', old), { recursive: true, force: true });
  }

  const id = new Date().toISOString().replace(/[:.]/g, '-');
  const release = join(SITE_DIR, 'releases', id);
  try {
    await cp('dist', release, { recursive: true });
    await writeFile(join(release, 'release.json'), JSON.stringify({ id, pages, builtAt: new Date().toISOString() }));
    // Relative, so the link works wherever the volume is mounted (/site here, /srv/site in Caddy).
    await rm(join(SITE_DIR, 'current.next'), { force: true });
    await symlink(join('releases', id), join(SITE_DIR, 'current.next'));
    await rename(join(SITE_DIR, 'current.next'), join(SITE_DIR, 'current'));
  } catch (error) {
    await rm(release, { recursive: true, force: true });
    throw error;
  }
  return pages;
}

async function currentRelease() {
  try {
    return basename(await readlink(join(SITE_DIR, 'current')));
  } catch {
    return null;
  }
}

async function cycle() {
  if (state.status === 'building') {
    state.queued = true;
    return;
  }
  do {
    state.queued = false;
    Object.assign(state, { status: 'building', startedAt: new Date().toISOString(), error: null });
    log('building');
    try {
      const pages = await publish();
      Object.assign(state, { status: 'idle', pages, publishedAt: new Date().toISOString() });
      log(`published ${pages} pages`);
    } catch (error) {
      Object.assign(state, { status: 'failed', error: error instanceof Error ? error.message : String(error) });
      log(`not published: ${state.error}`);
    }
    state.finishedAt = new Date().toISOString();
  } while (state.queued);
}

function authorised(request) {
  const given = Buffer.from((request.headers.authorization ?? '').replace(/^Bearer\s+/i, ''));
  const expected = Buffer.from(TOKEN);
  return TOKEN.length >= 32 && given.length === expected.length && timingSafeEqual(given, expected);
}

createServer((request, response) => {
  const reply = (status, body) => {
    response.writeHead(status, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify(body));
  };
  if (!authorised(request)) return reply(401, { error: 'Unauthorised' });
  if (request.method === 'POST' && request.url === '/publish') {
    cycle();
    return reply(202, state);
  }
  if (request.method === 'GET' && request.url === '/status') return reply(200, state);
  reply(404, { error: 'Not found' });
}).listen(PORT, () => log(`listening on :${PORT}`));

if (!TOKEN || TOKEN.length < 32) log('SITE_PUBLISH_TOKEN is missing or shorter than 32 characters; publish requests are refused.');
const { builtAt, pages } = await liveRelease();
Object.assign(state, { publishedAt: builtAt ?? null, pages: pages ?? null });
// A new image may carry new templates, so every start (i.e. every deploy) publishes once.
cycle();
