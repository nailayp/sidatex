---
name: dashboard-frontend
description: Builds and reviews SIDATEX Single Dashboard Blade UI with four tabs, methodology switchers, Chart.js, Leaflet, pivot tables, filters, and responsive layouts.
---

# Single Dashboard Frontend Skill

Dashboard structure:
1. Overview Global & Tren Perdagangan.
2. Tabulasi Time Series Komoditas.
3. Detail Analisis & Pemetaan Wilayah.
4. Neraca Perdagangan (Trade Balance).

Requirements:
- Keep one Dashboard sidebar item and one page with internal tabs.
- Keep Distribusi/Neraca methodology switcher visible and unambiguous.
- Never visually imply that Distribusi and Neraca totals are combined.
- Synchronize active methodology, period, transaction type, and filters across components.
- Use real backend data; no dummy or hardcoded actual-looking values.
- Provide loading, empty, unavailable, and error states.
- Chart.js: destroy/reuse instances safely and resize after tab visibility changes.
- Leaflet: invalidate map size after hidden tab becomes visible; validate coordinates and region identity.
- Tables: readable on desktop/mobile, searchable/sortable where required, and export current filtered data.
- Test desktop, tablet, and mobile; capture screenshots only after confirming the correct database and dataset.
