# Executive summary — audyt połówkowy PIM przed SaaS (2026-06-16)

> Adwersarski audyt principal-security/staff-architect. Tryb READ-ONLY na żywym lokalnym stacku (`https://pim.localhost`, `APP_ENV=dev`).
> Metoda: 13 domen (A–M) + onboarding przez subagenty, narzędzia statyczne (PHPStan max, Deptrac, semgrep, gitleaks pełna historia, cloc, jscpd), inspekcja żywej bazy (psql) oraz **empiryczna matryca 2-tenant** na żywym stacku. Pełne dowody: `01-findings.md` + `02-domain-reports/` + `raw/` + `probes/`.

## Liczby

| Severity | Liczba |
|---|---|
| CRITICAL | 5 |
| HIGH | 20 |
| MEDIUM | 38 |
| LOW | 18 |
| **Razem** | **81** |

5 CRITICAL: AUD-001 Mercure, AUD-002 RLS-martwy, AUD-004 Meili filter-key, AUD-005 sekrety w VCS, AUD-007 token_dev_only.

---

## WERDYKT (a) — ścieżka do SaaS: **NO-GO**

**Nie wolno uruchamiać systemu jako SaaS ani robić demo z realnymi danymi dwóch podmiotów w obecnym stanie.** Decyduje pięć findings klasy CRITICAL, z których trzy to **empirycznie potwierdzone** wektory cross-tenant/przejęcia konta:

1. **Mercure SSE — cross-tenant leak w czasie rzeczywistym (confirmed).** Hub działa w trybie `anonymous`, eventy publikowane bez `private`, na topikach bez prefiksu tenanta. Anonimowy `curl` bez konta odebrał realne zdarzenia tenanta demo (`object.enabled_changed`, objectId RPT-1). Każdy klient sieciowy nasłuchuje aktywności wszystkich najemców.
2. **`token_dev_only` — account takeover (confirmed).** `POST /api/auth/password-reset/request {email}` zwraca w body 64-znakowy surowy token resetu bezwarunkowo (brak guardu env). Znając czyjkolwiek email → przejęcie konta. To samo w invitation.
3. **Meilisearch filter injection przez klucz filtra — cross-tenant read (confirmed).** Niewalidowany klucz `?filter[parentId IS NULL OR tenantId]=<id-B>` znosi `AND`-scope tenanta; na żywym Meili odczyt dokumentów cudzego tenanta.
4. **RLS jest dekoracyjny — zero defence-in-depth.** Aplikacja łączy się jako rola `pim` = `rolsuper` + `rolbypassrls` + owner wszystkich tabel; 0 tabel z `FORCE`. Izolacja wisi WYŁĄCZNIE na warstwie aplikacji. Łamie własną regułę architektury (`01-architektura-pim.md:867`).
5. **Sekrety w trackowanym `.env`.** `APP_BYOK_KEY_V1` (master key szyfrujący klucze BYOK klientów), `JWT_PASSPHRASE`, `MERCURE_JWT_SECRET` w gicie od pierwszego commita, `.gitignore` jawnie je force-trackuje. Wymaga rotacji.

**Ważny pozytyw (uczciwie):** **rdzeń izolacji danych domenowych DZIAŁA.** Empiryczna matryca demo↔acme dowiodła, że Doctrine `TenantFilter` izoluje przez REST nawet dla super-admina — kolekcje pokazują tylko własne dane, cross-read po ID = 404 w obie strony, nagłówek `X-Tenant-Id` ignorowany. To NIE jest „fundamentalnie zepsuta izolacja" — to **solidny rdzeń app-layer z dziurami na obrzeżach (Mercure, Meili, asset-preview), zerowym defence-in-depth (RLS) i brakami operacyjnymi SaaS**. Wszystkie CRITICAL są naprawialne; szacunek Wave 0 = ~5-8 dni roboczych.

**Poza CRITICAL, blokery operacyjne SaaS (HIGH):** backup martwy od ~49 dni + MinIO bez backupu/wersjonowania (SPOF) + restore-test wyłączony; offboarding tenanta niewykonalny (24× FK RESTRICT, brak kaskady MinIO) → **RODO art. 17 niespełnione**; attribute-level permissions nieegzekwowane na read/PATCH/export; skala 50k zagrożona (brak indeksu GIN na `attributes_indexed` i GiST na `objects.path` — usunięte regresją migracji; eksport non-streaming + N+1 = OOM); auth happy-path i 57/100 E2E martwe w CI, custom role builder bez testów.

### Co MUSI być naprawione przed jakimkolwiek pilotem z realnymi danymi
**Wave 0 (wszystkie 9 pozycji, `03-fix-plan.md`)** — bezwarunkowo. Dodatkowo z Wave 1: osobna rola DB `pim_app` + `FORCE RLS` (z uprzednim ujednoliceniem GUC), działający backup + odtwarzalność MinIO, wykonalny offboarding (RODO). Bez tego pilot z realnym klientem jest nieodpowiedzialny.

---

## WERDYKT (b) — developer adoption risk: **4/10** (niski-umiarkowany)

**Repo można oddać seniorom bez wstydu — po naprawie onboardingu Day-1.** Backend jest zaskakująco zdrowy jak na kod w dużej mierze LLM-generated: wzorcowy ring DDD per bounded context, **PHPStan level max z 0 błędów i PUSTYM baseline** (brak ukrytego długu), 0 polskich identyfikatorów, słownik domeny czysty (ObjectType, nie Family), niski dług deklaratywny (4 TODO w 57k LOC PHP), git hygiene wzorcowy (Conventional Commits, atomowe PR).

**Co odstrasza (naprawialne w 1-2 dni dla największych):**
- **Onboarding kłamie** — `ONBOARDING.md` podaje błędne dane logowania (`admin@demo.local`/`demo` zamiast `admin@demo.localhost`/`changeme`) i pomija `audit:schema:update` → nowy dev nie zaloguje się w Dniu 1 i dostaje 500 na audytowanych encjach. Kanoniczny `pim:db:reset` nieudokumentowany. `shared-types generate` zepsuty (zły scheme + 404).
- **Frontend słabszy:** `apps/admin/README.md` to surowy template Vite (zero o features/Refine/i18n); monolity (product-detail-page 1190 linii, 17 plików >500) bez reguły `max-lines`; dashboard w całości na mock-data; 13.27% duplikacji (Bulk handlers kopiuj-wklej).
- **Dryf nieudokumentowany:** „wszystko przez API Platform" (CLAUDE.md) faktycznie odwrócone (117 custom `#[Route]` vs 2 `#[ApiResource]`) bez ADR; 3 wzorce fetch w FE; deptrac „zielony" maskuje 286 przecieków warstw.

Senior PHP zostaje. Senior React ma gorszy start, ale nic blokującego. 10 konkretnych napraw — `02-domain-reports/M-dx-metrics.md`.

---

## TOP 10 ryzyk

| # | Ryzyko | Sev | Conf | AUD |
|---|---|---|---|---|
| 1 | Mercure: anonimowy cross-tenant SSE leak (potwierdzony) | CRITICAL | confirmed | 001 |
| 2 | token_dev_only → account takeover znając email (potwierdzony) | CRITICAL | confirmed | 007 |
| 3 | Meili filter-key → cross-tenant read (potwierdzony) | CRITICAL | confirmed | 004 |
| 4 | RLS martwy: app=superuser+bypassrls, zero defence-in-depth | CRITICAL | confirmed | 002 |
| 5 | Sekrety w trackowanym `.env` (BYOK master key, JWT) | CRITICAL | confirmed | 005 |
| 6 | Backup martwy 49 dni + MinIO SPOF + restore-test off | HIGH | confirmed | 017/018/021 |
| 7 | Offboarding/RODO niewykonalny (24×RESTRICT + brak kaskady MinIO) | HIGH | confirmed | 019/020 |
| 8 | Attribute-level permissions nieegzekwowane (read/PATCH/export) | HIGH | confirmed | 008 |
| 9 | Skala 50k zagrożona: brak GIN/GiST + eksport OOM/N+1 | HIGH | confirmed | 013/014/015/016 |
| 10 | Testy: auth happy-path + 57/100 E2E martwe, role builder bez testów | HIGH | confirmed | 022/023/024 |

---

## NIEZBADANE (luki audytu — uczciwie)

- **Liczby p50/p95 wydajności na 50k SKU** — baza dev pusta (0 obiektów domenowych); findings F (brak GIN/GiST) oparte na EXPLAIN pustej tabeli + analizie kodu + regresie migracji, NIE na pomiarze na wolumenie. Komendy benchmark gotowe (`F-performance-static.md`), nieuruchomione (ochrona dev DB + czas).
- **Worker memory pod realnym importem/eksportem 50k** — twierdzenia E (AbstractBatchHandler, OOM eksportu) z analizy kodu, nie zmierzony RSS. `pim:benchmark:bulk-import --count=50000` nieuruchomiony.
- **B-01 write-path** (suspend/delete cudzego tenanta) — nietestowany (guardrail: zakaz mutacji `/api/admin/tenants/*`); ryzyko probable z analizy kodu.
- **Asset preview — wyciek bajtów end-to-end** — wektor potwierdzony, ale brak fizycznych blobów w dev storage (osierocone rekordy) uniemożliwił odtworzenie pobrania cudzych bajtów.
- **Mercure — pozostałe publishery** (export/import progress, permission invalidation) — potwierdzony leak tylko dla Catalog object events; reszta do domknięcia.
- **Zachowanie prod** (`APP_DEBUG=0`, nagłówki bezpieczeństwa prod) — brak uruchomionego prod env; prod Caddyfile budowany w osobnym pipeline poza repo; analiza wyłącznie statyczna `docker-compose.prod.yml`.
- **Migrate→rollback→migrate empiryczny** — zakaz mutacji schematu; ocena `down()` migracji destrukcyjnych statyczna z kodu.
- **Branch protection / required checks na GitHub** — ustawienie poza repo (wymaga `gh api`); CI gates są twarde lokalnie (brak soft-fail), ale „required to merge" niezweryfikowane.
- **GraphQL surface** — audyt skupiony na REST; API Platform stosuje ten sam filtr, custom resolvery niesprawdzone.
- **Pełna macierz cross-read dla wszystkich typów acme→demo** — ograniczona ubóstwem danych tenanta acme (3 obiekty, 0 assetów/kanałów); przetestowano reprezentatywny przekrój (izolacja czysta).
- **2 pliki TS niesparsowane przez semgrep** (`object-types/list.tsx`, `show.tsx` — `abstract?:`) + 2 timeouty → luka pokrycia statycznej analizy FE.

---

## Następne kroki

1. Założyć issues z `03-fix-plan.md` (Wave 0 jako blocker-label przed jakimkolwiek demo z realnymi danymi).
2. Domknąć empirycznie luki NIEZBADANE w dedykowanej sesji: benchmark 50k (perf/memory), pozostałe topiki Mercure, B-01 write na tenancie testowym.
3. Po naprawie Wave 0 + W1-izolacja/backup/offboarding — re-audyt izolacji (pen-test) przed pierwszym pilotem.
