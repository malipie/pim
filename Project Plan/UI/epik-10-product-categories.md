# Epik UI-10 — Product Categories Assignment

**Status:** Closed (8/8 tickets shipped)
**Sprzed mergowanego do main:** 2026-05-10
**Zakres czasowy:** 1-day burst (PCAT-01..07)
**Tickety:** [#474](https://github.com/malipie/PIM/issues/474), [#475](https://github.com/malipie/PIM/issues/475), [#476](https://github.com/malipie/PIM/issues/476), [#477](https://github.com/malipie/PIM/issues/477), [#478](https://github.com/malipie/PIM/issues/478), [#479](https://github.com/malipie/PIM/issues/479), [#480](https://github.com/malipie/PIM/issues/480), [#481](https://github.com/malipie/PIM/issues/481)
**PR-y:** [#482](https://github.com/malipie/PIM/pull/482), [#483](https://github.com/malipie/PIM/pull/483), [#485](https://github.com/malipie/PIM/pull/485), [#486](https://github.com/malipie/PIM/pull/486), [#487](https://github.com/malipie/PIM/pull/487), [#488](https://github.com/malipie/PIM/pull/488), [#489](https://github.com/malipie/PIM/pull/489) (+ chore [#484](https://github.com/malipie/PIM/pull/484))

---

## Problem

Cała koncepcja „Samochody mają inne pola niż Kosmetyki samochodowe" była **martwa**. Backend miał gotowy mechanizm dziedziczenia grup atrybutów po drzewie kategorii (`CategoryAttributeGroup` + `EffectiveAttributeGroupResolver`), ale **nie istniała relacja produkt↔kategoria** której resolver mógłby skonsumować dla `kind=Product`. Dwa konkretne objawy:

1. `EffectiveAttributeGroupResolver::resolve($product)` zwracał tylko global ObjectType groups — branch dla `kind=Product` nie istniał, choć branch `kind=Category` był gotowy.
2. „Effective preview" w panelu kategorii (killer feature) działał hipotetycznie — pokazywał *jakie grupy obiekt zobaczyłby*, ale dziś żaden produkt nie mógł być w kategorii, więc preview nie miał walidacji empirycznej.

Dodatkowo button „+ Create test object" w panelu kategorii był MOCK (disabled, tooltip „Faza 1") — pierwszy *zalążek* dokładnie tej historii.

## Outcome

Po epiku operator może:

1. **Przypisać produkt do N kategorii** z 1 oznaczoną jako primary (zakładka „Kategorie" w karcie produktu)
2. **Widzieć w formie produktu pola dziedziczone po kategorii** (resolver branch dla Product aktywuje istniejący `CategoryAttributeGroup`)
3. **Zobaczyć z poziomu panelu kategorii listę przypisanych produktów** (nowa karta „Produkty w tej kategorii (N)")
4. **Utworzyć produkt pre-przypisany do kategorii** jednym kliknięciem („+ Create test object" aktywne dla Product)

Killer feature „Effective preview" w panelu kategorii dostaje walidację empiryczną — co preview obiecuje pokazuje się w realnym formularzu produktu.

---

## Decyzje produktowe (potwierdzone z operatorem)

1. **Primary + dodatkowe** — produkt ma 1 kategorię główną (`is_primary=true`) + N dodatkowych. Partial unique index na DB gwarantuje 1 primary per object. Primary użyte później w eksportach single-channel (Shopify wymaga jednej kategorii per produkt) i w breadcrumbs storefront.
2. **Dedykowana junction `object_categories`** — nie reuse `Association`. Lepsze indeksy + czystsza semantyka + zgodność z resztą architektury (junction-pattern jak `CategoryAttributeGroup`).
3. **Tab Kategorie** w karcie produktu — osobny pomiędzy Multimedia a Powiązania. Operator zmienił scope w trakcie planowania (oryginalnie planowane jako sekcja w tabie Powiązania) — kategorie są pierwszej klasy obywatelem produktu, podczas gdy Powiązania semantycznie znaczą produkt↔produkt (cross-sell).
4. **Per-ObjectType cache invalidation** (nie per-object) — trade-off zaakceptowany: zmiana 1 produktu burst-uje cache dla wszystkich produktów tego typu. Per-object cache to follow-up Faza 1.1.

---

## Architektura

### Junction `object_categories`

```sql
CREATE TABLE object_categories (
  object_id   UUID NOT NULL,
  category_id UUID NOT NULL,
  is_primary  BOOLEAN NOT NULL DEFAULT false,
  position    INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (object_id, category_id),
  CONSTRAINT object_categories_no_self CHECK (object_id <> category_id)
);
CREATE INDEX object_categories_object_idx ON object_categories(object_id);
CREATE INDEX object_categories_category_idx ON object_categories(category_id);
CREATE UNIQUE INDEX object_categories_one_primary_per_object
  ON object_categories(object_id) WHERE is_primary = true;
ALTER TABLE object_categories
  ADD CONSTRAINT object_categories_object_fk FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE,
  ADD CONSTRAINT object_categories_category_fk FOREIGN KEY (category_id) REFERENCES objects(id) ON DELETE CASCADE;
```

**Tenant scope:** dziedziczony przez FK do `objects.tenant_id` (bez own column). Listed w `TenantAuditCommand::INFRA_TABLES` allowlist.

### API endpointy (HTTP layer — nie API Platform)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/products/{id}/categories` | List assignments |
| PUT | `/api/products/{id}/categories` | Atomic replace (primary + categoryIds) |
| POST | `/api/products/{id}/categories` | Idempotent single add (z opcjonalnym primary swap) |
| DELETE | `/api/products/{id}/categories/{categoryId}` | Detach + auto-promote next |
| GET | `/api/categories/{id}/products` | Reverse listing (paginated, hydra-shaped) |

Walidacje (PUT):
- max 50 kategorii per produkt (`MAX_ASSIGNMENTS = 50` w controllerze)
- każdy `categoryId` musi być `kind=category` w tym samym tenancie
- duplikaty w `categoryIds` blokowane (422)
- `primaryCategoryId` musi być w `categoryIds` (lub `null` przy pustej liście)
- target produkt musi być `kind=product` (404 inaczej)

### Resolver branch (PCAT-03)

```php
} elseif (ObjectKind::Product === $object->getKind()) {
    $assignments = $this->productCategories->findByProduct($object);
    if ([] !== $assignments) {
        $ancestorMap = [];
        foreach ($assignments as $assignment) {
            foreach ($this->collectCategoryAncestorIds($assignment->getCategory(), includeSelf: true) as $id) {
                $ancestorMap[$id->toRfc4122()] = $id;
            }
        }
        if ([] !== $ancestorMap) {
            $this->mergeCategoryGroups($groups, array_values($ancestorMap), $type);
        }
    }
}
```

Reuse istniejących helperów (`collectCategoryAncestorIds` z `includeSelf=true`, `mergeCategoryGroups`). Resolver pozostaje stateless. Backward compat: produkt bez assignments → fall back do ObjectType groups (zero regresji).

### Primary repair listener (PCAT-03)

Gdy kategoria jest cascade-removed:
- `preRemove` na CatalogObject (`kind=category`) buforuje `Uuid[]` produktów dla których była primary (DBAL SELECT przed cascade)
- `postFlush` dla każdego produktu wykonuje raw DBAL UPDATE: `WHERE oc.object_id = :pid ORDER BY position ASC, created_at ASC LIMIT 1` → set `is_primary=true`
- Brak innych assignments → primary `null` (legalne)

Raw DBAL bo managed entities są już detached po CASCADE. ORM-side mutations either miss the rows or fight Doctrine change tracking.

### Cache invalidator (PCAT-04)

`ObjectFormSchemaCacheInvalidator` rozszerzony o branch dla `ObjectCategory`:
```php
if ($entity instanceof ObjectCategory) {
    $product = $entity->getProduct();
    $this->perTypeTags[$product->getObjectType()->getId()->toRfc4122()] = true;
}
```

Per-ObjectType invalidation (zgodnie z istniejącą strategią). Trade-off: bursty.

---

## Frontend

### Tab „Kategorie" w karcie produktu (PCAT-05)

Nowa kolejność tabów: `Atrybuty` | `Multimedia` | **`Kategorie`** | `Powiązania` | `Historia` | `Warianty`

`<CategoriesTab>` ([apps/admin/src/features/catalog/products/components/categories-tab.tsx](../../apps/admin/src/features/catalog/products/components/categories-tab.tsx)):
- Chip-list assignments (★ primary, × detach)
- Single-row operations (toggle primary, detach) bezpośrednio przez POST/DELETE
- Button „+ Edytuj kategorie" otwiera dialog z multi-select tree

`<CategoryPickerDialog>` ([apps/admin/src/components/catalog/category-picker-dialog.tsx](../../apps/admin/src/components/catalog/category-picker-dialog.tsx)):
- Multi-select tree (search + checkboxes + per-row star radio dla primary)
- Auto-promote first selection do primary (mirroring DELETE handler semantics)
- Save → single PUT (atomic replace)

Adaptacja `<CategoryTree>` — opcjonalny `mode='multi-select'` z `selectedIds`/`onToggle`/`primaryId`/`onPrimaryChange`. Backward compat default `'select'`.

### Tab „Produkty (N)" w panelu kategorii (PCAT-06)

`<CategoryProductsCard>` ([apps/admin/src/features/catalog/categories/category-products-card.tsx](../../apps/admin/src/features/catalog/categories/category-products-card.tsx)) renderowany pod „Effective preview":
- Paginowana lista (PAGE_SIZE = 20) z totalItems counter
- Row: ★ primary + code + localized name + enabled badge + link do `/products/{id}`
- Empty state z hint o tab Kategorie

### „+ Create test object" (PCAT-06b)

Dla `targetType === 'product'` button MOCK staje się `<Link>` do `/products/new?categories=<id>&primary=<id>`. ProductDetailPage w mode='create' parsuje query params i po POST wykonuje PUT na junction. Inne kindy zostają na MOCK (Faza 2).

---

## Co NIE jest scope tego epiku

- **Per-object cache key** — Faza 1.1 (4-6h, wymaga rozszerzenia `GetObjectFormSchemaHandler` o trzecią warstwę tagów)
- **Bulk reassignment** — Faza 1 (zaznacz N produktów → przypisz do kategorii)
- **Drag&drop produktu z grid na drzewo** — Faza 1+
- **Reorder przypisanych kategorii w pickerze** — Faza 1+
- **Filtrowanie listy „Produkty per kategoria"** — Faza 1
- **OpenAPI annotations dla custom controllerów** — pomijamy (zgodnie z istniejącym wzorcem `CategoryAttributeGroupController`); follow-up ticket „API documentation completeness"
- **E2E spec dla picker-a** — follow-up

---

## Lessons (zapisane w `agent/lessons.md`)

### Patterns to follow

- **Junction bez tenant_id** dziedziczy izolację przez FK do TenantScoped głównej encji (`objects.tenant_id`). Dodać do `TenantAuditCommand::INFRA_TABLES` allowlist. Wzór: `category_attribute_groups`.
- **Partial unique index** dla 1-of-N constraint (`WHERE is_primary = true`). ORM XML nie wspiera — migracja jest autorytatywna, ORM mirror plain (lub none jeśli plain by zablokował multi-row case).
- **Atomic replace** (DELETE all + INSERT new w jednej transakcji) — używać ORM remove (nie DQL DELETE), żeby Identity Map była zsynchronizowana. Inaczej kolejne `persist` z tymi samymi composite PKs rzuca `EntityIdentityCollisionException`.
- **Listener który mutuje przez DBAL po cascade** — bo managed entities są detached. Cleanup DBAL UPDATE w `postFlush` jest bezpieczny (partial unique index nie ma window).

### Patterns to avoid

- **NIE używać DQL DELETE** w atomic replace pattern — zostawia stale managed entities w Identity Map.
- **NIE polegać na partial unique index w testach Foundry** — `ResetDatabase` rebuild schema z ORM mapping, partial indexes nie są w XML. Testy app-level (nie DB-level) dla 1-of-N invariants.

### Świadome decyzje

- **Per-ObjectType cache invalidation** zaakceptowane jako trade-off (burst akceptowany w MVP). Per-object follow-up Faza 1.1.
- **Custom controllers nie w OpenAPI docs** — zgodnie z istniejącym wzorcem `CategoryAttributeGroupController`. Follow-up ticket dla pełnego API documentation completeness.
- **`pnpm.overrides` dla fast-uri** ([#484](https://github.com/malipie/PIM/pull/484)) — transitive vuln w `@commitlint/cli > ajv > fast-uri`. Override do >=3.1.2 do czasu upstream commitlint bumpu ajv.

---

## Manual smoke flow (end-to-end weryfikacja)

1. Login `admin@demo.localhost / changeme`
2. Modeling → Kategorie → utwórz „Samochody" → zadeklaruj grupę „Specyfikacja samochodu" (target: Product)
3. Edit dowolnego produktu → tab Kategorie → „+ Edytuj kategorie" → wybierz „Samochody" → ustaw primary → Save
4. Refresh strony → tab Atrybuty pokazuje sekcję „Specyfikacja samochodu" (dziedziczona z kategorii)
5. Modeling → Kategorie → „Samochody" → karta „Produkty w tej kategorii (1)" — widać produkt z ★
6. „+ Create test object" w karcie Effective preview → otwiera form nowego produktu z prepopulated category
7. Save → produkt utworzony + już ma „Samochody" jako primary

**DevTools:**
- PUT/POST/DELETE wszystkie 200/201/204
- Console bez errorów

---

## Next steps

Po merge wszystkich 8 PR-ów epik jest closed. Naturalne follow-up tickety:

1. **PCAT-FOLLOWUP-01: Per-object cache key** (Faza 1.1, 4-6h)
2. **PCAT-FOLLOWUP-02: Bulk reassignment z grida produktów** (Faza 1)
3. **PCAT-FOLLOWUP-03: E2E spec dla picker-a** (małe)
4. **API documentation completeness** — OpenAPI annotations dla custom controllerów (osobny epik)

Pierwsze MDM follow-upy (rozszerzenia typu „bazy kompatybilności") są w [Zrodla/PRD/MDM-rozszerzenia-pomysly.md](../../Zrodla/PRD/MDM-rozszerzenia-pomysly.md) — Faza 2/3 scope.
