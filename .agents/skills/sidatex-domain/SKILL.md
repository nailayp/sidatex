---
name: sidatex-domain
description: Provides SIDATEX project context, roles, data lifecycle, dashboard structure, and core business constraints. Use when changing any SIDATEX feature or interpreting project requirements.
---

# SIDATEX Domain Skill

Read the repository's `AGENTS.md` first. Then inspect current implementation and approved project documents; this skill is a guardrail, not a substitute for current source evidence.

Core concepts:
- SIDATEX means Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau.
- One Dashboard page contains four internal analysis tabs.
- Methodology is selected as Distribusi or Neraca; never merge their statistical universes.
- Trade Balance compares export and import only inside the same methodology and period.
- Roles: Super Admin, Pegawai, Viewer Eksekutif/Berkepentingan.
- Data lifecycle includes DRAFT, PRIVATE, and RILIS; Trash is a separate recovery/deletion workflow.
- Pegawai sees RILIS only. PRIVATE visibility follows approved Super Admin and Viewer Eksekutif policy.
- AI chat/data transfer to third-party AI is outside the current project scope.

Before changing a business rule, find its source in current code, tests, or approved documentation. If sources conflict, report the conflict and ask for a decision.
