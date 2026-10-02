# Umstieg auf Version 2.0.0 (Breaking Changes)

Version 2.0.0 ist ein Major-Release. Bitte vor dem Update lesen, besonders wenn du Ligen, Gruppen, Topscorer, Spielerprofile oder Kalender-Abos nutzt.

## 1. Wechsel der API-Quelle

Die bisherige API (`https://api-v2.swissunihockey.ch/api/`) ist **keine API-Quelle mehr**. Das Plugin kennt jetzt zwei Quellen, einstellbar unter «Swiss Floorball» → «Einstellungen» → «API source» (Option `swissfloorball_api_source`):

| Quelle | Basis-URL | Zugangsdaten | Standard |
|---|---|---|---|
| Free API | `https://wc.swissunihockey.ch/` | keine | ja |
| Partner API | `https://office.swissunihockey.ch/api/legacy/` | API-Key und API-Secret | nein |

- Installationen, bei denen die alte Quelle gespeichert war, fallen automatisch auf die **Free API** zurück.
- Die Free API ist eine Positivliste (nur lesend). Endpunkte ausserhalb der Liste antworten mit HTTP 403; das Plugin zeigt dann den Hinweis «Diese Daten sind in der kostenlosen API nicht verfügbar».

### Was die Free API nicht liefert

Diese Funktionen brauchen die **Partner API**:

| Bereich | Betroffen |
|---|---|
| Ligen und Gruppen (ID-Finder) | Admin-Seite «Liga», Shortcode `[swfl-groups]` |
| Topscorer | `[swfl-topscorers]` |
| Spielerprofile | `[swfl-player]` |
| Nationalspieler | `[swfl-national-players]` |
| Spielereignisse | `[swfl-game-events]` |

Alles andere (Spiele, Ranglisten, Teams, Clubs, Saisons, Cups, Spieldetails, Kalender) funktioniert mit beiden Quellen.

### Partner API einrichten

1. «API source» auf **Partner API** stellen.
2. Die Felder **Partner API: API key** und **Partner API: API secret** erscheinen (bei «Free API» sind sie ausgeblendet). Beide ausfüllen, beide sind Pflicht.
3. Speichern. Das Plugin tauscht Key und Secret bei `bo/session/auth` gegen ein kurzlebiges Auth-Token (30 Minuten im Transient `swfl_partner_token`) und hängt es als `auth_token` an die Anfragen. Wird das Token abgelehnt (401/403), holt es einmal ein neues.

![Einstellungen mit gewählter Partner-API](media/admin-settings-partner.png)

Hinweis: Die Option `swissfloorball_api_key` existierte schon vorher, wurde aber nie für Anfragen verwendet. Ein dort gespeicherter Wert wird jetzt als Partner-Key interpretiert, aber nur, wenn die Quelle auf «Partner API» steht.

## 2. Kalender-Abos (ICS)

Der alte Kalenderexport der API wird nicht mehr verwendet. Das Plugin liefert einen **eigenen iCalendar-Feed**, gebaut aus den Spielen der API:

```text
/wp-json/swfl/v1/calendar?team_id=…
/wp-json/swfl/v1/calendar?club_id=…
/wp-json/swfl/v1/calendar?league=…&game_class=…&group=…   (optional: season)
```

Bereits abonnierte alte Kalender-URLs funktionieren nicht mehr und müssen neu abonniert werden. `[swfl-calendars]` erzeugt die neuen Links automatisch.

## 3. Geänderte Shortcodes

| Shortcode | Änderung |
|---|---|
| `[swfl-club-games]` | Zeigt die Spiele wochenweise mit Vor-/Zurück-Buttons. Neues Attribut `season`. |
| `[swfl-team-games]` | Blättert um das nächste Spiel herum. Neue Attribute `season` und `page_size` (Standard 4). |
| `[swfl-rankings]` | `group` ist jetzt der **Gruppenname** (z. B. `Gruppe 1`), nicht mehr eine Gruppen-ID. Neues optionales Attribut `view`. |
| `[swfl-topscorers]` | Nutzt den Endpunkt `topscorers/su`; `group` wird ignoriert. |
| `[swfl-mobiliar-topscorer]` | Neu. Ohne `club_id` die Topscorer der ganzen Liga, mit `club_id` nur die eines Clubs. Es gibt keinen Standard-Club mehr. |
| `[swfl-league-games]`, `[swfl-club-team-games]` | Neu, angelehnt an die offiziellen Swiss-Unihockey-Webcomponents (`uniho-*`). |

Attribute mit Bindestrich (`game-class`, `club-id`, `page-size`) funktionieren wie die Unterstrich-Form.

Beispiel `[swfl-mobiliar-topscorer]` ohne `club_id`:

![Mobiliar-Topscorer der ganzen Liga](media/mobiliar-topscorer-desktop.png)

Die Navigation (Woche, Seite, Runde) braucht JavaScript und die REST-Routen `swfl/v1/team-games`, `swfl/v1/league-games` und `swfl/v1/calendar` (öffentlich, nur lesend). Ohne JavaScript bleibt die erste Ansicht sichtbar. Sperrt ein Sicherheits-Plugin die REST-API, funktioniert die Navigation nicht.

## 4. Neues Aussehen und Markup

- Tabellen erscheinen im **Flat-Stil** (Standard): keine Rahmen oder Schatten, dünne Trennlinien. Der bisherige Look ist mit «Table style» = `classic` weiter verfügbar.
- Tabellen stecken in `.sfa-table-wrap` und scrollen bei schmalem Platz horizontal statt in eine Kartenansicht zu wechseln. Das Layout richtet sich nach der Breite des Widgets (Container Queries), nicht nach dem Bildschirm.
- Farben sind CSS-Variablen `--sfa-sys-*`, gültig nur innerhalb von `.swiss-floorball-plugin` und `.sfa-admin-wrap`. Eigene CSS-Overrides auf alte Klassen oder Variablen (`--sfa-primary`, `--sfa-secondary` wirken weiter, werden aber von «Seed colour» überschrieben) bitte prüfen.
- Der Kalender zeigt die Spaltenköpfe «Heim/Gast».

## 5. Neue Einstellungen

Alle Optionen werden bei der Deinstallation gelöscht.

| Option | Bedeutung |
|---|---|
| `swissfloorball_api_source` | `free` (Standard) oder `partner` |
| `swissfloorball_api_key`, `swissfloorball_api_secret` | Zugangsdaten der Partner API |
| `swissfloorball_theme` | `auto` (Standard), `light`, `dark` |
| `swissfloorball_seed_color` | Markenfarbe als Hex, leitet Primär- und Sekundärfarben ab |
| `swissfloorball_table_style` | `flat` (Standard) oder `classic` |
| `swissfloorball_table_striped` | Zebra-Streifen (Standard aus) |
| `swissfloorball_table_{accent,header,divider,highlight}_color` | Tabellenfarben |

## 6. Admin-Bereich

- Neues Dashboard mit Kennzahlen (Club, Club-ID, Saison, API-Quelle) und Kachelnavigation.
- Die Club-Übersicht (Teams) und die Clubspiele sind auf die eigene Seite «Club-Übersicht» umgezogen.
- Die Seite «Liga» und andere Helfer, die Ligen oder Gruppen laden, zeigen mit der Free API den Partner-Hinweis.

## Checkliste vor dem Update

1. Nutzt du `[swfl-topscorers]`, `[swfl-player]`, `[swfl-national-players]`, `[swfl-game-events]` oder `[swfl-groups]`? Dann Partner-API-Zugang bei Swiss Unihockey besorgen oder die Shortcodes entfernen.
2. Sind in `[swfl-rankings]` numerische Gruppen eingetragen? Auf Gruppennamen umstellen (`Gruppe 1`).
3. Gibt es abonnierte Kalender? Neu abonnieren.
4. Eigenes CSS auf Plugin-Klassen? Nach dem Update prüfen, notfalls «Table style» = `classic`.
5. Nach dem Update den Cache leeren («Cache leeren» in den Einstellungen).
