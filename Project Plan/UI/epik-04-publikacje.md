# Epik 04 — Publikacje

## Status: 🔵 placeholder

## 1. Cel epiku

Zarządzanie syndykacją danych do kanałów zewnętrznych — *„co się dzieje z moim katalogiem po edycji"*. Sync jobs panel, integracje (BaseLinker / Shopify / w przyszłości Magento / IdoSell / Comarch), API Configurator (drugi USP — klient sam tworzy endpointy/feedy), webhooks management.

## 2. Persony

- **Kasia, 32** — trigger publikacji (manual/bulk), monitoring statusu sync.
- **Piotr, 38** (IT/Integration) — primary user dla API Configurator, debug failed syncs, webhooks management.
- **Tomasz** — read-only check status (z Dashboard'u również).

## 3. Kluczowe widoki

### 3.1 Sync Jobs panel
- Lista wszystkich sync'ów (running / success / failed / partial) z filtrami (status, integration, date range).
- Per-job detail: stats (X new, Y updated, Z errors), progress bar dla running, expandable error list z retry per item.
- **Live updates przez Mercure SSE** — gdy sync się kończy, status w UI aktualizuje się bez refresh.

### 3.2 Integracje (Connectors panel)
- Lista zainstalowanych connectorów (BaseLinker, Shopify, własny custom).
- Per-connector: status (zielone/żółte/czerwone), last sync timestamp, configuration link.
- *„Add Integration"* wizard — Piotr konfiguruje credentials + mapping atrybutów + test sync 3 produktów.
- **Tier gating** — Starter ma 2 connectory, Pro 5, Enterprise wszystkie (z PRD § 12.2).

### 3.3 API Configurator (drugi USP — *most important view of this epic*)
- **Tabs:**
  - **Feeds** — klient tworzy XML feedy (Google Shopping template predefiniowany + custom).
  - **Exports** — CSV exporty z mapping wizard'em.
  - **Imports** — CSV importer z mapping (wzajemne z eksportami).
  - **API Endpoints** (Faza 1) — custom REST endpoint generator.
  - **Webhooks** (Faza 1) — outbound webhooks per event.
- **Mapping wizard:**
  - Wybierz ObjectType source (np. `product`).
  - Wybierz fields → mapuj na docelowe nazwy (np. PIM `description.pl` → XML `<g:description>`).
  - Filter (które obiekty trafiają do feedu — wszystkie / kategoria X / smart filter).
  - Schedule (cron — co 1h, co 6h, daily 2:00 UTC, manual only).
  - **Preview output** — pokazuje rzeczywisty XML/CSV/JSON dla 5 sample produktów.
  - Save → generuje URL feedu z autoryzacją (API key).

### 3.4 Webhooks (Faza 1)
- Lista subscribed webhooks (event → URL → status).
- Retry policy konfigurowalny per webhook.
- HMAC signing setup.

## 4. User stories (z `Project Plan/03-funkcjonalnosci-mvp.md`)

- US-008: Trigger publikacji na kanały + status.
- US-014: Integration panel (lista wszystkich + status + live error log).
- US-015: Add integration wizard (credentials, mapowania, test sync).
- US-016: Per-item retry (failed items → retry one-click).
- US-017: API Configurator (generowanie kluczy API + scope per profile).
- **US-EP04-NEW-001:** Klient w API Configurator tworzy feed Google Shopping w 5 minut bez developera.
- **US-EP04-NEW-002:** Klient dodaje custom feed XML dla partnera B2B (mapping pól + filter + schedule).

## 5. Business rules / edge cases

- _[TODO: rate limiting publish actions — bulk publish 5000 SKU vs API limits Shopify (Exponential Backoff per ADR architi)]_
- _[TODO: failed sync notifications — alert email/Slack/inapp dla Kasi i Piotra]_
- _[TODO: webhook delivery failure handling]_
- _[TODO: feed regeneration scheduling — co gdy klient zmienia mapping w trakcie generowania]_

## 6. Dependency na backend

- Architektura sekcja 3.10 + 7.x — Symfony Messenger handlers, Exponential Backoff dla Shopify.
- ADR-008 (API Platform 4) — wszystkie kanały korzystają z tych samych endpointów co admin.
- Epik 0.10 z `Project Plan/02-plan-projektu-pim.md` — API Configurator pełen scope MVP (XML feeds + CSV + UI form configurator + Google Shopping predef).
- Mercure SSE dla live updates statusu sync.

## 7. Komponenty Refine + shadcn

- Refine `useList` dla sync jobs lista.
- shadcn `Tabs`, `DataTable`, `Sheet`, `Dialog`, `Form`, `Select`, `Card`.
- Custom `MappingWizard` component (drag-drop fields → targets).
- Custom `XMLPreview` / `CSVPreview` — show output sample.
- Custom `LiveStatusBadge` — Mercure-connected indicator.

## 8. Open questions

- [ ] UX dla *„dodaj custom integration"* — wizard step-by-step czy single form?
- [ ] Mapping wizard — drag-drop UI czy dropdown (które bardziej intuicyjne dla Piotra)?
- [ ] Czy trigger ręczny *„Publish to Shopify"* idzie z list view (per produkt) czy z detail view?
- [ ] Jak prezentujemy *„rate limit reached"* (Shopify 429) — ostrzeżenie + auto-retry vs manual?
- [ ] Custom REST endpoint UX — *„zaprojektuj endpoint"* wizard czy code editor (gibsze, ale wymaga znajomości API)?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — drugi USP (API Configurator) musi być wow demo'em w pitch'u Enterprise.*
