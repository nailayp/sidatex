---
name: laravel-backend
description: Guides maintainable Laravel backend implementation in SIDATEX, including routes, controllers, services, validation, Eloquent, transactions, and error handling.
---

# Laravel Backend Skill

- Inspect neighboring implementation before designing a new pattern.
- Keep controllers thin; put domain processing in existing appropriate services.
- Validate request input at boundaries and authorize every sensitive action server-side.
- Use dependency injection and Laravel conventions already used by this repository.
- Use DB transactions for multi-step persistence and make rollback behavior explicit.
- Avoid mass assignment risks; define fillable/guarded intentionally.
- Avoid N+1 queries and repeated aggregation queries; eager load or aggregate deliberately.
- Do not swallow exceptions. Log context safely and return appropriate user-facing errors.
- Do not introduce speculative repositories, factories, interfaces, or abstractions.
- Preserve monetary precision and existing schema semantics.
- Add focused feature tests for success, invalid input, unauthorized access, duplicates, and failure/rollback.
- Follow PSR-12 and existing project formatting.
