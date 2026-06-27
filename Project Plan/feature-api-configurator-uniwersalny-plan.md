# Plan implementacji — Uniwersalny Konfigurator API (Cortex PIM)

> **Status:** draft do rozbicia na tickety. Utworzony 2026-06-26.
> **Kontekst:** operator (Marcin) chce uniwersalnego konfiguratora API, w którym może mapować dane PIM ↔ dowolne zewnętrzne API, wybierać atrybuty do integracji i ustawiać częstotliwość synchronizacji. Punktem wyjścia jest case IdoSell, ale celem jest mechanizm generyczny (Shopify i kolejne dochodzą bez przepisywania).
> **Powiązania:** `01-architektura-pim.md` §7 (warstwa integracji), `apps/api/src/ApiConfigurator/`, `apps/api/src/Integration/`, `apps/api/src/Import/` (silnik IMP2), ADR-0019 (import engine), ADR-0020 (powierzchnia API).
> **Ten dokument NIE jest backlogiem ticketów.** Zawiera architekturę + dwa briefy: dla agenta projektującego UI (§9) i dla agenta przepisującego plan na tickety (§10).

---

## 0. TL;DR

Budujemy **jeden obszar „Konfigurator API"** z dwoma obliczami:

1. **Konsument** (PIM ↔ cudze API) — NOWE, wypełnia pusty szkielet `src/Integration/`. User definiuje w UI dowolne zewnętrzne REST/JSON API (endpoint, auth, pola, paginacja), mapuje pola 1:1 z atrybutami PIM, ustawia kierunek i harmonogram synchronizacji.
2. **Producent** (cudze systemy ↔ API PIM) — ISTNIEJE jako zalążek `src/ApiConfigurator/` (`ApiProfile` + `ApiKey` + webhooki). Domykamy implementację i wpinamy w ten sam shell UI.

Kluczowa decyzja architektoniczna: **konfigurator to warstwa „transport + mapowanie + harmonogram" NAD istniejącymi silnikami Import (IMP2) i Export.** Inbound sync nie pisze własnego upsertu — deleguje do `ValueWriteCore`/`ObjectResolver` z `provenance=integration`. Outbound nie pisze własnego eksportu — reużywa Export engine. To spina konfigurator z tym, co już działa, i trzyma złożoność w ryzach.

Decyzje produktowe operatora (zatwierdzone 2026-06-26): **(1) w pełni generyczny**, **(2) dwukierunkowy**, **(3) mapowanie tylko 1:1** (bez silnika transformacji w MVP), **(4) oba oblicza — konsument i producent**.

---

## 1. Cel i zakres

**Cel:** operator (lub pilot-tenant) konfiguruje integrację z dowolnym zewnętrznym systemem bez dotykania kodu — wybiera atrybuty PIM do synchronizacji, mapuje je na pola zewnętrznego API, ustawia kierunek i częstotliwość. PIM pozostaje hubem (źródło prawdy ze schematem), zewnętrzne systemy to spoke'i.

**W zakresie:**
- Generyczny konektor REST/JSON (definiowany w UI).
- Oba kierunki: inbound (pull/polling z delta-sync), outbound (push na event/harmonogram), oraz bidirectional z polityką konfliktów.
- Mapowanie pól 1:1 (PIM attribute code ↔ remote field path).
- Harmonogram (cron) i monitoring synchronizacji.
- Domknięcie strony producenta (`ApiConfigurator`) i ujednolicenie UI.
- Bezpieczeństwo: SSRF-safe client, szyfrowane credentiale, izolacja tenantów, rate-limiting, audyt.

**Poza zakresem MVP (hooki na później, §7):** silnik transformacji wartości, GraphQL/SOAP/XML, pełen OAuth2 authorization-code flow, webhooki inbound (real-time zamiast pollingu), AI-assisted auto-mapping, marketplace gotowych connector-packów.

**Przypomnienie z poprzedniej rozmowy (case IdoSell):** dla IdoSell zakres to *eksport schematu* z IdoSell + *import schematu* do Cortex — to już mamy (StructuralImport + AutoMapper). Konfigurator API z tego dokumentu to osobna, większa, generyczna warstwa; IdoSell jest tylko jednym z connectorów, które przez nią przejdą.

---

## 2. Decyzje produktowe (zatwierdzone przez operatora)

| # | Decyzja | Wybór | Konsekwencja architektoniczna |
|---|---------|-------|-------------------------------|
| 1 | Poziom uniwersalności | **W pełni generyczny** | User definiuje dowolne API w UI: base URL, auth, endpointy, pola, paginacja. Model „connector descriptor" w stylu Airbyte Connector Builder. Brak twardo-kodowanych connectorów. |
| 2 | Kierunek synchronizacji | **Oba kierunki** | Per wiązanie (`SyncBinding`): inbound / outbound / bidirectional. Bidirectional wymaga polityki konfliktów i opiera się na `provenance`. |
| 3 | Transformacje | **Tylko 1:1** | Mapowanie pole→pole bez przekształceń. Minimalna koercja typów. Silnik transformacji = hook (osobny moduł, later). Redukuje to ryzyko bidirectional. |
| 4 | Oblicza | **Konsument + Producent** | Jeden obszar UI „Konfigurator API". Konsument w `Integration`, producent w `ApiConfigurator`. Wspólny shell, model sekretów, audyt. |

---

## 3. Dwa oblicza: Konsument + Producent

To rozróżnienie organizuje cały plan. Łatwo je pomylić, bo oba nazywają się „konfigurator API".

| | **Konsument** (PIM woła cudze API) | **Producent** (cudzy system woła API PIM) |
|---|---|---|
| Kierunek | PIM jest klientem HTTP | PIM jest serwerem HTTP |
| Co konfigurujemy | Połączenie do zewn. systemu, mapowanie, harmonogram | Projekcję naszego API, klucze, webhooki wychodzące |
| Stan w repo | `src/Integration/` — **pusty szkielet** | `src/ApiConfigurator/` — **zalążek** (`ApiProfile`, `ApiKey`, webhook delivery) |
| Sekrety | Przechowujemy cudze credentiale (szyfr. odwracalne, bo trzeba je wysłać) | Wydajemy własne klucze (hash Argon2id, bo weryfikujemy przychodzące) |
| Plan | §6.2–6.8, większość pracy | §6.9, domknięcie istniejącego |

**Uwaga kryptograficzna (ważna):** strona producenta **hashuje** klucze (Argon2id — `ApiKeyAuthenticator` weryfikuje przychodzący klucz). Strona konsumenta musi **szyfrować odwracalnie** (`AesGcmEncryptionService` + `EncryptedSecret`), bo credentiale zewnętrznego API trzeba odszyfrować i wysłać w żądaniu. To dwa różne mechanizmy — nie mylić.

---

## 4. Stan obecny (as-is) — co reużywamy

Zbadane w kodzie 2026-06-26. **Nie budujemy od zera — montujemy na istniejących klockach.**

| Klocek | Lokalizacja | Rola w konfiguratorze |
|--------|-------------|------------------------|
| `ApiProfile` (wzorzec) | `ApiConfigurator/Domain/Entity/ApiProfile.php` | Wzorzec encji konfiguracyjnej: named, `TenantScoped`, JSONB (`objectTypeIds` + `includedAttributes` + `filters`). Kalka pod `Connection`/`SyncBinding`. |
| `ApiKey` + Argon2id + rate-limit | `ApiConfigurator/Infrastructure/Security/` | Strona producenta (gotowe). Wzorzec rate-limitingu też dla konsumenta. |
| Webhook delivery | `ApiConfigurator/Application/WebhookDelivery*` | Producent: webhooki wychodzące (gotowy szkielet z HMAC). |
| `ImportSchedule` + `CronExpressionParser` + `ScheduleDispatcherService` | `Import/.../Schedule*` | **Harmonogram synchronizacji — reuse 1:1.** Cron + dispatcher + tracking runów (`ImportScheduleRun`, `ScheduleRunStatus`). |
| `AesGcmEncryptionService` + `EncryptedSecret` | `Shared/.../Crypto/` | **Szyfrowanie cudzych credentiali (odwracalne).** |
| `SsrfGuard` | `Import/Application/Service/Media/SsrfGuard.php` (z #1475) | **Strażnik SSRF** (walidacja URL/IP: private network, rebinding, redirect), wpinany w HTTP client. Krytyczny — user-defined endpoint to powierzchnia SSRF. |
| `ValueWriteCore` / `BatchValueWriter` (w `Catalog/Application/`) + `ObjectResolver` (w `Import/Application/Service/`) | Catalog + Import (IMP2) | **Inbound sync deleguje upsert tutaj** (provenance=integration). Nie piszemy własnego zapisu wartości. |
| Export engine | `src/Export/` | **Outbound sync reużywa eksport** zamiast własnego serializera. |
| `IntegrationAdapter` / `IntegrationClient` / `AttributeMapper` (interfejsy) | `01-architektura-pim.md` §7.5 | Kontrakty docelowe — generyczny konektor je implementuje. |
| Provenance enum (`manual\|import\|agent\|integration`) | Catalog (model wartości) | Bidirectional conflict + znakowanie pochodzenia. |
| Messenger + retry/backoff + dead-letter | konfiguracja IMP2 | Transport async + odporność sync runów. |
| StructuralImport + AutoMapper | `Import/.../Structural/` | Punkt 1 case IdoSell (schema export/import). Wzorzec auto-mapowania kolumn → atrybuty przyda się przy auto-suggest mapowań (later). |

---

## 5. Research — best practices → zasady projektowe

Źródła w §13. Z każdego wyciągamy konkretną zasadę:

1. **Airbyte Connector Builder** — generyczny konektor = `Connection` (base URL + auth) → `Streams`/`Endpoints` (ścieżka, metoda, paginacja, record selector JSONPath) → `Schema` (pola). UI generuje deklaratywny manifest „pod spodem". → **Zasada:** model konektora deklaratywny (JSONB descriptor), UI tylko go edytuje; „fetch sample → wykryj pola" zamiast ręcznego wpisywania schematu.
2. **Merge.dev Field Mapping** — no-code mapper remote field ↔ model docelowy; dwa poziomy (integration-wide + per-account); „Preview Values" i „Field Coverage". → **Zasada:** mapper dwukolumnowy z podglądem wartości i wskaźnikiem pokrycia; mapowania reużywalne między wiązaniami.
3. **iPaaS (Workato/Tray/n8n)** — wizualny builder, reusability mapowań (wersjonowane, nie kopiuj-wklej), test ze schema mismatch/null/type conflict, AI-assist auto-suggest. → **Zasada:** mapowanie jako wersjonowana encja; walidacja niezgodności typów przy zapisie; (later) auto-suggest.
4. **Sync scheduling / incremental (Airbyte, Merge polling guide)** — cursor (`updated_at` / incremental id / opaque), **cursor musi rosnąć monotonicznie** (walidacja przed persist = crash-safe, brak re-procesowania), adaptive polling, jitter między tenantami, retry 429 wg `Retry-After`, delta „since", interval splitting. → **Zasada:** inbound przez cursor delta-sync; stan kursora persystowany atomowo po stronie `SyncBinding`; backoff jak w Shopify (architektura §7.3).
5. **Bidirectional sync (Stacksync, integration patterns)** — w 2-way oba systemy to źródła prawdy → konieczna polityka konfliktów: last-write-wins (timestamp), system-priority per pole, field-level merge, manual review. → **Zasada:** MVP = LWW po timestamp + opcja „PIM zawsze wygrywa per mapping"; `provenance` rozstrzyga; brak złożonego mergera (1:1 mapping to upraszcza).

---

## 6. Architektura docelowa

### 6.1 Umiejscowienie (bounded context)

- **Konsument → `src/Integration/`** (wypełnienie szkieletu). To naturalne miejsce wg §7 architektury i wzorca `IntegrationAdapter`. Generyczny konektor = `Integration/Generic/` (lub `Integration/Connector/`), implementujący kontrakty z §7.5.
- **Producent → `src/ApiConfigurator/`** (domknięcie zalążka, epic 0.10).
- **Wspólne → `src/Shared/`**: crypto (jest), audyt sync, ewentualne kontrakty cross-BC przez `Integration\Contracts\*` (zgodnie z Deptrac — cross-context tylko przez `Contracts`).

> Decyzja do potwierdzenia w ADR (szkic §12): czy generyczny konektor to nowy sub-BC w `Integration`, czy rozszerzenie `ApiConfigurator` o stronę „outbound". Rekomendacja: **`Integration`** — bo `ApiConfigurator` semantycznie znaczy „nasze API jako produkt", a outbound to inna domena.

### 6.2 Model domenowy (strona konsumenta)

Wszystkie encje `TenantScoped` + RLS. Wszystkie pola konfiguracyjne złożone → JSONB (wzorzec `ApiProfile`).

- **`Connection`** — połączenie z zewnętrznym systemem. Pola: `code`, `name`, `baseUrl`, `authType` (`none|api_key|bearer|basic|oauth2_token`), `encryptedCredentials` (AesGcm), `defaultHeaders` (JSONB), `rateLimitHint`, `status` (`active|paused`), `lastHealthCheckAt`.
- **`RemoteEndpoint`** — descriptor operacji (odpowiednik „stream" Airbyte). Pola: `connectionId`, `name`, `role` (`read_list|read_one|write_create|write_update`), `httpMethod`, `pathTemplate`, `queryParams` (JSONB), `requestBodyTemplate` (JSONB), `pagination` (JSONB: `none|offset|page|cursor|link_header`), `recordSelector` (JSONPath do listy rekordów), `responseFormat` (`json` w MVP).
- **`RemoteField`** — pole zewnętrznego API (wykryte z próbki lub dodane ręcznie). Pola: `endpointId`, `path` (JSONPath), `label`, `dataType`, `sampleValue`.
- **`FieldMapping`** — mapowanie 1:1. Pola: `bindingId`, `pimTarget` (attribute code lub pole systemowe: `sku|name|status|category|...`), `remoteFieldPath`, `direction` (`inbound|outbound|both`), `isMatchKey` (bool — czy to identyfikator do parowania rekordów).
- **`SyncBinding`** — sedno: co, dokąd, jak, jak często. Pola: `connectionId`, `objectTypeId` (np. product), `readEndpointId?`, `writeEndpointId?`, `direction` (`inbound|outbound|bidirectional`), `schedule` (cron — reuse `CronExpressionParser`), `cursor` (JSONB: `field`, `type` `updated_at|incremental_id|opaque`, `state`), `conflictPolicy` (`lww|pim_wins|remote_wins`), `matchKeyMapping` (które `FieldMapping` jest kluczem), `enabled`.
- **`SyncRun`** + **`SyncRunLog`** — audyt (wzorzec `ImportScheduleRun` + `sync_job_logs`): `bindingId`, `direction`, `startedAt`, `status`, `counts` (created/updated/skipped/failed), `cursorBefore/After`, błędy per rekord.

### 6.3 Generyczny REST descriptor (Airbyte-like)

`Connection` + `RemoteEndpoint` razem tworzą deklaratywny descriptor. UI go edytuje, runtime go wykonuje przez `GenericRestClient`. To daje „w pełni generyczny" bez kodowania per-system: dowolne REST/JSON API = wiersze w bazie, nie nowy bundle.

### 6.4 Schema discovery

`SchemaDiscoveryService`: wywołuje `read_list`/`read_one` na próbce → spłaszcza JSON → proponuje listę `RemoteField` (path + wykryty typ + sample). User akceptuje/edytuje. Eliminuje ręczne wpisywanie schematu (wzorzec Airbyte/Merge).

### 6.5 Mapowanie 1:1

`FieldMapping` to czysta para `pimTarget ↔ remoteFieldPath` + kierunek. **Bez transformacji** (decyzja #3). Minimalna koercja typów (string/number/bool/date ISO-8601). Niezgodność typów → ostrzeżenie przy zapisie mapowania (zasada z iPaaS). Wartości złożone (enum/select, jednostki) — udokumentowane jako ograniczenie MVP; rozwiązanie = silnik transformacji (hook §7). Mapowania wersjonowane (reusability).

### 6.6 Kierunki synchronizacji

- **Inbound (pull)** — `InboundSyncHandler`: czyta cursor → woła `read_list` z delta (`since`/`>cursor`) → paginuje → mapuje 1:1 → **deleguje upsert do `ValueWriteCore`/`ObjectResolver` z `provenance=integration`** → przesuwa cursor atomowo (monotonicznie, crash-safe). Parowanie po `matchKey`.
- **Outbound (push)** — `OutboundSyncHandler`: trigger = event zmiany obiektu (reuse lifecycle subscriber) lub harmonogram → **reużywa Export engine** do zbudowania payloadu → mapuje 1:1 → woła `write_create`/`write_update` → backoff/retry (wzorzec Shopify §7.3) → dead-letter po wyczerpaniu prób.
- **Bidirectional** — oba powyższe na jednym `SyncBinding` + `ConflictResolver`: MVP = LWW po timestamp lub `pim_wins`/`remote_wins` per binding; `provenance` znakuje pochodzenie ostatniej zmiany. Pętle sync przerywane przez porównanie znacznika czasu i pochodzenia (nie wypychaj z powrotem zmiany, którą właśnie zaciągnąłeś).

### 6.7 Scheduling

Reuse `ImportSchedule` + `CronExpressionParser` + `ScheduleDispatcherService`. „Częstotliwość odpytywania" z wymagań = cron per `SyncBinding`. Jitter między tenantami (zasada z researchu). Adaptive polling — hook (MVP: stały cron).

### 6.8 Reuse silnika Import/Export — najważniejszy punkt architektoniczny

Konfigurator **nie duplikuje** logiki zapisu/odczytu danych produktowych:
- inbound → `ValueWriteCore` (ten sam upsert, walidacja, completeness, indeksowanie GIN co import plikowy),
- outbound → Export engine (ten sam serializer, keyset streaming, scope co eksport plikowy).

Konfigurator dokłada wyłącznie: **transport (GenericRestClient), mapowanie (FieldMapping), harmonogram (SyncBinding+cron), cursor i conflict.** Dzięki temu poprawki w silniku IMP2 automatycznie poprawiają synchronizację API.

### 6.9 Strona producenta (`ApiConfigurator`)

Zalążek istnieje (`ApiProfile`, `ApiKey`, webhook delivery). Do zrobienia (część była i tak planowana w epic 0.10, #90–#95):
- domknięcie ApiProfile builder (multiselect ObjectTypes/atrybutów/filtrów — jest model, brak UI),
- webhooki wychodzące real (delivery + retry + HMAC — częściowo jest),
- OpenAPI per-profil (`/api/docs/profile/{id}.jsonopenapi`),
- **ujednolicenie z konsumentem**: wspólny shell UI „Konfigurator API", wspólny model audytu i sekretów.

### 6.10 Bezpieczeństwo (krytyczne dla „w pełni generycznego")

User-definiowane endpointy = realna powierzchnia ataku. Wymagania twarde:
- **SSRF** — wszystkie wywołania zewnętrzne przez HTTP client chroniony `SsrfGuard` (blokada private network, anti-rebinding, kontrola redirectów). Opcjonalny allowlist domen per tenant/operator.
- **Sekrety** — `AesGcmEncryptionService`; credentiale nigdy nie wracają w odpowiedzi API (jak `webhookSecret`); rotacja = regeneracja.
- **Izolacja tenantów** — wszystko `TenantScoped` + RLS + GUC w workerach (wzorzec IMP2-2.5).
- **Rate-limiting** — per `Connection` (respekt 429 + backoff), per tenant (anti-abuse), limity jak w architekturze.
- **Audyt** — każde wywołanie zewnętrzne w `SyncRunLog` (URL, status, czas, rozmiar; bez sekretów).
- **Walidacja descriptora** — sanity-check `pathTemplate`/`baseUrl` (tylko http/https, brak file://, brak interpolacji do prywatnych zakresów).

---

## 7. Zakres MVP vs hooki

**MVP (pierwsza iteracja):** generyczny REST/JSON; oba kierunki; mapowanie 1:1; schema discovery z próbki; cron schedule; inbound cursor delta (`updated_at`); conflict LWW + `pim_wins`; reuse Import/Export; SSRF-safe client; szyfrowane credentiale; audyt; domknięcie strony producenta + wspólny shell.

**Hooki (świadomie odłożone, osobne decyzje):** silnik transformacji wartości (concat/split/lookup/format); GraphQL/SOAP/XML; pełen OAuth2 authorization-code; webhooki inbound (real-time); AI-assisted auto-mapping (wykorzysta wzorzec AutoMapper); adaptive polling; source-priority conflict per pole; marketplace gotowych connector-packów (IdoSell/Shopify jako paczki).

---

## 8. Rozbicie na fazy (high-level — agent §10 zamieni na tickety)

1. **Fundament konsumenta** — `Connection` + szyfrowane credentiale + `GenericRestClient` (SSRF-safe) + `ConnectionTester` (health/auth).
2. **Descriptor + discovery** — `RemoteEndpoint` + `RemoteField` + `SchemaDiscoveryService` (fetch sample → pola).
3. **Mapowanie 1:1** — `FieldMapping` + API + walidacja typów + wersjonowanie.
4. **Inbound sync** — cursor delta → `ValueWriteCore` upsert (provenance=integration) + parowanie matchKey.
5. **Outbound sync** — event/schedule → Export engine → write endpoint + backoff/dead-letter.
6. **Bidirectional + conflict** — `ConflictResolver` (LWW / pim_wins) + anti-loop przez provenance.
7. **Harmonogram** — integracja `SyncBinding` z `ScheduleDispatcherService` + jitter.
8. **Monitor + audyt** — `SyncRun`/`SyncRunLog` + UI historii runów + re-run.
9. **Strona producenta** — domknięcie `ApiConfigurator` (0.10) + wspólny shell UI.
10. **Hardening** — SSRF/secrets/rate-limit/RLS review + benchmark + (opcj.) pentest slice.

---

## 9. ZADANIE A — Brief dla agenta projektującego UI

> **Przekaż ten rozdział agentowi „UX/UI Design".** Cel: zaprojektować UI uniwersalnego konfiguratora API (konsument + producent) zanim powstaną tickety implementacyjne.

**Kontekst techniczny:** React 19 + Refine.dev + shadcn/ui (Radix + Tailwind). A11y obowiązkowa (walidacja axe-core dla customowych komponentów). i18n przez react-i18next (klucze EN, tłumaczenia pl/en). Wzorzec wizualny: istniejący admin PIM (sidebar Produkty/Kategorie/Zasoby).

**Do zaprojektowania (ekrany/flow):**
1. **Hub „Konfigurator API"** — dwie sekcje: „Połączenia" (konsument) i „Moje API" (producent). Lista połączeń ze statusem (active/paused/error), ostatni sync, kierunek.
2. **Kreator połączenia (Connection wizard)** — krok 1: base URL + typ auth + credentiale; krok 2: test połączenia (zielony/czerwony + payload); krok 3: definicja endpointów (read/write, metoda, ścieżka, paginacja); krok 4: schema discovery („Pobierz próbkę" → lista wykrytych pól do akceptacji).
3. **Field Mapping** — mapper dwukolumnowy: atrybuty PIM (lewa) ↔ pola zewnętrzne (prawa). Wskaźnik pokrycia, podgląd wartości (wzorzec Merge.dev), oznaczenie pola-klucza (matchKey), przełącznik kierunku per mapowanie. Ostrzeżenie przy niezgodności typów. **Bez UI transformacji** (1:1) — ale zostaw miejsce na przyszły przycisk „Dodaj transformację" (hook).
4. **Konfiguracja synchronizacji (SyncBinding)** — wybór ObjectType, kierunek (inbound/outbound/bidirectional), harmonogram (cron picker — reuse komponentu z `ImportSchedule`), konfiguracja cursora (pole + typ), polityka konfliktów (dla bidirectional), match key.
5. **Monitor synchronizacji** — historia runów (status, liczniki, czas, cursor), drill-down do błędów per rekord, przycisk „Uruchom teraz" / „Wstrzymaj" (reuse wzorca `ScheduleRuns`).
6. **Strona producenta** — ApiProfile builder (multiselect ObjectTypes/atrybutów/filtrów), zarządzanie kluczami API (mint/rotate/revoke — klucz pokazany raz), konfiguracja webhooków wychodzących.

**Deliverable agenta:** specyfikacja UI w `Project Plan/UI/` (jak istniejące pliki feature-* w tym folderze) — user flows, low-fi wireframes/opis ekranów, lista komponentów shadcn, stany (loading/empty/error/partial), noty a11y, klucze i18n. Format spójny z resztą `Project Plan/UI/`.

**Referencje do przejrzenia:** Merge.dev field mapping UI, Airbyte Connector Builder, n8n HTTP Request node, Workato/Tray recipe builder. **Nie projektuj silnika transformacji** — poza zakresem MVP.

---

## 10. ZADANIE B — Brief dla agenta przepisującego plan na tickety

> **Przekaż ten rozdział agentowi „Engineering Planning".** Cel: zamienić ten plan + projekt UI (§9) na wykonalny backlog ticketów w konwencji repo.

**KROK 1 — RESEARCH AS-IS (obowiązkowy, nie zakładaj — przeczytaj kod):**
- `src/ApiConfigurator/` — pełen obecny stan (co z `ApiProfile`/`ApiKey`/webhook jest gotowe, co brakuje względem README i #90–#95).
- `src/Integration/` — szkielet + README + wzorzec §7.5 architektury.
- `src/Import/` — `ScheduleDispatcherService`, `CronExpressionParser`, `ImportSchedule(Run)`, `ObjectResolver`, `AutoMapper`, `StructuralImport`, `SsrfGuard` (Media). Ustal dokładne sygnatury do reuse.
- `src/Catalog/Application/` — `ValueWriteCore`, `BatchValueWriter` (rdzeń zapisu wartości; inbound sync deleguje tutaj), `Provenance` enum (`Catalog/Domain/Provenance.php`).
- `src/Export/` — punkty wejścia do reużycia przy outbound.
- `src/Shared/Application/Crypto/` — `EncryptionServiceInterface`, `EncryptedSecret`, `AesGcmEncryptionService`.
- `SsrfGuard` (z #1475, `Import/.../Media/`) — jak go wpiąć w `GenericRestClient`.
- Provenance enum + lifecycle event subscriber (hook Fazy 2 z CLAUDE.md).
- Messenger config (transporty, retry_strategy, dead-letter) — wzorzec z IMP2.
- ADR-0019 (import engine), ADR-0020 (powierzchnia API), §7 architektury.
- Konfiguracja Deptrac — cross-context tylko przez `Contracts`.

**KROK 2 — ROZBICIE NA TICKETY:**
- Epik + fazy wg §8. Każda faza = milestone; każdy ticket = własny branch + PR + CI + merge.
- Ticket = jeden spójny, atomowy zakres (≤ ~kilka plików gdzie się da; cross-context → osobny ticket + Plan Mode per CLAUDE.md).
- **DoD każdego ticketu = zielone bramki:** PHPStan max, Deptrac, PHP-CS-Fixer, PHPUnit ≥80% nowej logiki, ApiTestCase dla nowych endpointów, Playwright E2E dla widocznych zmian, smoke 5 min na `pim.localhost`. Bez E2E ticket nie jest done (CLAUDE.md §Done).
- Oznacz tickety dotykające >1 bounded context lub decyzji architektonicznej jako wymagające **Plan Mode**.
- Wypisz zależności między ticketami (np. mapowanie blokuje sync; descriptor blokuje discovery).
- Uwzględnij tickety bezpieczeństwa jako osobne (SSRF, secret handling, RLS, rate-limit) — security-first, failing-test-first (wzorzec audyt fix-plan).
- Hooki z §7 → osobne tickety oznaczone `deferred`/`later`, nie mieszać z MVP.

**Deliverable agenta:** plik(i) backlogu w `Project Plan/` w konwencji `feature-imports-v2-tickets.md` (numerowane tickety z opisem, AC, DoD, zależnościami) + propozycja struktury GitHub Issues/milestones + **szkic ADR** (granica Konsument/Producent, umiejscowienie generycznego konektora w `Integration` vs `ApiConfigurator` — §12). Zaktualizuj `agent/current_status.md` i `02-plan-projektu-pim.md` po utworzeniu backlogu.

---

## 11. Ryzyka i otwarte kwestie

| Ryzyko / kwestia | Opis | Mitygacja / decyzja |
|---|---|---|
| SSRF przez user-defined endpoint | Generyczny konektor woła dowolny URL | `SsrfGuard` + walidacja descriptora + opcjonalny allowlist |
| Bidirectional loop | Zmiana zaciągnięta → wypchnięta z powrotem w kółko | Anti-loop przez `provenance` + porównanie timestampu; nie wypychaj zmian o `provenance=integration` z tego samego connectiona |
| 1:1 niewystarczające dla enum/jednostek | Brak transformacji → wartości się nie zgrają | Świadome ograniczenie MVP; udokumentować; hook na silnik transformacji |
| „W pełni generyczny" vs UX | Definiowanie API od zera jest trudne dla nie-developera | Schema discovery z próbki + dobre defaulty + (later) connector-packi jako szablony |
| Cursor drift / re-procesowanie po crashu | Stały cursor odtworzony → duplikaty | Monotoniczna walidacja kursora przed persist (wzorzec Merge/Airbyte + IMP2 checkpoint) |
| Pomylenie konsument/producent | Dwa „konfiguratory API" w jednym UI | Wyraźny podział w shellu (§3) + osobne BC |
| Otwarte: GraphQL/SOAP (IdoSell ma SOAP) | MVP tylko REST/JSON | IdoSell SOAP → hook lub dedykowany adapter później; case'em startowym REST/JSON |

---

## 12. Mini-ADR (szkic do sfinalizowania przez agenta §10)

**Tytuł:** Granica Konsument/Producent i umiejscowienie generycznego konektora API.

**Kontekst:** istnieją dwa znaczenia „konfiguratora API" — PIM jako producent (`ApiConfigurator`) i PIM jako konsument (nowe). Operator chce obu w jednym obszarze UI.

**Decyzja (proponowana):**
1. Strona konsumenta (generyczny konektor) ląduje w `src/Integration/` jako sub-BC, implementując kontrakty §7.5; **nie** rozszerza `ApiConfigurator`.
2. Strona producenta zostaje w `ApiConfigurator` (domknięcie 0.10).
3. Współdzielą: crypto, audyt sync, shell UI „Konfigurator API". Cross-BC tylko przez `*\Contracts\*` (Deptrac).
4. Konfigurator to warstwa transport+mapowanie+harmonogram nad Import/Export — nie duplikuje zapisu/odczytu danych produktowych.

**Konsekwencje:** czysty podział domen; reuse silnika IMP2; dwa mechanizmy sekretów (hash producent / szyfr konsument) świadomie współistnieją.

---

## 13. Źródła (research 2026-06-26)

- Airbyte — Connector Builder / low-code CDK: https://docs.airbyte.com/platform/connector-development/connector-builder-ui/overview
- Airbyte — Incremental sync (cursor): https://docs.airbyte.com/platform/connector-development/connector-builder-ui/incremental-sync
- Merge.dev — Field Mapping: https://docs.merge.dev/supplemental-data/field-mappings/overview/
- Merge.dev — 7 best practices for polling APIs: https://www.merge.dev/blog/api-polling-best-practices
- Stacksync — Two-way sync (conflict resolution): https://www.stacksync.com/blog/the-complete-guide-to-two-way-sync-definitions-methods-and-use-cases
- Akeneo — Mapping product data / attribute mapping: https://help.akeneo.com/using-the-integration-sap-commerce/mapping-product-data
- Enterprise Integration Patterns: https://www.enterpriseintegrationpatterns.com/
