# Swiss Floorball API – Dokumentation

Das WordPress-Plugin «Swiss Floorball API» (Version 2.0.0) holt Daten von der kostenlosen Swiss-Unihockey-API (`https://wc.swissunihockey.ch/`, optional von der Partner-API) und stellt sie per Shortcode (`swfl-*`) und im Admin-Bereich dar.

![Rangliste mit swfl-rankings](media/rankings-desktop.png)

## Inhalt

- [Umstieg auf 2.0.0](migration.md) – Breaking Changes, API-Wechsel, Checkliste
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

Der Webserver muss `wc.swissunihockey.ch` per HTTPS erreichen können. Für Ligen, Gruppen, Topscorer, Spielerprofile, Nationalspieler und Spielereignisse ist die Partner-API (`office.swissunihockey.ch`) mit API-Key und Secret nötig, siehe [Umstieg auf 2.0.0](migration.md) und [Administration](admin.md#einstellungen).
