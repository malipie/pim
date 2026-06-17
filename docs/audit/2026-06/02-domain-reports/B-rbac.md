# Raport domenowy B — Autoryzacja / RBAC

Data: 2026-06-16
Audytor: subagent domeny B (adwersarski audyt przed-SaaS)
Postawa: adwersarz. Każdy finding ma dowód (plik:linia / output / response HTTP). Stack żywy: https://pim.localhost.

---

## 1. Metodyka — co sprawdzono i jak

### Źródła statyczne (read-only)
- `apps/api/config/packages/security.yaml` (firewalle, access_control, PUBLIC_ACCESS) — odczyt pełny.
- `apps/api/config/routes/security.yaml` — odczyt pełny.
- `docs/rbac.md`, `Project Plan/PRD/PRD-PIM-rbac.md` — model docelowy (§3.2 macierz, §3.5 attribute permissions).
- `apps/api/src/Identity/Domain/Rbac/RbacMatrix.php` — source of truth ról/permission.
- `raw/routes.txt` (318 linii, pełny dump `debug:router`) — inwentarz endpointów.
- `raw/phpstan.txt` (No errors, level max, baseline pusty `ignoreErrors: []`).
- `raw/semgrep-byrule.txt` (0 findings z 290 reguł; 4 błędy parsowania TS po stronie admin — luka pokrycia FE).

### Mechanizmy egzekwowania — przeczytane w całości
- `Identity/Infrastructure/Http/EndpointGuardListener.php` (runtime guard `#[RequiresPermission]`).
- `Identity/Contracts/Attribute/RequiresPermission.php`, `NoPermissionRequired.php`.
- `PHPStan/Rules/RequiresPermissionAnnotationRule.php` (statyczny gate „każdy `#[Route]` ma atrybut").
- `Identity/Application/PermissionResolver.php` (SQL union ról + cache).
- `Identity/Infrastructure/Security/AbstractRbacVoter.php` + `AbstractPrdVoter.php` (tenant boundary w voterach).
- `Identity/Application/Policy/AttributePermissionPolicy.php` + `Serializer/FieldRestrictionFilter.php` (3-state attribute permissions).
- `Catalog/Infrastructure/ApiPlatform/Resource/CatalogObject.xml` + `ObjectType.xml` (security expression per operacja).
- Tokeny: `PasswordResetService.php`, `PasswordResetController.php`, `InvitationController.php`, `MagicLinkTokenHasher.php`, `RefreshTokenService.php`, `RbacApiTokenAuthenticator.php`.
- SSO: `SsoCallbackController.php`, `Sso/GoogleAuthProvider.php`, `Sso/SamlAuthProvider.php`.
- MFA: `LoginSuccessHandler.php`, `MfaLoginController.php`.
- Super Admin: `SuperAdminTenantsController.php`, `SuperAdminTenantWriteController.php`, `SuperAdmin/SuperAdminContext.php`, `BreakGlassController.php`.
- IDOR: `UserUpdateController.php`, `RevokeApiTokenController.php`, `Prd/ApiTokenVoter.php`, `PreviewAssetController.php`.
- SPA: `apps/admin/src/lib/http.ts` (storage tokenu).

### Empiryka (na żywym stacku)
- Login `admin@demo.localhost / changeme` → JWT (554 znaki, dekodowany payload).
- `GET /api/admin/tenants` jako demo super_admin → **HTTP 200, widoczne 2 tenanty (acme + demo)**.
- `GET /api/admin/tenants/{acme_id}` jako demo → **HTTP 200** (cross-tenant detail).
- `GET /api/users` jako demo → 1 user (tylko demo — TenantFilter działa dla domeny).
- `GET /api/products` bez tokenu → HTTP 401 (firewall OK).
- DB (read-only SELECT): 2 tenanty; `super_admin` rola GLOBALNA (tenant_id NULL, 1 wpis); `super_admin` to jedyna rola z `user.admin` + `tenant.*` + `role.*`; `admin@demo` i `admin@acme` oba mają przypisaną TĘ SAMĄ globalną rolę super_admin.

### Czego NIE dało się sprawdzić (luki audytu)
- **Empiryczna eskalacja cross-tenant WRITE** (`POST/DELETE /api/admin/tenants/{acme}/suspend`) — guardrail read-only zabronił mutacji; potwierdzone wyłącznie z kodu (gating `user.admin`, brak platform-vs-tenant gate). Confidence findings poniżej = probable dla write-path.
- **Negatywna kontrola cross-tenant** (czy zwykła per-tenant rola viewer NIE może cross-tenant) — brak drugiego loginu (mam tylko admin@demo); wnioskuję z matrix (`user.admin` tylko na global super_admin).
- **Pełen przebieg SSO/SAML** na żywym IdP — brak skonfigurowanego providera; walidacja podpisu oceniona z kodu (`wantAssertionsSigned=true`).
- **Realny payload eksportu vs attribute permissions** — Export nie uruchomiony end-to-end; brak konsumenta attribute-policy potwierdzony grepem (0 trafień w `Export/`).
- **Pokrycie Semgrep dla 2 plików FE** (`object-types/list.tsx`, `show.tsx`) — parser TS padł (syntax error w raw output); FE permission-gating tych widoków nieobjęte skanem.
- **PHPStan rule a route'y bez `#[Route]`** — rule łapie tylko publiczne metody z `#[Route]`. Endpointy API Platform (XML, bez `#[Route]`) NIE są łapane przez tę regułę; sprawdzone ręcznie (wszystkie 17+2 operacje mają `security=`).

---

## 2. Inwentarz route → permission (egzekwowanie)

### Mechanizm bazowy
Każdy endpoint pod `/api/*` jest chroniony dwuwarstwowo:
1. **Firewall** (`security.yaml:109`): `{ path: ^/api, roles: IS_AUTHENTICATED_FULLY }` — odrzuca anonim (zweryfikowane: `GET /api/products` bez tokenu = 401).
2. **`EndpointGuardListener`** + atrybut `#[RequiresPermission]` / `#[NoPermissionRequired]` na metodzie kontrolera (221 wystąpień RequiresPermission, 24 NoPermissionRequired) LUB **API Platform `security="is_granted(...)"`** dla zasobów XML.

Statyczny gate `RequiresPermissionAnnotationRule` wymaga, by KAŻDA publiczna metoda z `#[Route]` miała jeden z dwóch atrybutów. `raw/phpstan.txt` = „No errors", baseline `phpstan-baseline.neon` PUSTY (`ignoreErrors: []`). **Wniosek: deklarowana w CLAUDE.md „luka Phase 6 retrofit ~60–130 endpointów" jest na poziomie kontrolerów z `#[Route]` ZAMKNIĘTA** — żaden taki endpoint nie jest niezaanotowany (inaczej CI byłby czerwony). To pozytyw zweryfikowany empirycznie.

### Tabela (reprezentatywne grupy — pełen dump w raw/routes.txt)

| Endpoint | Wymagana permission | Egzekwowane? | Jak |
|---|---|---|---|
| `GET/POST/PATCH/DELETE /api/products` (+categories/objects) | object.read/write/delete | TAK | API Platform `security=is_granted(READ/CREATE/UPDATE/DELETE, ...)` → CatalogObjectVoter (CatalogObject.xml:112-486) |
| `GET /api/object_types{,/{id}}` | object_type → faktycznie `object.read`? | TAK (z uwagą) | `is_granted('READ', ObjectType)` → ObjectTypeVoter (ObjectType.xml:40,43) |
| `POST /api/users`, `PATCH /api/users/{id}`, deactivate | user.admin | TAK | `#[RequiresPermission(user, admin)]` (UserUpdateController:60) + `loadTargetInSameTenant` |
| `POST/PATCH/DELETE /api/roles`, attribute-permissions | user.admin | TAK | `#[RequiresPermission(user, admin)]` (RoleWriteController:53,111,187) |
| `GET/POST/PATCH/DELETE /api/admin/tenants/*` | user.admin | TAK (ale patrz B-01) | `#[RequiresPermission(user, admin)]` (SuperAdminTenants:62,93; SuperAdminTenantWrite:80,170,224,249,274) |
| `POST /api/admin/break-glass` | user.admin + audit | TAK | `#[RequiresPermission(user, admin)]` (BreakGlassController:82), audyt każdej próby |
| `POST /api/invitations` | settings.users.manage | TAK | `#[RequiresPermission(settings, users.manage)]` (InvitationController:42) |
| `GET /api/assets/{id}/preview` | brak (PUBLIC_ACCESS) | NIE (celowo) | `#[NoPermissionRequired]`, TenantFilter wyłączony, path-knowledge UUIDv7 (patrz B-04) |
| `POST /api/auth/login`, `/refresh`, `/2fa/login`, `/password-reset/*`, `/invitations/{token}/accept|verify`, `/sso/*`, `/api/me`, `/logout`, `/api/auth/2fa/*` | brak (PUBLIC lub self) | NIE (celowo) | PUBLIC_ACCESS + `#[NoPermissionRequired]` z uzasadnieniem |
| `GET /api/_test/guarded|public` | object.delete / brak | TAK (tylko dev/test) | `#[When(env: dev/test)]` — poza prod container (TestGuardedController:25-26) |
| `GET /api/metrics` | brak (PUBLIC) | NIE (celowo) | Network ACL/Caddy basic-auth zamiast RBAC |

**Endpointów domenowych BEZ ochrony: 0** wśród kontrolerów z `#[Route]` (gwarancja PHPStan) i 0 wśród operacji API Platform XML (zweryfikowane: CatalogObject 17/17, ObjectType 2/2 mają `security=`).

---

## 3. Findings

### B-01 [CRITICAL] Współdzielona globalna rola `super_admin` daje każdemu Ownerowi tenanta cross-tenant dostęp do wszystkich tenantów (multi-tenant breach)

**Dowód empiryczny.** Login `admin@demo.localhost` (tenant demo), następnie:
```
GET /api/admin/tenants  → HTTP 200
member: [ {code:"acme", name:"Acme Industries", active_users:1, ...},
          {code:"demo", ...} ]   totalItems: 2
GET /api/admin/tenants/019ebfbb-...-89499a (acme)  → HTTP 200
```
Demo Owner odczytał metadane i detal CUDZEGO tenanta (acme).

**Dowód strukturalny (DB, read-only SELECT).**
```
roles: super_admin | tenant_id IS NULL (global) | 1 wpis
permission user.admin → tylko rola super_admin
user_roles: admin@demo + admin@acme → OBA mają tę samą globalną super_admin
```
`RbacSeeder.php:103` tworzy `new Role(code, name)` bez tenanta = globalna. `AppFixtures.php:185` (`$admin->addRole($superAdmin)`) przypisuje TĘ globalną rolę do Ownera KAŻDEGO tenanta. Persona „tenant Owner" (Tomasz CEO, docs/rbac.md §38) dostaje globalnego super_admina.

**Brak rozróżnienia platform-vs-tenant.** `grep is_platform|isPlatform|platform_admin` w `Identity/` = 0 trafień. `SuperAdminTenantsController` + `SuperAdminTenantWriteController` (suspend/reactivate/delete/create) chronione WYŁĄCZNIE `#[RequiresPermission(user, admin)]` (SuperAdminTenantWrite:80,170,224,249,274). `SuperAdminContext::runCrossTenant` (SuperAdminContext.php:75) wyłącza Doctrine TenantFilter dla tych operacji.

**Atak.** Owner tenanta A (legalnie zalogowany, `user.admin` z globalnej roli) wywołuje `DELETE /api/admin/tenants/{B}` lub `/suspend` → kasuje/zawiesza konkurencyjnego klienta SaaS. Albo `GET /api/admin/tenants/{B}` → wyciek listy klientów + liczby userów (recon). W modelu SaaS to pełne złamanie izolacji najemców na płaszczyźnie administracyjnej.

**Rekomendacja.** Wprowadzić odrębną rolę platformową (np. `platform_operator`, tenant_id NULL, NIE przypisywana Ownerom) z dedykowaną permission `platform.tenants.manage`. `super_admin` per-tenant (tenant_id NOT NULL) dla Ownera = pełne uprawnienia W OBRĘBIE swojego tenanta. `/api/admin/*` gate na `platform.tenants.manage`, nie na `user.admin`. Fixtures: nie nadawać globalnej roli Ownerom.

---

### B-02 [HIGH] `token_dev_only` — plaintext token resetu hasła i zaproszenia zwracany w response BEZ guardu środowiska (wyciek na produkcji)

**Dowód.** `PasswordResetController.php:51-54`:
```php
return new JsonResponse([
    'status' => 'sent',
    'token_dev_only' => $plaintext, // null when email not found
]);
```
Brak `if ($this->environment === 'dev')` / `if (debug)`. Komentarz (linia 50) deklaruje „production drops this field", ale **kod tego nie robi**. Identycznie `InvitationController.php:78`: `'token_dev_only' => $result['token']` zawsze.

Brak listenera usuwającego pole (`grep token_dev_only|isDebug|kernel.environment` w obu kontrolerach = tylko same te wpisy).

**Atak.** Na produkcji: napastnik zna/zgaduje email ofiary → `POST /api/auth/password-reset/request` → response zawiera `token_dev_only` (256-bit token resetu) → `POST /api/auth/password-reset/confirm {token, new_password}` → przejęcie konta. Reset działa cross-tenant (token wiąże usera po email). Dla zaproszeń: napastnik z `settings.users.manage` widzi plaintext invite token → ale gorszy wektor to reset (publiczny endpoint, account-enumeration „safe" w intencji, ale token i tak wraca).

To dokładnie wzorzec z lekcji „Closed means closed" (`token_dev_only` w response ≠ produkcyjna gotowość).

**Rekomendacja.** Zwracać `token_dev_only` tylko gdy `kernel.environment !== 'prod'` (albo nigdy — token wyłącznie przez Mailer). Dopóki to nie jest naprawione, oba endpointy NIE są gotowe do produkcji.

---

### B-03 [HIGH] Brak rate-limitingu na publicznych endpointach auth poza login/refresh (brute-force, account enumeration, token brute)

**Dowód.** `config/packages/framework.yaml` definiuje limitery: `auth_login` (5/15min), `agent_run`, `integration_sync`, `backup_trigger`, import. Listenery konsumujące (`AuthLoginRateLimitListener.php:53` gate `=== '/api/auth/login'`, `AuthRefreshRateLimitListener.php:43` gate `=== '/api/auth/refresh'`) obejmują WYŁĄCZNIE te 2 ścieżki.

Brak limitera na:
- `POST /api/auth/password-reset/request` (enumeracja + spam mailowy),
- `POST /api/auth/password-reset/confirm` (brute-force tokenu — choć 256-bit czyni to teoretycznym),
- `POST /api/invitations/{token}/accept` + `/verify` (brute tokenu),
- `POST /api/auth/2fa/login` (**brute-force kodu TOTP — 6 cyfr = 10^6, krytyczne bez limitu**).

**Atak.** `2fa/login`: po kroku hasła napastnik z ważnym `mfa_token` (krótko żyjącym) może próbować kody TOTP bez limitu prób → przy braku limitu/lockoutu MFA staje się bezwartościowe (10^6 kombinacji, kod ważny ~30–90s, ale brak limitu pozwala na masową próbę w oknie).

**Rekomendacja.** Dedykowane limitery: `2fa/login` (np. 5 prób / 15 min / mfa_token lub IP, potem unieważnienie challenge), `password-reset/request` (per-IP + per-email), `invitations/*/accept` (per-IP). Weryfikować lockout po N nieudanych TOTP.

---

### B-04 [HIGH] Attribute-level (3-state) permissions NIE są egzekwowane na ścieżce danych — ani read wartości, ani PATCH, ani export

**Dowód.** `FieldRestrictionFilter.php` (kompozycja policy + integration_visible) ma docstring (linie 35-38): *„Wiring per endpoint (CatalogObject serializer hook, ApiToken response normaliser, audit log filter) is the Phase 6 retrofit's responsibility — this filter is the building block."* Jego JEDYNY konsument w kodzie to towarzyszący `RestrictedField.php` (grep `FieldRestrictionFilter` poza Policy/ = tylko RestrictedField.php).

Realni konsumenci `canViewAttribute/canEditAttribute/resolvePermission` (grep całego repo):
- `GetObjectTypeListSchemaHandler.php:64` — filtruje SCHEMAT formularza (ukrywa kolumny `restricted`).
- `ProductVoter` (broad gate, nie per-atrybut).
- `SecurityAttributePermissionReader` (adapter).

**Brak filtrowania w:**
- `CatalogObjectProcessor.php` (write PATCH) — `grep canEdit|AttributePermission|FieldRestriction` = 0. Pole `attributes` zapisywane w całości (linie 122, 175).
- Serializerze CatalogObject (GET zwraca pełen `attributes`) — brak normalizera filtrującego.
- **Export** — `grep AttributePermission|PermissionResolver` w `Export/` = 0 trafień. Eksport CSV/XLSX NIE respektuje attribute-level permissions.

**Atak.** Użytkownik z attribute-permission `view` (lub `restricted`) na wrażliwym atrybucie (np. cena zakupu, marża): (a) odczyta go i tak przez `GET /api/products/{id}` (surowe `attributes`), (b) **zmodyfikuje go przez `PATCH /api/products/{id}` mimo braku prawa edit**, (c) wyeksportuje go do CSV. PRD §3.5 (`restricted` → pole NIE w response) jest spełnione tylko w schemacie listy, nie w samych danych.

**Rekomendacja.** Wpiąć `FieldRestrictionFilter` w normalizer odpowiedzi CatalogObject (read) i bramkę edit w `CatalogObjectProcessor` (odrzuć/zignoruj pola bez `canEdit`). Export musi przejść przez ten sam policy przed wypisaniem kolumn. Do tego czasu attribute-level permissions to feature pozorny (UI ukrywa, API nie chroni).

---

### B-05 [MEDIUM] PreviewAsset — publiczny streaming z wyłączonym TenantFilter, brak ownership check (cross-tenant leak przy znajomości UUID)

**Dowód.** `PreviewAssetController.php:53` `#[NoPermissionRequired]`, security.yaml:105 PUBLIC_ACCESS. Linie 58-69: `$filters->disable('tenant')` przed `findById`, po czym BRAK porównania `asset.tenant == ...` (anonim nie ma tenanta). Komentarz (linia 36-39) przyznaje: *„cross-tenant leakage requires [...]"* i poleganie na UUIDv7 128-bit jako jedynej barierze. Hardening signed-URL jawnie odłożony (#438 follow-up).

**Atak.** Kto zdobędzie UUID assetu cudzego tenanta (np. z logów, wycieku response z innego endpointu, przeglądarki innego użytkownika) → `GET /api/assets/{uuid}/preview` zwróci bajty bez weryfikacji tenanta/uprawnień. UUIDv7 nie jest sekretem kryptograficznym — zawiera timestamp, jest przekazywany w wielu response.

**Rekomendacja.** Signed URL (HMAC + TTL) jak zaplanowano w #438, albo wymagać auth + voter `READ asset` (jeśli `<img>` blokuje Bearer — użyć krótko żyjącego signed query param). Minimum: po lookup sprawdzić, że tenant assetu zgadza się z kontekstem żądania, gdy żądanie niesie jakikolwiek principal.

---

### B-06 [MEDIUM] PermissionResolver: legacy `user_roles` w UNION zawsze wnosi PUSTY scope (osłabia per-locale/channel restriction)

**Dowód.** `PermissionResolver.php:113-125` — SQL łączy `user_role_assignments` (ze scope) `UNION` `user_roles` (legacy) z literalnym `'[]'` jako locale/channel/attribute_group scope. `mergeScope` (linie 164-173): pusty scope = „brak ograniczenia" = wygrywa unię (most-permissive).

**Skutek.** Jeśli użytkownik ma rolę w legacy `user_roles` (Sprint-0 path — fixtures DEMO używają właśnie `user_roles`, potwierdzone: `admin@demo` widoczny w `user_roles`, `user_role_assignments` puste), jego scope locale/channel jest ZAWSZE pusty = brak restrykcji per-locale/channel. Granularna izolacja scope (PRD §3.2 per-locale/channel) jest neutralizowana dopóki migracja `#644` nie skonsoliduje obu tabel. To „defence in depth" które obecnie nie działa dla userów na legacy path.

**Rekomendacja.** Przyspieszyć konsolidację `user_roles` → `user_role_assignments` (#644) albo przenieść scope do legacy ścieżki. Do tego czasu nie polegać na per-locale/channel scope jako granicy bezpieczeństwa dla istniejących userów.

---

### B-07 [LOW] Drift macierzy RBAC: w DB istnieją role spoza `RbacMatrix` (admin, approver, tenant_owner, modeler, channel_manager, marketing) — niejasna powierzchnia uprawnień

**Dowód.** DB (SELECT): role `admin`, `approver`, `tenant_owner`, `channel_manager`, `marketing`, `modeler` (per-tenant, tenant_id NOT NULL) istnieją obok 4 z `RbacMatrix.php` (super_admin, catalog_manager, integration_manager, viewer). `RbacMatrix` deklaruje tylko 4. docs/rbac.md opisuje tylko 4. To role z `SeedTenantPrdRolesService` / `PrdPermissionFixtures` (PRD §3.2 macierz pełna 10 ról) — ale dokumentacja `docs/rbac.md` i `RbacMatrix` ich nie opisują, więc faktyczne uprawnienia np. `approver`/`channel_manager` nie są audytowalne z jednego źródła prawdy. `tenant_owner` (per-tenant) ma tylko `tenant.delete` — semantyka niejasna.

**Rekomendacja.** Zsynchronizować source-of-truth: albo wszystkie 10 ról w `RbacMatrix` + docs/rbac.md, albo jawnie udokumentować że `RbacMatrix` = built-in global, a PRD-role = per-tenant template (`SeedTenantPrdRolesService`). Audyt uprawnień wymaga jednej tabeli macierzy zgodnej z DB.

---

## 3a. Co zweryfikowano jako POPRAWNE (chwalę tylko z dowodem)

- **Firewall**: anonim na `/api/products` = HTTP 401 (zweryfikowane curl). PUBLIC_ACCESS lista zamknięta, każdy wpis uzasadniony (`#[NoPermissionRequired(reason: ...)]`, 24 wpisy z sensownym reason).
- **PHPStan gate**: baseline pusty, „No errors" → 0 niezaanotowanych endpointów z `#[Route]`. Deklarowana luka „~130 retrofit" w praktyce zamknięta dla kontrolerów.
- **Tenant boundary w voterach**: `AbstractRbacVoter:104` / `AbstractPrdVoter:109` — instance-level cross-tenant deny. `GET /api/users` jako demo = tylko 1 user demo (TenantFilter działa dla domeny).
- **MFA gate**: `LoginSuccessHandler.php:52-57` — przy `isTotpEnabled()` login NIE mintuje JWT/refresh, zwraca tylko `mfa_required + mfa_token`. JWT dopiero po `MfaLoginController` (po weryfikacji kodu). Brak bypassu „po haśle przed MFA".
- **Tokeny**: reset/magic-link 256-bit (`random_bytes(32)`), SHA-256 hash w DB, single-use (`markUsed` rzuca przy re-use), TTL 1h (reset). Refresh token: rotacja single-use, family-based theft detection (cała rodzina revoke przy reuse), 30d TTL, SHA-256. API token 128-bit + SHA-256.
- **OAuth state**: `SsoCallbackController.php:112` `hash_equals(cookieState, state)`; cookie httpOnly+secure+sameSite=Lax, path-scoped `/api/auth/sso`, TTL-limited, clearowane po sukcesie.
- **SAML**: `wantAssertionsSigned=true` (`SamlAuthProvider.php:132`), błędy z `getErrors()` blokują.
- **SPA token storage**: JWT w pamięci modułu (`http.ts:9` — „never localStorage"), refresh w httpOnly cookie path-scoped. Anty-XSS poprawne.
- **IDOR users/tokens**: `UserUpdateController` `loadTargetInSameTenant` + last-admin guard + self-edit blocked (409). `RevokeApiTokenController:72` jawny `caller.tenant == token.tenantId` defence-in-depth + ownership.
- **Break-glass**: `#[RequiresPermission(user, admin)]` + audyt każdej próby (sukces i porażka), `special_flags=["SUPER_ADMIN_RECOVERY"]`. Brak publicznego endpointu operatora.
- **Internal test routes**: `#[When(env: dev/test)]` — poza prod container.

---

## 4. Proponowane probe empiryczne (do potwierdzenia, confidence=probable)

1. **B-01 write**: jako admin@demo `POST /api/admin/tenants/{acme_id}/suspend` → spodziewane HTTP 200/204 (cross-tenant write). NIE wykonane (guardrail read-only). Potwierdzi pełen impact (kasowanie cudzego tenanta).
2. **B-02 prod**: na buildzie prod (`APP_ENV=prod`) `POST /api/auth/password-reset/request {email: ofiara}` → sprawdzić czy `token_dev_only` != null w response. Kod sugeruje TAK.
3. **B-03 2fa brute**: user z TOTP, krok hasła → `mfa_token`; pętla `POST /api/auth/2fa/login` z błędnymi kodami → sprawdzić brak HTTP 429 / brak unieważnienia challenge po N prób.
4. **B-04 export/PATCH**: nadać roli attribute-permission `view` na atrybucie X; jako ten user `PATCH /api/products/{id}` zmieniając X → spodziewane HTTP 200 (zapis przeszedł mimo braku edit) + export CSV zawiera X.
5. **B-05 preview**: zdobyć UUID assetu acme (np. z DB), jako anonim `GET /api/assets/{uuid}/preview` → spodziewane HTTP 200 z bajtami (cross-tenant).
