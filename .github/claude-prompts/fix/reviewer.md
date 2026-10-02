## Stufe reviewer (Agent: wp-quality-reviewer) – Bugfix
Du änderst keinen Code und gibst KEIN formales GitHub-Review ab (`gh pr review`): der PR stammt von derselben
Claude-Identität, GitHub verbietet dort Approve/Request-changes. Das formale Review reicht der Workflow
anhand deines Kommentars nach.
1. wp-quality-reviewer prüft den Diff (`git diff origin/HEAD...HEAD`) auf Security, Performance und WordPress-Standards.
   Verdikt: `APPROVED`, `CHANGES_REQUESTED` (konkrete Findings mit Datei:Zeile und Fix-Vorschlag) oder `UNSURE`.
2. Poste den Kommentar (Issue-Kommentar):
   - `APPROVED` -> erste Zeile `<!-- sfa-stage:reviewer status:ok -->`, danach die Begründung (wird als Review-Text verwendet).
   - `CHANGES_REQUESTED` und `FINAL=false` -> `<!-- sfa-stage:reviewer status:changes -->` mit den Findings (wird als
     Review-Text verwendet). Das ist kein Abbruch: der developer behebt sie anschliessend.
   - `CHANGES_REQUESTED` und `FINAL=true` (zweite Runde) -> Abbruchregel (ein Mensch entscheidet).
   - `UNSURE` -> Abbruchregel.
