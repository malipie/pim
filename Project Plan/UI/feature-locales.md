# Feature (mini-spec) — Locales & Internacjonalizacja (`/settings/locales`)

## Status: 🟢 szczegół (mini-spec)

> **Mini-spec** — zwięzła specyfikacja zakładki `/settings/locales`. Krótszy format niż `feature-imports.md` / `feature-list-advanced.md` / `feature-exports.md` — obszar jest mniejszy, decyzje proste.
> Wzorzec: **Pimcore per-locale UX** (default / fallback / mandatory flags) + **Akeneo channel↔locale binding** + **per-tenant adaptacja pod SaaS**.
> Powiązane: ADR-011 (per-tenant locale fallback), RBAC-P5-015 (Settings → Tenant config — locales multi-select wzmiankowane), `user_roles.locale_scope` (RBAC per-rola restriction).

---

## 1. Cel feature'a

`/settings/locales` to miejsce gdzie tenant definiuje **z jakimi językami treści pracuje**. Locale = wymiar lokalizacji po którym wartości atrybutów się rozdzielają (`description.pl_PL` vs `description.en_US`).

Zakładka robi 4 rzeczy:
1. **Aktywacja locales** — tenant wybiera z globalnego katalogu ISO swoje aktywne locales.
2. **Default locale** — fallback target + język domyślny dla nowych atrybutów.
3. **Fallback chain** (ADR-011) — gdy brak tłumaczenia, system pokazuje fallback locale.
4. **Channel↔locale binding** — który locale aktywny na którym kanale dystrybucji.

**Persona główna:** Magda (Marketing/Content) — pracuje multi-locale. **Konfiguruje:** Owner/Admin (Magda używa, nie konfiguruje).

## 2. Model — per-tenant, dwupoziomowy

```
┌─ POZIOM SYSTEM (globalny, read-only, seeded) ──────────────┐
│  Katalog locales ISO 639-1 + ISO 3166 (~150 kombinacji):   │
│  pl_PL, en_US, en_GB, de_DE, de_AT, de_CH, cs_CZ, sk_SK... │
│  Super Admin NIE zarządza aktywnie — stała referencja.      │
└─────────────────────────────────────────────────────────────┘
                    │ tenant aktywuje SWOJE
                    ▼
┌─ POZIOM TENANT (/settings/locales) ────────────────────────┐
│  Aktywne locales + default + fallback chain + channel bind  │
│  Owner/Admin konfiguruje. To jest /settings/locales.        │
└─────────────────────────────────────────────────────────────┘
```

**Kluczowe:** to **NIE** są *„Ustawienia systemowe"* superadmina (jak w Pimcore single-instance). Cortex jest multi-tenant SaaS — locale config jest **per-tenant**, decyzja biznesowa klienta.

**Locale format:** `język_REGION` (Akeneo style) — `pl_PL`, `de_DE`, `de_AT`. NIE sam język (`pl`, `de`). Format `język_REGION` obsługuje *„ten sam język, różny rynek"* — `de_DE` vs `de_AT` to różne opisy regulacyjne / ceny dla DACH eksportu.

## 3. Schema

```sql
-- POZIOM SYSTEM: katalog ISO (seeded raz, read-only, brak tenant_id)
CREATE TABLE locales (
    code         VARCHAR(10) PRIMARY KEY,   -- 'pl_PL', 'en_US', 'de_DE'
    language     VARCHAR(3) NOT NULL,        -- 'pl', 'en', 'de' (ISO 639-1)
    region       VARCHAR(3) NOT NULL,        -- 'PL', 'US', 'DE' (ISO 3166)
    display_name JSONB NOT NULL,             -- {"pl": "Polski (Polska)", "en": "Polish (Poland)"}
    is_popular   BOOLEAN NOT NULL DEFAULT false  -- sekcja "Popularne" w dropdown
);

-- POZIOM TENANT: aktywacja + config
CREATE TABLE tenant_locales (
    id               UUID PRIMARY KEY,
    tenant_id        UUID NOT NULL REFERENCES tenants(id),
    locale_code      VARCHAR(10) NOT NULL REFERENCES locales(code),
    is_default       BOOLEAN NOT NULL DEFAULT false,   -- dokładnie 1 per tenant
    is_mandatory     BOOLEAN NOT NULL DEFAULT false,   -- wlicza się do completeness
    fallback_locale  VARCHAR(10) REFERENCES locales(code),  -- nullable, tworzy chain
    sort_order       INTEGER NOT NULL DEFAULT 0,
    is_active        BOOLEAN NOT NULL DEFAULT true,     -- soft delete (dane zachowane)
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, locale_code)
);
CREATE UNIQUE INDEX idx_tenant_locales_default ON tenant_locales(tenant_id) WHERE is_default = true;

-- Channel ↔ locale binding (które locales aktywne per kanał)
CREATE TABLE channel_locales (
    tenant_id    UUID NOT NULL REFERENCES tenants(id),
    channel_id   UUID NOT NULL REFERENCES channels(id),
    locale_code  VARCHAR(10) NOT NULL REFERENCES locales(code),
    PRIMARY KEY (tenant_id, channel_id, locale_code)
);
```

**Uwagi schema:**
- `is_default` — partial unique index gwarantuje dokładnie 1 default per tenant.
- `fallback_locale` — single fallback per locale, tworzy chain (`de_AT → de_DE → en_US → default`). Prostsze mentalnie niż Pimcore CSV-lista wielu fallbacków.
- `is_active` — soft delete. Deaktywacja locale **nie kasuje** wartości w `object_values` (dane zachowane, reaktywowalne).
- `is_mandatory` — locale wymagany dla completeness produktu (Pimcore *„Mandatory language"* pattern).

## 4. UX layout

```
┌─ Ustawienia → Języki i lokalizacje ─────────────────────────────────┐
│                                                                       │
│ Aktywne lokalizacje:                          [+ Dodaj lokalizację]  │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ ⠿ pl_PL  Polski (Polska)    ⭐ Domyślna  ☑ Wymagana            │ │
│ │           Fallback: —                                      [⋮]  │ │
│ │ ⠿ en_US  English (US)                    ☑ Wymagana            │ │
│ │           Fallback: [pl_PL ▼]                              [⋮]  │ │
│ │ ⠿ de_DE  Deutsch (DE)                    ☐ Wymagana            │ │
│ │           Fallback: [en_US ▼]                              [⋮]  │ │
│ │ ⠿ de_AT  Deutsch (AT)                    ☐ Wymagana            │ │
│ │           Fallback: [de_DE ▼]                              [⋮]  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│  ⠿ = drag-reorder (sort_order)                                       │
│                                                                       │
│ Przypisanie do kanałów:                                              │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Kanał        │ pl_PL │ en_US │ de_DE │ de_AT │                   │ │
│ │ Shopify      │  ☑    │  ☑    │  ☑    │  ☐    │                   │ │
│ │ BaseLinker   │  ☑    │  ☐    │  ☐    │  ☐    │                   │ │
│ │ Allegro      │  ☑    │  ☐    │  ☐    │  ☐    │                   │ │
│ └─────────────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────────────┘
```

**Modal *„Dodaj lokalizację"*:**

```
┌─ Dodaj lokalizację ─────────────────────────────────┐
│ 🔍 Szukaj języka...                                 │
├──────────────────────────────────────────────────────┤
│ POPULARNE                                            │
│  ├─ pl_PL  Polski (Polska)                           │
│  ├─ en_US  English (US)                              │
│  ├─ en_GB  English (UK)                              │
│  ├─ de_DE  Deutsch (Deutschland)                     │
│  ├─ cs_CZ  Čeština (Česko)                           │
│  └─ sk_SK  Slovenčina (Slovensko)                    │
├──────────────────────────────────────────────────────┤
│ WSZYSTKIE (~150)                                     │
│  ├─ af     Afrikaans                                 │
│  ├─ af_NA  Afrikaans (Namibia)                       │
│  └─ ... (search filtruje)                            │
└──────────────────────────────────────────────────────┘
```

**Różnica vs Pimcore screenshot:** Pimcore dropdown wyrzuca od razu `afrikaans, aghem, akan...` — overwhelm. Cortex ma sekcję *„Popularne"* na górze (typowe dla polskiego e-commerce eksportującego do CEE/DACH) + search + pełna lista ISO pod spodem.

## 5. Mechanika

### 5.1 Default locale
- Dokładnie 1 per tenant. Zmiana default → confirm modal (*„Zmiana wpłynie na nowe atrybuty i fallback"*).
- Default jest ultimate fallback target — koniec każdego fallback chain.
- Nie można usunąć/deaktywować default locale (UI blokuje, analog last-admin protection).

### 5.2 Fallback chain (ADR-011)
- Każdy locale ma opcjonalny single `fallback_locale`. Tworzy chain: `de_AT → de_DE → en_US → pl_PL (default)`.
- Runtime: gdy `description.de_AT` puste → system zwraca `description.de_DE`, jeśli puste → `description.en_US`, ... aż default.
- **Cykl detection** — UI blokuje fallback który tworzy pętlę (`A → B → A`).
- Fallback działa na **read** (wyświetlanie, eksport feed), NIE na completeness scoring (mandatory locale musi być faktycznie wypełniony).

### 5.3 Mandatory flag
- `is_mandatory = true` → locale wlicza się do completeness produktu.
- Produkt nie jest *„kompletny"* dopóki wszystkie mandatory locales mają wypełnione localizable atrybuty.
- Default locale jest **zawsze** mandatory (auto, niewyłączalny).
- Niemandatory locale = *„nice to have"*, brak tłumaczenia nie obniża completeness.

### 5.4 Soft delete / deaktywacja
- Usunięcie locale = `is_active = false` (soft). Dane w `object_values` **zachowane**.
- UI warning przed deaktywacją: *„de_DE: 2000 produktów ma wartości w tym locale. Dane zostaną zachowane, ale niewidoczne. Możesz reaktywować w każdej chwili."*
- **Brak console command** (vs Pimcore `pimcore:locale:delete-unused-tables`). Cleanup automatyczny (background job) lub przez UI *„Trwale usuń dane locale"* (hard, z hard-confirm typing).
- Reaktywacja locale → dane wracają, widoczne.

### 5.5 Dodanie nowego locale — wpływ na completeness
- Dodanie locale `de_AT` → wszystkie localizable atrybuty zyskują pusty slot dla `de_AT`.
- Jeśli `de_AT` ustawiony jako mandatory → completeness produktów spada (brak tłumaczeń).
- UX ostrzega przy dodaniu z `is_mandatory=true`: *„Dodanie de_AT jako wymagany obniży completeness — 2000 produktów bez tłumaczeń de_AT."*

## 6. Permissions (RBAC integration)

- **Nowa permission `settings.locales.manage`** — osobna od `manage_tenant`. Rationale: dodanie locale to operacyjna decyzja (Magda's manager może chcieć), nie krytyczna jak billing/tenant deletion. Owner + Admin mają domyślnie.
- Alternatywa: zostać przy `manage_tenant` (tylko Owner). **Rekomendacja: osobna permission** — Admin powinien móc.
- Update macierzy RBAC §3.2: dodać wiersz *„Settings — Locales (manage_locales)"* — Owner ✓, Admin ✓, reszta ✗.
- `tenant_locales` to **pula** z której `user_roles.locale_scope` (RBAC) wybiera per-rola restriction (Magda *„EN-only translator"*).

## 7. API endpoints

| Endpoint | Metoda | Cel |
|---|---|---|
| `/api/locales` | GET | Globalny katalog ISO (read-only, dla dropdown) |
| `/api/tenant-locales` | GET | Aktywne locales tenanta + config |
| `/api/tenant-locales` | POST | Aktywacja nowego locale |
| `/api/tenant-locales/{code}` | PATCH | Update (default, mandatory, fallback, sort_order) |
| `/api/tenant-locales/{code}` | DELETE | Soft delete (is_active=false) |
| `/api/tenant-locales/{code}/reactivate` | POST | Reaktywacja |
| `/api/tenant-locales/{code}/purge` | DELETE | Hard delete danych (z hard-confirm) |
| `/api/channel-locales` | GET/PUT | Channel↔locale binding macierz |

## 8. Dependency na inne obszary

- **Attribute model** — atrybut `localizable: true` generuje wartość per aktywny locale tenanta. Dodanie/usunięcie locale wpływa na localizable atrybuty.
- **Completeness engine** (epik 02) — mandatory locales wliczają się do completeness scoring.
- **RBAC** — `user_roles.locale_scope` wybiera z `tenant_locales` puli. Update macierzy §3.2 z `settings.locales.manage`.
- **Channels** (epik 04 Publikacje) — `channel_locales` binding decyduje który locale idzie do którego feed/sync.
- **Exports** (`feature-exports.md`) — multi-locale export toggle wybiera z aktywnych locales.
- **Imports** (`feature-imports.md`) — import multi-locale columns (`description.pl`, `description.en`) mapuje na aktywne locales.

## 9. User stories

| ID | Persona | Story |
|---|---|---|
| US-LOC-001 | Owner | Dodaje `de_DE` do tenanta przez modal z sekcji *„Popularne"* |
| US-LOC-002 | Owner | Ustawia `pl_PL` jako default — system blokuje deaktywację default |
| US-LOC-003 | Admin | Konfiguruje fallback chain `de_AT → de_DE → en_US` — system wykrywa próbę cyklu i blokuje |
| US-LOC-004 | Admin | Oznacza `en_US` jako mandatory — completeness produktów bez EN tłumaczeń spada |
| US-LOC-005 | Admin | Deaktywuje `de_AT` — widzi warning *„2000 produktów ma wartości"*, dane zachowane |
| US-LOC-006 | Owner | Przypisuje locales do kanałów — Shopify dostaje PL+EN+DE, Allegro tylko PL |
| US-LOC-007 | Magda | Edytuje produkt — widzi taby tylko dla aktywnych locales tenanta + tych w jej `locale_scope` |
| US-LOC-008 | Admin | Reaktywuje wcześniej usunięty `de_AT` — dane wracają widoczne |

## 10. Out of scope (mini-spec MVP)

- ❌ **Translation workflow** — eksport do TMS (Smartling/Lokalise), translation memory. Faza 2.
- ❌ **AI auto-translate** — generowanie tłumaczeń przez LLM. Faza 2 (data-ops agent).
- ❌ **Per-locale currency binding** — currency jest osobnym konceptem (per channel/market), NIE w `/settings/locales`.
- ❌ **Locale-specific validation rules** — np. *„niemiecki opis musi mieć disclaimer X"*. Faza 1+.
- ❌ **Hreflang / SEO locale tags** — generowanie hreflang dla feedów. Faza 1 razem z channel feed engine.
- ❌ **RTL languages** (arabski, hebrajski) — UI RTL support. Poza MVP scope (ICP to PL/CEE/DACH).

## 11. Estymacja

| Element | Estymacja |
|---|---|
| Backend — schema (3 tabele) + ISO katalog seed (~150 locales) | 6-8h |
| Backend — API endpoints (8) + fallback chain resolution + cycle detection | 10-14h |
| Backend — completeness integration (mandatory locales) | 4-6h |
| Backend — soft delete + reactivate + purge logic | 4-6h |
| Frontend — `/settings/locales` page (lista + drag-reorder + flags) | 8-12h |
| Frontend — modal *„Dodaj lokalizację"* (popularne + search + ISO) | 4-6h |
| Frontend — channel↔locale binding macierz | 4-6h |
| RBAC integration — `settings.locales.manage` permission + macierz update | 2-3h |
| Testy — unit + integration + E2E + fallback chain edge cases | 8-12h |
| **TOTAL** | **~50-73h** |

~8-10 ticketów, ~1.5-2 tygodnie solo dev.

## 12. Co dalej

1. Walidacja konceptu — czy `język_REGION` format (vs sam język) jest akceptowalny dla ICP.
2. Decyzja: `settings.locales.manage` osobna permission vs `manage_tenant` (rekomendacja: osobna).
3. POC fallback chain resolution — performance przy 200k SKU × 4 locales (czy chain resolution w runtime jest fast, czy cache potrzebny).
4. Wireframes Figma — przekazać do makiet (analog do RBAC mockups).
5. Update macierzy RBAC §3.2 — dodać wiersz `settings.locales.manage` gdy feature wchodzi do implementacji.
6. Sequencing — locale jest **dependency dla wielu obszarów** (attribute model, completeness, channels, exports). Powinno być wcześnie w MVP-Alpha, przed epikiem 04 Publikacje.

---

*Mini-spec wygenerowany 2026-05-16. Status: 🟢 szczegół. Wzorzec: Pimcore per-locale UX + Akeneo channel binding + per-tenant SaaS adaptacja. Następna iteracja: walidacja formatu locale + Figma wireframes + sequencing decision względem epiku 04.*
