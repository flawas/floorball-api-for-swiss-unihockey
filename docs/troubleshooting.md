# Fehlerbehebung

## Ausgabe ist leer oder «Daten konnten nicht geladen werden»

- Fehlen IDs (`league`, `game_class`, `group`, `team_id`, …)? Werte in den [Helper-Seiten](admin.md) prüfen. Fehlende Attribute werden zu `0`.
- Sind Club-Nummer und aktuelle Saison in den Einstellungen gesetzt? `swfl-club-games`, `swfl-club-teams` und `swfl-team-games` brauchen sie.
- Passt die Saison zu den IDs? Gruppen und Ranglisten gelten pro Saison.

## «Diese Daten sind in der kostenlosen API nicht verfügbar»

Ligen, Gruppen, Topscorer, Spielerprofile, Nationalspieler und Spielereignisse liefert nur die Partner-API. In den Einstellungen «API source» auf **Partner API** stellen und API-Key und Secret eintragen ([Umstieg auf 2.0.0](migration.md#1-wechsel-der-api-quelle)).

## «Anmeldung an der Partner-API fehlgeschlagen»

Key oder Secret fehlen oder sind falsch. Beide Werte unter «Partner API: API key» und «Partner API: API secret» prüfen (Leerzeichen am Rand, richtiges Paar). Das Speichern verwirft das zwischengespeicherte Token. Der Server muss ausserdem `office.swissunihockey.ch` erreichen.

## Die Partner-Felder fehlen in den Einstellungen

Sie erscheinen nur bei «API source» = **Partner API**. Erscheinen sie trotzdem nicht, Browser-Cache leeren (harter Reload) und prüfen, dass kein JavaScript-Fehler auf der Seite auftritt.

## Woche, Seite oder Runde lässt sich nicht umschalten

Die Navigation der Widgets nutzt JavaScript und die REST-Routen `/wp-json/swfl/v1/…`. Ist die REST-API gesperrt (Sicherheits-Plugin, Firewall), bleibt nur die erste Ansicht sichtbar. Erscheint über dem Widget «Die Daten konnten gerade nicht geladen werden», hat das Plugin die Anfrage abgewiesen (siehe nächster Abschnitt) – nach kurzer Zeit erneut versuchen.

## REST-Routen antworten mit 400, 429 oder 503

- **400 `rest_invalid_param`:** Ein Parameter ist ungültig, bevor die API gefragt wird. IDs sind ganze Zahlen von 1 bis 10 000 000 (optionale IDs dürfen leer oder `0` sein), `season` ist leer, `0` oder ein Startjahr von 1996 bis zum nächsten Jahr, `group` höchstens 40 Buchstaben, Ziffern, Leerzeichen und `. _ / -`.
- **429 `swfl_rate_limited`:** Ein Besucher hat mehr als «REST requests per minute and visitor» Anfragen gesendet. Teilen sich alle Besucher eine Adresse (Reverse-Proxy, CDN, Docker), den Proxy unter «Trusted proxies» eintragen oder die echte Adresse im Webserver wiederherstellen ([Administration](admin.md#vertrauenswürdige-proxys)). Welche Adressen gerade blockiert sind, zeigt die Einstellungsseite unter «Blocked visitors» ([Administration](admin.md#blockierte-besucher)).
- **429 `swfl_budget_exceeded`:** Ein Besucher hat seinen Anteil «Upstream requests per minute and visitor» an ungecachten API-Anfragen aufgebraucht (Standard 20 pro Minute). Gecachte Antworten und abgelaufene Kopien bekommt er weiter. Lädt ein normaler Besucher sehr lange Listen, den Wert erhöhen.
- **503 `swfl_budget_exceeded`:** Das seitenweite Limit «Upstream requests per minute» ist für die laufende Minute aufgebraucht. Gecachte Antworten kommen weiter, abgelaufene Kopien werden kurz weiter ausgeliefert, sonst sagt `Retry-After`, wann es wieder geht. Bei normalem Verkehr das Limit erhöhen.

Erste Verteidigungslinie gegen Fluten sollte eine Rate-Limit-Regel im CDN oder in der Web Application Firewall für `/wp-json/swfl/` sein (z. B. eine Cloudflare-Rate-Limiting-Regel); die Limits des Plugins greifen erst, wenn PHP schon läuft.

## Alter Kalender-Abo-Link funktioniert nicht mehr

Der alte Kalenderexport der API entfällt. `[swfl-calendars]` zeigt die neue URL (`/wp-json/swfl/v1/calendar?…`) – neu abonnieren ([Umstieg auf 2.0.0](migration.md#2-kalender-abos-ics)).

## API nicht erreichbar

Der Server muss `wc.swissunihockey.ch` (Partner-API: `office.swissunihockey.ch`) per HTTPS erreichen (Firewall, `WP_HTTP_BLOCK_EXTERNAL`). Der Standard-Timeout beträgt 3 Sekunden; bei langsamen Servern die Einstellung «API request timeout» erhöhen oder den Filter `swfl_request_timeout` nutzen ([Entwicklung](developer.md#filter)). Fehlgeschlagene Anfragen werden kurz gemerkt (dauerhafte Fehler wie unbekannte IDs 5 Minuten, vorübergehende wie Timeouts und 5xx höchstens 60 Sekunden, Filter `swfl_error_cache_ttl`), damit dieselbe Anfrage die API nicht sofort erneut trifft; «Cache leeren» verwirft sie. Mit `php verify_api.php` lässt sich die API unabhängig von WordPress testen.

## Alte Daten werden angezeigt

Antworten werden je nach Endpunkt gecacht (Standard 1 Stunde, einstellbar pro Endpunkt). Ist das Limit «Upstream requests per minute» aufgebraucht, liefern die REST-Routen bis zu einer Stunde lang eine abgelaufene Kopie aus. Unter «Swiss Floorball» → «Einstellungen» den Cache leeren ([Administration](admin.md#cache-leeren)). Eine Änderung der Club-Nummer leert ihn automatisch.

## Club-Name bleibt leer

Der Name wird beim Speichern der Club-Nummer aus der Clubliste (`clubs`) ermittelt. Bei API-Fehler oder unbekannter ID bleibt er leer – Nummer prüfen und erneut speichern.

## Styles fehlen

CSS/JS werden nur geladen, wenn der Beitragsinhalt einen `swfl-*`-Shortcode enthält, oder wenn das Widget «Nächstes Spiel und letztes Resultat» aktiv ist. Shortcodes in Widgets, Templates oder Page-Buildern lösen das nicht aus.

## Kalender zeigt «Fehlende Parameter für Kalender.»

`swfl-calendars` braucht `team_id`, `club_id` oder alle vier Attribute `season`, `league`, `game_class`, `group` ([Shortcodes](shortcodes.md#swfl-calendars)).
