---
name: unslop-maintainability
description: Prevents AI-generated code bloat, boilerplate, unnecessary abstractions, vague naming, dead code, and unexplained logic in SIDATEX.
---

# Unslop and Maintainability Skill

First inspect `.agents/skills/unslop/unslop-rule.md` if present and follow its exact project rules. Never overwrite or replace that file.

General standards:
- Zero Boilerplate.
- PSR-12.
- Clean Query Optimization.
- Use meaningful names and straightforward control flow.
- Do not add wrappers that only forward calls without a clear boundary.
- Do not create generic frameworks for one use case.
- Remove dead code only when in scope and proven unused.
- Do not add comments that merely restate code; explain non-obvious business logic.
- Do not make code look artificially verbose or overly polished at the expense of clarity.
- Keep changes minimal and explain why each new abstraction is needed.
