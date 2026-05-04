# Swiss Floorball API for WordPress

![Banner](assets/banner-914x298.png)

**Contributors:** flaviowaser  
**Donate link:** https://www.paypal.me/flaviowaser  
**Tags:** floorball, api, swiss floorball, unihockey, sports  
**Requires at least:** 5.0
**Tested up to:** 6.9  
**Stable tag:** 1.0.4  
**License:** GPLv2 or later  

The **Swiss Floorball API** plugin brings the power of the Swiss Floorball API v2 to your WordPress site. Display games, rankings, team rosters, and player statistics with ease using simple shortcodes.

## 🚀 Features

* **🔌 Plug & Play:** Configure your Club ID once and get instant access to your club's data.
* **📅 Calendars:** Display upcoming games for teams or entire clubs. Includes **ICS subscription links** for users to add games to their personal calendars.
* **🏆 Rankings:** Always up-to-date league tables for any group or league.

* **📊 Statistics:** Show topscorer lists and detailed game events.
* **📱 Responsive:** Built-in responsive tables that look great on mobile devices.
* **👤 Player Profiles:** Showcase individual player stats and national team members.

## 🛠 Installation

1. Download the plugin from the [releases page on GitHub](https://github.com/flawas/floorball-api-for-swiss-unihockey/releases) and upload the `floorball-api-for-swiss-unihockey.zip` file to your WordPress plugins.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > Swiss Floorball API**.
4. Enter your **Club ID** (e.g., `427892`) and the **Current Season** (e.g., `2025`).
5. Click **Save Changes**.
6. Add the shortcodes to your pages or posts.

![Settings Page](assets/screenshot-admin-club-backend.png)
*Configure your Club ID and Season in the settings.*

## 📝 Shortcodes

Use these shortcodes in any Page or Post to display data.

### Club & Team Data

| Shortcode | Description | Attributes |
|-----------|-------------|------------|
| `[swfl-club-teams]` | List all teams in your club. | None |
| `[swfl-club-games]` | List all games for your club this season. | None |
| `[swfl-team-games]` | List games for a specific team. | `team_id` |
| `[swfl-calendars]` | Show upcoming games + ICS link. | `team_id`, `club_id` |

**Example:**

```
[swfl-calendars team_id="427892"]
```

### League & Rankings

| Shortcode | Description | Attributes |
|-----------|-------------|------------|
| `[swfl-rankings]` | Show the ranking table. | `season`, `league`, `game_class`, `group` |
| `[swfl-topscorers]` | Show the topscorer list. | `season`, `league`, `game_class`, `group` |
| `[swfl-groups]` | List available groups. | `season`, `league`, `game_class` |

**Example:**

```
[swfl-rankings league="1" game_class="11" group="416947"]
```

### Players & Games

| Shortcode | Description | Attributes |
|-----------|-------------|------------|
| `[swfl-player]` | Show player profile. | `player_id` |
| `[swfl-game-events]` | Show events (goals, penalties) of a game. | `game_id` |
| `[swfl-national-players]` | List national team players. | None |

## 🎨 Customization

The plugin uses standard CSS classes prefixed with `.sfa-`. You can easily override these in your theme's `style.css` or the Customizer to match your site's branding.

## ❓ FAQ

**Q: Where do I find the IDs?**  
A: You can find Team, Club, and League IDs in the URL on the [Swiss Floorball website](https://www.swissunihockey.ch) or by using the plugin backend to explore and copy the IDs.

**Q: Is this plugin free?**  
A: Yep! It’s open-source and completely free to use.

**Q: Is this an official Swiss Floorball plugin?**  
A: Nope. This plugin isn’t official and has no connection to Swiss Floorball.

**Q: Can I use this plugin for commercial purposes?**  
A: Sorry, no. It’s meant only for personal and non-commercial use. It’s designed for Swiss Floorball clubs and teams to show their data on their websites.

**Q: Any further questions?**  
A: Just drop me a message on [GitHub](https://github.com/flawas), [LinkedIn](https://www.linkedin.com/in/flawas/), or via my [homepage](https://flaviowaser.ch/contact/).

## 🔗 External Services

This plugin connects to the **Swiss Unihockey API** (api-v2.swissunihockey.ch) to retrieve floorball data such as club games, team rosters, league rankings, player statistics, and calendar feeds.

**What data is sent and when:**
- Your configured Club ID and Season are sent to the API every time a shortcode is rendered or an admin page is loaded.
- No personal data of website visitors is transmitted. All requests are read-only.

**Service provider:** Swiss Unihockey (Floorball Schweiz)  
[Terms of Service](https://www.swissunihockey.ch/index.php?cID=1186) | [Privacy Policy](https://swissunihockey.tlex.ch/app/de/texts_of_law/2-7)

## 📸 Screenshots

1. **Settings Page**: Easy configuration.
![Settings Page](assets/screenshot-admin-backend.png)
2. **Club Games**: Overview of all club matches.
![Club Games](assets/screenshot-calendar-backend.png)
3. **Teams**: Detailed teams tables.
![Teams](assets/screenshot-teams-backend.png)
4. **Calendar**: Upcoming games with calendar export.
![Calendar](assets/screenshot-calendar-frontend.png)
5. **Rankings**: Detailed rankings tables.
![Rankings](assets/screenshot-ranking-frontend.png)

## 📜 Changelog

### 1.0.4 (2026-05-04)

* Security: Add nonce and permission checks for all admin `$_GET`/`$_POST` actions
* Security: Sanitize nonce with `wp_unslash()` + `sanitize_text_field()` before `wp_verify_nonce()`
* Security: Escape all dynamic HTML attributes with `esc_attr()` in settings field renderer
* Fix: Add `ABSPATH` guard to main plugin file and admin matches partial
* Fix: Rename plugin slug to `floorball-api-for-swiss-unihockey` for WordPress.org listing
* Fix: Rename all shortcodes from `suh-*` to `swfl-*` to meet 4-character prefix requirement
* Fix: Prefix settings group and section IDs with `sfa_` to avoid naming collisions
* Fix: Remove `load_plugin_textdomain()` call — not needed since WordPress 4.6
* Fix: Update "Tested up to" to WordPress 6.9
* Fix: Remove angle brackets from Donate link URL
* Fix: Exclude `.distignore` and `README.md` from release ZIP
* Improvement: Document external service (Swiss Unihockey API) with Terms of Service and Privacy Policy links

### 1.0.3 (2026-05-03)

* Security: Use `$wpdb->prepare()` for all database queries to prevent SQL injection
* Security: Sanitize all shortcode attributes with `absint()` before passing to API
* Security: Escape all `admin_url()` outputs with `esc_url()`
* Fix: Scoped all CSS to plugin containers to prevent styling conflicts with other theme elements (buttons, notices, etc.)
* Fix: Dark mode CSS variables scoped to `.swiss-floorball-plugin` / `.sfa-admin-wrap` instead of `:root`
* Fix: `prefers-reduced-motion` rule scoped to plugin elements only
* Fix: `scroll-behavior: smooth` scoped to plugin container instead of `html`
* Fix: Admin `.button-primary` styles scoped to `.sfa-admin-wrap` to avoid overriding WordPress core buttons
* Performance: CSS and JS only enqueued on pages/posts that contain a plugin shortcode (frontend) or on plugin admin pages (backend)
* Improvement: Replaced all inline styles in templates with reusable CSS classes
* Improvement: Added `aria-label` to admin search input for accessibility
* Improvement: Removed `console.log` statements from admin JavaScript
* Improvement: Use `const`/`let` and `.includes()` in admin JavaScript
* Improvement: Use `require_once` instead of `require` for the main plugin class
* Fix: Version constant `SWISS_FLOORBALL_API_VERSION` now matches plugin header version
* Improvement: Calendar link now has correct desktop styles (was previously only styled on mobile)
* Improvement: Replaced remaining inline styles in admin partials (matches, settings, shortcodes) with CSS classes
* Improvement: Replaced hardcoded colors in admin CSS with CSS variables
* Improvement: Escaped remaining `admin_url()` outputs in shortcodes partial

### 1.0.2 (2026-01-02)

* Fix: Mobile table background color
* Fix: Mobile table text alignment

### 1.0.1 (2025-11-30)

* Feature: Implement detailed matches display in admin
* Feature: Scope public CSS
* Fix: Add release workflow documentation
* Fix: Consistent language and naming in code

### 1.0.0 (2025-11-29)

* Initial release

---

*Developed by [Flavio Waser](https://flaviowaser.ch)*
