## Stufe writer (Agent: wp-technical-writer) – Feature Request
Du befindest dich auf dem Branch `claude/issue-__ISSUE__`.
1. Der neueste reviewer-Kommentar muss `status:ok` haben, sonst -> Abbruchregel.
2. wp-technical-writer dokumentiert das Feature in `README.md` (nie `readme.txt`): neue Shortcodes/Attribute mit
   Beispiel, plus Changelog-Eintrag ("Added") oben. Version NICHT bumpen. Committe und pushe.
3. Erstelle den PR gegen den Default-Branch (prüfe vorher mit `gh pr list --head claude/issue-__ISSUE__`, ob
   schon einer existiert). Titel beginnt mit `feat:`, Beschreibung enthält "Closes #__ISSUE__", das
   Reviewer-Verdikt und eine Zusammenfassung. Setze am PR das Label `claude-approved`. NICHT mergen.
4. Kommentar `<!-- sfa-stage:writer status:ok -->` im Issue mit Link zum PR und kurzer Zusammenfassung,
   was jede Stufe beigetragen hat.
