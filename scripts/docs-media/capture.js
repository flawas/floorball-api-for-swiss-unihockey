'use strict';
/**
 * Creates screenshots (frontend shortcodes + admin pages) and a short walkthrough video of the plugin,
 * running against the local Docker WordPress (docker-compose.yml). Free: Playwright + public Swiss Unihockey API.
 *
 * Env: WP_URL (default http://localhost:8000), OUT_DIR (default docs/media), CLUB_ID (optional, default 637 = HC Weggis Küssnacht),
 *      SEASON (optional; default = previous, complete season like verify_api.php).
 * Run from the repo root with Playwright resolvable (NODE_PATH) and ffmpeg installed.
 */
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const WP = process.env.WP_URL || 'http://localhost:8000';
const OUT = path.resolve(process.env.OUT_DIR || 'docs/media');
const API = 'https://api-v2.swissunihockey.ch/api/';
const DEFAULT_CLUB_ID = '637'; // HC Weggis Küssnacht
const cfg = JSON.parse(fs.readFileSync(path.join(__dirname, 'scenes.json'), 'utf8'));
const manifest = { generated: new Date().toISOString(), context: {}, screenshots: [], videos: [], skipped: [] };

const log = (...a) => console.log(...a);
const skip = (id, reason) => { log(`  skip ${id}: ${reason}`); manifest.skipped.push({ id, reason }); };

async function api(endpoint, params = {}) {
  const url = new URL(API + endpoint);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  const res = await fetch(url);
  if (!res.ok) throw new Error(`${endpoint}: HTTP ${res.status}`);
  return res.json();
}

function findLinkIds(node, page, out = []) {
  if (Array.isArray(node)) node.forEach((c) => findLinkIds(c, page, out));
  else if (node && typeof node === 'object') {
    if (node.page === page && node.ids && node.ids[0]) out.push(node.ids[0]);
    Object.values(node).forEach((c) => findLinkIds(c, page, out));
  }
  return out;
}

/** Same discovery as verify_api.php: IDs come from the API itself, so nothing rots when a season ends. */
async function discover() {
  const ctx = {};
  const [seasons, leagues, clubs] = await Promise.all(['seasons', 'leagues', 'clubs'].map((e) => api(e)));
  const se = seasons.entries, le = leagues.entries, cl = clubs.entries;
  ctx.season = process.env.SEASON || (se[1] || se[0]).set_in_context.season;
  ctx.league = le[0].set_in_context.league;
  ctx.game_class = le[0].set_in_context.game_class;
  const wanted = process.env.CLUB_ID || DEFAULT_CLUB_ID;
  const club = cl.find((c) => String(c.set_in_context.club_id) === wanted) || cl[0];
  ctx.club_id = String(club.set_in_context.club_id);
  ctx.club_name = club.text || '';
  const tryStep = async (name, fn) => { try { await fn(); } catch (e) { log(`  discovery ${name}: ${e.message}`); } };
  await tryStep('group', async () => {
    const g = await api('groups', { season: ctx.season, league: ctx.league, game_class: ctx.game_class, format: 'dropdown' });
    ctx.group = g.entries[0].set_in_context.group;
  });
  await tryStep('games', async () => {
    const g = await api('games', { mode: 'club', club_id: ctx.club_id, season: ctx.season });
    ctx.game_id = findLinkIds(g, 'game_detail')[0];
  });
  await tryStep('rankings', async () => {
    const r = await api('rankings', { season: ctx.season, league: ctx.league, game_class: ctx.game_class, group: ctx.group });
    ctx.team_id = findLinkIds(r, 'team_detail')[0];
  });
  await tryStep('topscorers', async () => {
    const t = await api('topscorers', { season: ctx.season, league: ctx.league, game_class: ctx.game_class, group: ctx.group });
    ctx.player_id = findLinkIds(t, 'player_detail')[0];
  });
  return ctx;
}

function wp(...args) {
  return execFileSync('docker', ['compose', 'run', '--rm', '-T', 'wp-cli', 'wp', ...args, '--allow-root'], { encoding: 'utf8' }).trim();
}

function fill(template, ctx) {
  let missing = null;
  const out = template.replace(/\{\{(\w+)\}\}/g, (_, k) => { if (!ctx[k]) missing = k; return ctx[k] || ''; });
  return { out, missing };
}

async function login(browser) {
  const context = await browser.newContext({ viewport: cfg.viewports.desktop });
  const page = await context.newPage();
  await page.goto(`${WP}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await Promise.all([page.waitForURL(/wp-admin/), page.click('#wp-submit')]);
  const state = await context.storageState();
  await context.close();
  return state;
}

function createPages(ctx) {
  const pages = [];
  for (const s of cfg.frontend) {
    const { out, missing } = fill(s.shortcode, ctx);
    if (missing) { skip(s.id, `no ${missing} discovered`); continue; }
    wp('post', 'create', '--post_type=page', '--post_status=publish', `--post_title=${s.title}`, `--post_name=docs-${s.id}`, `--post_content=${out}`, '--porcelain');
    pages.push({ ...s, shortcode: out });
  }
  return pages;
}

// Frontend screenshot of the plugin container for one page and viewport.
async function shootFrontend(browser, s, vpName, vp) {
  const context = await browser.newContext({ viewport: vp });
  const page = await context.newPage();
  try {
    await page.goto(`${WP}/docs-${s.id}/`, { waitUntil: 'networkidle' });
    const el = page.locator('.swiss-floorball-plugin').first();
    if (!(await el.count())) { skip(`${s.id}-${vpName}`, 'no plugin output'); return; }
    const text = ((await el.innerText()) || '').trim();
    if (text.length < 20) { skip(`${s.id}-${vpName}`, 'empty output'); return; }
    const file = `${s.id}-${vpName}.png`;
    await el.screenshot({ path: path.join(OUT, file) });
    manifest.screenshots.push({ id: s.id, title: s.title, type: 'frontend', viewport: vpName, file, shortcode: s.shortcode });
    log(`  ok ${file}`);
  } catch (e) {
    skip(`${s.id}-${vpName}`, e.message);
  } finally {
    await context.close();
  }
}

// Admin screenshots (desktop).
async function shootAdmin(browser, state) {
  const context = await browser.newContext({ viewport: cfg.viewports.desktop, storageState: state });
  const page = await context.newPage();
  for (const s of cfg.admin) {
    try {
      await page.goto(`${WP}/wp-admin/admin.php?page=${s.page}`, { waitUntil: 'networkidle' });
      const file = `${s.id}.png`;
      await page.screenshot({ path: path.join(OUT, file), fullPage: true });
      manifest.screenshots.push({ id: s.id, title: s.title, type: 'admin', viewport: 'desktop', file });
      log(`  ok ${file}`);
    } catch (e) {
      skip(s.id, e.message);
    }
  }
  await context.close();
}

// Walkthrough video: admin pages, then a shortcode page (login is not recorded).
async function recordWalkthrough(browser, state, pages) {
  const tmp = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'vid-'));
  const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, storageState: state, recordVideo: { dir: tmp, size: { width: 1280, height: 720 } } });
  const page = await context.newPage();
  const steps = cfg.admin.map((s) => `${WP}/wp-admin/admin.php?page=${s.page}`).concat(pages.slice(0, 3).map((s) => `${WP}/docs-${s.id}/`));
  for (const url of steps) {
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    await page.mouse.wheel(0, 500);
    await page.waitForTimeout(1200);
  }
  const video = await page.video().path();
  await context.close();
  try {
    const gif = path.join(OUT, 'walkthrough.gif'), mp4 = path.join(OUT, 'walkthrough.mp4');
    execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-i', video, '-vf', 'fps=8,scale=800:-1:flags=lanczos', '-loop', '0', gif]);
    execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-i', video, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', mp4]);
    manifest.videos.push({ id: 'walkthrough', title: 'Walkthrough: admin pages and shortcode output', gif: 'walkthrough.gif', mp4: 'walkthrough.mp4' });
    log('  ok walkthrough.gif / walkthrough.mp4');
  } catch (e) {
    skip('walkthrough', `ffmpeg: ${e.message}`);
  }
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  log('Discovering IDs from the Swiss Unihockey API ...');
  const ctx = await discover();
  manifest.context = ctx;
  log(ctx);

  log('Configuring WordPress ...');
  wp('option', 'update', 'swissfloorball_club_number', ctx.club_id);
  wp('option', 'update', 'swissfloorball_club_name', ctx.club_name);
  wp('option', 'update', 'swissfloorball_actual_season', String(ctx.season));

  const pages = createPages(ctx);

  const browser = await chromium.launch();
  const state = await login(browser);

  for (const s of pages) {
    for (const [vpName, vp] of Object.entries(cfg.viewports)) {
      await shootFrontend(browser, s, vpName, vp);
    }
  }
  await shootAdmin(browser, state);
  await recordWalkthrough(browser, state, pages);

  await browser.close();
  fs.writeFileSync(path.join(OUT, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
  log(`Done: ${manifest.screenshots.length} screenshots, ${manifest.videos.length} video(s), ${manifest.skipped.length} skipped.`);
  if (!manifest.screenshots.length) process.exit(1);
}

main().catch((e) => { console.error(e); process.exit(1); });
