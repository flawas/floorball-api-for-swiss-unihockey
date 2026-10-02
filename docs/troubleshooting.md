# Fehlerbehebung

## Ausgabe ist leer oder «Daten konnten nicht geladen werden»

- Fehlen IDs (`league`, `game_class`, `group`, `team_id`, …)? Werte in den [Helper-Seiten](admin.md) prüfen. Fehlende Attribute werden zu `0`.
- Sind Club-Nummer und aktuelle Saison in den Einstellungen gesetzt? `swfl-club-games`, `swfl-club-teams` und `swfl-team-games` brauchen sie.
- Passt die Saison zu den IDs? Gruppen und Ranglisten gelten pro Saison.

## API nicht erreichbar

Der Server muss `api-v2.swissunihockey.ch` per HTTPS erreichen (Firewall, `WP_HTTP_BLOCK_EXTERNAL`). Der Standard-Timeout beträgt 3 Sekunden; bei langsamen Servern die Einstellung «API request timeout» erhöhen oder den Filter `swfl_request_timeout` nutzen ([Entwicklung](developer.md#filter)). Fehlerantworten werden nicht gecacht. Mit `php verify_api.php` lässt sich die API unabhängig von WordPress testen.

## Alte Daten werden angezeigt

Antworten werden 1 Stunde gecacht. Unter «Swiss Floorball» → «Einstellungen» den Cache leeren ([Administration](admin.md#cache-leeren)). Eine Änderung der Club-Nummer leert ihn automatisch.

## Club-Name bleibt leer

Der Name wird beim Speichern der Club-Nummer aus der Clubliste (`clubs`) ermittelt. Bei API-Fehler oder unbekannter ID bleibt er leer – Nummer prüfen und erneut speichern.

## Styles fehlen

CSS/JS werden nur geladen, wenn der Beitragsinhalt einen `swfl-*`-Shortcode enthält. Shortcodes in Widgets, Templates oder Page-Buildern lösen das nicht aus.

## Kalender zeigt «Fehlende Parameter für Kalender.»

`swfl-calendars` braucht `team_id`, `club_id` oder alle vier Attribute `season`, `league`, `game_class`, `group` ([Shortcodes](shortcodes.md#swfl-calendars)).
