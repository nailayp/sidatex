---
name: testing-quality
description: Creates trustworthy PHPUnit tests and regression checks for SIDATEX without weakening assertions or contaminating production data.
---

# Testing Quality Skill

Testing principles:
- Test requirements and observable behavior, not private implementation details unless necessary.
- Verify runtime database identity before write-capable tests.
- Use `sidatex_testing` only.
- Keep fixtures deterministic and isolated; avoid dependence on batch IDs or data left by previous runs.
- Cover authorization, invalid inputs, duplicate uploads, rollback, idempotency, empty datasets, and period boundaries.
- Assert both positive and negative access.
- Do not change expected results merely to silence a failure.
- Do not remove tests or lower assertions without a documented reason.
- Run focused tests first, then relevant regression tests, then full suite.
- Report exact command, exit status, test count, assertion count, failures, errors, skips, and DB identity.
