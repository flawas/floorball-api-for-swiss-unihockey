# Entwicklung

## Filter

| Filter | Datei | Parameter | Zweck |
|---|---|---|---|
| `swfl_request_timeout` | `includes/class-floorball-api-for-swiss-unihockey-client.php` | `$timeout` (Standard `5`, Sekunden), `$url` | Timeout der API-Anfrage; Minimum 1 |
| `swfl_icon_svg` | `includes/class-floorball-api-for-swiss-unihockey-icons.php` | `$svg`, `$name`, `$args` | SVG-Markup eines Icons ändern; Ergebnis wird erneut mit `wp_kses()` gefiltert |

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

Eigene `do_action`-Hooks gibt es nicht.

## Erweiterung

- Neue API-Aufrufe immer über `Swiss_Floorball_API_Client::fetch_data()`, nie direkt `wp_remote_get`.
- Neue Ausgabe als statische `render_*`-Methode in `Swiss_Floorball_API_Display`; Ausgabe escapen, Strings mit Textdomain `swiss-floorball-api`.
- Neuer Shortcode: in `register_shortcodes()` registrieren, Callback mit `shortcode_atts()` und `absint()`, und den Namen in `page_has_shortcode()` aufnehmen (sonst werden Assets nicht geladen).

## Lokale Entwicklung

```bash
docker-compose up
```

Startet WordPress (`http://localhost:8000`) mit MariaDB, installiert WordPress, aktiviert das Plugin und legt die Seite «Shortcuts» mit den Shortcodes an (`docker-compose.yml`). Ein WP-CLI-Service ist enthalten.

## Prüfungen

```bash
php scripts/check_release.php   # Version, ABSPATH, Textdomain
php verify_api.php              # Live-Smoke-Test gegen die echte API (Netzwerk nötig)
```

`verify_api.php` lädt Client und Display mit gestubbten WordPress-Funktionen, ermittelt IDs aus der API und schlägt bei API-Fehlern, geänderten Antwortformen, PHP-Warnungen oder «Daten konnten nicht geladen werden» fehl. CI (`.github/workflows/ci.yml`): PHP-Lint 7.4–8.4, Release-Check, WordPress Plugin Check, phpcs (informativ).

## Konventionen

WordPress Coding Standards (`.phpcs.xml.dist`), `ABSPATH`-Guard in jeder PHP-Datei, Escaping bei der Ausgabe, Nonce- und Capability-Prüfung bei Admin-Aktionen, CSS nur unter `.swiss-floorball-plugin` bzw. `.sfa-admin-wrap`. Releases laufen tag-getrieben (`vX.Y.Z`) über `.github/workflows/release.yml`.

Siehe auch [Architektur](architecture.md).
