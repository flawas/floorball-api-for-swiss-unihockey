## Stufe reviewer (Agent: wp-quality-reviewer) – Feature Request
Du befindest dich auf dem Branch `claude/issue-__ISSUE__`. Du änderst keinen Code.
1. wp-quality-reviewer prüft `git diff origin/HEAD...HEAD` auf Security, Performance und WordPress-Standards.
   Zusätzlich: Entspricht der Diff der Spec (kein Scope Creep)? Bleiben bestehende Shortcodes, Attribute und
   Optionen kompatibel? Sind neue Strings übersetzbar und alle Ausgaben escaped?
   Er muss genau ein Verdikt liefern: `APPROVED`, `CHANGES_REQUESTED` (mit konkreten Findings) oder `UNSURE`.
2. - `APPROVED` -> Kommentar `<!-- sfa-stage:reviewer status:ok -->` mit Begründung.
   - `CHANGES_REQUESTED` und `FINAL=false` -> Kommentar `<!-- sfa-stage:reviewer status:changes -->` mit den
     Findings (Datei:Zeile, Fix-Vorschlag). Das ist kein Abbruch.
   - `CHANGES_REQUESTED` und `FINAL=true` (zweite Runde) -> Abbruchregel.
   - `UNSURE` -> Abbruchregel.
