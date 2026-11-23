---
name: statistics-methodology
description: Validates SIDATEX export-import statistics, Distribusi and Neraca scopes, periods, growth rates, trade balance, units, and HS classification.
---

# Statistics Methodology Skill

Never invent or silently alter BPS statistical definitions.

Required checks:
- State methodology: Distribusi or Neraca.
- State transaction: Ekspor, Impor, or Neraca Perdagangan.
- State exact period and whether value is monthly or cumulative.
- Verify source-field mapping against the relevant DBF family.
- Never combine Distribusi and Neraca totals.
- Trade Balance = export minus import only for matching methodology and period.
- MoM compares current month with previous month.
- QoQ compares current quarter with previous quarter.
- YoY compares the same period with the corresponding prior year.
- C-to-C compares cumulative current-year period with the same cumulative period in the prior year.
- If denominator or comparison period is zero/missing, handle according to approved methodology and label unavailable where appropriate.
- Keep units explicit (USD, USD million, net weight, thousand tons).
- Validate Migas/Non-Migas using the approved HS master and documented precedence.
- Ensure every chart/table/KPI/map/export uses the same active filters.

Every number in a reconciliation report must include SQL/source, filters, period, unit, and formula.
