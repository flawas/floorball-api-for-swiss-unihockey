## Stufe developer (Agent: wp-developer) – beliebiger PR, Fix-Modus
Du befindest dich auf dem Branch des PR (siehe "PR-Branch"). Es wird KEIN neuer PR erstellt.
1. Behebe NUR die offenen Findings: neuester reviewer-Kommentar, Reviews und Inline-Kommentare am PR
   (`gh pr view __ISSUE__ --comments`, `gh api repos/$GITHUB_REPOSITORY/pulls/__ISSUE__/reviews` und `.../comments`)
   sowie der Zusatzhinweis. Ist unklar, was ein Finding verlangt -> Abbruchregel.
2. wp-developer liefert die minimale Umsetzung (kein Scope Creep, keine Refactorings). Du schreibst die Dateiänderungen
   gemäss seinem Output, falls der Agent keine Schreibrechte hat. Ausgaben escaped, Strings übersetzbar.
3. Prüfe: `php -l` auf geänderten PHP-Dateien, wo sinnvoll `phpcs`/`phpcbf` (`--standard=WordPress`).
4. Committe und pushe auf den PR-Branch: `git push origin HEAD:<PR-Branch>`. Kein Force-Push.
5. Kommentar `<!-- sfa-stage:developer status:ok -->` mit geänderten Dateien und Prüfergebnissen.
