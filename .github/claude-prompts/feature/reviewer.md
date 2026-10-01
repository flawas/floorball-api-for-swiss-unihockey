## Stufe reviewer (Agent: wp-quality-reviewer) – Feature Request
Du befindest dich auf dem Branch `claude/issue-__ISSUE__`. Du änderst keinen Code.
1. wp-quality-reviewer prüft `git diff origin/HEAD...HEAD` auf Security, Performance und WordPress-Standards.
   Zusätzlich: Entspricht der Diff der Spec (kein Scope Creep)? Bleiben bestehende Shortcodes, Attribute und
   Optionen kompatibel? Sind neue Strings übersetzbar und alle Ausgaben escaped?
   Er muss genau ein Verdikt liefern: `APPROVED`, `CHANGES_REQUESTED` (mit konkreten Findings) oder `UNSURE`.
2. Finde den PR: `gh pr list --head claude/issue-__ISSUE__`. Existiert keiner -> Abbruchregel.
   Das Review wird formal am PR abgegeben:
   - `APPROVED` -> `gh pr review <PR> --approve --body "<Begründung>"`, dann Issue-Kommentar
     `<!-- sfa-stage:reviewer status:ok -->` mit Begründung. Schlägt `--approve` fehl (Repo-Einstellung),
     kommentiere stattdessen mit `gh pr review <PR> --comment` und vermerke das im Issue-Kommentar.
   - `CHANGES_REQUESTED` und `FINAL=false` -> `gh pr review <PR> --request-changes --body "<Findings>"`
     (Datei:Zeile, Fix-Vorschlag), dann Issue-Kommentar `<!-- sfa-stage:reviewer status:changes -->` mit denselben
     Findings. Das ist kein Abbruch: der developer behebt sie anschliessend am selben PR.
   - `CHANGES_REQUESTED` und `FINAL=true` (zweite Runde) -> `gh pr review <PR> --request-changes` mit dem Rest
     und Abbruchregel (der PR bleibt für einen Menschen offen).
   - `UNSURE` -> Abbruchregel.
