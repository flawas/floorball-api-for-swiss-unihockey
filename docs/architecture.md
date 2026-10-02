# Architektur

## Dateien und Klassen

| Datei | Klasse | Aufgabe |
|---|---|---|
| `floorball-api-for-swiss-unihockey.php` | – | Bootstrap, `Version:`-Header, Konstante `SWISS_FLOORBALL_API_VERSION`, Aktivierungs-/Deaktivierungs-Hooks |
| `includes/class-floorball-api-for-swiss-unihockey.php` | `Swiss_Floorball_Api` | Orchestrator, lädt Klassen, registriert Hooks |
| `includes/class-floorball-api-for-swiss-unihockey-loader.php` | Loader | Warteschlange für Actions/Filter (`run()`) |
| `includes/class-floorball-api-for-swiss-unihockey-client.php` | `Swiss_Floorball_API_Client` | einziger Zugriff auf die externe API, Caching |
| `includes/class-floorball-api-for-swiss-unihockey-display.php` | `Swiss_Floorball_API_Display` | gesamtes HTML-Rendering (statische `render_*`-Methoden) |
| `includes/class-floorball-api-for-swiss-unihockey-widgets.php` | `Swiss_Floorball_API_Widgets` | interaktive Widgets nach den offiziellen Webcomponents (Spiele, Rangliste, Mobiliar-Topscorer), REST-Routen `swfl/v1/*`, iCalendar-Feed |
| `public/js/swfl-widgets.js` | – | Navigation der Widgets (Woche, Seite, Runde, Team-Auswahl) über die REST-Routen |
| `includes/class-floorball-api-for-swiss-unihockey-icons.php` | Icons | Inline-SVG-Icons (Option `swissfloorball_show_icons`) |
| `includes/class-floorball-api-for-swiss-unihockey-i18n.php` | i18n | lädt die Textdomain `swiss-floorball-api` |
| `…-activator.php`, `…-deactivator.php` | | Methoden sind leer (keine Aktion) |
| `public/class-floorball-api-for-swiss-unihockey-public.php` | Public | Shortcodes, Assets |
| `admin/class-floorball-api-for-swiss-unihockey-admin.php`, `admin/partials/` | Admin | Menü, Einstellungen, Helper-Seiten |
| `uninstall.php` | – | löscht Optionen und Transients (auch Multisite) |

Die Dateinamen verwenden bewusst noch den alten Slug `floorball-api-for-swiss-unihockey`; Textdomain, Shortcode- und Options-Präfix sind `swiss-floorball-api`, `swfl-*`, `swissfloorball_*`.

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

Die Quelle kommt aus der Option `swissfloorball_api_source` (`Swiss_Floorball_API_Client::get_source()`, unbekannte Werte fallen auf `free` zurück):

| Konstante | Basis-URL | Besonderheit |
|---|---|---|
| `SOURCE_FREE` | `https://wc.swissunihockey.ch/` | Positivliste; HTTP 403 → `WP_Error` `swfl_endpoint_unavailable` |
| `SOURCE_PARTNER` | `https://office.swissunihockey.ch/api/legacy/` | Auth-Token per `bo/session/auth` (API-Key und Secret), 30 Minuten im Transient `swfl_partner_token`, als `auth_token` angehängt, bei 401/403 einmal erneuert |

Der Token steht nie im Cache-Schlüssel. Fehlercodes: `swfl_partner_credentials` (Key oder Secret fehlt), `swfl_partner_auth` (Anmeldung fehlgeschlagen), `swfl_endpoint_unavailable`. Die Render-Methoden übersetzen sie in verständliche Hinweise.

Cache leeren: Button auf der Einstellungsseite, Änderung der Club-Nummer oder Deinstallation – siehe [Administration](admin.md#cache-leeren).

## Verwendete Endpunkte

Mit beiden Quellen: `topscorers/mobiliar-highlight` (ohne `club_id` Liga-weit), `clubs`, `clubs/<id>/statistics`, `seasons`, `games` (Parameter `mode` = `club`/`team`/`list`, `club_id`/`team_id`, `season`), `games/<id>`, `cups`, `teams`, `teams/<id>`, `rankings`.

Nur Partner-API: `leagues`, `groups`, `topscorers`, `players/<id>`, `national_players`, `game_events/<id>`.

Die alte Kalender-URL `calendars` wird nicht mehr verwendet.

## REST-Routen

Öffentlich, nur lesend (`GET`, Namespace `swfl/v1`, Registrierung in `Swiss_Floorball_API_Widgets::register_routes()`):

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

Aktivierung und Deaktivierung haben keine Wirkung. `uninstall.php` löscht alle Plugin-Optionen (u. a. `swissfloorball_api_source`, `_api_key`, `_api_secret`, `_club_number`, `_club_name`, `_actual_season`, `_request_timeout`, `_theme`, `_seed_color`, `_table_*`), den Token-Transient `swfl_partner_token` und alle Transients `swfl_*`. Die Option `swissfloorball_show_icons` wird dort nicht gelöscht.
