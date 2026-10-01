## Stufe plan (Release-Plan) – Issue #__ISSUE__ ist das Release-Tracking-Issue
Du änderst keinen Code und keine Branches.
1. Ermittle den letzten Tag: `git tag --list 'v*' --sort=-v:refname | head -1`, und den Default-Branch-Stand.
   Sammle alle Änderungen seither: `git log <tag>..origin/HEAD --oneline` und gemergte PRs
   (`gh pr list --state merged --base <default> --json number,title,labels,mergedAt`, nur seit dem Tag).
2. Gibt es keine Änderungen seit dem letzten Tag -> Abbruchregel.
3. Prüfe, dass `origin/HEAD` grün ist: `gh run list --branch <default> --workflow CI --limit 1 --json conclusion`
   muss `success` sein, sonst Abbruchregel. Offene PRs mit Label `claude-approved` sind NICHT Teil des Releases.
4. Die Version ist IMMER der nächste Tag nach dem letzten Tag (letzter Tag + Bump nach SemVer), nie der Version-Header (der kann hinterherhinken):
   - Bugfixes/Compliance/Docs -> patch; neue Shortcodes/Attribute/Features abwärtskompatibel -> minor;
     Entfernungen oder inkompatible Änderungen an Shortcodes/Optionen -> major.
   - Ein expliziter Zusatzhinweis zur Versionsart gilt nur, wenn er plausibel ist.
   - Unklar oder mehrere Versionen plausibel -> Abbruchregel.
5. Kommentar im Issue, erste Zeile exakt `<!-- sfa-stage:plan status:ok version:X.Y.Z -->`
   (X.Y.Z ohne `v`), danach: Release-Plan als Checkliste (Versionswahl samt Begründung, enthaltene PRs/Commits,
   Changelog-Entwurf auf Englisch im Stil der bisherigen Einträge, Risiken).
