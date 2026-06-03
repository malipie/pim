# Plan audytu code review + checklista (read-only)

**Data:** 2026-05-27  
**Tryb pracy:** tylko przegląd i dokumentacja  
**Bez automatycznego wdrażania:** TAK

## 1. Cel audytu

Celem audytu jest ocena jakości kodu i organizacji repozytorium PIM, ze szczególnym uwzględnieniem:
- jakości architektury i zgodności z zasadami projektu,
- bezpieczeństwa i multi-tenancy,
- jakości testów i bramek CI,
- struktury katalogów i plików oraz ich utrzymaniowości.

## 2. Zakres audytu

- `apps/api`
- `apps/admin`
- `packages/shared-types`
- `docs/`
- `Project Plan/`
- `scripts/`
- `tools/`
- pliki konfiguracyjne root (`package.json`, `turbo.json`, `biome.json`, `pnpm-workspace.yaml`)

## 3. Zasady wykonania

- Audyt ma charakter **read-only**.
- Brak zmian wdrożeniowych, brak auto-fixów, brak auto-merge.
- Każde znalezisko musi zawierać:
  - lokalizację (plik/folder),
  - opis ryzyka,
  - rekomendację,
  - priorytet (`P0`, `P1`, `P2`).

## 4. Priorytety i statusy

**Priorytety**
- `P0` — blokujące (security, data isolation, broken quality gates)
- `P1` — ważne (stabilność, utrzymanie, spójność)
- `P2` — usprawnienia i dług techniczny

**Statusy checklisty**
- `TODO`
- `OK`
- `UWAGA`
- `BŁĄD`

---

## 5. Audit Sheet: Code Review + Repo Structure

| Check | Status | Evidence (plik/folder + notatka) | Owner | Priority |
|---|---|---|---|---|
| Root: monorepo structure (`apps/`, `packages/`, `docs/`, `scripts/`, `tools/`) jest spójna | TODO |  |  | P1 |
| Root: brak zbędnych katalogów/artefaktów tymczasowych w repo | TODO |  |  | P1 |
| Root: konfiguracje (`package.json`, `turbo.json`, `biome.json`, `pnpm-workspace.yaml`) są spójne | TODO |  |  | P0 |
| Root: brak nieuzasadnionej duplikacji konfiguracji między root i appkami | TODO |  |  | P1 |
| Root: nazewnictwo plików/folderów kodu jest spójne i angielskie | TODO |  |  | P2 |
| API: struktura bounded contexts/bundles zgodna z architekturą | TODO |  |  | P0 |
| API: brak mieszania warstw (Domain/Application/Infrastructure/UI) | TODO |  |  | P1 |
| API: endpointy mają spójną walidację i obsługę błędów | TODO |  |  | P0 |
| API: multi-tenancy (`tenant_id`, filtry, izolacja) jest konsekwentnie egzekwowana | TODO |  |  | P0 |
| API: batch/memory safety (`flush/clear`, brak ryzyk OOM) | TODO |  |  | P0 |
| API: migracje są czytelne, odwracalne i bez „quick-fix SQL” bez uzasadnienia | TODO |  |  | P1 |
| API: testy integracyjne pokrywają krytyczne flow auth/permissions | TODO |  |  | P0 |
| Admin: struktura feature’ów i komponentów czytelna (brak „god components”) | TODO |  |  | P1 |
| Admin: stany UI loading/empty/error są obecne i spójne | TODO |  |  | P1 |
| Admin: i18n — brak hardcoded user-facing stringów, użycie `t()` | TODO |  |  | P1 |
| Admin: permission gates / RBAC poprawnie ograniczają akcje i pola | TODO |  |  | P0 |
| Admin: błędy API są obsługiwane i widoczne dla użytkownika | TODO |  |  | P1 |
| Admin: E2E obejmuje krytyczne widoki/ścieżki po zmianach | TODO |  |  | P0 |
| Shared-types: zgodność typów z OpenAPI i backend DTO | TODO |  |  | P0 |
| Shared-types: brak nieuzasadnionych `any`/unsafe castów | TODO |  |  | P1 |
| Docs: `docs/` i `Project Plan/` zgodne z rzeczywistą implementacją | TODO |  |  | P1 |
| Docs: status „done vs in-progress/substrate” jest jednoznaczny | TODO |  |  | P1 |
| Scripts/Tools: skrypty mają aktualny cel i opis użycia | TODO |  |  | P2 |
| Scripts/Tools: brak martwych/nieużywanych skryptów | TODO |  |  | P2 |
| Scripts/Tools: backup/restore/test-infra skrypty są aktualne i weryfikowalne | TODO |  |  | P1 |
| Quality: lint/static analysis/test/build przechodzą dla aktualnego stanu | TODO |  |  | P0 |
| Quality: dependency health (lockfile, drift, audyty) bez krytycznych ryzyk | TODO |  |  | P1 |
| Maintainability: hotspoty (duże pliki/złożoność/duplikacje) zidentyfikowane | TODO |  |  | P2 |
| Security: brak oczywistych naruszeń (sekrety w kodzie, bypass auth, data leaks) | TODO |  |  | P0 |
| Smoke-test readiness: user-facing flow ma dowody testu manualnego (gdy claim „działa”) | TODO |  |  | P0 |

---

## 6. Rejestr znalezisk

| ID | Obszar | Plik/folder | Opis problemu | Severity | Rekomendacja | Status |
|---|---|---|---|---|---|---|
| F-001 |  |  |  | P0/P1/P2 |  | Open |
| F-002 |  |  |  | P0/P1/P2 |  | Open |
| F-003 |  |  |  | P0/P1/P2 |  | Open |

---

## 7. Podsumowanie końcowe audytu

- **Overall status:** Green / Yellow / Red  
- **P0 (blokujące):** X  
- **P1:** X  
- **P2:** X  
- **Decyzja:** Approve with changes / Changes required / Re-audit needed

---

## 8. Scope boundaries

**W zakresie**
- przegląd kodu,
- przegląd struktury katalogów i plików,
- dokumentacja znalezisk i rekomendacji.

**Poza zakresem**
- automatyczne wdrażanie,
- automatyczny refaktor,
- automatyczne mergowanie zmian.
