---
name: code-review
description: Performs skeptical read-only code review of SIDATEX changes for correctness, maintainability, security, performance, and regression risks.
---

# Code Review Skill

Review actual diffs and call sites. Do not accept an implementation summary as evidence.

Checklist:
1. Does the code meet the stated requirement?
2. Does it preserve existing business rules and data semantics?
3. Are authorization and validation enforced server-side?
4. Are null, zero, empty, duplicate, and failure cases handled?
5. Are monetary values and weights precise?
6. Are transactions and rollback correct?
7. Are queries efficient and indexes appropriate?
8. Is code readable and consistent with project conventions?
9. Are tests meaningful and not weakened?
10. Are claims supported by reproducible evidence?

Classify findings by severity and include file/line, impact, and recommended fix. Default to read-only.
