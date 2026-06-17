# Domena D — Sekrety i Konfiguracja

Audyt adwersarski PIM przed wypuszczeniem jako SaaS. Data: 2026-06-16. Tryb: READ-ONLY.

## Metodyka — co sprawdzono i jak

1. **Sekrety w repo i historii** — przeczytano `raw/gitleaks-history.json` (1042 commity, 4 trafienia), `raw/gitleaks-history-byrule.txt`, `raw/gitleaks-worktree-byrule.txt` (183 trafienia), `raw/gitleaks-worktree-byrule-clean.txt`, pliki `-byfile`. Odsiano false-positives (`.pnpm-store`, `vendor`, `node_modules`, `.php-cs-fixer.cache`, `docs/audit/...`) parsując `raw/gitleaks-worktree.json` Pythonem i filtrując po `File`. Sprawdzono `.gitleaks.toml` (allowlist).
2. **Tracked env / klucze** — `git ls-files | grep env`, `git ls-files apps/api/config/jwt/`, `git check-ignore -v`, `git show HEAD:<plik>`. Czytano treść `apps/api/.env`, `apps/api/.env.dev`, `apps/api/.env.test`.
3. **Symfony secrets vault** — `ls apps/api/config/secrets` (brak katalogu).
4. **Caddy / FrankenPHP** — `find docker -iname '*caddy*'`, przeczytano `docker/caddy/Caddyfile` (edge), `docker/caddy/Caddyfile.minio`, `apps/api/frankenphp/Caddyfile`, `apps/api/frankenphp/worker.Caddyfile`.
5. **Compose dev + prod** — przeczytano cały `docker-compose.yml` i `docker-compose.prod.yml`, analiza fallback-defaults `${VAR:-default}`, profili, portów (`ports:` vs `expose:`).
6. **Debug surfaces** — `config/bundles.php` (WebProfiler), `config/packages/api_platform.yaml` (`enable_swagger_ui` + blok `when@prod`), `config/packages/doctrine.yaml` (`logging`), żywe sondy HTTP.
7. **Logowanie sekretów/PII** — ripgrep po `logger->(info|error|...)` skrzyżowany z `password|token|secret|email|otp|mfa|reset`; ręczna inspekcja trafień w `Identity/Application/*Service.php`; sprawdzenie monolog config (brak własnego — domyślny Symfony).
8. **Empiryczna weryfikacja nagłówków** — `curl -skI https://pim.localhost/` i `.../api` na żywym stacku. Sondy `/_profiler`, `/api/docs`. `docker compose exec api printenv APP_ENV` (= `dev`).

## Czego NIE dało się sprawdzić (luki audytu)

- **Realny deploy prod** — nie istnieje uruchomione środowisko prod ani `.env.prod` w repo. Wnioski o prod oparte wyłącznie na statycznej analizie `docker-compose.prod.yml` + `when@prod`. Nie zweryfikowano czy operator faktycznie ustawia env-y zamiast fallbacków przy realnym deployu — to zależy od runbooka deployu, którego brak w repo.
- **Nagłówki bezpieczeństwa na prod** — zweryfikowano TYLKO dev edge Caddy (`pim.localhost`). Prod używa „release image z osobnym Caddyfile + pipeline build" (komentarz w `docker-compose.prod.yml:146-153`) — ten plik NIE istnieje w repo, więc nie wiadomo czy `common_security` snippet jest tam re-importowany. To istotna luka: nagłówki prod są nieweryfikowalne.
- **Rotacja / wartość sekretów w bazie** — `APP_BYOK_KEY_V1` szyfruje klucze klientów; nie sprawdzano czy realne tenant-keys już istnieją w `tenant_agent_configs` (poza zakresem READ-ONLY na danych).
- **Czy klucze z `apps/api/.env` były KIEDYKOLWIEK użyte w realnym deployu** — nie da się stwierdzić; traktuję je jako skompromitowane z definicji (są w publicznej-prywatnej historii gita).
- **Git remote visibility** — nie weryfikowano czy `github.com/malipie/PIM` jest public czy private (wpływa na severity wycieku, ale przy modelu SaaS multi-tenant zakładam najgorszy przypadek).

## Findings (dowody)

### D-01 (CRITICAL) — Realne sekrety w trackowanym `apps/api/.env` (BYOK master key, JWT passphrase, Mercure JWT)

`apps/api/.env` jest **trackowany w git** (potwierdzone: `git ls-files apps/api/.env` → trafienie; `.gitignore:52` ma jawny allowlist `!apps/api/.env`). Plik zawiera materiał kryptograficzny będący prawdziwymi sekretami, nie placeholderami:

- `apps/api/.env:78` — `APP_BYOK_KEY_V1=<base64 32B>` — **master key AES-256-GCM** którym szyfrowane są klucze API Anthropic klientów (BYOK, ADR-0017). Gitleaks `generic-api-key`, entropia 4.91. Skompromitowanie tego klucza = możliwość odszyfrowania wszystkich kluczy klientów BYOK z `tenant_agent_configs`.
- `apps/api/.env:84` — `JWT_PASSPHRASE=<hex 32B>` — passphrase do prywatnego klucza JWT (`config/jwt/private.pem`). Z passphrase + ewentualnym dostępem do klucza prywatnego można podpisywać dowolne JWT (impersonacja dowolnego użytkownika/tenanta).
- `apps/api/.env:112` — `MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"` — sekret podpisujący JWT huba Mercure (wartość placeholder, ale aktywnie używana jako default w compose).

Plik mówi sam w komentarzu (`.env:11`): „DO NOT DEFINE PRODUCTION SECRETS IN THIS FILE NOR IN ANY OTHER COMMITTED FILES" — a mimo to commituje sekrety. Choć `APP_ENV=dev` i to dev-defaults, klucz BYOK i JWT passphrase są materiałem kryptograficznym, który musi być traktowany jako skompromitowany. Wszystkie te wartości są w historii gita (commit `8927c4cb`).

### D-02 (HIGH) — Prod overlay startuje z niebezpiecznymi fallback-defaultami sekretów (nic nie WYMUSZA zmiany)

`docker-compose.prod.yml` używa wzorca `${VAR:-default}` dla wszystkich sekretów — `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d` z pustym `.env` wstanie produkcję z domyślnymi sekretami, BEZ żadnego błędu/abortu:

- `docker-compose.prod.yml:102` (worker) i base `docker-compose.yml:132` (api, NIE override'owany w prod) — `APP_SECRET: ${APP_SECRET:-ChangeMeBeforeDeploy}` — w prod API dziedziczy `ChangeMeBeforeDeploy`.
- `docker-compose.prod.yml:65,108` — `POSTGRES_PASSWORD:-!ChangeMe!}`.
- `docker-compose.prod.yml:115` — `MERCURE_JWT_SECRET:-!ChangeMercureKeyAtLeast256BitsLong!}`.
- `docker-compose.prod.yml:118` — `MEILI_KEY: ${MEILI_MASTER_KEY:-masterKeyPleaseChangeMe}`.
- base (dziedziczone w prod) `docker-compose.yml:315-316` — `MINIO_ROOT_USER:-minioadmin` / `MINIO_ROOT_PASSWORD:-minioadmin`.

W przeciwieństwie do `alertmanager` (`ALERTMANAGER_WEBHOOK_URL:-http://example.invalid/` — celowo „fails loudly"), sekrety mają działające defaulty → cichy start na słabych kluczach. Brak `.env.prod`/runbooka deployu w repo, który by to wymuszał. Brak Symfony Secrets Vault (`ls apps/api/config/secrets` → brak katalogu) — CLAUDE.md mówi „Klucze w Symfony Secrets Vault / env vars", vault nie jest realnie użyty.

### D-03 (HIGH) — `apps/api/.env.dev` trackowany z realnym `APP_SECRET`, BEZ allowlist w `.gitignore`

`apps/api/.env.dev` jest **trackowany** (`git show HEAD:apps/api/.env.dev` → istnieje). `git check-ignore -v apps/api/.env.dev` → brak dopasowania: plik nie jest objęty żadną regułą ignore (pattern `.env` w `.gitignore:47` to dokładne dopasowanie nazwy `.env`, nie łapie `.env.dev`; `.env.*.local` też nie). W `.gitignore` jest allowlist `!apps/api/.env` (52) i `!apps/api/.env.test` (53), ale `.env.dev` NIE jest świadomie allowlistowany — wszedł do repo „przez przypadek" (luka w regule ignore).

Zawiera: `apps/api/.env.dev:3` — `APP_SECRET=<REDACTED-32-hex-dev-secret>` (gitleaks `generic-api-key`, commit `a2be99cc`; realna wartość celowo nie cytowana w raporcie — rotacja w W0-7/#1579). `APP_SECRET` w Symfony używany m.in. do CSRF/podpisów — wyciek osłabia te mechanizmy w środowisku dev, a dodatkowo dowodzi systemowego problemu z trackowaniem plików env.

### D-04 (MEDIUM) — Mailpit działa w prod overlay, Meilisearch z `MEILI_ENV: development` w prod

`docker-compose.prod.yml` „dev-only-profiluje" jedynie serwis `admin` (`docker-compose.prod.yml:155` → `profiles: ["dev-only"]`). NIE wyłącza serwisów debug/dev z base:

- **Mailpit** (`docker-compose.yml:397-405`) nie ma profilu i nie jest override'owany w prod → uruchamia się w produkcji. Łapie cały wychodzący mail (reset hasła, zaproszenia z plaintext tokenami w `confirm_url`) i wystawia UI na `:8025`. Co prawda `expose` (nie `ports`), ale to wewnątrz sieci docker dostępne dla każdego skompromitowanego kontenera; mail w prod powinien iść do realnego SMTP, nie do catcher-a.
- **Meilisearch** dziedziczy `docker-compose.yml:298` — `MEILI_ENV: development` (NIE override'owany w prod). W trybie `development` Meili wyłącza wymóg klucza API na części operacji i wystawia interaktywny search preview / nie wymusza master key tak rygorystycznie jak `production`. `MEILI_MASTER_KEY` ma fallback `masterKeyPleaseChangeMe`.

### D-05 (MEDIUM) — Brak Symfony Secrets Vault; sekrety wyłącznie przez env/pliki

`ls apps/api/config/secrets` → katalog nie istnieje. CLAUDE.md („Brak hardkodowanych URL-i / kluczy / sekretów w kodzie. Klucze w Symfony Secrets Vault / env vars") i `apps/api/.env:12` odsyłają do secrets vault, ale realnie żaden sekret nie jest w vaulcie — wszystkie w plikach `.env*` (część trackowanych — patrz D-01/D-03) lub env-ach compose z defaultami (D-02). Dla SaaS multi-tenant brak vaulta oznacza brak rotacji, audytu dostępu i szyfrowania at-rest sekretów infrastrukturalnych.

### D-06 (LOW) — Wyciek wersji PHP w nagłówku `x-powered-by` na `/api`

Empiryczna sonda `curl -skI https://pim.localhost/api`:
```
x-powered-by: PHP/8.4.21
via: 1.1 Caddy
```
Edge Caddyfile (`docker/caddy/Caddyfile:56`) usuwa `-Server`, ale NIE usuwa `X-Powered-By` (ten nagłówek dodaje PHP/FrankenPHP za FrankenPHP-owym Caddy, więc edge go nie zdejmuje). Ujawnia dokładną wersję PHP (8.4.21) — fingerprinting ułatwiający dobór exploitów. Na `/` (Vite) nagłówka nie ma. Fix: `header /api* -X-Powered-By` na edge lub `header_remove`/`expose_php=Off` w php.ini.

### D-07 (LOW / operacyjne) — Prod nie nadpisuje `TRUSTED_HOSTS` (dziedziczy `pim.localhost`)

`docker-compose.prod.yml` nie ustawia `TRUSTED_HOSTS`/`TRUSTED_PROXIES`; dziedziczone z base `docker-compose.yml:150` — `TRUSTED_HOSTS: '^pim\.localhost$|^localhost$|^api$'`. Pod realną domeną prod (`pim.example.com` per architektura) Symfony odrzuci żądania jako nieznany host (`Host` header rejection) dopóki operator nie nadpisze env-em. To raczej awaria dostępności niż dziura, ale potwierdza, że prod overlay jest niekompletny (zgodnie z D-02 / brakiem runbooka).

## Co zweryfikowano jako POPRAWNE (z dowodem)

- **Nagłówki bezpieczeństwa edge (dev)** — `curl -skI https://pim.localhost/` zwraca komplet: `content-security-policy` (default-src 'self', frame-ancestors 'none', object-src 'none'), `strict-transport-security: max-age=63072000; includeSubDomains; preload`, `x-frame-options: DENY`, `x-content-type-options: nosniff`, `referrer-policy: strict-origin-when-cross-origin`, `permissions-policy` (camera/mic/geo/payment/usb/interest-cohort all `()`), `cross-origin-opener-policy: same-origin`, `cross-origin-resource-policy: same-origin`. Zdefiniowane w `docker/caddy/Caddyfile:20-58`. CSP ma świadomie `'unsafe-inline' 'unsafe-eval'` na script-src (udokumentowane: Vite HMR + Refine) — to osłabienie XSS-ochrony, ale uzasadnione w MVP.
- **Brak CORS** — zgodnie z architekturą. Brak `nelmio_cors` (potwierdzone: nie ma w `bundles.php`), brak `Access-Control-Allow-Origin` w odpowiedziach edge. Mercure ma własne `cors_origins https://pim.localhost https://localhost` (`docker-compose.yml:373`) — to konfiguracja huba, nie aplikacji.
- **Doctrine query logging wyłączony globalnie** — `config/packages/doctrine.yaml:41` `logging: false`, `:33` `profiling_collect_backtrace: false`. Zgodne z wymogiem memory-management w worker mode.
- **Swagger UI wyłączone w prod** — `config/packages/api_platform.yaml` blok `when@prod`: `enable_swagger_ui: false`, `enable_re_doc: false` (`enable_docs: true` zostaje — JSON OpenAPI dostępny, świadoma decyzja). W dev `/api/docs` zwraca 200 (zweryfikowane sondą).
- **WebProfiler nie zarejestrowany w żadnym env** — `config/bundles.php` nie zawiera `WebProfilerBundle` (ani dev). Sonda `/_profiler` zwraca 200, ale to Vite SPA catch-all (`Caddyfile:86 handle {}`), NIE Symfony profiler.
- **Logowanie nie ujawnia plaintext sekretów** — jedyne 3 trafienia logujące kontekst z PII/tokenów (`PasswordResetService.php:95`, `UserCreateService.php:159`, `InvitationService.php:122`) logują `token_id`/`invitation_id` (UUID rekordu) i `recipient` email do diagnostyki SMTP — NIE plaintext token/hasło. Akceptowalne.
- **Klucz prywatny JWT nie jest trackowany** — `git ls-files apps/api/config/jwt/` puste; `git check-ignore -v apps/api/config/jwt/private.pem` → `apps/api/.gitignore:23 /config/jwt/*.pem`. Trafienie gitleaks `private-key` jest na pliku worktree (lokalny, niezacommitowany).
- **Porty serwisów danych** — w obu compose serwisy `database/redis/meilisearch/minio/mercure/mailpit` używają `expose` (sieć docker), tylko `caddy` ma `ports: 80/443`. Brak bindowania DB/Redis/Meili na `0.0.0.0` hosta. Zgodne z single-origin.
- **`composer audit`** — `raw/composer-audit.txt`: „No security vulnerability advisories found".
- **`.gitleaks.toml`** — allowlist minimalny i uzasadniony (AWS example key w docs, fixed CI placeholder Mercure key). Nie maskuje realnych sekretów z D-01/D-03.

## Podsumowanie severity

| ID | Severity | Skrót |
|----|----------|-------|
| D-01 | CRITICAL | BYOK master key + JWT passphrase w trackowanym `.env` |
| D-02 | HIGH | Prod startuje z fallback-defaultami sekretów |
| D-03 | HIGH | `.env.dev` trackowany z `APP_SECRET`, luka w `.gitignore` |
| D-04 | MEDIUM | Mailpit + Meili `development` w prod overlay |
| D-05 | MEDIUM | Brak Symfony Secrets Vault |
| D-06 | LOW | `x-powered-by: PHP/8.4.21` na /api |
| D-07 | LOW | Prod nie nadpisuje TRUSTED_HOSTS |
