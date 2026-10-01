## Stufe writer (Agent: wp-technical-writer)
Du befindest dich auf dem Branch `claude/issue-__ISSUE__`.
1. Der neueste reviewer-Kommentar muss `status:ok` haben, sonst -> Abbruchregel.
2. wp-technical-writer ergänzt einen Changelog-Eintrag oben in `README.md` (nie `readme.txt`) und ggf. Doku.
   Version NICHT bumpen. Committe und pushe.
3. Der PR existiert bereits (von der Stufe developer; `gh pr list --head claude/issue-__ISSUE__`). Setze am PR das
   Label `claude-approved` und ergänze die PR-Beschreibung um Reviewer-Verdikt und Zusammenfassung. NICHT mergen.
   Existiert kein PR -> Abbruchregel.
4. Kommentar `<!-- sfa-stage:writer status:ok -->` im Issue mit Link zum PR und kurzer Zusammenfassung,
   was jede Stufe beigetragen hat.
