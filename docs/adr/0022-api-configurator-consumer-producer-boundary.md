# ADR-0022 — Granica Konsument/Producent i umiejscowienie generycznego konektora API

- Status: Accepted
- Data: 2026-06-26
- Deciders: Marcin (operator), architekt rozwiązań
- Powiązane: ADR-0016 (format kluczy API + Argon2id), ADR-0017 (BYOK AES-256-GCM), ADR-0019 (Import v2 — kontrakty silnika), ADR-0020 (powierzchnia API), §7 architektury (warstwa integracji)
- Źródło: `Project Plan/feature-api-configurator-uniwersalny-plan.md` (§12 mini-ADR), backlog `Project Plan/feature-api-configurator-tickets.md` (epik APIC)

## Kontekst i problem

Operator chce **uniwersalnego Konfiguratora API** — warstwy, w której PIM mapuje dane ↔ dowolne zewnętrzne REST/JSON API (oba kierunki, harmonogram synchronizacji), bez dotykania kodu per system. W repo istnieją dwa różne znaczenia „konfiguratora API", które łatwo pomylić, bo oba nazywają się tak samo:

- **Producent** — cudzy system woła API PIM. Istnieje zalążek w `src/ApiConfigurator/` (`ApiProfile` + `ApiKey` + webhook fan-out z HMAC). PIM jest serwerem HTTP; wydaje własne klucze.
- **Konsument** — PIM woła cudze API. Greenfield w pustym szkielecie `src/Integration/`. PIM jest klientem HTTP; przechowuje cudze credentiale i musi je odszyfrować, by wysłać żądanie.

Operator chce **obu oblicz w jednym obszarze UI** „Konfigurator API". Potrzebna decyzja: gdzie w architekturze (bounded contexts, Deptrac) ląduje generyczny konektor konsumenta, jak się ma do istniejącego `ApiConfigurator`, oraz jak nie zduplikować silników Import/Export.

Decyzje produktowe zatwierdzone przez operatora (2026-06-26): (1) w pełni generyczny, (2) dwukierunkowy, (3) mapowanie tylko 1:1 (bez silnika transformacji w MVP), (4) oba oblicza.

## Decision Drivers

- Czysty podział domen (DDD) — „nasze API jako produkt" to inna domena niż „konsumowanie cudzych API".
- Reuse istniejących silników (IMP2 import, Export) zamiast duplikacji zapisu/odczytu danych produktowych.
- Bezpieczeństwo: user-definiowane endpointy = realna powierzchnia SSRF; dwa różne mechanizmy sekretów.
- Deptrac: cross-context tylko przez `*_Contracts`.
- Minimalizacja ryzyka bidirectional przy mapowaniu 1:1.

## Decyzja

1. **Podział Konsument/Producent** — dwa oblicza jednego obszaru UI „Konfigurator API", ale **osobne bounded contexts**. Wspólny shell UI, wspólny model audytu i sekretów; rozłączne domeny.

2. **Generyczny konektor konsumenta ląduje w `src/Integration/Generic/`, NIE w `ApiConfigurator`.** `ApiConfigurator` semantycznie znaczy „nasze API jako produkt" (outbound projekcja: profile, klucze, webhooki). Konsumowanie cudzych API to inna domena → naturalne miejsce wg §7 architektury i wzorca `IntegrationAdapter`. Sub-context wystawia warstwę `Integration/Generic_Contracts`; zależy tylko od `Catalog_Contracts`, `Channel_Contracts`, `Asset_Contracts`, `Identity_Contracts`, `Shared`, `Vendor` (Deptrac).

3. **Konfigurator to warstwa transport + mapowanie + harmonogram NAD silnikami Import/Export** — nie duplikuje logiki danych produktowych:
   - **inbound** deleguje upsert do `ValueWriteCore`/`BatchValueWriter` + `ObjectResolver` z `provenance=Integration` (ten sam zapis, walidacja, completeness, indeks GIN co import plikowy);
   - **outbound** reużywa Export engine (`ExportBuilder`/`ColumnResolver`/`ValueSerializer`) do zbudowania payloadu.
   - Konfigurator dokłada wyłącznie: `GenericRestClient` (transport), `FieldMapping` (mapowanie), `SyncBinding`+cron (harmonogram), cursor i conflict. Poprawki w IMP2 automatycznie poprawiają synchronizację API.

4. **Dwa mechanizmy sekretów współistnieją świadomie** — producent **hashuje** klucze (Argon2id, `ApiKeyAuthenticator` weryfikuje przychodzący klucz, ADR-0016); konsument **szyfruje odwracalnie** (`AesGcmEncryptionService` + `EncryptedSecret`, ADR-0017), bo cudze credentiale trzeba odszyfrować i wysłać w żądaniu. Nie mylić — to dwa różne mechanizmy dla dwóch różnych kierunków.

5. **Mapowanie 1:1 w MVP** — para `pimTarget ↔ remoteFieldPath` + kierunek, bez przekształceń; minimalna koercja typów (string/number/bool/date ISO-8601), niezgodność typu → ostrzeżenie przy zapisie (nie błąd). Silnik transformacji = hook (`ValueTransformerInterface` seam, bez zmiany schematu). Wartości złożone (enum/jednostki) — udokumentowane ograniczenie MVP.

6. **Konflikt bidirectional = LWW + per-binding override** — MVP: last-write-wins po timestamp lub `pim_wins`/`remote_wins` per `SyncBinding`; `provenance` rozstrzyga i przerywa pętle sync (nie wypychaj z powrotem zmiany o `provenance=Integration` z tego samego connectiona). Per-field source-priority = hook.

7. **Bezpieczeństwo „w pełni generycznego"** — wszystkie wywołania zewnętrzne przez `GenericRestClient` owijający SSRF-safe `NoPrivateNetworkHttpClient` + `SsrfGuard` per redirect; walidacja descriptora (scheme allowlist, brak `file://`/interpolacji do prywatnych zakresów); credentiale nigdy w odpowiedzi API; wszystko `TenantScoped` + RLS + GUC w workerach; rate-limit per Connection (429 + backoff) i per tenant; audyt każdego wywołania w `SyncRunLog` (bez sekretów).

## Konsekwencje

- **Positive:** czysty podział domen; pełen reuse silnika IMP2/Export; dwa mechanizmy sekretów świadomie rozdzielone; generyczny konektor = wiersze w bazie (deklaratywny descriptor Airbyte-like), nie nowy bundle per system; bidirectional uproszczony przez 1:1 + provenance anti-loop.
- **Negative:** dwa „konfiguratory API" w jednym UI wymagają wyraźnego podziału w shellu, by nie mylić operatora; mapowanie 1:1 nie pokrywa enum/jednostek bez transformacji (ograniczenie MVP); IdoSell SOAP poza zakresem REST/JSON MVP.
- **Follow-ups (hooki §7):** silnik transformacji wartości, GraphQL/SOAP/XML adaptery, pełen OAuth2 authorization-code, inbound webhooks real-time, AI auto-mapping, adaptive polling, source-priority conflict, connector-pack marketplace.

## Alternatywy odrzucone

- **Rozszerzenie `ApiConfigurator` o stronę „outbound/konsument"** — miesza dwie domeny („nasze API" vs „cudze API") w jednym BC; gorsza czytelność i granice Deptrac.
- **Własny upsert/serializer w konektorze** — duplikacja logiki IMP2/Export, rozjazd walidacji/completeness, podwójny koszt utrzymania.
- **Twardo-kodowane connectory per system (bundle per integracja w MVP)** — sprzeczne z decyzją „w pełni generyczny"; connector-packi (IdoSell/Shopify) wracają jako szablony descriptora (hook), nie kod.
- **Silnik transformacji w MVP** — zwiększa ryzyko bidirectional; odłożony za seam interfejsu.

## Links

- `Project Plan/feature-api-configurator-uniwersalny-plan.md` (architektura, §6 model domenowy, §8 fazy)
- `Project Plan/feature-api-configurator-tickets.md` (backlog epiku APIC, 48 MVP + 8 hooków)
- `Project Plan/01-architektura-pim.md` §7 (warstwa integracji), §13 (rejestr ADR)
- Related ADRs: ADR-0016, ADR-0017, ADR-0019, ADR-0020
