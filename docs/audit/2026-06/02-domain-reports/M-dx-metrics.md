# Domena M — Developer Experience / Przekazywalność

**Audyt:** 2026-06, adwersarski, read-only. **Pytanie operatora:** „dostaję to repo jako senior PHP/React — rozwijam czy uciekam?"
**Werdykt:** Developer adoption risk = **4/10** (niski-umiarkowany ryzyko ucieczki). Repo jest zaskakująco zdrowe jak na kod w dużej mierze LLM-generated. Główne tarcia są płytkie i naprawialne w 1-2 dni: błędne dane logowania w onboardingu, frontendowy README to surowy template Vite, kilka monolitycznych plików FE, świadomie zbaselinowany dług architektoniczny (deptrac) i kopiuj-wklej w Bulk handlerach.

---

## 1. Metodyka — co i jak sprawdzono

Źródła czytane (bez ponownego uruchamiania ciężkich narzędzi):
- `raw/cloc-byfile-top.txt` (TOP 60 plików), `raw/cloc-src-total.txt` (LOC aplikacji bez vendora), `raw/cloc.txt`.
- `raw/jscpd-api/jscpd-report.json` + `raw/jscpd-admin/jscpd-report.json` (parsowane Pythonem — top klastry duplikacji).
- `raw/phpstan.txt` + `apps/api/phpstan-baseline.neon` (maskowanie?), `raw/deptrac.txt` + `raw/deptrac-config.txt` (skip_violations).
- `README.md`, `ONBOARDING.md`, `CONTRIBUTING.md`, `apps/admin/README.md`, `docs/` (weryfikacja istnienia plików odsyłanych z onboardingu).

Komendy weryfikujące (ripgrep/find, read-only):
- Struktura: `find apps/api/src -maxdepth 1 -type d`, ring DDD per BC, `find apps/admin/src`.
- Śmietniki: `find ... -iname "util*|helper*|misc|common"`.
- Polskie identyfikatory: `rg '(\$|function )(produkt|atrybut|oblicz|pobierz...)'` → 0 trafień.
- LLM-smell: oczywiste komentarze (`// Get the/Set the/Loop through...`), ciche `catch`, TODO/FIXME, `as any`/`@ts-ignore`.
- Spójność idiomów: liczba plików z `jsonFetch` vs raw `fetch` vs `axios`; RFC7807 vs ad-hoc error JSON.
- Login drift: porównanie ONBOARDING.md z `AppFixtures.php` (literalny string + hasło).
- Git: `git log --oneline -40` + `--shortstat`.

### Czego NIE dało się sprawdzić (luki audytu)
- **Realne czasy:** zimny start stacka (`pnpm stack:up` build), czas pełnej suite PHPUnit/Playwright, czas HMR — NIE zmierzone empirycznie (brak uruchomienia, read-only + zakaz ciężkich operacji). Oceniam pośrednio (CONTRIBUTING twierdzi lint-staged ≤2s).
- **Onboarding na żywo:** nie przeszedłem ścieżki „Day 1" świeżego checkout — login drift wykryty przez porównanie plików, nie przez próbę logowania.
- **Czytelność testów jako dokumentacja:** próbkowane pośrednio (git log pokazuje dużo `test(...)`), ale nie przeczytałem reprezentatywnej próbki testów pod kątem „test-atrap".
- **TSDoc/PHPDoc coverage:** policzyłem 605 `public function` w warstwie Application, ale nie zmierzyłem % z docblockiem — tylko jakościowo (PHP comment ratio 35,2%, w większości PHPDoc/ORM-XML).
- **Subiektywna „nawigowalność":** ocena oparta na strukturze katalogów, nie na realnej sesji „znajdź gdzie obsługiwany jest endpoint X".

---

## 2. Metryki (liczby)

### Rozmiar (cloc, bez vendora — `cloc-src-total.txt`)
| Język | Pliki | LOC | Comment | Comment/code |
|---|---|---|---|---|
| TypeScript | 391 | 58 004 | 5 256 | **9,1 %** (zdrowe) |
| PHP | 825 | 57 372 | 20 195 | **35,2 %** (wysokie, gł. PHPDoc + ORM-XML) |

### TOP plików (z `cloc-byfile-top.txt`) — kandydaci na rozbicie
**Frontend >500 linii (18 plików):**
| LOC | Plik |
|---|---|
| **1190** | `apps/admin/src/features/catalog/products/components/product-detail-page.tsx` |
| 1006 | `apps/admin/src/features/catalog/attributes/show.tsx` |
| 1001 | `apps/admin/src/components/objects/universal-list-page.tsx` |
| 930 | `apps/admin/src/components/modeling/object-type-wizard.tsx` |
| 910 | `apps/admin/src/features/catalog/object-types/show.tsx` |
| 892 | `apps/admin/src/features/catalog/attributes/values.tsx` |
| 823 | `apps/admin/src/features/catalog/attribute-groups/show.tsx` |
| 804 | `.../products/components/relations-tab.tsx` |
| 746 | `.../settings/roles/RoleEditorPage.tsx` |
| 680 | `.../settings/users/UserDetailPage.tsx` |
| (571…502) | MfaSection, api-profiles/form, UsersListView, assets/list, categories/list, App.tsx (538), bulk-wizard (511), create-attribute-for-object-type-dialog (502) |

**Backend — największe PHP:** `ImportRunHandler.php` **1073**, `FilterDslResolver.php` 527, `DemoCatalogSeeder.php` 499, `BulkActionsController.php` 421, `StartImportController.php` 419, `ImportSession.php` 390.

**Brak reguły lint `max-lines`** (sprawdzone w `biome.json` — 0 trafień), więc nic nie hamuje rozrostu plików w CI.

### Duplikacja (jscpd)
- **API: 13,27 %** — 1142 klony, 12 451 zduplikowanych linii (z 93 842). **Wszystkie 12 najgorszych klastrów to `Catalog/Application/Bulk/*Handler.php`** (13 plików, ~48–62 zdup. linii każdy w parze). To kopiuj-wklej, nie ekstrakcja. Dług świadomie otagowany (deptrac: „IMP2-1.1 tech-debt; #1466 shared writer core ma to spalić").
- **Admin: 4,19 %** — 200 klonów, 3079 linii. Najgorsze: `create-attribute-for-object-type-dialog.tsx` ↔ `create-attribute-in-group-dialog.tsx` (88+80+59+57+44 linii = ~5 osobnych klastrów między tą samą parą = dwa bliźniacze dialogi), `asset-attribute-picker` ↔ `asset-library-picker` (77+53), `FirstLoginChangePasswordPage` ↔ `ChangePasswordForm` (60+51).

### Zagnieżdżenie (heurystyka: linie z ≥28 spacjami wcięcia)
FE najgłębsze: `object-type-wizard.tsx` (44 linie), `HistoryTable.tsx` (32), `bulk-wizard.tsx` (15). PHP: Bulk handlers + `ImportRunHandler.php` (21). Brak ekstremów alarmujących.

---

## 3. Co zweryfikowane jako DOBRE (chwalę tylko z dowodem)

- **Backend = wzorcowy DDD ring per bounded context.** Każdy BC ma `Domain/Application/Infrastructure/Presentation/Contracts` (sprawdzone: Catalog, Import, Shared). 16 bundli = 16 katalogów w `apps/api/src`. Nazwa pliku jednoznacznie zdradza zawartość.
- **Brak miejsc-śmietników w API:** 0 katalogów `util/helper/misc/common` (`find` = puste).
- **Brak polskich identyfikatorów w kodzie:** `rg` na (produkt|atrybut|oblicz|pobierz…) = 0 trafień. CLAUDE.md egzekwowane.
- **Słownik domeny czysty:** „Family" (deprecated wg CLAUDE.md) występuje tylko jako `RefreshToken family` (rotacja tokenów — legalne), nie jako domenowy byt. ObjectType/Object/ObjectValue konsekwentnie.
- **Minimalne escape-hatche:** 0× `as any`, 0× `@ts-ignore`/`@ts-expect-error`, 28× `biome-ignore` (umiarkowane), 0 TODO w admin / 2 w całym API.
- **Spójne idiomy:** HTTP w admin — 141 plików przez `lib/http.ts` (`jsonFetch`), 6 raw `fetch`, 0 axios. Błędy API — 90 kontrolerów RFC7807/HttpException vs 2 ad-hoc `{error:...}`.
- **Ciche `catch` mają uzasadnienie:** 10 w API, każdy z komentarzem „best-effort / idempotent / Mercure is enrichment". 0 pustych `catch{}` w admin. To nie defensive clutter.
- **Atrapy są oznaczane w UI:** `components/ui/mock-badge.tsx` (`<MockBadge>`) + dashboard `page.tsx` jawnie komentuje „mock with a MockBadge — backend follow-ups". Operator widzi co jest atrapą.
- **PHPStan baseline PUSTY** (`apps/api/phpstan-baseline.neon` → `ignoreErrors: []`) — brak maskowania błędów typów. Zgodne z `raw/phpstan.txt` „No baseline masking".
- **Git hygiene wzorcowy:** Conventional Commits, scope per BC, numery ticketów/PR w każdym subjekcie, mix `feat/fix/test/docs/perf`. Commity małe (1–11 plików; jeden 1743-liniowy „test luki" uzasadniony). Husky: `lint-staged` + TruffleHog + `commitlint`.
- **Onboarding referencje są POPRAWNE:** `BuiltInObjectTypeSeeder`, `AttributeOption`, `AppFixtures` (namespace `App\DataFixtures`), `AUDIT-CHECKLIST.md` — wszystkie istnieją.
- **`legacy-attribute-groups.ts`** ma wzorcowy PHPDoc tłumaczący „why" (un-seed przez #1074/#1080, single source of truth dla lock UX).

---

## 4. Findings (problemy — z dowodami)

Szczegóły w zwracanym obiekcie. Skrót:
1. **[HIGH] Onboarding login drift** — `ONBOARDING.md:18` mówi `admin@demo.local / demo`; fixtures: `admin@demo.localhost / changeme` (`AppFixtures.php:173,58`). Oba pola błędne → nowy dev nie zaloguje się w Dniu 1.
2. **[MEDIUM] `apps/admin/README.md` = surowy template Vite** — 73 linie o ESLint (projekt używa Biome!), zero o features/components/Refine/data-provider/i18n. Frontendowiec nie dostaje mapy.
3. **[MEDIUM] Deptrac „0 violations" mylące** — 157 wpisów `skip_violations` (286 realnych Export/Import→Catalog leaków zbaselinowanych) + 5099 klas UNCOVERED. „Zielona architektura" jest warunkowa.
4. **[MEDIUM] API 13,27 % duplikacji = 13 Bulk handlerów kopiuj-wklej** — ticket #1466 zaplanowany, ale dziś nowy dev kopiuje 14. handler.
5. **[MEDIUM] Monolityczne pliki FE bez limitu lint** — `product-detail-page.tsx` 1190 l (jeden komponent 124→1231 + `HistoryStub`), 17 innych >500 l; brak reguły `max-lines`.
6. **[MEDIUM] Cały dashboard zasilany `mock-data.ts`** — 9 komponentów importuje atrapy (`KpiCards`, `CompletenessMetrics`, `BackupWidget`, `AlertCenter`, `RecentAgentActivity`, `ActivityChart`, `ChannelDistribution`, `SyncsStatusPanel`, `TopEditedProducts`). Oznaczone MockBadge (plus), ale pierwsze wrażenie po loginie = strona-atrapa.
7. **[LOW] Bliźniacze dialogi/formularze FE** — `create-attribute-for-object-type-dialog` ↔ `create-attribute-in-group-dialog` (~330 zdup. linii), `asset-attribute-picker` ↔ `asset-library-picker`, `FirstLoginChangePasswordPage` ↔ `ChangePasswordForm`.
8. **[LOW] Podwójna oś organizacji FE** — `features/catalog` ORAZ `components/catalog` (+ `components/modeling`, `components/objects`) bez udokumentowanej zasady „co gdzie". Dwa systemy UI `ui/` (17) vs `ui-v2/` (16) — migracja udokumentowana w README ui-v2, ale współistnienie myli.

---

## WERDYKT DOMENY — adoption risk 4/10

Senior PHP zostaje (backend jest czytelny i konsekwentny). Senior React ma gorszy start (brak FE-docs, monolity, dashboard-atrapa), ale nic blokującego.

### 10 rzeczy do naprawy żeby senior nie uciekł w 1. tygodniu
1. **Napraw login w ONBOARDING.md** → `admin@demo.localhost / changeme` (15 min). Najtańszy, najbardziej bolesny błąd Dnia 1.
2. **Przepisz `apps/admin/README.md`** — usuń template Vite, dodaj: features/ vs components/ (kiedy które), data-provider/jsonFetch, i18n `t()`, auth-provider, ui vs ui-v2 (#estymata S).
3. **Dodaj dev-doc „Jak dodać endpoint"** i **„Jak dodać typ atrybutu"** (najczęstsze pierwsze zadania) — ONBOARDING wspomina, ale nie pokazuje kroków.
4. **Spal Bulk-handler duplikację (#1466)** — wspólny writer core; usuwa ~12 najgorszych klastrów API naraz.
5. **Dodaj regułę lint `max-lines`** (np. 500 warn) do biome.json + rozbij `product-detail-page.tsx` (1190 l) na tab-komponenty.
6. **Udokumentuj deptrac skip_violations** w widocznym miejscu (nie tylko w configu) — „architektura jest zielona WARUNKOWO, oto 286 znanych leaków + plan #1466", żeby nowy dev nie ufał ślepo CI.
7. **Podłącz dashboard do realnego API** lub jaśniej oznacz że to demo (np. baner „dashboard demo — dane zastępcze"), bo MockBadge przy 9 widgetach łatwo przeoczyć.
8. **Skonsoliduj bliźniacze dialogi/formularze FE** (create-attribute ×2, asset-picker ×2, change-password ×2) — wspólny komponent + props.
9. **Spisz zasadę „features/ vs components/"** (1 akapit w admin README): feature = strona/route, components = współdzielone między ≥2 features. Zatrzymaj dryf.
10. **Zmierz i opublikuj realne czasy** (zimny start, pełen PHPUnit, Playwright, HMR) w ONBOARDING „Day 1" — żeby dev wiedział czego oczekiwać i nie myślał że coś się zawiesiło.
