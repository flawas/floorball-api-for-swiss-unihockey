# Architektur

## Dateien und Klassen

| Datei | Klasse | Aufgabe |
|---|---|---|
| `floorball-api-for-swiss-unihockey.php` | – | Bootstrap, `Version:`-Header, Konstante `SWISS_FLOORBALL_API_VERSION`, Aktivierungs-/Deaktivierungs-Hooks |
| `includes/class-floorball-api-for-swiss-unihockey.php` | `Swiss_Floorball_Api` | Orchestrator, lädt Klassen, registriert Hooks |
| `includes/class-floorball-api-for-swiss-unihockey-loader.php` | Loader | Warteschlange für Actions/Filter (`run()`) |
| `includes/class-floorball-api-for-swiss-unihockey-client.php` | `Swiss_Floorball_API_Client` | einziger Zugriff auf die externe API, Caching |
| `includes/class-floorball-api-for-swiss-unihockey-display.php` | `Swiss_Floorball_API_Display` | gesamtes HTML-Rendering (statische `render_*`-Methoden) |
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

`fetch_data( $endpoint, $args = array(), $cache_time = 3600 )` baut `https://api-v2.swissunihockey.ch/api/<endpoint>` (plus Query-Args), prüft den Transient `swfl_<md5(url)>` und fragt sonst per `wp_remote_get` ab. Fehler (HTTP ≠ 200, ungültiges JSON, Netzwerkfehler) liefern ein `WP_Error` und werden nicht gecacht. Erfolgreiche Antworten werden standardmässig 1 Stunde gecacht.

Cache leeren: Button auf der Einstellungsseite, Änderung der Club-Nummer oder Deinstallation – siehe [Administration](admin.md#cache-leeren).

## Verwendete Endpunkte

`clubs`, `clubs/<id>/statistics`, `leagues`, `seasons`, `games` (Parameter `mode` = `club`/`team`, `club_id`/`team_id`, `season`), `games/<id>`, `game_events/<id>`, `cups`, `groups`, `teams`, `teams/<id>`, `rankings`, `topscorers`, `players/<id>`, `national_players`, `sessions` (`render_sessions()`, von keinem Shortcode genutzt) sowie die Kalender-URL `calendars` (nur als Link aufgebaut).

## API-Antwortformen

- **Raster:** `data.regions[0].rows[].cells[].text[]` – Spiele, Ranglisten, Topscorer, Teams.
- **Liste:** `entries[]` (mit `text` und `set_in_context`) – Ligen, Saisons, Clubs, Gruppen.

Vor einer neuen Render-Methode die Form des Endpunkts prüfen (`php verify_api.php`, siehe [Entwicklung](developer.md)).

## Aktivierung, Deaktivierung, Deinstallation

Aktivierung und Deaktivierung haben keine Wirkung. `uninstall.php` löscht die Optionen `swissfloorball_api_key`, `_club_number`, `_club_name`, `_actual_season` und alle Transients `swfl_*`. Die Option `swissfloorball_show_icons` wird dort nicht gelöscht.
