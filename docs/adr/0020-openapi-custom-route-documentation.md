# ADR-0020 — Dokumentacja custom `#[Route]` w OpenAPI (powierzchnia API: API Platform + custom controllers)

- Status: Accepted
- Data: 2026-06-19
- Kontekst audytu: `docs/audit/2026-06/01-findings.md` (AUD-043, AUD-054), fix-plan W2-8 (#1600)
- Powiązane: ADR-0012 (CQRS w warstwie aplikacji), CLAUDE.md pkt 3 (reguły implementacyjne)

## Kontekst

CLAUDE.md pkt 3 deklarował: *„API jest produktem first-class … **Wszystko przez API Platform** … Custom REST tylko gdy API Platform nie wystarczy"*.

Audyt połówkowy 2026-06 (AUD-043 / AUD-054) wykazał **odwrócenie tej reguły bez ADR**:

- router ma ~228+ tras `/api/*`, a eksport OpenAPI (`api:openapi:export`) dokumentował tylko **31 ścieżek**;
- **117 plików z `#[Route]`** vs **2 `#[ApiResource]`** w bazie kodu;
- cała powierzchnia custom — `auth` (login/refresh/2FA), `password-reset`, `invitation`, `bulk-edit`, `import` (start/pause/resume/rollback), `export`, `asset` (upload/preview/bulk-delete), `admin`/`break-glass`, RBAC settings, `agent/cmd-k` — była **nieudokumentowana w kontrakcie OpenAPI**, mimo że jest tym samym publicznym kontraktem, którego użyją integratorzy.

Przyczyna odwrócenia jest merytoryczna, nie przypadkowa: większość przepływów PIM to operacje **komendowe/proceduralne** (login, MFA enroll, bulk-edit, import start/pause/rollback, break-glass, agentic cmd-k), nie zasobowy CRUD. API Platform 4 modeluje **zasoby** (`ApiResource`) z operacjami CRUD; wymuszanie procesów na model zasobowy (custom operations + processory/providery) dawało większy narzut i gorszą czytelność niż dedykowane kontrolery `#[Route]` z parą command/handler (CQRS — ADR-0012).

## Decyzja

1. **Uznajemy świadomie hybrydowy kształt powierzchni API** jako stan docelowy MVP:
   - **API Platform** dla bytów zasobowych (`CatalogObject`, `Attribute`, `AttributeGroup`, `Channel`, `Asset`, `ApiKey`/`ApiProfile`, `ImportProfile`, `Locale`, …) — REST + GraphQL + JSON-LD;
   - **custom `#[Route]` kontrolery** dla operacji proceduralnych (auth, MFA, reset, invitation, bulk, import/export lifecycle, asset binary, super-admin, agent).
   - Zasady „API jest produktem first-class", „admin używa tych samych endpointów co integratorzy" i „żadnych prywatnych endpointów" **pozostają w mocy** — hybryda dotyczy *mechanizmu* (ApiResource vs Route), nie *publiczności* kontraktu.

2. **Kontrakt OpenAPI musi być kompletny i uczciwy.** Zamiast pokazywać 31 z 228 ścieżek, wprowadzamy `App\Shared\OpenApi\CustomRouteOpenApiFactory` (dekorator `api_platform.openapi.factory`), który automatycznie i **deterministycznie** dorzuca custom trasy `/api/*` do eksportu OpenAPI: tagi (per segment ścieżki), `security` (bearer), parametry ścieżki, odpowiedzi `200`/`401`, oraz extension `x-pim-source: custom-route`. Wykluczone: trasy API Platform (`_api_resource_class`), wewnętrzne (`/api/_test`, `/api/docs`, `/api/graphql`, `/api/contexts`, `/api/errors`, `/api/.well-known`).

3. **Preferujemy dekorator nad natychmiastowy retrofit** 117 kontrolerów na `ApiResource`. Retrofit (estymata L) miałby wysokie ryzyko regresji i niską wartość dla operacji proceduralnych (które nie są CRUD). Operacje są w pełni wywoływalne dziś — to kwestia *dokumentacji*, nie funkcjonalności.

4. **CLAUDE.md pkt 3 skorygowany** do stanu faktycznego z referencją do tego ADR — zakaz cichego dryfu konstytucji (uczciwość: reguła ma opisywać rzeczywistość).

## Konsekwencje

**Pozytywne**

- OpenAPI / Swagger UI / generatory klienta integratorów widzą **pełną** powierzchnię API (auth, import, export, asset, RBAC, …), nie 31 zasobowych ścieżek.
- Bramka CI „OpenAPI spec drift" (`docs/api-spec/v0.json`) chroni teraz **cały** kontrakt — nowa custom trasa bez aktualizacji snapshotu = czerwone CI.
- Brak kosztownego, ryzykownego retrofitu; kontrolery CQRS zostają tam, gdzie są naturalne.

**Negatywne / dług**

- Auto-generowane operacje custom mają na razie **minimalne** schematy request/response (path params + tagi + `200`/`401` + security). Pełne schematy request/response dla krytycznych przepływów (auth, import) to **opcjonalny follow-up on-demand** (per potrzeba integratorów) — zarejestrowany jako dług, bez twardego deadline'u.
- Dwa dekoratory na `api_platform.openapi.factory` (`PermissionOpenApiFactory` + `CustomRouteOpenApiFactory`) — kolejność ustalona przez `decoration_priority`; oba efekty (custom ścieżki + `x-cortex-permission`) muszą współistnieć w eksporcie (weryfikowane testem + bramką drift).

## Roadmap / remediacja

- Selektywny retrofit na `ApiResource` **tylko** tam, gdzie zasobowy CRUD jest naturalny (już objęte przez AP) — nie planujemy migracji operacji proceduralnych.
- Wzbogacenie schematów request/response operacji custom — on-demand, przy onboardingu pierwszego integratora partnerskiego.
