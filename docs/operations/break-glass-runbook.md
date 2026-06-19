# Break-glass runbook — odzyskiwanie zablokowanego dostępu

> AUD-077 (W3-5.6). Procedura awaryjnego odzyskania dostępu, gdy normalny
> self-service nie zadziała: zablokowany jedyny Owner tenanta, password reset
> bez działającego maila, lockout MFA, lockout rate-limitera.
>
> Każdy krok poniżej odwołuje się do **rzeczywistego** kodu w `apps/api`
> (kontroler / komenda / endpoint zweryfikowany w źródle). Tam gdzie scenariusz
> nie ma automatyzacji, jest to wyraźnie oznaczone „**brak automatyzacji —
> procedura manualna**" zamiast wymyślonego endpointu.

## TL;DR — który mechanizm do którego problemu

| Problem | Mechanizm | Interfejs | Kto |
| --- | --- | --- | --- |
| Jedyny Owner odszedł / zablokowany — trzeba nadać komuś `tenant_owner` | break-glass „rescue admin" | `POST /api/admin/break-glass` **lub** CLI `cortex:rescue-admin` | Platform Operator |
| Owner zna login, ale nie ma działającego maila do resetu hasła | password-reset z tokenem w odpowiedzi (dev) **lub** ręczny UPDATE hasła (prod, brak maila) | `POST /api/auth/password-reset/*` / procedura manualna | Platform Operator |
| Owner zablokowany przez MFA (utracił authenticator + kody zapasowe) | **brak automatyzacji** — manualny reset `totp_secret`/`totp_enabled_at` w DB | procedura manualna | Platform Operator |
| Lockout rate-limitera logowania (HTTP 429 na `/api/auth/login`) | reset bucketów limitera per IP | CLI `pim:security:unblock-ip` | Operator |

> **Uwaga o uprawnieniach.** Authority do break-glass NIE niesie rola
> `super_admin` (po AUD-003/#1575 ma ją każdy tenant Owner). Niesie ją wyłącznie
> uprawnienie platformowe `platform.break_glass_recovery`, które posiada tylko
> dedykowana globalna rola `platform_operator`. W całym dokumencie „Super Admin"
> = principal z rolą `platform_operator`.

---

## 0. Prerekwizyty — kim jest „Platform Operator" i jak się uwierzytelnić

Break-glass wykonuje **Platform Operator** — osobny principal od jakiegokolwiek
Ownera tenanta. W seedzie (`AppFixtures`) jest to użytkownik
`platform-operator@cortex.localhost`, który ma TYLKO globalną rolę
`platform_operator` (czyli komplet grantów `platform.*`, w tym
`platform.break_glass_recovery`) i żadnej roli tenant-scoped.

Hasło seedowe (dev/test): `changeme` (stała `DEFAULT_ADMIN_PASSWORD` w
`AppFixtures`). **W produkcji to hasło MUSI być zmienione** — patrz
`docs/operations/secrets-runbook.md`.

Logowanie po JWT (firewall `login`, `POST /api/auth/login`, JSON `{email,
password}` — zweryfikowane w `config/packages/security.yaml`):

```bash
# Zwraca {"token": "<JWT>", ...}. Zapamiętaj token do nagłówka Authorization.
TOKEN=$(curl -s https://pim.localhost/api/auth/login \
  -H 'content-type: application/json' \
  -d '{"email":"platform-operator@cortex.localhost","password":"changeme"}' \
  | python3 -c 'import sys,json; print(json.load(sys.stdin)["token"])')
echo "$TOKEN"
```

> Jeśli **sam Platform Operator** ma włączone MFA i jest nim zablokowany,
> najpierw odblokuj jego MFA wg sekcji 3 (procedura manualna na DB), a dopiero
> potem loguj się tu po JWT.

Sanity-check uprawnienia (200 = masz `platform.break_glass_recovery`; 403 =
zalogowany principal nie ma roli `platform_operator`):

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://pim.localhost/api/admin/break-glass/usage \
  -H "Authorization: Bearer $TOKEN"
```

Kontener API do komend CLI (`docker compose exec`):

```bash
docker compose exec api php bin/console <komenda>
```

---

## 1. Scenariusz: zablokowany / nieobecny jedyny Owner tenanta

**Kiedy.** Jedyny `tenant_owner` odszedł bez przekazania, jest zablokowany, albo
nikt w tenancie nie ma już uprawnień administracyjnych. Cel: nadać wskazanemu
istniejącemu userowi rolę `tenant_owner` w jego tenancie (cross-tenant move
userów jest poza zakresem — user musi już należeć do tego tenanta).

To robi `BreakGlassController` (`POST /api/admin/break-glass`) — bliźniak CLI
`cortex:rescue-admin`. Oba kończą identycznym wpisem audytu.

### 1A. Wariant HTTP (rekomendowany — ma weryfikację MFA + rate limit)

Wymaga, by **Platform Operator miał włączone MFA** i podał poprawny kod TOTP lub
backup. Kontroler weryfikuje kolejno (zweryfikowane w `BreakGlassController::invoke`):
walidacja pól → reason ≥ 10 znaków → rate limit 5/24h → MFA włączone → kod
poprawny → tenant istnieje → user istnieje → user należy do tenanta → rola
`tenant_owner` zaseedowana.

Najpierw sprawdź budżet (5 prób / 24h / operator):

```bash
curl -s https://pim.localhost/api/admin/break-glass/usage \
  -H "Authorization: Bearer $TOKEN"
# -> {"used":0,"limit":5,"remaining":5,"window_hours":24,"recent_invocations":[]}
```

Wykonaj rescue:

```bash
curl -s https://pim.localhost/api/admin/break-glass \
  -H "Authorization: Bearer $TOKEN" \
  -H 'content-type: application/json' \
  -d '{
        "tenant_code": "demo",
        "user_email": "new-owner@demo.localhost",
        "reason": "Jedyny Owner zablokowany — ticket OPS-123, autoryzacja CTO",
        "mfa_totp": "123456"
      }'
```

**Oczekiwany sukces (200):**

```json
{
  "audit_id": "0192...rfc4122",
  "tenant": { "id": "...", "code": "demo" },
  "user":   { "id": "...", "email": "new-owner@demo.localhost" },
  "role_assigned": "tenant_owner"
}
```

**Częste odpowiedzi błędne (wszystkie audytowane, część liczy się do budżetu):**

| HTTP | `code` | Znaczenie |
| --- | --- | --- |
| 400 | — | brak pola / reason < 10 znaków |
| 428 | `mfa_required` | Platform Operator nie ma włączonego MFA |
| 422 | `mfa_invalid` | zły kod TOTP/backup (liczy się do budżetu 5/24h) |
| 429 | `rate_limit_exceeded` | wyczerpany budżet 5/24h |
| 404 | `tenant_not_found` / `user_not_found` | zła `tenant_code` / `user_email` |
| 409 | `user_tenant_mismatch` | user nie należy do tego tenanta |
| 409 | `role_not_seeded` | brak roli `tenant_owner` — uruchom `cortex:tenant:seed-roles` |

`role_not_seeded` naprawiasz seedem ról PRD dla tenanta (po UUID tenanta —
zweryfikowane w `SeedTenantPrdRolesCommand`), potem powtórz rescue:

```bash
docker compose exec api php bin/console cortex:tenant:seed-roles <TENANT_UUID>
```

**Weryfikacja sukcesu.** User powinien móc się zalogować i mieć uprawnienia
Ownera. Szybkie potwierdzenie po stronie operatora — wpis pojawia się w
`recent_invocations`:

```bash
curl -s https://pim.localhost/api/admin/break-glass/usage \
  -H "Authorization: Bearer $TOKEN"
# recent_invocations[0].outcome == "super_admin_bypass", target_user == e-mail
```

### 1B. Wariant CLI (emergency bez UI / gdy operator nie ma MFA)

`cortex:rescue-admin` (komenda `RescueAdminCommand`) wykonuje ten sam flow przez
`SuperAdminContext::runCrossTenant`, ale **bez weryfikacji TOTP i bez rate
limitu** — guard rails są w docblocku oznaczone jako deferred do follow-upu.
Dlatego wariant CLI traktuj jako break-glass „twardy", wymagający fizycznego
dostępu do hosta.

Argumenty (zweryfikowane w `configure()`): `email` i `tenant-slug` pozycyjnie,
`--super-admin-id` (UUID, **wymagany** — trafia do audytu), `--reason`.

`--super-admin-id` to UUID Platform Operatora wykonującego akcję. Pobierz go z DB
(po e-mailu seedowym):

```bash
docker compose exec database psql -U pim -d pim \
  -c "SELECT id FROM users WHERE email = 'platform-operator@cortex.localhost';"
```

Wykonaj rescue:

```bash
docker compose exec api php bin/console cortex:rescue-admin \
  'new-owner@demo.localhost' 'demo' \
  --super-admin-id='<UUID_z_powyzej>' \
  --reason='Break-glass CLI — OPS-123, brak działającego UI'
```

**Oczekiwany sukces:** `[OK] Granted tenant_owner role to new-owner@demo.localhost
in tenant "demo". Audit entry <uuid> recorded.` (exit 0).

**Błędy** kończą się exit 1 i wpisem audytu z `RESCUE_FAILED`: `tenant_not_found`,
`user_not_found`, `user_tenant_mismatch`, `role_not_seeded` (komunikaty jak w
tabeli 1A).

---

## 2. Scenariusz: reset hasła bez działającego maila

**Kiedy.** Owner zna swój login, ale nie odbierze maila resetowego (brak skonfigurowanego
SMTP w prod / niedostępna skrzynka).

Endpointy `POST /api/auth/password-reset/request` i `/confirm` są PUBLIC_ACCESS
(token JEST czynnikiem auth — zweryfikowane w `security.yaml` i
`PasswordResetController`). Token jest hashowany (SHA-256), TTL 1h, jednorazowy.

### 2A. Dev/test — token zwracany w odpowiedzi

W środowisku **innym niż `prod`** odpowiedź `request` zawiera pole
`token_dev_only` (trait `DevTokenExposure`, gating po `%kernel.environment%`).
Pozwala to dokończyć reset bez skrzynki:

```bash
# 1) Poproś o reset — w dev zwróci token_dev_only
TOK=$(curl -s https://pim.localhost/api/auth/password-reset/request \
  -H 'content-type: application/json' \
  -d '{"email":"owner@demo.localhost"}' \
  | python3 -c 'import sys,json; print(json.load(sys.stdin).get("token_dev_only",""))')

# 2) Ustaw nowe hasło (min 8 znaków)
curl -s https://pim.localhost/api/auth/password-reset/confirm \
  -H 'content-type: application/json' \
  -d "{\"token\":\"$TOK\",\"password\":\"NoweHaslo123\"}"
# -> {"user_id":"...","email":"owner@demo.localhost","status":"password-updated"}
```

> **Mailpit (dev).** Mail resetowy i tak ląduje w Mailpit (`htmlTemplate
> email/password-reset.html.twig`). Możesz wziąć link z Mailpit UI zamiast
> `token_dev_only` — link ma postać `https://pim.localhost/password-reset/<token>`.

### 2B. Produkcja bez maila — brak automatyzacji, procedura manualna

W `prod` pole `token_dev_only` jest **celowo usuwane** (AUD-007/#1577 — leak =
account-takeover na PUBLIC_ACCESS), a bez działającego SMTP token nigdzie nie
dotrze. **Brak automatyzacji** wystawienia resetu hasła operatorowi (nie ma
komendy CLI typu `reset-password` — w `src` istnieje tylko domenowe
`User::changePassword()`, niepodpięte pod żaden console command).

Procedura manualna (host z dostępem do bazy):

1. Wygeneruj poprawny hash hasła **algorytmem aplikacji** (Argon2id wg konfiguracji
   hashera). NIE wstawiaj plaintextu. Najprościej z kontenera API (Symfony użyje
   tego samego enkodera co runtime):

   ```bash
   docker compose exec api php bin/console security:hash-password
   # podaj nowe hasło interaktywnie -> skopiuj wynikowy hash $argon2id$...
   ```

2. Wstaw hash do wiersza usera (kolumna `password`):

   ```bash
   docker compose exec database psql -U pim -d pim -c \
     "UPDATE users SET password = '<HASH_ARGON2ID>' WHERE email = 'owner@demo.localhost';"
   ```

3. Poproś usera o natychmiastową zmianę hasła po zalogowaniu (lub wymuś flagę —
   w `User` istnieje `markPasswordChangeRequired()` / kolumna change-required).

> **Lepsza droga niż manualny UPDATE** — jeśli problem dotyczy Ownera tenanta,
> rozważ scenariusz 1 (nadanie `tenant_owner` innemu, sprawnemu userowi) zamiast
> grzebania w hashu hasła. Manualny UPDATE hasła to ostateczność, gdy odzyskać
> trzeba konkretne konto.

---

## 3. Scenariusz: lockout MFA (utracony authenticator + kody zapasowe)

**Kiedy.** User ma włączone TOTP, utracił authenticator ORAZ wszystkie kody
zapasowe — nie zaloguje się przez drugi czynnik.

**Brak automatyzacji administracyjnej.** Endpointy 2FA
(`/api/auth/2fa/disable`, `/api/me/mfa/recovery-codes/regenerate` —
`TwoFactorController`) są **wyłącznie self-service**: wymagają poprawnego kodu
TOTP/backup *tego samego* usera (proof-of-possession). Nie istnieje endpoint ani
komenda CLI, którą Platform Operator zdejmie MFA innemu userowi. W domenie jest
metoda `User::disableTotp()` (zeruje `totp_secret` + `totp_enabled_at`), ale nie
jest podpięta pod żaden kontroler/komendę operatorską.

Procedura manualna (host z dostępem do bazy) — odpowiednik `disableTotp()`:

```bash
docker compose exec database psql -U pim -d pim -c \
  "UPDATE users
      SET totp_secret = NULL,
          totp_enabled_at = NULL,
          totp_backup_codes = '[]'::jsonb
    WHERE email = 'locked-user@demo.localhost';"
```

> Nazwy kolumn pochodzą z `User` (`totp_secret`, `totp_enabled_at`,
> `totp_backup_codes`). Po wykonaniu `isTotpEnabled()` zwraca false (bo
> `totp_enabled_at IS NULL`) i user loguje się samym hasłem.

**Weryfikacja sukcesu.** User loguje się bez kroku 2FA; opcjonalnie potwierdź
stan:

```bash
docker compose exec database psql -U pim -d pim -c \
  "SELECT email, totp_enabled_at FROM users WHERE email='locked-user@demo.localhost';"
# totp_enabled_at musi być NULL
```

**Post-recovery (obowiązkowe):** poproś usera o ponowne włączenie 2FA
(`POST /api/auth/2fa/enrol` → `/verify`) — patrz sekcja „Po odzyskaniu".

---

## 4. Scenariusz: lockout rate-limitera logowania (HTTP 429)

**Kiedy.** Logowanie zwraca `429 Too Many Requests` z nagłówkiem `Retry-After`
(limiter `auth_login` — 5 prób / okno). Dotyka wszystkich logujących się z danego
IP, dopóki okno nie wygaśnie.

To jest automatyzowane — komenda `pim:security:unblock-ip`
(`UnblockIpCommand`) resetuje bucket limiterów `auth_login` **i** `auth_refresh`
dla wskazanego IP:

```bash
docker compose exec api php bin/console pim:security:unblock-ip 203.0.113.7
# -> [OK] Rate-limiter buckets cleared for IP 203.0.113.7 on auth_login + auth_refresh.
```

**Weryfikacja sukcesu.** Kolejna próba logowania z tego IP nie zwraca już 429
(zwraca 200 przy dobrym haśle albo 401 przy złym — czyli limiter przepuścił
request do warstwy auth).

> Limiter password-reset (`password_reset_email` / `password_reset_ip`) to osobne
> buckety — `unblock-ip` ich NIE czyści (czyści tylko `auth_login` +
> `auth_refresh`). Reset password-reset limitera nie ma dedykowanej komendy.

---

## 5. Audyt — gdzie ląduje ślad i jak sprawdzić, kto użył break-glass

Każde użycie break-glass (HTTP i CLI), **łącznie z próbami nieudanymi**, zapisuje
wiersz w tabeli `audit_logs` (zweryfikowane w `BreakGlassController::recordAttempt`
i `RescueAdminCommand`):

- `action = 'rescue_admin'`
- `super_admin_id` = UUID Platform Operatora wykonującego akcję
- `cross_tenant_access = true`
- `special_flags @> '["SUPER_ADMIN_RECOVERY"]'` (CLI przy porażce dokłada
  `"RESCUE_FAILED"`)
- `permission_check_result`: `super_admin_bypass` (sukces) lub `denied` (porażka)
- `new_value` (JSON): `target_email`, `target_tenant`, `reason`, `outcome`
- `resource_type`: `api:admin:break-glass` (HTTP) lub `cortex:rescue-admin` (CLI)

### Kto i kiedy używał break-glass — zapytanie

```bash
docker compose exec database psql -U pim -d pim -c "
  SELECT created_at,
         super_admin_id,
         permission_check_result            AS outcome,
         new_value->>'target_email'         AS target_user,
         new_value->>'target_tenant'        AS target_tenant,
         new_value->>'reason'               AS reason,
         special_flags
    FROM audit_logs
   WHERE special_flags::jsonb @> '[\"SUPER_ADMIN_RECOVERY\"]'
   ORDER BY created_at DESC
   LIMIT 50;"
```

Szybki podgląd ostatnich 5 wywołań danego operatora bez SQL — endpoint
`GET /api/admin/break-glass/usage` zwraca `recent_invocations` (ostatnie 5 z 24h:
`audit_id`, `created_at`, `target_user`, `target_tenant`, `outcome`).

> Tabela `audit_logs` jest tenant-scoped przez RLS; powyższe `psql` wykonujesz
> jako rola DB (`pim`) poza warstwą aplikacji, więc widzisz wiersze wszystkich
> tenantów. Aplikacyjnie cross-tenant odczyt audytu wymaga `platform.audit.view_all`
> (rola `platform_operator`).

---

## 6. Po odzyskaniu — sprzątanie i powrót do normalnego flow

Break-glass to wyłom — domknij go:

1. **Rotacja sekretów dotkniętego konta.** Po manualnym UPDATE hasła (2B) lub
   resecie MFA (3) wymuś u usera zmianę hasła i ponowne włączenie 2FA
   (`/api/auth/2fa/enrol` → `/verify`). Jeśli ruszałeś hasło Platform Operatora —
   zmień je natychmiast (patrz `docs/operations/secrets-runbook.md`).
2. **Sprawdź, czy nadana rola jest zamierzona.** Po scenariuszu 1 zweryfikuj, że
   `tenant_owner` trafił do właściwej osoby i — jeśli to była tymczasowa eskalacja
   — odbierz ją, gdy stały Owner wróci. Odebranie roli idzie normalnym flow
   zarządzania użytkownikami (Settings UI / API users), nie break-glassem.
3. **Zamknij ślad operacyjnie.** Dopisz `audit_id` (z odpowiedzi 200 lub z
   `audit_logs`) do ticketu/incydentu, który autoryzował break-glass — `reason`
   w audycie powinien już ten ticket wskazywać.
4. **Re-enable normalnego dostępu.** Po scenariuszu 4 (unblock-ip) nie ma nic do
   cofania — bucket sam się ponownie napełnia przy kolejnych próbach. Po
   scenariuszach 1–3 upewnij się, że user wrócił do normalnego logowania
   (hasło + ewentualnie 2FA) bez dalszej interwencji operatora.

> **Zasada.** Każde użycie break-glass powinno wynikać z autoryzowanego incydentu,
> mieć opisowy `reason` w audycie i kończyć się rotacją tego, co break-glass
> tymczasowo obszedł (hasło / MFA / eskalacja roli).
