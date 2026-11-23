---
name: dbf-ingestion
description: Handles SIDATEX DBF source inspection, parsing, validation, staging, deduplication, transaction ingestion, and raw-file traceability.
---

# DBF Ingestion Skill

Before parsing, inspect DBF field names, types, widths, decimals, encoding, record count, and deleted-record behavior. Never assume all four DBF families share one schema.

Processing rules:
- Keep raw source files and source provenance traceable.
- Preserve numeric source text where needed to avoid precision loss.
- Preserve blank/null separately from numeric zero.
- Validate required fields, period, transaction type, source family, and master mappings.
- Unknown HS must be reported and traceable; do not silently discard or invent classification.
- Prevent duplicate files/periods using the project's approved idempotency rules.
- Use chunking only where it preserves atomicity and correct rollback semantics.
- Persist batch metadata and activity logs consistently.
- Never write to the archived `transactions` table for new ingestion.
- Route records to the correct Distribusi or Neraca transaction table.
- Verify runtime DB is `sidatex_testing` before any test ingestion.

For reconciliation, compare source record count, accepted count, rejected count, and stored count. Explain every difference.
