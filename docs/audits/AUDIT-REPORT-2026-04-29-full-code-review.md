# AUDIT REPORT — 2026-04-29 (Full Code Review)

## Zakres i metodologia
- **Zakres:** wszystkie foldery związane z kodem w repozytorium (`apps/admin`, `api`, `packages/shared-types`, `docker`, `scripts`, `tools` + powiązane konfiguracje).
- **Perspektywa review:** architektura, DDD boundaries, bezpieczeństwo, wydajność, testy, tooling/CI, zgodność z zasadami projektu.
- **Forma:** findings + severity + rekomendacje + next steps.
- **Tryb pracy:** wyłącznie read-only, bez zmian w kodzie.

---

## Pozytywne obserwacje

1. **DDD i separacja kontekstów**
   - Czytelny podział bounded contexts (Catalog/Channel/Asset/Identity/Integration/Agent/ApiConfigurator).
   - Egzekwowanie granic przez deptrac i strukturę katalogów.

2. **Fundament multi-tenancy**
   - Spójny wzorzec `tenant_id` + filter/listener + audyt tenantowy.
   - Dobre przygotowanie pod RLS jako „defence in depth”.

3. **Worker memory discipline (FrankenPHP)**
   - Obecny wzorzec batch (`flush` + `clear`) i evidence, że temat pamięci jest traktowany serio.

4. **Bezpieczeństwo auth**
   - Sensowne praktyki przy JWT/refresh, rotacji i konfiguracji cookies.

5. **Single-origin przez Caddy**
   - Zgodność z założeniem „bez CORS” i routingiem `/api`, `/.well-known/mercure`, frontend.

6. **Jakość i testy**
   - Silne quality gates (PHPStan max, Biome strict, testy backend + E2E).
   - Dobra automatyzacja walidacji regresji.

7. **API-first + i18n**
   - Dobre osadzenie API Platform i integracji frontendu z API.
   - Spójny kierunek i18n po stronie admina.

---

## Findings

### CRITICAL
Brak znalezisk klasy **Critical**.

### HIGH

#### HIGH-001 — Niespójne pokrycie testowe dla feature flag `custom object types`
- **Severity:** High
- **Evidence:** guardy i walidacje istnieją na kilku warstwach, ale brak jednego testu integracyjnego spinającego pełną ścieżkę API dla flagi OFF/ON.
- **Ryzyko:** regresja może przejść mimo poprawnych testów jednostkowych poszczególnych elementów.
- **Rekomendacja:** dodać testy integracyjne API (co najmniej scenariusz OFF → 403, ON → success).

#### HIGH-002 — Ryzyko wycieku kontekstu tenant w scenariuszach async/worker
- **Severity:** High
- **Evidence:** czyszczenie kontekstu działa poprawnie dla request lifecycle, ale async handlery wymagają jawnego i testowalnego rebindingu tenant.
- **Ryzyko:** błędna izolacja tenantów przy kolejkach/workerach.
- **Rekomendacja:** wymusić tenantId w wiadomościach async i rebinding na starcie handlera + testy funkcjonalne dla worker flow.

### MEDIUM

#### MEDIUM-001 — PHPStan ignores mogą ukrywać nieaktualne wyjątki
- **Severity:** Medium
- **Evidence:** konfiguracja sprzyja pozostawaniu „martwych” ignore rules.
- **Rekomendacja:** okresowo czyścić ignore i włączyć raportowanie niedopasowanych wpisów.

#### MEDIUM-002 — Bulk path wymaga mocniejszego fail-safe
- **Severity:** Medium
- **Evidence:** ścieżka bulk zależy od poprawnego ustawienia kontekstu; brak twardych bezpieczników przy dużych wolumenach.
- **Rekomendacja:** dodać guard/safety switch i test edge-case dla dużych importów.

#### MEDIUM-003 — Brak metryk czasu zapytań DB przy wyłączonym SQL logging
- **Severity:** Medium
- **Evidence:** logging w prod słusznie wyłączony, ale brak metryk p95/p99 utrudnia wczesne wykrycie degradacji.
- **Rekomendacja:** histogram query duration + alerting.

#### MEDIUM-004 — Caddy hardening: brak rate limit / timeout policy
- **Severity:** Medium
- **Evidence:** reverse proxy działa, ale bez pełnych bezpieczników na przeciążenie/slow clients.
- **Rekomendacja:** dodać limity, timeouty i health strategy.

#### MEDIUM-005 — Brak CSP headerów dla admin frontendu
- **Severity:** Medium
- **Evidence:** brak jawnej polityki CSP.
- **Rekomendacja:** wdrożyć CSP (docelowo nonce/hash strategy).

#### MEDIUM-006 — `/api/docs` publiczne w produkcji (domyślna ekspozycja)
- **Severity:** Medium
- **Evidence:** public access do dokumentacji API w runtime prod.
- **Rekomendacja:** wyłączyć Swagger UI w prod lub ograniczyć dostęp.

#### MEDIUM-007 — Brak jawnego routingu transportów Messenger
- **Severity:** Medium
- **Evidence:** implicit/default behavior może dawać różnice między środowiskami.
- **Rekomendacja:** jawny mapping transportów i routing wiadomości.

#### MEDIUM-008 — Brak wersjonowanych snapshotów OpenAPI w `docs/api-spec`
- **Severity:** Medium
- **Evidence:** folder istnieje, ale brak stabilnego procesu release snapshotów.
- **Rekomendacja:** dodać krok CI na tagach/release.

### LOW

1. **E2E część testów oznaczona jako `fixme` (zależności od kolejnych ticketów)** — akceptowalne, ale warto monitorować.
2. **Drobne ryzyka utrzymaniowe/naming edge-cases** — dobrze udokumentowane, niski priorytet.
3. **Incydentalne flake/różnice lokalnego środowiska** — obecnie nieblokujące, do obserwacji.

---

## Next steps (priorytetyzowane)

### P1 — najbliższe (blok jakościowy)
1. Dodać integracyjne testy API dla feature flag `custom object types` (HIGH-001).
2. Ustandaryzować tenant rebinding w async handlerach + test scenariusza worker (HIGH-002).

### P2 — stabilność i przewidywalność runtime
3. Wprowadzić jawny routing Messenger transportów (MEDIUM-007).
4. Dodać safety guard dla dużych bulk operacji (MEDIUM-002).
5. Wdrożyć metryki zapytań DB i alerting p95/p99 (MEDIUM-003).

### P3 — security hardening
6. Dodać rate limiting i timeout policy w Caddy (MEDIUM-004).
7. Wdrożyć CSP dla admina (MEDIUM-005).
8. Ograniczyć/wyłączyć publiczne `/api/docs` w produkcji (MEDIUM-006).

### P4 — governance/release quality
9. Uszczelnić utrzymanie PHPStan ignores (MEDIUM-001).
10. Dodać workflow CI generujący wersjonowane snapshoty OpenAPI (MEDIUM-008).

---

## Konkluzja
Projekt jest w **dobrym stanie jakościowym** i ma solidne fundamenty architektoniczne. Brak znalezisk krytycznych. Największe ryzyka dotyczą spójności zachowania w scenariuszach async/multi-tenant oraz domknięcia hardeningu bezpieczeństwa i release governance.

Najlepszy kolejny krok: domknąć **P1** przed intensyfikacją prac funkcjonalnych w kolejnym epiku.
