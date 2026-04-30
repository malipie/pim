# Epik 08 — Modelowanie

## Status: 🟢 szczegół (in progress)

> **Flagowy epik UI** — definiuje schemat danych całego systemu. Bez Modelowania klient nie zacznie używać PIM-u (każdy klient definiuje minimum 1-2 custom Object Types lub Attribute Groups specyficzne dla swojej branży).

---

## 1. Cel epiku

Zakładka, w której **Adam** (architekt informacji) lub **Marcin (founder, dogfooding)** lub **Kasia (gdy nie ma osobnego Adama)** definiuje:
- **Object Types** — typy obiektów w systemie (Produkt, Kategoria, Zasób, Marka — built-in; Usługa, Subskrypcja, Lokalizacja, dowolny — custom).
- **Attributes** — globalna biblioteka atrybutów reusable across types (`description`, `voltage`, `appointment_duration`).
- **Attribute Groups** ⭐ NEW concept — first-class entity grupująca atrybuty w sekcje formularza, wymienna jednostka przypinana do ObjectType / Category / pojedynczego obiektu.
- **Categories** — drzewo kategorii z deklaracjami Attribute Groups dla obiektów należących do tej kategorii.

Cel UX: *„nie-developer (Adam, ale też Marcin/Kasia w mniejszych firmach) może w 30 minut zdefiniować model danych dla nowej branży (np. usługi medyczne) bez pisania kodu i bez Pimcore-style miesiąca konfiguracji"*.

## 2. Persony

| Persona | Częstość użycia | Co robi |
|---|---|---|
| **Adam, 40** ⭐ NEW | Raz na 1-2 tyg. | Główne use case'y: dodaje nowy Attribute, tworzy nowy Object Type per wymaganiu biznesu, modyfikuje Attribute Groups |
| **Marcin (dogfooding)** | Często w MVP | Definiuje model dla własnego sklepu, eksperymentuje z UX |
| **Kasia, 32** | Rzadko, ale możliwa | Gdy nie ma Adama (mniejsze firmy) — Kasia z konwencją + audit logiem może modyfikować model. **MVP: brak role gating, full access.** |

## 3. Konteksty teoretyczne (z rozmowy źródłowej)

### 3.1 Trzy ortogonalne wymiary, które trzeba rozdzielić

W tradycyjnych PIM (Pimcore, Akeneo) te wymiary się mieszają, co prowadzi do chaosu:

1. **Czym jest obiekt?** (Produkt / Kategoria / Usługa / Asset / cokolwiek innego) → **Object Type**.
2. **Jakie pola/atrybuty go opisują?** (sku, description, voltage, czas trwania, cena) → **Attribute**.
3. **W którym fragmencie taksonomii się znajduje?** (Fryzjer → Strzyżenie / Lekarz → Chirurg → Ortopeda) → **Category** (sama też ObjectType, ale specjalna).

W naszym Modelowaniu te trzy wymiary mają **osobne sub-zakładki** (Tab 1, 2, 4), plus **Attribute Group** (Tab 3) jako klejąca abstrakcja.

### 3.2 Reguła decyzyjna: Object Type vs Category

| Pytanie | Jeśli TAK → | Jeśli NIE → |
|---|---|---|
| Czy to opisuje *rodzaj rzeczy* w świecie (różny model danych)? | **Object Type** | Category |
| Czy to opisuje *gdzie* w taksonomii ta sama rzecz się znajduje? | **Category** | Object Type |

**Praktyka:**
- *„Produkt"* vs *„Usługa"* vs *„Asset"* → różne Object Types (mają różne modele danych).
- *„Fryzjer"* vs *„Lekarz"* → różne kategorie usługi (oba to nadal Usługa).
- *„Chirurg"* vs *„Ortopeda"* → różne podkategorie kategorii Lekarz.

**Test:** czy ten sam fizyczny obiekt zmienia kategorię? Jeśli tak — to Category. Czy zmienia ObjectType? Praktycznie nigdy.

### 3.3 Subtype vs Category — kiedy co

| Różnica modelu danych | Wybór |
|---|---|
| <30% atrybutów się różni | **Category** (jeden ObjectType + kategorie wariujące) |
| 30-70% | Eksperyment, można refaktorować później |
| >70% | **Subtype** (osobne Object Types) |

**Przykład:** usługa lekarska vs fryzjerska to ~50%, *Category* jest dobrym wyborem (oba to `kind=service`). Gdyby doszła *„Service: SaaS subscription"* z zupełnie innym modelem (license keys, billing cycles, MRR) — osobny ObjectType `kind=subscription`.

### 3.4 Built-in vs Custom Object Types

**Built-in** (`is_built_in=true`, `code_immutable=true`, `deletable=false`):
- `product` — fundament PIM-u, integracje go nazywają wprost (`/api/products`, mapping SAP/Edito).
- `category` — hierarchia jako mechanizm domeny, `is_hierarchical=true`, specjalna zdolność deklarowania attribute groups.
- `asset` — DAM ma szczególne potrzeby (metadata EXIF, transformacje, CDN URLs).
- `brand` — marka jako 4-ty predefiniowany ObjectType (decyzja PRD § 4 + ADR-009 update).

**Custom** (`is_built_in=false`):
- `service`, `location`, `event`, `subscription`, `bundle`, `person`, dowolne.
- Pełna kontrola: code, settings (is_hierarchical, has_variants, is_abstract), attribute groups, deletion (jeśli brak instancji).

**Kłódka 🔒 w UI:** built-in fields są *visible* dla wszystkich, ale `code` / fundamentalne flagi są disabled z tooltipem *„System type — limited customization"*.

### 3.5 Attribute Group jako first-class entity (proponowany ADR-012) ⭐ NEW

**To jest abstrakcja, której Pimcore i Akeneo nie mają (lub mają słabo):**
- W Pimcore — grupowanie jest *„doklejone"* do Class Definition.
- W Akeneo — Attribute Group jest tylko sortowaniem UI, bez własnej semantyki.

**U nas — Attribute Group jako pierwszorzędny obywatel:**
- Ma własny URL (`/modeling/attribute-groups/medical-requirements`).
- Jest wymienną jednostką (przeniesienie grupy między ObjectType to jedna operacja).
- Może mieć własny audit, wersjonowanie, kontrolę dostępu (Faza 1+).

**Trzy poziomy przypinania Attribute Group:**

1. **Globalnie do ObjectType** — atrybuty wspólne dla wszystkich obiektów tego typu. *„Każdy Produkt ma name, sku, description"*.
2. **Per kategoria w hierarchii** — atrybuty dziedziczone w dół drzewa kategorii. *„Kategoria Lekarz definiuje grupę Wymagania medyczne, podkategoria Chirurg dziedziczy + dodaje Chirurgia szczegóły, Ortopeda dziedziczy + dodaje Ortopedia"*.
3. **Per indywidualny obiekt** — opcjonalnie ad-hoc dodatkowa grupa do konkretnego produktu (rzadkie, ale potrzebne dla ekstremalnych edge case'ów).

### 3.6 System attributes vs business attributes

System attributes (`is_system=true`):
- `created_at`, `updated_at`, `created_by`, `updated_by`, `id`.
- Automatycznie dodawane do każdego ObjectType jako specjalna grupa **"Audyt"** (`auto_attached=true`).
- Read-only w UI, niemodyfikowalne, niezmienne.
- Pimcore nie ma tego jako atrybutów; Akeneo też nie. **U nas tak — wszystko jest atrybutem.**

### 3.7 Visibility rules (`visible_when`)

Atrybut w grupie może mieć regułę warunkowej widoczności:

```yaml
attribute: nfz_code
visible_when: is_nfz_eligible == true
```

Bez tego użytkownicy się gubią (formularz pokazuje 50 pól, użytkownik nie wie które wypełnić). Akeneo tego nie ma natywnie.

### 3.8 Pełen model encji (delta wobec ADR-009)

```
object_types (id, code, name, is_hierarchical, is_abstract, has_variants, is_built_in, code_immutable, deletable)
attributes (id, code, type, localizable, scopable, is_system, validation_rules, ui_config)
attribute_groups (id, code, name, description, icon, color, is_system_group)  ⭐ NEW
attribute_group_attributes (attribute_group_id, attribute_id, position, is_required_in_group, visible_when_jsonb)
object_type_attribute_groups (object_type_id, attribute_group_id, position)
  ← globalne grupy dla ObjectType
category_attribute_groups (category_object_id, target_object_type_id, attribute_group_id, position)
  ← grupy deklarowane przez kategorię dla obiektów określonego typu
```

Plus istniejące (z ADR-009):
- `objects (id, kind, parent_id, ...)` — polimorficzne, kind = ObjectType code.
- `object_values (object_id, attribute_id, value, locale, channel, provenance)` — EAV.
- `attributes_indexed JSONB` — denormalizowany cache.

---

## 4. Layout zakładki Modelowanie — 4 sub-tabs

```
┌─ Modelowanie ─────────────────────────────────────────┐
│                                                        │
│  ┌─[Object Types]─[Attributes]─[Attribute Groups]─┐   │
│  │                                                   │   │
│  │  [Categories]                                    │   │
│  │                                                   │   │
│  │  ┌──────────────────────────────────────────┐  │   │
│  │  │                                            │  │   │
│  │  │    Active sub-tab content here            │  │   │
│  │  │                                            │  │   │
│  │  │                                            │  │   │
│  │  │                                            │  │   │
│  │  └──────────────────────────────────────────┘  │   │
│  └───────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────┘
```

URL routing:
- `/modeling/object-types` (default landing).
- `/modeling/attributes`.
- `/modeling/attribute-groups`.
- `/modeling/categories` (osobna pozycja w nawigacji wewnętrznej, bo Categories ma też daily-use view dla operatora — *do uzgodnienia czy ten sub-tab wewnątrz Modelowania, czy osobna pozycja w sidebar*).

---

## 5. Sub-tab 1: Object Types

### 5.1 List view

```
┌─ Object Types ─────────────────────────────────────────┐
│                                          [+ New Type]  │
│                                                         │
│  🔒 Built-in (system)                                   │
│  ┌─────────────────────────────────────────────────┐  │
│  │ 📦 Product        | hierarchical: false  | 1247  │  │
│  │ 📂 Category       | hierarchical: TRUE   | 134   │  │
│  │ 🖼  Asset          | hierarchical: false  | 5421  │  │
│  │ 🏷  Brand          | hierarchical: false  | 87    │  │
│  └─────────────────────────────────────────────────┘  │
│                                                         │
│  ✏️  Custom (your organization)                          │
│  ┌─────────────────────────────────────────────────┐  │
│  │ 🏥 Service        | hierarchical: false  | 24    │  │
│  │ 📍 Location       | hierarchical: false  | 5     │  │
│  │ 🔄 Subscription   | hierarchical: false  | 0     │  │
│  └─────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

**Kolumny:**
- Ikona + nazwa wyświetlana.
- `code` (mono font).
- `is_hierarchical` flaga.
- Liczba instancji (`COUNT(*)` z `objects WHERE kind=...`).
- Actions: Edit (Sheet drawer right-side) / Delete (only custom + 0 instances).

**Filtry / search:** tylko dla custom (built-in zazwyczaj 4-5).

### 5.2 Detail view (built-in — Product)

```
┌─ Product ──────────────────────────────────────  🔒 ──┐
│  System type — limited customization                  │
│                                                        │
│  Display name:       [Produkty                  ]  ✏️ │
│  Display name (EN):  [Products                  ]  ✏️ │
│  Code:               product                       🔒 │
│  Icon:               [📦                        ]  ✏️ │
│  Color (badge):      [#3B82F6 ▼                 ]  ✏️ │
│                                                        │
│  ─── Built-in attribute groups ───────── 🔒 ──────    │
│  ┌─ Identification (auto-attached) ───────────────┐  │
│  │  Atrybuty: sku (required), name, slug         🔒 │  │
│  └─────────────────────────────────────────────────┘  │
│  ┌─ Audit (auto-attached) ────────────────────────┐  │
│  │  Atrybuty: created_at, updated_at, created_by │🔒 │  │
│  └─────────────────────────────────────────────────┘  │
│                                                        │
│  ─── Custom attribute groups ──────────────────────   │
│  ┌─ Marketing                              [edit] 🗑 │  │
│  │  Atrybuty: short_description, long_description, │  │
│  │  tags                                            │  │
│  └─────────────────────────────────────────────────┘  │
│  ┌─ Technical specifications              [edit] 🗑 │  │
│  │  Atrybuty: voltage, current, dimensions          │  │
│  └─────────────────────────────────────────────────┘  │
│  [+ Add attribute group]                              │
│                                                        │
│  ─── Settings ──────────────────────────────────────  │
│  ☐ Is hierarchical                              🔒    │
│  ☑ Has variants                                 🔒    │
│  ☐ Is abstract                                  🔒    │
│  Allowed parent types:  Category ✓                    │
│                                                        │
│  Variant axes (jeśli has_variants):                   │
│  [color] × [size]                          [edit]     │
│                                                        │
│  ─── Where used ────────────────────────────────────  │
│  • 1247 instances exist                                │
│  • Used in 12 categories                              │
│  • Referenced by 8 API integrations                   │
└────────────────────────────────────────────────────────┘
```

**Kluczowe elementy:**
- 🔒 wszędzie gdzie pole/grupa jest niemodyfikowalna z tooltipem *„System type — protected"*.
- ✏️ przy polach modyfikowalnych (display name, icon, color, custom groups, allowed parent types).
- *„Built-in attribute groups"* osobno od *„Custom"* — wizualnie jasne co jest fundament a co dodano.

### 5.3 Detail view (custom — Service)

```
┌─ Service ────────────────────────────────── ✏️ ──────┐
│  Custom type — full control                          │
│                                                       │
│  Display name:       [Usługi                    ]   │
│  Display name (EN):  [Services                  ]   │
│  Code:               [service                   ]   │
│  Icon:               [🏥                        ]   │
│  Color:              [#10B981 ▼                 ]   │
│                                                       │
│  ─── Required base attribute groups ─── 🔒 ───────   │
│  (auto-attached for every Object Type)                │
│  ┌─ Identification ──────────────────────────────┐  │
│  │  sku (required), name, slug                  🔒 │  │
│  └────────────────────────────────────────────────┘  │
│  ┌─ Audit ───────────────────────────────────────┐  │
│  │  created_at, updated_at, created_by          🔒 │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ─── Custom attribute groups ──────────────────────  │
│  ┌─ Pricing                              [edit] 🗑 │  │
│  │  base_price, currency, vat_rate                 │  │
│  └────────────────────────────────────────────────┘  │
│  ┌─ Scheduling                           [edit] 🗑 │  │
│  │  appointment_duration, requires_appointment      │  │
│  └────────────────────────────────────────────────┘  │
│  [+ Add attribute group]                              │
│                                                       │
│  ─── Settings ─────────────────────────────────────  │
│  ☐ Is hierarchical                                   │
│  ☐ Has variants                                      │
│  ☐ Is abstract                                       │
│                                                       │
│  Allowed parent types:                                │
│  ☑ Category                                          │
│  ☐ Service (self-referential, np. usługa składowa)   │
│                                                       │
│  ─── Where used ───────────────────────────────────  │
│  • 24 instances exist                                 │
│  • Used in 8 categories (Lekarz, Fryzjer, ...)       │
│                                                       │
│  ─────────────────────────────────────────────────   │
│                                                       │
│  [Delete this Object Type]                            │
│  Note: Disabled because 24 instances exist.           │
│  Migrate or delete instances first.                   │
└───────────────────────────────────────────────────────┘
```

### 5.4 Create new Object Type — wizard

```
Step 1: Basics
  - Display name (PL): [_________]
  - Display name (EN): [_________]
  - Code (auto-generated from PL, editable): [_________]
  - Icon picker: [emoji / Lucide icon]
  - Color: [color picker]

Step 2: Settings
  - Is hierarchical? (jak Category)
  - Has variants? (jak Product)
  - Is abstract? (czy można tworzyć instancje)
  - Allowed parent types (multi-select)

Step 3: Initial attribute groups
  - Auto-attach: Identification, Audit (always)
  - Add existing groups (from library) — multi-select.
  - Or skip and add later.

Step 4: Confirm
  - Preview: jak będzie wyglądał formularz tego typu.
  - Confirm + Create.
```

Po Create — automatycznie pojawia się nowa pozycja w sidebar admin app po refresh (`Sidebar` czyta `object_types` table).

---

## 6. Sub-tab 2: Attributes

### 6.1 List view (global library)

```
┌─ Attributes ─────────────────────────────────────────┐
│  Search: [_____________]  Type: [all ▼]  [+ New]    │
│                                                        │
│  ┌────────────────────────────────────────────────┐  │
│  │ Code              | Type     | Used in (count)  │  │
│  ├────────────────────────────────────────────────┤  │
│  │ 🔒 sku             | text     | 4 types, 1 group  │ │
│  │ 🔒 name            | text     | 4 types, 1 group  │ │
│  │ 🔒 created_at      | datetime | 4 types (system)  │ │
│  │ description       | richtext | 3 types, 2 groups │  │
│  │ voltage           | number   | 1 type, 1 group   │  │
│  │ appointment_dur.. | number   | 1 type (service)  │  │
│  │ ...                                              │  │
│  └────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────┘
```

**Filtry:**
- Type (text, number, select, ...).
- System vs business attributes.
- Localizable / scopable flags.
- Where-used (czy jest używany, w ilu types/groups).

**Bulk actions:**
- Bulk import from CSV (US-MOD-008).
- Bulk archive (atrybuty unused — kandydaci do cleanup'u).

### 6.2 Detail view (Attribute)

```
┌─ Attribute: voltage ─────────────────────────────────┐
│                                                        │
│  Code:                [voltage                  ]    │
│  Display name:        [Napięcie                 ]    │
│  Display name (EN):   [Voltage                  ]    │
│  Type:                [number ▼                 ]    │
│  Unit (jeśli metric): [V                        ]    │
│                                                        │
│  ─── Flags ─────────────────────────────────────────  │
│  ☐ Localizable (per locale)                           │
│  ☑ Scopable (per channel)                             │
│  ☐ Unique                                             │
│                                                        │
│  ─── Validation ────────────────────────────────────  │
│  Min: [0      ]                                       │
│  Max: [10000  ]                                       │
│  Allowed values: (n/a for number)                     │
│                                                        │
│  ─── UI Configuration ──────────────────────────────  │
│  Widget:    [number-with-unit ▼]                     │
│  Placeholder: [np. 230                 ]              │
│  Helper text: [Napięcie znamionowe w V ]              │
│                                                        │
│  ─── Where used ────────────────────────────────────  │
│  Groups:                                               │
│  • Technical specifications                           │
│  • Power consumption                                   │
│                                                        │
│  Object Types:                                         │
│  • Product (via Technical specifications)             │
│                                                        │
│  Categories that declare attribute groups using this:│
│  • Elektronika (via Technical specifications)         │
│                                                        │
│  Total instances with non-null voltage: 247           │
│                                                        │
│  ─── Preview ───────────────────────────────────────  │
│  ┌────────────────────────────────────────────────┐  │
│  │ Napięcie: [230___] V                           │  │
│  │           Napięcie znamionowe w V              │  │
│  └────────────────────────────────────────────────┘  │
│                                                        │
│  ─── Actions ───────────────────────────────────────  │
│  [Edit attribute]  [Migrate type → ...]  [Archive]    │
└────────────────────────────────────────────────────────┘
```

### 6.3 Edit attribute — dangerous changes

Niektóre zmiany na atrybucie wpływają na *wszystkie* obiekty go używające. UI pokazuje *„Migration impact"* przed save:

```
┌─ Migrate attribute "material" from text → select ────┐
│                                                        │
│  Current: text (free-form input)                      │
│  Target:  select (predefined options)                 │
│                                                        │
│  ─── Impact analysis ────────────────────────────────  │
│  • 3,247 produktów ma wartość w tym atrybucie.       │
│  • 47 unikalnych wartości tekstowych:                 │
│    - "stal nierdzewna" (1,820)                        │
│    - "AISI 316L" (892)                                │
│    - "Stal nierdz." (267)  ← prawdopodobnie duplikat │
│    - ... (44 inne)                                    │
│                                                        │
│  ─── Mapping plan ──────────────────────────────────  │
│  Map existing values to select options:               │
│  - "stal nierdzewna" → [Stal nierdzewna ▼]           │
│  - "AISI 316L"       → [AISI 316L      ▼]            │
│  - "Stal nierdz."    → [Stal nierdzewna ▼] (merge)   │
│  - ... [Auto-suggest with AI Faza 2]                 │
│                                                        │
│  Unmapped values (will become NULL):                  │
│  - "stal" (12 produktów)                              │
│  - [skip / map to "other"]                            │
│                                                        │
│  ─── Confirmation ──────────────────────────────────  │
│  ☐ Backup snapshot przed migracją (recommended)       │
│  ☐ Dry-run first (no actual changes)                  │
│                                                        │
│  [Cancel]  [Dry-run]  [Apply Migration]               │
└────────────────────────────────────────────────────────┘
```

To jest **kluczowy enterprise feature** — Akeneo i Pimcore tego nie mają natywnie (klient pisze SQL skrypty ręcznie).

---

## 7. Sub-tab 3: Attribute Groups ⭐ NEW first-class entity

### 7.1 List view

```
┌─ Attribute Groups ───────────────────────────────────┐
│  Search: [_____________]  [+ New Group]              │
│                                                        │
│  ┌────────────────────────────────────────────────┐  │
│  │ Group                  | Attributes | Used in   │  │
│  ├────────────────────────────────────────────────┤  │
│  │ 🔒 Identification       | 3          | 4 types  │  │
│  │ 🔒 Audit                | 5          | 4 types  │  │
│  │ Marketing              | 4          | 1 type   │  │
│  │ Technical specifications| 12        | 1 type   │  │
│  │ Pricing                | 3          | 2 types  │  │
│  │ Wymagania medyczne     | 4          | 1 cat.   │  │
│  │ Refundacja NFZ         | 4          | 1 cat.   │  │
│  │ Chirurgia szczegóły    | 3          | 1 cat.   │  │
│  │ Ortopedia              | 2          | 1 cat.   │  │
│  └────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────┘
```

### 7.2 Detail view (Attribute Group)

```
┌─ Attribute Group: Wymagania medyczne ────────────────┐
│                                                        │
│  Code:           [wymagania-medyczne          ]      │
│  Display name:   [Wymagania medyczne          ]      │
│  Display name EN:[Medical requirements        ]      │
│  Description:    [Atrybuty specyficzne dla    ]      │
│                  [usług medycznych — referrals]      │
│  Icon:           [🏥                          ]      │
│  Color:          [#EF4444 ▼                  ]      │
│                                                        │
│  ─── Attributes in this group ──────────────────────  │
│  (drag-drop to reorder)                               │
│  ┌────────────────────────────────────────────────┐  │
│  │ ☰ requires_referral    ☑ required  visible_when│  │
│  │ ☰ min_age              ☐ required             │  │
│  │ ☰ contraindications   ☐ required             │  │
│  │ ☰ specialist_required  ☐ required             │  │
│  └────────────────────────────────────────────────┘  │
│  [+ Add attribute from library]                       │
│  [+ Create new attribute (in library + add here)]    │
│                                                        │
│  ─── Visibility rules per attribute ────────────────  │
│  contraindications:                                   │
│    visible_when: requires_referral == true            │
│    [Edit rule]                                        │
│                                                        │
│  ─── Where used ────────────────────────────────────  │
│  Used directly by:                                    │
│  • Object Type: (none — only via category)           │
│                                                        │
│  Declared by categories:                              │
│  • Category: Lekarz (target type: Service)            │
│    Inherited by: Chirurg, Ortopeda, Pediatra,        │
│    Internista (4 sub-categories)                      │
│                                                        │
│  Affected objects:                                    │
│  • 17 services in Lekarz tree have this group        │
│                                                        │
│  ─── Preview UI ────────────────────────────────────  │
│  [Pokaż jak ta grupa będzie wyglądać w formularzu]   │
└────────────────────────────────────────────────────────┘
```

### 7.3 Add attribute to group (inline)

```
┌─ Add attribute to "Wymagania medyczne" ──────────────┐
│                                                        │
│  Search library:   [______________]                   │
│                                                        │
│  Existing attributes:                                  │
│  ┌────────────────────────────────────────────────┐  │
│  │ ○ requires_referral   (boolean)                │  │
│  │ ○ min_age             (number)                 │  │
│  │ ● contraindications   (richtext, localizable)  │  │
│  │ ○ specialist_required (boolean)                │  │
│  │ ○ ...                                          │  │
│  └────────────────────────────────────────────────┘  │
│                                                        │
│  Settings for this group:                             │
│  ☐ Required in this group                             │
│  Position in form: [auto / 1 / 2 / 3 ...]            │
│  Visibility rule:  [No rule ▼]                       │
│    [+ Add visible_when condition]                    │
│                                                        │
│  [Cancel]  [Add to group]                             │
│                                                        │
│  Or: [Create new attribute]                          │
└────────────────────────────────────────────────────────┘
```

### 7.4 Visible_when rule editor

```
┌─ Visibility rule: contraindications ─────────────────┐
│                                                        │
│  Show this attribute when:                            │
│                                                        │
│  ┌────────────────────────────────────────────────┐  │
│  │ requires_referral  [equals ▼]  [true     ▼]   │  │
│  └────────────────────────────────────────────────┘  │
│  [+ AND condition]   [+ OR condition]                 │
│                                                        │
│  Otherwise: hidden in form                            │
│                                                        │
│  Test rule:                                           │
│  Input: requires_referral = true   → [VISIBLE]        │
│  Input: requires_referral = false  → [HIDDEN]         │
│                                                        │
│  [Cancel]  [Save rule]                                │
└────────────────────────────────────────────────────────┘
```

---

## 8. Sub-tab 4: Categories (modeling view)

### 8.1 Tree view + attribute groups declaration

```
┌─ Categories (modeling) ──────────────────────────────┐
│                                                        │
│  Object Type filter: [Service ▼]   [+ New category]  │
│                                                        │
│  ┌─ Service (root, all services here) ──────────────┐ │
│  │ ├─ 🏥 Lekarz                                   ▼  │ │
│  │ │  Declared groups (for Service):                │ │
│  │ │   • Cennik medyczny                           │ │
│  │ │   • Wymagania medyczne                        │ │
│  │ │   • Refundacja NFZ                            │ │
│  │ │   [+ Declare group]                           │ │
│  │ │                                                │ │
│  │ │  ├─ Internista                                │ │
│  │ │  │  Declared groups: (none — inherits all)    │ │
│  │ │  │                                             │ │
│  │ │  ├─ 🦴 Chirurg                              ▼  │ │
│  │ │  │  Declared groups:                          │ │
│  │ │  │   • Chirurgia szczegóły                    │ │
│  │ │  │                                             │ │
│  │ │  │  ├─ Chirurg ogólny                         │ │
│  │ │  │  └─ Ortopeda                              ▼ │ │
│  │ │  │     Declared groups:                       │ │
│  │ │  │      • Ortopedia                            │ │
│  │ │  │                                             │ │
│  │ │  └─ Pediatra                                  │ │
│  │ │                                                │ │
│  │ └─ 💇 Fryzjer                                  ▼  │ │
│  │    Declared groups (for Service):               │ │
│  │     • Cennik podstawowy                         │ │
│  │     • Specyfika fryzjerska                      │ │
│  │    ├─ Strzyżenie męskie                         │ │
│  │    ├─ Strzyżenie damskie                        │ │
│  │    └─ Koloryzacja                               │ │
│  └────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────┘
```

### 8.2 Category detail (Ortopeda)

```
┌─ Category: Ortopeda ─────────────────────────────────┐
│                                                        │
│  Code:           [ortopeda                    ]      │
│  Display name:   [Ortopeda                    ]      │
│  Path:           service.lekarz.chirurg.ortopeda     │
│  Parent:         [Chirurg ▼]                         │
│  Sort order:     [3]                                  │
│                                                        │
│  ─── Attribute groups for this category ────────────  │
│  Target Object Type: [Service ▼]                     │
│                                                        │
│  Inherited from parents (read-only):                  │
│  ┌────────────────────────────────────────────────┐  │
│  │ ✓ Cennik medyczny      (from Lekarz)           │  │
│  │ ✓ Wymagania medyczne   (from Lekarz)           │  │
│  │ ✓ Refundacja NFZ       (from Lekarz)           │  │
│  │ ✓ Chirurgia szczegóły  (from Chirurg)          │  │
│  └────────────────────────────────────────────────┘  │
│                                                        │
│  Declared directly:                                   │
│  ┌────────────────────────────────────────────────┐  │
│  │ ✓ Ortopedia                            [edit]🗑│  │
│  └────────────────────────────────────────────────┘  │
│  [+ Declare group]                                    │
│                                                        │
│  ─── Effective preview for object in this category ──│
│  An object of type "Service" placed in "Ortopeda"    │
│  will see these attribute groups:                     │
│                                                        │
│  ┌────────────────────────────────────────────────┐  │
│  │ 🔒 Identification (from Service ObjectType)     │  │
│  │ 🔒 Audit          (from Service ObjectType)     │  │
│  │  Cennik medyczny (inherited from Lekarz)       │  │
│  │  Wymagania medyczne (inherited from Lekarz)    │  │
│  │  Refundacja NFZ  (inherited from Lekarz)       │  │
│  │  Chirurgia szczegóły (inherited from Chirurg)  │  │
│  │  Ortopedia       (declared here)               │  │
│  └────────────────────────────────────────────────┘  │
│                                                        │
│  [+ Create test object in this category]              │
└────────────────────────────────────────────────────────┘
```

To jest **inheritance preview** — Adam widzi co Kasia zobaczy w formularzu *„Stwórz nową usługę → Ortopeda"*. Akeneo tego nie ma; Pimcore tego nie ma. *Killer feature.*

### 8.3 Override / disable inherited group

Czasem kategoria-dziecko chce *wyłączyć* grupę odziedziczoną z rodzica (rzadkie, ale potrzebne):

```
[+ Override inherited group]
  → wybierz grupę: Wymagania medyczne (from Lekarz)
  → akcja: ☑ Hide in this category and descendants
  
  Result: dla obiektów w tej kategorii grupa "Wymagania medyczne" nie pokaże się.
```

### 8.4 Category cycle prevention

System blokuje próby tworzenia cykli (Lekarz → Chirurg → Lekarz). Walidacja przy save w bazie + UI ostrzeżenie z preview drzewa.

---

## 9. User stories

| ID | Persona | Story |
|---|---|---|
| US-MOD-001 | Adam | Tworzy nowy ObjectType "Service" z code, display name, settings (is_hierarchical=false, has_variants=false). Auto-attached: Identification + Audit groups. |
| US-MOD-002 | Adam | Definiuje atrybut `appointment_duration` (number, minutes), dodaje do nowej grupy "Scheduling". |
| US-MOD-003 | Adam | Tworzy Attribute Group "Wymagania medyczne" z 4 atrybutami. Reusable bundle. |
| US-MOD-004 | Adam | Tworzy kategorię "Lekarz" w drzewie Service. Deklaruje że obiekty Service w tej kategorii dostają grupy: "Cennik medyczny", "Wymagania medyczne", "Refundacja NFZ". |
| US-MOD-005 | Adam | Tworzy podkategorię "Chirurg" pod "Lekarz". Deklaruje grupę "Chirurgia szczegóły". Sprawdza inheritance preview — widzi że Chirurg dziedziczy 3 grupy z Lekarz + ma 1 własną. |
| US-MOD-006 | Adam | Tworzy podkategorię "Ortopeda" pod "Chirurg". Deklaruje grupę "Ortopedia". Inheritance preview pokazuje sumarycznie 5 grup (Identification + Audit + 3 z Lekarz + 1 z Chirurg + 1 z Ortopeda). |
| US-MOD-007 | Adam | Klika "+ Create test object in this category" — system tworzy testową usługę w Ortopedzie i otwiera w nowym tabie do wypełnienia. Walidacja działa. |
| US-MOD-008 | Adam | Bulk import 50 atrybutów technicznych z CSV (kable: voltage, current, ampacity, conductor_count, ...). |
| US-MOD-009 | Adam | Migracja atrybutu `material` z text na select. Widzi impact analysis (3247 produktów, 47 unikalnych wartości, sugerowane mapping z auto-merge duplikatów). Robi dry-run, akceptuje, wykonuje. |
| US-MOD-010 | Adam | Definiuje visible_when rule: `nfz_code` widoczny tylko gdy `is_nfz_eligible == true`. Testuje regułę z dwoma input'ami. |
| US-MOD-011 | Adam (lub Kasia) | Where-used dla atrybutu `voltage` — widzi że jest w 2 grupach, używany przez 1 ObjectType (Product), w 247 instancjach. |
| US-MOD-012 | Adam | Próbuje usunąć ObjectType "Service" — system blokuje (24 instancje istnieją), proponuje migrate-and-delete workflow. |
| US-MOD-013 | Adam | Kopia ObjectType "Service" jako "ServicePremium" z odziedziczonymi grupami + dodaje `vip_features` group. |

## 10. Business rules / edge cases

### 10.1 Konflikty Attribute Groups
- **Rule:** atrybut nie może być w dwóch grupach w tym samym formularzu obiektu (system to wymusza).
- **Edge case:** Lekarz deklaruje grupę X (zawiera atrybut `material`), Service ObjectType ma globalną grupę Y (też zawiera `material`). Konflikt → walidacja przy save modelu, nie w runtime.

### 10.2 Cykle w drzewie kategorii
- **Rule:** kategoria nie może być rodzicem swojego przodka.
- **Implementacja:** ltree trigger w Postgres + walidacja UI.

### 10.3 Delete protection
- **ObjectType built-in:** zawsze blokowane.
- **ObjectType custom z instancjami:** blokowane do migrate-and-delete.
- **Attribute Group:** blokowane jeśli używana przez co najmniej 1 ObjectType lub Category. Confirm modal *„Detach from N usages first"*.
- **Attribute:** blokowane jeśli ma wartości w `object_values`. Confirm migration plan.

### 10.4 Code immutability
- **Built-in ObjectType code:** niezmienne (na zawsze, by nie łamać integracji).
- **Built-in Attribute code:** niezmienne (`sku`, `created_at`, etc.).
- **Custom ObjectType code:** zmienne, ale ostrzeżenie *„This will break X integrations"* + audit log.
- **Custom Attribute code:** zmienne z impact warning.

### 10.5 System attributes auto-attachment
- Każdy nowy ObjectType (built-in lub custom) **automatycznie** dostaje grupę "Audyt" (`is_system=true`).
- Klient **nie może** odpiąć tej grupy. UI pokazuje ją z 🔒.
- Powód: każdy obiekt MUSI mieć `created_at` / `updated_at` dla audit log + sortowania.

### 10.6 Visibility rule limits
- **MVP:** tylko proste `attribute == value` rules (1 condition).
- **Faza 1:** AND/OR composite rules.
- **Faza 2:** complex expressions (np. `created_at > 30 days ago AND brand IN ['Bosch', 'Festo']`).

### 10.7 Mass attribute changes (migration workflow)
- **Type changes** (text → select, number → metric): wymagają mapping plan + dry-run + backup.
- **Type changes destructive** (richtext → boolean): blokowane (dane lost), force flag z explicite confirmation.
- **Localization changes** (non-localizable → localizable): all existing values → default locale; klient może później distribute.

### 10.8 Model versioning (Faza 2 kandydat)
- Każda modyfikacja modelu (ObjectType / Attribute / AttributeGroup / Category) tworzy *„model version"*.
- Obiekty są *pinned* do wersji modelu przy tworzeniu.
- Migration to nowej wersji modelu — explicit event z zatwierdzeniem.

---

## 11. Permissions (deferred MVP)

**MVP:** brak role gating. Każdy zalogowany user ma full access do Modelowania.

**Faza 1 (proponowany ADR-013 *„User personas and modeling permissions"*):**
- `model_admin` — pełen dostęp do Modelowania (default dla Adama).
- `model_viewer` — read-only widok Modelowania (Piotr może czytać żeby debugować integracje, nie modyfikować).
- `content_editor` — brak dostępu do Modelowania (Kasia, Magda).
- `super_admin` — overrides everything (Tomasz, Marcin).

**Audit log** od dnia 1 (Doctrine AuditBundle, epik 0.11.4 z planu) — każda zmiana w Modelowaniu jest logowana z user_id + timestamp + diff. Nawet bez role gating, mamy *trace* kto co zmienił.

---

## 12. Dependency na backend

### 12.1 ADR-y, które musimy zaktualizować lub stworzyć

- **ADR-009** (rozszerzenie) — Brand jako 4-ty predefiniowany ObjectType, Attribute Group jako encja first-class.
- **ADR-012** (NEW) — *„Attribute Group as first-class entity for cross-objecttype data modeling"*. Patrz § 3.5 + § 3.8 (DDL).
- **ADR-013** (NEW, Faza 1) — *„User personas and modeling permissions"* — granularne role + permissions.

### 12.2 Encje + tabele (delta vs ADR-009)

```sql
-- Nowa tabela
CREATE TABLE attribute_groups (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    code VARCHAR(128) NOT NULL,
    name JSONB NOT NULL,             -- {"pl": "Wymagania medyczne", "en": "Medical requirements"}
    description JSONB,
    icon VARCHAR(64),
    color VARCHAR(16),
    is_system_group BOOLEAN NOT NULL DEFAULT false,
    auto_attached BOOLEAN NOT NULL DEFAULT false,  -- Audit grupa
    UNIQUE (tenant_id, code)
);

-- Junction: Attribute → AttributeGroup
CREATE TABLE attribute_group_attributes (
    attribute_group_id UUID NOT NULL REFERENCES attribute_groups(id) ON DELETE CASCADE,
    attribute_id UUID NOT NULL REFERENCES attributes(id),
    position INTEGER NOT NULL DEFAULT 0,
    is_required_in_group BOOLEAN NOT NULL DEFAULT false,
    visible_when JSONB,             -- conditional visibility
    PRIMARY KEY (attribute_group_id, attribute_id)
);

-- Junction: ObjectType → AttributeGroup (globalne grupy dla typu)
CREATE TABLE object_type_attribute_groups (
    object_type_id UUID NOT NULL REFERENCES object_types(id) ON DELETE CASCADE,
    attribute_group_id UUID NOT NULL REFERENCES attribute_groups(id),
    position INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (object_type_id, attribute_group_id)
);

-- Junction: Category → AttributeGroup (dziedziczone grupy w drzewie)
CREATE TABLE category_attribute_groups (
    category_object_id UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
    target_object_type_id UUID NOT NULL REFERENCES object_types(id),
    attribute_group_id UUID NOT NULL REFERENCES attribute_groups(id),
    position INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (category_object_id, target_object_type_id, attribute_group_id)
);

-- Update: object_types
ALTER TABLE object_types ADD COLUMN is_built_in BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE object_types ADD COLUMN code_immutable BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE object_types ADD COLUMN deletable BOOLEAN NOT NULL DEFAULT true;
ALTER TABLE object_types ADD COLUMN icon VARCHAR(64);
ALTER TABLE object_types ADD COLUMN color VARCHAR(16);

-- Seed built-in
INSERT INTO object_types (code, name, is_built_in, code_immutable, deletable, is_hierarchical, has_variants)
VALUES
  ('product',  '{"pl":"Produkt","en":"Product"}',     true, true, false, false, true),
  ('category', '{"pl":"Kategoria","en":"Category"}',  true, true, false, true,  false),
  ('asset',    '{"pl":"Zasób","en":"Asset"}',         true, true, false, false, false),
  ('brand',    '{"pl":"Marka","en":"Brand"}',         true, true, false, false, false);

-- Update: attributes
ALTER TABLE attributes ADD COLUMN is_system BOOLEAN NOT NULL DEFAULT false;

-- Seed system attributes (auto-attached)
INSERT INTO attributes (code, type, is_system) VALUES
  ('id', 'uuid', true),
  ('created_at', 'datetime', true),
  ('updated_at', 'datetime', true),
  ('created_by', 'reference:user', true),
  ('updated_by', 'reference:user', true);
```

### 12.3 Domain service

`EffectiveAttributeGroupResolver` — service który dla danej pary `(object_id, category_path)` zwraca **efektywną listę Attribute Groups** z dziedziczeniem:

```php
class EffectiveAttributeGroupResolver
{
    /**
     * @return array<AttributeGroup>
     */
    public function resolve(Object $object): array
    {
        $groups = [];
        
        // 1. System auto-attached groups (Audit)
        $groups[] = $this->getSystemAuditGroup();
        
        // 2. Globalne grupy dla ObjectType
        foreach ($object->getObjectType()->getAttributeGroups() as $group) {
            $groups[] = $group;
        }
        
        // 3. Grupy dziedziczone z drzewa kategorii (od root do leaf)
        foreach ($object->getCategoryPath() as $category) {
            foreach ($category->getDeclaredAttributeGroups($object->getKind()) as $group) {
                if (!$this->isAlreadyInList($groups, $group)) {
                    $groups[] = $group;
                }
            }
        }
        
        // 4. Per-object ad-hoc grupy (jeśli)
        foreach ($object->getAdHocAttributeGroups() as $group) {
            $groups[] = $group;
        }
        
        return $groups;
    }
}
```

Cache: dla danego `(object_type_id, category_path)` lista jest stabilna — cache w Redis z TTL 5 min, invalidacja na każdą zmianę modelu.

### 12.4 Doctrine listener

Listener `ObjectFormSchemaListener` — gdy klient otwiera formularz produktu/usługi/etc., serwer zwraca *zsynchronizowaną listę grup + atrybutów* używając `EffectiveAttributeGroupResolver`. Frontend renderuje formularz dynamicznie.

---

## 13. Komponenty Refine + shadcn

### 13.1 Generic admin patterns
- `Refine.Resource` per sub-tab (`object-types`, `attributes`, `attribute-groups`, `categories-modeling`).
- `useTable`, `useForm`, `useShow`, `useCreate`, `useUpdate`, `useDelete` — standardowy Refine.

### 13.2 shadcn components
- `Tabs` — top-level 4 sub-tabs.
- `Table` + `DataTable` (TanStack Table) — list views.
- `Sheet` (right drawer) — detail view per Object Type / Attribute / Group.
- `Dialog` — wizard create new + migration impact.
- `Form` + `FormField` — Zod + React Hook Form.
- `Input`, `Select`, `Switch`, `Combobox` (shadcn) — base inputs.
- `Card`, `Badge`, `Tooltip` — info display.
- `Tree` (custom, brak w shadcn — może wykorzystać `react-arborist`) — drzewo kategorii.

### 13.3 Custom components

| Komponent | Rola |
|---|---|
| `BuiltInLockBadge` | 🔒 ikona z tooltipem *„System type — protected"*. |
| `WhereUsedList` | Komponent pokazujący gdzie atrybut/grupa jest używana (groups, types, categories, instance count). |
| `EffectiveAttributesPreview` | Pokazuje *„this is what user will see"* dla danego (ObjectType, Category) combo. |
| `MigrationImpactAnalyzer` | Modal pokazujący impact zmiany typu atrybutu (count, distinct values, suggested mapping). |
| `VisibleWhenRuleEditor` | Builder dla conditional visibility rules. |
| `CategoryTreeWithGroups` | Drzewo kategorii z embedded declared groups per category. |
| `AttributeFromLibraryPicker` | Search + select z biblioteki atrybutów. |
| `IconPicker` | Picker dla emoji + Lucide icons. |
| `ColorPicker` | shadcn-style color picker (hex). |

### 13.4 react-arborist

Dla drzewa kategorii (sub-tab 4) — `react-arborist` jest najmocniejszą biblioteką tree dla React (drag-drop, virtualization dla 1000+ węzłów, keyboard navigation). MIT.

---

## 14. Open questions

- [ ] **Categories management** — dwa widoki (modeling tu + daily-use w Produktach lub osobny epik)? Decyzja: **modeling tu** (deklaracja attribute groups), **daily-use** w epiku 02 Produkty (drag-drop produkty między kategoriami).
- [ ] **Sub-tab Categories vs osobna pozycja sidebar** — może lepiej osobna pozycja menu *„Kategorie"* (zamiast 4-tej zakładki w Modelowaniu)? Argument za osobnymi: Categories są *często* zmieniane przez Magdę (kampanie marketingowe), nie tylko *„raz na 1-2 tyg."* jak inne aspekty Modelowania. Ale wtedy 9 pozycji menu zamiast 8.
- [ ] **Attribute Group nesting** — czy AG może zawierać sub-AGs (zamiast płaskiej listy atrybutów)? Nie w MVP (over-engineering), ale Faza 2 kandydat.
- [ ] **System attributes — które dokładnie auto-attached?** Lista: `id`, `created_at`, `updated_at`, `created_by`, `updated_by`, `tenant_id`. Czy `enabled`, `position`, `slug` też?
- [ ] **Category tree depth limit** — Postgres ltree wspiera ~256 levels, ale UX'owo gubię się powyżej 5-6 levels. Soft warning? Hard limit?
- [ ] **Multi-parent categories** — czy obiekt może być w wielu kategoriach (np. *„Konsultacja online"* w `Lekarz` AND `Telemedycyna`)? Akeneo i Pimcore: TAK. My: rozważyć czy w MVP czy Faza 1.
- [ ] **Versioned model** — Faza 2 lub MVP-Final? Wpływa na to, czy obiekty *„starych"* wersji modelu się ładują po zmianie schematu.
- [ ] **Localized vs system-wide names** — czy `display_name` jest zawsze multi-locale (PL/EN), czy może być system-wide angielskie?
- [ ] **Permissions deferred** — kiedy ADR-013? Po jakim signal'u? Pierwszym pilocie? Pierwszej skardze klienta?
- [ ] **Wizard *„Create new ObjectType"*** — multi-step (lepszy onboarding) czy single-form (szybciej)? Adam doświadczony — single, Marcin nowy — multi.
- [ ] **Test objects creation** — przycisk *„Create test object"* w Category detail (US-MOD-007) — czy tworzy w prawdziwej tabeli, czy w dedicated `test_objects` (sandboxie)?

---

## 15. Wpisanie do roadmapy backend (delta vs `Project Plan/02-plan-projektu-pim.md`)

Modelowanie wpływa na:
- **Epik 0.3** (Domain model — Catalog) — dochodzi tabela `attribute_groups`, junction tables, listener `EffectiveAttributeGroupResolver`. Estymacja: **+12-16h** ponad obecny scope (już zwiększony o brand + variants + multi-level inheritance).
- **Epik 0.6** (Admin UI — core CRUD) — Modelowanie jest *swoim własnym* zestawem widoków, nie delta na innych. Dochodzi nowa pozycja menu + 4 widoki listy + 4 widoki detail + wizardy. Estymacja: **+30-40h** (samodzielny zestaw widoków + komponenty custom).
- **Epik 0.11** (Hardening) — audit log obejmuje zmiany w Modelowaniu (już planowane przez DoctrineAuditBundle, brak dodatkowego scope).

**Total impact na Faza 0:** **+42-56h**. Aktualny budżet ~270-380h → **~310-440h**. Wciąż w zakresie *„pełen MVP"* (Marcin akceptuje +50-80h scope, PRD § 12.1).

---

## 16. Co dalej

1. **Walidacja koncepcji** z Marcinem (lub Adamem jeśli zatrudniony) — czy 4 sub-tabs to dobra struktura, czy lepsze coś innego.
2. **Wireframes w Figma** — przekazać ten plik external UX designerowi (z PRD § 13.5 *„external UX designer 10-20h kontrakt"*).
3. **Klikalny prototyp** — przed implementacją, żeby walidacja flow z Adamem była realna (nie ASCII).
4. **Przepisanie ADR-009 + napisanie ADR-012** — formalne ADR dla Attribute Group jako first-class.
5. **Aktualizacja `Project Plan/02-plan-projektu-pim.md`** epik 0.3 i 0.6 estymacji.
6. **Aktualizacja `Project Plan/03-funkcjonalnosci-mvp.md`** — dodać Adam jako persona + user stories US-MOD-001 do US-MOD-013.

---

*Plik wersjonowany w `Zrodla/UI/`. Status: szczegół. Następna iteracja: walidacja z Marcinem (lub designer'em) + Figma wireframes + ADR-012 draft.*
