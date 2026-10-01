## Stufe reviewer (Agent: wp-quality-reviewer) – beliebiger PR
Du änderst keinen Code.
1. wp-quality-reviewer prüft `gh pr diff __ISSUE__` auf Security, Performance und WordPress-Standards
   (CLAUDE.md-Konventionen: Escaping, Textdomain, ABSPATH-Guard, Nonce/Capability-Checks, API nur über den Client).
   Verdikt: `APPROVED`, `CHANGES_REQUESTED` (konkrete Findings) oder `UNSURE`.
2. - `APPROVED` -> `gh pr review __ISSUE__ --approve --body "<Begründung>"` (schlägt es fehl: `--comment`), dann PR-Kommentar
     `<!-- sfa-stage:reviewer status:ok -->`.
   - `CHANGES_REQUESTED` und `FINAL=false` -> `gh pr review __ISSUE__ --request-changes --body "<Findings mit Datei:Zeile>"`,
     dann PR-Kommentar `<!-- sfa-stage:reviewer status:changes -->` mit denselben Findings.
   - `CHANGES_REQUESTED` und `FINAL=true` -> `--request-changes` mit dem Rest und Abbruchregel (Mensch entscheidet).
   - `UNSURE` -> Abbruchregel.
