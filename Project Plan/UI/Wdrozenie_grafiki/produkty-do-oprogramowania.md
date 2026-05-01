# Produkty — backlog do oprogramowania

> Baza pod kolejne GitHub tickety. Każda pozycja zaznaczona w kodzie komentarzem `MOCK:` lub `TODO(handoff)`.
>
> **Stan na 2026-05-02 po merge'u #358.** Aktualna implementacja list+detail jest w ~75-80% gotowa po marathonie UI-02; ten plik wylicza luki które handoff design ujawnia.

## Frontend + nowy endpoint backendowy

### Bulk operations (rozszerzenie istniejącego /bulk-edit)

- [ ] **Bulk attribute edit** — `POST /api/products/bulk-edit` z operation `edit_attribute`. Frontend: `BulkEditAttributeModal` (picker atrybutu + value input) wywoływany z `bulk-actions-toolbar.tsx`. Backend: rozszerzenie istniejącego `BulkEditController` o nowy operation handler. Estymacja: M (FE 3-4h, BE 4-5h).
- [ ] **Bulk category change** — `POST /api/products/bulk-edit` z operation `change_category`. Modal picker kategorii + apply do selected SKU.
- [ ] **Bulk export CSV** — `GET /api/products/export?ids=…&format=csv` (streaming). Backend: streaming CSV response (FrankenPHP buffer flush — patrz lessons), wszystkie visible columns + attribute columns. Frontend: modal "Export selected" → trigger download. Estymacja: M (FE 3-4h, BE 5-6h).

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
