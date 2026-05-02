# [VIEW-01] Modelowanie · Object Types — pixel-perfect lista + detail + wizard

> Ticket view-first wg szablonu `feedback_view_first_ticket_template.md`. Stan na 2026-05-02.
> Źródło prawdy designu: `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/object-types.jsx` + screenshot detail Produkty.
> Prototyp uruchomiony przez `python3 -m http.server 3000` w `Zrodla/.../src` → `http://localhost:3000/Modelowanie.html`, zakładka **Object Types**.

---

## 1. Kontekst i cel widoku

Widok **Modelowanie · Object Types** to centrum administracji schemą domenową PIM-u. Operator (architekt informacji w organizacji-tenancie) używa go żeby:

1. **Zobaczyć wszystkie ObjectType** w workspace — built-in (Produkty, Kategorie, Zasoby, Marki) i custom (Usługi, Lokalizacje, Subskrypcje).
2. **Wejść w detail dowolnego typu** żeby przejrzeć jego identyfikację, attached attribute groups (built-in vs custom), settings (hierarchical/has variants/abstract), where-used.
3. **Edytować custom typy** (nazwa, ikona, kolor, settings, attached groups) i widzieć blokady na built-in.
4. **Stworzyć nowy custom ObjectType** przez 4-stepowy inline wizard (Identyfikacja → Atrybuty → Ustawienia → Podsumowanie).
5. **Usunąć custom typ** gdy nie ma instancji (Danger zone).

Powiązane: ADR-009 (ObjectType jako koncept pierwszej klasy), proponowany ADR-012 (AttributeGroup jako first-class), CLAUDE.md sekcja „Reguły implementacyjne" punkt 11.

Epik: UI-03 — pixel-perfect modelowanie. Backlog źródłowy: `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md`. Ticket nadrzędny: zamyka pierwszy widok view-first flow (lista + detail + wizard razem, nie osobno).

## 2. Mockup / źródło designu

> **WAŻNE — pixel-perfect binding**: implementacja FE MUSI 1:1 odwzorować kod prototypu z `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/object-types.jsx`. To **single source of truth dla layoutu, klas Tailwind, struktury DOM, copy, paddingów, fontów, kolorów i animacji**. Każdy element w `ObjectTypesView`, `ObjectTypeDetail`, `NewObjectTypeView`, `Field`, `GroupCard`, `SettingRow`, `Stat` ma odpowiednik w produkcyjnym kodzie React+Tailwind w `apps/admin/src`. Adaptacje stack-specific (shadcn primitive zamiast hand-rolled) są dozwolone, ale wizualny rezultat ma się zgadzać <2% pixel mismatch.

### Szczegółowe odwołania do prototypu:

- **Lista (`ObjectTypesView`)**: `object-types.jsx:1–87`.
- **Detail (`ObjectTypeDetail`)**: `object-types.jsx:89–244` + screenshot dostarczony 2026-05-02 (Produkty — system type).
- **Wizard (`NewObjectTypeView`)**: `object-types.jsx:304–478`. **Ticket implementuje DOKŁADNIE TEN widok** — nie wymyślamy własnego flow tworzenia ObjectType. Pełny mapping w sekcji 3.4a niżej.
- **Komponenty pomocnicze**: `Field` (246–258), `GroupCard` (260–280), `SettingRow` (282–295), `Stat` (297–302).
- **Shared (`Card`, `LocaleTabs`, `LockBadge`, `I` ikony)**: `Zrodla/.../src/modeling/shared.jsx`.
- **Mock data**: `Zrodla/.../src/modeling/data.jsx` — `OBJECT_TYPES` (7 typów: 4 built-in, 3 custom). Kontrakt response BE musi pokrywać wszystkie pola z mock-a (`id`, `code`, `name`, `nameEn`, `icon`, `color`, `builtIn`, `hierarchical`, `hasVariants`, `abstract`, `instances`, `categories`, `integrations`, `groups[].{code,name,system,attrs[]}`).
- **Powiązane widoki w tym samym shell-u**: zakładki `Attributes` (#VIEW-02), `Attribute Groups` (#VIEW-03), `Categories` (#VIEW-04) w tym samym `/modeling/*`. Nie dotykamy ich w VIEW-01 — tylko spójność topbar/breadcrumb.
- **Rodzic**: brak; **dzieci/modale**: modal „Add attribute group" (do zrobienia w VIEW-03), `<LocaleAddDialog>` (jedyny popup w VIEW-01 — sekcja 3.7), edycja-inline custom group (otwiera detail z VIEW-03).

### Sposób weryfikacji „pixel-perfect":

1. **Side-by-side comparison** — operator otwiera prototyp `http://localhost:3000/Modelowanie.html` w lewej połowie ekranu i implementację `https://pim.localhost/modeling/object-types/{id}` w prawej. Każda sekcja, padding, font-size, border-radius musi się zgadzać.
2. **Visual regression Playwright** — `toHaveScreenshot()` na każdej z 3 tras (`/list`, `/detail/{id}`, `/new`) z baseline'em wygenerowanym z prototypu. Tolerancja <2% pixel mismatch.
3. **Manual review** — operator przejdzie przez listę elementów z sekcji 3.4 / 3.4a / 3.4b (niżej) i odznaczy każdy zgodny z mockupem.

## 3. Zakres frontend (FE)

### 3.1 Routing

> **WAŻNE**: Zarówno detail jak i wizard tworzenia są **osobnymi pełnoekranowymi widokami trasowanymi**, renderowanymi w shellu `/modeling/*` jako `<Outlet>` zakładki Object Types. **Brak popupów / Sheetów / Dialogów dla tych dwóch widoków** — obecny `<CreateCustomObjectTypeDialog>` (Sheet) zostaje **całkowicie usunięty** i zastąpiony trasowanym wizardem. Mockup w `NewObjectTypeView` (`object-types.jsx` 304–478) renderuje się inline w obrębie shellu modelowania (zachowuje sidebar workspace + topbar), a nie jako overlay.

- Lista: `/modeling/object-types` — istnieje (`ObjectTypesListPage`). Lista renderuje się jako default content zakładki Object Types.
- Detail: `/modeling/object-types/:id` — istnieje (`ObjectTypeShowPage`), wymaga pełnej przebudowy pixel-perfect. **Osobny widok**, nie popup; URL bezpośrednio shareable; powrót przez breadcrumb „Wstecz do listy Object Types".
- Nowy typ: `/modeling/object-types/new` — **NOWA TRASA, OSOBNY WIDOK PEŁNOEKRANOWY**. Wizard 4-stepowy renderowany w obrębie shellu modelowania (nie modal/Sheet/Dialog). URL shareable; powrót przez „Anuluj" lub breadcrumb. **Komponent `<CreateCustomObjectTypeDialog>` (obecny popup w `apps/admin/src/components/modeling/create-custom-object-type-dialog.tsx`) usuwamy z repo** — replacement: nowa trasa + komponent `<ObjectTypeWizardPage>` (route-level) + `<ObjectTypeWizard>` (presentation).
- Edycja inline (nie osobna trasa): tryb edycji per pole na detailu — pencil → input/picker w miejscu, blur/Enter zapisuje (PATCH). Pixel-perfect mockup nie ma osobnego ekranu „edit", tryb edycji jest in-place.
- Auth requirement: `IS_AUTHENTICATED_FULLY` + role `ROLE_ADMIN` na wszystkich 3 trasach.

#### Dlaczego osobny widok, nie popup

1. **Pixel-perfect zgodność z mockupem** — `NewObjectTypeView` w prototypie ma 320px sidebar z live preview + tips, breadcrumb „Wstecz do listy", buttons „Anuluj/Utwórz typ" w prawym górnym rogu, step indicator. To wszystko nie mieści się w 420px Sheet.
2. **URL shareable** — operator może wkleić link do wizardu w połowie tworzenia (np. zostawić zakładkę otwartą).
3. **Spójność z detailem** — detail jest osobnym widokiem, więc create też powinien być (analogia kreator-↔-szczegół).
4. **A11y** — popup/Sheet trapuje focus, blokuje skroll body, dodaje warstwy ARIA. Pełnoekranowy widok nie ma tych komplikacji.

### 3.2 Komponenty (lista płaska)

#### Komponenty istniejące do reużycia (sprawdzone w kodzie):

- `ModelingPageHeader` — `caption`, `title`, `description`, `ctaLabel`, `onCtaClick`. **Reuse jako nagłówek listy.**
- `ModelingSection` — `label`, `tagline`, `summary?`, `locked?`, `children` (rows). **Reuse pod „Built-in (system)" i „Custom (your organization)".**
- `ModelingRow` — `to`, `leading`, `title`, `code`, `badges`, `secondaryLabel`, `metaPrimary`, `metaSecondary`. **Reuse pod row listy.** Sprawdź czy renderuje chevron right (mockup ma `chevRight` na hover) — jeśli nie, dodaj.
- `BuiltInLockBadge` — istnieje, mała `<Lock>` z tekstem „system". **Reuse dla badge przy nazwie typu.**
- `AuditLogIndicator` — istnieje, kropka + tekst „Audit log: aktywny · ostatnia zmiana N min temu". **Reuse w prawym górnym rogu detailu.**
- `WhereUsedList` — istnieje, ale to lista not 3-card grid jak w mockupie. **NOWY KOMPONENT** `<WhereUsedStats>` z 3 boxami (`Stat` z mockupu): `instances` / `categories` / `integrations`.
- `Card`, `CardContent` — shadcn, reuse.
- `Sheet` — reuse pod ewentualne modale (np. Attach attribute group w przyszłości; w VIEW-01 nie używany).
- `Button`, `Input` — shadcn, reuse.

#### Komponenty NOWE do napisania (apps/admin/src/components/modeling/):

- `<ObjectTypeIcon size? color? icon? />` — wspólny renderer ikony z tłem `color + 18%` (mockup używa `style={{ background: t.color + "18", color: t.color }}`). Domyślne 14×14 / 10×10 wariant.
- `<LocaleTabsField values={{pl, en}} onChange? readOnly? primary='pl' />` — pixel-perfect odpowiednik `window.LocaleTabs` z prototypu (PL/EN/+Dodaj język tabs, input pod spodem, badge `PRIMARY` po prawej stronie inputu).
- `<FieldDisplay label value mono? lock? editable? onEdit? />` — odpowiednik `Field` z mockupu (etykieta + pudełko z wartością, lock icon, pencil edit).
- `<GroupCard group locked? onEdit? onRemove? />` — `groups.attrs[]` jako chipy, max 8 widoczne + „+N więcej", pencil + trash po prawej (gdy nie locked). Bezpośredni link do detailu grupy w VIEW-03.
- `<SettingToggleRow label desc checked onChange? lock? />` — toggle 11x6 px w stylu Apple, lock badge przy etykiecie.
- `<StatBox value label />` — box z dużą cyfrą (font-display 24px) + label pod spodem.
- `<DangerZoneCard title description disabled? destructiveLabel onConfirm />` — czerwona ramka, button po prawej (disabled gdy instances > 0), confirmation alert dialog przy klik.
- `<IconPicker selected onChange options? />` — 8-emoji preset (`📦 🎫 📍 📅 🔄 💎 🛠️ 🚚`) jako wybieralne kafelki 10×10 px.
- `<ColorPicker selected onChange options? />` — 7-kolor preset (`#6366f1 #22c55e #f59e0b #ef4444 #3b82f6 #a855f7 #14b8a6`) jako kafelki 8×8 px z borderem na zaznaczonym.
- `<ObjectTypeWizardPage>` — **route-level component** dla `/modeling/object-types/new`, renderowany w `<Outlet>` shellu modelowania. Wewnątrz: `<ObjectTypeWizard>`.
- `<ObjectTypeWizard onCancel onCreated />` — 4-step wizard z step indicator, sidebar 320px (live preview + tips), Anuluj / Utwórz w prawym górnym rogu, ← Poprzedni / Dalej → na dole każdego kroku. **Renderowany inline jako pełnoekranowy widok**, nie w Sheet/Dialog.
- `<LocaleAddDialog open onClose onLocaleAdded availableLocales currentLocales />` — modal (shadcn Dialog) wywoływany z `<LocaleTabsField>` po kliknięciu „+ Dodaj język". Pokazuje listę z `LOCALE_LIBRARY` (14 entries — `pl`, `en`, `de`, `fr`, `it`, `es`, `pt`, `nl`, `cs`, `sk`, `ru`, `uk`, `hu`, `ro`) z odfiltrowanymi już aktywowanymi. Wybór dodaje locale do workspace (POST `/api/workspaces/current/locales`) + dodaje pusty entry do label aktualnie edytowanego ObjectType. **Modal jest mały (400px) i dotyczy konkretnej akcji „dodaj język"** — to inny przypadek niż detail/wizard ObjectType (które są osobnymi widokami).
- `<AuditTrailCompact entries={5} />` — lista 5 ostatnich zmian (kto / kiedy / co — diff badge); na razie loading-state + empty-state, faktyczne dane z `GET /api/object_types/{id}/audit_log` (BE 4.1).

#### Komponenty do przebudowy:

- `ObjectTypesListPage` (`features/catalog/object-types/list.tsx`):
  - Usunąć stan `createOpen` + `<CreateCustomObjectTypeDialog>` — zastąpić przekierowaniem na `/modeling/object-types/new`.
  - Liczniki `groups.length` (kolumna „N grup atrybutów") — fetchować z nowego pola w GET listy (zob. 4.1) zamiast `row.schemaVersion`.
  - Badge `hierarchical` / `variants` — czytać z prawdziwych pól `hierarchical`, `hasVariants`, `abstract` w response (zob. 4.1) zamiast heurystyki po `kind`.
  - Empty state custom: zachować obecny tekst, ale dodać duży przycisk-CTA na dole tak jak w mockupie (linia 80–83 prototypu) — wewnątrz `ModelingSection` jako footer slot lub osobny komponent pod sekcją.
- `ObjectTypeShowPage` (`features/catalog/object-types/show.tsx`):
  - Pełna przebudowa zgodnie z mockupem (8 sekcji w stałej kolejności — patrz 3.4 niżej).
  - Usunąć obecny prosty Card z `DetailRow` — zastąpić strukturalnymi sekcjami.
  - Zastąpić `<WhereUsedList>` triplem `<StatBox>`.
- `CreateCustomObjectTypeDialog` — **usunąć cały plik** (`apps/admin/src/components/modeling/create-custom-object-type-dialog.tsx`), zastąpić routing-driven `<ObjectTypeWizardPage>` (osobny widok pełnoekranowy, nie popup).

### 3.3 State management

- **Refine resources**: `object_types` (już skonfigurowany — sprawdź `apps/admin/src/lib/refine.ts` lub data provider). Dodać:
  - `update` action (PATCH) — `useUpdate` w detailu dla edycji label/icon/color/settings.
  - `deleteOne` action — `useDelete` dla Danger zone.
  - `create` action — `useCreate` w wizardzie (POST).
- **Local state w detailu**:
  - `editingField: 'name' | 'icon' | 'color' | 'settings' | null` — który field jest w trybie edit.
  - `optimisticPatch: Partial<ObjectTypeDetail>` — dla optymistycznych update przy save inline.
  - `confirmingDelete: boolean` — modal potwierdzenia w Danger zone.
- **Local state w wizardzie**:
  - `step: 1..4`, `name: {pl, en}`, `code: string`, `icon: string`, `color: string`, `hierarchical: bool`, `hasVariants: bool`, `abstract: bool`, `attachedGroupIds: Uuid[]` (krok 2).
  - Walidacja per krok przed `Dalej →` (kod snake_case, label PL niepuste).
- **Mutacje**:
  - `useUpdate({ resource: 'object_types', id })` — invalidate `['object_types']` + `['object_types', id]` + `['object_types', id, 'usage']`.
  - `useDelete` — invalidate listy + push routera na `/modeling/object-types`.
  - `useCreate` — push routera na `/modeling/object-types/{newId}` po sukcesie.
- **Cache strategy**:
  - Lista `staleTime: 30s` (mockup pokazuje audit-log update 14 min — krótki staleTime OK).
  - Detail `staleTime: 0` (zawsze fresh przy wejściu).
  - Usage `staleTime: 60s` (zgodnie z TTL cache po stronie BE).

### 3.4 Struktura sekcji detailu (kolejność i zawartość)

Render w tej kolejności (gdy nie jest tryb wizard `/new`):

1. **Top breadcrumb** — `Wstecz do listy Object Types` (lewy) + `AuditLogIndicator` (prawy).
2. **Header**: ikona 14×14 z tłem-color, nazwa H1 28px font-display, badge `system` (Lock) lub `custom` (emerald), code mono pod nazwą, oddzielony • od „System type — limited customization" / „Custom type — full control"; po prawej: `Duplikuj` (ghost) + `Edytuj` (filled black).
3. **Card „Identyfikacja"**:
   - Label „Nazwa" + `LocaleTabsField` (PL primary input, EN secondary tab, +Dodaj język).
   - Grid 2-kol: Code (lock dla built-in), Icon (editable picker dla custom), Color (editable picker dla custom), Tenant (lock zawsze — zwraca aktualny tenant code z BE).
4. **Card „Built-in attribute groups"** — header z lock badge + tagline „dołączane automatycznie", `space-y-2` lista `<GroupCard locked>` per `system=true` group, **bez** edit/remove buttons.
5. **Card „Custom attribute groups"** — header z taglinem „globalne grupy dla wszystkich obiektów typu „{nazwa}"" + przycisk „+ Add attribute group" (tylko dla custom OR built-in z możliwością dołączania, ale nie usuwania). Pod spodem lista `<GroupCard>` (z edit/remove). Empty state: dashed border z tekstem „Brak custom grup. Dodaj pierwszą — np. Marketing, Pricing, Specyfika.".
6. **Card „Settings"**:
   - 3 toggle rows: `Is hierarchical`, `Has variants`, `Is abstract` (lock dla built-in).
   - Border-top divider.
   - „Allowed parent types" — chipy parent typów (mockup pokazuje `Category` jako jedyny). Dla custom + przycisk dashed „+ Add parent type" (otwiera dropdown wyboru z innych ObjectTypes — w VIEW-01 placeholder, faktyczna implementacja w VIEW-04 categories).
7. **Card „Where used"** — grid 3-kol z `<StatBox>`: instances, categories, integrations (z `/api/object_types/{id}/usage`).
8. **Card „Danger zone"** (TYLKO custom):
   - Tytuł + opis (różny gdy `instances > 0`: „Niemożliwe — N instancji istnieje. Migruj je lub usuń najpierw." vs „Brak instancji — można bezpiecznie usunąć.").
   - Button „Usuń" → confirmation modal (`<AlertDialog>` shadcn) → `useDelete`.
9. **Footer (poza Card)**: tekst „Pim · workspace „{nazwa tenanta}" · ADR-009 · proponowany ADR-012 (Attribute Group as first-class)" po lewej + `v1.0.0-rc.4 · model schema rev N` po prawej. Nazwa workspace pobierana z `GET /api/workspaces/current.name`.

### 3.4a Mapping wizardu `/modeling/object-types/new` — element-po-elemencie z `NewObjectTypeView` (object-types.jsx:304–478)

**Layout** (`object-types.jsx:320–321`): root `<div>` w obrębie shellu modelowania (nie modal). Sidebar workspace + topbar zachowane jak w detailu.

**Top breadcrumb** (322–325): „Wstecz do listy Object Types" z `arrowLeft` icon, font-medium 12.5px text-zinc-500 hover text-zinc-900.

**Header** (327–343): grid `flex-1 + shrink-0`:
- Lewa: caption „Nowy ObjectType" 13px text-zinc-500 font-medium → tytuł H1 28px font-display font-semibold (renderuje `name || "Bez nazwy"` — live updates z input) → opis 13px text-zinc-500 max-w-2xl.
- Prawa: button „Anuluj" (ghost, hover bg-zinc-100, h-9 px-3 rounded-xl) + button „Utwórz typ" (filled bg-zinc-900 text-white, h-9 px-4 rounded-xl, ikona `check`).

**Step indicator** (345–360): horizontal row z 4 segmentami, każdy:
- Aktywny: `bg-zinc-900 text-white`.
- Ukończony (`step > s.n`): `bg-zinc-100 text-zinc-700` z zieloną kropką `bg-emerald-500` zawierającą ✓.
- Niedostępny: `bg-white border border-zinc-200 text-zinc-500`.
- Każdy segment klikalny → `setStep(s.n)` (cofa do wcześniejszych kroków).
- Pomiędzy segmentami: `<span className="h-px w-6 bg-zinc-200" />`.

**Grid 2-kol** (362): `[1fr_320px]` — lewa kolumna content kroku, prawa sidebar (preview + tips).

#### Krok 1 — Identyfikacja (364–399):
- Header: „Identyfikacja" 11px uppercase tracking-wider text-zinc-500 font-medium.
- `<LocaleTabsField>` z propsami `values={{pl: name, en: ""}}` `onChange={(v) => setName(v.pl || "")}` `placeholder="np. Subskrypcja"` — w VIEW-01 to musi być realny komponent (sekcja 3.2) z tabs PL/EN/+Dodaj język.
- Grid `grid-cols-2 gap-x-8 gap-y-4`:
  - **Code**: input h-10 rounded-xl bg-white border border-zinc-200 text-13px font-mono, placeholder „subscription". Auto-snake_case na `onChange` (lower + replace non-alphanum).
  - **Ikona**: 8 emoji wybieralnych (linia 380): `📦 🎫 📍 📅 🔄 💎 🛠️ 🚚`. Wybrany: `bg-zinc-900 text-white`. Niewybrany: `bg-white border border-zinc-200 hover:bg-zinc-50`.
  - **Kolor** (col-span-2): 7 kolorów (linia 390): `#6366f1 #22c55e #f59e0b #ef4444 #3b82f6 #a855f7 #14b8a6`. Wybrany: `border-2 border-zinc-900`. Niewybrany: `border-2 border-transparent`.

#### Krok 2 — Atrybuty (400–418):
- Header: „Built-in attribute groups" + subtitle „Te grupy zostaną dołączone automatycznie i nie można ich usunąć:".
- Lista 3 boxów `flex items-center gap-2 px-3 py-2.5 rounded-xl bg-zinc-50` z `<LockBadge>` + nazwą: „Identyfikacja", „Audyt", „Lokalizacje" (mockup). **W produkcyjnym BE: faktyczna lista built-in groups z `GET /api/attribute_groups?builtIn=true` filtrowane po `kind` typu, który tworzymy** — niech wizard pobiera tę listę z BE, nie hardcode na FE.
- Header: „Custom attribute groups" 11px uppercase.
- Przycisk dashed `w-full py-3 rounded-2xl border border-dashed border-zinc-300` z tekstem „+ Dodaj grupę atrybutów" — w VIEW-01 placeholder, klik wyświetla toast „Dostępne po VIEW-03".

#### Krok 3 — Ustawienia (419–426):
- Header: „Ustawienia".
- 3 `<SettingRow>` (komponent `<SettingToggleRow>` w sekcji 3.2):
  - „Is hierarchical" / „Obiekty mogą tworzyć drzewo".
  - „Has variants" / „Obiekty mogą mieć warianty".
  - „Is abstract" / „Tylko sub-typy mogą mieć instancje".
- Brak locka — to nowy typ, custom kind = wszystkie ustawienia odblokowane.

#### Krok 4 — Podsumowanie (427–438):
- Header: „Podsumowanie" + opis „Sprawdź ustawienia i zatwierdź. Po utworzeniu typ pojawi się w sekcji Custom.".
- Box `rounded-2xl bg-zinc-50 p-4 grid grid-cols-2 gap-3 text-13px` z 4 polami:
  - Nazwa: `{name || "—"}` font-medium.
  - Code: `{code || "—"}` font-mono.
  - Ikona: emoji wybrana.
  - Kolor: kwadrat 4×4 z `style={{ background: color }}`.

#### Footer kroku (440–450):
- Lewa: „← Poprzedni" (disabled gdy step=1, text-zinc-300).
- Prawa: „Dalej →" (gdy step<4) lub „✓ Utwórz typ" (gdy step=4).
- `border-t border-zinc-100 pt-3`.

#### Sidebar 320px (453–474):
- **Card „Podgląd"** (455–464): mini preview ObjectType w trakcie tworzenia — ikona 10×10 z tłem `color + 18`, nazwa 14px font-semibold (lub „Nowy typ" jeśli pusta), code mono 11.5px (lub „code…").
- **Card „Wskazówki"** (465–473): `<ul>` z 4 bulletami:
  - „Code powinien być w snake_case"
  - „Nazwa pojawia się w UI i navbarze"
  - „Hierarchical = drzewo (jak Category)"
  - „Variants = osie wariantowości (kolor × rozmiar)"

### 3.4b Mapping detailu element-po-elemencie z `ObjectTypeDetail` (object-types.jsx:89–244)

**Top back-link** (100–104): `arrowLeft` + „Wstecz do listy Object Types" — 12.5px text-zinc-500 font-medium hover text-zinc-900.

**Header** (107–129):
- Ikona 14×14 (`h-14 w-14`) `rounded-2xl grid place-items-center text-26px shrink-0` z `style={{ background: t.color + "18", color: t.color }}`.
- Tytuł H1 28px font-display font-semibold tracking-tight + badge: `<LockBadge tip="System type — protected" />` dla built-in albo `<span className="bg-emerald-50 text-emerald-700">custom</span>` dla custom.
- Pod tytułem: code mono + separator `· ` + opis („System type — limited customization" lub „Custom type — full control").
- Prawa strona: „Duplikuj" (ghost) + „Edytuj" (filled bg-zinc-900).

**Identyfikacja Card** (133–150):
- Padding `p-6`, header 11px uppercase tracking-wider „Identyfikacja".
- Pole „Nazwa" + `<LocaleTabsField>` (PL primary, EN secondary, + Dodaj język).
- Grid 2-kol `grid-cols-2 gap-x-8 gap-y-4`:
  - Code (lock dla built-in), Icon (editable), Color (editable), Tenant (lock).

**Built-in attribute groups Card** (153–162): header + lock badge + tagline „dołączane automatycznie", lista `<GroupCard locked>` per `system=true` group.

**Custom attribute groups Card** (165–185):
- Header z taglinem `globalne grupy dla wszystkich obiektów typu „{nazwa}"` + przycisk „+ Add attribute group" (text-zinc-700 hover text-zinc-900).
- Empty state: `border border-dashed border-zinc-200 rounded-2xl py-8` z tekstem.
- Lista `<GroupCard>` z edit/remove buttons po prawej.

**Settings Card** (188–208):
- 3 `<SettingRow>` (label + desc + toggle), każdy z lockiem dla built-in.
- `border-t border-zinc-100 pt-5` separator.
- „Allowed parent types" — chip `<span className="bg-zinc-100">` z `Category` (mockup) + przycisk dashed „+ Add parent type" (tylko custom).

**Where used Card** (211–218): `grid grid-cols-3 gap-4` z 3 `<Stat>` (instances/categories/integrations).

**Danger zone Card** (221–240) — TYLKO custom:
- `border border-rose-100`, header `text-rose-600`.
- Tytuł + opis (różny per `instances > 0`).
- Button „Usuń" — disabled gdy instances>0 (`bg-zinc-100 text-zinc-400`), aktywny `bg-rose-600 text-white hover:bg-rose-700`.

### 3.4c Mapping listy element-po-elemencie z `ObjectTypesView` (object-types.jsx:1–87)

**Header sekcji** (40–55): caption „N typów obiektów" + tytuł „Object Types" 28px font-display + opis + CTA „+ Nowy typ" (h-9 px-4 rounded-xl bg-zinc-900 text-white).

**Card „Built-in (system)"** (58–65):
- Padding `p-3`, header `flex items-center gap-2 px-4 pt-2 pb-3` z `<LockBadge>` + „Built-in (system)" 11px uppercase + „— fundament PIM-u, używane przez integracje".
- Lista row'ów `space-y-0.5`.

**Card „Custom (your organization)"** (68–84):
- Header z liczbami `N typów · M instancji` po prawej.
- Lista row'ów + duży CTA „+ Stwórz nowy ObjectType (np. Subskrypcja, Lokalizacja, Wydarzenie)" (`m-3 w-[calc(100%-1.5rem)] py-3 rounded-2xl bg-zinc-900 text-white`).

**Row** (10–35):
- Grid `[40px_1.5fr_1fr_120px_120px_28px]` gap-4 px-5 py-4 rounded-2xl row-hover.
- Ikona 10×10 z tłem `color + 18` + emoji.
- Nazwa 15px font-semibold + badges (`<LockBadge>`, `hierarchical`, `variants`) + code mono 12px text-zinc-500 pod spodem.
- nameEn 12px text-zinc-500.
- „N grup atrybutów" — `groups.length`.
- „N instancji" — bold 14px text-zinc-900.
- Chevron right text-zinc-300 group-hover text-zinc-700 justify-self-end.

### 3.5 i18n

Wszystkie nowe i istniejące user-facing stringi w `apps/admin/src/locales/pl/translation.json` i `en/translation.json`. Lista nowych kluczy:

```
object_types:
  detail_subtitle_system: 'System type — limited customization'
  detail_subtitle_custom: 'Custom type — full control'
  duplicate_action: 'Duplikuj'
  edit_action: 'Edytuj'
  identification_section: 'Identyfikacja'
  field_name: 'Nazwa'
  field_code: 'Code'
  field_icon: 'Ikona'
  field_color: 'Kolor (badge)'
  field_tenant: 'Tenant'
  builtin_groups_section: 'Built-in attribute groups'
  builtin_groups_tagline: 'dołączane automatycznie'
  builtin_groups_lock_tip: 'Auto-attached system groups — cannot be removed'
  custom_groups_section: 'Custom attribute groups'
  custom_groups_tagline: 'globalne grupy dla wszystkich obiektów typu „{{name}}"'
  custom_groups_empty: 'Brak custom grup. Dodaj pierwszą — np. Marketing, Pricing, Specyfika.'
  add_attribute_group: '+ Add attribute group'
  attrs_count_one: '{{count}} atrybut'
  attrs_count_few: '{{count}} atrybuty'
  attrs_count_many: '{{count}} atrybutów'
  more_attrs: '+{{count}} więcej'
  settings_section: 'Settings'
  setting_hierarchical_label: 'Is hierarchical'
  setting_hierarchical_desc: 'Obiekty mogą tworzyć drzewo (jak Category)'
  setting_variants_label: 'Has variants'
  setting_variants_desc: 'Obiekty mogą mieć warianty (jak Product → kolor × rozmiar)'
  setting_abstract_label: 'Is abstract'
  setting_abstract_desc: 'Nie można tworzyć instancji bezpośrednio (tylko przez sub-typy)'
  allowed_parent_types_label: 'Allowed parent types'
  add_parent_type: '+ Add parent type'
  where_used_section: 'Where used'
  stat_instances_label: 'instancji w bazie'
  stat_categories_label: 'kategorii używa tego typu'
  stat_integrations_label: 'integracji odwołuje się'
  danger_zone_title: 'Danger zone'
  delete_action: 'Usuń ObjectType „{{name}}"'
  delete_blocked_message: 'Niemożliwe — {{count}} instancji istnieje. Migruj je lub usuń najpierw.'
  delete_safe_message: 'Brak instancji — można bezpiecznie usunąć.'
  delete_button_blocked: 'Zablokowane'
  delete_button_safe: 'Usuń'
  delete_confirm_title: 'Usunąć ObjectType „{{name}}"?'
  delete_confirm_body: 'Operacja jest nieodwracalna. Wszystkie powiązania z attribute groups zostaną zerwane.'
  footer_workspace: 'Pim · workspace „{{tenant}}" · ADR-009 · proponowany ADR-012 (Attribute Group as first-class)'
  footer_version: 'v{{version}} · model schema rev {{rev}}'
  audit_trail_title: 'Historia zmian (5 ostatnich)'
  audit_trail_empty: 'Brak zmian.'

object_type_wizard:
  title_new: 'Nowy ObjectType'
  default_name: 'Bez nazwy'
  intro: 'Stwórz nowy rodzaj obiektu w swoim PIM-ie. Po utworzeniu będzie można dodawać instancje, podłączać atrybuty i mapować integracje.'
  cancel: 'Anuluj'
  submit: 'Utwórz typ'
  step_1_label: 'Identyfikacja'
  step_2_label: 'Atrybuty'
  step_3_label: 'Ustawienia'
  step_4_label: 'Podsumowanie'
  prev: '← Poprzedni'
  next: 'Dalej →'
  step_2_intro: 'Te grupy zostaną dołączone automatycznie i nie można ich usunąć:'
  step_2_custom_label: 'Custom attribute groups'
  step_2_add_group: '+ Dodaj grupę atrybutów'
  step_4_intro: 'Sprawdź ustawienia i zatwierdź. Po utworzeniu typ pojawi się w sekcji Custom.'
  preview_label: 'Podgląd'
  preview_default_name: 'Nowy typ'
  preview_default_code: 'code…'
  tips_label: 'Wskazówki'
  tip_snake_case: 'Code powinien być w snake_case'
  tip_name_visibility: 'Nazwa pojawia się w UI i navbarze'
  tip_hierarchical: 'Hierarchical = drzewo (jak Category)'
  tip_variants: 'Variants = osie wariantowości (kolor × rozmiar)'
  validation_code_required: 'Code jest wymagany.'
  validation_code_format: 'Code musi być w snake_case (małe litery, cyfry, _).'
  validation_name_pl_required: 'Nazwa PL jest wymagana.'
  conflict_code_taken: 'Code „{{code}}" jest już zajęty w tym tenantcie.'
```

**Ban na literały** w JSX poza data-fixtures. Przed PR uruchom `pnpm lint:i18n` (jeśli istnieje skrypt — jeśli nie, dodaj jako follow-up audit ticket).

### 3.6 a11y

- ARIA roles: `role="tablist"` dla LocaleTabs, `role="tab"`, `aria-selected`, `aria-controls`.
- Wizard step indicator: `role="progressbar"`, `aria-valuenow={step}`, `aria-valuemax={4}`, `aria-label="Krok {step} z 4"`.
- Toggle rows: `<button role="switch" aria-checked={checked}>` (nie `<input type=checkbox>` — mockup wizualnie to switch).
- Pencil icon (edit) buttons: `<button aria-label="Edytuj {field}">`.
- Trash icon (delete group): `<button aria-label="Usuń grupę {name}">`.
- Empty state ma `<p role="status" aria-live="polite">` żeby screen reader przeczytał gdy zniknął loading.
- Lock badge: `<span aria-label="System — nie można edytować">`.
- Color picker swatche: `<button aria-label="Kolor {hex}" aria-pressed={selected}>`.
- Icon picker swatche: `<button aria-label="Ikona {emojiName}" aria-pressed={selected}>`.
- Keyboard navigation:
  - Tab order: header → identification → groups → settings → where used → danger zone.
  - Enter na row listy = nawigacja do detailu.
  - Esc w wizardzie = `onCancel` (jak Anuluj).
  - W LocaleTabs strzałki ←→ przełączają między PL/EN.
- Focus ring: użyj `.focus-ring` z prototypu (`box-shadow: 0 0 0 4px rgba(24,24,27,0.08)`).
- **axe-core scan**: 0 violations level=`serious`/`critical` na każdej sub-trasie (`/modeling/object-types`, `/modeling/object-types/:id`, `/modeling/object-types/new`). Test Playwright + `@axe-core/playwright`.

### 3.7 Zarządzanie językami workspace (NOWE — wymagane przez `<LocaleTabsField>`)

> Mockup `LocaleTabsField` ma przycisk „+ Dodaj język" w detailu Identyfikacja (każdy ObjectType, każde pole multilingual). Aby ten przycisk realnie coś robił, musimy mieć **per-tenant configurable list of enabled locales**. Dotychczas hard-code'owane były `pl` + `en` w fixtures. VIEW-01 dorzuca pełną obsługę: lista dostępnych locali, dodawanie, usuwanie (z guardem przed usunięciem `pl` jako `primary_locale`).

#### Komponenty FE (sekcja 3.2 rozszerzona):

- `<LocaleTabsField>` ma teraz prop `enabledLocales: string[]` (z workspace settings) i `primaryLocale: string`. Po kliknięciu „+ Dodaj język":
  1. Otwiera `<LocaleAddDialog>` (mały modal 400px — to JEDYNY popup w VIEW-01, bo to akcja-mikro a nie pełen widok).
  2. Modal pokazuje listę z `LOCALE_LIBRARY` (constant w `apps/admin/src/lib/locales.ts`) odfiltrowaną z już aktywowanych.
  3. Wybór locale → POST `/api/workspaces/current/locales` `{locale: 'de'}` → 201 → toast → refresh `enabledLocales` → tab dla `de` pojawia się w `<LocaleTabsField>` z pustym inputem.
- `<LocaleManagementSection>` (NOWY, w `/settings/workspace` lub jako sub-tab) — pełen UI zarządzania: lista aktywnych locali, ustawienie primary, usunięcie (z confirm gdy są dane). **WYŁĄCZONE Z VIEW-01** w sensie pełnym; w VIEW-01 implementujemy tylko POST add via `<LocaleAddDialog>`. Pełen ekran settings w follow-up VIEW-99 (Settings).
- `useEnabledLocales()` hook — wraps `useOne({resource: 'workspaces', id: 'current'})` → zwraca `{locales: string[], primary: string, isLoading}`. Cache `staleTime: 5min`.

#### Stałe FE (NOWY plik `apps/admin/src/lib/locales.ts`):

```ts
export const LOCALE_LIBRARY = [
  { code: 'pl', label: 'Polski',          flag: '🇵🇱' },
  { code: 'en', label: 'English',         flag: '🇬🇧' },
  { code: 'de', label: 'Deutsch',         flag: '🇩🇪' },
  { code: 'fr', label: 'Français',        flag: '🇫🇷' },
  { code: 'it', label: 'Italiano',        flag: '🇮🇹' },
  { code: 'es', label: 'Español',         flag: '🇪🇸' },
  { code: 'pt', label: 'Português',       flag: '🇵🇹' },
  { code: 'nl', label: 'Nederlands',      flag: '🇳🇱' },
  { code: 'cs', label: 'Čeština',         flag: '🇨🇿' },
  { code: 'sk', label: 'Slovenčina',      flag: '🇸🇰' },
  { code: 'ru', label: 'Русский',         flag: '🇷🇺' },
  { code: 'uk', label: 'Українська',      flag: '🇺🇦' },
  { code: 'hu', label: 'Magyar',          flag: '🇭🇺' },
  { code: 'ro', label: 'Română',          flag: '🇷🇴' },
] as const;
```

### 3.8 Empty / loading / error states

- **Lista**:
  - Loading: `<ModelingSection>` z 4 skeletonami row (każdy 64px wysoki, animate-pulse).
  - Empty (built-in): nigdy nie powinno się pojawić (seed gwarantuje 4 rzędy) — ale defense-in-depth: `<EmptyState>` z tekstem „Built-in seed nie został zaaplikowany — sprawdź `console doctrine:fixtures:load`".
  - Empty (custom): tekst „Brak custom ObjectTypes — utwórz pierwszy poniżej." + duży CTA (mockup linia 80).
- **Detail**:
  - Loading: skeleton header (40px ikona + 28px tytuł + 13px subtitle) + 5 skeleton cards.
  - 404: redirect na `/modeling/object-types` + toast „ObjectType nie znaleziony" (RFC 7807 `type=urn:pim:errors:object-type-not-found`).
- **Wizard**:
  - Loading przy submit: button „Utwórz typ" disabled + spinner; inputy disabled.
  - Error przy submit: inline alert nad wizardem (`<Alert variant="destructive">`) z RFC 7807 `detail`. Conflict 409 (code zajęty) → highlight pola Code + tłumaczenie `conflict_code_taken`.

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request | Response (200/2xx) | Permissions | Filtry/sort/paginacja |
|--------|------|---------|--------------------|-------------|------------------------|
| GET | `/api/object_types` | — | JSON-LD `hydra:Collection` z polami: `id`, `code`, `kind`, `label`, `builtIn`, `codeImmutable`, `deletable`, `icon`, `color`, `schemaVersion`, **`hierarchical`** (NEW), **`hasVariants`** (NEW), **`abstract`** (NEW), **`allowedParentTypes`** (NEW, lista UUID/code referencji do innych ObjectType), **`attributeGroupsCount`** (NEW, liczba attached groups system+custom razem), **`builtInGroupsCount`** (NEW), **`customGroupsCount`** (NEW), **`instancesCount`** (NEW, denormalized z usage cache albo on-the-fly count) | `ROLE_ADMIN` | sort=`label.{locale}`, filter=`builtIn`, `kind`; bez paginacji w MVP (max 50 typów na tenant) |
| GET | `/api/object_types/{id}` | — | jak wyżej + **`attachedGroups`**: `[{id, code, label, system, attrsCount, attrsPreview: [code,...] (max 8), color?, icon?}]`, **`auditLogPreview`**: `[{id, action, actorName, occurredAt, diffSummary}]` (max 5) | `ROLE_ADMIN` + `READ` voter na obiekcie | — |
| POST | `/api/object_types` | `{code, label{pl,en}, icon?, color?, hierarchical?: bool, hasVariants?: bool, abstract?: bool}` | 201 + Location → utworzony ObjectType | `ROLE_ADMIN` + feature flag `pim.catalog.enable_custom_object_types=true` | — |
| **PATCH** (NEW) | `/api/object_types/{id}` | `{label?, icon?, color?, hierarchical?, hasVariants?, abstract?, allowedParentTypes?: [id,...], completenessRules?}` | 200 + zaktualizowany ObjectType | `ROLE_ADMIN` + voter `EDIT` (built-in: tylko `icon`, `color`, `label`; custom: pełen zakres) | — |
| **DELETE** (NEW) | `/api/object_types/{id}` | — | 204 No Content | `ROLE_ADMIN` + voter `DELETE` (built-in: zawsze 403; custom: 409 jeśli `instancesCount > 0`) | — |
| **POST** (NEW) | `/api/object_types/{id}/duplicate` | `{newCode, newLabel{pl,en}}` | 201 + Location → nowy custom ObjectType skopiowany | `ROLE_ADMIN` + feature flag | — |
| **POST/DELETE** (NEW) | `/api/object_types/{id}/groups/{groupId}` | — (POST=attach, DELETE=detach) | 204 | `ROLE_ADMIN` + voter | — |
| **GET** (NEW) | `/api/object_types/{id}/audit_log` | `?limit=5` | `[{id, action, actorId, actorName, occurredAt, diffJson, schemaRev}]` | `ROLE_ADMIN` | `limit` 1–50, default 10 |
| GET | `/api/object_types/{id}/usage` | — | bez zmian (już istnieje) — `{instanceCount, attributesAttachedCount, attributeGroupsAttachedCount, referencedByApiProfileCount, referencedByCategoryAttachmentCount}` | `IS_AUTHENTICATED_FULLY` | — |
| **GET** (NEW) | `/api/workspaces/current` | — | `{id, code, name, plan, enabledLocales: ['pl','en'], primaryLocale: 'pl'}` | `IS_AUTHENTICATED_FULLY` | — |
| **POST** (NEW) | `/api/workspaces/current/locales` | `{locale: 'de'}` | 201 + zaktualizowane `enabledLocales` | `ROLE_ADMIN` + walidacja `locale` jest w `LOCALE_LIBRARY` (server-side allowlist) | — |
| **DELETE** (NEW) | `/api/workspaces/current/locales/{locale}` | — | 204 (lub 409 gdy `locale === primary` lub gdy istnieją obiekty z wartościami w tym locale) | `ROLE_ADMIN` | — |
| **PATCH** (NEW) | `/api/workspaces/current` | `{primaryLocale?: 'en'}` | 200 + workspace | `ROLE_ADMIN` + walidacja że nowy primary jest w `enabledLocales` | — |

**Wszystko przez API Platform** dla GET (collection + item) i PATCH/DELETE — definicja w `ObjectType.xml`. PATCH state processor + DELETE state processor (analogicznie do `AttributeGroupProcessor.php`). Endpointy custom (`/duplicate`, `/groups/{groupId}`, `/audit_log`) zostają jako custom controllers (w `Catalog/Presentation/Controller/`), bo wykraczają poza CRUD.

**Cursor pagination N/A** — kolekcja ObjectTypes jest mała (max ~50 per tenant), brak paginacji w MVP. Acceptance: jeśli per tenant > 50 typów → osobny ticket pagination.

**Errors w RFC 7807**: `type=urn:pim:errors:built-in-object-type-protected` dla 403 na delete built-in, `type=urn:pim:errors:object-type-has-instances` dla 409 na delete z instancjami, `type=urn:pim:errors:custom-object-types-disabled` dla 403 z disabled flag, `type=urn:pim:errors:object-type-code-conflict` dla 409 na duplikat code.

### 4.2 Encje / schema / migracje

#### Modyfikacje istniejących:

**`ObjectType` encja** (`apps/api/src/Catalog/Domain/Entity/ObjectType.php`) — dodać pola:

```php
private bool $hierarchical = false;
private bool $hasVariants = false;
private bool $abstract = false;

/** @var list<Uuid> */
private array $allowedParentTypeIds = [];
```

+ gettery/settery (touch() przy zmianie). `allowedParentTypeIds` jako JSONB (lista UUID), zamiast junction — w MVP małe N (~5 parent types max), nie warto osobnej tabeli.

**Migracja Doctrine** (NEW, w `apps/api/migrations/`):

```sql
-- ObjectType: nowe pola settings
ALTER TABLE object_types
  ADD COLUMN hierarchical BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN has_variants BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN abstract BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN allowed_parent_type_ids JSONB NOT NULL DEFAULT '[]'::jsonb;

UPDATE object_types SET has_variants = TRUE WHERE kind = 'product';
UPDATE object_types SET hierarchical = TRUE WHERE kind = 'category';

CREATE INDEX idx_object_types_kind ON object_types (tenant_id, kind);
CREATE INDEX idx_object_types_built_in ON object_types (tenant_id, is_built_in);

-- Tenant: enabled_locales + primary_locale
ALTER TABLE tenants
  ADD COLUMN enabled_locales JSONB NOT NULL DEFAULT '["pl","en"]'::jsonb,
  ADD COLUMN primary_locale VARCHAR(8) NOT NULL DEFAULT 'pl';

-- CHECK constraint: primary_locale musi być w enabled_locales
ALTER TABLE tenants
  ADD CONSTRAINT chk_primary_locale_in_enabled
  CHECK (enabled_locales @> to_jsonb(primary_locale));
```

**Expand-contract**: tu nie potrzeba — dodajemy kolumny z `DEFAULT`, brak destruktywnych operacji. Migracja w jednym kroku OK (CLAUDE.md tolerance: <10k rekordów dla in-place; ObjectTypes max ~50 per tenant).

**`BuiltInObjectTypeSeeder`** (`apps/api/src/Catalog/Application/BuiltInObjectTypeSeeder.php`) — zaktualizować żeby ustawić `hasVariants=true` dla product i `hierarchical=true` dla category w nowo seedowanych workspace.

**`Tenant`** (`apps/api/src/Shared/Domain/Tenant.php`) — dodać pola:

```php
/** @var list<string> */
private array $enabledLocales = ['pl', 'en'];
private string $primaryLocale = 'pl';

public function getEnabledLocales(): array { return $this->enabledLocales; }
public function getPrimaryLocale(): string { return $this->primaryLocale; }

public function enableLocale(string $locale): void
{
    if (!in_array($locale, LocaleLibrary::CODES, true)) {
        throw new InvalidLocaleException($locale);
    }
    if (in_array($locale, $this->enabledLocales, true)) {
        return; // idempotent
    }
    $this->enabledLocales[] = $locale;
}

public function disableLocale(string $locale): void
{
    if ($locale === $this->primaryLocale) {
        throw new CannotDisablePrimaryLocaleException($locale);
    }
    $this->enabledLocales = array_values(array_filter(
        $this->enabledLocales,
        fn (string $l): bool => $l !== $locale
    ));
}

public function changePrimaryLocale(string $locale): void
{
    if (!in_array($locale, $this->enabledLocales, true)) {
        throw new LocaleNotEnabledException($locale);
    }
    $this->primaryLocale = $locale;
}
```

**`LocaleLibrary`** (`apps/api/src/Shared/Domain/LocaleLibrary.php`) — NEW class z constem `CODES = ['pl','en','de','fr','it','es','pt','nl','cs','sk','ru','uk','hu','ro']`. Single source of truth zarówno dla BE walidacji jak i dla FE — generujemy TS const z OpenAPI lub po prostu duplikujemy w `apps/admin/src/lib/locales.ts` z testem zgodności.

**Mapping XML** (`apps/api/src/Shared/Infrastructure/Doctrine/Orm/Mapping/Tenant.orm.xml`) — dodać:

```xml
<field name="enabledLocales" type="json" column="enabled_locales" nullable="false" />
<field name="primaryLocale" type="string" column="primary_locale" length="8" nullable="false" />
```

#### Nowe encje:

`AuditLogEntry` — **OPCJONALNIE** w VIEW-01. Jeśli dh_auditor (widzę w `config/packages/dh_auditor.yaml`) już loguje zmiany ObjectType, użyć jego query API. Jeśli nie — odłożyć audit log endpoint na follow-up ticket VIEW-01.1, w VIEW-01 zostawić mock-empty na FE z polem `auditLogPreview: []` w response.

**Decyzja**: Sprawdzić w trakcie implementacji `dh_auditor` config, jeśli ObjectType jest auditowane → wystawić `/api/object_types/{id}/audit_log` jako adapter na dh_auditor query. Jeśli nie → audited dodać w `dh_auditor.yaml` i wystawić endpoint.

#### ADR

**ADR-aktualizacja wymagana**: ADR-009 sekcja „ObjectType schema fields" — dodać `hierarchical`, `hasVariants`, `abstract`, `allowedParentTypeIds` do listy pól. Krótka justyfikacja: pixel-perfect Modelowanie wymaga eksponowania tych settings użytkownikowi; dotychczas były domyślnie hard-coded per `kind`.

Plik: `Project Plan/01-architektura-pim.md` sekcja 13 (ADR list) — dopisać ADR-013 lub adnotować w ADR-009 update note.

### 4.3 Listenery / event subscribers

- **`TenantAssignmentListener`** — już aktywny dla ObjectType (`TenantScoped` interface). Nie ruszać.
- **`ObjectFormSchemaCacheInvalidator`** — istniejący listener czyści cache `pim_modeling_cache` przy zmianach schemy. Sprawdzić czy obejmuje PATCH/DELETE ObjectType + attach/detach AttributeGroup. Jeśli nie — dodać hook w state processor PATCH/DELETE żeby invalidować tag `pim_usage.object_type.{id}`.
- **`ObjectTypeChangedListener`** — **NOWY** subscriber na Doctrine `postUpdate` ObjectType: bumpuje `schema_version` o 1 gdy zmieniono settings (hierarchical/hasVariants/abstract/allowedParentTypes/completenessRules) — pole `label`, `icon`, `color` nie bumpuje. Logika: porównanie `getOriginalEntityData()` vs aktualna entity przed flush.
- **`ObjectTypeDeletedSubscriber`** — **NOWY**: przed remove sprawdza `instancesCount > 0` → throw `ObjectTypeHasInstancesException` (custom domain exception, mapowane na 409 RFC 7807).

### 4.4 Permissions / RBAC

- **Voter `ObjectTypeVoter`** — **NOWY** w `apps/api/src/Catalog/Infrastructure/Security/`:
  - `READ` — wszyscy `ROLE_ADMIN` w tym samym tenant.
  - `EDIT` — built-in: tylko pola `icon`, `color`, `label`; custom: pełen zakres (sprawdzać payload PATCH per pole).
  - `DELETE` — built-in: ZAWSZE deny (`BuiltInObjectTypeException`); custom: deny gdy `instancesCount > 0`, allow w przeciwnym wypadku.
  - `ATTACH_GROUP` / `DETACH_GROUP` — built-in groups (`system=true`) zawsze deny detach; allow attach/detach custom groups dla `ROLE_ADMIN`.
- **Endpoint security** w `ObjectType.xml`:
  ```xml
  <operation class="ApiPlatform\Metadata\Patch" security="is_granted('EDIT', object)" />
  <operation class="ApiPlatform\Metadata\Delete" security="is_granted('DELETE', object)" />
  ```
- **Audit log entry** — każda mutacja PATCH/DELETE/POST attach/detach pisze do `audit_log` (lub tabela dh_auditor) z `(actor_id, action, entity=ObjectType, entity_id, diff_json, schema_rev)`. Test integracyjny weryfikuje że entry powstał.

### 4.5 Provenance

N/A dla VIEW-01 — ObjectType nie pisze do `object_values`. Provenance dotyczy tylko obiektów (instancji), nie schemy.

### 4.6 Worker / async

N/A dla VIEW-01 — wszystkie operacje synchroniczne. `schema_version` bump jest w listenerze przy save (synchronously w transakcji). Cache invalidation jest synchroniczny przez `TagAwareCache`.

**WYJĄTEK**: jeśli przyszłość przyniesie tysiące custom ObjectTypes per tenant (Faza 2/3), endpoint listy może wymagać paginacji. Notatka w lessons.md.

### 4.7 Real-time (Mercure)

N/A dla VIEW-01 — schemy domenowe nie są real-time critical. Multi-user collab nad modelowaniem to feature dla Fazy 2.

## 5. Sub-tasks (checklist)

### Backend

- [ ] Migracja Doctrine: dodać `hierarchical`, `has_variants`, `abstract`, `allowed_parent_type_ids` na `object_types`, indeksy `idx_object_types_kind` + `idx_object_types_built_in`, backfill dla built-in seedów.
- [ ] Encja `ObjectType`: dodać pola + gettery/settery (`isHierarchical`, `setHierarchical`, etc.).
- [ ] Aktualizacja `BuiltInObjectTypeSeeder` — `hasVariants=true` dla product, `hierarchical=true` dla category.
- [ ] ApiResource XML: dodać operations `Patch`, `Delete`; rozszerzyć normalization o nowe pola; security expression per operation.
- [ ] State processor `ObjectTypePatchProcessor` — invalidacja cache + listener `schema_version` bump + audit log entry.
- [ ] State processor `ObjectTypeDeleteProcessor` — guard `instancesCount > 0` + guard `built_in` + cascade detach junctions.
- [ ] Voter `ObjectTypeVoter` — READ/EDIT/DELETE/ATTACH_GROUP/DETACH_GROUP.
- [ ] Listener `ObjectTypeSchemaVersionBumper` — `postUpdate` z porównaniem origin data.
- [ ] Custom controller `DuplicateObjectTypeController` — `POST /api/object_types/{id}/duplicate`.
- [ ] Custom controller `AttachAttributeGroupController` — `POST/DELETE /api/object_types/{id}/groups/{groupId}`.
- [ ] Custom controller `ObjectTypeAuditLogController` — `GET /api/object_types/{id}/audit_log` (adapter na dh_auditor).
- [ ] Update `CreateCustomObjectTypeController` żeby przyjmował `icon`, `color`, `hierarchical`, `hasVariants`, `abstract` w payload.
- [ ] Doctrine query w `ObjectTypeRepository` zwracający też `instancesCount` (lewy join na `objects` z `count(*)` lub denormalizacja w `usage_query` cache).
- [ ] **Locales workspace**: migracja `tenants.enabled_locales` + `primary_locale` + CHECK constraint.
- [ ] **Locales workspace**: encja `Tenant` rozszerzona o `enabledLocales`/`primaryLocale` + metody `enableLocale`/`disableLocale`/`changePrimaryLocale` + custom exceptions (`InvalidLocaleException`, `CannotDisablePrimaryLocaleException`, `LocaleNotEnabledException`, `LocaleHasObjectValuesException`).
- [ ] **Locales workspace**: klasa `LocaleLibrary` w `Shared/Domain/` z 14 kodami.
- [ ] **Locales workspace**: custom controller `WorkspaceController` z `GET /api/workspaces/current`, `POST /locales`, `DELETE /locales/{locale}`, `PATCH /` (primary).
- [ ] **Locales workspace**: query do sprawdzenia czy locale jest używany w `object_values` przed DELETE (DBAL count na `object_values WHERE locale = :locale`).
- [ ] **Locales workspace**: ApiTestCase: GET workspace 200, POST add 201 (idempotent przy duplikacie), POST 400 (locale spoza library), DELETE 204, DELETE 409 (primary), DELETE 409 (są wartości w tym locale), PATCH primary 200, PATCH 400 (primary nie w enabled).
- [ ] Custom domain exceptions: `ObjectTypeHasInstancesException`, `ObjectTypeCodeConflictException`.
- [ ] Mapowanie wyjątków → RFC 7807 w `ProblemNormalizer` (jeśli istnieje, sprawdzić — jeśli nie, dodać).
- [ ] PHPUnit unit testy: `ObjectType` getter/setter coverage, `ObjectTypeService::create/duplicate/delete` invariants, `ObjectTypeVoter` macierz ról × operacji.
- [ ] PHPUnit integration testy: `ObjectTypeSchemaVersionBumper` (zmiana settings → bump; zmiana label → no bump).
- [ ] ApiTestCase: GET list 200 (built-in + custom split), GET item 200, POST 201 z full payload, POST 403 gdy feature flag off, PATCH 200 (dla custom — pełne pola), PATCH 200 (dla built-in — tylko icon/color/label), PATCH 403 (dla built-in — próba zmiany code lub settings), DELETE 204 (custom bez instancji), DELETE 409 (custom z instancjami), DELETE 403 (built-in), POST `/duplicate` 201, POST `/groups/{groupId}` 204, audit_log 200.
- [ ] ApiTestCase: cross-tenant izolacja — tenant A nie widzi/edytuje ObjectType tenant B (404 lub 403).
- [ ] OpenAPI snapshot: regenerować `docs/api-spec/v{version}.json` po zmianach.

### Frontend

- [ ] Trasa `/modeling/object-types/new` w router (`apps/admin/src/lib/router.tsx` lub ekwiwalent).
- [ ] Komponent `<ObjectTypeIcon>` w `components/modeling/`.
- [ ] Komponent `<LocaleTabsField>` w `components/modeling/` (PL/EN tabs + +Dodaj język).
- [ ] Komponent `<FieldDisplay>` w `components/modeling/`.
- [ ] Komponent `<GroupCard>` w `components/modeling/` (chipy atrybutów, edit/remove buttons).
- [ ] Komponent `<SettingToggleRow>` w `components/modeling/` z `role="switch"`.
- [ ] Komponent `<StatBox>` w `components/modeling/`.
- [ ] Komponent `<DangerZoneCard>` w `components/modeling/` z AlertDialog confirm.
- [ ] Komponent `<IconPicker>` w `components/modeling/`.
- [ ] Komponent `<ColorPicker>` w `components/modeling/`.
- [ ] Komponent `<ObjectTypeWizard>` w `components/modeling/` — 4-step inline.
- [ ] Komponent `<AuditTrailCompact>` w `components/modeling/` — 5 ostatnich entries.
- [ ] Przebudowa `ObjectTypesListPage` — zachowane sekcje built-in/custom, dodane prawdziwe badges hierarchical/variants z BE, CTA do `/new`.
- [ ] Przebudowa `ObjectTypeShowPage` — pixel-perfect 9 sekcji w stałej kolejności (3.4).
- [ ] Usunięcie `CreateCustomObjectTypeDialog` (przeniesione do wizard route).
- [ ] Hook `useObjectTypeMutations` — wrapper na `useUpdate`, `useDelete`, `useDuplicate` z odpowiednimi invalidacjami.
- [ ] i18n keys w `pl.json` i `en.json` (sekcja 3.5).
- [ ] Skeleton states na liście i detailu (axe-core friendly z `role="status"`).
- [ ] Toast errors mapped do RFC 7807 codes (sekcja 4.1).
- [ ] Refine resource config: dodać `update`, `deleteOne`, `create` actions dla `object_types` + nowy resource `workspaces`.
- [ ] **Locales FE**: stała `LOCALE_LIBRARY` w `apps/admin/src/lib/locales.ts` (14 entries) + test zgodności z BE `LocaleLibrary::CODES`.
- [ ] **Locales FE**: hook `useEnabledLocales()` (wraps `useOne({resource: 'workspaces', id: 'current'})`).
- [ ] **Locales FE**: komponent `<LocaleAddDialog>` (mały modal 400px, jedyny popup w VIEW-01).
- [ ] **Locales FE**: rozszerzenie `<LocaleTabsField>` o prop `enabledLocales` + przycisk „+ Dodaj język" otwierający `<LocaleAddDialog>` + auto-refresh po POST.
- [ ] **Locales FE**: i18n keys dla `LocaleAddDialog` (tytuł „Dodaj język", lista locali, „Anuluj" / „Dodaj").
- [ ] **Locales FE**: error handling — gdy POST 400 (invalid locale) toast error; gdy 403 (brak ROLE_ADMIN) toast „Brak uprawnień do zmiany locali".

### E2E + integration

- [ ] Playwright `apps/admin/tests/modeling-object-types.spec.ts`:
  - `displays built-in and custom sections with correct counts`
  - `navigates to detail and shows all 9 sections in order`
  - `built-in object type shows lock badges and disables settings toggles`
  - `custom object type allows editing label, icon, color`
  - `wizard creates new ObjectType through 4 steps with validation`
  - `wizard rejects duplicate code with conflict message`
  - `danger zone shows safe message when instances=0 and confirms delete`
  - `danger zone shows blocked message when instances>0`
  - `axe-core scan returns 0 serious/critical violations on each route`
  - `clicks "+ Dodaj język" → LocaleAddDialog opens → selecting "de" calls POST /workspaces/current/locales → de tab appears in LocaleTabsField`
  - `cannot add locale already enabled (de filtered out from dialog list after add)`
- [ ] Visual regression: porównanie screenshotu detailu Produkty vs mockup (Playwright `toHaveScreenshot` z tolerancją <2% pixel mismatch). Baseline screenshot z Figma/prototypu.
- [ ] Seed dataset: dev DB ma seedowane 4 built-in + 3 custom ObjectTypes (po `console doctrine:fixtures:load`) + faktyczne `instancesCount` (50k SKU dla product, 184 dla category, etc. — z `DemoCatalogSeeder`).

### Testy non-functional

- [ ] k6 / `pgbench` na `GET /api/object_types` — p95 < 100ms (mała kolekcja).
- [ ] k6 na `GET /api/object_types/{id}` — p95 < 200ms (z attached groups + audit preview join).
- [ ] EXPLAIN ANALYZE każdego query w PR description — zero seq scan na `object_types`/`object_type_attribute_groups`/`audit_log`.
- [ ] Vite build report: bundle Δ <50KB gzip (komponenty modeling są lazy-loadowane).
- [ ] Lighthouse CI run na `/modeling/object-types` i `/modeling/object-types/{id}`: performance ≥85, a11y =100, best-practices ≥90.

### Dokumentacja

- [ ] Update `agent/current_status.md` — VIEW-01 in progress / done.
- [ ] Update `agent/lessons.md` — pattern „view-first ticket: lista + detail + wizard razem" jeśli odkryje coś non-obvious.
- [ ] Update `Project Plan/01-architektura-pim.md` — ADR-009 update note o nowych polach.
- [ ] Update `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md` — checkbox „VIEW-01 done".

### Manual smoke (operator)

- [ ] Operator zaloguje się jako `admin@demo.localhost / changeme`.
- [ ] Przejdzie do `/modeling/object-types`, zobaczy 4 built-in + 3 custom.
- [ ] Wejdzie w detail Produkty — zobaczy wszystkie 9 sekcji jak na mockupie.
- [ ] Wejdzie w detail Usługi (custom) — zobaczy Danger zone.
- [ ] Edytuje icon Produkty (built-in pozwala) — sprawdzi że zapisał się.
- [ ] Spróbuje edytować code Produkty — disabled (lock).
- [ ] Stworzy nowy custom „Test" przez wizard — zobaczy w sekcji custom.
- [ ] Spróbuje usunąć Test (instances=0) — confirm dialog → 204 → zniknął z listy.
- [ ] Otworzy DevTools Network — wszystkie XHR 2xx.
- [ ] DevTools Console — brak czerwonych errorów.

## 6. Acceptance criteria — funkcjonalne

- [ ] Widok listy `/modeling/object-types` wyświetla dwie sekcje: „Built-in (system)" (4 typy: Produkty, Kategorie, Zasoby, Marki) i „Custom (your organization)" (3 typy: Usługi, Lokalizacje, Subskrypcje) — pixel-perfect zgodnie z mockupem `Modelowanie.html` po renderze.
- [ ] Każdy row pokazuje: ikonę z tłem `color + 18`, nazwę PL, mono code pod spodem, badges (system/hierarchical/variants), kolumnę „N grup atrybutów" z faktyczną liczbą z BE, kolumnę „N instancji" z faktyczną liczbą z `/usage`, chevron-right na hover.
- [ ] CTA „+ Nowy typ" (header) i wielki CTA „+ Stwórz nowy ObjectType…" (pod custom sekcją) prowadzą do `/modeling/object-types/new`.
- [ ] Detail `/modeling/object-types/{id}` renderuje wszystkie 9 sekcji w kolejności (3.4) — pixel-perfect zgodnie z screenshotem detailu Produkty.
- [ ] Built-in ObjectType (Produkty): pole Code zablokowane (lock icon, brak edit), pole Tenant zablokowane, settings toggles zablokowane (opacity-60, brak click), brak Danger zone.
- [ ] Custom ObjectType (Usługi/Lokalizacje/Subskrypcje): wszystkie pola edytowalne, settings toggles aktywne, Danger zone widoczna.
- [ ] Wizard `/modeling/object-types/new` — 4 kroki w kolejności (Identyfikacja → Atrybuty → Ustawienia → Podsumowanie), step indicator klikalny (cofa do wcześniejszych kroków), sidebar z live preview + tips.
- [ ] Wizard krok 1: nazwa PL/EN przez LocaleTabsField (PL primary), code auto-snake_case, IconPicker (8 emoji), ColorPicker (7 kolorów).
- [ ] Wizard krok 2: lista 3 built-in groups z lock badge + przycisk dashed „+ Dodaj grupę atrybutów" (placeholder w VIEW-01, faktyczna implementacja w VIEW-03).
- [ ] Wizard krok 3: 3 toggle rows (hierarchical / hasVariants / abstract) bez locka.
- [ ] Wizard krok 4: podsumowanie + przycisk „Utwórz typ" → POST `/api/object_types` → 201 → push na `/modeling/object-types/{newId}`.
- [ ] Inline edit pól na detailu (PL/EN nazwa, icon, color, settings toggles dla custom): klik pencil → input w miejscu → blur lub Enter zapisuje (PATCH) → toast „Zapisano" → bump `schema rev` w stopce (jeśli zmiana settings).
- [ ] Danger zone delete: przycisk „Usuń" disabled gdy `instancesCount > 0` (z tooltipem powodu); klik gdy aktywny → AlertDialog confirm → DELETE → toast „Usunięto" → push na listę.
- [ ] Footer pokazuje aktualny `schema rev` z BE (response GET item zawiera `schemaVersion`).
- [ ] AuditLogIndicator w prawym górnym rogu pokazuje ostatnią datę zmiany (z `auditLogPreview[0].occurredAt` lub `updatedAt`).
- [ ] Wszystkie interakcje z mockupu działają end-to-end (klik → BE response → visible result).
- [ ] Empty/loading/error states zaobserwowalne (test manual + Playwright).
- [ ] i18n PL/EN przełącza się dla wszystkich nowych stringów (sprawdzenie w UI: /settings/locale = en).
- [ ] **Locales: dodanie języka** — w detailu ObjectType, klik „+ Dodaj język" w `<LocaleTabsField>` otwiera mały modal 400px ze listą locali z `LOCALE_LIBRARY` (odfiltrowane już aktywne). Wybór `de` → POST `/api/workspaces/current/locales` → 201 → tab `🇩🇪 DE` pojawia się w LocaleTabsField z pustym inputem do wpisania niemieckiej nazwy.
- [ ] **Locales: persistence** — po dodaniu `de`, refresh strony i wejście w detail innego ObjectType — tab `de` jest widoczny we wszystkich `<LocaleTabsField>` (bo to settings workspace, nie per-ObjectType).
- [ ] **Locales: walidacja** — próba POST z `locale=zz` (spoza library) → 400 RFC 7807 + toast error.
- [ ] **Locales: primary lock** — w VIEW-01 user nie usuwa locali z UI (tylko dodaje). DELETE i PATCH primary są w API (testy ApiTestCase), ale FE-side używane będą w VIEW-99 Settings.

## 7. Acceptance criteria — non-functional (TWARDE GATES)

- [ ] **Performance**: `GET /api/object_types` p95 < 100ms (k6 raport w PR), `GET /api/object_types/{id}` p95 < 200ms (z attached groups + audit join), `PATCH /api/object_types/{id}` p95 < 250ms.
- [ ] **N+1 query check**: EXPLAIN ANALYZE w PR dla każdego nowego query: GET list, GET item, GET usage, attach group. Zero N+1 (joiny eager via Doctrine `addSelect`).
- [ ] **Indeksy**: `idx_object_types_kind (tenant_id, kind)`, `idx_object_types_built_in (tenant_id, is_built_in)` aplikowane przez migrację.
- [ ] **Pagination**: N/A dla ObjectTypes (max ~50 per tenant).
- [ ] **Memory**: `flush()` w state processorach bez `clear()` jest OK (single entity update). N/A dla worker mode w VIEW-01.
- [ ] **Bundle size FE**: Δ rozmiaru bundle <50KB gzip (Vite build report w PR; nowe komponenty modeling lazy-loadowane przez `React.lazy`).
- [ ] **Lighthouse** (`/modeling/object-types`, `/modeling/object-types/{id}`, `/modeling/object-types/new`): performance ≥85, a11y =100, best-practices ≥90.
- [ ] **PHPStan max**: 0 errors w `apps/api/src/Catalog/`.
- [ ] **Biome strict**: 0 errors w `apps/admin/src/`.
- [ ] **PHPUnit coverage**: ≥80% nowej logiki w `Catalog/Application/` i `Catalog/Domain/Entity/ObjectType.php` setterów.
- [ ] **ApiTestCase**: każdy nowy endpoint ma test 401 (no auth) + 403 (wrong role) + 404 (wrong id) + 409/400 (walidacja payload) + 200/201/204 happy path.
- [ ] **Playwright E2E**: 9 scenariuszy z sekcji „E2E + integration" zielonych.
- [ ] **axe-core**: 0 violations serious/critical na każdej z 3 sub-tras (`/list`, `/detail`, `/new`).
- [ ] **composer audit + pnpm audit**: 0 high/critical.
- [ ] **Multi-tenancy**: ApiTestCase `crossTenantIsolation` — tenant A nie widzi/edytuje ObjectType tenant B (404 dla GET, 404 dla PATCH/DELETE; nie 403, bo RLS udaje że nie istnieje).
- [ ] **RBAC**: `ObjectTypeVoter` test macierzy: 5 ról × 5 operacji × 2 typy (built-in vs custom).
- [ ] **Audit log**: ApiTestCase weryfikuje że PATCH/DELETE/POST attach pisze entry w `audit_log` (lub dh_auditor) z `(actor_id, action, entity_id, diff_json, schema_rev)`.
- [ ] **Provenance**: N/A.
- [ ] **i18n coverage**: wszystkie nowe klucze obecne w `pl.json` i `en.json`. CI lint sprawdza brak literałów w nowych komponentach.
- [ ] **OpenAPI snapshot**: `docs/api-spec/v{version}.json` zaktualizowany; diff w PR review.

## 8. Smoke-test scenariusze (manualne, dla operatora)

> **Przed**: `pnpm stack:up` lokalnie LUB testowanie po merge na `https://pim.localhost`.

1. **Login**: otwórz `https://pim.localhost`, zaloguj się jako `admin@demo.localhost` / `changeme`.
2. **Nawigacja do listy**: w sidebarze klik „Modelowanie" → tab „Object Types". Oczekiwany: widok listy z 4 built-in (Produkty, Kategorie, Zasoby, Marki) + 3 custom (Usługi, Lokalizacje, Subskrypcje).
3. **Sprawdzenie response status w DevTools Network**: `GET /api/object_types` = 200, `GET /api/object_types/{id}/usage` = 200 dla każdego typu.
4. **Wejście w detail Produkty**: klik na row Produkty. Oczekiwany URL: `/modeling/object-types/{id}`. Oczekiwany: 9 sekcji w kolejności jak na screenshoot.
5. **Sprawdzenie pól zablokowanych**: Code = lock icon, Tenant = lock icon, settings toggles = disabled, brak Danger zone.
6. **Edycja icon Produkty (allowed dla built-in)**: klik pencil przy Icon → IconPicker → wybierz inną emoji → zapis. Oczekiwany: `PATCH /api/object_types/{id}` 200, toast „Zapisano", icon zaktualizowany w UI.
7. **Próba edycji code (disabled)**: klik pencil przy Code — powinien być niedostępny (lock).
8. **Wejście w detail Usługi (custom)**: oczekiwany pełen edit + Danger zone z opisem „Niemożliwe — 24 instancji istnieje" (button disabled).
9. **Stworzenie nowego custom „Test"**: klik „+ Nowy typ" w headerze listy → wizard otwiera się na `/modeling/object-types/new`. Krok 1: name PL=„Test", code=„test", wybierz emoji + kolor → Dalej. Krok 2: zobacz lock built-in groups → Dalej. Krok 3: wszystkie toggles off → Dalej. Krok 4: zobacz podsumowanie → klik „Utwórz typ". Oczekiwany: `POST /api/object_types` 201, push na `/modeling/object-types/{newId}`, w stopce „schema rev 1".
10. **Powrót do listy**: kliknij „Wstecz". Oczekiwany: widzisz „Test" w sekcji Custom.
11. **Usunięcie Test**: wejdź w detail Test (instances=0 → button „Usuń" aktywny) → klik „Usuń" → AlertDialog → potwierdź. Oczekiwany: `DELETE /api/object_types/{id}` 204, toast, push na `/modeling/object-types`, brak Test w liście.
12. **Sprawdzenie 2-tenant izolacji** (jeśli env demo ma 2 tenanty): zaloguj się na tenant B → `/modeling/object-types` → brak Test (utworzonego przez A) i brak custom typów A.
13. **DevTools Console**: brak czerwonych errorów w trakcie całego flow (warningi React StrictMode OK).
14. **Zmiana lokali** (`/settings/locale = en`): wszystkie etykiety przełączone na EN, footer „Pim · workspace…" w EN.

## 9. Edge cases / poza zakresem

### Świadomie poza zakresem (deferred):

- **Multi-parent ObjectType (DAG)** — mockup pokazuje „Allowed parent types" jako lista chipów. W VIEW-01 implementujemy jako JSONB list of UUIDs, ale UX dodawania/usuwania parent type to placeholder (przycisk dashed nieaktywny). Faktyczna implementacja UX w VIEW-04 (Categories), gdzie cross-ObjectType references mają więcej sensu.
- **Edycja inline custom attribute groups** (pencil + trash przy `<GroupCard>`) — przyciski są w UI, ale klik prowadzi do detailu grupy w VIEW-03 (read-only do czasu zaimplementowania VIEW-03). Trash placeholder + toast „Funkcja dostępna po VIEW-03".
- **Add attribute group** modal — przycisk widoczny, ale klik otwiera placeholder Sheet z tekstem „Implementacja w VIEW-03". Konkretne wiring po VIEW-03.
- **Schema versioning ADR-012** — w VIEW-01 `schema_version` jest bumpowane przy zmianach settings, ale brak UI „przejdź do poprzedniej wersji". Faza 2.
- **Bulk import CSV ObjectTypes** — mockup nie pokazuje, brak w VIEW-01.
- **Wizard krok 2: faktyczne attach attribute group przy create** — w VIEW-01 wizard tylko sugeruje built-in groups, nie pozwala dodać custom groups inline. Po utworzeniu typu user wraca do detailu i klika „+ Add attribute group" w sekcji Custom (placeholder w VIEW-01).
- **Audit log full timeline** — `<AuditTrailCompact>` pokazuje tylko 5 ostatnich, brak link „Zobacz pełny historię". Full timeline w follow-up VIEW-01.1 / dh_auditor UI.

### Edge cases pokryte:

- Built-in ObjectType bez custom groups — empty state „Brak custom grup. Dodaj pierwszą…".
- Custom ObjectType z 0 instances — Danger zone aktywna.
- Custom ObjectType z >0 instances — Danger zone z disabled button + opis powodu.
- Code conflict przy POST — RFC 7807 409 + highlight pola w wizardzie.
- Feature flag `enable_custom_object_types=false` — POST 403 z komunikatem.
- Cross-tenant attempt — 404.
- Locale missing (np. wszedł EN, ale ObjectType ma tylko PL) — fallback na PL z badge „PL primary".
- Network timeout/error podczas GET item — error boundary z retry button.

### Edge cases zostawione na później:

- Concurrent edit (User A edytuje icon, User B edytuje color) — last-write-wins w MVP, optimistic locking (`If-Match: etag`) follow-up.
- ObjectType z 50k attribute groups (nierealne, hard cap = 100 groups per type w MVP).
- Migracja danych przy zmianie kind (`product` → `service`) — nieobsługiwana, kind jest immutable po utworzeniu.

## 10. Powiązane ADR / dokumenty

- **ADR-009** (`Project Plan/01-architektura-pim.md` sekcja 13) — update note: nowe pola `hierarchical`, `hasVariants`, `abstract`, `allowedParentTypeIds` na encji `ObjectType`.
- **Proponowany ADR-012** — AttributeGroup as first-class. Stan: nie zatwierdzony, ale mockup używa terminologii. W VIEW-01 traktujemy AttributeGroups jako już first-class (nazwa + own attrs), bez naruszania ADR-009.
- **ADR-013** (NEW) — „ObjectType configurable settings exposed via API" — krótki dokument uzasadniający wystawienie `hierarchical`/`hasVariants`/`abstract` jako edytowalnych pól (poprzednio domyślnie po `kind`).
- Aktualizacja `agent/current_status.md`: sub-faza = MVP-Final, epik = UI-03, ticket = VIEW-01.
- Aktualizacja `agent/lessons.md` (po zamknięciu): wzorce „view-first FE+BE w jednym ticket" jeśli wykryje coś non-obvious.
- Aktualizacja `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md`: checkbox „VIEW-01 done", linia z linkiem do PR.
- Aktualizacja `docs/api-spec/v{version}.json` po regeneracji OpenAPI.

---

## Estymacja

- **Backend**: ~14h (migracja 1h, encja+API Resource+state processors 4h, voter 2h, custom controllers 3h, exceptions+RFC 7807 1h, testy 3h).
- **Frontend**: ~16h (komponenty modeling 6h, list + detail rebuild 4h, wizard 3h, i18n + a11y polish 1h, testy Playwright 2h).
- **Audit + dokumentacja**: ~2h (ADR-013, lessons, status update).
- **Razem**: ~32h. Realny rozmiar = duży ticket; jeden PR, jeden coherent diff.

> Po pierwszych 2h analizy/Plan Mode (jeśli okaże się że ADR wymaga decyzji architektonicznej cross-context) — wracamy do operatora po akceptację, zanim zacznie się implementacja.

---

## Branch + PR

- Branch: `feat/view-01-modelowanie-object-types`
- PR title: `feat(modeling): pixel-perfect Object Types view (VIEW-01)`
- PR description: link do tego ticketu + checklist DoD + screenshot przed/po + EXPLAIN ANALYZE + k6 raport + Lighthouse score.

> **Smoke test claim**: PR description NIE używa słowa „działa" / „works" / „ready" przed manual smoke testem operatora. Domyślnie: „komponent shipped, end-to-end smoke wymaga akceptacji operatora".
