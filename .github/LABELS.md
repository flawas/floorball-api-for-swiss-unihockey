# Labels

Übersicht aller Labels im Repo und was sie bewirken. Nur die `claude-*`-Labels und `sonarqube` haben Einfluss auf Workflows; die GitHub-Standardlabels dienen der Einordnung.

## Start-Labels (lösen einen Lauf aus)

| Label | Setzen an | Wirkung | Workflow |
|---|---|---|---|
| `claude-fix` | Issue | Bugfix-Ablauf: debugger → architect → developer → PR → reviewer → writer | [claude-auto-fix.yml](workflows/claude-auto-fix.yml) |
| `claude-feature` | Issue | Feature-Ablauf: architect → developer → PR → reviewer → writer (keine debugger-Stufe) | [claude-feature.yml](workflows/claude-feature.yml) |
| `claude-release` | Issue (Release-Tracking) | Release-Ablauf: plan → prepare → CI → verify → publish | [claude-release.yml](workflows/claude-release.yml) |
| `sonarqube` | **PR** (Branch `claude/issue-<nr>`) | Holt die Sonar-Findings des PR; bei Findings behebt der developer sie, danach reviewer/writer erneut. Das Label wird danach entfernt, damit es erneut gesetzt werden kann. | [claude-sonar-pr.yml](workflows/claude-sonar-pr.yml) |

Hinweise:
- `claude-fix` und `claude-feature` nicht gleichzeitig setzen.
- `sonarqube` an einem **Issue** bewirkt nichts; es markiert dort nur Issues, die der Sonar-Fixer angelegt hat ([claude-sonar.yml](workflows/claude-sonar.yml)). Dieser setzt sie zusammen mit `claude-fix`.
- Ein bereits gesetztes Label löst nichts aus: zum Neustart entfernen und neu setzen, oder per Kommentar steuern (siehe unten).

## Status-Labels (setzt Claude)

| Label | Gesetzt an | Bedeutung |
|---|---|---|
| `claude-aborted` | Issue | Eine Stufe hat im Zweifel abgebrochen; der Grund steht im Kommentar. Das Start-Label (`claude-fix`/`claude-feature`) bleibt, damit der Typ erkennbar ist. Zuerst versucht der Unblocker-Agent den Abbruch aufzulösen; du kannst jederzeit mit einem Kommentar antworten, dann wird die Stufe fortgesetzt und das Label entfernt. |
| `needs-human` | Issue | Der Unblocker kann nicht selbst entscheiden (z.B. Workflow-Änderungen, Secrets, Berechtigungen, Releases) oder hat 2 Versuche ausgeschöpft. Ein Mensch entscheidet; ein Kommentar von dir setzt den Lauf fort. Sub-Issues mit diesem Label werden nicht automatisch gestartet. |
| `claude-split` | Issue | Der Unblocker hat den Auftrag in kleine Sub-Issues aufgeteilt; das Original ist geschlossen und verweist auf sie. |
| `claude-approved` | PR | Der Reviewer-Agent hat den PR freigegeben. Gemergt wird nie automatisch. |

## Standardlabels (ohne Workflow-Wirkung)

| Label | Bedeutung |
|---|---|
| `bug` | Etwas funktioniert nicht |
| `enhancement` | Neue Funktion oder Wunsch |
| `documentation` | Dokumentation |
| `question` | Rückfrage |
| `duplicate` | Bereits vorhanden |
| `invalid` | Nicht zutreffend |
| `wontfix` | Wird nicht bearbeitet |
| `good first issue` | Für Einsteiger |
| `help wanted` | Hilfe erwünscht |

## Unblocker (Abbruch automatisch auflösen)

Bricht eine Stufe ab, startet automatisch [claude-unblock.yml](workflows/claude-unblock.yml). Der Agent `wp-unblocker` liest den Abbruchkommentar und entscheidet:

| Aktion | Wann | Ergebnis |
|---|---|---|
| `resume` | Rückfrage/Mehrdeutigkeit, die er beantworten kann | Er trifft die Entscheidung; abgebrochene Stufe und alle folgenden laufen mit der Entscheidung als Hinweis weiter |
| `split` | Auftrag zu gross oder gemischt | Kleine Sub-Issues (max. 6, Tiefe max. 2), die Claude bearbeiten darf, werden mit `claude-fix`/`claude-feature` gestartet; Freigabe-Pflichtiges bekommt `needs-human` |
| `escalate` | Betrifft `.github/`, Secrets, Berechtigungen, Löschen, Releases, inkompatible Schnittstellen, oder Unsicherheit | Label `needs-human`, Kommentar mit Optionen und Empfehlung |

Maximal 2 Versuche je Issue, danach `needs-human`. Manuell starten: Actions → "Claude Unblocker" → Run workflow.

## Steuerung ohne Label: Kommentare

Kommentare von Menschen mit Schreibrechten ([claude-comment.yml](workflows/claude-comment.yml)):

| Situation | Wirkung eines normalen Kommentars |
|---|---|
| Issue, Stufe abgebrochen | Abgebrochene Stufe und alle folgenden laufen mit dem Kommentar als Hinweis neu |
| Offener Claude-PR (im Issue oder PR kommentiert) | developer behebt, danach reviewer und writer |
| Lauf läuft noch / kein Claude-Issue | Keine Aktion; spätere Stufen lesen den Kommentar |

Befehle (optional): `/claude all`, `/claude from <stufe>`, `/claude <stufe>` mit den Stufen `debugger`, `architect`, `developer`, `reviewer`, `writer`; im PR zusätzlich `/claude reviewer`.

Ein menschliches "Request changes"-Review am Claude-PR startet den developer ebenfalls ([claude-pr-review.yml](workflows/claude-pr-review.yml)).

## Typische Wege

- **Bug beheben:** Issue mit `claude-fix` → PR → Freigabe (`claude-approved`) → du mergst.
- **Feature umsetzen:** Issue mit `claude-feature` → PR → Freigabe → du mergst.
- **Claude fragt nach:** Label `claude-aborted` → Kommentar mit der Antwort schreiben → Lauf geht weiter.
- **Sonar-Findings im PR:** Label `sonarqube` am PR setzen.
- **Sonar-Findings im Main-Branch:** Workflow "Claude SonarQube Fixer" manuell starten.
