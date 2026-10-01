## Stufe developer (Agent: wp-developer)
Du befindest dich bereits auf dem Branch `claude/issue-__ISSUE__` (vom Workflow vorbereitet).
1. Lies den architect-Kommentar (Spec) und ggf. den debugger-Kommentar. Fehlt die Spec -> Abbruchregel.
2. Ist `FIX=true`: lies zusätzlich den neuesten reviewer-Kommentar und behebe NUR dessen Findings.
3. wp-developer liefert die minimale Umsetzung. Du schreibst die Dateiänderungen gemäss seinem Output,
   falls der Agent keine Schreibrechte hat.
4. Prüfe: `php -l` auf geänderten PHP-Dateien; bei Änderungen am API-Client/Display zusätzlich
   `php verify_api.php`. Schlägt etwas fehl und ist nicht trivial behebbar -> Abbruchregel.
5. Committe (Nachricht mit "Refs #__ISSUE__") und pushe `claude/issue-__ISSUE__`. Kein PR, kein Merge.
6. Kommentar `<!-- sfa-stage:developer status:ok -->` mit geänderten Dateien und Prüfergebnissen.
