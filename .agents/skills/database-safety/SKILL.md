---
name: database-safety
description: Enforces safe Laravel database targeting, migration review, test isolation, backups, and protection of the SIDATEX operational database.
---

# Database Safety Skill

Highest priority: protect operational `sidatex`.

Before any test, migration, upload, seeder, browser action, or script that may write:
1. Inspect Laravel runtime connection (`DB::connection()->getDatabaseName()` and PDO driver).
2. Confirm automated testing targets SQLite in-memory (`:memory:`) and never operational database `sidatex`.
3. If operational `sidatex` is targeted for a test or write without explicit authorization, stop immediately.
4. Never rely on `.env` alone; cached config and long-running PHP processes may differ.
5. Do not run destructive SQL or broad cleanup commands.
6. Do not use production data mutation as a test setup shortcut.

For migrations:
- Inspect `up()` and `down()` before running.
- Identify table/column/index effects and data-loss risks.
- Run only against testing database after explicit verification.
- Report exact migration command and database identity.

For audits:
- Prefer read-only SELECT queries.
- Record before/after counts and timestamps.
- Never claim production unchanged without comparable evidence.
