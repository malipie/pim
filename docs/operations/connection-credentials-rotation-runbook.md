# Connection credentials rotation runbook — BYOK key rotacja dla konektorów

> APIC-P5-03 (ADR-0022 / ADR-0017). Jak rotować klucz BYOK, którym szyfrowane
> są credentiale zewnętrznych API w `integration_connections`, bez przestoju
> synchronizacji i bez wycieku starego klucza w danych at-rest.

## TL;DR

1. Wygeneruj nowy 32-bajtowy klucz, dodaj go jako `APP_BYOK_KEY_V{n}` (n > obecne).
2. Deploy — od tej chwili **każdy nowy zapis** credentiali używa `vN`, a stare
   wiersze nadal się odszyfrowują swoim oryginalnym kluczem (oba klucze załadowane).
3. `php bin/console integration:credentials:rotate --dry-run` — ile wierszy czeka.
4. `php bin/console integration:credentials:rotate` — re-encrypt wszystkich starych
   wierszy do `vN` (idempotentne, per-tenant, RLS-safe).
5. Gdy `--dry-run` pokazuje `0 of N` — stary klucz `v{n-1}` można wycofać z env.

---

## Model szyfrowania (kontekst)

Credentiale konektora (`{header,value}` / `{user,pass}` / `{token}` zależnie od
`AuthType`) są szyfrowane **odwracalnie** (AES-256-GCM, libsodium) — w przeciwieństwie
do kluczy producenta, które są **hashowane** (Argon2id). Runtime synchronizacji
musi odszyfrować credentiale i odtworzyć je przeciwko zdalnemu API, więc szyfr
musi być odwracalny. Dwa mechanizmy współistnieją świadomie — patrz ADR-0022.

`AesGcmEncryptionService` trzyma mapę `wersja => 32-bajtowy klucz`. **Najwyższy
numer wersji jest aktywny** (używany do nowych zapisów); starsze wersje zostają
załadowane, żeby istniejące wiersze dekryptowały się bez wymuszonego sweepu.
Kolumny na encji `Connection`: `credentials_ciphertext` (TEXT, base64
`wersja || ciphertext+tag`) + `credentials_key_version` (INT).

`EncryptionServiceInterface::needsRotation(EncryptedSecret)` zwraca `true`, gdy
blob był zaszyfrowany wersją starszą niż aktywna — dokładnie wzorzec, którego
Argon2id używa do `needs_rehash`.

## Lazy re-encrypt

`ConnectionCredentialsCipher::rotateIfNeeded(Connection)` to prymityw lazy
re-encrypt: jeśli wiersz jest na starej wersji → odszyfrowuje starym kluczem,
ponownie szyfruje aktywnym i nadpisuje obie kolumny w miejscu, zwracając `true`
(sygnał dla wołającego, żeby zrobić `flush`). Nowe zapisy (`apply()`, np. przez
`ConnectionProcessor` przy POST/PATCH) **od razu** używają aktywnego klucza, więc
rotacji wymagają tylko wiersze nietknięte od czasu dodania nowego klucza — i te
domyka sweep `integration:credentials:rotate`.

> **Świadome odejście:** rotacja NIE jest wpięta w gorącą ścieżkę odczytu
> (`GenericRestClient::authHeaders`) — klient HTTP to infrastruktura i nie powinien
> robić `flush` ORM w pętli pull/push. Rotacja jest sterowana idempotentnym
> sweepem; on-read auto-rotacja w runtime sync to kandydat na follow-up, gdyby
> profil rotacji tego wymagał.

---

## Krok po kroku

### 1. Wygeneruj nowy klucz

```bash
openssl rand -base64 32          # 32 losowe bajty, base64 (format env var)
```

### 2. Dodaj jako kolejną wersję (NIE nadpisuj poprzedniej)

Klucze BYOK są sekretami runtime → **Symfony Secrets Vault** lub env
(`%env(...)%`), nigdy w repo. Jeśli aktywne jest `APP_BYOK_KEY_V1`, dodaj
`APP_BYOK_KEY_V2` — obie zmienne muszą być obecne, żeby stare wiersze nadal się
dekryptowały:

```dotenv
APP_BYOK_KEY_V1=<stary base64 32-byte>   # zostaje aż sweep dobiegnie końca
APP_BYOK_KEY_V2=<nowy base64 32-byte>    # nowa wersja aktywna
```

Mapowanie env → service: patrz `secrets-runbook.md` (warstwa „application-level
sekrety czytane w runtime").

### 3. Deploy

Po restarcie procesów (api + worker) aktywną wersją jest najwyższy `V{n}`.
Od tej chwili każdy nowy/edytowany connection zapisuje się na `vN`; stare wiersze
działają dalej na swoim kluczu.

### 4. Sprawdź ile wymaga rotacji (dry-run)

```bash
docker compose exec api php bin/console integration:credentials:rotate --dry-run
# => "3 of 12 connection(s) need credential rotation."
```

### 5. Wykonaj sweep

```bash
docker compose exec api php bin/console integration:credentials:rotate
# => "Rotated 3 of 12 connection(s) to the active key version."
```

Sweep jest **per-tenant** (ustawia GUC `app.current_tenant` + `TenantContext`,
tak jak `integration:sync:dispatch-due`), więc FORCE RLS nie ukrywa wierszy, a
izolacja tenantów jest zachowana. Jest **idempotentny** — ponowne uruchomienie
pominie wiersze już na aktywnej wersji.

### 6. Wycofaj stary klucz

Gdy `--dry-run` zwraca `0 of N`, żaden wiersz nie jest już zaszyfrowany starym
kluczem. Usuń `APP_BYOK_KEY_V{n-1}` z env/vault i zrób deploy. Od teraz tylko
aktywny klucz jest w pamięci procesu.

---

## Harmonogram / automatyzacja

Sweep jest tani i idempotentny — można go uruchamiać planowo (np. nocny cron
obok maintenance) albo ad-hoc po każdej rotacji klucza. Nie wymaga okna
przestoju: synchronizacje w locie działają na starym kluczu aż do momentu, gdy
sweep podmieni ich wiersz.

## Weryfikacja

- `--dry-run` → `0 of N` po sweepie = wszystkie wiersze na aktywnej wersji.
- SQL kontrolne (per tenant, z ustawionym GUC):
  ```sql
  SELECT credentials_key_version, count(*)
  FROM integration_connections
  WHERE credentials_ciphertext IS NOT NULL
  GROUP BY 1;
  ```
  Po sweepie wszystkie wiersze powinny mieć aktywny numer wersji.
- Smoke: po sweepie wykonaj „Test połączenia" w UI konektora — odszyfrowanie
  pod nowym kluczem musi dać 2xx z remote (auth nadal działa).

## Powiązane

- `secrets-runbook.md` — gdzie trzymać `APP_BYOK_KEY_V*` (vault vs env).
- ADR-0017 — algorytm + wersjonowanie BYOK.
- ADR-0022 — granica konsument/producent, dlaczego szyfr odwracalny (nie hash).
