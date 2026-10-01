## Stufe writer (Agent: wp-technical-writer)
Du befindest dich auf dem Branch `claude/issue-__ISSUE__`.
1. Der neueste reviewer-Kommentar muss `status:ok` haben, sonst -> Abbruchregel.
2. wp-technical-writer ergänzt einen Changelog-Eintrag oben in `README.md` (nie `readme.txt`) und ggf. Doku.
   Version NICHT bumpen. Committe und pushe.
3. Erstelle den PR gegen den Default-Branch (prüfe vorher mit `gh pr list --head claude/issue-__ISSUE__`, ob
   schon einer existiert). Beschreibung: "Fixes #__ISSUE__", Reviewer-Verdikt und Zusammenfassung. Setze am PR
   das Label `claude-approved`. NICHT mergen.
4. Kommentar `<!-- sfa-stage:writer status:ok -->` im Issue mit Link zum PR und kurzer Zusammenfassung,
   was jede Stufe beigetragen hat.
