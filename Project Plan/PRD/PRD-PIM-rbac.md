# Schemat logiczny — RBAC dla Cortex PIM (wsad dla PRD) — v2 comprehensive

**Adresat:** Analityk Biznesowy przygotowujący PRD modułu Role i Uprawnienia
**Autor:** Lead Systems Analyst — synteza wymagań projektowych Cortex PIM + best practices z Gemini/DeepSeek
**Data:** 2026-05-15 (revision 2: brak Faza 1 cuts, dodano Super Admin + Approver, field-level + resource policies + workflow-state permissions)
**Status:** Draft — pełen scope MVP bez odkładania na fazy

> **Nota o scope.** Dokument w wersji v2 stanowi **pełen RBAC dla Cortex PIM bez podziału na fazy**. Decyzja właściciela: *„nic nie dzielimy na fazy, wszystko teraz, raz a dobrze, iterujemy tyle razy ile trzeba"*. To zwiększa estymację z ~110-160h (v1 hybrid) do **~250-380h** total, ale eliminuje technical debt + refactor risk + breaking changes na produkcji.
>
> **Update v2.1 (2026-05-16):** §3.5 przeprojektowana z negative blacklist (`restricted_roles`) na 3-state positive grants (`role_attribute_permissions` + `role_attribute_group_permissions`) z resolution order attribute → group → role default. Dodaje ~+23-35h scope (UI 3-state toggle + bulk per-group + cross-tab communication + migration script). Łącznie ~273-415h.
>
> **Sequencing potwierdzony**: RBAC implementowany **teraz**, przed kontynuacją feature'ów które dotykają user/role/audit concepts (`feature-list-advanced.md` cross-user audit, `feature-exports.md` API tokens scoped, Cmd+K agent permissions, bulk operations gating).

---

## 1. Strategia wdrożenia uprawnień — kiedy projektować RBAC

### 1.1 Trzy okna w cyklu życia produktu

| Okno | Charakterystyka | Werdykt |
|---|---|---|
| **Sprint 0 / fundament** (pierwsze 5-10% kodu) | Schema PIM nieustalona — brak ObjectType, brak channels, brak bounded contexts. RBAC tu = guesswork. | **Nie wdrażać.** |
| **Mid-build** (40-70% funkcjonalności core gotowych) | Schema ustabilizowana, lista modułów znana, persony zwalidowane. Klient jeszcze nie ma production users. | **Optymalne okno.** |
| **Late-MVP / Post-launch** | Production users obecni, 100+ endpoints + 200+ komponentów UI zbudowanych bez guardów. Refactor = breaking change. | **Nie wdrażać. Maksymalny dług.** |

### 1.2 Uzasadnienie — koszt opóźnienia rośnie nieliniowo

Koszt wdrożenia RBAC = funkcja `f(liczba_endpointów × liczba_komponentów × stan_production)`:

- **Sprint 0**: `f(0 × 0 × 0) ≈ 0h`, ale **wartość = 0** (brak modeli do zabezpieczenia, ryzyko premature abstraction).
- **Mid-build (40-70% kompletności)**: `f(50 × 100 × brak production) ≈ 60-90h` refactor + 150-250h core implementation = **210-340h total**. Ryzyko niskie.
- **Late-MVP / Post-launch**: `f(150 × 300 × ma production users) ≈ 80-120h refactor + 150-250h core + 30-50h migration / notification / downtime` = **260-420h total**. Ryzyko wysokie.

Różnica między *„teraz"* a *„później"* to nie estymacja, lecz **ryzyko** — breaking change na produkcji, session invalidation, cross-tenant leakage przy refactor, dług który blokuje rozwój nowych feature'ów.

### 1.3 Sześć cross-cutting concerns które wymuszają wczesny RBAC

Te elementy *nie są dodatkiem* — są **prerequisite** każdego endpointa i komponentu:

1. **API tokens scopes** — token bez scope = all-access. Dodanie scopes po onboardingu klientów = breaking change ich integracji (Allegro, Shopify, BaseLinker).
2. **Audit log permission check results** — bez `granted/denied` w logach audit jest bezużyteczny do compliance (ISO 27001, SOC 2 require granular access logs).
3. **Frontend show/hide per-role** — refactor każdego sidebar, toolbar, modal create button, action menu = pełen sprint dedicated. Dodatkowo: pole formularza renderowane jako tekst vs input (Gemini insight) wymaga schema dostępnego dla renderera.
4. **Tenant boundary defence-in-depth** — Doctrine filter + RLS + permission check + Super Admin cross-tenant bypass to **cztery warstwy** które wymagają spójnego user/role/tenant modelu od dnia 1.
5. **Cmd+K agent rate limits + permission delegation** — limity `50 tool calls/h/user` zakładają user identity tracking, agent działa *„as the user"* (nie superuser) — permissions check w każdym tool call.
6. **Field-level secret scrubbing** (Gemini insight) — Integration Manager widzi config Shopify ale **nie** tajny token. Bez field-level filtering w serializerze tokeny wycieką do response. To NIE jest *„dodatkowa funkcja"*, to security baseline.

### 1.4 Rekomendacja sequencingu dla Cortex PIM

**Status obecny (2026-05-15)**: ~60% MVP funkcjonalności core zbudowane. Bounded contexts ustalone. ObjectType jako concept pierwszej klasy (ADR-009) zwalidowany.

→ **RBAC wdrażamy teraz**, w pełnym scope (bez Faza 1 cuts), przed implementacją:
- `feature-list-advanced.md` cross-user audit + Cmd+K agent
- `feature-exports.md` API tokens scoped
- Bulk operations z permission gating
- Multi-tenant management UI (Super Admin level — Marcin operator panel)

**Decyzja zmienia CLAUDE.md**: ADR-013 z Fazy 1 → **MVP-Alpha** w pełnym scope. Wszystkie wcześniejsze *„Faza 1 candidate"* w PRD-PIM-list-advanced i PRD-PIM-exports dotykające RBAC → MVP. Update sekcji *„Priorytety implementacyjne"*.

### 1.5 Anti-patterns do uniknięcia

> *„Dodajmy permissions check tylko w 1-2 najwrażliwszych endpointach i będziemy rozszerzać iteracyjnie."*

To prowadzi do niespójnego pokrycia — niektóre endpointy chronione, niektóre nie, brak audit consistency. **RBAC ma być systemowy lub żadny.**

> *„Frontend permissions wystarczą — schowamy przyciski."*

Frontend permissions to UX wygoda, **nigdy** security boundary. Każdy disabled button może być fired przez DevTools. Server-side guard jest jedynym prawdziwym zabezpieczeniem.

> *„Role hardcoded w kodzie wystarczą, custom roles dodamy później."*

Custom roles UI w Fazie 1 = refactor każdego komponentu user management. **Decyzja Marcina: custom roles w MVP od dnia 1.**

---

## 2. Proponowane role w Cortex PIM

### 2.1 Filozofia projektowania ról

- **Hierarchia uprawnień ma dwa poziomy:**
  - **Platform level** (cross-tenant): Super Admin — operator platformy Cortex.
  - **Tenant level**: 8 ról wewnątrz pojedynczego tenant (Owner, Admin, Catalog Mgr, ...).
- **Role mapowane na persony zwalidowane w epikach UI** — ale **bez hard-coding** mapping persona→role. Persony to UX targeting, role to security gates.
- **8 starter templates seedowanych per tenant** automatic przy onboardingu. **Templates są edytowalne** w MVP (decyzja Marcina: custom roles teraz).
- **Brak dziedziczenia ról** — flat structure. Każdy user ma N:M roles, permissions to suma uprawnień wszystkich przypisanych ról (union semantics).
- **Custom roles via UI w MVP** — klient w Settings → Roles tworzy własne role z matrix checkbox UI.

### 2.2 Lista ról

**Platform level (cross-tenant):**

| # | Rola | Persona | Scope |
|---|------|---------|-------|
| 0 | **Super Admin** | Marcin (operator platformy), Cortex DBA | Cross-tenant. Zarządzanie tenantami (CRUD), pricing tier change, billing override, audit cross-tenant, break-glass recovery. **Nie ma dostępu do danych produktowych** klientów (privacy boundary) — tylko metadata tenantów + system configuration. |

**Tenant level (8 ról seedowanych per tenant):**

| # | Rola | Persona | Główny zakres odpowiedzialności |
|---|------|---------|-----------------------------------|
| 1 | **Tenant Owner** | Tomasz / Marcin (właściciel firmy klienta) | Pełne uprawnienia w tenant + jedyny `manage_billing` + `delete_tenant`. Unique per tenant (max 1, transfer ownership = explicit flow). |
| 2 | **Administrator** | Piotr (IT) / co-owner | Wszystko oprócz `manage_billing` + `delete_tenant`. Pełne `manage_users`, `manage_roles`, `manage_integrations`, `manage_api_tokens_all`. |
| 3 | **Catalog Manager** | Kasia | Pełen CRUD Produktów/Kategorii/Multimediów + bulk operations + eksport + Approve workflow zmian. Brak Settings/Modelowanie/Integrations config. |
| 4 | **Content Editor (Marketing)** | Magda | View+Edit Produktów (description, SEO, tags, categories per-locale) + eksport własnych konfiguracji + Cmd+K bulk content actions. Brak Delete Produktów, brak Modelowanie, brak Integrations, brak `price` edit (field-level restriction). |
| 5 | **Information Architect (Modeler)** | Adam | Pełen Modelowanie (ObjectType + Attribute + AttributeGroup + Family CRUD) + Approve schema-ops z Cmd+K agenta. View Produkty/Kategorie (kontekst skutków). Brak Edit produktów. |
| 6 | **Integration Manager** | Piotr (zewnętrzny consultant / IT) | Manage Integrations + sync jobs CRUD + retry sync + View Produktów z atrybutami oznaczonymi `integration_visible` + CRUD wszystkich API tokens tenant. Brak Users/Billing/Modelowanie. |
| 7 | **Channel Manager** | sales operator multi-channel | Manage Publikacje (per-channel) + View Produkty + Edit channel-specific fields (description.shopify, price.allegro). Brak edit globalnych atrybutów (description.pl). |
| 8 | **Approver** | dedykowany reviewer / Tomasz w trybie reviewer | Approve/Reject pending changes (bulk operations awaiting approval, Cmd+K agent proposed changes, workflow state transitions). View wszystko. Brak własnych edit/delete operations. |
| 9 | **Viewer (Read-only / Audit)** | Tomasz w trybie audit, zewnętrzny auditor, accountant | Read-only across all modules. View audit log cross-user. Own API tokens (read-only scope only). |

### 2.3 Reguły szczególne

- **Super Admin scope ograniczony** — może widzieć metadata tenantów (nazwa, plan, billing status, user count, storage usage), ale **NIE** widzi danych domenowych klientów (Produkty, Atrybuty, integration secrets). To privacy boundary — Marcin jako operator nie jest *„właścicielem"* danych klientów.
- **Owner uniqueness**: dokładnie 1 user z rolą *„Tenant Owner"* per tenant. Transfer ownership = explicit flow (current Owner promotes innego usera, zostaje sam Administrator).
- **Last admin protection**: system blokuje deactivation/deletion ostatniego usera z rolą zawierającą `manage_users` + `manage_roles`. Recovery przez Super Admin break-glass action (logged z special flag `SUPER_ADMIN_RECOVERY`).
- **Self-modification block**: user nie może modyfikować swojej własnej roli ani permissions. Może modyfikować profile (name, password, MFA) zawsze.
- **Multi-role union**: user może mieć ≥2 roles równocześnie. Permissions = union. Np. *„Catalog Manager + Integration Manager"* = bulk CRUD produktów + manage Allegro/Shopify config.
- **Approver scope**: Approver akceptuje, nie tworzy. Klient z rolą Approver może być **dodatkowo** Catalog Manager (multi-role union) — wtedy może i tworzyć i zatwierdzać własne zmiany. Brak osobnego *„Approver only"* business constraint w MVP (klient sam decyduje przez role assignment).
- **Custom roles**: klient w Settings → Roles może tworzyć własne role. System nie pozwala usunąć 8 starter templates (`is_system=true`), ale klient może edytować ich permissions (Approver z dodatkową permission `bulk_operations.execute`).

---

## 3. Macierz uprawnień (RBAC matrix)

### 3.1 Permissions atomic — definicja

Permission jest tuplą `(scope, module, action)`:

- **Scope**: `platform` (cross-tenant, tylko Super Admin) lub `tenant` (wewnątrz pojedynczego tenant).
- **Module**: jeden z 12 modułów Cortex PIM (sekcja 3.2).
- **Action**: `view` / `add` / `edit` / `delete` / `approve` / `execute` / `manage` / `assign` / `rollback`.

**Macierz nie zawiera wymiarów contextual** (per-locale, per-channel, per-attribute, ownership) — te są **niezależnymi warstwami policy** dokładnie opisanymi w sekcjach 3.5 / 3.6 / 3.7. Macierz odpowiada na jedno pytanie: *„czy rola X w ogóle ma uprawnienie do akcji Y na module Z?"*. Pytanie *„czy w obrębie tego uprawnienia można zrobić wszystko, czy tylko ograniczony podzbiór?"* — to inna warstwa.

**Konwencja nazewnicza** (Marcin's feedback): gdzie semantyka *„own vs all"* ma znaczenie, są **dwa osobne wiersze** w macierzy (`Exports — View own history` vs `Exports — View all users' history`). Brak magic notation `(own)` w komórkach — zawsze czyste ✓/✗.

### 3.2 Macierz główna — moduł × rola

Legenda: **✓ = ma uprawnienie**, **✗ = nie ma uprawnienia**. Bez wyjątków, bez nawiasów.

> **⚠ Cross-tab relationship z §3.5 (Per-attribute & per-AttributeGroup permissions):**
>
> Permission z macierzy działa jako **broad gate** (np. *„Produkty — Edit ✓"*). W obrębie tej permission **może być per-attribute exceptions** — niektóre atrybuty restricted/view-only mimo broad edit (definiowane w zakładce *„Uprawnienia per atrybut"* w Settings → Roles).
>
> **Resolution order**: macierz first → per-attribute permissions drugorzędnie. Bez `products.view` w macierzy żadne per-attribute grants tej roli **nie działają** (endpoint guard odrzuca request przed dojściem do field-level check).
>
> **UI komunikacja**: macierz pokazuje badge `[N wyjątków ⚠]` per row gdy są exceptions + sticky info banner. Szczegóły w §3.5.

| Moduł / Akcja | Super Admin | Owner | Admin | Catalog Mgr | Marketing | Modeler | Integ. Mgr | Channel Mgr | Approver | Viewer |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Cross-tenant: List tenants** | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Cross-tenant: Manage tenant (CRUD)** | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Cross-tenant: Audit log** | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Cross-tenant: Break-glass recovery** | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Produkty — View** | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Produkty — Add** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Produkty — Edit** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ |
| **Produkty — Delete** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Produkty — Bulk operations** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Produkty — Approve pending changes** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ |
| **Kategorie — View** | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Kategorie — Add/Edit** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Kategorie — Delete** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Multimedia — View** | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Multimedia — Add/Edit own uploads** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Multimedia — Add/Edit any uploads** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Multimedia — Delete** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Modelowanie — View** | ✗ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✓ |
| **Modelowanie — Add/Edit Attribute** | ✗ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| **Modelowanie — Add/Edit AttributeGroup** | ✗ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| **Modelowanie — Add ObjectType** | ✗ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| **Modelowanie — Delete custom (`is_system=false`)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| **Modelowanie — Approve schema-ops (Cmd+K)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✓ | ✗ |
| **Auto-grant access do nowych ObjectTypes** | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✓ |
| **Publikacje — View** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ |
| **Publikacje — Publish/Unpublish** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✓ | ✗ | ✗ |
| **Imports — View own history** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✗ | ✓ | ✓ |
| **Imports — View all users' history** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ | ✗ | ✓ | ✓ |
| **Imports — Run new import** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ |
| **Exports — View own history** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ |
| **Exports — View all users' history** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ | ✓ |
| **Exports — Run new export** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✗ | ✗ |
| **Workflow — View** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ | ✓ |
| **Workflow — Approve/Reject transitions** | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ |
| **Workflow — Edit entity (any state)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Cmd+K agent — Use schema-ops** | ✗ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| **Cmd+K agent — Use bulk actions** | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Cmd+K agent — Approve agent's pending changes** | ✗ | ✓ | ✓ | ✓ | ✗ | ✓ | ✗ | ✗ | ✓ | ✗ |
| **Settings — Users (manage_users)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Settings — Roles (manage_roles, CRUD custom roles)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Settings — Tenant config (manage_tenant)** | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Settings — Billing (manage_billing)** | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Settings — Integrations (manage_integrations)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ |
| **Settings — Integration secrets (read tokens)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ |
| **API tokens own — CRUD** | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **API tokens all users — View/Revoke** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ |
| **Audit log — View own actions** | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Audit log — View cross-user (full tenant)** | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ | ✓ |
| **Tenant deletion** | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

**Co macierz NIE pokazuje (i dlaczego):**

- Field-level restrictions (Marketing nie może edytować `price`, Integration Manager nie widzi `cost_price`) → sekcja **3.5 Per-attribute restrictions**. Niezależna warstwa stosowana **po** permission grant.
- Locale-specific edits (Marketing-EN nie może edytować `description.pl`) → sekcja **3.6 Per-locale i per-channel scope**. Kontrolowane przez kolumny `user_roles.locale_scope` / `channel_scope`.
- Channel-specific edits (Channel Manager Allegro nie może edytować `description.shopify`) → tak samo, sekcja 3.6.
- Workflow-state gating (Catalog Manager edytuje w `draft` ale nie `published`) → sekcja **3.8 Workflow-state policy** (dodaję poniżej).
- `integration_visible` flag dla Integration Manager → sekcja 3.5.

### 3.3 Systemowe permissions specjalne (manage_*)

Niezależne od `(module, action)` matrix — dotyczą całego tenanta:

| Permission | Opis | Domyślnie przyznane |
|---|---|---|
| `manage_tenant` | Edycja ustawień tenant (nazwa, locales, channels), tenant deletion | Tylko Owner |
| `manage_users` | Invite, edit, deactivate users, assign roles | Owner, Admin |
| `manage_roles` | CRUD ról (custom roles), assign permissions to roles | Owner, Admin |
| `manage_billing` | Pricing tier change, payment method, invoices view | Tylko Owner |
| `manage_integrations` | Config Allegro/Shopify/BaseLinker/Magento/IdoSell connection credentials, webhooks | Owner, Admin, Integration Manager |
| `manage_api_tokens_all` | View/revoke API tokens innych userów (security audit) | Owner, Admin, Integration Manager |
| `manage_workflow_definitions` | CRUD workflow definitions (states, transitions, approval rules) | Owner, Admin |

### 3.4 Per-ObjectType permissions (z ADR-009)

Każdy zdefiniowany ObjectType (Product, Category, Asset, w przyszłości Customer/Supplier/PriceList) generuje 4 permissions atomic:

```
{object_type}.view
{object_type}.add
{object_type}.edit
{object_type}.delete
```

**Flaga `auto_grant_new_object_types: boolean` per rola** — gdy włączona (default dla Owner/Admin/Modeler), rola automatycznie zyskuje `view + edit` dla każdego nowo utworzonego ObjectType. Bez flagi — nowe ObjectType wymaga manualnego przypisania w role edit UI.

### 3.5 Per-attribute & per-AttributeGroup permissions (3-state positive grants)

**Filozofia:** Pozytywne 3-state permissions per (rola × atrybut) i (rola × AttributeGroup), z resolution order **attribute → group → role default**. Wzorzec analogiczny do Akeneo/Salesforce/SharePoint field-level security. Zastępuje wcześniejszy negative blacklist `attributes.restricted_roles` (PRD v1).

**Trzy stany per permission:**

| State | UI behavior | API response | Edit attempt |
|---|---|---|---|
| `restricted` | Hidden — atrybut NIE renderowany w formularzu (DOM-level removed) | Scrubbed z response (field-level serializer filter) | 403 Forbidden |
| `view` | Renderowany jako **read-only tekst** w formularzu (NIE disabled input — Gemini insight) | Visible w response | 403 Forbidden + tooltip *„Read-only dla Twojej roli"* |
| `edit` | Renderowany jako **input** (editable) | Visible w response | Standard validation → success or validation error |

**Schema (zastąpienie current `attributes.restricted_roles` z RBAC-P1-005):**

```sql
-- DROP: attributes.restricted_roles (migration script konwertuje istniejące entries na nowe tabele)
ALTER TABLE attributes DROP COLUMN restricted_roles;

-- KEEP: integration_visible (independent semantics — broad "field goes to API integrations Y/N", nie per-rola)
-- attributes.integration_visible BOOLEAN DEFAULT true → bez zmian

-- NEW: per-attribute permission per rola
CREATE TABLE role_attribute_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
    permission VARCHAR(16) NOT NULL CHECK (permission IN ('restricted', 'view', 'edit')),
    PRIMARY KEY (role_id, attribute_id)
);
CREATE INDEX idx_role_attribute_permissions_role ON role_attribute_permissions(role_id);

-- NEW: per-AttributeGroup permission per rola (bulk shortcut)
CREATE TABLE role_attribute_group_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    attribute_group_id UUID NOT NULL REFERENCES attribute_groups(id) ON DELETE CASCADE,
    permission VARCHAR(16) NOT NULL CHECK (permission IN ('restricted', 'view', 'edit')),
    PRIMARY KEY (role_id, attribute_group_id)
);
CREATE INDEX idx_role_attribute_group_permissions_role ON role_attribute_group_permissions(role_id);

-- NEW: default per rola (gdy żaden override nie istnieje)
ALTER TABLE roles ADD COLUMN default_attribute_permission VARCHAR(16) NOT NULL DEFAULT 'edit'
    CHECK (default_attribute_permission IN ('restricted', 'view', 'edit'));
```

**Resolution order w Voter (runtime):**

```
Per request: user X edytuje atrybut A produktu P

1. Sprawdź broad permission w macierzy (3.2):
   - User X w rolach z `products.edit` ✓? Jeśli NIE → 403, koniec
   - "Macierz first" — per-attribute grants NIE bypass broad gate (decyzja designerska A)

2. Sprawdź `role_attribute_permissions` (atrybut-level override):
   - Znaleziono entry (role_id × attribute_id)? → użyj tej wartości. STOP.

3. Sprawdź `role_attribute_group_permissions` (group-level override):
   - Atrybut A należy do grupy G. Znaleziono entry (role_id × G)? → użyj tej wartości. STOP.

4. Fallback: `roles.default_attribute_permission`
   - Wartość dla tej roli (np. 'edit' / 'view' / 'restricted'). STOP.
```

**Default value przy create roli (decyzja designerska C — inherit z macierzy):**

Gdy klient tworzy custom rolę w Settings → Roles + ustawia broad permissions w macierzy, system automatycznie ustawia `roles.default_attribute_permission`:

| Macierz permissions roli | `default_attribute_permission` |
|---|---|
| `products.edit` ✓ | `edit` |
| `products.view` ✓ (bez edit) | `view` |
| `products.*` żadnego | `restricted` |

Klient może później zmienić explicit w UI (Settings → Roles → Edit → Advanced section).

**Cross-cutting z macierzą uprawnień (decyzja designerska A — Macierz first):**

Per-attribute grants **NIE bypass** broad permission z macierzy. Mechanizm:

- Rola NIE ma `products.view` w macierzy → wszystkie per-attribute permissions tej roli są **inactive** (nieprzetwarzane, bo endpoint guard odrzuca request przed dojściem do field-level check)
- Klient w UI Settings → Roles → *„Uprawnienia per atrybut"* widzi warning gdy próbuje ustawić `view`/`edit` na atrybut bez broad `products.view` w macierzy:
  ```
  ⚠ Ta rola nie ma "Produkty: View" w macierzy uprawnień.
  Per-attribute permissions nie zadziałają dopóki nie włączysz broad permission.
  [Idź do macierzy]
  ```
- W macierzy uprawnień (3.2) UI pokazuje **badge z liczbą wyjątków** per wiersz + info banner gdy są exceptions

**Komunikacja UI: cross-tab badges + banners:**

W zakładce **Macierz uprawnień**:
- Per row (np. *„Produkty — Edit"*): badge `[3 wyjątki ⚠]` + tooltip z listą (*„price: View only, cost_price: Restricted, internal_sku: Restricted"*)
- Info banner na górze gdy są jakiekolwiek per-attribute exceptions: *„⚠ Ta rola ma N wyjątków per-attribute. Sprawdź szczegóły w zakładce 'Uprawnienia per atrybut'."*

W zakładce **Uprawnienia per atrybut**:
- Warning gdy macierz blokuje broad: *„Ta rola nie ma broad permission z macierzy. Per-attribute grants nieaktywne."*

**Bulk operations (decyzje designerskie A + B):**

- **Per-group toggle** (`Restricted for All` / `View for All` / `Edit for All`) — apply do **widocznych w current filter** atrybutów w grupie (decyzja A — Visible w current view)
- **Per-attribute toggle** — instant change (single attribute, brak preview)
- **Bulk change >5 atrybutów** — preview modal (decyzja B): *„Zmieniasz: 47 atrybutów z 'edit' na 'view', 3 atrybuty z 'edit' na 'restricted'. Continue?"* z buttons `[Anuluj] [Zastosuj]`

**`integration_visible` flag — pozostaje jako separate concern:**

Atrybut wciąż ma flagę `integration_visible BOOLEAN DEFAULT true`. To **niezależna semantyka** od per-rola permissions:

- `integration_visible: true` — atrybut może iść do API responses dla integrations (Shopify sync, Allegro feed)
- `integration_visible: false` — atrybut tylko dla wewnętrznego użytku (np. `cost_price`, `internal_sku`), NIE w sync responses niezależnie od roli

Field-level serializer filter (RBAC-P3-012) handles **oba**: per-rola permissions ORAZ `integration_visible`.

**Migration z PRD v1 do v2:**

Migration script konwertuje istniejące `attributes.restricted_roles` JSONB na entries w `role_attribute_permissions`:
```
FOR each attribute a WHERE restricted_roles IS NOT EMPTY:
    FOR each role_code IN a.restricted_roles:
        IF role.default_attribute_permission == 'edit':
            INSERT role_attribute_permissions (role.id, a.id, 'view')
            -- przekonwertuje "restricted dla edit" na "view-only"
        ELSE:
            INSERT role_attribute_permissions (role.id, a.id, 'restricted')

DROP COLUMN attributes.restricted_roles
```

**Przykład realnego use case (Accountant persona):**

Setup:
- Rola `accountant` z broad `products.view` ✓ + `products.edit` ✓ w macierzy
- `roles.default_attribute_permission = 'restricted'` (klient explicit ustawił, bo Accountant ma minimal scope)
- W zakładce *„Uprawnienia per atrybut"* klient kliknął `Edit for All` na grupie *„Pricing"* (zawiera price, cost_price, vat_rate)
- Resultat: `role_attribute_group_permissions(accountant, pricing_group, 'edit')` saved

Runtime:
- Accountant otwiera produkt → response API zawiera pricing fields (view + edit possible), reszta atrybutów scrubbed (restricted)
- Form pokazuje: 3 inputs dla pricing fields, reszta atrybutów nie istnieje w formularzu
- PATCH `{values: {price: 100}}` → 200 OK
- PATCH `{values: {description: "X"}}` → 403 z `field: "description", reason: "attribute_restricted"`

### 3.6 Per-locale i per-channel permissions

W tabeli `user_roles` lub `roles` można dodatkowo określić scope ograniczenia:

```sql
ALTER TABLE user_roles ADD COLUMN locale_scope JSONB DEFAULT '["*"]'::JSONB;
ALTER TABLE user_roles ADD COLUMN channel_scope JSONB DEFAULT '["*"]'::JSONB;
```

**Use case (Magda tłumacz EN):** user przypisany do roli Content Editor z `locale_scope: ["en"]` może edytować tylko `description.en`, `meta_description.en`, nie może modyfikować `description.pl`. Próba edycji innego locale → 403 Forbidden z message *„Brak uprawnień dla locale PL"*.

**Use case (Channel Manager Allegro only):** user z `channel_scope: ["allegro"]` może edytować `description.allegro`, `price.allegro`, nie może modyfikować `description.shopify`.

**Default scope `["*"]`** = brak ograniczenia (wszystkie locales/channels).

### 3.7 Ownership convention (own vs all)

Gdy w macierzy 3.2 widzisz **dwa osobne wiersze** typu *„X — View own"* i *„X — View all users'"* — to oznacza ownership-based authorization:

- **`own` semantyka**: user widzi/modyfikuje tylko zasoby gdzie `created_by = current_user_id` (lub odpowiedni FK do user'a stwórcy).
- **`all users'` semantyka**: user widzi/modyfikuje wszystkie zasoby w tenant niezależnie od stwórcy.

**Mechanika backend:**

```
PRE: GET /api/exports/sessions?scope=all
1. Check user has "exports.view_all_users" permission
2. If YES → query: WHERE tenant_id = :current_tenant
3. If NO → fallback do "exports.view_own_history" check
4. If YES → query: WHERE tenant_id = :current_tenant AND created_by = :current_user
5. If NO → 403 Forbidden
```

**Domyślne API behavior** (gdy klient nie przekazał `?scope=`):
- User z permission `view_all_users` → backend zwraca wszystkich (broadest scope).
- User tylko z `view_own_history` → backend zwraca tylko swoje.
- Frontend pokazuje toggle *„Wszyscy / Tylko ja"* gdy user ma obie permissions (User toggle = przekazanie `?scope=own|all` do API).

**Lista zasobów z ownership semantyką w MVP:**

| Zasób | `own` semantics | `all` semantics |
|---|---|---|
| **Exports — history** | created_by = current_user | wszystkie w tenant |
| **Imports — history** | created_by = current_user | wszystkie w tenant |
| **Multimedia — uploaded assets** | uploaded_by = current_user | wszystkie w tenant |
| **API tokens** | created_by = current_user | wszystkie w tenant |
| **Audit log** | actor_user_id = current_user | wszystkie w tenant |

**Co NIE ma ownership semantyki w MVP** (zawsze wszyscy widzą wszystko jeśli mają permission):
- Produkty / Kategorie / ObjectTypes / Attributes / AttributeGroups — to są **shared resources**, brak konceptu *„właściciela"*. Klient z permission `products.edit` może edit każdy produkt w tenant.
- Workflow tasks / Pending changes — visible dla wszystkich z permission (workflow jest kolaboracyjne).

**Rationale**: ownership semantyka jest dla **operational resources** (rzeczy które tworzy user, np. swój eksport) — nie dla **domain resources** (rzeczy które są wspólne dla teamu, np. produkty).

### 3.8 Workflow-state policy (orthogonal warstwa)

Gdy produkt jest w stanie `workflow_state = 'published'`, **nawet user z permission `products.edit` nie może go bezpośrednio edytować** — najpierw musi zostać transitioned do `draft`/`review` przez Approver.

To jest **dodatkowa warstwa policy NIEZALEŻNA od permission matrix**. Macierz mówi *„czy w ogóle może edit"*, workflow-state mówi *„czy może edit *teraz*, w obecnym state"*.

**Mechanika backend:**

```
PRE: PATCH /api/products/{id} (current state = 'published')
1. Check user has "products.edit" permission → ✓
2. Resource policy: check product.workflow_state
   - state = 'draft' → ALLOW edit
   - state = 'review' → ALLOW edit (jeśli user ma "workflow.edit_in_review")
   - state = 'published' → DENY z message "Produkt opublikowany.
     Najpierw zatwierdź przejście do 'draft' przez Approver."
3. Alternative: jeśli user ma "workflow.transition.unpublish" permission,
   może auto-transition published → draft + edit w jednym requestcie
   (audit log: special_flag = AUTO_UNPUBLISH_FOR_EDIT)
```

**Stany w MVP** (Symfony Workflow integration):
- `draft` — nieopublikowane, free editing dla `products.edit`
- `review` — czeka na approval, editable tylko dla `workflow.edit_in_review` (subset ról)
- `published` — opublikowane, **edit zablokowany** dla wszystkich, wymaga `workflow.transition.unpublish` przed edycją
- `archived` — usunięte z aktywnego katalogu, read-only

**Per-role workflow access** (orthogonal do permission matrix):

| Rola | Może edit w `draft` | Może edit w `review` | Może auto-unpublish | Może approve `review → published` |
|---|:---:|:---:|:---:|:---:|
| Owner | ✓ | ✓ | ✓ | ✓ |
| Admin | ✓ | ✓ | ✓ | ✓ |
| Catalog Manager | ✓ | ✓ | ✗ | ✓ |
| Marketing | ✓ | ✗ | ✗ | ✗ |
| Approver | ✗ | ✓ | ✓ | ✓ |
| Inne | ✗ | ✗ | ✗ | ✗ |

**Default behavior**: Marketing edytujący produkt w `published` → modal *„Produkt opublikowany. Skontaktuj się z Approver aby cofnąć publikację."* + button *„Request unpublish"* tworzy pending workflow request.

### 3.9 API token scopes

API tokens **nie dziedziczą permissions usera** — mają **własne scopes** definiowane przy tworzeniu tokena. Każdy scope = subset permission matrix.

**Pre-defined scope sets (6 templates):**

| Scope template | Permissions |
|---|---|
| `read-only` | View dla wszystkich modułów + Audit log view own |
| `read-write-catalog` | Pełne CRUD Produkty/Kategorie/Multimedia bez delete |
| `read-write-catalog-with-delete` | j.w. + delete |
| `integration-allegro` | View Produktów z `integration_visible=true` + Publikacje Allegro CRUD + Imports run |
| `integration-shopify` | View Produktów z `integration_visible=true` + Publikacje Shopify CRUD + Imports run + Exports run |
| `custom` | User wybiera permissions w UI checkbox grid |

**Rationale:** API token wykrada się łatwo (commit w git, log file, screen share). Pełne user permissions w tokenie = total compromise. Scopes = blast radius limitation.

**Token expiry:** configurable per token (30 dni / 90 dni / 1 rok / never). Default `90 dni`. Token z `never` wymaga `manage_api_tokens_all` permission + audit log entry z `LONG_LIVED_TOKEN_CREATED` flag.

---

## 4. Schemat logiczny — Backend

### 4.1 Diagram warstw walidacji uprawnień

```
Request HTTP
    │
    ▼
┌─────────────────────────────────────┐
│ 1. Authentication Layer             │
│    - Parse Bearer token (JWT lub    │
│      API token)                     │
│    - Validate signature + expiry    │
│    - Resolve user_id + tenant_id    │
│      (z token payload lub session)  │
│    - On failure → 401 Unauthorized  │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 2. Tenant Context Resolver          │
│    - Super Admin path: bypass       │
│      tenant scope, ale audit logged │
│      jako CROSS_TENANT_ACCESS       │
│    - Tenant user path: apply        │
│      TenantFilter (każde SELECT     │
│      ma WHERE tenant_id = :current) │
│    - RLS w Postgres (defence in     │
│      depth) — pełen scope MVP, nie  │
│      Faza 1                         │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 3. Permission Resolver              │
│    - Load user.roles (cached Redis  │
│      z TTL 5 min, event-driven      │
│      invalidation)                  │
│    - Compute effective permissions  │
│      = UNION(role.permissions for   │
│      each role in user.roles)       │
│    - Dla API token: zamiast user    │
│      permissions → token.scopes     │
│    - Apply locale_scope +           │
│      channel_scope restrictions    │
│      z user_roles                   │
│    - Wynik: PermissionSet object    │
│      attached to request context    │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 4. Endpoint Guard (declarative)     │
│    - Każdy endpoint deklaruje       │
│      required permission:           │
│      @RequiresPermission(           │
│        module="products",           │
│        action="edit"                │
│      )                              │
│    - Guard sprawdza:                │
│      permission ∈ user.permissions  │
│    - On failure → 403 Forbidden     │
│      (RFC 7807 Problem Details)     │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 5. Resource Policy (fine-grained)   │
│    - Per-attribute restriction:     │
│      attribute.restricted_roles     │
│      check                          │
│    - Per-locale: user.locale_scope  │
│      vs requested locale            │
│    - Per-channel: user.channel_     │
│      scope vs requested channel     │
│    - Workflow state gating:         │
│      product.workflow_state         │
│      vs user role permissions       │
│    - Ownership: user owns target    │
│      resource (np. own_uploads,     │
│      own_exports, own_api_tokens)   │
│    - On failure → 403 z context     │
│      message ("Brak uprawnień       │
│      dla locale EN", etc.)          │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 6. Data Access Layer Filter         │
│    - Row-level filtering w          │
│      Doctrine: queries (GET lists)  │
│      mają auto-applied WHERE        │
│      clauses dla scope ograniczeń   │
│    - Np. Magda z locale_scope=      │
│      ["en"] dostaje listę produktów │
│      ale tylko z atrybutami         │
│      `*.en` w response              │
│    - Defence-in-depth: ukryte       │
│      dane nigdy nie ładują się do   │
│      pamięci aplikacji              │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 7. Field-level Serialization Filter │
│    - Przed wysłaniem response JSON, │
│      serializer skanuje pola pod    │
│      kątem field-level permissions  │
│    - Integration token (Shopify     │
│      access_token) → usuń z         │
│      response gdy user nie ma       │
│      `manage_integrations`          │
│    - `cost_price` na produkcie →    │
│      usuń gdy user.role IN          │
│      attribute.restricted_roles    │
│    - `description.pl` →             │
│      usuń gdy user.locale_scope     │
│      nie zawiera "pl"               │
│    - Dynamic field exclusion z      │
│      Symfony serializer groups +    │
│      runtime decision context       │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 8. Business Logic Layer             │
│    - Actual handler executes        │
│    - Audit Log Listener (Doctrine   │
│      event subscriber) loguje:      │
│      - user_id + tenant_id +        │
│        is_super_admin flag          │
│      - resource + action            │
│      - permission_check_result      │
│      - old_value / new_value        │
│        (field-level diff)           │
│      - timestamp + IP + user_agent  │
│      - cross_tenant_access flag     │
│        (gdy Super Admin)            │
└─────────────────────────────────────┘
    │
    ▼
Response (200/201/204) lub Error (401/403/404/409)
```

### 4.2 Reguły kluczowe walidacji

1. **Permission check zawsze server-side**. Frontend show/hide to UX wygoda, **nigdy** security boundary.
2. **Tenant boundary first, permission second.** Brak permission check nigdy nie ujawnia danych z innego tenant.
3. **Super Admin bypass z audit trail** — każdy cross-tenant access logged z `CROSS_TENANT_ACCESS` flag, dostęp do listy tenantów wymaga MFA verification w sesji (re-auth co 1h dla super-sensitive operations jak `delete_tenant`).
4. **API token check zastępuje user permission check**, nie sumuje się z nim.
5. **Cmd+K agent działa as-the-user**, nie jako superuser. Agent tool calls przechodzą przez ten sam permission stack co manual requests.
6. **Field-level filtering w serializerze** (Gemini insight) — kluczowe dla integration secrets. Bez tego pełen token Shopify wycieka do response Marketing user'a.
7. **Workflow-state gating** — Catalog Manager może edit produkt w `draft` lub `review` state, **nie** w `published` (wymaga `Workflow.transition.published_to_draft` permission). Implementacja przez Symfony Workflow + Voter integration.
8. **Permission cache invalidation**. Cache Redis user.permissions z TTL 5 min, plus event-driven:
   - User role change
   - Role permission change
   - User deactivation
   - API token revoke
   - Custom role edit (Mercure SSE invalidates all sessions z affected role)
9. **Audit denial logging** — denial 403 logged dla:
   - `manage_*` permissions (privilege escalation attempts)
   - Destructive actions (delete, bulk delete)
   - Cross-tenant access attempts
   - Field-level violations (Marketing trying to edit `price`)
10. **Recovery / break-glass**: Super Admin `rescue-admin` action — assignuje rolę Owner do usera bez przechodzenia przez tenant-level permission stack. Każde użycie zapisane w `audit_logs` z special flag `SUPER_ADMIN_RECOVERY`.

### 4.3 Encje + tabele (data model RBAC)

```sql
-- Cross-tenant — Super Admin tylko
super_admins
    id (UUID, PK)
    email (UNIQUE globally)
    password_hash
    mfa_enabled (boolean — obligatoryjne dla Super Admin)
    mfa_secret (encrypted)
    last_login_at
    deactivated_at (nullable)
    created_at

-- Tenant level
users
    id (UUID, PK)
    tenant_id (UUID, FK — w MVP single tenant per user, schema gotowy na N:M w future)
    email (UNIQUE per tenant)
    password_hash
    name
    avatar_url
    locale  -- UI language preference
    mfa_enabled (boolean)
    mfa_method (enum: 'email_totp', 'app_totp', null)
    mfa_secret (encrypted)
    sso_provider (enum: 'google', 'microsoft', 'saml', null)
    sso_subject_id (string, nullable — for SSO matching)
    last_login_at
    is_active (boolean)
    created_at
    deactivated_at

roles
    id (UUID, PK)
    tenant_id (UUID, FK)
    name (string)
    description (text)
    is_system (boolean — built-in templates, klient może edytować permissions ale nie usunąć)
    is_unique (boolean — Owner has TRUE)
    auto_grant_new_object_types (boolean)
    created_at
    updated_at

permissions
    id (UUID, PK)
    code (string, np. "products.edit", "settings.users.manage")
    module (string)
    action (string)
    is_system (boolean)
    description (text)

role_permissions
    role_id (FK)
    permission_id (FK)
    PRIMARY KEY (role_id, permission_id)

user_roles
    user_id (FK)
    role_id (FK)
    locale_scope (JSONB array — domyślnie ["*"])
    channel_scope (JSONB array — domyślnie ["*"])
    assigned_by (user_id, FK)
    assigned_at
    PRIMARY KEY (user_id, role_id)

api_tokens
    id (UUID, PK)
    tenant_id (FK)
    user_id (FK — kto stworzył)
    name (string)
    token_hash (string — hashed, never store plain)
    scopes (JSONB array of permission codes)
    locale_scope (JSONB — token też ma scope restrictions)
    channel_scope (JSONB)
    expires_at (nullable)
    last_used_at
    last_used_ip
    created_at
    revoked_at (nullable)
    revoked_by (user_id, FK, nullable)

-- Per-attribute restrictions (z Cortex PIM Modelowanie)
attributes  (existing, dodać kolumny)
    + integration_visible (boolean, default true)
    + restricted_roles (JSONB array of role codes, default '[]')

-- Audit (existing AuditBundle, extended)
audit_logs
    id, tenant_id (nullable dla cross-tenant Super Admin actions), user_id, super_admin_id (nullable),
    action, resource_type, resource_id,
    old_value (JSONB — field-level diff), new_value (JSONB),
    permission_check_result (granted/denied/n_a/super_admin_bypass),
    cross_tenant_access (boolean),
    special_flags (JSONB — SUPER_ADMIN_RECOVERY, LONG_LIVED_TOKEN_CREATED, BREAK_GLASS, etc.),
    timestamp, ip_address, user_agent

invitations
    id (UUID, PK)
    tenant_id (FK)
    invited_by (user_id, FK)
    email (string)
    role_ids (JSONB array)
    locale_scope (JSONB — pre-assigned scope)
    channel_scope (JSONB)
    token (hashed magic link token)
    expires_at (default 7 days)
    accepted_at (nullable)
    declined_at (nullable)

-- Multi-tenant membership (rekomendowane: zero constraint już w MVP)
user_tenant_memberships
    user_id (FK)
    tenant_id (FK)
    status (enum: 'active', 'invited', 'deactivated')
    invited_at
    activated_at
    PRIMARY KEY (user_id, tenant_id)

-- SSO / SAML config (MVP)
sso_providers
    id (UUID, PK)
    tenant_id (FK)
    provider_type (enum: 'saml', 'oauth_google_workspace', 'oauth_microsoft_365')
    config (JSONB — encrypted, contains IDp metadata URL, certificate, etc.)
    is_active (boolean)
    enforce_for_users (boolean — gdy true, użytkownicy muszą logować przez SSO, password disabled)
```

### 4.4 Multi-tenant + RBAC interakcja

- **Każde zapytanie ma tenant scope first.** TenantFilter na poziomie Doctrine ORM + Postgres RLS. Permission check sprawdza permissions **w obrębie tenant**.
- **Super Admin bypasses tenant scope** ale każdy access logged z special flag. Read-only dla danych domenowych klientów (privacy).
- **Roles są per-tenant** (`roles.tenant_id NOT NULL`). 8 starter templates seedowanych per tenant przy onboardingu.
- **Permissions są globalne** (`permissions` table bez `tenant_id`) — pula uprawnień jest immutable system seed (~50 atomic permissions).
- **Multi-tenant membership N:M od dnia 1** — `user_tenant_memberships` zero constraint, klient widzi tenant-switch dropdown w top bar gdy ma ≥2 active memberships.

### 4.5 SSO / SAML (MVP scope)

- **Email + password + opcjonalna MFA** = baseline path.
- **Magic link invitation** = primary onboarding flow.
- **Google Workspace OAuth** = self-serve sign-in dla użytkowników z Google account (lower friction).
- **Microsoft 365 OAuth** = j.w. dla użytkowników z Microsoft account.
- **SAML 2.0 SSO** = enterprise feature, klient konfiguruje IDp w Settings → SSO. Gdy `enforce_for_users=true`, password login disabled dla wszystkich users tenant.

---

## 5. Schemat logiczny — Frontend

### 5.1 Diagram warstw walidacji UI

```
Login → Token received (or SSO callback)
    │
    ▼
┌─────────────────────────────────────┐
│ 1. Session Bootstrap                │
│    - GET /api/me                    │
│    - Response: {                    │
│        user: {id, email, name,      │
│          mfa_enabled, sso_provider, │
│          ...},                      │
│        tenant: {id, name, ...},     │
│        memberships: [{tenant_id,    │
│          tenant_name, role_names    │
│          }] (multi-tenant),         │
│        roles: [{name, locale_scope, │
│          channel_scope, ...}],      │
│        permissions: [               │
│          "products.view",           │
│          "products.edit",           │
│          ...                        │
│        ] flattened set,             │
│        attribute_restrictions: {    │
│          "price": {                 │
│            can_view: true,          │
│            can_edit: false,         │
│            reason: "restricted_     │
│              roles"                 │
│          }                          │
│        },                           │
│        features: {                  │
│          can_switch_tenant: bool,   │
│          can_export_full: bool      │
│        }                            │
│      }                              │
│    - Persisted w client store       │
│    - Token w httpOnly secure cookie │
│      (NIE localStorage)             │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 2. Router Guards (route-level)      │
│    - Każda route ma metadata:       │
│      requires_permission:           │
│        ["products.view"]            │
│    - Router middleware sprawdza     │
│      przed renderem komponentu      │
│    - On fail → redirect do          │
│      /403 page (z context message)  │
│      + ciche powiadomienie toast    │
│    - Bypass dla public routes       │
│      (/login, /403, /profile,       │
│      /accept-invitation, /reset-    │
│      password)                      │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 3. Layout-level visibility          │
│    - Sidebar nav: filtruj items     │
│      wg. permissions                │
│      (item nie rendered w DOM, nie  │
│      tylko display:none — Gemini    │
│      insight)                       │
│    - Top bar tenant-switch dropdown │
│      tylko gdy memberships.length   │
│      ≥ 2                            │
│    - Notifications bell: filter     │
│      events wg. relevance per role  │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 4. Component-level guards           │
│    - <PermissionGate                │
│        permission="products.edit"   │
│        fallback={<Disabled />}>     │
│        <EditButton />               │
│      </PermissionGate>              │
│    - lub useCanI() hook:            │
│      const canEdit = useCanI(       │
│        "products", "edit"           │
│      )                              │
│    - Komponenty wewnętrzne          │
│      decydują czy ukryć,            │
│      czy pokazać disabled z         │
│      tooltipem "Brak uprawnień"     │
│    - Pole disabled vs read-only     │
│      vs not-rendered — wybór per    │
│      kontekst (sekcja 5.2)          │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 5. Field-level form rendering       │
│    (Gemini insight)                 │
│    - Dynamic form generator         │
│      sprawdza attribute_            │
│      restrictions per pole:         │
│      - can_edit=true → input        │
│      - can_view + !can_edit → tekst │
│        (NIE disabled input — pure   │
│        text rendering)              │
│      - !can_view → pole nie         │
│        renderowane wcale            │
│    - Pole `price` dla Marketing:    │
│      renderuje się jako tekst       │
│      "1234.56 PLN" w detail view    │
│      zamiast jako Input ze stale    │
│      cursor                         │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 6. Action handlers (defensive)      │
│    - Każdy button click sprawdza    │
│      ponownie can-i                 │
│    - API request leci do serwera    │
│      → server-side validation       │
│      jest source of truth           │
└─────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────┐
│ 7. Global HTTP Interceptor          │
│    (Gemini insight)                 │
│    - Każda response 403 z API       │
│      przechwycona przez interceptor │
│    - Akcje:                         │
│      a) Toast error "Brak           │
│         uprawnień — Twoje           │
│         uprawnienia mogły się       │
│         zmienić"                    │
│      b) Rollback state              │
│         optimistic UI changes       │
│         do poprzedniego stanu       │
│      c) Trigger refresh: GET        │
│         /api/me i update store      │
│      d) Jeśli to była navigacja —   │
│         redirect do /403            │
│    - Każda response 401 →           │
│      logout + redirect /login       │
└─────────────────────────────────────┘
```

### 5.2 Reguły kluczowe UI

1. **Frontend permissions to UX, nie security.** Server-side guard jest jedynym prawdziwym zabezpieczeniem.
2. **Show vs disable vs hide vs read-only-text — wybór per akcja:**
   - **Hide** dla feature-gating (cała zakładka nie istnieje w sidebar gdy brak permission). Component physically nie rendered w DOM (Gemini insight — nie tylko `display:none`).
   - **Disabled z tooltipem** dla per-akcja gating (button Delete widoczny ale disabled, tooltip *„Brak uprawnień do usuwania produktów"*).
   - **Read-only text rendering** dla field-level restrictions (Gemini insight — pole `cost_price` dla Marketing renderuje się jako tekst, nie disabled input). Zachowuje informacyjność bez kuszenia stale cursor.
   - **Show z communicate** dla collaborative scenarios (button Approve dla user który nie ma approval permission → toast *„Skontaktuj się z Approver aby zatwierdzić"*).
3. **Permission cache w client store.** Po bootstrap (`/api/me`) permissions cached w memory. Refresh w 4 trigger scenarios:
   - Server zwrócił 403 (permissions stale)
   - User explicitly otworzył Settings → Profile (force refresh)
   - SSE event `user.permissions.changed` przez Mercure (real-time invalidation)
   - Tenant switch (full re-bootstrap)
4. **Multi-tenant context UI:**
   - Top bar dropdown z aktualnym tenant (gdy memberships.length ≥ 2).
   - Tenant switch = full page reload (re-bootstrap, nowy permission set).
   - Brak cross-tenant data leakage w stanie client store (clear store przy switch).
5. **Cmd+K agent + permissions integration:**
   - Cmd+K palette filtruje suggested commands wg. user permissions.
   - Agent tool call validation: gdy LLM zwróci tool call requiring permission user nie ma → palette pokazuje error *„Nie masz uprawnień. Skontaktuj się z administratorem."* zamiast triggering API request.
6. **API token UI w Settings:**
   - Lista własnych tokenów (każdy user) — name, scope, expires, last_used.
   - Lista wszystkich tokenów tenant (tylko Admin/Integration Manager z `manage_api_tokens_all`).
   - Create token wizard: name + scope template dropdown (6 templates) + custom scope checkbox grid + optional expiry (30/90/365/never) + locale/channel scope → pokazuje token raz (modal *„Skopiuj teraz, nie zobaczysz go ponownie"*).
   - Revoke token: confirm modal z target's name + last used info.
7. **Role builder UI (Settings → Roles, MVP scope):**
   - Lista ról: 8 system templates (read-only z indicator badge `System`) + custom roles (klient stworzył).
   - *„Create custom role"* button → matrix UI (checkbox grid moduł × akcja) + name/description + auto_grant_new_object_types toggle + save.
   - Edit role: matrix UI w edit mode, system templates pozwalają edytować permissions ale nie name/code.
   - Delete role: only custom roles, system templates show *„System role — cannot delete"*.
   - Per-attribute restrictions edit: secondary tab w role editor — lista wszystkich atrybutów z toggle *„Restricted for this role"* + `integration_visible` flag per attribute.
8. **MFA setup flow:**
   - Profile → Security → Enable MFA.
   - User wybiera method: `email TOTP` lub `Authenticator app`.
   - Setup wizard: pokazuje secret + QR + verify code → enabled.
   - 10 recovery codes one-time use generated and shown (GitHub style).
   - MFA enforcement policy (per-role): Owner/Admin **wymagane**, inne role opcjonalne. Konfigurable w Settings → Security.
9. **Invite user flow (magic link):**
   - Settings → Users → *„Invite user"* button.
   - Modal: email + name + role(s) (multi-select z listy templates + custom) + locale_scope + channel_scope → send magic link.
   - Invited user listed w Users z status `invited` (different badge color).
   - Magic link email: *„Welcome to Cortex PIM. Click to set up your account."* → linkuje do `/accept-invitation?token=X`.
   - Klik → user ustawia password + opcjonalnie MFA → zalogowany do tenant.
10. **Last admin protection UI:**
    - Próba deactivation/role-change ostatniego user'a z `manage_users` + `manage_roles` → modal block *„Nie można usunąć ostatniego administratora. Najpierw przypisz rolę Administrator innemu użytkownikowi."*.
    - Próba `Delete Tenant` przez Owner → confirm modal z typing tenant name + checkbox *„Rozumiem, że ta operacja jest nieodwracalna"*.

### 5.3 Mockup permission matrix UI — custom role builder

```
┌─ Create Custom Role ────────────────────────────────────────────────┐
│                                                                       │
│ Name:        [Junior Catalog Editor                       ]          │
│ Code:        [junior_catalog_editor                       ] (auto)    │
│ Description: [Edit produktów bez delete + bez approve]                │
│                                                                       │
│ Auto-grant view+edit dla nowych ObjectTypes:  ☐                       │
│                                                                       │
│ ┌─ Permissions Matrix ─────────────────────────────────────────────┐ │
│ │                                                                    │ │
│ │ [Moduł]                View   Add   Edit   Delete  Approve  Exec │ │
│ │ ───────────────────────────────────────────────────────────────  │ │
│ │ Produkty                ☑     ☑     ☑      ☐       ☐       N/A   │ │
│ │ Kategorie               ☑     ☑     ☑      ☐       N/A     N/A   │ │
│ │ Multimedia              ☑     ☑     ☑      ☐       N/A     N/A   │ │
│ │ Modelowanie             ☑     ☐     ☐      ☐       ☐       N/A   │ │
│ │ Publikacje              ☑     N/A   N/A    N/A     N/A     ☐     │ │
│ │ Imports                 ☑     N/A   N/A    N/A     N/A     ☑(own)│ │
│ │ Exports                 ☑(own)N/A   N/A    ☑(own)  N/A     ☑     │ │
│ │ Workflow                ☑     N/A   N/A    N/A     ☐       N/A   │ │
│ │ Cmd+K (schema)          ☐     N/A   N/A    N/A     N/A     ☐     │ │
│ │ Cmd+K (bulk)            ☑     N/A   N/A    N/A     N/A     ☑     │ │
│ │ Settings — Users        ☐     ☐     ☐      ☐       N/A     N/A   │ │
│ │ Settings — Roles        ☐     ☐     ☐      ☐       N/A     N/A   │ │
│ │ Settings — Tenant       ☐     ☐     ☐      ☐       N/A     N/A   │ │
│ │ Settings — Integrations ☐     ☐     ☐      ☐       N/A     N/A   │ │
│ │ Audit log (cross-user)  ☐     N/A   N/A    N/A     N/A     N/A   │ │
│ │                                                                    │ │
│ └────────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│ ┌─ Field-level Restrictions ──────────────────────────────────────┐ │
│ │                                                                    │ │
│ │ Lista atrybutów restricted dla tej roli (read-only zamiast edit): │ │
│ │  ☑ price.*           (rola nie może edytować ceny)                 │ │
│ │  ☑ cost_price        (rola nie widzi cost_price wcale)             │ │
│ │  ☐ description.*                                                   │ │
│ │  ☐ ean                                                             │ │
│ │  ... (search w 200+ atrybutach)                                    │ │
│ │                                                                    │ │
│ └────────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│ ┌─ Locale & Channel Scope (opcjonalne) ───────────────────────────┐ │
│ │  Locales: ☑ * (wszystkie)  □ pl  □ en  □ de                       │ │
│ │  Channels: ☑ * (wszystkie)  □ shopify  □ baselinker  □ allegro    │ │
│ └────────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│                                          [Anuluj]   [Utwórz rolę]    │
└───────────────────────────────────────────────────────────────────────┘
```

### 5.4 Mockup users list — Settings → Users

```
┌─ Settings → Users ────────────────────────────────────────────────────┐
│                                                                         │
│ [+ Invite user]   [Filter: ▼ All roles]   [Search...]                 │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ User                Role(s)              Status      Last Login   ⋮ │ │
│ ├─────────────────────────────────────────────────────────────────────┤ │
│ │ 🟢 Tomasz Kowalski  Tenant Owner         Active      2 min ago   ⋮ │ │
│ │ 🟢 Kasia Nowak      Catalog Manager      Active      1 hour ago  ⋮ │ │
│ │ 🟢 Magda Wiśniewska Marketing            Active      3 hours ago ⋮ │ │
│ │                     (locale: pl, en)                                │ │
│ │ 🟢 Adam Lewandowski Modeler              Active      yesterday   ⋮ │ │
│ │ 🟡 Piotr Adamski    Integration Manager  Invited     N/A          ⋮ │ │
│ │                                          (link 7d valid)            │ │
│ │ 🔴 Anna Kwiatkowska Approver             Deactivated 2 weeks ago ⋮ │ │
│ │                                                                     │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Showing 6 of 6 users                                                   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. Krytyczne ostrzeżenia i decyzje dla analityka

### 6.1 Co świadomie zawiera MVP (vs v1 dokumentu który miał Faza 1 cuts)

- ✅ **Super Admin cross-tenant** — operator panel dla Marcina (lista tenantów, billing override, audit cross-tenant, break-glass recovery).
- ✅ **Custom roles UI w MVP** — klient tworzy własne role w Settings → Roles z matrix UI.
- ✅ **Per-attribute permissions** — `attributes.integration_visible` + `attributes.restricted_roles` flags.
- ✅ **Per-locale i per-channel scope w `user_roles`** — user może być Marketing dla locale EN only.
- ✅ **Workflow-state-based permissions** — Catalog Manager edytuje w `draft/review`, nie `published`.
- ✅ **Resource ownership** — *„own"* vs *„all"* dla sync jobs, exports, API tokens.
- ✅ **Approver jako osobna rola** — separate workflow agent approvals.
- ✅ **MFA enforcement policy per-role** — Owner/Admin wymagane, inne opcjonalne.
- ✅ **Configurable API token TTL** — 30/90/365/never z audit dla long-lived.
- ✅ **Cross-user audit panel** — Approver/Viewer widzi cross-user audit log.
- ✅ **Multi-tenant membership N:M od dnia 1** — `user_tenant_memberships` zero constraint + tenant-switch dropdown.
- ✅ **Magic link invitation jako primary** — manual password jako edge case opcja.
- ✅ **Field-level filtering w serializerze** — integration secrets scrubbing.
- ✅ **Field-level form rendering** — text vs input vs hidden per attribute restriction.
- ✅ **Global HTTP interceptor 403** — toast + rollback optimistic UI state.
- ✅ **SSO/SAML w MVP** — Google Workspace + Microsoft 365 + SAML 2.0.

### 6.2 Świadome ograniczenia (poza MVP, ale możliwe iteracje)

- **Field-level permissions per ObjectType variant** — np. *„Marketing może edit description.pl ale nie description.en"* jest supportowane przez `locale_scope`, ale **complex nested patterns** (np. *„edit only attributes in AttributeGroup 'Marketing'"*) → potencjalna iteracja w przyszłości.
- **Conditional permissions** (*„może delete tylko produkty starsze niż 30 dni"*) — nie w MVP. Pattern z DeepSeek (row-level), zaakceptowane jako post-MVP iteration.
- **Permission delegation** (*„Magda może w moim imieniu zaakceptować zmiany przez weekend"*) — nie w MVP.
- **Time-based access** (*„token aktywny tylko 9:00-17:00 PL time"*) — nie w MVP.
- **IP-based restrictions** (*„API token tylko z office IP"*) — nie w MVP.

### 6.3 Pytania otwarte do walidacji z Marcinem

- [ ] **Super Admin login flow** — osobny URL (`admin.cortex.pl`) czy ten sam `pim.cortex.pl` z role detection? Default rekomendacja: **osobny subdomain** dla isolation + security.
- [ ] **Super Admin MFA enforcement** — obligatoryjne zawsze (nie opcjonalne nawet dla testowych accountów)? **Default: tak, hard requirement.**
- [ ] **MFA enforcement timeline** — wymagać MFA przy first login (force setup) lub allow opt-in pierwsze 30 dni? **Default: opt-in 30 dni, potem mandatory dla Owner/Admin, mandatory dla Super Admin od dnia 1.**
- [ ] **API token expiry default** — 90 dni czy 30 dni? **Default: 90 dni (balance UX vs security).**
- [ ] **SSO enforce_for_users default** — tenant administrator decyduje per-tenant, czy default `false` dla wszystkich tenantów new?
- [ ] **Workflow-state permissions** — czy każde edycja produktu w `published` state wymaga `Workflow.transition.unpublish_first`, czy może być direct edit z auto-transition do `draft`? **Default: auto-transition do draft + audit log, klient potwierdza w modal.**
- [ ] **`integration_visible` default dla nowych atrybutów** — `true` (visible by default, klient explicit hides sensitive) czy `false` (hidden by default, klient explicit shows)? **Default: `true` — typowy atrybut idzie do sync, sensitive są wyjątkiem.**
- [ ] **Tenant scope dla starter templates** — czy każdy tenant ma own copy 8 templates (klient może modyfikować) czy templates są globalne immutable a klient kopiuje przed edit? **Default: each tenant has own copy seedowane przy onboardingu, klient może edit/delete custom copies ale nie underlying system templates definition.**

### 6.4 Aktualizacja innych PRD-ów (zadanie po RBAC)

Po implementacji RBAC trzeba zaktualizować:

- `PRD-PIM-list-advanced.md` — sekcje:
  - § 4.2 (audit) — z self-audit only → **cross-user audit dla Viewer/Approver/Admin**.
  - § 8 (bulk actions) — wszystkie akcje z per-action permissions.
  - § 9 (Cmd+K) — agent działa as-the-user, schema-ops wymaga rola Modeler lub Owner/Admin, bulk wymaga Catalog Manager / Marketing.
  - § 11 (limits) — Cmd+K rate limits per-user (50/h).
- `PRD-PIM-exports.md` — sekcje:
  - § 8.5 (audit) — z self-audit only → **cross-user audit panel dla Viewer/Approver/Admin**.
  - § 9.3 (API endpoints) — `manage_api_tokens_all` permission dla cross-user token management.
  - § 11.6 (security) — per-tier scope restrictions z faktycznymi role names.
  - § 12 (pricing) — gating per tier z RBAC backed.
- `CLAUDE.md` — sekcja *„Priorytety implementacyjne"*:
  - ADR-013 z Fazy 1 → **MVP-Alpha** w pełnym scope.
  - Dodać epik *„0.X Identity & RBAC"* przed kontynuacją 0.6/0.7/0.10/0.11.

---

## 7. Estymacja implementacji RBAC w pełnym scope MVP

| Obszar | Zakres | Estymacja |
|---|---|---|
| **Backend — encje + migrations** | super_admins, users, roles, permissions, role_permissions, user_roles, api_tokens, attributes (delta), audit_logs (delta), invitations, user_tenant_memberships, sso_providers | 20-30h |
| **Backend — auth + tenant context** | JWT auth, API token auth, MFA (email TOTP + Authenticator), magic link invitations, SSO (Google + Microsoft + SAML), tenant context resolver, Postgres RLS aktywacja | 50-70h |
| **Backend — permission engine** | Permission resolver, endpoint guards (declarative), resource policies (per-attribute, per-locale, per-channel, ownership, workflow-state), Cmd+K agent integration | 40-50h |
| **Backend — field-level filtering** | Serializer groups dynamic, integration secret scrubbing, sensitive field handling per role | 12-18h |
| **Backend — audit log extension** | Permission check results in audit_logs, cross-tenant flags, special_flags handling | 8-12h |
| **Backend — Super Admin operator panel** | Cross-tenant endpoints, tenant CRUD, audit cross-tenant, break-glass recovery, billing override | 20-30h |
| **Backend — refactor existing endpoints** | Dodać `@RequiresPermission` do ~60 existing endpoints (post 60% MVP build) | 20-30h |
| **Frontend — bootstrap + session** | `/api/me` integration, store layout, token httpOnly cookie, MFA flow UI | 15-20h |
| **Frontend — guards + interceptor** | Route guards, layout-level visibility, component-level `<PermissionGate>` + `useCanI()` hook, HTTP interceptor 403 + rollback state | 20-25h |
| **Frontend — Settings UI** | Users list + invite flow + edit user, Roles list + custom role builder + per-attribute restrictions, API tokens CRUD, SSO config | 40-50h |
| **Frontend — field-level form rendering** | Dynamic form generator integration z attribute_restrictions, text-vs-input rendering | 15-20h |
| **Frontend — Super Admin operator UI** | Cross-tenant tenant list + tenant detail + tenant CRUD, audit cross-tenant view, break-glass recovery UI | 15-20h |
| **Frontend — refactor existing components** | Sidebar + toolbars + action menus + bulk operations w istniejących komponentach dodać `<PermissionGate>` | 15-20h |
| **Testing — integration tests** | RBAC matrix coverage, cross-tenant leakage prevention, field-level scrubbing, workflow integration | 25-30h |
| **Testing — E2E Playwright** | Login flows (password / SSO / MFA), invite flow, role assignment, permission enforcement | 15-20h |
| **TOTAL** | | **~330-445h** |

**Realistyczna estymacja w izolacji** (Marcin solo dev tempo): **8-12 tygodni**.

Po implementacji RBAC kontynuacja `feature-list-advanced.md` + `feature-exports.md` (z aktualizacjami sekcji RBAC) + nowych feature'ów z permission gating od dnia 1.

---

## 8. Powiązane dokumenty

- **Source of truth detaliczne:** `Project Plan/UI/feature-list-advanced.md` (bulk operations + audit + Cmd+K), `Project Plan/UI/feature-exports.md` (API tokens + audit).
- **Sibling feature-PRD:** `PRD-PIM-list-advanced.md`, `PRD-PIM-exports.md` — wymagają update sekcji audit/tokens po implementacji RBAC.
- **Master product PRD:** `Zrodla/PRD/PRD-PIM.md`.
- **Architektura:** `Project Plan/01-architektura-pim.md` — ADR-006/009/010/011 + ADR-013 (RBAC) do dopisania.
- **Plan projektu:** `Project Plan/02-plan-projektu-pim.md` — dodać epik *„0.X Identity & RBAC"* przed 0.6.
- **Funkcjonalności MVP:** `Project Plan/03-funkcjonalnosci-mvp.md` — dodać user stories US-RBAC-001 do US-RBAC-040.
- **CLAUDE.md konstytucja projektu** — update sekcji *„Priorytety implementacyjne"*: ADR-013 → MVP-Alpha.

---

*Dokument v2 wygenerowany 2026-05-15 jako synteza brainstormingu RBAC + insights z konkurencyjnych analiz (Gemini: field-level filtering, resource policies, HTTP interceptor; DeepSeek: Super Admin separate, Approver role, `integration_visible` flag, system role concept). Decyzja właściciela: pełen scope MVP bez Faza 1 cuts. Status: Draft — wymaga walidacji pozostałych decyzji architektonicznych (sekcja 6.3) + ADR-013 dopisanie do `Project Plan/01-architektura-pim.md`.*
