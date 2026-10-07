# Release

## Vorbereitung

1. Update der Version im `swiss-floorball-api.php` File
2. Update der Version im `README.md` File

## Release

1. Tag erstellen
2. Workflow starten

## Automatisch (Claude)

1. Issue erstellen, Label `claude-release` setzen (oder Workflow "Claude Release" manuell starten)
2. Ablauf: plan → prepare (Release-PR) → CI → verify (Freigabe) → publish (Merge, Tag, `release.yml`)
3. Optional: unter Settings → Environments → `release` Required reviewers setzen (menschliche Freigabe vor publish)
