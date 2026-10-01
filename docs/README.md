# Swiss Floorball API – Dokumentation

Das WordPress-Plugin «Swiss Floorball API» (Version 1.0.5) holt Daten von der öffentlichen Swiss-Unihockey-API (`https://api-v2.swissunihockey.ch/api/`) und stellt sie per Shortcode (`swfl-*`) und im Admin-Bereich dar.

## Inhalt

- [Shortcodes](shortcodes.md) – alle Shortcodes mit Attributen und Beispielen
- [Administration](admin.md) – Einstellungen und Helper-Seiten, IDs finden
- [Architektur](architecture.md) – Klassen, Datenfluss, Caching, API-Antwortformen
- [Entwicklung](developer.md) – Filter, Erweiterung, Docker, `verify_api.php`, Konventionen
- [Fehlerbehebung](troubleshooting.md) – häufige Probleme

## Schnellstart

1. Plugin aktivieren. Im Admin-Menü «Swiss Floorball» → «Einstellungen» die **Club-Nummer** und die **aktuelle Saison** (Jahreszahl, z. B. `2025`) eintragen.
2. Unter «Liga», «Clubs», «Spiele» und «Saison» die IDs für Liga, Spielklasse, Gruppe, Team oder Spiel nachschlagen (siehe [Administration](admin.md)).
3. Shortcode in eine Seite einfügen:

```text
[swfl-club-games]
[swfl-rankings league="1" game_class="11" group="1"]
```

Die Saison wird bei Shortcodes ohne eigenes `season`-Attribut aus der Einstellung übernommen.

## Voraussetzung

Der Webserver muss `api-v2.swissunihockey.ch` per HTTPS erreichen können. Ein API-Key wird vom Plugin nicht für Anfragen verwendet (siehe [Administration](admin.md#einstellungen)).
