# Runner (Cloud und self-hosted)

Alle Glue-Jobs (Auslöser, Gates, Dispatch, Sonar-Abfrage) laufen auf `ubuntu-24.04` (GitHub-Cloud). Die rechenintensiven Stufen **developer** und **reviewer** (inkl. `developer_fix`, `reviewer_2` und `claude-pr.yml`) laufen auf dem Runner aus der Repository-Variable **`RUNNER_HEAVY`**. Ohne die Variable bleibt alles in der Cloud.

| Variable (Settings → Secrets and variables → Actions → Variables) | Beispiel | Wirkung |
|---|---|---|
| `RUNNER_HEAVY` | `["self-hosted","linux","x64"]` | developer/reviewer laufen self-hosted |
| `RUNNER_HEAVY` | `"ubuntu-24.04"` oder leer | Cloud (Standard) |

Der Wert ist JSON (Anführungszeichen beim String nicht vergessen).

## Fallback auf die Cloud

Vor den Stufen wählt [claude-pick-runner.yml](workflows/claude-pick-runner.yml) den Runner:

| Situation | Runner |
|---|---|
| `RUNNER_HEAVY` nicht gesetzt oder ein Cloud-Label | Cloud (`ubuntu-24.04`), wie bisher |
| `RUNNER_HEAVY` = self-hosted und ein passender Runner ist **online** | self-hosted |
| `RUNNER_HEAVY` = self-hosted, aber **kein** passender Runner online | Cloud (Fallback, Warnung im Log) |
| `RUNNER_HEAVY` ungültig (kein JSON) | Cloud (Warnung im Log) |

Die Online-Prüfung braucht das optionale Secret **`RUNNER_CHECK_TOKEN`**: ein Fine-grained Personal Access Token für dieses Repo mit dem Recht *Administration: Read* (bei Organisations-Runnern zusätzlich *Self-hosted runners: Read* an der Organisation). Der normale `GITHUB_TOKEN` darf Runner nicht auflisten. **Ohne dieses Secret** wird `RUNNER_HEAVY` ungeprüft verwendet: ist dann kein Runner online, warten die Jobs, bis einer frei wird. Dann `RUNNER_HEAVY` leeren, um zurück in die Cloud zu wechseln.

## Voraussetzungen für den self-hosted Runner

- Installiert: `git`, `gh`, `jq`, `curl`, `php` (>= 8.1), `composer`, `python3` (3.12 über `actions/setup-python` möglich), `bash`.
- Ausgehend erreichbar: GitHub, Anthropic API, 1Password, Packagist, PyPI.
- Als Dienst mit **`--ephemeral`** (frischer Runner je Job) betreiben; sonst bleiben Workspace, Caches und Tokens zwischen Läufen liegen.

## Sicherheit

Die Stufen führen Claude mit Shell-Zugriff (`git`, `gh`, `php`, `phpcs`, `graphify`) auf Basis von Issue-/PR-Text aus. Auf einem self-hosted Runner trifft das deine eigene Infrastruktur.
- Self-hosted Runner **nie** für ein öffentliches Repo mit offenen Issues/PRs von Fremden betreiben, ohne die Auslöser einzuschränken (hier: nur Menschen mit Schreibrechte und Branches dieses Repos, Forks sind ausgeschlossen).
- Den Runner in einer isolierten VM/einem Container ohne Zugriff auf private Netze oder weitere Zugangsdaten betreiben.
- Runner-Gruppen nutzen, damit nur dieses Repo den Runner verwenden darf.
