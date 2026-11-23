# SIDATEX Agent & Skills Pack

This pack adds project-scoped Antigravity custom agents and skills.

## Contents
- `AGENTS.md`: persistent project rules.
- `.agents/agents/`: specialist custom agents.
- `.agents/skills/`: focused reusable skills.

## Install
1. Extract this ZIP into the SIDATEX repository root:
   `D:\laragon\www\sidatex`
2. Merge the `.agents` folder with the existing one. Do not delete existing files.
3. If `AGENTS.md` already exists, merge the rules manually rather than overwriting it.
4. Preserve the existing `.agents/skills/unslop/unslop-rule.md`.
5. Restart Antigravity or reload customizations if the new agents/skills do not appear.

Antigravity workspace skills use `.agents/skills/<skill-name>/SKILL.md`. Custom subagents use `.agents/agents/<name>.md`.

## Suggested team
- `sidatex-orchestrator`: lead coordinator.
- `laravel-implementer`: writes scoped backend code.
- `database-guardian`: checks schema and database safety.
- `security-auditor`: checks authorization and security.
- `test-engineer`: builds and runs isolated tests.
- `frontend-reviewer`: checks dashboard and responsive UI.
- `statistics-auditor`: checks BPS methodology and reconciliation.
- `independent-reviewer`: skeptical final review without editing.

## Suggested workflow
1. Ask the orchestrator to inspect the task and produce an implementation plan.
2. Have the implementer make the smallest scoped change.
3. Run independent reviews (database/security/statistics/frontend as relevant).
4. Have the test engineer verify behavior in `sidatex_testing`.
5. Have the independent reviewer inspect the diff and evidence without editing.
6. Orchestrator issues a PASS / FAIL / NOT VERIFIED report.

## Example prompt
"Use sidatex-orchestrator. First inspect AGENTS.md and relevant skills. Do not edit yet. Analyze the requested change, identify affected files and risks, then delegate implementation and independent review. Protect operational database sidatex; verify runtime PDO is sidatex_testing before any write. Do not claim success without test and review evidence."

## Important
This pack does not install plugins, grant tools, or guarantee that every subagent can run concurrently. Antigravity discovers workspace custom agents and skills from the documented folders; available tool access depends on the active Antigravity environment and configuration.
