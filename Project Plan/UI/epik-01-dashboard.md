# Epik 01 — Dashboard

## Status: 🔵 placeholder

## 1. Cel epiku

Główny ekran startowy admina — *„Tomasz patrzy na liczby"*. Daje 5-7 KPI w 5 sekund, mobile-friendly (Tomasz w terenie sprawdza z telefonu). Read-only w MVP, drill-down do szczegółów w Fazie 1.

## 2. Persony

- **Tomasz, 48** (Owner/CEO) — primary user, codzienny check.
- **Marcin (founder dogfooding)** — używa do walidacji własnego sklepu.
- **Kasia, 32** (Catalog Manager) — zaczyna dzień od Dashboard, widzi co wymaga uwagi.

## 3. Kluczowe widoki

**Top KPI band (4-6 widget'ów):**
- SKU active / total.
- Completeness average (% z czerwonym/żółtym/zielonym indicator'em).
- Sync status per integration (kolorowy dot per kanał, last sync timestamp).
- Top 10 *„most edited products"* (sygnał aktywności).
- Pending agent changes (Faza 2 — inbox count).
- Status backupów (last successful, RPO indicator).

**Wykresy (Faza 1+):**
- Produkty dodane/zmodyfikowane w ostatnich 30 dniach (Recharts).
- Completeness per family / per kategoria.
- Sync velocity per kanał.

**Mobile widok:**
- Read-only.
- Pojedyncza kolumna (każdy KPI jako card).
- Pull-to-refresh.

## 4. User stories

- **US-EP01-001:** Tomasz widzi dashboard od razu po login z 5 KPI w 5 sekund.
- **US-EP01-002:** Tomasz na wakacjach z telefonu sprawdza, czy synch z BaseLinker poszedł w nocy.
- **US-EP01-003:** Kasia rano klika *„Top 10 most edited products"* żeby zobaczyć co Magda robiła wczoraj.
- _[TODO: doprecyzować po pierwszym pilotażu, które KPI faktycznie używane]_

## 5. Business rules / edge cases

- _[TODO: empty state — co widzimy gdy nowy klient z 0 produktów]_
- _[TODO: error state — co gdy API nie odpowiada (cache last successful values?)]_
- _[TODO: drill-down — klik w KPI prowadzi gdzie?]_

## 6. Dependency na backend

- Endpoint `/api/dashboard/stats` (do dodania) — pre-aggregated KPI per tenant.
- Mercure SSE channel `/.well-known/mercure?topic=dashboard.{tenant_id}` — real-time refresh.
- Doctrine query optimization (cached aggregates) — KPI nie może liczyć on-the-fly z 50k SKU.

## 7. Komponenty Refine + shadcn

- `shadcn/ui Card` — bazowy widget KPI.
- `recharts` (już w stacku) — Line/Bar/Pie charts.
- `shadcn/ui Tabs` — przełączanie między widokami (np. *„Today / 7 days / 30 days"*).
- Custom `KPIWidget` component (icon + value + delta + trend arrow).

## 8. Open questions

- [ ] Lista finalnych KPI w MVP — które 5-7 widget'ów?
- [ ] Personalizacja dashboard'u (klient sam wybiera widget'y) — MVP czy Faza 2?
- [ ] Drill-down navigation — gdzie klika prowadzi?
- [ ] Dark mode na mobile — czy auto-detect od system theme?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — do iteracji po pierwszym pilotażu, gdy zobaczymy, które KPI faktycznie patrzy klient.*
