# Shortcodes

Alle Shortcodes sind in `public/class-floorball-api-for-swiss-unihockey-public.php` registriert (`register_shortcodes()`). Jede Ausgabe steckt in einem `<div class="swiss-floorball-plugin">`. ID-Attribute werden mit `absint()` bereinigt. CSS/JS werden nur auf Seiten geladen, deren Inhalt einen dieser Shortcodes enthält.

Die benötigten IDs findest du in den [Helper-Seiten](admin.md).

| Shortcode | Attribute |
|---|---|
| `[swfl-club-teams]` | – |
| `[swfl-club-games]` | – |
| `[swfl-team-games]` | `team_id` |
| `[swfl-clubs]` | – |
| `[swfl-calendars]` | `team_id`, `club_id`, `season`, `league`, `game_class`, `group` |
| `[swfl-cups]` | – |
| `[swfl-groups]` | `season`, `league`, `game_class` |
| `[swfl-teams]` | – |
| `[swfl-rankings]` | `season`, `league`, `game_class`, `group` |
| `[swfl-player]` | `player_id` |
| `[swfl-national-players]` | – |
| `[swfl-topscorers]` | `season`, `league`, `game_class`, `group` |
| `[swfl-game-events]` | `game_id` |

Alle Attribute sind Ganzzahlen (ID bzw. Jahreszahl). Der Code erzwingt keine Pflichtattribute: Fehlt ein Wert, wird `0` bzw. leer an die API übergeben und die Ausgabe ist leer oder eine Fehlermeldung. «Pflicht (fachlich)» heisst unten: ohne den Wert ist die Ausgabe nicht sinnvoll.

## `swfl-club-teams`

Teams des Clubs aus den Einstellungen (Club-Nummer), Quelle `clubs/<id>/statistics`. Keine Attribute.

```text
[swfl-club-teams]
```

## `swfl-club-games`

Spiele des Clubs aus den Einstellungen in der aktuellen Saison (Option `swissfloorball_actual_season`). Keine Attribute.

```text
[swfl-club-games]
```

## `swfl-team-games`

Spiele eines Teams in der aktuellen Saison (aus den Einstellungen).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `team_id` | int | leer | ja (fachlich) |

```text
[swfl-team-games team_id="429757"]
```

## `swfl-clubs`

Liste aller Clubs (`clubs`). Keine Attribute.

## `swfl-calendars`

Kalender-Link und kommende Spiele (Quellen `games` und Kalender-URL `calendars`).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `team_id` | int | leer | eines von: `team_id`, `club_id` oder alle vier Gruppen-Attribute |
| `club_id` | int | leer | s. o. |
| `season` | int | leer (Fallback: aktuelle Saison) | nur im Gruppen-Modus |
| `league` | int | leer | nur im Gruppen-Modus |
| `game_class` | int | leer | nur im Gruppen-Modus |
| `group` | int | leer | nur im Gruppen-Modus |

Priorität: `team_id`, dann `club_id`, dann Gruppen-Modus (nur wenn `season`, `league`, `game_class` und `group` alle gesetzt sind). Sonst erscheint «Fehlende Parameter für Kalender.».

```text
[swfl-calendars team_id="429757"]
[swfl-calendars club_id="441"]
```

## `swfl-cups`

Liste der Cups (`cups`). Keine Attribute.

## `swfl-groups`

Gruppen einer Liga/Spielklasse (`groups`, Format `dropdown`).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `season` | int | Option `swissfloorball_actual_season` | nein |
| `league` | int | leer | ja (fachlich) |
| `game_class` | int | leer | ja (fachlich) |

```text
[swfl-groups league="1" game_class="11"]
```

## `swfl-teams`

Liste aller Teams (`teams`). Keine Attribute.

## `swfl-rankings`

Rangliste einer Gruppe (`rankings`).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `season` | int | Option `swissfloorball_actual_season` | nein |
| `league` | int | leer | ja (fachlich) |
| `game_class` | int | leer | ja (fachlich) |
| `group` | int | leer | ja (fachlich) |

```text
[swfl-rankings league="1" game_class="11" group="1"]
```

## `swfl-player`

Spielerprofil (`players/<id>`).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `player_id` | int | leer | ja (fachlich) |

```text
[swfl-player player_id="12345"]
```

## `swfl-national-players`

Liste der Nationalspieler (`national_players`). Keine Attribute.

## `swfl-topscorers`

Topscorer einer Gruppe (`topscorers`).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `season` | int | Option `swissfloorball_actual_season` | nein |
| `league` | int | leer | ja (fachlich) |
| `game_class` | int | leer | ja (fachlich) |
| `group` | int | leer | ja (fachlich) |

```text
[swfl-topscorers league="1" game_class="11" group="1"]
```

## `swfl-game-events`

Spielereignisse eines Spiels (`game_events/<id>`).

| Attribut | Typ | Default | Pflicht |
|---|---|---|---|
| `game_id` | int | leer | ja (fachlich) |

```text
[swfl-game-events game_id="1234567"]
```

Weiter: [Fehlerbehebung](troubleshooting.md), [Architektur](architecture.md).
