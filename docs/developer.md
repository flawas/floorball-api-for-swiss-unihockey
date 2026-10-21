# Entwicklung

## Filter

| Filter | Datei | Parameter | Zweck |
|---|---|---|---|
| `swfl_request_timeout` | `includes/class-swfl-client.php` | `$timeout` (Standard: Option `swfl_request_timeout`, Standard `3`, Sekunden), `$url` | Timeout der API-Anfrage; Minimum 1 |
| `swfl_rest_rate_limit` | `includes/class-swfl-request-guard.php` | `$limit` (Standard: Option `swfl_rest_rate_limit`, Standard `60`, Anfragen pro Minute und Besucher) | Rate-Limit der öffentlichen REST-Routen; `0` schaltet es aus |
| `swfl_upstream_budget` | `includes/class-swfl-upstream-budget.php` | `$limit` (Standard: Option `swfl_upstream_budget`, Standard `120`) | Ungecachte API-Anfragen pro Minute, die öffentliche REST-Routen seitenweit auslösen dürfen; `0` schaltet das Budget aus |
| `swfl_upstream_visitor_budget` | `includes/class-swfl-upstream-budget.php` | `$limit` (Standard: Option `swfl_upstream_visitor_budget`, Standard `20`) | Anteil des Budgets pro Besucher und Minute; `0` schaltet ihn aus |
| `swfl_trusted_proxies` | `includes/class-swfl-client-ip.php` | `$entries` (Adressen und CIDR-Bereiche aus der Option `swfl_trusted_proxies`) | Proxys, deren `X-Forwarded-For` das Rate-Limit liest; IPv4 ab /8, IPv6 ab /16, höchstens 100 |
| `swfl_client_ip` | `includes/class-swfl-client-ip.php` | `$ip` (ermittelte Adresse, leer wenn keine), `$remote_addr` | Adresse, die einen Besucher für das Rate-Limit ausmacht; IPv6 wird danach auf /64 gruppiert |
| `swfl_error_cache_ttl` | `includes/class-swfl-cache-entry.php` | `$ttl` (300), `$error`, `$url` | Wie lange eine fehlgeschlagene oder leere Antwort gemerkt wird; vorübergehende Fehler höchstens 60 Sekunden, `0` = nicht merken |
| `swfl_stale_ttl` | `includes/class-swfl-cache-entry.php` | `$ttl` (3600), `$url` | Wie lange eine Antwort nach ihrer Cache-Dauer als abgelaufene Kopie für das aufgebrauchte Budget bleibt; `0` = keine Kopie |
| `swfl_icon_svg` | `includes/class-swfl-icons.php` | `$svg`, `$name`, `$args` | SVG-Markup eines Icons ändern; Ergebnis wird erneut mit `wp_kses()` gefiltert |

```php
add_filter(
	'swfl_request_timeout',
	function ( $timeout, $url ) {
		return 10;
	},
	10,
	2
);
```

## Actions

| Action | Parameter | Zweck |
|---|---|---|
| `swfl_cache_warmup_done` | `$refreshed` | Nach einem Warm-up-Lauf |
| `swfl_upstream_budget_exceeded` | `$limit` | Höchstens einmal pro Minute, solange das seitenweite Budget aufgebraucht ist; zum Protokollieren oder Alarmieren |

## Erweiterung

- Neue API-Aufrufe immer über `SWFL_Client::fetch_data()`, nie direkt `wp_remote_get`. Der Client wählt die Quelle (Free/Partner) selbst. Prüfe bei neuen Endpunkten, ob die Free-API sie anbietet; sonst zeigt `swfl_endpoint_unavailable` den Partner-Hinweis.
- Neue Ausgabe als statische `render_*`-Methode in `SWFL_Display`; Ausgabe escapen, Strings mit Textdomain `swiss-floorball-api`.
- Interaktive Widgets (Navigation per JavaScript) liegen in `SWFL_Widgets` mit REST-Route (`swfl/v1`) und `public/js/swfl-widgets.js`.
- Neuer Shortcode: in `register_shortcodes()` registrieren, Callback mit `shortcode_atts()` und `absint()`, und den Namen in `page_has_shortcode()` aufnehmen (sonst werden Assets nicht geladen).

## Lokale Entwicklung

```bash
docker-compose up
```

Startet WordPress (`http://localhost:8000`) mit MariaDB, installiert WordPress, aktiviert das Plugin und legt die Seite «Shortcuts» mit allen Shortcodes sowie pro Shortcode eine eigene Seite (`/swfl-…/`, Skript `scripts/create-test-pages.sh`) an. Die Navigation erhält ein Dropdown «Shortcodes» mit allen Seiten, praktisch für saubere Screenshots. Ein WP-CLI-Service ist enthalten.

Standardmässig läuft die Free-API. Für Ligen, Topscorer & Co. in den Einstellungen die Partner-API mit Key und Secret aktivieren.

## Prüfungen

```bash
php scripts/check_release.php   # Version, ABSPATH, Textdomain
php scripts/tests/run.php       # Offline-Tests (ohne WordPress, ohne Netzwerk), optional mit Namensfilter
php verify_api.php              # Live-Smoke-Test gegen die echte API (Netzwerk nötig)
docker exec swiss-floorball-api-wordpress-1 php /var/www/html/wp-content/plugins/swiss-floorball-api/scripts/tests/integration.php
```

`scripts/tests/` (nicht im Release-ZIP) stubbt die wenigen WordPress-Funktionen, die die Klassen nutzen, und testet Cache, Budget, Zähler, Argumente, Besucher-Erkennung und Warm-up. Mit `$GLOBALS['swfl_t']['process'] = true` bildet der Fake den In-Process-Cache der Optionen nach; `swfl_test_db_write()` simuliert eine parallele Anfrage. `integration.php` läuft im Docker-WordPress gegen den echten REST-Server mit vorgegebenen API-Antworten und stellt die Einstellungen danach wieder her.

Mit `SWFL_API_SOURCE=partner php verify_api.php` wird die Partner-API getestet (gespeicherter Key und Secret nötig); Standard ist `free`.

`verify_api.php` lädt Client und Display mit gestubbten WordPress-Funktionen, ermittelt IDs aus der API und schlägt bei API-Fehlern, geänderten Antwortformen, PHP-Warnungen oder «Daten konnten nicht geladen werden» fehl. CI (`.github/workflows/ci.yml`): PHP-Lint 7.4–8.4, Offline-Tests auf 7.4/8.1/8.3/8.4, Release-Check, WordPress Plugin Check, phpcs (blockierend).

## Konventionen

WordPress Coding Standards (`.phpcs.xml.dist`), `ABSPATH`-Guard in jeder PHP-Datei, Escaping bei der Ausgabe, Nonce- und Capability-Prüfung bei Admin-Aktionen, CSS nur unter `.swiss-floorball-plugin` bzw. `.sfa-admin-wrap`. Releases laufen tag-getrieben (`vX.Y.Z`) über `.github/workflows/release.yml`.

Siehe auch [Architektur](architecture.md).
