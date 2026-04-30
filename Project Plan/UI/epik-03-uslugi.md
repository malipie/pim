# Epik 03 — Usługi (Services)

## Status: 🔵 placeholder

## 1. Cel epiku

Zarządzanie obiektami typu **Usługa** (`ObjectType=service`) — przykład **custom ObjectType** dodanego przez Adama w Modelowaniu (epik 08). Pozycja menu generowana **dynamicznie** z `object_types` table — gdy klient w Modelowaniu doda kolejny custom kind (np. `subscription`, `event`), pojawi się analogiczna pozycja menu obok Usług.

Rzeczywiste use case'y dla pierwszych pilotów:
- **Branża medyczna:** Lekarz / Chirurg / Ortopeda / Pediatra (z kategoriami w drzewie).
- **Branża fryzjerska:** Strzyżenie męskie / Damskie / Koloryzacja / Manicure.
- **Doradztwo / SaaS:** Konsultacje / Subskrypcje / Webinary.

## 2. Persony

- **Kasia / Magda** w roli content editor dla usług (analog do produktów).
- **Custom rola per branża** — np. *„Recepcjonistka medyczna"* (krótka edycja cennika + grafiku) — Faza 2 z permission system.
- **Adam** — definiuje `ObjectType=service` + jego attribute groups + categories drzewo (w Modelowaniu, epik 08).

## 3. Kluczowe widoki

**Generic UI = analog do epiku 02 (Produkty), z parametryzacją per `kind`:**

- Lista usług (Table view) — kolumny dynamicznie generowane z `ObjectType.list_columns_config`. Domyślnie: SKU, Name, Category, Pricing, Duration, Status.
- Detail usługi (Edit view) — dynamiczny formularz z efektywną listą Attribute Groups (z dziedziczenia kategorii).
- Create service — wizard analogiczny do produktów, ale z innymi default fields.
- Categories management dla `kind=service` — tree z drag-drop (osobny ObjectType `category` ale z filter'em na Service).

**Specyfika Usług (vs Produkty):**
- **Brak wariantów** w typowym scenariuszu (Lekarz nie ma wariantów color×size). Ale ADR-010 to wspiera, gdyby klient zażądał (np. *„Konsultacja: online vs osobista"*).
- **Hierarchia kategorii głęboka** (Service → Lekarz → Chirurg → Ortopeda) — kluczowy use case dla Adama z rozmowy źródłowej.
- **Pricing per kanał** mniejszy niż per locale (większość usług sprzedawanych w 1 walucie / kraju) — ale `scopable` flag wciąż przydatny.
- **Brak DAM heavy** — usługa rzadko ma 50 zdjęć, częściej ikona + 1-2 zdjęcia ilustracyjne.

## 4. User stories

- **US-EP03-001:** Adam (lub Marcin sam) definiuje w Modelowaniu nowy `ObjectType=service` + Attribute Groups (Cennik podstawowy, Czas trwania, Wymagania medyczne, Refundacja NFZ) — patrz epik 08.
- **US-EP03-002:** Kasia po dodaniu typu *Service* widzi nową pozycję menu *„Usługi"*. Klika i widzi listę pustą + CTA *„dodaj pierwszą usługę"*.
- **US-EP03-003:** Kasia tworzy usługę *„Konsultacja ortopedyczna"* w kategorii Lekarz/Chirurg/Ortopeda. System dynamicznie pokazuje formularz z efektywnymi grupami atrybutów (Identyfikacja + Opis + Cennik medyczny + Wymagania medyczne + Refundacja NFZ + Chirurgia szczegóły + Ortopedia + Audyt).
- **US-EP03-004:** Kasia bulk-edit'uje 30 usług ortopedycznych — zmienia `requires_referral=true` na wszystkich.
- **US-EP03-005:** Magda generuje opisy SEO usług (Faza 1, BYOK).
- _[TODO: integracja z systemem rezerwacji / kalendarzem — Faza 2/3?]_
- _[TODO: integracja z NFZ API — out of scope MVP, Faza 3+ na zlecenie]_

## 5. Business rules / edge cases

- _[TODO: gdy Adam usuwa Attribute Group z Modelowania, co z istniejącymi wartościami (cascade delete? soft delete? migrate)]_
- _[TODO: różnice walidacji per branża (lekarska wymaga PWZ, fryzjer nie)]_
- _[TODO: kategorie multi-parent (chirurg ortopeda należy do *Chirurg* AND *Ortopeda*) — czy wspieramy?]_

## 6. Dependency na backend

- ADR-009 (Generic ObjectType) — *to jest fundament* tego epiku. Bez niego Usługi nie istnieją.
- Proponowany **ADR-012 (Attribute Group as first-class entity)** — z epiku 08 — *kluczowa zależność* dla dziedziczenia atrybutów per kategoria.
- ADR-006 (Hybrid attribute model) — `object_values` parametryzowany per `object_type_id`.
- Doctrine listener wykrywający dodanie nowego ObjectType i automatycznie konfigurujący generic CRUD endpointy + admin UI.

## 7. Komponenty Refine + shadcn

- **Wszystko współdzielone z epikiem 02 (Produkty)** — generic CRUD components parametryzowane przez `object_type_id`.
- Custom `DynamicSidebarNav` — komponent który czyta `object_types` i generuje pozycje menu dynamicznie.
- Custom `EffectiveAttributeGroupResolver` — service zwracający efektywną listę Attribute Groups dla `(object_type, category_path)` z dziedziczenia.

## 8. Open questions

- [ ] Czy Usługi *muszą* być w sidebar od dnia 1, czy pojawiają się dopiero po dodaniu pierwszej Service przez Adama?
- [ ] UX *„dodaj pierwszą usługę"* — wizard wprowadzający, który prowadzi przez Modelowanie najpierw?
- [ ] Ikona menu — auto-generated z `ObjectType.icon` field? Picker w Modelowaniu?
- [ ] Naming display vs code — w sidebar pokazujemy display name (`Usługi`), nie code (`service`).
- [ ] Search across all ObjectTypes — czy globalny search obejmuje custom kindy?
- [ ] Custom kindy w Cmd+K — czy agent (Faza 2) może tworzyć obiekty custom kind?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — kluczowy showcase elastyczności PIM-u dla mid-market poza retail.*
