# Architektur

## Dateien und Klassen

| Datei | Klasse | Aufgabe |
|---|---|---|
| `swiss-floorball-api.php` | – | Bootstrap, `Version:`-Header, Konstante `SWFL_VERSION`, Aktivierungs-/Deaktivierungs-Hooks |
| `includes/class-swfl-plugin.php` | `SWFL_Plugin` | Orchestrator, lädt Klassen, registriert Hooks |
| `includes/class-swfl-loader.php` | Loader | Warteschlange für Actions/Filter (`run()`) |
| `includes/class-swfl-migrator.php` | `SWFL_Migrator` | einmaliges Umbenennen der Optionen `swissfloorball_*` → `swfl_*` auf `plugins_loaded` |
| `includes/class-swfl-client.php` | `SWFL_Client` | einziger Zugriff auf die externe API, Caching |
| `includes/class-swfl-display.php` | `SWFL_Display` | gesamtes HTML-Rendering (statische `render_*`-Methoden) |
| `includes/class-swfl-widgets.php` | `SWFL_Widgets` | interaktive Widgets nach den offiziellen Webcomponents (Spiele, Rangliste, Mobiliar-Topscorer), REST-Routen `swfl/v1/*`, iCalendar-Feed |
| `public/js/swfl-widgets.js` | – | Navigation der Widgets (Woche, Seite, Runde, Team-Auswahl) über die REST-Routen |
| `includes/class-swfl-icons.php` | Icons | Inline-SVG-Icons (Option `swfl_show_icons`) |
| `includes/class-swfl-i18n.php` | i18n | lädt die Textdomain `swiss-floorball-api` |
| `…-activator.php`, `…-deactivator.php` | | Methoden sind leer (keine Aktion) |
| `public/class-swfl-public.php` | Public | Shortcodes, Assets |
| `admin/class-swfl-admin.php`, `admin/partials/` | Admin | Menü, Einstellungen, Helper-Seiten |
| `uninstall.php` | – | löscht Optionen und Transients (auch Multisite) |

Alle Dateien, Klassen, Optionen, Transients, Hooks und Shortcodes verwenden den Präfix `swfl`; Text-Domain und Hauptdatei heissen wie der WordPress.org-Slug `swiss-floorball-api`. Die alte Hauptdatei `floorball-api-for-swiss-unihockey.php` bleibt als kleiner Weiterleiter bestehen, damit bereits aktivierte Installationen aktiv bleiben.

## Datenfluss

```text
Shortcode-Callback (Public)  ─┐
                              ├─> Display::render_*() ─> Client::fetch_data() ─> API / Transient
Admin-Partial (Helper-Seite) ─┘
```

Die Shortcode-Callbacks bereinigen Attribute und rufen nur die Display-Methode auf. `Display` gibt das HTML direkt aus (keine Templates), daher nutzen Admin und Frontend denselben Code.

## Client und Caching

`fetch_data( $endpoint, $args = array(), $cache_time = 3600 )` baut `<Basis-URL der API-Quelle>/<endpoint>` (plus Query-Args), prüft den Transient `swfl_<md5(url)>` und fragt sonst per `wp_remote_get` ab. Fehler (HTTP ≠ 200, ungültiges JSON, Netzwerkfehler) liefern ein `WP_Error` und werden nicht gecacht. Erfolgreiche Antworten werden standardmässig 1 Stunde gecacht.

### API-Quellen

Die Quelle kommt aus der Option `swfl_api_source` (`SWFL_Client::get_source()`, unbekannte Werte fallen auf `free` zurück):

| Konstante | Basis-URL | Besonderheit |
|---|---|---|
| `SOURCE_FREE` | `https://wc.swissunihockey.ch/` | Positivliste; HTTP 403 → `WP_Error` `swfl_endpoint_unavailable` |
| `SOURCE_PARTNER` | `https://office.swissunihockey.ch/api/legacy/` | Auth-Token per `bo/session/auth` (API-Key und Secret), 30 Minuten im Transient `swfl_partner_token`, als `auth_token` angehängt, bei 401/403 einmal erneuert |

Der Token steht nie im Cache-Schlüssel. Fehlercodes: `swfl_partner_credentials` (Key oder Secret fehlt), `swfl_partner_auth` (Anmeldung fehlgeschlagen), `swfl_endpoint_unavailable`. Die Render-Methoden übersetzen sie in verständliche Hinweise.

Cache leeren: Button auf der Einstellungsseite, Änderung der Club-Nummer oder Deinstallation – siehe [Administration](admin.md#cache-leeren).

## Verwendete Endpunkte

Mit beiden Quellen: `topscorers/mobiliar-highlight` (ohne `club_id` Liga-weit), `clubs`, `clubs/<id>/statistics`, `seasons`, `games` (Parameter `mode` = `club`/`team`/`list`, `club_id`/`team_id`, `season`), `games/<id>`, `cups`, `teams`, `teams/<id>`, `rankings`.

Nur Partner-API: `leagues`, `groups`, `topscorers/su`, `players/<id>`, `national_players`, `game_events/<id>`.

Die alte Kalender-URL `calendars` wird nicht mehr verwendet.

## REST-Routen

Öffentlich, nur lesend (`GET`, Namespace `swfl/v1`, Registrierung in `SWFL_Widgets::register_routes()`):

| Route | Parameter | Zweck |
|---|---|---|
| `/team-games` | `team_id` (Pflicht), `season`, `page_size` | Seitenweise Spiele eines Teams |
| `/league-games` | `game_class`, `league` (Pflicht), `season`, `group`, `round` | Spiele einer Liga/Gruppe pro Runde |
| `/calendar` | `team_id` oder `club_id` oder `league` + `game_class` (+ `group`), `season` | iCalendar-Feed (`text/calendar`), aus den Spielen der API gebaut |

## API-Antwortformen

- **Raster:** `data.regions[0].rows[].cells[].text[]` – Spiele, Ranglisten, Topscorer, Teams.
- **Liste:** `entries[]` (mit `text` und `set_in_context`) – Ligen, Saisons, Clubs, Gruppen.

Vor einer neuen Render-Methode die Form des Endpunkts prüfen (`php verify_api.php`, siehe [Entwicklung](developer.md)).

## Aktivierung, Deaktivierung, Deinstallation

Aktivierung und Deaktivierung haben keine Wirkung. `uninstall.php` löscht alle Plugin-Optionen (u. a. `swfl_api_source`, `_api_key`, `_api_secret`, `_club_number`, `_club_name`, `_actual_season`, `_request_timeout`, `_theme`, `_seed_color`, `_table_*`), den Token-Transient `swfl_partner_token` und alle Transients `swfl_*`. Die Option `swfl_show_icons` wird dort nicht gelöscht.
