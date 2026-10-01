## Stufe verify (Freigabe-Entscheid, Agent: wp-quality-reviewer) – Issue #__ISSUE__
Du änderst keinen Code. Dein Verdikt entscheidet, ob die Release-Pipeline automatisch weiterläuft.
Der CI-Lauf (Lint, Konsistenz, Plugin Check) auf dem Release-Branch war bereits grün, sonst wärst du nicht gestartet.
1. Lies Version aus dem neuesten prepare-Kommentar (`status:ok`, sonst Abbruchregel). Checke `release/vX.Y.Z` aus
   (`git fetch origin && git checkout -B release/vX.Y.Z origin/release/vX.Y.Z`).
2. Prüfe selbst:
   - `git diff --stat origin/HEAD...HEAD`: genau diese Dateien dürfen sich ändern: Plugin-Hauptdatei und README.md.
     Jede andere Datei -> `CHANGES_REQUESTED`.
   - Im Diff der Hauptdatei nur Version-Header und Konstante; im README nur Stable tag und der neue Changelog-Eintrag.
   - `SFA_REQUIRE_NEW_VERSION=1 php scripts/check_release.php X.Y.Z` muss OK sein.
   - Changelog-Eintrag deckt sich mit den tatsächlich gemergten Änderungen seit dem letzten Tag (`git log <tag>..origin/HEAD`).
   - wp-quality-reviewer prüft den Code seit dem letzten Tag (`git diff <tag>..origin/HEAD -- '*.php'`) stichprobenartig
     auf Security (Escaping, Nonces, Capabilities) und WordPress.org-Konformität, nur Findings mit Datei:Zeile.
3. Verdikt: `APPROVED` -> Kommentar `<!-- sfa-stage:verify status:ok version:X.Y.Z -->` mit Begründung (= Freigabe).
   `CHANGES_REQUESTED` -> `<!-- sfa-stage:verify status:changes -->` mit Findings; es wird nicht released.
   `UNSURE` -> Abbruchregel. Im Zweifel NICHT freigeben.
