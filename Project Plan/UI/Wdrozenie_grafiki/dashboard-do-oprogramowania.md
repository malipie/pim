# Dashboard — backlog do oprogramowania

> Baza pod kolejne GitHub tickety. Każda pozycja zaznaczona w kodzie komentarzem `MOCK:` lub w dokstrings komponentu jako "Backend: …". Stan na 2026-05-01 (ticket #356 zamyka same makiety; backend dorabiamy później).
>
> **Reguła**: pojedyncza pozycja → osobny issue na GitHubie z labelami `frontend` (+ ewentualnie `backend`), `must-have`/`optional`, `UI`, `epik-UI-03` (lub przeniesione do nowego epika gdy tematyka backendu się rozjedzie).

## Frontend-only (mock UI, nie wymaga nowego endpointu)

- [ ] Dashboard: range picker (7d / 30d / 90d) dla `ActivityChart` — dziś hardkodowane 30d. Plik: `apps/admin/src/features/dashboard/components/ActivityChart.tsx`. Kandydat: prosty `<select>` w prawym górnym rogu karty, zapis stanu w URL search param `range`. Estymacja: S.
- [ ] Konfigurowalne KPI cards — operator wybiera które 4 kafle widoczne (z 6-8 dostępnych). Plik: `apps/admin/src/features/dashboard/components/KpiCards.tsx`. Kandydat: prefs w `localStorage` na MVP, później `workspace_settings`. Estymacja: M.
- [ ] Hover-tooltips na chartach + drill-down click na wpis listy `TopEditedProducts`. Estymacja: S.
- [ ] Skeleton/loader components dla każdego bloku (po podłączeniu prawdziwych zapytań TanStack Query). Estymacja: S.

## Frontend + nowy endpoint backendowy

- [ ] **KPI counts** — `GET /api/dashboard/kpis` zwraca `{ products: int, attributes: int, families: int, categories: int, deltas: { …: int } }` (delta vs. poprzedni okres). FE: `KpiCards` zamiast `mock-data.ts`. BE: serwis zliczający per tenant (Doctrine count + cache 5 min). Estymacja: M (FE 2h, BE 4-5h).
- [ ] **Activity chart** — `GET /api/dashboard/activity?range=30d` zwraca `[{ day, added, modified }]`. Source: agregacja po `audit_log.action` per dzień. Estymacja: M (FE 2h, BE 5-6h).
- [ ] **Top edited products** — `GET /api/dashboard/top-edited?limit=10` zwraca produkty + liczbę edycji (z audit_log per `product_id`). Estymacja: S (FE 1h, BE 3-4h).
- [ ] **Syncs status panel** — `GET /api/integrations/status` zwraca per-integration: `lastSync`, `status`, `pushed`, `failed`. Source: tabela `sync_jobs` + ostatni job per integration. Estymacja: M (FE 2h, BE 5-6h, depends on epik 0.8/0.9 BaseLinker+Shopify).
- [ ] **Force sync** button — `POST /api/integrations/{id}/sync` triggeruje sync job (Symfony Messenger). Wymaga authorization (admin only). Estymacja: M.
- [ ] **Completeness metrics overall + per-channel** — `GET /api/dashboard/completeness` zwraca `[{ channel, percent }]`. Algorytm: % produktów które przeszły walidację channelową. Estymacja: L (FE 2h, BE 8-10h — wymaga channel mapping + validation engine).
- [ ] **Recent agent activity** — `GET /api/audit-log?actor=agent&limit=6`. UWAGA: `provenance=agent` dopiero w Fazie 2 (CLAUDE.md PIM). W MVP zwraca pustą listę lub miks `manual/import/integration`. FE pokazuje banner "Agent: Faza 2". Estymacja: S (FE 1h, BE 3h, gating: Faza 2).
- [ ] **Alert center** — `GET /api/alerts?limit=5`. Encja `Alert` nie istnieje — wymaga nowej domeny: `Alert { id, severity, title, source, cta_url, created_at, acknowledged_at }`. Source events: failed integration syncs, completeness drops, schema_rev changes. Estymacja: L (FE 2h, BE 12-15h — nowa domena + event subscribers).
- [ ] **Channel distribution** — `GET /api/dashboard/channel-distribution` zwraca histogram: ile produktów w 1/2/3/4/5 kanałach. Estymacja: S (FE 1h, BE 3-4h).

## Wymaga decyzji architektonicznej (przed wdrożeniem)

- [ ] **Hero "Zapytaj agenta" CTA** — wymaga decyzji o LLM provider integration (Anthropic SDK PHP per CLAUDE.md, ale agent layer = Faza 2). Pytania: streaming SSE w MVP czy dopiero w Fazie 2? Command palette ⌘K — czy globalne (sidebar też) czy tylko dashboard? Czy wykorzystujemy istniejący endpoint czy budujemy `/api/agent/chat` od zera? Zob. epik 0.7 w `Project Plan/02-plan-projektu-pim.md`.
- [ ] **Schema completeness algorytm** — jak liczymy `completeness` per kanał? Opcje: (a) % wymaganych atrybutów wypełnionych (z mappingu integration → required_attributes), (b) % atrybutów `required` per ObjectType wypełnionych (channel-agnostic), (c) hybrid (channel-specific override). Wpływa na BE design `CompletenessCalculator` + tabelę `channel_attribute_requirements`.
- [ ] **`Alert` jako encja first-class vs. on-the-fly aggregator** — czy alerty trzymamy w tabeli z TTL/acknowledge, czy zawsze re-computujemy z source events? TTL/ack daje historię + UX "oznacz jako przeczytane"; aggregator unika storage drift'u ale nie pamięta że operator widział alert.

## Zależności od innych epików

- Epik 0.8 (BaseLinker) i 0.9 (Shopify) — `SyncsStatusPanel` zależy od pełnej integracji (po Fazie 1).
- Epik 0.7 (Agent layer) — `HeroAgentPanel` CTA + `RecentAgentActivity` z `provenance=agent` (po Fazie 2).
- Epik 0.11 (Hardening + analityka) — `Alert` encja, audit_log endpoint, completeness calculator (kandydat do MVP-Final albo Fazy 1).
