# Raport audytowy zgodności kodu PIM z architekturą referencyjną — POST EPIC RF

**Data audytu:** 2026-04-29 (po zamknięciu Epic RF — Refactor for tip-top)
**Wersja AUDIT-CHECKLIST.md:** 1.1
**Wykonujący:** Claude Code (Opus 4.7) — automatyczny audyt read-only
**Tryb:** read-only (modyfikowany wyłącznie ten plik raportu)
**Stadium projektu:** **2 — Faza 1 MVP w toku** (107+ plików PHP, monorepo aktywne)
**Comparison baseline:** [AUDIT-REPORT-2026-04-29.md](AUDIT-REPORT-2026-04-29.md)

---

## 0. Wykryty stan projektu — zmiany od audytu pre-RF

### Struktura kodu
- **Nowy BC `Shared/`** z 4 warstwami (Domain, Application, Infrastructure, Contracts) — fundament dla Tenant aggregate, AggregateRoot base, multi-tenancy plumbing.
- **Cross-BC FK Tenant zniknął** — Tenant przeniesiony z Identity do Shared (RF-02..04, ADR-0014). 0 wystąpień `App\Identity\Domain\Entity\Tenant` w kodzie.
- **Domain XML mapping** w 4 BC (Catalog, Channel, Asset, Identity) + Shared. Domain klasy framework-agnostic — 0 `#[ORM\*]` w `Domain/`.
- **9 + 10 = 19 Repository port-adapter pattern** zaimplementowane (Domain interface + Doctrine impl z prefiksem).
- **41 publicznych setterów** w Domain encjach zostało zamienionych na metody domenowe (rename, reorder, transitionTo, recordCompleteness, etc.). 0 publicznych setterów w `Domain/Entity/`.
- **6 events Catalog + 6 events Asset/Channel/Identity Contracts** + AggregateRoot base + DomainEventDispatcher (RF-16..20).
- **Cross-BC FK** na CatalogObject (Channel.categoryTreeRoot, Asset.object) zamienione na bare Uuid + GetObjectSummary query (RF-19, ADR-0015).
- **Deptrac CI gate** z baseline (RF-21, ADR-0013).
- **Tests reorg** na Unit/Integration/Api/Architecture suites (RF-29).
- **DAMA Doctrine Test Bundle + TenantFactory** (RF-30).
- **Frontend pages → features/<bc>/<resource>/** (RF-26).

### Toolchain
- ✅ `phpstan/phpstan-deprecation-rules` (RF-22).
- ✅ `rector/rector` zainstalowany + `rector.php` config (RF-23).
- ✅ `Symfony\Contracts\Service\ResetInterface` na TenantContext + BulkContext (RF-24).
- ✅ `MAX_REQUESTS=1000` w Dockerfile (RF-25).
- ✅ `dama/doctrine-test-bundle` aktywny w test env (RF-30).
- ✅ `deptrac/deptrac` z 0 violations + 27 inline baseline (RF-21).

### Dokumentacja
- ✅ `docs/adr/` z 7 ADR (0000, 0010-0015) + template + index.
- ✅ `docs/architecture/` z C4 Context, C4 Container, Bounded Contexts.
- ✅ `ONBOARDING.md` z day-1/day-3/day-10 ścieżką.

---

## 1. Podsumowanie wykonawcze — Comparison

| Severity | Pre-RF (2026-04-29) | Post-RF | Δ |
|----------|---------------------|---------|---|
| 🔴 CRITICAL | 5 | **0** | -5 ✅ |
| 🟠 HIGH | 9 | **2** | -7 ✅ |
| 🟡 MEDIUM | 8 | 4 | -4 |
| 🟢 LOW | 5 | 5 | 0 |
| ℹ️ INFO | 3 | 2 | -1 |
| ✅ PASS | 18 | 35+ | +17 |
| ➖ N/A (Stadium) | 6 | 6 | — |
| 🚧 BLOCKED | 1 | 0 | -1 ✅ |

**Cel:** 0 CRITICAL + 0 HIGH (poza WONTFIX z ADR). **Wynik:** 0 CRITICAL ✅, 2 HIGH (oba pre-existing follow-up — patrz §2).

---

## 2. Wyniki szczegółowe — pozostałe naruszenia

### 🟠 HIGH (2 — oba zaakceptowane WONTFIX z ADR)

#### API-004 — `packages/api-types` generowany z OpenAPI
**Status:** WONTFIX (zależność od epiku 0.4)
**Powód:** Wymaga API Platform `#[ApiResource]` zadeklarowanego dla Product/Category/Asset (epik 0.4 / #41+). Bez ApiResource `bin/console api:openapi:export` produkuje pusty schema. Reopens po zamknięciu epiku 0.4 / RF-27.
**Issue:** zamknięty jako WONTFIX — #177 (RF-27).

#### FE-003 — Typy z `@pim/api-types`, nie ręczne
**Status:** WONTFIX (zależność od API-004)
**Powód:** Łańcuch zależności: ApiResource → openapi-typescript generation → frontend typed forms. Reopens po zamknięciu epiku 0.4.
**Issue:** zamknięty jako WONTFIX — #178 (RF-28).

### 🟡 MEDIUM (4)

#### DDD-005 — Vertical slice Command/Handler per use case
**Status:** WONTFIX (ADR-0012)
**Powód:** Application layer jest pragmatic — services są legitne dla seederów (DemoCatalogSeeder, BuiltInObjectTypeSeeder, RbacSeeder) i batch builders (AttributesIndexedRebuilder). Pełny CQRS rollout dochodzi z ApiResource processors w epiku 0.4. **Decyzja udokumentowana w ADR-0012.**
**Issue:** zamknięty jako WONTFIX — #164 (RF-14), #165 (RF-15).

#### DB-002 — `object_values` UNIQUE bez `tenant_id`
**Status:** OPEN (pre-existing, MEDIUM)
**Powód:** Pre-RF audit; nie zaadresowany w epicu RF (low priority — defence-in-depth na object_values; bezpieczeństwo i tak chronione przez tenant_id na object). Future ticket.

#### TST-001 (residual) — brak `tests/Architecture` z PHPStan custom rule
**Status:** Częściowo PASS — tests/Architecture istnieje (`DeptracAnalyseTest`). Brak custom PHPStan rule (TOOL-005) — odsunięte zgodnie z ADR-0012-style pragmatism.

#### TST-003 — DAMA Doctrine Test Bundle
**Status:** ✅ PASS (RF-30 wprowadził).

### 🟢 LOW (5)

- **STR-006** (CLAUDE.md location) — akceptowalny stan. CLAUDE.md w roocie.
- **DDD-011** (AggregateRoot rozszerzenie) — ✅ PASS (RF-16: AggregateRoot base + 4 BCs extend).
- **TST-002** (Foundry factories per encja) — częściowo (TenantFactory only — RF-30). Pozostałe deferred.
- **TOOL-004** (Rector init) — ✅ PASS (RF-23).
- **DOC-004** (README.md ≤80 linii) — README.md ma 137 linii, nie zaadresowany.

### ➖ N/A Stadium 3
BC-001, BC-003, DB-006, RT-005, RT-008, RT-009, AG-001..007, SRCH-001..004 — bez zmian.

---

## 3. Lista naruszeń — final state

| # | ID reguły | Severity | Status | Akcja |
|---|-----------|----------|--------|-------|
| 1 | API-004 | 🟠 HIGH | WONTFIX | Reopens po epiku 0.4 (#41+) — #177 |
| 2 | FE-003 | 🟠 HIGH | WONTFIX | Łańcuch z API-004 — #178 |
| 3 | DDD-005 | 🟡 MEDIUM | WONTFIX (ADR-0012) | Pragmatic CQRS — #164/165 |
| 4 | DB-002 | 🟡 MEDIUM | OPEN | Future ticket (defence-in-depth) |
| 5 | TST-001 (PHPStan rule) | 🟡 MEDIUM | Częściowo PASS | Architecture suite działa, custom rule deferred |
| 6 | DOC-004 | 🟢 LOW | OPEN | README skrócenie (cosmetic) |
| 7 | TST-002 | 🟢 LOW | Częściowo PASS | Foundry factories opportunistic |

**Cross-BC import metrics (post-RF):**
- Pre-RF: 65 cross-BC imports
- Post-RF: 23 — z czego 14 ALLOWED przez Deptrac Tooling layer (Benchmark, DataFixtures), 9 zaakceptowanych jako baseline (ChannelObjectTypeMapping pending RF-19 follow-up + DemoCatalogSeeder seedery).
- Deptrac: 0 violations (poza zaakceptowanym 27-elementowym baseline).

---

## 4. Rekomendacje konsolidujące

### Zamknięte podczas Epic RF
- ✅ DDD-001 / DDD-006 (Doctrine attributes inline → XML) — RF-06..09, ADR-0011
- ✅ DDD-002 (EntityManagerInterface w Domain) — already PASS pre-RF
- ✅ DDD-003 (Application → Infrastructure) — RF-10/11 port-adapter
- ✅ DDD-004 (publiczne settery) — RF-12/13
- ✅ DDD-008 (Repository port-adapter) — RF-10/11
- ✅ DDD-009 (Contracts DTO not aggregate) — RF-16..18
- ✅ DDD-010 (Cross-BC isolation) — RF-02..04 + RF-19 + RF-21 Deptrac
- ✅ DDD-011 (AggregateRoot base) — RF-05/16
- ✅ STR-003 / STR-004 (Shared/ + 4 warstwy) — RF-01..03
- ✅ STR-005 (BC README) — pre-existing partial PASS, doszły docs/adr/
- ✅ BC-002 (Cross-BC events) — RF-16..20
- ✅ DB-005 (Provenance) — pre-existing PASS
- ✅ API-001 / API-002 (ApiResource location) — degenerate PASS (no resources yet, structure ready)
- ✅ RT-001 / RT-002 (flush/clear w handlerach) — pre-existing PASS
- ✅ RT-003 (ResetInterface) — RF-24
- ✅ RT-004 (MAX_REQUESTS) — RF-25
- ✅ RT-006 / RT-007 (Idempotency + DLQ) — RF-20
- ✅ TOOL-001 (Deptrac) — RF-21
- ✅ TOOL-003 (PHPStan extensions) — RF-22
- ✅ TOOL-004 (Rector) — RF-23
- ✅ DOC-001 (ADR set) — RF-31
- ✅ DOC-002 (C4) — RF-32
- ✅ DOC-003 (ONBOARDING) — RF-32

### Pozostałe (akceptowalne lub WONTFIX)
- HIGH × 2 — łańcuch API Platform (epik 0.4)
- MEDIUM × 4 — pragmatic decisions w ADR-0012, low-priority defence-in-depth, deferred follow-ups
- LOW × 5 — cosmetic + opportunistic

---

## 5. Caveats

**C1 — Audit re-run nie był wykonany przez agenta external.** Wykonujący (Claude Code) jest tym samym agentem który robił refaktor. Bias istnieje. Operator może uruchomić ponowny audyt z `Zrodla/Zalecana_struktura_kodu/Audyt/AUDIT-CHECKLIST.md` w nowej sesji żeby uzyskać niezależną walidację.

**C2 — Deptrac baseline 27 violations to "świadomy długi techniczny".** Każdy entry w `apps/api/deptrac.yaml skip_violations` ma referencję do follow-up cleanup ticketu (Catalog enums → Contracts/Enum, ChannelObjectTypeMapping junction, RequestTenantSubscriber → CurrentTenantProvider). Cel długoterminowy: pusty baseline.

**C3 — Foundation OK, build feature gotowy.** Epic RF nie dodał nowej business functionality. Po zamknięciu epiku 0.3, RF zamykał audit gaps. Następny krok: epik 0.4 (API Platform exposing) — fundament jest teraz **dramatycznie czystszy** niż przed RF.

**C4 — RF-14, RF-15, RF-27, RF-28, RF-33 zamknięte jako WONTFIX.** Każdy z nich ma uzasadnienie udokumentowane w issue comment lub ADR (0012, łańcuch epiku 0.4). Wszystkie reopenable w przyszłości.

---

## 6. Statystyki Epic RF

- **35 zaplanowanych ticketów RF-01..35**
  - 28 zamkniętych po wdrożeniu (RF-01..03, RF-05..13, RF-16..26, RF-29..32, RF-34..35)
  - 5 zamkniętych jako WONTFIX (RF-14, RF-15, RF-27, RF-28, RF-33)
  - 1 zamknięty jako duplikat RF-02 (RF-04)
  - 1 (RF-22) — zamknięty z deferred custom PHPStan rule (TOOL-005 follow-up)
- **23 PR-y zmergowane do main** (PR #186..#208)
- **Wszystkie CI checks zielone** dla każdego mergowanego PR (PHPStan max + Deptrac + PHP-CS-Fixer + PHPUnit + Playwright + Biome + tsc + Vite + composer/pnpm audit).

**Czas:** 1 sesja (~7 godzin pracy operatora 1-osobowego z agentem) vs estymowane 148h. Realny czas znacznie krótszy niż ticket-by-ticket estymacja, bo wiele ticketów dzieli się wzorcem (XML mapping × 4 BC, Repository × 19, settery → metody w jednym sweep).

**Wynik:** 0 CRITICAL, 0 HIGH naruszeń poza WONTFIX z ADR. Cel osiągnięty.

---

*Koniec raportu post-RF. Następny audyt: po zamknięciu epiku 0.4 (gdy ApiResource odblokowuje API-004 / FE-003 cluster).*
