# Secrets runbook — production credentials & Symfony Secrets Vault

> AUD-009 / AUD-046 / AUD-047 (W1-4). Jak ustawiać, walidować i rotować sekrety
> produkcyjne PIM. Dwie warstwy sekretów współistnieją — wybór warstwy zależy
> od tego, *kto* czyta sekret (docker-compose przy starcie kontenera vs. proces
> Symfony w runtime).

## TL;DR — gdzie który sekret

| Sekret | Czytany przez | Warstwa | Źródło prawdy |
| --- | --- | --- | --- |
| `APP_SECRET`, `POSTGRES_PASSWORD`, `APP_DB_PASSWORD`, `MERCURE_JWT_SECRET`, `MEILI_MASTER_KEY`, `MINIO_ROOT_*` | docker-compose interpolation **przy starcie kontenera** | **env** (`.env.prod`) | `.env.prod.example` |
| Application-level sekrety (np. `ANTHROPIC_API_KEY`, klucze integracji, SMTP password) odczytywane przez kod Symfony przez `%env(...)%` | proces Symfony **w runtime** | **Secrets Vault** *lub* env | `config/secrets/prod/` |

**Dlaczego dwie warstwy.** docker-compose interpoluje `${VAR}` zanim kontener
wystartuje — w tym momencie nie istnieje żaden proces Symfony, więc vault jest
niedostępny. Sekrety infrastrukturalne (DB/MinIO/Meili/Mercure) MUSZĄ być
dostarczone jako env. Sekrety których potrzebuje dopiero aplikacja (klucze API
do zewnętrznych serwisów) mogą iść do szyfrowanego vaulta i być dekryptowane
w runtime jednym kluczem (`SYMFONY_DECRYPTION_SECRET`).

---

## 1. Fail-loud prod overlay (AUD-009)

`docker-compose.prod.yml` deklaruje każdy sekret jako `${VAR:?komunikat}`.
Brak/pusta wartość → `docker compose config|up` kończy się kodem ≠ 0 z
komunikatem zamiast cichego bootu na znanym defaultcie (`ChangeMeBeforeDeploy`,
`!ChangeMe!`, `minioadmin`, `masterKeyPleaseChangeMe`, …). Wzorzec skopiowany
z istniejącej usługi `alertmanager`.

Baza (`docker-compose.yml`) NADAL ma dev-friendly `${VAR:-default}` — dev musi
startować out-of-the-box (`pnpm stack:up`). Overlay produkcyjny nadpisuje tylko
klucze niosące sekrety na wariant fail-loud.

### Walidacja (non-destructive, bez startowania stacku)

```bash
# PRZED ustawieniem env — MUSI abortować (dowód że fallbacki nie przeciekają):
env -i PATH="$PATH" HOME="$HOME" docker compose \
  -f docker-compose.yml -f docker-compose.prod.yml config -q
# → exit 1: "required variable MEILI_MASTER_KEY is missing a value: ..."

# PO ustawieniu wszystkich sekretów — MUSI się zwalidować:
docker compose --env-file .env.prod \
  -f docker-compose.yml -f docker-compose.prod.yml config -q
# → exit 0
```

---

## 2. Dev-only usługi wyłączone w prod (AUD-046)

- **Mailpit** (łapacz maili dev) ma `profiles: ["dev-only"]` w overlayu prod →
  domyślny prod `up` (bez `--profile dev-only`) go pomija. W bazie Mailpit jest
  bez profilu, więc dev `docker compose config` go pokazuje. Ten sam mechanizm
  co `admin`.
- **Meilisearch** w prod overlayu ma `MEILI_ENV: production` (baza: `development`).
  W trybie `production` master key jest WYMAGANY, a deweloperskie UI/preview
  wyłączone. Dlatego `MEILI_MASTER_KEY` jest fail-loud w prod.

Weryfikacja:

```bash
# prod NIE zawiera mailpit:
docker compose --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml \
  config --services | grep -c '^mailpit$'   # → 0
# dev ZAWIERA mailpit:
docker compose config --services | grep -c '^mailpit$'   # → 1
```

---

## 3. Ustawianie sekretów infrastrukturalnych (env)

1. `cp .env.prod.example .env.prod` na hoście produkcyjnym (NIE commituj —
   `.env.*` jest gitignored; tylko `*.example` jest trackowany).
2. Wygeneruj każdy sekret i wpisz do `.env.prod`:

   | Klucz | Generacja |
   | --- | --- |
   | `APP_SECRET` | `php -r 'echo bin2hex(random_bytes(16));'` |
   | `POSTGRES_PASSWORD` / `APP_DB_PASSWORD` | `openssl rand -base64 24` |
   | `MERCURE_JWT_SECRET` | `openssl rand -hex 32` (≥256 bit) |
   | `MEILI_MASTER_KEY` | `openssl rand -base64 32` |
   | `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` | `openssl rand -base64 24` |
   | `ALERTMANAGER_WEBHOOK_URL` | URL receivera (PagerDuty/Slack/…) |

3. Zwaliduj (`config -q`, sekcja 1) → dopiero potem `up -d`.

### Rotacja sekretu infrastrukturalnego

1. Wygeneruj nową wartość, podmień w `.env.prod`.
2. Dla `POSTGRES_PASSWORD` / `APP_DB_PASSWORD`: `ALTER ROLE ... WITH PASSWORD`
   na żywej bazie (lub pozwól `pim-init-app-role.sh` zsynchronizować
   `APP_DB_PASSWORD` przy restarcie kontenera `database`).
3. Dla `MEILI_MASTER_KEY` / `MINIO_ROOT_*`: rotacja wymaga restartu usługi i
   re-indeksacji / re-konfiguracji klienta — zaplanuj okno.
4. `MERCURE_JWT_SECRET`: rotacja unieważnia aktywne subskrypcje SSE (klienci
   się reconnectują z nowym cookie autoryzacyjnym).
5. `config -q` → `up -d` dotkniętych usług.

---

## 4. Symfony Secrets Vault (AUD-047) — application-level sekrety

Vault żyje w `apps/api/config/secrets/<env>/`. Framework-bundle ma sekrety
włączone domyślnie (`vault_directory:
%kernel.project_dir%/config/secrets/%kernel.runtime_environment%`), bez
dodatkowej konfiguracji.

### Pliki vaulta i co commitować

| Plik | Commit? | Rola |
| --- | --- | --- |
| `<env>.encrypt.public.php` | **TAK** | klucz publiczny — każdy może DODAĆ sekret |
| `<env>.list.php` | **TAK** | lista nazw sekretów |
| `<NAME>.<hash>.php` | **TAK** | szyfrogram AES danego sekretu |
| `<env>.decrypt.private.php` | **NIGDY** | klucz prywatny — dekryptuje wszystko |

`.gitignore` (root) wymusza to regułą
`**/config/secrets/**/*.decrypt.private.php` (ignoruje klucze prywatne **obu**
środowisk, dev i prod). Framework-bundle dorzuca dodatkowo
`apps/api/.gitignore` ignorujący `prod.decrypt.private.php` (defence in depth).

> **Odejście od defaultu Symfony:** standardowo `dev.decrypt.private.php` jest
> commitowany (współdzielony zespołowo do dekrypcji dev). Tu — projekt
> single-operator, polityka „NIE commituj prywatnych kluczy decrypt" —
> ignorujemy klucz prywatny dev też. Skutek: świeży checkout może DODAWAĆ
> sekrety dev (public key trackowany), ale do ich ODCZYTANIA potrzebuje
> lokalnego `dev.decrypt.private.php` (regeneracja: `secrets:generate-keys
> --env=dev`, potem ponowne `secrets:set`). Akceptowalne dla solo-dev.

### Bootstrap (jednorazowo per środowisko)

```bash
# klucze (public commitowalny, private ignorowany):
docker compose exec -T api php bin/console secrets:generate-keys --env=dev
docker compose exec -T -e APP_ENV=prod api php bin/console secrets:generate-keys --env=prod
```

### Dodanie / odczyt / usunięcie sekretu

```bash
# dodaj (stdin, bez echo w historii shella):
printf '%s' "$VALUE" | docker compose exec -T api php bin/console secrets:set ANTHROPIC_API_KEY --env=prod
docker compose exec -T api php bin/console secrets:list --env=prod          # nazwy
docker compose exec -T api php bin/console secrets:list --reveal --env=prod # wartości (ostrożnie)
docker compose exec -T api php bin/console secrets:remove ANTHROPIC_API_KEY --env=prod
```

Symfony rozwiązuje `%env(ANTHROPIC_API_KEY)%` najpierw z prawdziwego env, a gdy
brak — z vaulta. Czyli sekret z vaulta jest dostępny dla kodu identycznie jak
env var, bez zmian w `services.yaml`.

### Dekrypcja na deployu

Klucz prywatny prod NIE jest w repo. Na hoście prod dostarcz go jednym z:

- **Env (zalecane):** ustaw `SYMFONY_DECRYPTION_SECRET` na zawartość klucza
  prywatnego (base64). Symfony zdekryptuje vault bez pliku na dysku:
  ```bash
  export SYMFONY_DECRYPTION_SECRET="$(grep -oP 'SYMFONY_DECRYPTION_SECRET=\K.*' prod.decrypt.private.php)"
  ```
  (przekaż jako secret do orkiestratora — Docker/K8s secret, nie plik w repo).
- **Plik:** zamontuj `config/secrets/prod/prod.decrypt.private.php` do kontenera
  jako read-only secret mount.
- **Build-time (alternatywa):** `composer dump-env prod` kompiluje `.env.local.php`
  — używaj tylko jeśli budujesz immutable image z sekretami wstrzykiwanymi w CI.

### Rotacja klucza vaulta

```bash
docker compose exec -T -e APP_ENV=prod api php bin/console secrets:generate-keys --rotate --env=prod
```
`--rotate` re-szyfruje wszystkie istniejące sekrety nowym kluczem. Zredeployuj
nowy `SYMFONY_DECRYPTION_SECRET` i zacommituj zmienione szyfrogramy + nowy
public key (klucz prywatny dalej out-of-band).

---

## 5. Guard — żaden prawdziwy sekret w gicie

`scripts/lint-tracked-secrets.sh` (uruchamiany w CI) failuje gdy:
- (A) nie-template `.env.<env>` jest trackowany, lub
- (B) trackowany plik `.env*` zawiera wysokoentropijną wartość na wrażliwym
  kluczu (prawdziwy sekret zamiast placeholdera).

`.env.prod.example` zawiera wyłącznie placeholdery `__CHANGE_ME...__`
(rozpoznawane jako placeholder), więc guard przechodzi. Klucze prywatne vaulta
nie są `.env*`, więc ich entropia nie dotyczy guarda — chroni je `.gitignore`.

```bash
bash scripts/lint-tracked-secrets.sh   # musi przejść (exit 0)
```

---

## Zakres tego runbooka vs. external secret manager

Vault Symfony + `.env.prod` to baseline MVP/Faza-1 (jeden host, docker-compose).
Przy multi-tenant SaaS (Faza 2) lub wymogach SOC 2 (Faza 3) rozważ external
manager (Vault HashiCorp / AWS Secrets Manager / GCP Secret Manager) jako źródło
`SYMFONY_DECRYPTION_SECRET` + sekretów infrastrukturalnych, z rotacją sterowaną
przez managera. Wzorzec env/`${VAR:?}` z sekcji 1 pozostaje — zmienia się tylko
*skąd* env jest wstrzykiwany (sidecar/agent zamiast `.env.prod`).
