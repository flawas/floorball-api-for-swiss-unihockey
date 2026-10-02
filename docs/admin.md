# Administration

Das Menü «Swiss Floorball» (Capability `manage_options`, Menüposition 26) wird in `admin/class-floorball-api-for-swiss-unihockey-admin.php` (`addPluginAdminMenu()`) angelegt.

## Einstellungen

Untermenü «Einstellungen» (Gruppe `swfl_general_settings`). Gespeichert als WordPress-Optionen:

| Option | Bedeutung | Bereinigung |
|---|---|---|
| `swissfloorball_club_number` | Club-Nummer (ID) | `absint`; bei Änderung wird der API-Cache geleert und der Club-Name automatisch geholt |
| `swissfloorball_club_name` | Club-Name, nur Anzeige (wird automatisch gesetzt) | – |
| `swissfloorball_actual_season` | Aktuelle Saison als Jahreszahl, z. B. `2025` | `absint` |
| `swissfloorball_show_icons` | Icons anzeigen (Standard `1`) | `'1'` oder `'0'` |
| `swissfloorball_request_timeout` | API-Timeout in Sekunden (1–30, Standard `3`) | `absint`, begrenzt auf 1–30; 0/ungültig → `3` |
| `swissfloorball_api_key` | Optionaler API-Key | `sanitize_text_field` |

Hinweis: `swissfloorball_api_key` wird im Code nur gespeichert und bei der Deinstallation gelöscht; der Client sendet ihn nicht mit.

### Cache leeren

Auf der Einstellungsseite löst ein Formular `admin_post_swfl_clear_cache` aus (Nonce `swfl_clear_cache_action`, Capability `manage_options`). Es löscht alle Transients `swfl_*` und leitet mit Hinweis zurück.

## Helper-Seiten

| Seite | Zweck |
|---|---|
| Dashboard | Überblick über den konfigurierten Club |
| Liga | Ligen (`leagues`) mit IDs für `league` und `game_class` |
| Clubs | Clubs (`clubs`) mit Club-IDs |
| Spiele | Spiele des Clubs; Klick auf ein Spiel zeigt Details und Ereignisse (`match_id`) und liefert die Spiel-ID |
| Saison | Saisons (`seasons`) für `season` |
| Shortcodes | Übersicht der Shortcodes |

Die Seiten nutzen dieselben `Swiss_Floorball_API_Display::render_*`-Methoden wie die Shortcodes (siehe [Architektur](architecture.md)).

## IDs finden

- **Club-ID:** Seite «Clubs».
- **Saison:** Seite «Saison» (Jahreszahl).
- **Liga / Spielklasse (`league`, `game_class`):** Seite «Liga».
- **Gruppe:** Shortcode [`swfl-groups`](shortcodes.md#swfl-groups) mit Liga und Spielklasse.
- **Team-ID:** Shortcode `swfl-club-teams` bzw. Seite «Clubs».
- **Spiel-ID:** Seite «Spiele».

Weiter: [Fehlerbehebung](troubleshooting.md).
