# Raport domenowy M1 — Onboarding empiryczny

Data: 2026-06-16
Audytor: subagent adwersarski (postawa „repo widziane pierwszy raz", ignoruję wiedzę kontekstową, opieram się wyłącznie na README / ONBOARDING.md / CONTRIBUTING.md i weryfikuję empirycznie).
Zakres: M1 — (a) setup od zera wg dokumentacji, (b) trace „małego feature end-to-end" (dodanie pola do encji `Channel`).
Tryb: READ-ONLY. Stack działał (`https://pim.localhost`), nie wykonywałem `stack:reset` / `down -v` / żadnych mutacji DB.

---

## 1. Metodyka — co i jak sprawdzono

Symulowałem onboarding nowego seniora PHP/React: czytałem dokumentację krok po kroku i dla KAŻDEJ komendy / ścieżki / pliku weryfikowałem czy istnieje i czy zadziałałaby. Tam gdzie stack żył — potwierdzałem empirycznie (curl, listing plików), nie tylko czytając kod.

Komendy i artefakty użyte jako dowód:
- Odczyt: `README.md`, `ONBOARDING.md`, `CONTRIBUTING.md`, `package.json`, `.env.example`, `apps/admin/package.json`, `packages/shared-types/package.json`, `turbo.json`, `docker-compose.yml`, `apps/api/Dockerfile`, `apps/api/docker-entrypoint.sh`, `apps/api/src/DataFixtures/AppFixtures.php`, `apps/api/src/Shared/Infrastructure/Maintenance/DatabaseResetCommand.php`, encja `Channel.php`, `Channel.xml` (API Platform Resource), `ChannelInput.php`.
- Weryfikacja istnienia komend / plików: `find`, `ls`, `rg`, `grep`.
- Empiryczne probe'y na żywym stacku:
  - Login: `curl -sk -X POST https://pim.localhost/api/auth/login -d '{"email":"admin@demo.localhost","password":"changeme"}'` → **HTTP 200 + JWT** (dowód niżej).
  - Endpoint zasobu: `curl -sk https://pim.localhost/api/channels -H "Authorization: Bearer <token>"` → **HTTP 200**, `[{"id":...,"code":"allegro","name":"Allegro"}]`.
  - Endpointy OpenAPI: pętla po `/api/docs.json`, `/api/docs.jsonopenapi`, `/api/docs.jsonld`, `/api/docs` (kody niżej).
  - Scheme test: `curl http://pim.localhost/api/docs.json` (plaintext) vs `https`.

Liczby ustalone:
- Setup wg README: 5 numerowanych kroków + sekcja E2E.
- Setup wg ONBOARDING.md „Day 1": 6 komend.
- Migracje w repo: **97** plików w `apps/api/migrations/`.
- Trace „dodaj pole do Channel": **~9–11 plików** w 2 aplikacjach (szczegóły w sekcji 4).

## 2. Czego NIE dało się sprawdzić (luki audytu)

- **Nie uruchamiałem setupu od zera** (zakaz `stack:reset`/`down -v` — wymazałby dev DB operatora). Weryfikacja kroków setupu jest statyczna (czytanie + istnienie komend/plików) + empiryczna tam gdzie żywy stack pozwalał bez mutacji. Nie potwierdziłem więc empirycznie że świeży `git clone → pnpm install → pnpm stack:up → migrate → fixtures` przechodzi end-to-end na czystym wolumenie; bazuję na czytaniu `DatabaseResetCommand` (kanoniczny flow) i entrypointu.
- **Nie odpalałem quality gates** z onboardingu (`composer phpstan/cs-check/deptrac`, `phpunit --testsuite=unit`, `pnpm lint/typecheck/build`) — potwierdziłem tylko że targety istnieją (skrypty w `composer.json` / `package.json` / `phpunit.dist.xml`), nie że przechodzą na czysto. Wyniki phpstan/semgrep są w `docs/audit/2026-06/raw/` (poza zakresem M1).
- **Nie weryfikowałem trace'u feature przez faktyczną implementację** (zakaz edycji) — śledziłem warstwy czytając istniejący kod `Channel`, zliczałem pliki do dotknięcia i sprawdzałem czy istnieje wzór do skopiowania.
- **Artefakt środowiska**: część outputów `rg`/`grep` podmieniała wybrane tokeny (np. „Channel" → „ln", „doctrine:fixtures:load" → „doctrine:ln") — to artefakt sandboxa wyświetlania, nie stan repo. Wszystkie findings oparłem o bezpośredni `Read` plików (gdzie podmiana nie występuje) lub o nazwy/ścieżki niezależne od podmienianych tokenów.

## 3. Findings — setup od zera (część a)

### M1-01 [HIGH] ONBOARDING.md podaje fałszywe dane logowania — blokada „Day 1"
`ONBOARDING.md:18` mówi: *„Login with `admin@demo.local` / `demo`."*
Rzeczywistość (fixtures): `apps/api/src/DataFixtures/AppFixtures.php:58` → `private const string DEFAULT_ADMIN_PASSWORD = 'changeme';`, `:173` → `'demo' => 'admin@demo.localhost'`.
Dowód empiryczny (żywy stack):
- `admin@demo.localhost` / `changeme` → **HTTP 200** + JWT (`{"token":"<REDACTED-JWT>"}`).
- README.md (sekcja smoke) używa poprawnego `admin@demo.localhost / changeme` — czyli dwa pliki onboardingowe są w sprzeczności, a ten przeznaczony WPROST dla nowego dewelopera kłamie.
Skutek: developer w „Day 1" wpisze `admin@demo.local`/`demo`, dostanie toast „Nieprawidłowy e-mail lub hasło" i nie ma jak zgadnąć poprawnych creds bez czytania kodu fixtures. Pełna blokada pierwszego logowania = blokada całego „Day 1".

### M1-02 [HIGH] Sekwencja seedowania w ONBOARDING.md pomija `audit:schema:update` — audytowane encje wywalają 500
`ONBOARDING.md:14-15` uczy ręcznego dwustopniowego flow:
```
doctrine:migrations:migrate --no-interaction
doctrine:fixtures:load --no-interaction
```
Kanoniczny flow w kodzie (`apps/api/src/Shared/Infrastructure/Maintenance/DatabaseResetCommand.php:73-98`) ma **cztery** kroki SQL przed fixtures: drop → create → `doctrine:migrations:migrate` → **`audit:schema:update --force`** → (fixtures). Komentarz w kodzie (`:77-83`) tłumaczy dlaczego ten krok jest obowiązkowy:
> „dh_auditor creates *_audit tables outside the regular Doctrine migrations pipeline. Without this step, INSERTs into audited entities (ImportSession, ImportProfile, Channel, Asset, …) trip a 'relation does not exist' inside the auditor listener, rollback the surrounding transaction, and the operator sees a bare 500 with a foreign-key violation".
`audit:schema:update` nie pada w ŻADNYM z README/ONBOARDING/CONTRIBUTING (`grep -rn "audit:schema"` → brak). `doctrine:fixtures:load` z fixtures (`AppFixtures` persystuje m.in. `Channel`/Allegro `:208`) na świeżej bazie bez audit-tabel uderzy w listener auditora.
Skutek: dev który dosłownie wykona ONBOARDING.md i potem spróbuje cokolwiek zapisać do audytowanej encji dostaje nieczytelny 500 „relation *_audit does not exist". Mitygacja: entrypoint (`apps/api/docker-entrypoint.sh:41`) na dev/test auto-woła `pim:dev:ensure-seeded` (które wewn. używa `pim:db:reset` z pełnym flow) — więc „happy path" przez sam `stack:up` może się zaseedować poprawnie, ale ręczny flow z dokumentacji jest niekompletny i myli.

### M1-03 [MEDIUM] `pim:db:reset` — kanoniczny one-shot — nie jest udokumentowany w żadnym pliku onboardingowym
`apps/api/src/Shared/Infrastructure/Maintenance/DatabaseResetCommand.php` to JEDYNE źródło prawdy o poprawnej sekwencji (drop+create+migrate+audit:schema:update+fixtures+reindex). `grep -rn "pim:db:reset" README.md ONBOARDING.md CONTRIBUTING.md` → **brak**. Zamiast wskazać `docker compose exec api bin/console pim:db:reset --with-fixtures --force`, oba pliki onboardingowe uczą niekompletnej ręcznej sekwencji (patrz M1-02). Nowy dev nie ma jak odkryć właściwej komendy bez przeszukania `src/`.

### M1-04 [MEDIUM] README nie wspomina o `doctrine:migrations:migrate` — schemat nie powstanie sam
README „Lokalny development" (kroki 1–4) i sekcja „Running E2E" (`README.md` linia z `doctrine:fixtures:load`) NIE wymieniają `doctrine:migrations:migrate` (`grep -n "migrat" README.md` → brak). Tylko ONBOARDING.md je ma. Dev który idzie wyłącznie README'em: `pnpm install → cp .env → pnpm dev → curl` — przy świeżym wolumenie schemat tworzy entrypoint przez `pim:dev:ensure-seeded`, ale README sugeruje że `fixtures:load` wystarczy bez migracji, co jest fałszem dla manualnego flow. Dwa pliki uczą dwóch różnych (i każdy niekompletny) flow.

### M1-05 [MEDIUM] Niespójność `docker compose exec` vs `exec -T` między README a ONBOARDING/CONTRIBUTING
README (sekcja „Quality gates"): `docker compose exec api composer phpstan` (bez `-T`). ONBOARDING.md:23-26 i CONTRIBUTING.md:114 mieszają warianty: część z `-T`, część bez. `docker compose exec` (bez `-T`) alokuje TTY — w skrypcie / pipeline / nie-tty shellu zwraca „the input device is not a TTY" i ubija krok. Dla nowego dewelopera kopiującego komendy do skryptu to cichy fail. Higiena → ujednolicić na `-T`.

### M1-06 [LOW] `.env.example` → `.env` nie jest twardo wymagany (compose ma własne defaulty), ale README sugeruje że jest krytyczny
`.env.example:9-10` daje `POSTGRES_USER=pim` / `POSTGRES_PASSWORD=ChangeMeInDev`; `docker-compose.yml:133,194,245-247` fallbackuje na `app` / `!ChangeMe!` SPÓJNIE dla api i database (`${POSTGRES_USER:-app}` w obu). Czyli `pnpm dev` BEZ `cp .env.example .env` wystartuje (oba serwisy użyją `app`). README krok 2 sugeruje że bez tego nic nie ruszy. To nie jest blokada, ale rozjazd „dokumentacja vs realne wymaganie" — drobny szum. (Zauważalne: defaultowe `APP_SECRET=ChangeMeBeforeDeploy` i `MINIO_ROOT_PASSWORD=minioadmin` to dev-only stuby — poza zakresem M1, do raportu sekretów.)

## 4. Findings — feature end-to-end „dodaj pole do Channel" (część b)

### M1-07 [MEDIUM] Brak udokumentowanego splitu Domain entity ↔ API Platform Resource (XML) ↔ Input DTO — dev utknie na warstwie API
Adwersarsko: gdzie dodać pole, żeby pojawiło się w API? Encja `apps/api/src/Channel/Domain/Entity/Channel.php` NIE ma atrybutu `#[ApiResource]` (`rg "ApiResource" Channel.php` → brak). API Platform jest skonfigurowane przez OSOBNY plik XML: `apps/api/src/Channel/Infrastructure/ApiPlatform/Resource/Channel.xml` (`resource class="App\Channel\Domain\Entity\Channel"`), z osobnymi DTO `ChannelInput.php` + `ChannelPatchInput.php` i state processorem `ChannelProcessor.php`. Powód w komentarzu DTO (`ChannelInput.php:10-13`): *„AP4 default Doctrine processor cannot hydrate the constructor-only Channel aggregate — this DTO is the deserialisation target"*.
Ten wzorzec (Domain entity ma tylko konstruktor + metody domenowe; serializacja przez grupy w XML; zapis przez DTO + Processor) NIE jest opisany w ONBOARDING/CONTRIBUTING (`rg "ApiPlatform/Resource|state provider|processor" ONBOARDING.md CONTRIBUTING.md bounded-contexts.md` → brak trafień). Dev przyzwyczajony do API Platform z atrybutami na encji będzie szukał `#[ApiResource]` na `Channel`, nie znajdzie, i utknie. Dodanie POLA wymaga edycji: encja → ORM XML → migracja → DTO Input → DTO PatchInput → Processor (mapowanie DTO→aggregate) → grupy serializacji w `Channel.xml`.

### M1-08 [MEDIUM] Skrypt regeneracji typów `@pim/shared-types` jest zepsuty — błędny scheme i błędna ścieżka
Po dodaniu pola dev musi odświeżyć kontrakt FE↔BE. `packages/shared-types/package.json:13`:
```
"generate": "openapi-typescript http://pim.localhost/api/docs.json -o src/api.d.ts"
```
Dwa błędy potwierdzone empirycznie:
- **Scheme**: `curl http://pim.localhost/api/docs.json` → **HTTP 000** (brak nasłuchu plaintext na tej ścieżce; stack jest HTTPS-only przez Caddy).
- **Ścieżka**: `https://pim.localhost/api/docs.json` → **HTTP 404**. Poprawna ścieżka OpenAPI w AP4 to `/api/docs.jsonopenapi` → **HTTP 200** (potwierdzone pętlą: `.json`→404, `.jsonopenapi`→200, `.jsonld`→200, `/api/docs`→200).
Skutek: `pnpm --filter @pim/shared-types generate` failuje z mylącym „connection refused", a nawet po naprawie scheme nadal 404. Plik `packages/shared-types/src/api.d.ts` jest zacommitowany, więc `pnpm build`/`typecheck` przechodzą (turbo `build` nie zależy od `generate`) — ale jedyna droga odświeżenia typów po zmianie schematu jest niedrożna. To dokładnie blokuje krok „regen typów" w trace'ie feature.

### M1-09 [LOW] README nazywa `shared-types` „build step", ale pakiet nie ma skryptu `build`
README („Struktura monorepo") opisuje `packages/shared-types` jako *„TypeScript types generowane z OpenAPI spec (build step)"*. `packages/shared-types/package.json` ma tylko `generate` (zepsute, M1-08) i `typecheck` — **brak** skryptu `build` (`rg '"build"' shared-types/package.json` → brak). `turbo run build` nie regeneruje typów. Mylące dla nowego dewelopera oczekującego że typy są częścią pipeline'u buildu; w rzeczywistości to ręczny, zepsuty `generate` + zacommitowany artefakt.

### M1-10 [LOW] Brak wzorca/szablonu „end-to-end" dla nowej encji/pola
`rg "add a new field|add a field|new entity|how to add"` po `docs/` i plikach onboardingowych → tylko fragmenty w `Project Plan/UI/*` (ticket-specyficzne, nie reusable guide). Brak jednego „kuchennego" przykładu „od migracji do UI". ONBOARDING.md „Day 3" sugeruje skopiowanie wzorca (`AttributeOption` / `BuiltInObjectTypeSeeder`), ale to seed/fixture, NIE pełny vertical-slice (migracja→ORM→DTO→Processor→Resource XML→UI form→shared-types→test). Dev musi sam zrekonstruować ~9–11 warstw z różnych BC bez referencyjnego przykładu.

## 5. Co działa (zweryfikowane pozytywnie — chwalę tylko z dowodem)

- Login `admin@demo.localhost` / `changeme` → **HTTP 200** + JWT (README poprawne; ONBOARDING błędne).
- `GET /api/channels` z Bearer → **HTTP 200**, realny seed `allegro`.
- Klasy/komendy cytowane w onboardingu istnieją: `AppFixtures` (`apps/api/src/DataFixtures/AppFixtures.php`), `BuiltInObjectTypeSeeder` (`apps/api/src/Catalog/Application/BuiltInObjectTypeSeeder.php`).
- Skrypty composer cytowane jako gates istnieją: `phpstan`, `deptrac`, `cs-check`, `cs-fix`, `lint` (`apps/api/composer.json:97-104`).
- `phpunit --testsuite=unit` — sekcja `<testsuite name="unit">` istnieje w `apps/api/phpunit.dist.xml:23`.
- Skrypty pnpm cytowane w README istnieją: `dev`, `stack:up/down/reset/rebuild`, `typecheck`, `build`, `backup:*` (`package.json:12-29`).
- Skrypty admin `lint/typecheck/build/e2e/e2e:ui` istnieją (`apps/admin/package.json:8-16`).
- Docs „Day 10" istnieją: `docs/architecture/c4-context.md`, `c4-container.md`, `bounded-contexts.md`; ADR-0010/0013/0014/0015 istnieją (`docs/adr/0010-..0015-*.md`). `Zrodla/Zalecana_struktura_kodu/Audyt/AUDIT-CHECKLIST.md` istnieje; `docs/audits/` ma raporty.
- `.nvmrc` = `22` spójne z README „Node ≥22".
- `apps/api/bin/console` istnieje z shebangiem `#!/usr/bin/env php`, WORKDIR kontenera api = `/app` (`apps/api/Dockerfile:20`) → `docker compose exec -T api bin/console …` rozwiązuje się poprawnie.

## 6. Podsumowanie liczbowe friction

- Dane logowania: **1 plik kłamie** (ONBOARDING.md), 1 mówi prawdę (README) — sprzeczne.
- Sekwencja seedowania: **3 różne, każda niekompletna** (README bez migrate, ONBOARDING bez audit:schema:update, kod = kanoniczne `pim:db:reset` nieudokumentowane).
- Komendy nieistniejące/zepsute: **1** (`shared-types generate` — zły scheme + 404).
- Wzorzec architektoniczny krytyczny dla feature, nieudokumentowany: **1** (Domain↔API DTO/Processor split).
- Pliki do dotknięcia dla „dodaj pole do Channel": **~9–11**, bez referencyjnego szablonu.
