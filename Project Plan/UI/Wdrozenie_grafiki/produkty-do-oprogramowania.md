# Produkty — backlog do oprogramowania

> Baza pod kolejne GitHub tickety. Każda pozycja zaznaczona w kodzie komentarzem `MOCK:` lub `TODO(handoff)`.
>
> **Stan na 2026-05-14 po marathonie UI-09 (12/12 ticketów ✅).** Aktualna implementacja list+detail jest w ~95% gotowa — pełen scope MVP feature'a cockpit operatora wg `PRD-PIM-list-advanced.md` dostarczony. Sekcja "Już zrealizowane" rozszerzona o wszystkie tickety UI-09.

## Zrealizowane w marathonie UI-09 (2026-05-14)

- [x] **VIEW-09** (#536+#537) — Smart filter presets (5 built-in + user-defined) + push-down advanced filter panel + filter chips edit popover
- [x] **VIEW-10** (#539) — 25 operatorów per typ atrybutu + URL filter DSL serializer + `?smart_preset=` BE
- [x] **VIEW-09b** (#541) — Query mode AND/OR brackets editor (recursive QueryGroupEditor)
- [x] **VIEW-11** (#542) — Cross-page selection toolbar + select-all-matching (10k cap)
- [x] **VIEW-12** (#543) — Bulk wizard 3-step + `bulk_sessions` + `bulk_logs` + `set_attribute` E2E
- [x] **VIEW-17** (#544) — 24h rollback toast + executor + `GET/POST /api/bulk-sessions/{id}[/rollback]`
- [x] **VIEW-13** (#545) — clear/append/remove/increment_numeric/multi_attribute_edit handlery + wizard 6-mode picker
- [x] **VIEW-14** (#546) — add/remove/move_category handlery + `BulkCategoryModal` + `toast.action` 5s Undo
- [x] **VIEW-15** (#547) — publish/unpublish_channels handler + `BulkPublishModal` + cascade banner (soft flag)
- [x] **VIEW-16** (#548) — delete + duplicate handlery + hard confirm typing modal
- [x] **VIEW-18** (#549) — `AttributeLockReader` + `attribute_locked` JSONB slot + endpoint + FE toggle
- [x] **VIEW-19** (#550) — Cmd+K palette + rule-based planner + 6 MVP intents (USP demo-ready)

## Follow-up tickety (deferred z marathon UI-09)

- [ ] **VIEW-15.1** — `GET /api/products/bulk-actions/cascade-preview` server-side count variants + cross-sell + sales-in-progress warning. FE: BulkPublishModal banner z prawdziwym count zamiast placeholder.
- [ ] **VIEW-17.1** — `BulkRollbackHandler` extension: dispatch per-action-type. Obecnie pokrywa `set_attribute` only. Recipe rows już w BulkLog dla `add_category/remove_category/move_category/publish_channels/delete/duplicate`. Estymacja: M (12-16h).
- [ ] **VIEW-18.1** — `AttributeLockReader` integration w pozostałych 5 attribute handlerach (clear/append/remove/increment/multi). Skip+report wizard banner "X produktów ma zablokowany atrybut Y". Estymacja: S (4-6h).
- [ ] **VIEW-19.1** — Anthropic SDK PHP integration. POST `/api/agent/cmd-k` swap planner z regex → Claude Sonnet 4.5 tool-use. Mercure SSE `cmd-k.{user_id}` streaming. BYOK + rate limits (50/h/user, 10/run, 100k tokens/run, $20/dzień/tenant) z CLAUDE.md §8.5. Estymacja: L (22-32h) — to jest epik 0.7 Faza 2.

## Frontend + nowy endpoint backendowy

## Frontend + nowy endpoint backendowy

### Bulk operations (rozszerzenie istniejącego /bulk-edit)

- [x] **Bulk attribute edit** — zrealizowane w VIEW-12 + VIEW-13 (`POST /api/products/bulk-actions/set_attribute` + clear/append/remove/increment/multi).
- [x] **Bulk category change** — zrealizowane w VIEW-14 (`POST /api/products/bulk-actions/{add|remove|move}_category`).
- [ ] **Bulk export CSV** — `GET /api/products/export?ids=…&format=csv` (streaming). Backend: streaming CSV response (FrankenPHP buffer flush — patrz lessons), wszystkie visible columns + attribute columns. Frontend: modal "Export selected" → trigger download. Estymacja: M (FE 3-4h, BE 5-6h). NIE objęte marathonem UI-09 (export-as-data ≠ edit-bulk).

### Relationships / Associations

- [ ] **AssociationController CRUD** — Entity + Repository istnieją (po Sprint 0), brak HTTP controllera. Endpointy:
  - `GET /api/products/{id}/relationships` — list per type (akcesorium / cross-sell / alternatywa).
  - `POST /api/products/{id}/relationships` — create relation.
  - `DELETE /api/products/{id}/relationships/{relationId}` — remove.
  - `PATCH /api/products/{id}/relationships/order` — reorder w obrębie typu.
- [ ] **RelationshipsTab UI** — 3 sekcje per typ z autocomplete SKU pickerem, reorder drag, delete inline. Plik FE: `apps/admin/src/features/catalog/products/components/RelationshipsTab.tsx` (stub mock zamknięty w UI-03.3).

### Media / DAM

- [ ] **Upload do MinIO/S3 przez Flysystem** — `POST /api/products/{id}/media` (multipart). Backend: ProductMediaController + storage adapter.
- [ ] **Image transformations** — thumbnail + per-channel resize (Shopify max 4096px, BaseLinker 800x800, etc.).
- [ ] **AI metadata extraction** — alt text, dominant color, OCR (Faza 2).
- [ ] **MediaTab UI** — 4-image grid + Upload button + delete + reorder. Plik FE: stub mock w UI-03.3.

### History / Audit

- [ ] **Per-product audit log endpoint** — `GET /api/products/{id}/audit-log` zwraca timeline wpisów (manual/import/integration provenance, who, when, diff). Event sourcing istnieje w `Catalog/Contracts/Event/*`, brak HTTP endpointu. Plik FE: `HistoryTab.tsx` (stub mock).

### Import

- [ ] **CSV/XLSX import dryRun + commit** — `POST /api/products/import?dryRun=true` accept multipart, parser (League\Csv lub PhpSpreadsheet), validation row-by-row, response z preview (sample rows + error count). Commit: same endpoint without dryRun, używa `BulkContext::setBulk()` + async reindex. Estymacja: L (BE 12-15h, FE 6-8h).

### Agent (Faza 2 per CLAUDE.md PIM)

- [ ] **Agent suggestions card wiring** — Anthropic SDK PHP integration. UI plik: `detail-sidebar.tsx` (3 placeholdery z mock comments).
- [ ] **"Wygeneruj opis EN"** tool dispatch — agent fetcher current product → asks model for translation → submits as `pending_change`.
- [ ] **"Uzupełnij kod HS"** tool dispatch — agent classification by product description.
- [ ] **"Zaproponuj akcesoria"** tool dispatch — agent rekomendacje na bazie ObjectType + atrybuty + relacje.

## Wymaga decyzji architektonicznej

- [ ] **Variants inheritance model** — lazy compute (per read) vs. eager denormalization (copy master values na variant create). Performance tradeoff dla dużych masterów (>50 variantów). Wymaga ADR.
- [ ] **CSV import schema** — fixed columns (uniwersalne kolumny SKU/name/price/...) vs. ObjectType-aware (dynamic columns per attached AttributeGroups). Drugi model jest bardziej elastyczny ale wymaga upload + 2-step (typ → mapping → preview).
- [ ] **Saved views per-user** vs. tenant-shared — obecnie MVP shared (wszystkie views widoczne dla tenant). Faza 1 + CurrentUserProvider w Identity = per-user filtering.
- [ ] **AttributeLevel enum** (`master | variant | both`) — czy variant może mieć własne atrybuty których master nie ma? Wpływa na schema `product_values` (kolumna `level`) i resolver inheritance.

## Już zrealizowane (po marathonie UI-02 — nie blokuje)

- [x] Saved views CRUD (#297, #336, #346 fix JWT auth header)
- [x] Bulk toggle_enabled (#293, #301)
- [x] Excel grid z inline edit (#350)
- [x] Variants tree mode (#351)
- [x] CreateProductWizard 4-step (#347)
- [x] DuplicateProductDialog (#332)
- [x] AdvancedFilterBuilder (#349)
- [x] DetailDynamicForm z ObjectType + AttributeGroups (#307, #338, #355)
- [x] VariantsTab z attribute autocomplete (#352)
- [x] ProductFilterChips
- [x] Effective attribute groups endpoint (#335)

## Zależności od innych epików

- Epik 0.8 (BaseLinker) + 0.9 (Shopify) — sync status w detail-sidebar zależy od pełnej integracji (po Fazie 1).
- Faza 2 (Agent layer) — wszystkie agent-related pozycje powyżej.
- Epik 0.11 (Hardening) — audit log endpoints + import CSV mogą wpaść tutaj.

## Dopisane przy NUI-04 (#1423, 2026-06-11) — luki vs design list-view-v2

- **Wiersze locked/pinned** (`LOCK_SKUS`/`STAR_SKUS` w mocku — kłódka edycji + pin „flagowy") — brak modelu w backendzie; SKIP w NUI-04. Wymaga: pola lock/pin per obiekt + endpoint + uprawnienia.
- **Liczniki per smart-preset** (design pokazuje count przy każdym presecie) — FE renderuje count gdy API go zwróci; dziś `/api/smart-presets` nie liczy trafień. Wymaga: tani endpoint zbiorczy `/api/smart-presets/counts` (cache 60 s).
- **Dedykowany token wyszukiwania EAN** — placeholder obiecuje EAN; dziś leci przez wyszukiwanie pełnotekstowe. Opcjonalnie: jawny `ean:` qualifier w search API.
