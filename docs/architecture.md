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
| `includes/class-swfl-highlights.php` | `SWFL_Highlights` | nächstes Spiel und letztes Resultat (Shortcodes `swfl-next-game`, `swfl-last-result`) |
| `includes/class-swfl-game-widget.php` | `SWFL_Game_Widget` | Sidebar-Widget mit nächstem Spiel und letztem Resultat |
| `includes/class-swfl-widgets.php` | `SWFL_Widgets` | interaktive Widgets nach den offiziellen Webcomponents (Spiele, Rangliste, Mobiliar-Topscorer), REST-Routen `swfl/v1/*`, iCalendar-Feed |
| `public/js/swfl-widgets.js` | – | Navigation der Widgets (Woche, Seite, Runde, Team-Auswahl) über die REST-Routen |
| `includes/class-swfl-icons.php` | Icons | Inline-SVG-Icons (Option `swfl_show_icons`) |
| `includes/class-swfl-i18n.php` | i18n | lädt die Textdomain `swiss-floorball-api` |
| `…-activator.php`, `…-deactivator.php` | | Methoden sind leer (keine Aktion) |
| `public/class-swfl-public.php` | Public | Shortcodes, Assets |
| `admin/class-swfl-admin.php`, `admin/partials/` | Admin | Menü, Einstellungen, Helper-Seiten |
| `uninstall.php` | – | löscht Optionen und Transients (auch Multisite) |

Alle Dateien, Klassen, Optionen, Transients, Hooks und Shortcodes verwenden den Präfix `swfl`; Text-Domain und Hauptdatei heissen wie der WordPress.org-Slug `swiss-floorball-api`.

## Datenfluss

```text
Shortcode-Callback (Public)  ─┐
                              ├─> Display::render_*() ─> Client::fetch_data() ─> API / Transient
Admin-Partial (Helper-Seite) ─┘
```

Die Shortcode-Callbacks bereinigen Attribute und rufen nur die Display-Methode auf. `Display` gibt das HTML direkt aus (keine Templates), daher nutzen Admin und Frontend denselben Code.

## Client und Caching

`fetch_data( $endpoint, $args = array(), $cache_time = null, $force_refresh = false )` baut `<Basis-URL der API-Quelle>/<endpoint>` (plus URL-kodierte Query-Args), prüft den Transient `swfl_<sha256(url)>` und fragt sonst per `wp_remote_get` ab. Ohne `$cache_time` gilt die Cache-Dauer des Endpunkts aus den Einstellungen (Standard 1 Stunde). Fehler (HTTP ≠ 200, ungültiges JSON, Netzwerkfehler) liefern ein `WP_Error`.

Was im Transient steht, kodiert `SWFL_Cache_Entry`:

- **Antwort:** Hülle `{ swfl_entry: 1, fresh_until, data }`. Sie ist bis `fresh_until` frisch und bleibt danach noch `swfl_stale_ttl` (Standard 1 Stunde) als abgelaufene Kopie liegen. Rohe Antworten, die 2.1.2 geschrieben hat, gelten als frisch.
- **Leere Antwort** (`data.regions` ohne Zeilen oder `entries` leer, typisch für unbekannte oder unpassende IDs): wie ein Fehler nur kurz (höchstens 300 Sekunden, nie länger als die Cache-Dauer des Endpunkts) und ohne abgelaufene Kopie.
- **Fehler-Marker** `{ swfl_entry: 1, error }`: Code, Meldung und Daten des `WP_Error`. Dauerhafte Fehler (4xx ausser 401/403/408/429, z. B. 404 für unbekannte IDs) 300 Sekunden, vorübergehende (Timeout, 5xx, 408, 429, ungültiges JSON) höchstens 60 Sekunden, nie länger als die Cache-Dauer des Endpunkts (Filter `swfl_error_cache_ttl`, `0` = aus). Fehlende oder abgelehnte Partner-Zugangsdaten und 401/403 werden nie gemerkt. Ein Marker überschreibt nie Daten: weder eine abgelaufene Kopie noch eine Antwort, die eine parallele Anfrage inzwischen gespeichert hat. `$force_refresh` (Warm-up) überspringt das Lesen und schreibt nie einen Marker, eine fehlgeschlagene Auffrischung lässt die alten Daten stehen.

### Budget für ungecachte Anfragen

Läuft eine öffentliche REST-Route (`SWFL_Request_Guard::check_rate_limit()` setzt den Zustand, `rest_request_after_callbacks` und spät `rest_post_dispatch` beenden ihn), holt `SWFL_Upstream_Budget::acquire()` vor jeder ungecachten Anfrage eine Einheit des seitenweiten Minutenbudgets (`swfl_upstream_budget`, Standard 120). Ist es aufgebraucht, liefert der Client eine abgelaufene Kopie (die Route antwortet dann mit `max-age=60`) oder einen `WP_Error` `swfl_budget_exceeded` (503 mit `Retry-After`). Vorher zählt jede Einheit gegen den Anteil des Besuchers (`swfl_upstream_visitor_budget`, Standard 20; Bucket aus `SWFL_Client_Ip`): ist er aufgebraucht, gibt es ebenfalls die abgelaufene Kopie oder `swfl_budget_exceeded` mit 429, ohne dass eine Einheit des Seitenbudgets verbraucht wird. So reichen ein oder zwei Adressen nicht mehr, um das Budget für alle aufzubrauchen. Eine abgewiesene Seite einer langen Liste lässt die ganze Anfrage scheitern, statt eine gekürzte Liste zu liefern; schon geholte Seiten bleiben gecacht. Administratoren, Shortcode-Seiten, Admin und der WP-Cron-Warm-up zählen nicht.

`SWFL_Counter` zählt Rate-Limit pro Besucher und Budget in festen, an der Minute ausgerichteten Fenstern, immer atomar: mit persistentem Object Cache per `wp_cache_add()`/`wp_cache_incr()`, sonst in einer Options-Zeile pro Zähler und Fenster (`swfl_count_<Fensterende, 10 Ziffern>_<name>`, ein `INSERT … ON DUPLICATE KEY UPDATE`). Ein Ereignis über dem Limit wird nur gelesen, nicht geschrieben. Legt ein Ereignis eine neue Zeile an, löscht es alle Zeilen, deren Fenster abgelaufen ist – Besucher hinterlassen so keine Zeilen. Nur wenn die Datenbank diese Anweisungen ablehnt, zählt ein Transient `swfl_c_<name>` (nicht atomar).

### Besucher-Erkennung

`SWFL_Client_Ip` bestimmt den Bucket des Rate-Limits: IPv4 nach voller Adresse, IPv6 nach dem /64-Präfix, IPv4-mapped Adressen als IPv4. `X-Forwarded-For` wird nur gelesen, wenn `REMOTE_ADDR` in «Trusted proxies» (`swfl_trusted_proxies`, Filter `swfl_trusted_proxies`) liegt, von rechts, vertrauenswürdige Hops werden übersprungen (höchstens 32). Der Filter `swfl_client_ip` kann die Adresse ersetzen. Wird ein Bucket abgewiesen, merkt sich `SWFL_Rate_Limit_Log` ihn eine Stunde lang für die Liste «Blocked visitors» der Einstellungsseite (der Zähler selbst kennt nur einen Hash).

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
| `/games` | `team_id` oder `club_id` oder `league` + `game_class` (+ `group`), `season` | JSON: Spiele (`game_id`, `date`, `time`, `place`, `home`, `away`, `result`, `league`, `link`, `cells`) |
| `/next-game` | wie `/games` | JSON: nächstes Spiel ohne Resultat (`game`, `null` ohne Treffer) |
| `/last-result` | wie `/games` | JSON: letztes Spiel mit Resultat (`game`, `null` ohne Treffer) |
| `/rankings` | `league`, `game_class` (Pflicht), `season`, `group` | JSON: Rangliste (`headers`, `rows`) |
| `/topscorers` | `league` + `game_class` (Partner-API) oder `club_id`, `season` (Mobiliar-Topscorer) | JSON: Topscorer-Tabelle bzw. `players` |

Die JSON-Routen sind in `SWFL_Rest` umgesetzt, nutzen den Cache des API-Clients, das Rate-Limit der übrigen Routen und senden `Cache-Control: public, max-age=3600` (`max-age=60`, wenn eine abgelaufene Kopie ausgeliefert wurde).

Jedes Argument aller Routen hat einen eigenen `validate_callback` und `sanitize_callback` (`SWFL_Request_Guard::int_arg()`, `season_arg()`, `group_arg()`): IDs nur Ziffern, 1 bis 10 000 000 (optionale auch leer oder `0`), `season` leer, `0` oder 1996 bis nächstes Jahr, `group` höchstens 40 Buchstaben, Ziffern, Leerzeichen und `. _ / -`. WordPress lehnt ungültige Werte mit `400 rest_invalid_param` ab, bevor eine API-Anfrage entsteht. Abweisungen (429, 503) tragen `Retry-After` und `Cache-Control: no-store`; die HTML-Routen und der Kalender antworten bei aufgebrauchtem Budget mit 503 statt mit einem Hinweis oder einer gekürzten Liste.

## API-Antwortformen

- **Raster:** `data.regions[0].rows[].cells[].text[]` – Spiele, Ranglisten, Topscorer, Teams.
- **Liste:** `entries[]` (mit `text` und `set_in_context`) – Ligen, Saisons, Clubs, Gruppen.

Vor einer neuen Render-Methode die Form des Endpunkts prüfen (`php verify_api.php`, siehe [Entwicklung](developer.md)).

## Aktivierung, Deaktivierung, Deinstallation

Aktivierung und Deaktivierung haben keine Wirkung. `uninstall.php` löscht alle Plugin-Optionen (u. a. `swfl_api_source`, `_api_key`, `_api_secret`, `_club_number`, `_club_name`, `_actual_season`, `_request_timeout`, `_rest_rate_limit`, `_upstream_budget`, `_trusted_proxies`, `_theme`, `_seed_color`, `_table_*`), den Token-Transient `swfl_partner_token`, alle Transients `swfl_*` (auch Fehler-Marker und Zähler) und die Zähler-Zeilen `swfl_count_*`. Die Option `swfl_show_icons` wird dort nicht gelöscht.
