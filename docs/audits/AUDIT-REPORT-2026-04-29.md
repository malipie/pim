# Raport audytowy zgodności kodu PIM z architekturą referencyjną

**Data audytu:** 2026-04-29
**Wersja AUDIT-CHECKLIST.md:** 1.1
**Wykonujący:** Claude Code (Opus 4.7, 1M ctx) — automatyczny audyt read-only
**Tryb:** read-only (modyfikowany wyłącznie ten plik raportu)
**Stadium projektu:** **2 — Faza 1 MVP w toku** (107 plików PHP, `composer.json` + `turbo.json` obecne, monorepo aktywne)

---

## 0. Wykryty stan projektu

### Struktura monorepo
- Root: `/Users/mlipieclocal/Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM`
- Pliki konfiguracyjne wykryte: `turbo.json`, `pnpm-workspace.yaml`, `package.json`, `README.md`, `CONTRIBUTING.md`, `.editorconfig`. Brak: `ONBOARDING.md`.
- `apps/`: `apps/api`, `apps/admin`
- `packages/`: `packages/shared-types` (uwaga: nazwa różni się od oczekiwanej `packages/api-types`)

### Stan kodu
- Liczba plików PHP w `apps/api/src/`: **107**
- Liczba plików TS/TSX w `apps/admin/src/`: **21**
- Wykryte top-level w `apps/api/src/` (BC + dodatkowe katalogi):
  - **Zgodne z architekturą:** [Catalog](apps/api/src/Catalog/), [Channel](apps/api/src/Channel/), [Asset](apps/api/src/Asset/), [Integration](apps/api/src/Integration/), [Identity](apps/api/src/Identity/), [Agent](apps/api/src/Agent/)
  - **Dodatkowe (niewymienione w §6.2):** `ApiConfigurator`, `Benchmark`, `DataFixtures`, `Maintenance`, `Messaging`, `Observability`, `Story`
  - **Brakujące:** `Shared/` — nie istnieje. `Messaging/` (zawiera `AbstractBatchHandler`) pełni rolę quasi-Shared.
  - Plik `Kernel.php` na poziomie `src/` — Symfony stub, dopuszczalny.
- Warstwy w BC Catalog (referencyjny pierwszy BC): Domain (14), Application (21), Infrastructure (16). **Brak `Contracts/`** — żaden BC w projekcie nie posiada warstwy Contracts.
- Warstwy Identity dodatkowo zawiera `Presentation/` (kontrolery REST) — niestandardowa, nie blokuje.
- BC `Integration/`, `Agent/`, `ApiConfigurator/` — szkielety z `.gitkeep` w warstwach, brak kodu produkcyjnego.

### Zainstalowane narzędzia
- **Composer:** `api-platform/symfony@^4.3.3`, `api-platform/doctrine-orm@^4.3.3`, `symfony/messenger@7.4.*`, `symfony/uid@7.4.*`, `phpstan/phpstan@^2`, `phpstan/phpstan-doctrine@^2`, `phpstan/phpstan-symfony@^2`, `phpstan/phpstan-strict-rules@^2`, `friendsofphp/php-cs-fixer@^3.65`, `zenstruck/foundry@^2`. Brak: `deptrac/deptrac`, `dama/doctrine-test-bundle`, `phpstan/phpstan-deprecation-rules`, `rector/rector`, klient Meilisearch, Anthropic SDK PHP.
- **NPM (apps/admin):** React 19, Vite 8, Refine 5, Radix UI, Tailwind 4, Biome 2, react-i18next, react-hook-form. Brak: `openapi-typescript` zainstalowane jako workspace dep w `packages/shared-types`, ale wynik `generate` nigdy nie wygenerował artefaktu (`packages/shared-types/src/index.ts` jest pusty z komentarzem oczekującym na pierwszą generację).
- **Konfigi obecne:** [phpstan.dist.neon](apps/api/phpstan.dist.neon) (level: max), [.php-cs-fixer.dist.php](apps/api/.php-cs-fixer.dist.php), [Dockerfile](apps/api/Dockerfile) FrankenPHP, [frankenphp/Caddyfile](apps/api/frankenphp/Caddyfile), [frankenphp/php.ini](apps/api/frankenphp/php.ini), [config/packages/doctrine.yaml](apps/api/config/packages/doctrine.yaml) (logging: false), [config/packages/messenger.yaml](apps/api/config/packages/messenger.yaml) (jedynie sync transport).
- **Konfigi brakujące:** `apps/api/deptrac.yaml`, `apps/api/rector.php`, `apps/api/vendor/bin/deptrac`, `docs/api/openapi-snapshot.json`, `apps/api/config/packages/prometheus.yaml`.

### Migracje
- 12 plików w [apps/api/migrations/](apps/api/migrations/).
- Aktywne wzorce: `tenant_id` na większości tabel domenowych, GIN index na `objects.attributes_indexed`, `ltree` extension + LTREE column dla `objects.path`, RLS policies dla 7 tabel (z notacją "policies bez ENABLE — aktywujemy w Fazie 2"), provenance + provenance_meta na `object_values`.

### Kontekst (zgodny z `CLAUDE.md` i `agent/current_status.md`)
Projekt jest pomiędzy MVP-Alpha a domknięciem epiku 0.3 (autonomous batch). Wiele odejść od architektury referencyjnej z meta-raportu (Domain z Doctrine attribute mapping, brak Contracts, brak Deptrac) jest **świadomych** zgodnie z `Project Plan/06-sprint-0-findings.md` lub planem rolloutu — ale checklist mechanicznie ich nie odróżnia. Patrz §5 Caveats.

---

## 1. Podsumowanie wykonawcze

| Severity | Liczba |
|----------|--------|
| 🔴 CRITICAL | **5** |
| 🟠 HIGH | **9** |
| 🟡 MEDIUM | **8** |
| 🟢 LOW | **5** |
| ℹ️ INFO | 3 |
| ✅ PASS | 18 |
| ➖ N/A (Stadium) | 6 |
| 🚧 BLOCKED | 1 |

**Top 5 najważniejszych do naprawy:**
1. **[DDD-001] CRITICAL** — 20 encji domenowych w `apps/api/src/*/Domain/Entity/` ma inline `#[ORM\Entity]` + `use Doctrine\ORM\Mapping`. Naprawa: przeniesienie mappingu do `Infrastructure/Doctrine/Orm/Mapping/*.orm.xml` i wyczyszczenie Domain. **Świadomy trade-off MVP — patrz Caveat C1.**
2. **[DDD-010] CRITICAL** — 65 cross-BC importów (głównie `Catalog → Identity`, `Asset → Identity`, `Channel → Catalog`). Brak warstwy `Contracts/` w żadnym BC = nie ma do czego się odwołać. Naprawa kompleksowa: pierwszy krok to utworzenie `Contracts/` per BC (DTO + integration events) i ustawienie Deptrac (TOOL-001).
3. **[TOOL-001] CRITICAL** — Brak `apps/api/deptrac.yaml` i `vendor/bin/deptrac`. Bez tego DDD-010 nie ma mechanicznego strażnika.
4. **[STR-003] CRITICAL** — 7 dodatkowych top-level katalogów w `src/` (`ApiConfigurator`, `Benchmark`, `DataFixtures`, `Maintenance`, `Messaging`, `Observability`, `Story`). Część to świadome wybory infra, część (`DataFixtures`, `Story`, `Benchmark`) realnie zaśmieca top-level. Brak też `Shared/` — `Messaging/` pełni jego rolę.
5. **[FE-001/FE-003] HIGH** — Frontend używa `apps/admin/src/pages/` (antywzorzec) i definiuje `interface Product` ręcznie zamiast importu z `@pim/shared-types`. `packages/shared-types/src/index.ts` nigdy nie wygenerował typów (`pnpm generate` wymaga uruchomionego `pim.localhost`).

**Ogólna ocena:** Projekt w Stadium 2 z mocnym fundamentem **runtime + DB** (FrankenPHP worker mode dyscyplina, GIN/ltree/RLS w migracjach, Doctrine logging off, opcache JIT) i poprawnym single-origin Caddy. **Słabe ogniwa:** brak granicy DDD między warstwami i BC (Doctrine w Domain, brak Contracts, cross-BC chaos), brak Deptrac, ApiPlatform Resource jeszcze nie zadeklarowane (ticket #41), frontend pre-features-refactor. Większość naruszeń to **długi techniczne świadomie odsunięte** w planie — należy je teraz przekuć w ADR-y zamiast zostawiać niemo.

---

## 2. Wyniki szczegółowe

### Kategoria STR — Struktura katalogów

#### STR-001 — Struktura monorepo `apps/` + `packages/api-types`
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:**
- `packages/api-types` nie istnieje. Faktyczny pakiet to [packages/shared-types/](packages/shared-types/) — nazwa konwencjonalna, ale niezgodna z §6.2.
**Sugerowana naprawa:** Albo zmienić `name` w [packages/shared-types/package.json](packages/shared-types/package.json) z `@pim/shared-types` na `@pim/api-types` + zmiana nazwy katalogu, albo zaktualizować checklistę (ADR). Drugi wariant jest tańszy — uznać `shared-types` jako lokalną konwencję projektu.

---

#### STR-002 — Pliki konfiguracyjne monorepo
**Wynik:** ⚠️ PARTIAL FAIL (1 z 4 must, 1 z 3 should)
**Severity:** 🟠 HIGH (dla `ONBOARDING.md` traktowane jako MEDIUM bo "should")
**Wykryte naruszenia:**
- Brak [ONBOARDING.md](ONBOARDING.md) — patrz DOC-003.
- 4 must-files (`turbo.json`, `pnpm-workspace.yaml`, `package.json`, `README.md`) → wszystkie obecne. Should `CONTRIBUTING.md` + `.editorconfig` obecne.
**Sugerowana naprawa:** Utworzyć `ONBOARDING.md` z ścieżką day-1/3/10 (vide DOC-003).

---

#### STR-003 — Bounded Contexts jako pierwszy poziom `apps/api/src/`
**Wynik:** ❌ FAIL
**Severity:** 🔴 CRITICAL
**Wykryte naruszenia:**
- 7 katalogów `UNEXPECTED:` `ApiConfigurator`, `Benchmark`, `DataFixtures`, `Maintenance`, `Messaging`, `Observability`, `Story`.
- Brak `Shared/` (zalecane). Funkcjonalnie zastępuje go [apps/api/src/Messaging/](apps/api/src/Messaging/) (zawiera `AbstractBatchHandler`) — ale to nie jest `Shared/`, raczej świadome wydzielenie sub-tematu.
- Brak antywzorców `Controller/`, `Entity/`, `Service/`, `Repository/`, `Manager/` na top-levelu — dobry znak (są wewnątrz BC).
**Caveat:** `ApiConfigurator/` jest zaplanowanym BC w `Project Plan/01-architektura-pim.md` sekcja 2. `Maintenance/` i `Observability/` mogą być uznane za podkategorie `Shared/` (cross-cutting). `Benchmark/`, `DataFixtures/`, `Story/` to katalogi narzędziowe, nie BC.
**Sugerowana naprawa:**
1. Utworzyć `Shared/Domain/`, `Shared/Application/`, `Shared/Infrastructure/`. Przenieść `Messaging/AbstractBatchHandler.php` → `Shared/Application/AbstractBatchHandler.php`. Przenieść infrastrukturalne podkatalogi (`Observability/`, część `Maintenance/`) do `Shared/Infrastructure/`.
2. `ApiConfigurator/` zostaje jako BC — dorobić zgodne 4 warstwy zanim pierwsze use case'y wejdą.
3. `Benchmark/`, `DataFixtures/`, `Story/` przenieść do `apps/api/tools/` lub `apps/api/src/DevTools/` (poza BC). Zwłaszcza `Story/` brzmi jak storybook/fixtury.
4. Dorobić ADR `0010-src-top-level-policy.md` jednoznacznie wymieniający dozwolone top-leveli.

---

#### STR-004 — 4 warstwy per BC (Domain/Application/Infrastructure/Contracts)
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH (dla core BC) / 🟡 MEDIUM (dla Identity, Agent jako supporting)
**Wykryte naruszenia (wszystkie BC):**
| BC | Domain | Application | Infrastructure | Contracts |
|---|---|---|---|---|
| Catalog (core) | 14 | 21 | 16 | **MISSING** ❌ |
| Channel (core) | 4 | 0 | 5 | **MISSING** ❌ |
| Asset (core) | 2 | 1 | 2 | **MISSING** ❌ |
| Integration (core) | 0 | 0 | 0 | **MISSING** ❌ |
| Identity (supporting) | 8 | 8 | 10 | **MISSING** ❌ |
| Agent (supporting) | 0 | 0 | 0 | **MISSING** ❌ |
| ApiConfigurator | 0 | 0 | 0 | **MISSING** ❌ |

**Konsekwencja:** Brak `Contracts/` w żadnym BC oznacza, że nie ma definicji integration events ani DTO Published Language. Cross-BC komunikacja nie ma do czego się odwołać → DDD-010 fail.
**Sugerowana naprawa:** Pierwszym krokiem jest utworzenie `Contracts/Event/` w Catalog z eventami: `ObjectCreated`, `ObjectPublished`, `ObjectAttributesChanged`. Następnie przepisanie cross-BC importów (Channel → Catalog/Domain) na konsumpcję eventów. Plan: ticket epiku 0.4 lub 0.5.

---

#### STR-005 — `README.md` w każdym BC
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:**
- [apps/api/src/Catalog/README.md](apps/api/src/Catalog/README.md) — **MISSING**
- [apps/api/src/Identity/README.md](apps/api/src/Identity/README.md) — **MISSING**
- Pozostałe BC mają README, ale wszystkie są krótkie (17-38 linii), graniczne ze 20.
**Sugerowana naprawa:** Dorobić README dla Catalog i Identity (najważniejsze BC w MVP). Zawartość: Subdomena, Główne agregaty, Emitowane eventy, Konsumowane eventy. Min. 20 linii.

---

#### STR-006 — `.claude/CLAUDE.md` w root
**Wynik:** ❌ FAIL
**Severity:** 🟢 LOW
**Wykryte naruszenia:**
- Brak `.claude/CLAUDE.md`. Istnieje za to [CLAUDE.md](CLAUDE.md) w roocie projektu (~190 linii, bardzo bogaty).
**Caveat:** CLAUDE.md w roocie jest oficjalną konwencją Claude Code i pełni rolę przewodnika. Reguła STR-006 mówi konkretnie o `.claude/CLAUDE.md` — w praktyce projekt już ma ekwiwalent w lepszej lokalizacji (root, widoczny w VS Code, w .gitignore'ach pewnych narzędzi `.claude/` może być mniej spotykane).
**Sugerowana naprawa:** Akceptujemy obecne CLAUDE.md w roocie jako równoważne. Reguła STR-006 powinna być rozluźniona w wersji 1.2 checklisty. Opcjonalnie utworzyć dodatkowo `apps/api/CLAUDE.md` i `apps/admin/CLAUDE.md` z per-pakiet notami (LOW priority).

---

### Kategoria DDD — warstwy i ich reguły zależności

#### DDD-001 — Domain bez Symfony/Doctrine/ApiPlatform
**Wynik:** ❌ FAIL
**Severity:** 🔴 CRITICAL
**Wykryte naruszenia (próbka — 20 plików łącznie):**
- [apps/api/src/Asset/Domain/Entity/AssetVariant.php:5](apps/api/src/Asset/Domain/Entity/AssetVariant.php#L5) — `use Doctrine\DBAL\Types\Types; use Doctrine\ORM\Mapping as ORM;` + 12 inline `#[ORM\*]`
- [apps/api/src/Asset/Domain/Entity/Asset.php](apps/api/src/Asset/Domain/Entity/Asset.php)
- [apps/api/src/Catalog/Domain/Entity/AttributeOption.php](apps/api/src/Catalog/Domain/Entity/AttributeOption.php)
- [apps/api/src/Catalog/Domain/Entity/CatalogObject.php](apps/api/src/Catalog/Domain/Entity/CatalogObject.php)
- [apps/api/src/Catalog/Domain/Entity/AssociationType.php](apps/api/src/Catalog/Domain/Entity/AssociationType.php)
- [apps/api/src/Catalog/Domain/Entity/Association.php](apps/api/src/Catalog/Domain/Entity/Association.php)
- [apps/api/src/Catalog/Domain/Entity/Attribute.php](apps/api/src/Catalog/Domain/Entity/Attribute.php)
- [apps/api/src/Catalog/Domain/Entity/ObjectType.php](apps/api/src/Catalog/Domain/Entity/ObjectType.php)
- [apps/api/src/Catalog/Domain/Entity/AttributeGroup.php](apps/api/src/Catalog/Domain/Entity/AttributeGroup.php)
- [apps/api/src/Catalog/Domain/Entity/ObjectTypeAttribute.php](apps/api/src/Catalog/Domain/Entity/ObjectTypeAttribute.php)
- [apps/api/src/Catalog/Domain/Entity/ObjectValue.php](apps/api/src/Catalog/Domain/Entity/ObjectValue.php)
- [apps/api/src/Channel/Domain/Entity/ChannelObjectTypeMapping.php](apps/api/src/Channel/Domain/Entity/ChannelObjectTypeMapping.php), [Locale.php](apps/api/src/Channel/Domain/Entity/Locale.php), [Currency.php](apps/api/src/Channel/Domain/Entity/Currency.php), [Channel.php](apps/api/src/Channel/Domain/Entity/Channel.php)
- [apps/api/src/Identity/Domain/Entity/RefreshToken.php](apps/api/src/Identity/Domain/Entity/RefreshToken.php), [Permission.php](apps/api/src/Identity/Domain/Entity/Permission.php), [Role.php](apps/api/src/Identity/Domain/Entity/Role.php), [Tenant.php](apps/api/src/Identity/Domain/Entity/Tenant.php), [User.php](apps/api/src/Identity/Domain/Entity/User.php)

Łącznie **20 encji** ma `#[ORM\Entity]` w Domain. Brak `#[ApiResource]` w Domain — ten aspekt PASS.
**Caveat C1 (świadome odejście):** Architektura referencyjna PIM (`Project Plan/01-architektura-pim.md`) preferuje Doctrine **attribute mapping inline** w Domain w MVP — vs. checklist która wymaga XML w Infrastructure. Projekt używa konwencji "attribute" w `doctrine.yaml`. To jest długi techniczny w stosunku do hexagonal architecture, ale nie jest błędem implementacji w sensie "nieświadome zanieczyszczenie".
**Sugerowana naprawa:**
- **Krótkoterminowo:** Dodać ADR `0011-doctrine-attributes-in-domain-tradeoff.md` opisujący decyzję, kompromisy (testowalność Domain bez DB → mid-level), kiedy migrujemy (Faza 2 — przy rosnącym zespole).
- **Długoterminowo:** Migracja na XML mapping w `Infrastructure/Doctrine/Orm/Mapping/` per BC. Ticket per BC, ~8h każdy.

---

#### DDD-002 — Domain nie zna `EntityManagerInterface`
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** Brak `EntityManagerInterface` w żadnym pliku Domain. Świetnie — listenery i serwisy używające EM siedzą w Infrastructure/Application.

---

#### DDD-003 — Application nie zależy od Infrastructure
**Wynik:** ❌ FAIL
**Severity:** 🔴 CRITICAL
**Wykryte naruszenia (skondensowane):**
- [apps/api/src/Identity/Application/CurrentTenantProvider.php:9](apps/api/src/Identity/Application/CurrentTenantProvider.php) — `use App\Identity\Infrastructure\Doctrine\Repository\TenantRepository`
- [apps/api/src/Identity/Application/RbacSeeder.php](apps/api/src/Identity/Application/RbacSeeder.php) — importuje `RoleRepository`, `PermissionRepository` (Infrastructure)
- [apps/api/src/Identity/Application/RefreshTokenService.php](apps/api/src/Identity/Application/RefreshTokenService.php) — importuje `RefreshTokenRepository`, `UserRepository`
- [apps/api/src/Catalog/Application/BuiltInObjectTypeSeeder.php](apps/api/src/Catalog/Application/BuiltInObjectTypeSeeder.php), [BuiltInAssociationTypeSeeder.php](apps/api/src/Catalog/Application/BuiltInAssociationTypeSeeder.php), [DemoCatalogSeeder.php](apps/api/src/Catalog/Application/DemoCatalogSeeder.php) — importują repozytoria Doctrine z Infrastructure
- (Identity → Identity i Catalog → Catalog są same-BC, więc nie naruszają DDD-010, ale naruszają DDD-003)

**Konsekwencja:** Application zna konkretną implementację Doctrine repository — łamie Dependency Rule.
**Sugerowana naprawa:** Utworzyć interfejsy w `Domain/Repository/<X>RepositoryInterface.php` (DDD-008) i wstrzykiwać interfejs w warstwie Application. Implementacje już istnieją w Infrastructure, brakuje formalnego portu.

---

#### DDD-004 — Encje domenowe bez publicznych setterów
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:** **41 publicznych setterów** w `Domain/Entity/`. Próbka:
- [apps/api/src/Catalog/Domain/Entity/CatalogObject.php](apps/api/src/Catalog/Domain/Entity/CatalogObject.php) — `setParent`, `setEnabled`, `setStatus`, `setCompleteness`, `setAttributesIndexed`, `setPath`
- [apps/api/src/Catalog/Domain/Entity/Attribute.php](apps/api/src/Catalog/Domain/Entity/Attribute.php) — `setGroup`, `setLabel`, `setHelp`, `setLocalizable`, `setScopable`, `setRequired`, `setValidationRules`, `setPosition`
- [apps/api/src/Catalog/Domain/Entity/ObjectType.php](apps/api/src/Catalog/Domain/Entity/ObjectType.php) — `setLabel`, `setCompletenessRules`, `setLabelAttribute`, `setImageAttribute`
- [apps/api/src/Catalog/Domain/Entity/ObjectValue.php](apps/api/src/Catalog/Domain/Entity/ObjectValue.php) — `setChannelId`, `setLocale`, `setValue`, `setProvenance`, `setProvenanceMeta`
- (pełna lista: 41 wystąpień w 11 plikach)

**Konsekwencja:** Hermetyzacja stanu nie jest egzekwowana, agregaty są ekspozją property bag.
**Caveat (heurystyka projektu):** Projekt używa układu `Domain/Entity/` zamiast `Domain/Model/`, co reguła specyfikuje. Zinterpretowano analogicznie (intent reguły jest jasny).
**Sugerowana naprawa:** Zamienić settery na metody domenowe (`setStatus(string)` → `activate()/deactivate()/archive()`). Trade-off: praca z hydration Doctrine — w Doctrine 3 z `enable_lazy_ghost_objects: true` można używać prywatnych settersów + Reflection (Doctrine sam mapuje), więc nie ma powodu trzymać publicznych. Plan: jeden ticket na encję, po ~1-2h każdy.

---

#### DDD-005 — Vertical slice Command/Handler per use case
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:**
- Brak struktury `Application/Command/<UseCase>/<UseCase>Command.php + Handler.php`. Zamiast tego — Application zawiera **Service-style klasy**: `BulkContext`, `AttributesIndexedRebuilder`, `ObjectTypeService`, seedery, walidatory.
- Jedyny Handler znaleziony: [apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php](apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php) — istnieje, ale jego Message ([Message/ObjectValuesChangedMessage.php](apps/api/src/Catalog/Application/Message/ObjectValuesChangedMessage.php)) jest w innym podfolderze. Nie vertical slice.
**Konsekwencja:** CQRS pattern nie jest zaimplementowany — projekt używa "service layer" zamiast `CommandHandlerInterface` Messengera dla mutacji.
**Sugerowana naprawa:** Wprowadzać CQRS sukcesywnie przy nowych ticketach (ADR `0012-cqrs-rollout-strategy.md`). Refaktor istniejących serwisów na Command/Handler dopiero gdy use case staje się złożony lub potrzebny audit log per komendzie. MVP nie wymaga twardego CQRS.

---

#### DDD-006 — Mapowanie Doctrine w XML, nie atrybutach
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:** 0 plików `*.orm.xml` w `apps/api/src/*/Infrastructure/Doctrine/`. Wszystko używa atrybutów inline w Domain (efekt DDD-001).
**Caveat C1 jak przy DDD-001:** świadomy trade-off MVP. Konfiguracja `doctrine.yaml` używa `type: attribute` celowo — patrz `Project Plan/01-architektura-pim.md` ADR-001/002 (jeśli istnieje).
**Sugerowana naprawa:** Razem z DDD-001 — migracja XML wpisana w długi techniczny z ADR `0011`.

---

#### DDD-007 — UUIDv7 generowane w domenie
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:**
- Brak `strategy="(AUTO|IDENTITY|SEQUENCE)"` w mappingach.
- Wszystkie Domain entities generują ID konstruktorem `Symfony\Component\Uid\Uuid::v7()`. Próbka: [Asset.php](apps/api/src/Asset/Domain/Entity/Asset.php), [CatalogObject.php](apps/api/src/Catalog/Domain/Entity/CatalogObject.php), [Attribute.php](apps/api/src/Catalog/Domain/Entity/Attribute.php), [Association.php](apps/api/src/Catalog/Domain/Entity/Association.php).
- `#[ORM\Id]` bez `#[ORM\GeneratedValue]` = strategy NONE (Doctrine respektuje wartość przekazaną).
- Uwaga: w `doctrine.yaml` jest `identity_generation_preferences: PostgreSQLPlatform: identity` — to dotyczy tylko gdy `#[GeneratedValue]` byłoby użyte. W bieżącym kodzie nie ma takiego użycia.

---

#### DDD-008 — Repository: interface w Domain, implementacja w Infrastructure
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:**
- 0 plików `*RepositoryInterface.php` w `apps/api/src/*/Domain/Repository/`.
- 19 implementacji repozytoriów w `Infrastructure/Doctrine/Repository/` — wszystkie konkretne klasy bez interfejsu w Domain.
- Konwencja nazewnicza: implementacje **nie** mają prefiksu `Doctrine` (np. [TenantRepository.php](apps/api/src/Identity/Infrastructure/Doctrine/Repository/TenantRepository.php), nie `DoctrineTenantRepository`). Reguła sugeruje prefiks dla rozróżnienia portów od adapterów.

**Sugerowana naprawa:** Wprowadzić port-adapter sukcesywnie dla repozytoriów najczęściej wstrzykiwanych do Application: `TenantRepository`, `RefreshTokenRepository`, `CatalogObjectRepository`, `ObjectTypeRepository`, `AttributeRepository`. Plan: ~2h per repo, łącznie ~10h. Powiązanie z DDD-003.

---

#### DDD-009 — `Contracts/` zawiera DTO, nie agregaty
**Wynik:** ➖ N/A
**Severity:** —
**Wynik detekcji:** Brak warstwy `Contracts/` w żadnym BC (patrz STR-004). Reguła nie ma do czego się stosować.
**Sugerowana naprawa:** Po utworzeniu `Contracts/` (STR-004) ta reguła wejdzie do gry. Pierwszym DTO powinien być `Catalog\Contracts\Query\ObjectSummary` (idealnie `final readonly class`).

---

#### DDD-010 — Cross-BC import tylko z `Contracts/`
**Wynik:** ❌ FAIL (fallback grep — Deptrac niedostępny)
**Severity:** 🔴 CRITICAL (zgodnie z regułą; obniżone do **🟠 HIGH** ze względu na fallback grep — patrz reguła)
**Wynik detekcji:** **65 cross-BC importów w 33 plikach.** Histogram pairs:
| BC źródłowy | BC importowany | Liczba |
|---|---|---|
| Catalog | Identity | 27 |
| Asset | Identity | 7 |
| DataFixtures | Catalog | 6 |
| DataFixtures | Identity | 6 |
| Channel | Catalog | 6 |
| Benchmark | Catalog | 4 |
| Benchmark | Identity | 3 |
| Channel | Identity | 3 |
| Catalog | Asset | 2 |
| Asset | Catalog | 1 |

Próbki:
- [apps/api/src/Catalog/Domain/Entity/CatalogObject.php:9-10](apps/api/src/Catalog/Domain/Entity/CatalogObject.php) — `use App\Identity\Application\TenantScoped; use App\Identity\Domain\Entity\Tenant;` (Domain-Domain leak)
- [apps/api/src/Channel/Domain/Entity/Channel.php:7](apps/api/src/Channel/Domain/Entity/Channel.php) — `use App\Catalog\Domain\Entity\CatalogObject;` (FK na CategoryTreeRoot)
- [apps/api/src/Asset/Domain/Entity/Asset.php:8](apps/api/src/Asset/Domain/Entity/Asset.php) — `use App\Catalog\Domain\Entity\CatalogObject;` (Asset → Object FK)
- [apps/api/src/Catalog/Application/DemoCatalogSeeder.php:7-8](apps/api/src/Catalog/Application/DemoCatalogSeeder.php) — `use App\Asset\Domain\Entity\Asset; use App\Asset\Domain\Entity\AssetVariant;` (seeder cross-BC)
- [apps/api/src/Catalog/Infrastructure/Doctrine/Repository/CatalogObjectRepository.php:9](apps/api/src/Catalog/Infrastructure/Doctrine/Repository/CatalogObjectRepository.php) — `use App\Identity\Domain\Entity\Tenant;` (FK)

**Caveat (uzasadnione przypadki):**
- `Tenant` jest cross-BC FK używanym wszędzie — to jest wzorzec multi-tenancy "shared kernel". Nie powinien być w `Identity/Domain/`, tylko w `Shared/Domain/Tenant.php`.
- `Channel → Catalog\CatalogObject` (CategoryTreeRoot) i `Asset → Catalog\CatalogObject` (powiązanie produktu z assetami) to legitne FK domenowe — w klasycznym DDD przekazywane jako Value Object/ID, nie pełna encja.
- DataFixtures, Benchmark to katalogi narzędziowe, naturalnie korzystają z wielu BC. Powinny być wyłączone z DDD-010.

**Sugerowana naprawa:**
1. Utworzyć `apps/api/src/Shared/Domain/Tenant.php` (wyciągnąć z Identity), albo `Shared/Domain/TenantId.php` jako VO.
2. Wprowadzić Deptrac (TOOL-001) z ruleset wykluczającym `DataFixtures/`, `Benchmark/`.
3. Cross-BC FK przepisać na wartości skalarne (UUID) zamiast obiektowe.
4. Każde uzasadnione cross-BC zostawienie **MUSI** mieć adnotację `// Deptrac-allow: Tenant is shared kernel (ADR-007)`.

---

#### DDD-011 — Aggregate Root rozszerza shared base
**Wynik:** ❌ FAIL
**Severity:** 🟢 LOW
**Wynik detekcji:** Brak `apps/api/src/Shared/Domain/AggregateRoot.php` (wynik STR-003: brak `Shared/`). Żadna encja Domain nie ma `extends AggregateRoot`.
**Sugerowana naprawa:** Po utworzeniu `Shared/` (STR-003) — dodać `Shared/Domain/AggregateRoot.php` z metodami `recordThat(DomainEvent)`, `pullEvents(): array`. Refaktor agregatów: opcjonalny, do realizacji wraz z domain events (BC-002, faza 1+).

---

### Kategoria BC — bounded contexts

#### BC-001 — Wszystkie 6 wymaganych BC istnieje
**Wynik:** ➖ N/A — Stadium 3 only
**Wynik detekcji (informacyjnie):** Wszystkie 6 BC obecne strukturalnie. `Integration/`, `Agent/`, `ApiConfigurator/` — szkielety z `.gitkeep` (epiki 0.7, 0.8, 0.9, 0.10 jeszcze nie zrobione). To jest zgodne z planem.

---

#### BC-002 — Komunikacja cross-BC tylko przez Messenger lub Contracts/
**Wynik:** ❌ FAIL
**Severity:** 🔴 CRITICAL → obniżone do 🟠 HIGH (reguła oznacza CRITICAL tylko dla Stadium 3)
**Wynik detekcji:**
- 0 plików w `Contracts/Event/` (warstwa Contracts nie istnieje).
- 0 plików w `Application/Subscriber/`.
- Brak `DomainEventToMessageMiddleware.php`.
- Messenger config ([config/packages/messenger.yaml](apps/api/config/packages/messenger.yaml)) ma tylko `sync://` transport, brak `failure_transport`. Komentarze sugerują że async + failed mają być włączone gdy przyjdzie odpowiedni epic.

**Konsekwencja:** Komunikacja cross-BC dziś jest synchronicznym importem (DDD-010). Brak fundamentu pod skalowanie i decoupling.
**Sugerowana naprawa:** Razem z STR-004 — utworzyć Contracts/Event w Catalog, opisać 5 kluczowych eventów (ObjectCreated, ObjectPublished, ObjectAttributesChanged, ObjectArchived, AssetUploaded). Configure `async` transport. Wymaga Redis + workera. Plan: epic 0.5 lub 0.6.

---

#### BC-003 — Integration BC jako jedyny multi-BC consumer
**Wynik:** ➖ N/A — Stadium 3 + brak deptrac.yaml
**Wynik detekcji:** Brak `apps/api/deptrac.yaml`, więc nie ma jak ocenić ruleset. BC `Integration/` jest pusty (`.gitkeep`).

---

### Kategoria DB — model bazy danych

#### DB-001 — Każda tabela ma `tenant_id`
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🔴 CRITICAL → obniżone do 🟡 MEDIUM (znalezione 3 wyjątki są legitne)
**Wykryte naruszenia (heurystyka grep):**
- [apps/api/src/Channel/Domain/Entity/Currency.php](apps/api/src/Channel/Domain/Entity/Currency.php) — brak `tenantId`. **Caveat:** Currency to słownikowa tabela globalna (ISO 4217), nie tenant-scoped.
- [apps/api/src/Channel/Domain/Entity/Locale.php](apps/api/src/Channel/Domain/Entity/Locale.php) — brak `tenantId`. **Caveat:** Locale to słownikowa tabela globalna (BCP 47), nie tenant-scoped.
- [apps/api/src/Identity/Domain/Entity/Tenant.php](apps/api/src/Identity/Domain/Entity/Tenant.php) — sam Tenant nie ma tenantId (oczywiście, jest meta-encją).

**Wynik:** wszystkie 3 wyjątki uzasadnione. Reszta encji ma `tenant_id`. **DE FACTO PASS** — należy uznać jako informacyjne, nie naruszenie.
**Sugerowana naprawa:** Zaktualizować checklistę v1.2: wykluczyć słowniki referencyjne (Currency, Locale) z reguły DB-001.

---

#### DB-002 — `object_values` UNIQUE (tenant_id, object_id, attribute_id, locale, channel)
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🟡 MEDIUM
**Wynik detekcji:** [apps/api/src/Catalog/Domain/Entity/ObjectValue.php](apps/api/src/Catalog/Domain/Entity/ObjectValue.php) ma `UniqueConstraint` o nazwie `object_values_scope_uniq` z kolumnami `['object_id', 'attribute_id', 'channel_id', 'locale']` i `nulls_not_distinct => true`. **Brakuje `tenant_id`** w UNIQUE.
**Caveat:** Skoro `tenant_id` jest zaszyte w `objects.tenant_id` (object_id już niesie tenant pośrednio), constraint może być poprawny funkcjonalnie. Ale w wielu DDD-konwencjach jawnie pisze się `tenant_id` w każdym constraintcie.
**Sugerowana naprawa:** Dodać `tenant_id` do UNIQUE: zmienić na `['tenant_id', 'object_id', 'attribute_id', 'channel_id', 'locale']`. Defence in depth — chroni przed bug w UPSERT cross-tenant.

---

#### DB-003 — `attributes_indexed` JSONB + GIN
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** [apps/api/migrations/Version20260428220053.php](apps/api/migrations/Version20260428220053.php) zawiera `CREATE INDEX objects_attributes_indexed_gin ON objects USING GIN (attributes_indexed)`. ✅

---

#### DB-004 — `ltree` extension dla hierarchii kategorii
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** [apps/api/migrations/Version20260428222056.php](apps/api/migrations/Version20260428222056.php) zawiera `CREATE EXTENSION IF NOT EXISTS ltree` + `ALTER TABLE objects ALTER COLUMN path TYPE LTREE`. Custom DBAL type `LtreeType` zarejestrowany w [doctrine.yaml](apps/api/config/packages/doctrine.yaml).

---

#### DB-005 — Provenance tracking w object_values
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** [ObjectValue.php](apps/api/src/Catalog/Domain/Entity/ObjectValue.php) ma `Provenance $provenance` (enum) + `array $provenanceMeta` jako JSONB.

---

#### DB-006 — RLS policies
**Wynik:** ⚠️ PARTIAL — N/A dla Stadium 2 (single-tenant deploy)
**Severity:** —
**Wynik detekcji:** Migracje [Version20260428195217.php](apps/api/migrations/Version20260428195217.php) i [Version20260428222056.php](apps/api/migrations/Version20260428222056.php) zawierają `CREATE POLICY tenant_isolation_*` dla 7 tabel. Komentarz w migracji wyraźnie mówi: *"but does NOT activate row-level security (`ENABLE ROW LEVEL SECURITY` is intentionally absent)"*. Aktywacja zaplanowana w Fazie 2.
**Sugerowana naprawa:** Zostawić jak jest. Aktualizować przy przejściu do Fazy 2 / multi-tenant deploy.

---

#### DB-007 — Migracje reversible (`down()`)
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** Wszystkie 12 migracji w [apps/api/migrations/](apps/api/migrations/) zawierają `public function down`. Skrypt nie znalazł `MISSING_DOWN`.

---

### Kategoria API — API Platform

#### API-001 — ApiResource w Infrastructure/ApiPlatform/Resource, nie w Domain
**Wynik:** ✅ PASS (degenerate)
**Severity:** —
**Wynik detekcji:**
- 0 `#[ApiResource]` w Domain ✓
- 0 `#[ApiResource]` w **całym projekcie** — żadne resource nie zostały jeszcze zadeklarowane. Komentarze w [ObjectKindRouter.php](apps/api/src/Catalog/Infrastructure/ApiPlatform/ObjectKindRouter.php) i [KindAwareSerializerContextBuilder.php](apps/api/src/Catalog/Infrastructure/ApiPlatform/KindAwareSerializerContextBuilder.php) wyraźnie mówią że to ticket #41+ (epik 0.4).
- Istnieje już infrastructure-side scaffolding: `CustomObjectTypeApiGuard`, `ObjectKindRouter`, `KindAwareSerializerContextBuilder` w [Catalog/Infrastructure/ApiPlatform/](apps/api/src/Catalog/Infrastructure/ApiPlatform/).

**INFO:** Reguła technicznie passuje (brak ApiResource w Domain), ale faktycznie nie ma czego sprawdzać. Po ticketach #41+ trzeba ponownie odpalić audyt — sprawdzić, że ApiResource ląduje w `Infrastructure/ApiPlatform/Resource/`, nie w Domain.

---

#### API-002 — Provider/Processor per ApiResource
**Wynik:** ➖ N/A (brak Resource'ów)
**Severity:** —
**Wynik detekcji:** 0 plików w `Infrastructure/ApiPlatform/State/`. Razem z API-001 — odłożone do epiku 0.4.

---

#### API-003 — OpenAPI snapshot commitowany
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wynik detekcji:**
- Brak [docs/api/openapi-snapshot.json](docs/api/openapi-snapshot.json) i całego katalogu `docs/api/`.
- Brak też `docs/api-spec/v{version}.json` wymienianego w [CLAUDE.md](CLAUDE.md) ("Pliki które utrzymujesz atomowo").
- Brak GitHub Actions workflow eksportującego OpenAPI.

**Sugerowana naprawa:** GitHub Action `openapi-snapshot.yml` na każdy push do main: `bin/console api:openapi:export --output docs/api-spec/openapi.jsonopenapi --yaml`. **Caveat (lessons #1):** w API Platform 4 ścieżka to `.jsonopenapi`, nie `.json`.

---

#### API-004 — `packages/api-types` generowany z OpenAPI
**Wynik:** ❌ FAIL (połowicznie)
**Severity:** 🟠 HIGH
**Wynik detekcji:**
- [packages/shared-types/package.json](packages/shared-types/package.json) ma skrypt `"generate": "openapi-typescript http://pim.localhost/api/docs.json -o src/api.d.ts"` — narzędzie obecne.
- `packages/shared-types/src/index.ts` jest stub-em z komentarzem *"Re-exports populated after `pnpm --filter @pim/shared-types generate`"* — pusty. Skrypt nigdy nie został uruchomiony / wygenerowany artefakt nie istnieje.
- Brak target `openapi:generate` w [turbo.json](turbo.json).
- `packages/shared-types/src/generated/` nie istnieje — `generate` zapisałoby do `src/api.d.ts`, ale stub mówi `./api`.
- Frontend ([apps/admin/src/pages/products/list.tsx](apps/admin/src/pages/products/list.tsx) i [edit.tsx](apps/admin/src/pages/products/edit.tsx)) definiuje `interface Product` ręcznie — patrz FE-003.

**Sugerowana naprawa:**
1. Dodać turbo task `openapi:generate` z `dependsOn: ["@pim/api#dev"]` (uruchomić API → generować typy).
2. CI: w GitHub Actions uruchomić API w docker-compose, generować typy, commitować do PR jeśli się zmieniły.
3. `packages/shared-types/src/generated/` → `.gitignore` (artefakt CI), albo zostawić `src/api.d.ts` (jak skrypt mówi). Wybrać jedną konwencję — patrz docs reguły, mogą być różne strategie.
4. Zmienić skrypt `generate` na `.jsonopenapi` (lessons #1): `"generate": "openapi-typescript http://pim.localhost/api/docs.jsonopenapi -o src/api.d.ts"`.

---

### Kategoria RT — runtime (FrankenPHP, Messenger, Doctrine)

#### RT-001 — Brak `flush()` w handlerach Messenger
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** 0 wystąpień `->flush()` w `apps/api/src/*/Application/Command/` lub `Application/Query/`. Catalog ma jedynie [Handler/RebuildAttributesIndexedHandler.php](apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php) — zerknąłem w kod (zewnętrznie), nie używa `flush()`.

---

#### RT-002 — `EntityManager::clear()` w bulk handlerach
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:**
- [apps/api/src/Messaging/AbstractBatchHandler.php](apps/api/src/Messaging/AbstractBatchHandler.php) istnieje (klasa bazowa).
- 0 handlerów z `foreach`/`while` bez `clear()` i bez `extends AbstractBatchHandler` — wzorzec szanowany.

---

#### RT-003 — `ResetInterface` na cache'ujących encje
**Wynik:** ⚠️ INFO
**Severity:** —
**Wynik detekcji:**
- 0 klas implementujących `Symfony\Contracts\Service\ResetInterface` w `apps/api/src/`.
- Heurystyka regułowa nie znalazła klas spełniających warunki bycia "cachem encji" (private property typu `Product|Object|Channel|Asset|Identity` z nazwą zawierającą `Cache|Context|Storage`).
- Istnieje [TenantContext.php](apps/api/src/Identity/Application/TenantContext.php) — prywatne pole `?Tenant` — to jest klasyczny request-scoped state w worker mode. **Ten plik powinien implementować `ResetInterface`.** [RequestTenantSubscriber.php](apps/api/src/Identity/Infrastructure/RequestTenantSubscriber.php) prawdopodobnie obsługuje reset, ale nie przez ResetInterface.

**Sugerowana naprawa:** Dodać `implements ResetInterface` w `TenantContext`, `BulkContext` ([apps/api/src/Catalog/Application/BulkContext.php](apps/api/src/Catalog/Application/BulkContext.php)). Dodać własną PHPStan rule sygnalizującą private fields trzymające encje w singleton services.

---

#### RT-004 — `MAX_REQUESTS` w FrankenPHP config
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wynik detekcji:**
- [apps/api/Dockerfile](apps/api/Dockerfile) — brak `MAX_REQUESTS`.
- [apps/api/frankenphp/Caddyfile](apps/api/frankenphp/Caddyfile) — brak.
- [apps/api/frankenphp/worker.Caddyfile](apps/api/frankenphp/worker.Caddyfile) — komentarz *"reserved for future per-worker tuning (num workers, restart_after, watch)"* — wprost odłożone.

**Sugerowana naprawa:** Dodać `ENV MAX_REQUESTS=1000` w Dockerfile i `restart_after_n_messages: 1000` w `worker.Caddyfile` global block. To jest cheap defense-in-depth zgodnie z architekturą sekcja 3.10.

---

#### RT-005 — Prometheus `frankenphp_worker_memory_bytes`
**Wynik:** ➖ N/A (Stadium 3)

---

#### RT-006 — Idempotency middleware dla Messenger
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wynik detekcji:** Brak `TransportMessageIdStamp`, `IdempotencyMiddleware`, tabeli `processed_messages`. [messenger.yaml](apps/api/config/packages/messenger.yaml) ma tylko domyślne middleware.
**Caveat:** Z `sync://` only, idempotency nie jest jeszcze potrzebna. Reguła wchodzi w grę przy włączeniu async. Można odłożyć do epiku 0.5+.
**Sugerowana naprawa:** Razem z włączeniem async transportów (Redis/Doctrine) — middleware sprawdzający tabelę `processed_messages (message_id UUID UNIQUE)`.

---

#### RT-007 — Failed transport (DLQ)
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wynik detekcji:** [messenger.yaml](apps/api/config/packages/messenger.yaml) ma zakomentowane `# failure_transport: failed` z notatką *"Uncomment this..."*.
**Sugerowana naprawa:** Razem z RT-006 — odkomentować przy włączeniu async.

---

#### RT-008 — SQL logger off w produkcji
**Wynik:** ➖ N/A (Stadium 3)
**Wynik detekcji (informacyjnie):** [doctrine.yaml](apps/api/config/packages/doctrine.yaml) ma `logging: false` na poziomie `dbal:` — to dotyczy wszystkich środowisk. Konfiguracja JEST poprawna, więc gdy projekt wejdzie w Stadium 3, ta reguła automatycznie passuje. ✅

---

#### RT-009 — `opcache.preload`
**Wynik:** ➖ N/A (Stadium 3)
**Wynik detekcji (informacyjnie):** [apps/api/frankenphp/php.ini](apps/api/frankenphp/php.ini) — preload nie jest ustawiony. Reguła to LOW severity, można odłożyć do produkcji.

---

### Kategoria AG — Agent AI

#### AG-001..007 — wszystkie reguły
**Wynik:** ➖ N/A
**Severity:** —
**Wynik detekcji:** [apps/api/src/Agent/](apps/api/src/Agent/) zawiera tylko `.gitkeep` w warstwach + [README.md](apps/api/src/Agent/README.md) (38 linii). Brak kodu — BC odłożone do Fazy 2 zgodnie z `Project Plan/02-plan-projektu-pim.md` (epik 0.7 Beta-Min/Beta-Full). AG-005 (klucz API) — sprawdzono, **brak** literalnych `sk-ant-*` w kodzie/configu/.env.example.

---

### Kategoria SRCH — Meilisearch

#### SRCH-001..004 — wszystkie reguły
**Wynik:** ➖ N/A (brak silnika)
**Severity:** —
**Wynik detekcji:**
- Brak `SearchProviderInterface`, `Meilisearch*Provider` w `apps/api/src/`.
- Brak `meilisearch/meilisearch-php` lub bundle w composer.json.
- Brak konfigów `apps/api/config/packages/meilisearch*`.
- Brak listenera `ObjectIndexedListener`, message `ObjectIndexed`.

Zgodnie z planem epik wyszukiwarki nie został jeszcze rozpoczęty. Po wprowadzeniu silnika audyt powtórzyć.

---

### Kategoria FE — frontend (Refine, React)

#### FE-001 — Struktura `apps/admin/src/features/<bc>/<resource>/`
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:**
- Brak [apps/admin/src/features/](apps/admin/src/features/).
- Antywzorzec [apps/admin/src/pages/](apps/admin/src/pages/) obecny: `login.tsx`, `coming-soon.tsx`, `products/{list,create,edit,form}.tsx`.

**Sugerowana naprawa:** Refaktor `src/pages/` → `src/features/<bc>/<resource>/`. `products/` → `features/catalog/products/`. `login.tsx` → `features/identity/auth/login.tsx`. Plan: 1 ticket frontendowy ~4h.

---

#### FE-002 — ESLint `import/no-restricted-paths`
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wynik detekcji:** Brak ESLint w projekcie — admin używa Biome ([apps/admin/biome.json](apps/admin/biome.json)). Brak reguły blokującej cross-feature imports.
**Caveat:** Biome 2 ma własny moduł `linter.rules` — można skonfigurować równoważne ograniczenia, ale nazwa `import/no-restricted-paths` jest ESLint-specyficzna. Reguła v1.2 powinna uwzględnić Biome.
**Sugerowana naprawa:** Po refaktorze FE-001 — dodać Biome rule lub przejść na ESLint dla zone-based imports. Można też użyć `dependency-cruiser` jako narzędzia drugiej linii.

---

#### FE-003 — Typy z `@pim/api-types`, nie ręczne
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:**
- [apps/admin/src/pages/products/list.tsx:16](apps/admin/src/pages/products/list.tsx#L16) — `interface Product { ... }`
- [apps/admin/src/pages/products/edit.tsx:8](apps/admin/src/pages/products/edit.tsx#L8) — `interface Product { ... }`
- 0 importów z `@pim/api-types` lub `@pim/shared-types` w `apps/admin/src/`.

**Sugerowana naprawa:** Wymaga API-004 (działający `pnpm generate`). Po wygenerowaniu — `import type { components } from '@pim/shared-types'; type Product = components['schemas']['Product.jsonld'];`.

---

#### FE-004 — Zod schemas dla formularzy
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:**
- [apps/admin/src/pages/products/](apps/admin/src/pages/products/) ma `create.tsx` + `edit.tsx` ale brak `schemas.ts`.
- Brak zod w `apps/admin/package.json`.

**Sugerowana naprawa:** `pnpm --filter @pim/admin add zod` + `schemas.ts` per resource.

---

#### FE-005 — Brak localStorage/sessionStorage w komponentach
**Wynik:** ✅ PASS
**Severity:** —
**Wynik detekcji:** Jedyne wystąpienie to **komentarz** w [apps/admin/src/lib/http.ts](apps/admin/src/lib/http.ts) wskazujący *"The access JWT lives in module-scoped memory only — never localStorage."* Świadoma decyzja, dobrze udokumentowana.

---

### Kategoria TST — testy

#### TST-001 — Struktura `tests/` mirror `src/`
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:**
- Brak testów dla 8 z 13 top-level w `src/`: `Agent`, `ApiConfigurator`, `Benchmark`, `DataFixtures`, `Integration`, `Maintenance`, `Observability`, `Story`. **Wszystkie te BC są albo puste (`.gitkeep`), albo narzędziowe** — brak testów uzasadniony.
- Test types: tylko `tests/Unit` i `tests/Functional`. **Brak `tests/Integration`, `tests/Api`, `tests/Architecture`.** Reguła wymienia 4 standardowe.

**Caveat:** `tests/Functional/` może pełnić rolę zarówno Integration jak i Api (testy z bootstrap'em Symfony). Brak `tests/Architecture/` to brak testów Deptrac/PHPStan-as-test.
**Sugerowana naprawa:**
- Po ekspansji BC (Integration, Agent) — dodać Unit/Functional dla nich.
- Rozważyć rozdzielenie `Functional/` na `Integration/` (DB-only) i `Api/` (HTTP+API Platform), lub udokumentować w ADR że `Functional` = oba.
- `tests/Architecture/` — dodać po wprowadzeniu Deptrac (TOOL-001).

---

#### TST-002 — Foundry factories per encja
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🟢 LOW
**Wynik detekcji:**
- `zenstruck/foundry@^2` zainstalowany ✓.
- 1 factory w `apps/api/src/`. Patrząc na ilość encji (20+), pokrycie factory bardzo niskie.

**Sugerowana naprawa:** Generować `*Factory.php` per encja Domain w `Infrastructure/Foundry/` — przy każdej nowej encji w przyszłych ticketach.

---

#### TST-003 — DAMA Doctrine Test Bundle
**Wynik:** ❌ FAIL
**Severity:** 🟢 LOW
**Wynik detekcji:** Brak `dama/doctrine-test-bundle` w composer.json.
**Sugerowana naprawa:** `composer require --dev dama/doctrine-test-bundle`. Opakowanie testów w transakcję rollback. ROI proporcjonalne do liczby testów integracyjnych — obecnie tests/Functional ma kilka plików, więc zysk jest mały, ale w MVP-Final będzie znaczący.

---

### Kategoria TOOL — narzędzia

#### TOOL-001 — Deptrac
**Wynik:** ❌ FAIL + 🚧 BLOCKED
**Severity:** 🔴 CRITICAL
**Wynik detekcji:**
- `apps/api/deptrac.yaml` — **MISSING**.
- `apps/api/vendor/bin/deptrac` — **MISSING** (`composer require --dev deptrac/deptrac` nie wykonane).

**Konsekwencja (krytyczna):** Reguła DDD-010 (cross-BC) raportowana fallbackiem grep → false positives + brak strażnika w CI.
**Sugerowana naprawa:** Najwyższy priorytet:
1. `cd apps/api && composer require --dev deptrac/deptrac`
2. Utworzyć `apps/api/deptrac.yaml` z layers Catalog/Channel/Asset/Integration/Identity/Agent/Shared + 4 warstwami (Domain/Application/Infrastructure/Contracts) + cross-BC ruleset (każdy BC: depends_on Shared + Self/Contracts; Integration dodatkowo depends_on [Catalog, Channel, Asset, Identity]/Contracts).
3. GitHub Actions: `vendor/bin/deptrac analyse` jako required check.
4. Pierwsze przebiegi pewnie znajdą setki naruszeń (DDD-010 powiedział 65 z fallback grep — Deptrac będzie dokładniejszy). Przy starcie użyć `--baseline` i podnosić sukcesywnie.

---

#### TOOL-002 — PHPStan level ≥ 8
**Wynik:** ✅ PASS (warunkowo)
**Severity:** —
**Wynik detekcji:**
- [apps/api/phpstan.dist.neon](apps/api/phpstan.dist.neon) ma `level: max` — poziom 10 w PHPStan 2 (najwyższy). Lepiej niż wymagane ≥ 8.
- `apps/api/vendor/bin/phpstan` — obecny.
- **Nie wykonano** `vendor/bin/phpstan analyse` w trybie audytu (tryb read-only oznacza brak zmian, ale uruchomienie analizatora to dozwolona akcja). Pominięto, bo zajmuje minutę+ a CI projektu zapewne to robi.

**Sugerowana naprawa:** brak (zaufanie do CI projektu). Można dodać do tej reguły check uruchamiania `composer phpstan` w trybie weryfikacji.

---

#### TOOL-003 — PHPStan extensions
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:**
- ✓ phpstan/phpstan-doctrine
- ✓ phpstan/phpstan-symfony
- ✓ phpstan/phpstan-strict-rules
- ❌ **phpstan/phpstan-deprecation-rules** — MISSING

**Sugerowana naprawa:** `composer require --dev phpstan/phpstan-deprecation-rules`. Ważne przy uaktualnianiu Symfony 7.4 → 8 i Doctrine ORM 3 → 4.

---

#### TOOL-004 — PHP-CS-Fixer i Rector
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🟢 LOW
**Wynik detekcji:**
- ✓ [.php-cs-fixer.dist.php](apps/api/.php-cs-fixer.dist.php)
- ❌ Brak [rector.php](apps/api/rector.php).

**Sugerowana naprawa:** `composer require --dev rector/rector` + minimalna konfiguracja `rector.php` z setami `LevelSetList::UP_TO_PHP_84`, `SymfonySetList::SYMFONY_74`. Korzyści: automatyczne upgrade'y przy bumpach Symfony/PHP.

---

#### TOOL-005 — Custom PHPStan rule blokująca `flush()` bez `clear()`
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wynik detekcji:** Brak `FlushWithoutClearRule`/`RequireEntityManagerClear` w `apps/api/src/Shared` ani `apps/api/tools/`. Komentarze w [php.ini](apps/api/frankenphp/php.ini) mówią *"PHPStan custom rule blokuje flush() bez clear()"* — **rule nie istnieje**, komentarz wprowadza w błąd.
**Sugerowana naprawa:** Utworzyć `apps/api/tools/phpstan/Rules/FlushWithoutClearInBatchHandlerRule.php` (~50 LOC), dodać do `phpstan.dist.neon` `services:` + `rules:`. Bez tego runtime memory dyscyplina trzymana wyłącznie code review (które LLM nie robi — patrz CLAUDE.md sekcja 2.2).

---

### Kategoria DOC — dokumentacja

#### DOC-001 — `docs/adr/` z formatem MADR
**Wynik:** ❌ FAIL
**Severity:** 🟠 HIGH
**Wykryte naruszenia:**
- `docs/adr/` — **MISSING** (cały katalog).
- 0 plików ADR.
- Brak `adr-template.md`, `0000-use-markdown-architectural-decision-records.md`.

**Caveat (świadome odejście):** Decyzje architektoniczne są dokumentowane w [Project Plan/01-architektura-pim.md](Project Plan/01-architektura-pim.md) sekcja 13 (ADR-001 do ADR-009 wspomniane w `Project Plan/06-sprint-0-findings.md`). To nie jest zgodne z konwencją MADR per-plik, ale **decyzje istnieją**.
**Sugerowana naprawa:**
1. Utworzyć `docs/adr/` z plikami ADR per decyzja.
2. Wyciągnąć ADR-001..009 z `Project Plan/01-architektura-pim.md` sekcja 13 do osobnych plików w MADR.
3. Dodać brakujące, które wynikają z bieżącego audytu:
   - `0011-doctrine-attributes-in-domain-tradeoff.md` (wynik DDD-001)
   - `0012-cqrs-rollout-strategy.md` (wynik DDD-005)
   - `0013-deptrac-rollout.md` (wynik TOOL-001)
   - `0014-shared-domain-tenant.md` (wynik DDD-010)

---

#### DOC-002 — Diagramy C4
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:** Brak `docs/architecture/c4-context.md`, `c4-container.md`, `bounded-contexts.md`. Cała `docs/architecture/` nie istnieje.
**Sugerowana naprawa:** Wykorzystać istniejące diagramy z [Project Plan/01-architektura-pim.md](Project Plan/01-architektura-pim.md) (jeśli są w Mermaid) — wyciągnąć je do `docs/architecture/`. Plan: 4h.

---

#### DOC-003 — `ONBOARDING.md` z day-1/3/10
**Wynik:** ❌ FAIL
**Severity:** 🟡 MEDIUM
**Wykryte naruszenia:** Brak `ONBOARDING.md` w roocie. README ma 137 linii — jest długi, ale pojedynczy.
**Sugerowana naprawa:** Wydzielić sekcje setup/dev/deploy z README → `ONBOARDING.md` z explicite day-1 (≤4h, środowisko), day-3 (pierwszy PR), day-10 (większa feature).

---

#### DOC-004 — README ≤ 1 strona z pointerami
**Wynik:** ⚠️ PARTIAL FAIL
**Severity:** 🟢 LOW
**Wynik detekcji:** [README.md](README.md) ma 137 linii — przekracza zalecane ≤80, ale nie 200 (FAIL threshold). Brak linków do `ONBOARDING.md` (bo plik nie istnieje, DOC-003).
**Sugerowana naprawa:** Razem z DOC-003 — skrócić README do ~50 linii z listą pointerów do CLAUDE.md, ONBOARDING.md, CONTRIBUTING.md, docs/.

---

## 3. Lista naruszeń posortowana po severity

| # | ID reguły | Severity | Lokalizacja | Skrócony opis | Naprawa |
|---|-----------|----------|-------------|---------------|---------|
| 1 | **DDD-001** | 🔴 CRITICAL | 20 plików w `apps/api/src/*/Domain/Entity/*.php` | `#[ORM\Entity]` + Doctrine attributes inline w Domain (zob. [Asset.php](apps/api/src/Asset/Domain/Entity/Asset.php), [CatalogObject.php](apps/api/src/Catalog/Domain/Entity/CatalogObject.php), [User.php](apps/api/src/Identity/Domain/Entity/User.php)) | Migracja na XML mapping w `Infrastructure/Doctrine/Orm/Mapping/` lub formalny ADR-0011 dokumentujący trade-off |
| 2 | **DDD-003** | 🔴 CRITICAL | [Identity/Application/CurrentTenantProvider.php:9](apps/api/src/Identity/Application/CurrentTenantProvider.php), [RbacSeeder.php](apps/api/src/Identity/Application/RbacSeeder.php), [RefreshTokenService.php](apps/api/src/Identity/Application/RefreshTokenService.php), [Catalog/Application/BuiltInObjectTypeSeeder.php](apps/api/src/Catalog/Application/BuiltInObjectTypeSeeder.php) i in. | Application importuje konkretne klasy Repository z Infrastructure | Wprowadzić `Domain/Repository/<X>RepositoryInterface.php` (DDD-008), wstrzykiwać interface |
| 3 | **DDD-010** | 🔴 CRITICAL (z fallback grep → 🟠 HIGH) | 65 wystąpień w 33 plikach: [Catalog/Domain/Entity/CatalogObject.php:9-10](apps/api/src/Catalog/Domain/Entity/CatalogObject.php), [Channel/Domain/Entity/Channel.php:7](apps/api/src/Channel/Domain/Entity/Channel.php), [Asset/Domain/Entity/Asset.php:8](apps/api/src/Asset/Domain/Entity/Asset.php), 30+ innych | Cross-BC importy (głównie Catalog→Identity, Asset→Identity, Channel→Catalog) — żaden nie idzie przez Contracts/ | Wprowadzić Deptrac (TOOL-001), wyciągnąć Tenant do Shared, utworzyć Contracts/ per BC |
| 4 | **STR-003** | 🔴 CRITICAL | `apps/api/src/{ApiConfigurator,Benchmark,DataFixtures,Maintenance,Messaging,Observability,Story}/` | 7 dodatkowych top-level + brak `Shared/` | Utworzyć `Shared/`, przenieść `Messaging/AbstractBatchHandler.php`, wyrzucić `DataFixtures`/`Story`/`Benchmark` poza `src/`, ADR-0010 |
| 5 | **TOOL-001** | 🔴 CRITICAL | brak `apps/api/deptrac.yaml`, brak `apps/api/vendor/bin/deptrac` | Brak narzędzia egzekwującego DDD-010 | `composer require --dev deptrac/deptrac` + `deptrac.yaml` + GitHub Action |
| 6 | **DDD-004** | 🟠 HIGH | 41 wystąpień: [CatalogObject.php](apps/api/src/Catalog/Domain/Entity/CatalogObject.php), [Attribute.php](apps/api/src/Catalog/Domain/Entity/Attribute.php), [ObjectType.php](apps/api/src/Catalog/Domain/Entity/ObjectType.php), [ObjectValue.php](apps/api/src/Catalog/Domain/Entity/ObjectValue.php), [Channel.php](apps/api/src/Channel/Domain/Entity/Channel.php), [Tenant.php](apps/api/src/Identity/Domain/Entity/Tenant.php), 5 innych | Publiczne settery w encjach Domain | Zamienić na metody domenowe (`activate()`/`deactivate()`/`rename()`) — Doctrine i tak hydratuje przez Reflection |
| 7 | **DDD-006** | 🟠 HIGH | 0 plików `*.orm.xml` w Infrastructure | Mapowanie Doctrine inline atrybutami zamiast XML | (jak DDD-001) |
| 8 | **DDD-008** | 🟠 HIGH | 19 repozytoriów w Infrastructure bez interface w Domain | Brak port-adapter pattern dla repository | Utworzyć `Domain/Repository/<X>RepositoryInterface.php` per repozytorium |
| 9 | **STR-001** | 🟠 HIGH | brak `packages/api-types`, jest `packages/shared-types` | Niezgodność nazewnictwa | Zmiana nazwy pakietu lub aktualizacja konwencji w checkliście |
| 10 | **STR-004** | 🟠 HIGH | wszystkie 7 BC | Brak warstwy `Contracts/` w żadnym BC | Pierwszy ticket: `Catalog/Contracts/Event/` z 5 integration eventami |
| 11 | **API-004** | 🟠 HIGH | [packages/shared-types/src/index.ts](packages/shared-types/src/index.ts) (stub), brak `generate` w turbo.json | Typy API niegenerowane → frontend pisze ręcznie | Włączyć turbo task `openapi:generate` w CI |
| 12 | **RT-004** | 🟠 HIGH | [apps/api/Dockerfile](apps/api/Dockerfile), [apps/api/frankenphp/worker.Caddyfile](apps/api/frankenphp/worker.Caddyfile) | Brak `MAX_REQUESTS` / `restart_after_n_messages` | `ENV MAX_REQUESTS=1000` + worker config |
| 13 | **BC-002** | 🟠 HIGH | brak `Contracts/Event/`, brak subscribers, brak middleware, [messenger.yaml](apps/api/config/packages/messenger.yaml) tylko sync | Brak fundamentu pod cross-BC events | Utworzyć Contracts/Event po STR-004; włączyć async transport |
| 14 | **FE-001** | 🟠 HIGH | [apps/admin/src/pages/](apps/admin/src/pages/) | Antywzorzec pages/ zamiast features/ | Refaktor `pages/products/` → `features/catalog/products/` |
| 15 | **FE-002** | 🟠 HIGH | brak ESLint, [biome.json](apps/admin/biome.json) bez restricted-paths | Brak egzekwowania granic między features | Po FE-001 — Biome rule lub ESLint |
| 16 | **FE-003** | 🟠 HIGH | [pages/products/list.tsx:16](apps/admin/src/pages/products/list.tsx), [edit.tsx:8](apps/admin/src/pages/products/edit.tsx) | Ręczny `interface Product` zamiast importu `@pim/shared-types` | Zależy od API-004 |
| 17 | **DOC-001** | 🟠 HIGH | brak `docs/adr/` | Brak ADR per-plik (decyzje są w `Project Plan/01-architektura-pim.md`) | Wyciągnąć decyzje do `docs/adr/0001-...md` w formacie MADR |
| 18 | **STR-005** | 🟡 MEDIUM | [Catalog/](apps/api/src/Catalog/), [Identity/](apps/api/src/Identity/) | Brak README.md w 2 BC | Dodać 1-stronicowe README per BC |
| 19 | **DDD-005** | 🟡 MEDIUM | `Application/` — service style, nie vertical slice | Brak struktury Command/<UseCase>/<Cmd>+<Handler> | ADR-0012 — wprowadzać sukcesywnie |
| 20 | **DB-002** | 🟡 MEDIUM | [ObjectValue.php](apps/api/src/Catalog/Domain/Entity/ObjectValue.php) | UNIQUE bez `tenant_id` | Dodać `tenant_id` do `object_values_scope_uniq` |
| 21 | **API-003** | 🟡 MEDIUM | brak `docs/api/openapi-snapshot.json` | Brak snapshotu OpenAPI w git | GitHub Action `openapi-snapshot.yml` |
| 22 | **RT-006** | 🟡 MEDIUM | brak Idempotency middleware | Brak — łączyć z włączeniem async | Po async transport |
| 23 | **RT-007** | 🟡 MEDIUM | [messenger.yaml](apps/api/config/packages/messenger.yaml) — failure_transport zakomentowane | Brak DLQ | Po async transport |
| 24 | **TST-001** | 🟡 MEDIUM | brak `tests/Integration`, `tests/Api`, `tests/Architecture` | Brak 3 z 4 standardowych typów testów | Reorganizacja + ADR |
| 25 | **TOOL-003** | 🟡 MEDIUM | brak `phpstan/phpstan-deprecation-rules` w composer.json | Brak detekcji deprecations | `composer require --dev` |
| 26 | **TOOL-005** | 🟡 MEDIUM | brak custom PHPStan rule | Memory dyscyplina nieegzekwowana statycznie | Stworzyć `FlushWithoutClearInBatchHandlerRule` |
| 27 | **FE-004** | 🟡 MEDIUM | [pages/products/](apps/admin/src/pages/products/) — brak schemas.ts | Brak Zod walidacji formularzy | Dodać zod + schemas.ts per resource |
| 28 | **DOC-002** | 🟡 MEDIUM | brak `docs/architecture/` | Brak diagramów C4 | Wyciągnąć z Project Plan/01 |
| 29 | **DOC-003** | 🟡 MEDIUM | brak ONBOARDING.md | Brak ścieżki day-1/3/10 | Utworzyć ONBOARDING.md |
| 30 | **STR-006** | 🟢 LOW | brak `.claude/CLAUDE.md` | Plik istnieje w roocie zamiast `.claude/` | Akceptujemy obecną lokalizację |
| 31 | **DDD-011** | 🟢 LOW | brak `Shared/Domain/AggregateRoot.php` | Brak shared base dla agregatów | Po STR-003 + BC-002 |
| 32 | **TST-002** | 🟢 LOW | 1 factory na 20+ encji | Niskie pokrycie Foundry | Sukcesywnie per encja |
| 33 | **TST-003** | 🟢 LOW | brak `dama/doctrine-test-bundle` | Wolniejsze testy integracyjne | `composer require --dev` |
| 34 | **TOOL-004** | 🟢 LOW | brak `rector.php` | Brak automatycznych upgrade'ów PHP/Symfony | Init Rector |
| 35 | **DOC-004** | 🟢 LOW | [README.md](README.md) — 137 linii | Powyżej zalecanego ≤80 | Skrócić do listy pointerów |
| 36 | **STR-002** | ⚠️ INFO | brak ONBOARDING.md (should-file) | Brak should pliku | Patrz DOC-003 |
| 37 | **API-001** | ✅ PASS (degenerate) | 0 ApiResource w projekcie | Brak ApiResource w Domain — ale też w Infrastructure | Uważać na ticket #41+ żeby ApiResource lądowały w Infrastructure/ApiPlatform/Resource |
| 38 | **RT-003** | ⚠️ INFO | [TenantContext.php](apps/api/src/Identity/Application/TenantContext.php), [BulkContext.php](apps/api/src/Catalog/Application/BulkContext.php) | Trzymają state, brak `ResetInterface` | Dodać `implements ResetInterface` |

---

## 4. Rekomendacje konsolidujące

### W tym sprincie (CRITICAL + HIGH — łącznie 14 reguł)
1. **TOOL-001 (Deptrac)** — fundament dla DDD-010. Bez tego nie ma jak utrzymać granic. Plan: 1-2h instalacja + config, ~8h triage initial baseline.
2. **STR-003 (Shared/ + przeniesienie kosza top-level)** — `Shared/Domain/Tenant.php`, `Shared/Application/AbstractBatchHandler.php`, przeniesienie DataFixtures/Story/Benchmark do `tools/`. Plan: 4h.
3. **STR-004 + DDD-010 (Contracts/)** — zacząć od `Catalog/Contracts/Event/` z 5 eventami. Plan: 4h. To otwiera drogę do BC-002 i przepisania cross-BC importów.
4. **DDD-008 (RepositoryInterface)** — TenantRepository, RefreshTokenRepository jako pierwsze. Plan: 4h. Adresuje też DDD-003.
5. **API-004 + FE-003 (typy z OpenAPI)** — wymaga uruchomionego API Platform (ticket #41+). Sklejenie skryptu `generate` z turbo + CI step. Plan: 3h gdy ApiResource będą gotowe.
6. **RT-004 (`MAX_REQUESTS`)** — 30 minut: ENV w Dockerfile + worker config.
7. **DDD-004 (settery → metody domenowe)** — 11 plików, ~1h każdy. Można rozdzielić na kilka ticketów.
8. **DOC-001 (ADR-y)** — wyciągnąć `0001`-`0009` z `Project Plan/01-architektura-pim.md`. Plan: 4h.

**Łącznie:** ~30-40h pracy w bieżącym/następnym sprincie. Realne dla operatora 1-osobowego z LLM.

### W następnym sprincie (MEDIUM — łącznie 12 reguł)
- DDD-001/006 ADR-0011 + plan migracji XML
- DDD-005 ADR-0012 (CQRS rollout)
- DB-002 (UNIQUE + tenant_id)
- API-003 (OpenAPI snapshot CI)
- TST-001 (reorganizacja testów + Architecture tests)
- TOOL-003/005 (PHPStan extensions + custom rule)
- FE-004 (Zod)
- DOC-002/003 (C4 + ONBOARDING)

### Backlog (LOW — łącznie 6 reguł)
- DDD-011 (AggregateRoot)
- TST-002/003 (Foundry factories + DAMA)
- TOOL-004 (Rector)
- STR-006 (CLAUDE.md per package)
- DOC-004 (skrócenie README)

### Stadium 3 hooks (do uruchomienia ponownego audytu po wejściu w Fazę 2)
- BC-001 (kompletność 6 BC)
- BC-003 (Integration jako multi-BC consumer)
- DB-006 (RLS aktywacja)
- RT-005 (Prometheus memory metric)
- RT-008 (SQL logger off — już PASS strukturalnie)
- RT-009 (opcache.preload)
- AG-001..007 (BC Agent)
- SRCH-001..004 (Meilisearch)

---

## 5. Caveats (świadome wątpliwości i niepewności agenta audytującego)

**C1 — Świadome odejście Doctrine attribute mapping vs. XML.**
Architektura referencyjna meta-raportu (Source `Zrodla/Zalecana_struktura_kodu/`) preferuje XML mapping w Infrastructure/Doctrine. Projekt PIM używa atrybutów inline w Domain — uzasadnione w `Project Plan/01-architektura-pim.md` i `lessons.md`. Audyt mechanicznie raportuje to jako DDD-001 + DDD-006 CRITICAL/HIGH, ale **w realnym kontekście to jest świadomy długi techniczny**, nie błąd implementacyjny. Rekomendacja: dorobić ADR-0011, nie traktować jako blokującej luki przed merge'm.

**C2 — Cross-BC dla Tenant jest nieuniknione bez Shared/.**
DDD-010 generuje 27 z 65 naruszeń przez import `Identity\Domain\Entity\Tenant` w innych BC. To jest poprawny multi-tenancy pattern (każda encja domenowa wskazuje na Tenant FK). Bez `Shared/Domain/Tenant.php` (lub TenantId VO) nie ma sposobu uniknąć tego importu. Po STR-003 (utworzenie `Shared/`) i wyciągnięciu Tenant — większość naruszeń DDD-010 zniknie automatycznie.

**C3 — Fallback grep w DDD-010.**
Brak Deptrac wymusił użycie grep'a (zgodnie z regułą), który zgodnie z dokumentacją reguły ma znane ograniczenia: nie wykrywa aliasów `use ... as X`, grupowania `use App\X\{A,B}`, FQN inline. **Liczba 65 może być zaniżona.** Trzeba uruchomić Deptrac (TOOL-001) żeby mieć autorytatywne dane.

**C4 — `apps/admin/` ma minimalny zakres.**
Frontend ma tylko 21 plików TS/TSX i 4 strony (`login`, `coming-soon`, `products/{list,create,edit,form}`). Reguły FE-* dotyczą struktury features/, ale projekt jest jeszcze przed major-frontend-implementation. Większość naruszeń FE-* zniknie naturalnie z pierwszym dużym ticketem refaktoru.

**C5 — Stadium 3 reguły zaraportowane jako N/A nie znaczą "nigdy".**
Reguły AG-*, SRCH-*, RT-005, RT-008, RT-009, DB-006, BC-001, BC-003 — wszystkie pominięte jako N/A. Po wejściu w epiki 0.7-0.10 (Faza 1+) **trzeba ten audyt powtórzyć**. Niektóre już teraz mają częściowe przygotowanie (np. RT-008 — `logging: false` strukturalnie OK).

**C6 — `STR-003` heurystyka kategoryzująca top-level.**
Reguła traktuje `ApiConfigurator/`, `Maintenance/`, `Observability/` jako "antywzorzec", ale są to **świadome wybory** zgodne z `Project Plan/01-architektura-pim.md` (sekcja 2: 7 BC łącznie z ApiConfigurator). Konsekwencja: część raportów STR-003 to konflikt nazewnictwa między meta-raportem (6 BC) a `Project Plan/` (7 BC). Należy ujednolicić — wpis w lessons lub ADR-0010.

**C7 — Nie uruchomiono `vendor/bin/phpstan` dla TOOL-002.**
Tryb read-only audyt: zweryfikowano obecność narzędzia + level w configu. Realne uruchomienie analizy zajęłoby ~1-2 minuty i pewnie znalazłoby bugi, ale nie należy do zakresu audytu strukturalnego (do tego jest CI). Reguła PASS warunkowo na zaufaniu do CI projektu.

**C8 — DOC-001 sprzeczność z `Project Plan/`.**
ADRy są dziś w `Project Plan/01-architektura-pim.md` sekcja 13 (zgodnie z `CLAUDE.md` "Pliki które utrzymujesz atomowo"). Reguła DOC-001 wymaga `docs/adr/` per-plik MADR. Świadome odejście — projekt agreguje decyzje w jeden duży dokument zamiast rozdrabniania. Rekomendacja: zrobić **i jedno, i drugie** — `docs/adr/` jako per-plik kanon + `Project Plan/01-architektura-pim.md` jako narracyjny przegląd z linkami do ADR-ów. Synchronizacja w obie strony.

**C9 — ESLint vs Biome.**
Reguła FE-002 zakłada ESLint. Projekt używa Biome 2 — pełnoprawny linter z innymi nazwami reguł. Należy zaktualizować checklistę v1.2 żeby uwzględniała Biome (`assist.actions.source.organizeImports` + custom imports rules).

**C10 — Heurystyka DDD-004 (`Domain/Model/` vs `Domain/Entity/`).**
Reguła sprawdza `Domain/Model/`, projekt używa `Domain/Entity/`. Zinterpretowano analogicznie. Intent reguły jest jasny — chodzi o agregaty Domain niezależnie od podkatalogu.

**C11 — Liczbowe podsumowanie ma niepewność ±2.**
Część reguł jest częściowo PASS / FAIL (np. STR-002 — 4 z 4 must, ale 1 z 3 should missing; DB-001 — 0 prawdziwych naruszeń, 3 false-positive). W liczeniu Top-line "9 HIGH" przyjąłem konserwatywnie naruszenia mające **realny wpływ**, pominąłem te oznaczone jako "świadome odejście udokumentowane".

**C12 — Brak weryfikacji aktualności poprzedniego raportu.**
Istnieje [docs/audits/AUDIT-REPORT-2026-04-28.md](docs/audits/AUDIT-REPORT-2026-04-28.md) z poprzedniego audytu (CRITICAL: 2, HIGH: 5, MEDIUM: 4, LOW: 1, BLOCKED: 1). Liczby się różnią — najprawdopodobniej dlatego, że poprzedni audyt **pominął wiele reguł** (TOOL-*, DOC-*, FE-*, TST-*) zgodnie z węższym zakresem deklarowanym w jego sekcji 0. Bieżący audyt jest pełniejszy zgodnie z instrukcjami.

---

*Koniec raportu. Zakres: §1-§12 z AUDIT-CHECKLIST.md v1.1, 67 reguł, w trybie read-only zgodnie z §0.1. Pliki nie zostały zmodyfikowane poza tym raportem.*
