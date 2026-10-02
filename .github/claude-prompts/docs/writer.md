## Stufe writer (Agent: wp-technical-writer) – Dokumentation des gesamten Plugins
Du befindest dich auf dem Branch `claude/issue-__ISSUE__` (vom Workflow vorbereitet). Das Issue ist nur das Tracking.
Die Doku wird bei jedem Lauf für das **gesamte Plugin** neu abgeglichen, nicht nur für einzelne Änderungen.
Sprache: siehe Zusatzhinweis ("Sprache der Dokumentation"); fehlt sie, Englisch.

1. **Bestandsaufnahme (graphify zuerst, dann gezielt lesen):** Hauptdatei und Version, alle Klassen unter `includes/`,
   `admin/`, `public/`, alle Shortcodes (`add_shortcode`) mit ihren Attributen und Defaults, Admin-Seiten und
   Einstellungen (`swissfloorball_*`), API-Endpunkte im Client, Caching (Transients `swfl_<md5>`), Hooks/Filter
   (`apply_filters`/`do_action`), Aktivierung/Deaktivierung/Deinstallation, Übersetzung, Docker-Entwicklung,
   `verify_api.php`. Was du dokumentierst, musst du im Code belegen können (Datei:Zeile) – nichts erfinden.
2. **Schreibe/aktualisiere `docs/`** (der Agent liefert Struktur und Texte; du schreibst die Dateien selbst mit Write/Edit) (Dateien bei Bedarf anlegen, veraltetes entfernen/korrigieren):
   - `docs/README.md` – Inhaltsverzeichnis, Kurzüberblick, Schnellstart
   - `docs/shortcodes.md` – JEDER Shortcode: Zweck, alle Attribute (Typ, Default, Pflicht), Beispiel, Hinweise
   - `docs/admin.md` – Einstellungen und Helper-Seiten (Liga, Clubs, Spiele, Saison, Shortcodes), wie man IDs findet
   - `docs/architecture.md` – Aufbau (Klassen, Datenfluss Client → Display → Shortcode/Admin), Caching, API-Antwortformen
   - `docs/developer.md` – Hooks/Filter, Erweiterung, lokale Entwicklung (Docker, `verify_api.php`), Konventionen
   - `docs/troubleshooting.md` – häufige Probleme (leere Ausgabe, Cache leeren, API nicht erreichbar, falsche IDs)
   Verwende Markdown mit Codeblöcken, relative Links zwischen den Seiten, keine Screenshots.
   **Medien:** Existiert `docs/media/manifest.json` (erzeugt vom Workflow "Docs Media"), bette die dort gelisteten
   Screenshots/Videos an passender Stelle ein: Frontend-Screenshots (`<id>-desktop.png`, `<id>-mobile.png`) bei dem
   jeweiligen Shortcode in `docs/shortcodes.md`, Admin-Screenshots (`admin-*.png`) in `docs/admin.md`, das GIF
   `walkthrough.gif` in `docs/README.md` (mit Link auf `walkthrough.mp4`). Nur Dateien einbetten, die in `manifest.json`
   unter `screenshots`/`videos` stehen und wirklich existieren; übersprungene (`skipped`) nicht erwähnen. Relative
   Pfade (`media/<datei>`), sinnvoller Alt-Text. Existiert das Manifest nicht, keine Bilder erfinden oder verlinken.
3. **Konsistenzprüfung vor dem Commit:** Jeder `swfl-*`-Shortcode aus dem Code steht in `docs/shortcodes.md` und
   umgekehrt; jedes dokumentierte Attribut/Filter existiert im Code (mit `grep` prüfen); Versionsnummer stimmt mit
   dem `Version:`-Header überein; alle relativen Links zeigen auf existierende Dateien.
4. **Nicht anfassen:** `README.md` im Repo-Root (Quelle für `readme.txt`/WordPress.org), `readme.txt`, Changelog,
   Versionsnummern, Quellcode. Nur Dateien unter `docs/` ändern. Keine Zugangsdaten oder interne URLs aufnehmen.
5. Committe (Nachricht `docs: update plugin documentation`, "Refs #__ISSUE__") und pushe `claude/issue-__ISSUE__`.
   Erstelle einen PR gegen den Default-Branch (Titel `docs: update plugin documentation`, Beschreibung "Closes #__ISSUE__",
   Liste der aktualisierten Seiten und was sich gegenüber vorher geändert hat). Label `documentation`. NICHT mergen.
6. Kommentar `<!-- sfa-stage:writer status:ok -->` im Issue mit Link zum PR und einer Kurzfassung (neue/geänderte/
   entfernte Seiten, bemerkte Lücken oder Unklarheiten im Code).
