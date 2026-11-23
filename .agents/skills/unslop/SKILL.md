---
name: unslop
description: Enforces the core unslop rules for SIDATEX (Zero Boilerplate, PSR-12 Standard, Clean Query Optimization) as defined in unslop-rule.md.
---

# Unslop Core Rules Skill

This skill enforces code cleanliness, simplicity, and maintainability in the SIDATEX repository.

## Directive
Always read and strictly follow the project rules defined in:
[.agents/skills/unslop/unslop-rule.md](file:///d:/laragon/www/sidatex/.agents/skills/unslop/unslop-rule.md)

## Core Principles
1. **Zero Boilerplate:** Write lean, readable code without redundant wrappers, over-engineered layers, or speculative abstractions.
2. **PSR-12 Standard:** Maintain strict PSR-12 formatting and clean code style across all PHP files.
3. **Clean Query Optimization:** Ensure queries are performant, prevent N+1 issues with eager loading, avoid float conversions on monetary/weight values, and target data mart summaries appropriately.
