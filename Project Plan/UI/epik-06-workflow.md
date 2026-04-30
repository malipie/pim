# Epik 06 — Workflow

## Status: 🔵 placeholder

## 1. Cel epiku

Zarządzanie stanami obiektów (`draft → review → approved → published`) i approval flow. **W MVP minimalna namiastka** (`enabled` boolean jako proxy dla *„draft / live"*). Pełen Symfony Workflow z multi-step approval — Faza 1 (z PRD § 7).

## 2. Persony

- **Kasia** — pracuje codziennie z draft'ami przed akceptacją.
- **Tomasz** (Owner) — review i approve'uje krytyczne zmiany (cena flagowych produktów, opisy nowych marek).
- **Magda** (Faza 2) — approval content marketing.

## 3. Kluczowe widoki

### 3.1 MVP — `enabled` boolean tylko
- W detail produktu: toggle *„Enabled"* (live / paused) — `Object.enabled` boolean.
- `enabled=false` → produkt nie idzie do kanałów (filter w sync handlerach).
- W list view: filter *„Show only enabled / Show only disabled"*.

### 3.2 Faza 1 — pełen workflow
- **State machine konfigurowalny per ObjectType** (Symfony Workflow): `draft → review → approved → published`.
- Per-state UI: stylized badge w list view, sticky header w detail.
- Action buttons: *„Submit for review"*, *„Approve"*, *„Reject"*, *„Revert to draft"*.
- Permission per state — np. tylko user z rolą `super_admin` może `approved → published`.

### 3.3 Faza 1 — Approval inbox
- Widok *„My pending approvals"* (Tomasz / supervisor).
- Bulk approve / reject z optional komentarzem.
- Notyfikacje (in-app + email) przy nowym pending item.

### 3.4 Faza 2 — Workflow per atrybut
- *„Cena wymaga approval CFO"*.
- *„Opisy free-for-all (auto-publish)"*.
- Granularne permissions per pole.

### 3.5 Audit trail (cross-epic z Ustawienia / Dashboard)
- *„Kto zmienił status produktu X 3 dni temu"* — z audit log (Doctrine AuditBundle epik 0.11.4).
- Filter audit po user / entity / time range / action.

## 4. User stories

- US-013: Audit log (kto co kiedy zmienił, filter by date/user/entity) — z `Project Plan/03-funkcjonalnosci-mvp.md`.
- **US-EP06-001 (MVP):** Kasia toggle'uje *„Enabled"* na produkcie żeby zatrzymać publikację bez usuwania.
- **US-EP06-002 (Faza 1):** Kasia submit'uje 30 produktów do review przed publikacją kampanii świątecznej.
- **US-EP06-003 (Faza 1):** Tomasz w *„My pending approvals"* widzi 30 produktów, akceptuje 28, odrzuca 2 z komentarzem.
- _[TODO: workflow per ObjectType — czy wszystkie typy mają taki sam state machine?]_
- _[TODO: rollback z published do draft — kiedy dozwolony?]_

## 5. Business rules / edge cases

- _[TODO: produkt w stanie `published` zostaje zedytowany — czy wraca do `draft` automatycznie czy klient sam decyduje?]_
- _[TODO: kanały publikacji — czy każdy kanał ma swój workflow (na Shopify approved, na BaseLinker draft)?]_
- _[TODO: bulk state change — wymaga approval per produkt czy bulk approve?]_
- _[TODO: integration z agentem Faza 2 — agent tworzy zmiany w stanie `pending_changes`, approve flow]_

## 6. Dependency na backend

- ADR-006 — Doctrine listener przyjmuje `enabled` flagi w sync handlerach.
- Symfony Workflow component (Faza 1) — definicje state machines per ObjectType.
- Audit log z DoctrineAuditBundle (epik 0.11.4 z planu).
- `pending_changes` table jako pusta migracja w MVP (z rewizji 2026-04-27, dla Fazy 2 agent).

## 7. Komponenty Refine + shadcn

- shadcn `Switch` (toggle enabled/disabled).
- shadcn `Badge` (stan workflow z kolorami).
- shadcn `DropdownMenu` (transitions actions).
- Custom `WorkflowStateBadge` — komponent renderujący current state + available transitions.
- Custom `ApprovalInbox` — widok inbox-style z bulk actions.

## 8. Open questions

- [ ] Czy MVP `enabled` jest *jedynym* polem stanu, czy dochodzi `visibility: public/internal/archived` (obok)?
- [ ] Dla Usług (custom kind) — czy ten sam workflow co Produkty czy parametryzowany w Modelowaniu?
- [ ] Approval notifications — email w MVP? In-app SSE w Fazie 1? Slack webhook w Fazie 2?
- [ ] Workflow versioning — gdy Adam zmienia state machine, co się dzieje z obiektami w stanach starych?
- [ ] Cross-channel workflow — czy publish to Shopify zawsze w parze z BaseLinker, czy per-channel?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — w MVP minimum (enabled boolean), pełen Symfony Workflow w Fazie 1.*
