# Domena H — API Contract i błędy (audyt 2026-06-16)

Audytor: subagent (adwersarski). Środowisko: `https://pim.localhost`, `APP_ENV=dev`.
Read-only. Wszystkie probe'y to GET/POST walidacyjne — żaden write nie zmodyfikował danych.

## Metodyka — co sprawdzono i jak

1. **Konfiguracja błędów**: `config/packages/framework.yaml`, `config/packages/api_platform.yaml`,
   `public/index.php`, `apps/api/Dockerfile`, `docker-compose.yml`, `docker-compose.prod.yml`,
   `apps/api/frankenphp/php.ini`, `apps/api/frankenphp/Caddyfile`, `docker/caddy/Caddyfile`.
2. **Empiryczne curl** na żywym stacku (zalogowany jako admin@demo.localhost przez JWT) — błędy
   401/404/405/415/422/400 na endpointach API Platform i custom controllerach, z negocjacją
   `Accept: application/json` / `application/ld+json` / brak Accept.
3. **Spójność OpenAPI**: porównanie `raw/openapi.json` (live export) z `docs/api-spec/v0.json`
   (snapshot) — normalizacja JSON + diff ścieżek + diff bajtowy. Porównanie liczby ścieżek
   OpenAPI z liczbą route'ów w `raw/routes.txt`.
4. **Rate limiting**: grep wszystkich `RateLimiterFactory`/`->consume()` w `src/`, mapowanie
   zdefiniowanych limiterów (framework.yaml) do faktycznych konsumentów. Inspekcja każdego
   endpointu auth (login/refresh/MFA/password-reset/invitation) pod kątem limitera.
5. **Limity payloadów**: php.ini (`post_max_size`/`upload_max_filesize`/`memory_limit`),
   edge Caddy `request_body`, guardy aplikacyjne (BulkEdit `MAX_IDS`, Export `HARD_CAP`,
   FilterDsl nesting/conditions). Probe 240 KB JSON na `/api/products/bulk-edit`.

## Czego NIE dało się sprawdzić (luki audytu)

- **Zachowanie w PROD (`APP_DEBUG=0`)** nie zostało zweryfikowane empirycznie — stack lokalny działa
  w `dev`. Wnioski o sanityzacji `trace` w prod oparte na konfiguracji (`docker-compose.prod.yml`
  ustawia `APP_DEBUG=0`, `php.ini display_errors=0`), nie na żywym prod-responsie. Symfony domyślnie
  usuwa `trace` przy `kernel.debug=false`, ale FlattenException JSON dla custom controllerów nie był
  obserwowany w prod-mode.
- **Faktyczne wymuszenie 429 na login** w prod-limicie (5/15min) nie testowane do końca (dev override =
  200/15min; nie chciałem zużywać prawdziwego budżetu ani ryzykować zablokowania konta operatora).
  Potwierdzono natomiast że listener rzuca `TooManyRequestsHttpException` z `Retry-After`.
- **Edge Caddy 150MB limit** potwierdzony w configu, nie testowany realnym 150MB+ uploadem (ryzyko
  obciążenia maszyny operatora).
- **`agent_run` / `integration_sync`** limitery — zdefiniowane ale bez konsumenta (Faza 1/2, endpointy
  jeszcze nie istnieją); nie traktuję jako lukę bo feature poza MVP.
- Nie sprawdzano GraphQL error surface (jeśli włączony) — skupiono się na REST.

## Findings (szczegóły z dowodami)

### H-01 [CRITICAL] Reset-token i invitation-token wyciekają w body HTTP — brak prod-guarda
`PasswordResetController.php:53`, `InvitationController.php:78`, `InvitationActionsController.php:148`.
Pole `token_dev_only` z prawdziwym tokenem zwracane w response, BEZ żadnego runtime guarda
(`grep kernel.debug|isProd|APP_ENV|when@prod` w obu plikach → NO env guard). Komentarz mówi
"production drops this field" ale kod tego nie robi.

Dowód (live, bez auth):
```
POST /api/auth/password-reset/request  {"email":"admin@demo.localhost"}
HTTP 200 | {"status":"sent","token_dev_only":"<REDACTED-64-hex-dev-reset-token>"}
```
Endpoint jest `PUBLIC_ACCESS` (security.yaml:90). Token jest funkcjonalny (consume() go akceptuje).
Jeśli ten kod trafi do prod bez dorobienia mailera + guarda → **account takeover dowolnego konta**
znając tylko email. Dokładnie lekcja #658 z lessons.md ("dev-mode token in response ≠ closure").

### H-02 [HIGH] Dwa niespójne formaty błędów: API Platform RFC 7807 vs Symfony FlattenException
55 route'ów API Platform zwraca czyste RFC 7807 (`@context`, `/errors/404`, `/validation_errors/`).
~157 custom route'ów pod `/api/` (87 controllerów rzuca `BadRequestHttpException`/
`UnprocessableEntityHttpException` z gołymi stringami) zwraca FORMAT Symfony FlattenException:
```
POST /api/object_types {} (Accept: application/json)
HTTP 400 | {"type":"https://tools.ietf.org/html/rfc2616#section-10","title":"An error occurred",
  "status":400,"detail":"code is required.","class":"Symfony\\...\\BadRequestHttpException","trace":[...]}
```
To NIE jest RFC 7807: `type` wskazuje na RFC2616 (nie na dokument błędu), zawiera `class`
(nazwa klasy wyjątku — info leak) i `trace`. Bez Accept zwraca wręcz `text/html` error page
(przeciek struktury routingu). Integrator dostaje dwa różne kontrakty błędów na jednym API.

### H-03 [HIGH] OpenAPI dokumentuje 31 ścieżek; router ma 228 ścieżek /api/*
`raw/openapi.json` = 31 paths (tylko zasoby API Platform). `raw/routes.txt` = 317 route'ów,
228 unikalnych ścieżek `/api/*`. Cała powierzchnia custom: auth, MFA, password-reset, invitation,
bulk-edit, export, import, asset upload, super-admin tenants, RBAC — NIEUDOKUMENTOWANA w OpenAPI.
Narusza "API jest produktem first-class" (CLAUDE.md). Integrator/security-reviewer nie widzi 80%
powierzchni z kontraktu. (Snapshot v0.json jest dobrze utrzymany — patrz H-06 — ale opisuje tylko
te 31 ścieżek.)

### H-04 [MEDIUM] Dockerfile hardkoduje APP_ENV=dev, brak prod-stage
`apps/api/Dockerfile:9` → `ENV APP_ENV=dev`. Brak multi-stage prod target, brak `.env.prod`.
Override istnieje WYŁĄCZNIE w `docker-compose.prod.yml` (APP_ENV=prod, APP_DEBUG=0). Jeśli operator
zbuduje/uruchomi obraz bez prod-overlayu (np. `docker run` obrazu, k8s manifest z domyślnym ENV,
pomyłka w deploy) → cała aplikacja w dev mode: pełne `trace` w błędach, Swagger UI włączony,
profiler. Ryzyko deployment-misconfiguration. Mitygacja: php.ini `display_errors=0` ogranicza część.

### H-05 [MEDIUM] Brak aplikacyjnego limitu rozmiaru JSON body na endpointach nie-importowych
Limit chain: edge Caddy 150MB → php.ini `post_max_size=110M` → aplikacja (tylko import: 100MB).
Endpointy nie-importowe (każdy PATCH/POST z JSON) nie mają guarda rozmiaru body — `json_decode`
całego ciała następuje PRZED jakąkolwiek walidacją. Dowód: 240KB JSON na `/api/products/bulk-edit`
w pełni zdekodowany (`size_upload: 240067`) zanim aplikacja odrzuca po `operation`. Przy ~109MB JSON
to OOM/CPU-DoS pojedynczego workera (`memory_limit=256M`, recykling po 1000 req). Ograniczone do
DoS workera, nie wyciek danych. Guardy domenowe istnieją punktowo (BulkEdit MAX_IDS=5000,
FilterDsl nesting≤3/≤20 cond/grupę, Export HARD_CAP=500k) ale nie ma globalnego limitu body.

### H-06 [LOW / pozytyw] Brak driftu OpenAPI; rate-limit auth sanityzowany
- `raw/openapi.json` vs `docs/api-spec/v0.json`: **bajt-w-bajt identyczne** po normalizacji
  (196717 == 196717, IDENTICAL: True). Wersja 0.1.0 zgodna. Brak driftu integer/number na status.
- `/api/auth/login` (5/15min/IP), `/api/auth/refresh` (30/h/IP), `/api/api_keys` (1000/h/key),
  `/api/backups` (1/h/tenant), `/api/imports` (20/h/tenant) — wszystkie limitery zdefiniowane SĄ
  faktycznie konsumowane (`->consume()`). 429 zwraca `Retry-After`. Login failure zwraca
  RFC 7807 (`application/problem+json`).
- MFA brute-force ograniczony: `MfaLoginChallengeStore` MAX_ATTEMPTS=5/challenge + login limiter →
  ~25 prób TOTP/15min/IP, niewykonalne dla 6-cyfrowego kodu (1M kombinacji).

### H-07 [LOW] Brak rate-limitera na password-reset/request i invitation
`PasswordResetController` (PUBLIC_ACCESS, security.yaml:90) i invitation endpoints nie mają limitera.
Always-200 chroni przed enumeracją kont, ale brak limitu pozwala na spam żądań resetu / timing-based
probing. Niższy priorytet niż H-01 (który czyni reset i tak trywialnym póki token wycieka).
