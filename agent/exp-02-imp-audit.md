# EXP-02 — Audit kontraktu IMP pipeline pod round-trip eksportu

> **Ticket:** [#581](https://github.com/malipie/PIM/issues/581) — EXP-02 POC: IMP kontrakt audit
> **Data audytu:** 2026-05-15
> **Zakres:** read-only review `apps/api/src/Import/` (IMP-01..IMP-15 merged PR-y #442–#456)
> **Cel:** zweryfikować 4 critical kontrakty z PRD §9.2 potrzebne dla round-trip Magdy *„export XLSX → edycja w Excelu → reimport"*. Każdy zwery­fikowany gap = follow-up ticket `IMP-16..19`.

---

## Streszczenie wykonawcze

**Wynik: 4/4 kontrakty FAIL.** IMP-01..IMP-15 dostarczyły solidny MVP dla *fresh* imports (INSERT new products + image download + ZIP extract), ale **NIE obsługuje** semantyki round-trip jakiej wymaga eksport produktów (PRD-PIM-exports.md §9.2):

- ❌ **Kontrakt 1 — Variants flat z `parent_sku`** — IMP ignoruje kolumnę, tworzy niezwiązane produkty.
- ❌ **Kontrakt 2 — Multi-value pipe-separated** — IMP traktuje `"a|b|c"` jako raw string, brak pipe-split.
- ❌ **Kontrakt 3 — Asset URL → asset_id resolution** — IMP zapisuje URL jako literal string, brak MinIO lookup.
- ❌ **Kontrakt 4 — Multi-locale columns** — IMP nie parsuje notation `attribute.locale` w headerach, `ObjectValue.locale` zawsze NULL.

**Konsekwencja:** Round-trip eksport→edit→reimport jest **broken** bez IMP-16..IMP-19. Magda po eksporcie XLSX i edycji w Excelu nie zaimportuje z powrotem variantów / tagów / asset URLs / multi-locale fields. Eksport będzie *„download-only"*, NIE round-trip-first jak deklaruje PRD §3.2.

**Rekomendacja:** 4 follow-up tickety IMP-16..IMP-19 (suma 32-44h). MUST-HAVE dla MVP round-trip (PRD §3.5 killer scenario). Bez nich eksport może shippować jako "download-only feature", ale wtedy PRD §2.4 *„North Star Metric — Magda 4-8 round-tripów/miesiąc"* nie jest realistic.

---

## Kontrakt 1 — Variants flat z `parent_sku` column

**Status:** ❌ FAIL

**Refs:**
- [`apps/api/src/Import/Application/Service/ImportObjectCreator.php:68-83`](../apps/api/src/Import/Application/Service/ImportObjectCreator.php#L68-L83) — konstruktor i metoda `create()` nie przyjmuje ani nie przypisuje `parent_sku`.
- [`apps/api/src/Catalog/Domain/Entity/ObjectValue.php:68-84`](../apps/api/src/Catalog/Domain/Entity/ObjectValue.php#L68-L84) — `ObjectValue` constructor nie inicjalizuje relacji parent.
- `Project Plan/UI/feature-imports.md` §3 (cytat): *„Tylko master rows w MVP. Variants w detail produktu ręcznie."*

**Test scenario:**
- Input XLSX:
  ```
  sku        parent_sku  name              description.pl
  TST-001    (blank)     Czujnik X-200     Master
  TST-001-A  TST-001     X-200 PNP M12     Variant PNP
  TST-001-B  TST-001     X-200 NPN M8      Variant NPN
  ```
- **Expected:** IMP rozpoznaje `parent_sku=TST-001` → linkuje TST-001-A/B jako variant mastera TST-001.
- **Actual:** IMP ignoruje kolumnę `parent_sku`, tworzy 3 niezwiązane `CatalogObject`. Round-trip zwraca strukturę masters+variants jako płaską listę produktów bez powiązań.

**Gap:**
1. `ImportObjectCreator` nie mapuje `parent_sku` na żaden pole encji.
2. Brak lookup'u parent SKU w `CatalogObjectRepository` podczas create.
3. ADR-010 (axis-driven variants) zakłada variant = osobny `CatalogObject` z FK na parent, ale IMP nie wystawia tej relacji.
4. feature-imports.md explicit *„variants z wide format"* jako Out of MVP, ale feature-exports.md §8.3 wymaga `parent_sku` w eksporcie → **inconsistent contract** między bliźniaczymi features.

---

## Kontrakt 2 — Multi-value pipe-separated parser

**Status:** ❌ FAIL

**Refs:**
- [`apps/api/src/Import/Application/Service/ImportObjectCreator.php:109-135`](../apps/api/src/Import/Application/Service/ImportObjectCreator.php#L109-L135) — `buildValuePayload()` zwraca `['value' => $raw]` (raw string) dla wszystkich typów poza Number/Price/Metric/Boolean.
- Brak case `AttributeType::Multiselect` w switch statement.
- feature-imports.md §3: *„Auto-mapping algorithm (a) Rules-based dictionary PL/EN"* — brak mention'u pipe-separator parsing.

**Test scenario:**
- Input: kolumna `tags = "promo|nowość|bestseller"` (pipe-separated per PRD-PIM-exports.md §8.2 default).
- **Expected:** `value=['option_codes' => ['promo', 'nowość', 'bestseller']]` (dla `AttributeType::Multiselect`).
- **Actual:** `value=['value' => "promo|nowość|bestseller"]` (literal string).

**Gap:**
1. Brak detekcji `AttributeType::Multiselect` w `buildValuePayload()`.
2. Brak pipe-split logiki — klient musiałby ręcznie rozbić wartości w Excel przed reimportem.
3. feature-exports.md §8.2 *„default pipe-separated"* nie ma odpowiednika w feature-imports.md.

---

## Kontrakt 3 — Asset URL → asset_id resolution

**Status:** ❌ FAIL

**Refs:**
- [`apps/api/src/Import/Application/Service/ImportObjectCreator.php:109-135`](../apps/api/src/Import/Application/Service/ImportObjectCreator.php#L109-L135) — brak case `AttributeType::Asset`, brak URL parsera, brak MinIO lookup.
- feature-imports.md §13 wspomina "image download HTTP + ZIP extraction", ale to download *nowych* obrazków, nie URL → existing asset_id resolution.

**Test scenario:**
- Input: kolumna `main_image = "https://cdn.cortex.example.com/uuid-tenantA/uuid-asset-1234.jpg"` lub presigned MinIO URL.
- **Expected:** Regex extract asset_id z path → lookup w `AssetRepository` → ObjectValue `value=['asset_id' => 'uuid-asset-1234']`.
- **Actual:** ObjectValue `value=['value' => "https://cdn..."]` (literal URL string). Po 1h presigned URL expiration → broken link.

**Gap:**
1. Brak regex/parser na URL aby extract asset_id.
2. Brak asset repository lookup.
3. Po round-trip image nie linkuje się do istniejącego asset, jest pseudo-string.

---

## Kontrakt 4 — Multi-locale columns

**Status:** ❌ FAIL

**Refs:**
- [`apps/api/src/Import/Application/Service/ImportObjectCreator.php:51-99`](../apps/api/src/Import/Application/Service/ImportObjectCreator.php#L51-L99) — metoda `create()` acceptuje `?string $categoryCode` ale **nie acceptuje** `?string $locale`.
- [`apps/api/src/Catalog/Domain/Entity/ObjectValue.php:68-84`](../apps/api/src/Catalog/Domain/Entity/ObjectValue.php#L68-L84) — `ObjectValue` ma `?string $locale` parametr, ale `ImportObjectCreator` **nigdy nie ustawia** tego pola → zawsze NULL po imporcie.
- feature-imports.md §3: *„Per-locale mapping (c) Single locale per import — klient wybiera locale na początku, multi-locale = osobne importy"*.
- feature-imports.md open questions: *„Multi-locale w jednym pliku — Faza 1+"*.

**Test scenario:**
- Input XLSX: `[sku, name, description.pl, description.en, price]`.
- **Expected:** Header parser rozpoznaje notation `description.pl` → mapuje na atrybut `description` z `locale=pl` w ObjectValue. Dwa osobne ObjectValue rows per produkt (`description` locale=pl + `description` locale=en).
- **Actual:** IMP traktuje `description.pl` i `description.en` jako dwa osobne atrybuty (literal column names) → fail mapping (brak atrybutu `description.pl` w `attributes` table) lub random behavior.

**Gap:**
1. `ImportRowReader` nie parsuje notation `<attribute>.<locale>` w nagłówku kolumny.
2. `ImportObjectCreator` L78-83 nigdy nie ustawia `locale` w `ObjectValue` constructor.
3. JSONB envelope `{value, locale, channel?, provenance?}` z `docs/api/jsonb-schemas.md` nie jest respektowane.
4. Round-trip multi-locale broken na poziomie struktury danych.

---

## Recommendation — follow-up tickets

| Ticket | Kontrakt | Scope | Estymacja | Priorytet |
|--------|----------|-------|-----------|-----------|
| **IMP-16** | Variants flat parent_sku | `ImportObjectCreator.create()` accept `parent_sku` param + lookup parent w repo + create variant relation (zgodnie z ADR-010). Header detection `parent_sku` column. ApiTestCase: master+variant XLSX → 2 produkty z FK. | **8-12h** | BLOCKER round-trip variants |
| **IMP-17** | Multi-value pipe parser | `buildValuePayload()` case `AttributeType::Multiselect` + pipe-split + option_code validation per atrybut definition. ApiTestCase: `tags="a\|b\|c"` → 3-element array. | **6-8h** | HIGH (tags / kategorie / gallery common) |
| **IMP-18** | Asset URL → asset_id | URL parser (regex CDN + presigned MinIO + path-based asset_id extraction) + `AssetRepository` lookup + ObjectValue `asset_id` mapping. ApiTestCase: round-trip image URL → existing asset reference. | **8-10h** | HIGH (main_image / gallery round-trip) |
| **IMP-19** | Multi-locale columns | Header parser `<attribute>.<locale>` notation w `ImportRowReader` + `ImportObjectCreator` setLocale() + `ObjectValue.locale` field. ApiTestCase: `description.pl` + `description.en` w jednym pliku → 2 ObjectValue rows per produkt. | **10-14h** | MEDIUM-HIGH (multi-locale dwujęzyczne SEO use case) |

**Total estymacja IMP-16..19:** **32-44h** dodatkowych godzin do realizacji round-trip-first eksportu.

---

## Decyzja architekturalna do podjęcia

PRD-PIM-exports.md §9.2 explicit: *„POC w Sprint 1 = pierwsza priorytet"* — team przewidział tę walidację jako blocker dla MVP eksportu. Audit potwierdza wszystkie 4 gaps.

**Trzy ścieżki przed startem EXP-03+ implementacji:**

### A. IMP-16..19 jako MUST-HAVE MVP (recommend dla round-trip-first positioning)
- Eksport implementowany razem z IMP follow-up tickets.
- Round-trip Magdy działa end-to-end od dnia 1.
- Estymacja MVP eksportu rośnie z ~50-95h (PRD §13.5) do ~82-139h.
- Spójne z PRD §3.2 differentiator *„round-trip-first design"*.

### B. IMP-16..19 jako Faza 1+ — eksport jako "download-only" w MVP
- Eksport ship'uje bez round-trip wsparcia w MVP.
- Magda może eksportować ale **nie** reimportować zmienionych plików.
- Estymacja MVP eksportu pozostaje 50-95h.
- PRD §1 jednozdaniowe pozycjonowanie *„z roundtripem przez import"* ZNIEKSZTAŁCONE — musimy ulepszyć comm.

### C. Hybrid: IMP-16 + IMP-19 w MVP, IMP-17 + IMP-18 w Faza 1
- Variants flat + multi-locale w MVP (critical SEO+variants round-trip).
- Multi-value tags + asset URL w Faza 1 (workaround: klient nie eksportuje tags/gallery w MVP).
- Estymacja MVP rośnie do ~68-113h.
- Eksport ship'uje z core round-trip dla primary use case Magdy (SEO PL+EN).

**Rekomendacja:** ścieżka **A** (pełen scope w MVP) jeśli operator akceptuje +32-44h, lub **C** jeśli budżet napięty. **NIE B** — bez round-trip eksport traci killer differentiator względem Shopify/Akeneo.

Decyzja zapadnie w PR description tego ticketu i wpłynie na kolejność EXP-03..EXP-15.
