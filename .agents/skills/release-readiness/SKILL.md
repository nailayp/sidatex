---
name: release-readiness
description: Runs a final SIDATEX acceptance checklist covering tests, database safety, RBAC, statistical reconciliation, browser behavior, backups, and evidence-based reporting.
---

# Release Readiness Skill

Do not deploy or declare production-ready based on a green test suite alone.

Acceptance checklist:
- Requirements and scope approved.
- Independent code review completed.
- Full relevant tests pass with exact output.
- Runtime database targeting verified.
- Operational DB untouched unless an explicitly approved deployment plan says otherwise.
- RBAC and direct URL tests pass.
- Statistical KPIs reconcile to source data with methodology and period shown.
- Dashboard desktop/mobile verified.
- Upload rollback, duplicate prevention, and logs verified.
- No dummy data or misleading zero defaults.
- Known limitations documented.
- Backup and rollback plan documented for any approved deployment.

Mark every item PASS, FAIL, or NOT VERIFIED. Any critical FAIL blocks release; any important NOT VERIFIED blocks a confident readiness claim.
