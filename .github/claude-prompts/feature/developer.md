## Stufe developer (Agent: wp-developer) – Feature Request
Du befindest dich bereits auf dem Branch `claude/issue-__ISSUE__` (vom Workflow vorbereitet).
1. Lies den architect-Kommentar (Spec). Fehlt die Spec -> Abbruchregel.
2. Ist `FIX=true`: lies zusätzlich den neuesten reviewer-Kommentar und behebe NUR dessen Findings.
3. wp-developer liefert die Umsetzung. Setze genau die Spec um, nichts darüber hinaus (kein Scope Creep,
   keine Refactorings). Du schreibst die Dateiänderungen gemäss seinem Output, falls der Agent keine
   Schreibrechte hat.
4. Neue Strings sind übersetzbar (Textdomain `swiss-floorball-api`), Ausgaben escaped, neue Shortcode-Attribute
   sanitisiert (`absint()` bei IDs). Neues Verhalten ist abwärtskompatibel; bestehende Ausgaben ändern sich nicht.
5. Prüfe: `php -l` auf geänderten PHP-Dateien; bei Änderungen am API-Client/Display zusätzlich
   `php verify_api.php`. Schlägt etwas fehl und ist nicht trivial behebbar -> Abbruchregel.
6. Committe (Nachricht mit "Refs #__ISSUE__") und pushe `claude/issue-__ISSUE__`. Kein PR, kein Merge.
7. Kommentar `<!-- sfa-stage:developer status:ok -->` mit geänderten Dateien und Prüfergebnissen.
