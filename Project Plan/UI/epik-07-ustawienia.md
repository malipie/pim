# Epik 07 — Ustawienia (Settings)

## Status: 🔵 placeholder

## 1. Cel epiku

Konfiguracja systemu — users, roles (Faza 1+), locales, currencies, BYOK keys (LLM), Anthropic settings, billing (Faza 2), integrations credentials, notifications. *„Wszystko czego nie chcemy mieszać z codzienną pracą Kasi"*.

## 2. Persony

- **Tomasz** (Owner) — billing, users, plan upgrades.
- **Piotr** (IT) — integrations credentials, API keys, locales setup, BYOK keys.
- **Adam** (jeśli różni od Marcina) — locales fallback chain (z ADR-011), default values na ObjectType.
- **Marcin (dogfooding)** — wszystko (jest właścicielem wszystkiego).

## 3. Kluczowe widoki — sekcje Settings

### 3.1 Profile (per user)
- Zmiana hasła, email, 2FA setup.
- Personal preferences (default landing page, dark mode toggle, language).

### 3.2 Users & Roles (Faza 1)
- Lista użytkowników w tenancie + invite flow.
- Per user: role, permissions, last login, active.
- Role management — predefiniowane (super_admin, catalog_manager, marketing, integration_manager, viewer) z możliwością custom (Faza 2).
- _[Uproszczenie MVP: role gating wyłączone (per użytkownika), wszyscy mają wszystko. Dochodzi w Fazie 1.]_

### 3.3 Locales & Currencies
- Lista włączonych locales (PL, EN, DE, CS, ...).
- **Per-tenant fallback chain configuration** (z ADR-011):
  - *„dla `de` fallback na `en`, dla `cs` fallback na `pl`"*.
  - UI: drag-drop list of fallback chains.
- Currencies management (PLN, EUR, USD, ...).
- Default locale + default currency.

### 3.4 Channels
- Lista zdefiniowanych kanałów (web sklep / BaseLinker / Shopify / custom).
- Per-channel: configuration credentials (read-only — managed in Publikacje epik 04), associated locales + currencies.
- _[Uwaga: pełna konfiguracja channel'a jest w Publikacje (epik 04 § 3.2 Integracje), tu tylko overview.]_

### 3.5 BYOK / AI Keys
- **Anthropic API key** (input z reveal/hide toggle).
- **Test connection** button — robi próbny tool call do Anthropic, pokazuje balans tokenów.
- Per-tenant Anthropic billing usage (last 30 days, current month).
- Hard limits config (z sekcji 8.5 archi: 50 tool calls/h, $20/dzień, $300/mies — klient widzi current vs limit).
- Future (Faza 2): wybór providera (OpenAI / Mistral / Azure / Ollama).
- _[BYOK domyślne dla wszystkich on-prem klientów — z PRD § 10.4]_

### 3.6 Backups
- Status pgBackRest backups (last full / differential / WAL).
- Restore time tested (last successful restore drill).
- Manual *„Trigger backup now"* button.
- Faza 1+: rollback to point-in-time (PITR).

### 3.7 Audit log (jeśli osobny od Workflow epik 06 § 3.5)
- Filter audit po user / entity / time range / action.
- Export audit log do CSV.

### 3.8 Notifications
- Per-user preferences (email / in-app / Slack webhook Faza 2).
- Subscriptions: failed sync, completeness threshold, agent budget exceeded, backup failed.

### 3.9 Billing (Faza 2 — z SaaS aktywacją)
- Current plan + tier.
- Usage (SKU count, users, AI calls, paid connectors).
- Upgrade / downgrade flow.
- Invoices history + download PDF.
- _[Faza 2 z SaaS aktywacją; w MVP single-tenant managed billing przez fakturę manualną.]_

### 3.10 Tenant settings (advanced)
- Tenant code, name, owner email.
- Branding (logo, primary color) — Faza 2.
- Custom domain — Faza 2.

## 4. User stories

- **US-EP07-001:** Tomasz invituje 2 nowe osoby (Kasia, Magda) w Fazie 1 z konkretną rolą.
- **US-EP07-002:** Piotr konfiguruje BYOK Anthropic key, testuje connection, widzi balans.
- **US-EP07-003:** Piotr aktywuje 3 dodatkowe locales (DE, CS, EN) + ustawia fallback chain (DE→EN, CS→PL).
- **US-EP07-004:** Tomasz monitoruje cost AI w sekcji BYOK — w 25 dniu miesiąca widzi że zbliża się do $300/mies cap.
- **US-EP07-005:** Kasia w Profile zmienia language na EN (domyślnie była PL).
- _[TODO: enterprise SLA — klient enterprise widzi metryki uptime own tenant'a]_

## 5. Business rules / edge cases

- _[TODO: usuwanie ostatniego super_admin — block]_
- _[TODO: invitation expiry — link wygasa po 7 dniach]_
- _[TODO: API key rotation — ile krócej żyje, jak wymusić rotację]_
- _[TODO: BYOK key zmiana w trakcie agent run — co dzieje się z trwającymi tool calls]_
- _[TODO: locale removal — co z istniejącymi wartościami w tym locale]_

## 6. Dependency na backend

- ADR-003 (multi-tenant) — multi-tenant settings per tenant.
- Sekcja 8.5 archi — BYOK + hard limits + alerty.
- Sekcja 9.4 archi — szyfrowanie wrażliwych pól (BYOK keys AES-256-GCM).
- Symfony Vault / ENV vars dla secrets management.
- Architektura sekcja 11.1a — RLS aktywacja przed multi-tenant SaaS w Fazie 1.

## 7. Komponenty Refine + shadcn

- shadcn `Tabs` — sekcje Settings (Profile / Users / Locales / Channels / BYOK / Backups / ...).
- shadcn `Form`, `Input`, `Select`, `Switch`, `Button`.
- Custom `SecretInput` — input z reveal/hide + copy-to-clipboard.
- Custom `UsageMeter` — visualizacja current vs limit.
- Custom `FallbackChainEditor` — drag-drop UI dla locale fallback.

## 8. Open questions

- [ ] Czy Modelowanie powinno być pod Ustawieniami (jako sub-tab) czy osobno? *Decyzja: osobno (epik 08), bo Modelowanie ma własną głębokość.*
- [ ] Categories management — w epiku 02 (Produkty), epiku 08 (Modelowanie), czy własny sub-tab w Settings?
- [ ] Single Sign-On (SSO/SAML) — Faza 3+ (per PRD § 11.5).
- [ ] White-label theming — Faza 3+.
- [ ] Tenant branding — kiedy pojawia się w UI (Faza 2 z SaaS)?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — Settings rośnie iteracyjnie, najpierw absolute essentials (Profile, BYOK, Locales), reszta dochodzi.*
