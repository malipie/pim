# Domena I — Frontend Security & Jakość (admin React 19 / Vite / Refine)

Audyt adwersarski, read-only. Data: 2026-06-16. cwd: `/Users/mlipieclocal/dev/PIM`.
Zakres: `apps/admin/src`, `apps/admin/package.json`, `docker/caddy/Caddyfile`, surowe outputy w `docs/audit/2026-06/raw/`.

## Metodyka — co i jak sprawdzono

1. **Storage tokenów / auth model** — `grep -rn "localStorage|sessionStorage|document.cookie|Authorization|Bearer|getToken|setToken|accessToken|refreshToken"` po `apps/admin/src`; pełen odczyt `lib/http.ts`, `lib/auth-provider.ts`, `lib/identity/use-identity.ts`, `lib/identity/restricted-field.ts`, helperów pobierających token (`lib/download.ts`, `lib/asset-upload.ts`, `features/exports/*`, `layout/bulk-sessions-popover.tsx`).
2. **Egzekwowanie uprawnień (UI-only vs backend)** — odczyt `components/identity/PermissionRoute.tsx`, `PermissionGate.tsx`; `grep -rn "<PermissionRoute"` (sprawdzenie realnego użycia, nie tylko definicji); odczyt stron wrażliwych `features/admin/break-glass/index.tsx`, `features/admin/tenants/*`, `features/settings/users|roles/*`; analiza routingu w `App.tsx` (linie 377–587).
3. **Wylogowanie / invalidacja** — odczyt `authProvider.logout()` (POST `/api/auth/logout` + `clearAccessToken()`), `check()`, `onError()`.
4. **pnpm audit** — `raw/pnpm-audit.txt` + `raw/pnpm-audit.json`; rozwiązanie zainstalowanych wersji z `pnpm-lock.yaml` i `node_modules/dompurify/package.json`.
5. **Stan błędów** — `grep -rln "ErrorBoundary|componentDidCatch|getDerivedStateFromError|errorElement"`; analiza `App.tsx` (Suspense vs error boundary), `main.tsx` (globalny handler); przegląd połykanych `catch`.
6. **XSS sinks** — `grep -rn "dangerouslySetInnerHTML|innerHTML|eval(|new Function("`; odczyt `components/catalog/wysiwyg-editor.tsx` (DOMPurify); sprawdzenie `target="_blank"` pod `rel=noopener/noreferrer`.
7. **Security headers** — odczyt `docker/caddy/Caddyfile` (blok `common_security`) + **live curl** `https://pim.localhost/` z weryfikacją realnie wysyłanych nagłówków.
8. **i18n leaks (próbka)** — porównanie spłaszczonych kluczy `locales/pl.json` (2274) vs `locales/en.json` (2226); izolacja prawdziwych braków bez sufiksów plural; odczyt `lib/i18n.ts` (`fallbackLng`); grep literałów PL w JSX.

## Czego NIE dało się sprawdzić (luki audytu)

- **Czy któryś endpoint backendu jest chroniony WYŁĄCZNIE przez ukrycie w UI** — to wymaga korelacji z domeną B (backend authz). Frontend NIE wymyśla uprawnień (źródłem jest `/api/auth/me`), a strony admin reagują na realne 403 z serwera — ale to NIE dowodzi że każdy endpoint ma `#[RequiresPermission]`. **Do potwierdzenia w domenie B**: czy `/api/admin/break-glass/*`, `/api/admin/tenants/*`, `/api/settings/roles/*` faktycznie zwracają 403 bez uprawnień (frontend zakłada że tak — patrz wzorzec `setForbidden(true)` na status 403).
- **Runtime XSS przez WYSIWYG** — nie wstrzykiwano payloadu na żywym stacku (read-only); ocena oparta na statycznej analizie DOMPurify.
- **Pełny audyt i18n** — sprawdzono tylko parytet kluczy + próbkę; nie zweryfikowano poprawności interpolacji/pluralizacji we wszystkich 2274 kluczach.
- **CSP bypass w praktyce** — nie testowano realnego obejścia `unsafe-inline`/`unsafe-eval` przez wstrzyknięty skrypt.
- **Test logowania/sesji E2E** — nie wykonano pełnego cyklu login→reload→refresh-cookie na żywo (ograniczenie read-only; analiza statyczna kodu refresh-flow).

## Ustalenia POZYTYWNE (zweryfikowane empirycznie)

- **Token JWT NIE jest w localStorage/sessionStorage/cookie po stronie JS.** `lib/http.ts:23` — `let accessToken: string | null = null` (zmienna modułowa, pamięć). Wszystkie miejsca czytające token robią to przez `getAccessToken()` (`lib/download.ts:11`, `lib/asset-upload.ts:110`, `features/exports/wizard/use-run-export.ts:59`, `features/exports/sessions/ExportSessionsView.tsx:94`, `layout/bulk-sessions-popover.tsx:80`). Wszystkie wystąpienia `localStorage` dotyczą wyłącznie UI-state (view mode, page size, wizard draft, dashboard KPI selection) — żadne nie przechowuje tokenu. **XSS czytający localStorage nie wykradnie JWT.** (Uwaga: XSS może wciąż wykonać żądania przez fetch z aktywnym tokenem w pamięci — ochrona dotyczy kradzieży/persystencji, nie wykonania.)
- **Refresh przez HttpOnly cookie** (`lib/http.ts:184` `doRefresh()` POST `/api/auth/refresh` z `credentials: same-origin`), single-flight guard przeciw lawinie 401 (`refreshInFlight`).
- **Logout realnie woła backend** — `lib/auth-provider.ts:127` POST `/api/auth/logout` + `clearAccessToken()`. Nie jest to czysto kliencki state-clear.
- **Permisje pochodzą z backendu** — `useIdentity` (`lib/identity/use-identity.ts:45`) pobiera `/api/auth/me`; `PermissionGate`/`PermissionRoute` to tylko warstwa UX nad odpowiedzią serwera. Field-level masking (`restricted-field.ts`) renderuje wg envelope `{value, editable}` który WYCINA backend serializer — defence in depth, nie jedyna ochrona.
- **Jedyny `dangerouslySetInnerHTML` jest sanityzowany DOMPurify** — `components/catalog/wysiwyg-editor.tsx:125`. Brak `eval()`, `new Function()`, surowego `.innerHTML`.
- **Security headers landują na żywo** (curl `https://pim.localhost/`): CSP, HSTS (2 lata, preload), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`, `-Server`. Konfiguracja: `docker/caddy/Caddyfile:26-57`, importowana per-origin (`pim.localhost` linia 61).
- **`target="_blank"` linki mają `rel`** (`noopener`/`noreferrer`): `features/settings/sso/index.tsx:249`, `AssetDrawer.tsx:135`, `channel-inline-icons.tsx:73`, `coming-soon.tsx:26`, `assets/show.tsx:96`. Brak tabnabbingu.
- **Brak hardcoded literałów PL w próbce** `features/admin/`, `features/settings/roles/` (poza udokumentowanymi fallbackami) — dyscyplina `t()` zachowana.

## FINDINGS (defekty)

### [LOW] dompurify 3.4.8 — Trusted Types policy survives clearConfig() (CVE GHSA-vxr8-fq34-vvx9)
- **Lokalizacja**: `apps/admin/package.json:42` (`"dompurify": "^3.4.8"`); zainstalowana 3.4.8 (`node_modules/dompurify/package.json` → `version: 3.4.8`).
- **Dowód**: `raw/pnpm-audit.txt` — `low | dompurify | Vulnerable versions <3.4.9 | Patched >=3.4.9 | Paths apps__admin>dompurify`.
- **Wektor**: app nie używa Trusted Types ani `RETURN_TRUSTED_TYPE`, więc realna ekspozycja minimalna; jednak DOMPurify to jedyna bariera XSS dla WYSIWYG (`wysiwyg-editor.tsx:125,205`) — chcemy ją mieć patchowaną.
- **Rekomendacja**: bump `dompurify` do `>=3.4.9` (`^` już to pokrywa po `pnpm update dompurify`). Usunąć też zdeprecjonowany `@types/dompurify@3.2.0` (dompurify ma własne typy).

### [MEDIUM] qs 6.15.1 (przez @refinedev/core) — DoS w qs.stringify (CVE GHSA-q8mj-m7cp-5q26)
- **Lokalizacja**: tranzytywna zależność `@refinedev/core@5.0.12` → `qs@6.15.1` (`pnpm-lock.yaml`).
- **Dowód**: `raw/pnpm-audit.txt` — `moderate | qs | Vulnerable >=6.11.1 <=6.15.1 | Patched >=6.15.2 | Paths apps__admin>@refinedev/core>qs`. Lockfile: `qs@6.15.1`.
- **Wektor**: `qs.stringify` rzuca TypeError na null/undefined w comma-format arrays gdy `encodeValuesOnly` ustawione — DoS jeśli Refine serializuje takie query. Eksploatacja zależy od tego czy Refine używa tej ścieżki z user-controlled danymi (filtry/sort); w admin głównie wewnętrzne, ale komponenty filtrów budują query z inputu użytkownika.
- **Rekomendacja**: `pnpm.overrides` na `qs@>=6.15.2` lub bump `@refinedev/core` gdy wyda wersję z patchem; po bumpie re-run `pnpm audit`.

### [MEDIUM] Brak Error Boundary w całej aplikacji — uncaught render error = biały ekran bez recovery
- **Lokalizacja**: cały `apps/admin/src` — `grep -rln "ErrorBoundary|componentDidCatch|getDerivedStateFromError|errorElement"` zwraca **0 wyników**. `App.tsx` ma tylko `Suspense fallback` (linia 377, dla lazy chunków), brak `errorElement` na żadnej z ~50 tras. `main.tsx` (24 linie) nie rejestruje globalnego handlera (`window.onerror`, `unhandledrejection`).
- **Dowód**: `App.tsx:377` `<Suspense fallback={<RouteFallback />}>` — jedyna sieć bezpieczeństwa; brak `errorElement`/boundary. `main.tsx` render `<StrictMode><App/></StrictMode>` bez wrappera.
- **Wektor/awaria**: pojedynczy rzucony błąd w renderze dowolnego komponentu (np. nieoczekiwany kształt odpowiedzi API mimo guardu `http.ts`, błąd w bibliotece Plate/Slate, null-deref) zrzuca CAŁE drzewo React → pusty `#root`, użytkownik widzi biały ekran bez komunikatu i bez opcji odświeżenia. To dokładnie ryzyko, przed którym broni komentarz `http.ts:141` (incydent white-screen 2026-05-13) — ale ochrona jest punktowa (tylko fetch JSON), nie globalna.
- **Rekomendacja**: dodać top-level `ErrorBoundary` (class component lub `react-error-boundary`) wokół `<Routes>` z fallbackiem „coś poszło nie tak + przeładuj"; opcjonalnie `errorElement` per layout route. Rozważyć `window.addEventListener('unhandledrejection')` dla telemetrii.

### [LOW] CSP osłabione: script-src 'unsafe-inline' 'unsafe-eval' (mitigacja XSS zneutralizowana)
- **Lokalizacja**: `docker/caddy/Caddyfile:50` — `script-src 'self' 'unsafe-inline' 'unsafe-eval'`. Potwierdzone live (curl): nagłówek CSP zawiera te dyrektywy na produkcyjnym origin.
- **Dowód**: live `content-security-policy: ... script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' ...`.
- **Wektor**: przy ewentualnym wstrzyknięciu HTML/JS, `unsafe-inline` + `unsafe-eval` pozwalają wykonać inline `<script>` i `eval` — CSP nie zatrzyma XSS. Reszta CSP jest mocna (`object-src none`, `base-uri self`, `form-action self`, `frame-ancestors none`), więc impact ograniczony, ale głównej dyrektywy anty-XSS brak.
- **Świadome odejście**: komentarz `Caddyfile:42-49` dokumentuje to jako tradeoff dla Vite HMR/Refine z planem na nonces „w follow-up gdy powstanie CSP report endpoint". To akceptowalny, udokumentowany dług.
- **Rekomendacja**: dla PROD builda (rolldown, bez HMR) rozważyć osobny, ostrzejszy CSP z nonce'ami zamiast `unsafe-inline`/`unsafe-eval`; dev origin może zostać luźniejszy.

### [LOW] i18n: 28 kluczy obecnych tylko w pl.json, brak w en.json — mixed-language UI dla EN
- **Lokalizacja**: `apps/admin/src/locales/{pl,en}.json`. Klucze bez tłumaczenia EN, m.in.: `categories.picker.*` (title, save, search_placeholder, ...), `products.detail.categories.*` (add_button, detach, empty, set_primary, ...), `products.detail.tabs.categories`, `products.view_mode.{aria,excel,grid}`.
- **Dowód**: porównanie spłaszczonych kluczy — `pl: 2274, en: 2226; in pl not en: 48` (z czego 28 to prawdziwe braki bez sufiksów plural, 20 to warianty `_few`/`_many`). `in en not pl: 0`.
- **Wektor/awaria**: `lib/i18n.ts:12` `fallbackLng: 'pl'` — użytkownik EN na tych ekranach (picker kategorii, tab Kategorie w detalu produktu, przełącznik widoku) zobaczy POLSKIE stringi zamiast angielskich. To NIE surowy-klucz-leak (fallback ratuje), ale mieszanka językowa = defekt jakości i niespójność dla anglojęzycznego pilota. Domyślny PL ma komplet (en-only=0), więc PL user nie zobaczy surowych kluczy.
- **Rekomendacja**: uzupełnić 28 brakujących kluczy w `en.json`; dodać CI-check parytetu kluczy pl↔en (fail gdy różnica > tolerowanych plural-wariantów).

### [LOW] PermissionRoute zdefiniowany ale nigdy nieużyty — sidebar/route bez UX-gatingu uprawnień
- **Lokalizacja**: `components/identity/PermissionRoute.tsx` (pełna implementacja + docstring z przykładem użycia). `grep -rn "<PermissionRoute"` → **0 użyć JSX** w całym `src`. Trasy wrażliwe (`/admin/tenants`, `/admin/break-glass`, `/settings/users`, `/settings/roles`) w `App.tsx:513-515,323,330` są pod `AuthedRoute` (tylko auth), bez `PermissionRoute`.
- **Dowód**: jedyne wystąpienie `<PermissionRoute` to komentarz-przykład w `PermissionRoute.tsx:16`. Strony zamiast tego robią request i reagują na 403 (`break-glass/index.tsx:65` `if (status===403) setForbidden(true)`).
- **Wektor**: to NIE luka bezpieczeństwa (backend egzekwuje, strona pokazuje „forbidden" po 403 — patrz zależność od domeny B). Konsekwencja: użytkownik bez uprawnień może KLIKNĄĆ link w menu i nawigować do `/admin/break-glass`, zobaczyć szkielet strony, dopiero potem dostać „brak dostępu" po nieudanym requeście. UX leak (martwy guard component) + niewykorzystany kod. Warunkiem braku realnego problemu jest faktyczne 403 z backendu — **do potwierdzenia w domenie B**.
- **Rekomendacja**: albo podpiąć `PermissionRoute` do tras admin/settings (spójny 403 page + ukrycie linków w menu przez `PermissionGate` — `menu-permissions.ts` już istnieje), albo usunąć martwy komponent. Zweryfikować z domeną B że każdy `/api/admin/*` i `/api/settings/roles/*` ma `#[RequiresPermission]`.

## Połykane błędy (kontekst, nie osobny finding)

Istnieją świadome `catch {}` połykające błędy z komentarzem (np. `features/catalog/attribute-groups/show.tsx:260` „ignored — the next reload will resync", `category-selector-card.tsx:170` „Intentionally swallow"). To celowe i udokumentowane — w połączeniu z brakiem globalnego Error Boundary podnosi to jednak ryzyko cichych awarii (użytkownik nie wie że akcja się nie powiodła). Adresowane częściowo przez `useHttpErrorToast` w innych ścieżkach.
