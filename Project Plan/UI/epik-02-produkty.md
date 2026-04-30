# Epik 02 — Produkty

## Status: 🔵 placeholder

## 1. Cel epiku

Pełen workflow zarządzania katalogiem produktów (`ObjectType=product`) — *core PIM-u*, codzienne narzędzie pracy Kasi (Catalog Manager). Lista + edycja pojedyncza + bulk actions + import + agent commands (Cmd+K w MVP-Beta-Demo).

## 2. Persony

- **Kasia, 32** (Catalog Manager) — primary user, 80% czasu pracy.
- **Magda, 29** (Marketing) — secondary, edytuje opisy SEO + kategorie.
- **Marcin (founder dogfooding)** — pierwszy real-world klient, IdoSell + Shopify migracja katalogu do PIM-u.

## 3. Kluczowe widoki

### 3.1 Lista produktów (List view)
- Tabela z kolumnami: SKU, Name, Brand, Family, Categories, Completeness%, Channels (dots), Actions.
- Filtry: family, kategoria, brand, completeness range, provenance, status (enabled), channel publication status.
- Sortowanie po dowolnej kolumnie.
- Bulk actions: bulk edit attribute, add/remove category, change family (z ostrzeżeniem), publish to channels.
- Cursor-based pagination (>1000 produktów).
- Saved filters (*„moje 230 czerwonych produktów do dorobienia"*).

### 3.2 Detail produktu (Edit view)
- Layout: sticky header (SKU, name, completeness%, channel dots) + left sidebar (sections nav: Identyfikacja / Opis / Atrybuty techniczne / Kategorie / Marketing / Channels / Locales / History) + main content + right sidebar (related products, integration status, audit log compact).
- **Dynamiczny formularz** — pokazuje tylko atrybuty z family + kategorii (z dziedziczeniem inheritance variant→master→brand z ADR-009).
- **Provenance badges** przy każdym polu (klikalne, tooltip z source).
- **Lock icon** obok pola (*„zablokuj przed nadpisaniem importem"*).
- **Localizable tabs** (PL/EN/...) dla atrybutów `localizable`.
- **Channel sub-tabs** (Web/BaseLinker/Datasheet) dla atrybutów `scopable`.
- **Auto-save 3s debounce** lub Cmd+S.
- **Diff modal** przed save dla zmian wpływających na publikację.

### 3.3 Create product
- Wizard 3-step lub single form z minimum required.
- Wybierz family → wybierz kategorię → wypełnij wymagane atrybuty.
- Cmd+K shortcut: *„stwórz produkt sku=ABC123 family=Czujniki"* (Faza 1).

### 3.4 Variants management
- Master + variants axis (z ADR-010 Axis-Driven Variants).
- Generator variants po deklaracji axes (3 colors × 4 sizes → propose 12 variants).
- Per-variant override tylko `level=variant` atrybutów.

### 3.5 Import (placeholder MVP, fuller w Fazie 1)
- Drag-drop XLSX/CSV.
- Mapping wizard (klient definiuje column → attribute mapping).
- Preview 5 wierszy.
- Conflict resolution (nadpisz / merge / skip per istniejący SKU).
- Provenance: `import` z meta `{supplier, file, date}`.

## 4. User stories (z `Project Plan/03-funkcjonalnosci-mvp.md`)

- US-001: Import produktów z Excel/CSV od dostawcy z mapowaniem kolumn.
- US-002: Edycja atrybutów pojedynczego produktu z dynamicznym formularzem.
- US-003: Sprawdzenie completeness — które produkty są niepełne.
- US-004: Bulk edit atrybutów dla wielu produktów.
- US-005: Dodanie nowego atrybutu/rodziny przez agenta (Cmd+K) — *cross-epic z Modelowaniem*.
- US-006: Pisanie opisów SEO i treści marketingowych per locale.
- US-007: Zarządzanie kategoriami (drzewo, drag-drop) — *kandydat do epiku 02 albo osobno do Modelowania*.

## 5. Business rules / edge cases

- _[TODO: zachowanie przy zmianie family — co dzieje się z atrybutami które nie pasują]_
- _[TODO: lockable fields — czy lock'owanie blokuje też agent edits w Fazie 2?]_
- _[TODO: konflikty bulk edit (50 produktów + zmiana atrybutu, niektóre już mają inną wartość)]_
- _[TODO: undo / rollback po bulk edit (do 24h?)]_

## 6. Dependency na backend

- ADR-006 (Hybrid attribute model) — `object_values` + `attributes_indexed JSONB`.
- ADR-009 (Generic ObjectType) — Product jako kind=`product`.
- ADR-010 (Axis-Driven Variants) — variants encja z `master_object_id`.
- ADR-011 (Per-tenant locale fallback chain) — fallback przy lookup wartości.
- Doctrine listener `attributes-indexed-rebuild` — async dla bulk path.

## 7. Komponenty Refine + shadcn

- Refine `useTable`, `useForm`, `useShow`, `useDelete`, `useUpdate`, `useCreate`.
- shadcn `Table`, `DataTable` (TanStack Table integration), `Form`, `Tabs`, `Sheet` (right sidebar), `Dialog` (diff modal).
- Custom `DynamicAttributeForm` — pole formularza generowane na podstawie `Attribute.type`.
- Custom `ProvenanceBadge` — komponent pokazujący 4 warianty (manual/import/agent/integration).
- `react-dropzone` (już w stacku) — drag-drop import.

## 8. Open questions

- [ ] Categories management — w epiku 02 (osobny widok tree pod Produktami) czy w Modelowaniu (epik 08)?
- [ ] Variants UX — pełen matrix grid vs lista z filter'em (przy 100+ variants)?
- [ ] Bulk edit confirm flow — modal vs sticky toolbar with progress?
- [ ] Smart filters / saved searches — MVP czy Faza 1?
- [ ] AI assist w polu opisu (Faza 1: *„wygeneruj SEO opis z atrybutów"*) — UX wewnątrz pola czy osobny button?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — najważniejszy epik operatorski po Modelowaniu, do priorytetowej iteracji.*
