## Stufe developer (Agent: wp-developer) – Feature Request
Du befindest dich bereits auf dem Branch `claude/issue-__ISSUE__` (vom Workflow vorbereitet).
1. Lies den architect-Kommentar (Spec). Fehlt die Spec -> Abbruchregel.
2. Ist `FIX=true`: behebe NUR die offenen Findings. Quellen: der neueste reviewer-Kommentar im Issue sowie das
   Review am PR (`gh pr view <PR> --comments`, `gh api repos/$GITHUB_REPOSITORY/pulls/<PR>/reviews` und
   `.../pulls/<PR>/comments` für Inline-Kommentare) und der Zusatzhinweis. Ist unklar, was ein Finding verlangt -> Abbruchregel.
3. wp-developer liefert die Umsetzung. Setze genau die Spec um, nichts darüber hinaus (kein Scope Creep,
   keine Refactorings). Du schreibst die Dateiänderungen gemäss seinem Output, falls der Agent keine
   Schreibrechte hat.
4. Neue Strings sind übersetzbar (Textdomain `swiss-floorball-api`), Ausgaben escaped, neue Shortcode-Attribute
   sanitisiert (`absint()` bei IDs). Neues Verhalten ist abwärtskompatibel; bestehende Ausgaben ändern sich nicht.
5. Prüfe: `php -l` auf geänderten PHP-Dateien; bei Änderungen am API-Client/Display zusätzlich
   `php verify_api.php`. Schlägt etwas fehl und ist nicht trivial behebbar -> Abbruchregel.
6. Committe (Nachricht mit "Refs #__ISSUE__") und pushe `claude/issue-__ISSUE__`. Danach (nur wenn noch kein PR existiert, prüfe mit `gh pr list --head claude/issue-__ISSUE__`) PR gegen den Default-Branch erstellen: Titel `feat: ...`, Beschreibung "Closes #__ISSUE__" und Kurzfassung der Änderung. Kein Merge. Bei `FIX=true` existiert der PR bereits: nur pushen und am PR kommentieren, welche Findings behoben wurden.
7. Kommentar `<!-- sfa-stage:developer status:ok -->` mit geänderten Dateien, Prüfergebnissen und Link zum PR.
