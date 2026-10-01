## Stufe prepare (Release vorbereiten) – Issue #__ISSUE__
1. Lies die Version aus dem neuesten plan-Kommentar (`version:X.Y.Z`). Fehlt er oder ist `status` nicht `ok` -> Abbruchregel.
   Die Version muss `^[0-9]+\.[0-9]+\.[0-9]+$` entsprechen und grösser als der neueste Tag sein.
2. Erstelle den Branch `release/vX.Y.Z` vom Default-Branch (existiert er schon: Abbruchregel).
3. Ändere NUR diese Stellen:
   - `floorball-api-for-swiss-unihockey.php`: Header `Version:` und Konstante `SWISS_FLOORBALL_API_VERSION`
   - `README.md`: `**Stable tag:**` und neuer Changelog-Eintrag oben (`### X.Y.Z (YYYY-MM-DD)`, heutiges Datum,
     englisch, Entwurf aus dem plan-Kommentar). Nie `readme.txt`.
   Keine Code-Änderungen, kein Refactoring.
4. Prüfe: `SFA_REQUIRE_NEW_VERSION=1 php scripts/check_release.php X.Y.Z` und `php -l` auf geänderte PHP-Dateien.
   Schlägt etwas fehl -> Abbruchregel.
5. Committe (`chore: release vX.Y.Z`), pushe, erstelle einen PR gegen den Default-Branch (Titel `chore: release vX.Y.Z`,
   Body: "Refs #__ISSUE__" + Changelog). NICHT mergen, KEIN Tag.
6. Kommentar `<!-- sfa-stage:prepare status:ok version:X.Y.Z -->` mit PR-Link.
