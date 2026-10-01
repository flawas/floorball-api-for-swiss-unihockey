# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

A WordPress plugin ("Swiss Floorball API") that fetches data from the Swiss Unihockey public API
(`api-v2.swissunihockey.ch`) and renders it via shortcodes (games, rankings, topscorers, player
profiles, calendars) and an admin dashboard. PHP only, no build step, no Composer/npm dependencies.
Plugin slug on WordPress.org is `swiss-floorball-api`; the main file and class names still use the
older `floorball-api-for-swiss-unihockey` naming (see Naming below).

## Local development

Run WordPress + MariaDB in Docker, with this repo mounted as the plugin directory:

```bash
docker-compose up
```

This auto-installs WordPress at `http://localhost:8000`, activates the plugin, and creates a
"Shortcuts" page pre-populated with every shortcode for manual testing. WP-CLI is available via the
`wp-cli` service.

Automated checks live in `.github/workflows/ci.yml` (PHP lint on 7.4–8.4, `scripts/check_release.php`
for version/ABSPATH/text-domain consistency, WordPress Plugin Check). Run the consistency check locally with
`php scripts/check_release.php`. Beyond that, verification is manual (through the Docker site). Note:
`verify_api.php` is the live smoke test: it loads the real client and display classes with stubbed
WordPress functions, discovers season/league/group/club/game/team IDs from the API itself, and fails on API
errors, changed response shapes, PHP warnings or "data could not be loaded" output. It runs in CI as an
informational job (`continue-on-error`, external API) and needs network access:

```bash
php verify_api.php
```

Use it to sanity-check real API responses/shapes when changing anything in
`includes/class-floorball-api-for-swiss-unihockey-client.php` or the render methods in
`includes/class-floorball-api-for-swiss-unihockey-display.php`.

## Release process

**Claude-gated release (preferred):** open an issue and add the label `claude-release` (or run the
"Claude Release" workflow with the issue number). `claude-release.yml` runs plan → prepare (release PR
`release/vX.Y.Z`, version bump + changelog) → `ci.yml` on the release branch → verify (Claude's go/no-go) →
publish (merges the PR, tags, dispatches `release.yml`). `publish` runs in the GitHub environment `release`;
add required reviewers there to keep a human as the last gate. `release.yml` itself also runs `ci.yml` first,
so no release path skips the tests.

Manual path:

Releases are tag-driven via `.github/workflows/release.yml`, triggered by pushing a `vX.Y.Z` tag (or
manual dispatch with a tag input):

1. The workflow verifies the `Version:` header in `floorball-api-for-swiss-unihockey.php` matches the
   tag — a mismatch fails the build.
2. `scripts/convert_readme.php` generates `readme.txt` from `README.md` (metadata block + heading
   levels converted to WordPress.org readme format). `readme.txt` is gitignored and never hand-edited.
3. A release ZIP is built via `rsync` excluding dev-only files (`.github`, `.vscode`, `.claude`,
   `docker-compose.yml`, `scripts`, `verify_api.php`, `graphify-out`, `assets`, etc. — see
   `.distignore` for the authoritative exclude list used by `svn`/plugin-check tooling) and attached to
   a GitHub Release.
4. A second job deploys the same tag to the WordPress.org SVN repo (slug `swiss-floorball-api`).

When bumping the version, update it in **both** places: the `Version:` header and
`SWISS_FLOORBALL_API_VERSION` constant in `floorball-api-for-swiss-unihockey.php`, and add a changelog
entry at the top of `README.md` (the single source of truth — `readme.txt` is generated from it, never
edited directly).

## Architecture

Standard WordPress plugin boilerplate (loader/i18n/admin/public split), with all API logic
centralized outside that split:

- `floorball-api-for-swiss-unihockey.php` — plugin bootstrap; defines `SWISS_FLOORBALL_API_VERSION`,
  registers activation/deactivation hooks, instantiates `Swiss_Floorball_Api`.
- `includes/class-floorball-api-for-swiss-unihockey.php` — core orchestrator. Loads every other class
  and wires admin/public hooks through the loader.
- `includes/class-floorball-api-for-swiss-unihockey-loader.php` — generic action/filter registration
  queue, run in `Swiss_Floorball_Api::run()`.
- `includes/class-floorball-api-for-swiss-unihockey-client.php` (`Swiss_Floorball_API_Client`) — the
  **only** place that talks to the external API. `fetch_data($endpoint, $args, $cache_time)` builds the
  URL, caches responses as WordPress transients keyed `swfl_<md5(url)>` (default 1 hour), and returns
  the decoded JSON array or a `WP_Error`. Any new API call should go through this client, not a direct
  `wp_remote_get`.
- `includes/class-floorball-api-for-swiss-unihockey-display.php` (`Swiss_Floorball_API_Display`) — all
  HTML rendering. Every `render_*` method fetches via the shared client and echoes a `.sfa-data-table`
  (or similar) directly — these are **not** templates, they're static methods called from both admin
  partials and public shortcode callbacks, so admin and frontend reuse the exact same rendering code.
  The Swiss Unihockey API returns a generic `regions[0].rows[].cells[].text[]` grid shape for most
  endpoints (games, rankings, topscorers, teams) vs. an `entries[]` list shape for simpler lookup
  endpoints (leagues, seasons, clubs, groups) — check which shape an endpoint uses before writing a new
  render method.
- `public/class-floorball-api-for-swiss-unihockey-public.php` — registers all `swfl-*` shortcodes;
  each shortcode callback just sanitizes attributes (`absint()` on IDs) and delegates straight to a
  `Swiss_Floorball_API_Display::render_*` method, wrapped in an output buffer inside a
  `.swiss-floorball-plugin` div. Styles/scripts are only enqueued when `page_has_shortcode()` detects
  one of the registered shortcodes in the current post content.
- `admin/class-floorball-api-for-swiss-unihockey-admin.php` — settings page (Club ID, season) plus
  "helper" admin pages (Liga, Clubs, Spiele/Matches, Saison) that call the same `Display::render_*`
  methods to let admins browse/discover IDs (league, game_class, group, team, game) needed for
  shortcode attributes. Also handles cache-busting via `admin_post_swfl_clear_cache`.
- Settings are stored as plain WordPress options: `swissfloorball_club_number`,
  `swissfloorball_actual_season`.

### Naming inconsistency (intentional, documented in README changelog)

The WordPress.org slug, shortcode prefix, text domain, and option prefix are `swiss-floorball-api` /
`swfl-*` / `swiss-floorball-api` / `swissfloorball_*`, but the plugin's internal PHP class names, file
names, and the `$plugin_name` property still use `floorball-api-for-swiss-unihockey` (the original
slug before the WordPress.org rename). Don't try to "fix" this inconsistency as a drive-by refactor —
it was a deliberate, scoped decision (changing class/file names would be a much larger breaking
change) and is called out in the 1.0.5 changelog.

## Conventions

- All output must be escaped (`esc_html`, `esc_attr`, `esc_url`) and all strings wrapped for
  translation with text domain `swiss-floorball-api` — this plugin targets the WordPress.org
  directory and has previously failed automated plugin-check scans on exactly these issues (see
  README changelog for the history of security/compliance fixes already applied).
- Every PHP file starts with an `ABSPATH` guard (`if ( ! defined( 'ABSPATH' ) ) { exit; }`).
- Admin `$_GET`/`$_POST` handlers require nonce + capability checks (`current_user_can`,
  `wp_verify_nonce` with `wp_unslash()` + `sanitize_text_field()` applied to the raw nonce first).
- CSS/JS is scoped to plugin containers (`.swiss-floorball-plugin`, `.sfa-admin-wrap`) — never touch
  `:root` or global selectors like `html`, `.button-primary`, so the plugin doesn't leak styles into
  themes or wp-admin globally.
