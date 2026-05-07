# Funkcjonalności MVP — archetyp, persony, user stories

**Wersja:** 1.0 (faza koncepcyjna — funkcjonalna)
**Data:** 2026-04-26
**Powiązane dokumenty:** `01-architektura-pim.md`, `02-plan-projektu-pim.md`
**Status:** zatwierdzony do realizacji

---

## 1. Wprowadzenie i cel dokumentu

Dokumenty `01-architektura-pim.md` i `02-plan-projektu-pim.md` opisują **jak** zbudować PIM — stack, model danych, sub-fazy, tickety techniczne. Brakowało odpowiedzi na pytania **co** i **dla kogo**:

- Kim jest pierwszy pilot pilotażowy MVP?
- Jakie persony używają systemu codziennie?
- Jakie konkretne workflow są kluczowe dla pierwszego "wow effect"?
- Jaki jest happy path do udanego wdrożenia?

Ten dokument zamyka tę lukę. Jest materiałem wejściowym dla:

1. **UX designera** (zewnętrzny kontrakt po sprintu 0/MVP-Alpha) — persony + workflow + wow moments → wireframes w Figma
2. **Claude Code w VS Code** — kontekst dla rozpisania ticketów epików 0.6 (Admin UI), 0.7 (Agent), 0.8-0.9 (Integracje)
3. **Sales pitch / decision deck** dla pierwszego klienta pilotażowego — konkret czego oczekiwać z PIM-u

Architektura PIM jest celowo **agnostic** wobec branży (sekcja 4 architektury, hybrid model atrybutów). Persony i user stories w tym dokumencie są **przykładem dla konkretnego archetypu**, ale schema, formularze, agent — wszystko driven by Attribute schema, brak hardcoded "form dla rur" czy "form dla narzędzi".

---

## 2. Archetyp pierwszego pilota

### 2.1 Profil firmy

> **Archetyp pierwszego pilota MVP:**
> - **Branża:** technika przemysłowa (mechanika, automatyka, hydraulika, narzędzia, maszyny — PIM agnostic, klient sam definiuje atrybuty per family)
> - **Profil sprzedaży:** B2B (zaczynamy od B2B, B2C/B2B2C w fazie 1+)
> - **Skala:**
>   - GMV ~50 MLN PLN/rok
>   - ~10-15k SKU
>   - ~15-20 etatów ogółem
> - **Model biznesowy:** **multimarka + własna produkcja** (najtrudniejszy case — import od dostawców w różnych formatach + kanoniczne karty własnej marki + override / fallback / provenance)
> - **Rynek:** Polska, ekspansja na CEE w fazie 2+
> - **Obecny stack:** ERP (Comarch / Subiekt / Symfonia), sklep własny (PrestaShop / Magento / własny CMS), marketplace (BaseLinker, Allegro Biznes), Excele od dostawców, OpenAI w drugiej karcie do opisów

### 2.2 Pricing dla archetypu (target)

| Tier | Wdrożenie | Subskrypcja | Profil |
|---|---|---|---|
| **Pro** (główny target MVP) | 50k PLN | 30k PLN/rok (~2.5k/mies) | 50 MLN GMV, 10-15k SKU |
| **Starter** (faza 1) | 25k PLN | 15k PLN/rok (~1.25k/mies) | 20-50 MLN GMV, 5-10k SKU |
| **Enterprise** (faza 2) | 100k+ PLN | 60k+ PLN/rok | 100+ MLN GMV, 20k+ SKU, multi-tenant |

Pierwsze ~25-30 klientów stałych w roku 3 → ~1.2-1.5 mln/rok recurring revenue.

### 2.3 TAM (Total Addressable Market) PL

- Firmy 50 MLN GMV B2B technical w PL: **200-500** (główny target Pro tier)
- Firmy 20-50 MLN GMV: ~1000-3000 (Starter tier, faza 1)
- Firmy 100+ MLN GMV: ~50-150 (Enterprise tier, faza 2)

Konkurencja:
- **Akeneo Cloud** — €30k+/rok, overkill funkcjonalnie, anglojęzyczny support
- **PIMcore** — open-source ale wymaga miesięcy konfiguracji + drogie wdrożenie partnera (Pimcore Solution Partner)
- **Excel + ad-hoc rozwiązania** — rzeczywista konkurencja w segmencie 50 MLN GMV (większość firm "jakoś sobie radzi")
- **Brak polskiego konkurenta agentic-first** — to nasza dziedzina

### 2.4 Wow moment dla pierwszego pilota

> *"Pierwszy raz w karierze zaimportowałem 200 produktów od nowego dostawcy w 3 minuty zamiast 3 godzin. Agent zmapował kolumny automatycznie. A jak chciałem dodać nowy atrybut, to wpisałem komendę i było gotowe — bez czekania tygodnia na IT. PIM rozumie że jestem w branży hydraulicznej i wie że DN jest miarą średnicy, nie liczbą losową. Zaoszczędzam 8h tygodniowo."*

Ten cytat (lub jego wariant z konkretnymi liczbami klienta) jest **success criterion** pierwszego pilota.

---

## 3. Persony

### 3.1 Lista person

| # | Persona | Etat | Rola w PIM | Priorytet UX |
|---|---|---|---|---|
| 1 | **Owner / Dyrektor** | 1 (czasem 2 z Dyrektorem Operacyjnym) | Power user (czasem sam edytuje), super-admin uprawnień, dashboard | MVP minimum + dashboard |
| 2 | **Catalog Manager** | 1 (dedykowany) | **MAIN USER**, codziennie w PIM | **#1 priorytet** |
| 3 | **Marketing Specialist** | 1 (dedykowany) | Współużytkownik z Catalog Managerem (opisy, kategorie, kampanie) | MVP minimum |
| 4 | **IT/Integration Specialist** | 1 (dedykowany lub fractional przez agencję) | Konfiguracja integracji, debug syncu | **#1.5 priorytet** |
| 5 | **Sales / Handlowcy** | 3-5 | **NIE wchodzą do PIM** (żyją w ERP/CRM) | Out of scope |
| - | **Magazyn** | 3-5 | W ERP, nie w PIM | Out of scope |

### 3.2 Persona 1 — Owner / Dyrektor (Tomasz, 48 lat)

**Profil:**
- Wiek 45-55, przedsiębiorca, polski właściciel firmy lub współwłaściciel z bratem/wspólnikiem
- Doświadczenie w branży 15-25 lat, często rozpoczynał jako handlowiec
- Zna swoich klientów osobiście, jeździ na spotkania B2B sam
- Niekoniecznie biegły technologicznie, ale rozumie biznesową stronę systemów
- Pracuje 50-60h/tydzień, w środę 8:00-20:00, weekend często też

**Dzień pracy z PIM:**
- 8:00 — kawa, przegląda dashboard PIM (główny ekran startowy): liczba aktywnych produktów, ostatnie sync errors, completeness ogólna, MRR vs cel
- 9:30 — Catalog Manager pyta czy "może dodać atrybut Klasa Wybuchowa do rodziny Czujniki ATEX" — Tomasz akceptuje
- 14:00 — sam wchodzi i edytuje opis flagowego produktu własnej marki (ProTech) bo Marketing nie zdążył, jutro spotkanie z dużym klientem
- 16:00 — sprawdza co Catalog Manager wgrał z nowego dostawcy (z Włoch) — czy są opisy po polsku
- 18:00 — przegląda w mobile dashboard (telefon) — czy synch z BaseLinker poszedł

**Frustracje (główne pain pointy):**
1. **Brak wglądu w "co się dzieje"** — chaos co aktualne, co stare, co czeka na publikację
2. **Każdy system inny** — sklep, ERP, marketplace, Excele — żadnego wspólnego widoku
3. **Decyzje po omacku** — czy pakuję wszystko na Allegro? Co ma najgorszą completeness? Brak danych
4. **Catalog Manager idzie na urlop = stoi cały produktowy łańcuch** — nikt nie wie co Catalog Manager robił

**Oczekiwania od PIM:**
- Dashboard z **5-7 KPI** widoczny w 5 sekund: SKU active/total, completeness avg, sync status, top 10 most-edited produkty, sales velocity per kategoria
- Możliwość **samodzielnego override** — jeśli chce zmienić opis produktu, nie czeka na Catalog Managera
- Mobile-friendly dashboard (read-only) — z telefonu może rzucić okiem
- **Audit log** — kto co kiedy zmienił, jeśli klient pyta "kto poprawił atrybut X 3 dni temu"

**Wow moment dla Tomasza:**
> *"Po raz pierwszy mam jeden ekran który pokazuje wszystko co dzieje się z moim katalogiem. Nawet w Sopotonze na wakacjach mogę szybko sprawdzić czy wszystko działa. Kasia (Catalog Manager) jest wreszcie zwolniona z 'co dzieje się dzisiaj?' rozmów ze mną."*

### 3.3 Persona 2 — Catalog Manager (Kasia, 32 lata) — **MAIN USER, priorytet #1**

**Profil:**
- Wiek 28-38, absolwentka politechniki (Mechanika, Automatyka, Inżynieria Produkcji)
- 5-10 lat w branży B2B technical, wcześniej w mniejszej hurtowni / dystrybutorze
- Zna branżę technicznie — wie różnicę między DN50 a Ø50, rozumie normy DIN/EN/PN
- Excel guru (vlookup, pivot tables, ale nie macros)
- Nie programuje, nie zna SQL, ale rozumie struktury danych
- Pracuje 8:30-16:30, czasem zostaje godzinę na "ten jeden Excel od włoskiego dostawcy"

**Dzień pracy z PIM:**
- 8:30 — kawa, otwiera dashboard PIM: 12 nowych produktów wgranych przez agenta w nocy (z webhooka dostawcy), 230 produktów ma żółty status completeness
- 9:00 — otwiera Excel od włoskiego dostawcy, wczoraj klient pytał o ich nowe zawory. Wgrywa Excel do PIM, agent automatycznie mapuje kolumny (rozpoznał profile dostawcy z poprzedniego importu), pokazuje preview 5 wierszy. Klika Import.
- 9:30 — review zaimportowanych: 195 OK, 5 błędów (dwa SKU już istnieją, należy zdecydować czy override; jeden ma brakujący materiał; dwa mają nieznaną klasę ciśnieniową)
- 10:30 — bulk edit: zaznacza 30 produktów Festo, kategoria główna = "Pneumatyka / Zawory rozdzielające"
- 11:30 — Marketing prosi o dodanie atrybutu "klasa szczelności IP" do rodziny Czujniki — Cmd+K → "dodaj atrybut IP_class do rodziny Czujniki, typ select, opcje IP54, IP65, IP67, IP68, IP69K, wymagany". Agent proponuje zmianę. Tomasz (Owner) akceptuje w ciągu 5 min.
- 12:30 — pisze opis własnego produktu marki ProTech, nowa linia czujników indukcyjnych, 800 znaków SEO + atrybuty techniczne
- 14:00 — sprawdza completeness: 230 produktów żółtych → klika filtr "zacznij z najczęściej kupowanymi", agent sugeruje brakujące atrybuty na podstawie family
- 15:30 — odpowiada Marketingowi pytania o materiały dla nowej kampanii hydrauliki
- 16:00 — trigger publikacji do BaseLinker dla 50 nowych produktów, sprawdza status w panelu
- 16:30 — koniec, ale w głowie ma 80 produktów do dorzucenia jutro (przyszedł cennik od polskiego dystrybutora)

**Frustracje (głębokie pain points — DZIŚ, bez PIM):**

1. **"Każdy dostawca nazywa to samo inaczej"** — DN vs Średnica vs nominalna średnica vs Ø vs średnica wewn. To samo, ale jak to scalić? Dziś robi ręcznie w Excelu.
2. **"Czekam tydzień na IT z dodaniem atrybutu"** — zmiana schema = ticket do IT = tydzień blocked
3. **"Nie wiem które produkty są niekompletne"** — przegląda ręcznie po jednym produkcie w panelu sklepu
4. **"Override vs nadpisanie"** — jak zmieniła "stal nierdz." na "AISI 316L", a dostawca przy aktualizacji przysłał "Stal nierdz." — czy nadpisuję czy nie?
5. **"Excele dostawców co miesiąc inne"** — żaden stabilny mapping kolumn
6. **"Opisy SEO ad-hoc"** — pisze w OpenAI w drugiej karcie, copy-paste do panelu sklepu, brak historii zmian
7. **"3 panele dla jednej akcji"** — zmiana atrybutu w sklepie + ERP + BaseLinker, każdy inaczej

**Oczekiwania od PIM-u (must-have):**

- Jeden ekran z wszystkimi produktami, filtry: rodzina, kategoria, completeness, provenance, status publikacji
- **Dynamiczny formularz** — pokazuje **tylko atrybuty istotne dla rodziny** (nie 200 pustych pól)
- **Provenance badges** przy polach: "wartość od dostawcy Festo z 15.04", "wartość edytowana przez Ciebie 18.04"
- **Status completeness** widoczny w liście (czerwone, żółte, zielone)
- **Cmd+K command palette** — agent rozumie kontekst branżowy (DN, PN, materiały, normy)
- **Import Excela z mapowaniem kolumn** — pierwsze raz ręcznie, potem zapisany profil dostawcy, agent auto-mapuje
- **Bulk edit** — zaznacz wiele, edytuj atrybut na raz
- **Override flag** — możliwość zablokowania pól przed nadpisaniem przez import
- **Inbox agenta** — pending changes, accept/reject, diff przed/po
- **DAM** — drag-drop zdjęć, automatyczne resize, przypisanie do produktu

**Wow moment dla Kasi:**
> *"Pierwszy raz w karierze importowałam Excel od nowego dostawcy w 3 minuty zamiast w 3 godziny. Agent zmapował kolumny automatycznie. Dodanie nowego atrybutu wpisałam komendą i było gotowe — bez czekania tygodnia na IT. Mam dashboard completeness, wreszcie wiem które 50 produktów wymaga uwagi. Wieczorami jestem 17:00 nie 19:00."*

### 3.4 Persona 3 — Marketing Specialist (Magda, 29 lat)

**Profil:**
- Wiek 25-32, absolwentka marketingu / dziennikarstwa / SEO
- 3-5 lat doświadczenia, wcześniej w agencji marketingowej
- Excel ok, Google Analytics ok, CMS ok, copy-writing dobry
- Nie zna branży technicznie (nie wie czemu DN50 ≠ DN65), ale szybko się uczy

**Dzień pracy z PIM:**
- 9:00 — otwiera PIM, lista zadań: 30 produktów bez SEO opisu PL, 12 bez EN, kampania "Promocja Hydraulika" do przygotowania
- 9:30 — bulk edit: 30 produktów → generuje opisy SEO (faza 2: agent help, MVP: ręcznie)
- 11:00 — kategorie reorganizacja: drag-drop drzewa kategorii, nowa podkategoria "Czujniki przemysłowe → Indukcyjne"
- 13:00 — kampania: zestaw produktów z kategorii Hydraulika do banner'a głównego sklepu — tworzy "kolekcję", trigger sync
- 15:00 — sprawdza analitykę produktów: które najbardziej oglądane, które konwertują (faza 1 — analytics dashboard)

**Frustracje:**
1. **"Catalog Manager dyktuje atrybuty, ja muszę pisać opisy"** — żaden help, brak wzorców
2. **"Inny formularz dla każdej kategorii"** — w sklepie różne pola SEO, w marketplace inne
3. **"Brak wglądu które produkty mają najlepszy ROI"** — analytics w drugiej karcie
4. **"Drzewo kategorii ad-hoc"** — chaos, dziedziczenie atrybutów nieprzewidywalne

**Oczekiwania od PIM:**
- Bulk edit opisów per locale (PL/EN), z preview
- Drzewo kategorii drag-drop
- Kolekcje (smart i manual) — produkty sprostowane logicznie (np. "Hot deals", "New arrivals", "Promocja Q4")
- Faza 1: analytics dashboard (top viewed, top converted)
- Faza 2: agent help generujący SEO-friendly opisy z atrybutów technicznych

**Wow moment dla Magdy:**
> *"Wreszcie mam jedno miejsce do edycji opisów dla wszystkich kanałów. Drzewo kategorii intuicyjne. W fazie 2 agent generuje pierwszą wersję opisu z atrybutów technicznych — ja tylko polish."*

### 3.5 Persona 4 — IT/Integration Specialist (Piotr, 38 lat) — **priorytet #1.5**

**Profil:**
- Wiek 30-45, IT/integration background, ~10 lat doświadczenia
- Zna PHP/Python/REST/JSON, SQL podstawy, ERP-y (Comarch, Symfonia API)
- Nie programuje codziennie, ale debuguje integracje, pisze małe skrypty
- W archetypie 50 MLN GMV: **dedykowany pełen etat** lub **fractional przez agencję** (np. Ideo)

**Dzień pracy z PIM:**
- 8:00 — alerty z nocnego sync: "ERP → sklep: 12 produktów failed, 3 timeout, 1 webhook missed"
- 8:30 — debuguje failed sync: PIM panel integracji → BaseLinker → ostatnie błędy → klika konkretny produkt → widzi szczegół błędu (brakujący atrybut "Producent" w mapowaniu) → naprawia mapowanie → retry tylko tych 12
- 10:30 — Catalog Manager prosi o nową integrację z Allegro Biznes — Piotr otwiera "Add Integration" wizard, wpisuje credentials, mapuje pola PIM → Allegro fields, robi test sync 3 produktów
- 13:00 — Catalog Manager pyta dlaczego konkretny produkt nie poszedł na Shopify — Piotr otwiera produkt → zakładka "Publication status" → widzi: Shopify rejected, brak metafield "vendor_part_number"
- 14:30 — review webhooków Shopify, sprawdzanie HMAC, czy są retry'e
- 16:00 — pisze dokumentację dla nowej integracji w wewnętrznym wiki

**Frustracje:**
1. **"Każda integracja inny config, inny tool"** — Shopify w jednym, BaseLinker w drugim, ERP w trzecim panelu
2. **"Catalog Manager modyfikuje atrybuty, sync się rozjeżdża"** — brak widoczności co Kasia zmieniła, że ERP padło
3. **"Webhooki failą, alert = email z 200 błędami HTML"** — niepraktyczne
4. **"Per-item retry niemożliwy"** — albo cały sync, albo nic
5. **"Mapowania w YAML albo CMS — gdzie jest source of truth?"**

**Oczekiwania od PIM:**
- **Jeden panel integracji** — lista wszystkich integracji + status (zielone/żółte/czerwone) + last sync timestamp
- **Live log błędów** per integracja (nie email)
- **Mapowanie atrybutów wizualne** — drag-drop albo dropdowns, nie YAML
- **Per-item retry** — failed items widoczne, retry one-click
- **Webhooks management** w tym samym panelu — URL, secret, retry policy, last received
- **API Configurator** — generowanie kluczy API dla zewnętrznych konsumentów (klient B2B chce ściągać katalog) bez kodowania
- **Audit trail** — kto co zmienił w mapowaniu, kiedy

**Wow moment dla Piotra:**
> *"Skonfigurowałem nową integrację z Allegro Biznes w 2 godziny zamiast 2 tygodni. Mapowania zrobiłem w UI, sprawdziłem testowo, deploy. Webhooki monitoruję w jednym panelu. Pierwszy raz mam czas na coś poza sync errorami."*

---

## 4. User Stories — MVP Catalog Manager (priorytet #1)

Format każdej user story:

```
US-XXX: Tytuł
Persona: Catalog Manager (Kasia)
As a: [rola]
I want to: [akcja]
So that: [wartość biznesowa]

Priority: must-have / nice-to-have / faza 1
Epic mapping: [epik z 02-plan-projektu-pim.md]
Estimate: [godziny]

Acceptance Criteria:
- [ ] Kryterium 1
- [ ] Kryterium 2
- ...

UX notes: [kluczowe ekrany, gdzie jest akcja]
Wow factor: [co robi to "wow" dla Kasi]
```

---

### US-001: Import produktów z Excel/CSV od dostawcy z mapowaniem kolumn

**Persona:** Catalog Manager
**As a:** Catalog Manager
**I want to:** zaimportować plik Excel/CSV od nowego dostawcy z automatycznym lub ręcznym mapowaniem kolumn na atrybuty PIM
**So that:** dodać 50-200 produktów w 5 minut zamiast 3 godzin ręcznego klikania

**Priority:** must-have
**Epic mapping:** 0.6 Admin UI (UI importera) + 0.3 Catalog domain model (logika importu) + nowy 0.6.X (importer/dedupe)
**Estimate:** 16-22h

**Acceptance Criteria:**
- [ ] Mogę wgrać plik .xlsx, .csv lub .xml (drag-drop lub file picker)
- [ ] System pokazuje **preview pierwszych 5 wierszy**
- [ ] Dla każdej kolumny mogę:
  - [ ] Wybrać czy mapuje na **atrybut** (z dropdown listy atrybutów rodziny)
  - [ ] Wybrać że mapuje na **SKU/identyfikator**
  - [ ] **Ignorować** kolumnę
  - [ ] Wybrać **transformację** (np. "konwertuj DN50 na 50", "uppercase")
- [ ] Mogę zapisać mapowanie jako **profil dostawcy** ("Festo XLSX 2026", "Italian Supplier ABC v3")
- [ ] Przy następnym imporcie tego samego dostawcy — profil ładuje się automatycznie
- [ ] Przed importem widzę **dry-run summary**:
  - [ ] X produktów nowych (insert)
  - [ ] Y produktów aktualizowanych (update)
  - [ ] Z produktów w błędach (z listą szczegółową)
- [ ] Mogę zdecydować dla istniejących produktów: **override**, **merge**, **skip**
- [ ] Po imporcie: lista zaimportowanych z linkami, lista błędów z możliwością **napraw → retry per item**
- [ ] **Provenance** każdego importu: `provenance: 'import'`, `provenance_meta: { supplier: 'Festo', file: 'price_2026_q2.xlsx', date: '2026-04-26' }`
- [ ] **Rollback** całego importu w ciągu 24h (faza 1: do 7 dni)

**UX notes:**
- Trigger: główny button "Importuj produkty" w toolbar listy produktów + ekran "Importy" z historią
- 4-step wizard: Upload → Map columns → Preview & Decide → Run & Status
- W Preview & Decide: tabela z kolumnami "źródło → cel", dropdowns z autocomplete

**Wow factor:**
> *"Pierwszy raz w karierze zaimportowałam Excel w 3 minuty zamiast 3 godzin. Agent rozpoznał format dostawcy z poprzedniego razu i zmapował kolumny automatycznie."*

---

### US-002: Edycja atrybutów pojedynczego produktu z dynamicznym formularzem

**Persona:** Catalog Manager
**As a:** Catalog Manager
**I want to:** edytować atrybuty produktu w formularzu, który pokazuje **tylko relewantne pola** dla rodziny, z provenance badges
**So that:** nie zgubię się w 200 pustych polach, wiem skąd pochodzi każda wartość, i wiem co mogę bezpiecznie zmienić

**Priority:** must-have
**Epic mapping:** 0.6 Admin UI (Resource Products edit), 0.3 Catalog domain model
**Estimate:** 14-18h

**Acceptance Criteria:**
- [ ] Otwieram produkt → formularz pokazuje **wyłącznie atrybuty z rodziny + atrybuty wspólne (sku, name, brand, category)**
- [ ] Każde pole atrybutu pokazuje **provenance badge**:
  - [ ] 🟢 manual (Ja edytowałam) — zielona
  - [ ] 🔵 import (z dostawcy) — niebieska, hover pokazuje "Festo, 15.04.2026, plik price.xlsx"
  - [ ] 🟣 agent (agent dodał/zmienił) — fioletowa
  - [ ] ⚫ integration (z BaseLinker/ERP) — szara
- [ ] Mogę kliknąć **"zablokuj pole"** (lock icon) — wartość nie zostanie nadpisana przez import
- [ ] Pola **scopable** (per-channel) i **localizable** (per-locale) mają widoczne tabsy/zakładki
- [ ] Walidacja **inline**: jeśli atrybut wymaga liczby z jednostką, nie pozwala wpisać tekstu
- [ ] **Auto-save** po blur (3s debounce) lub Cmd+S
- [ ] Przed zapisaniem **diff modal**: stare → nowe wartości
- [ ] Historia zmian widoczna w tabie "History" — kto, kiedy, co
- [ ] Completeness procent widoczny live w toolbar formularza

**UX notes:**
- Layout: Sticky header (SKU, name, completeness%), left sidebar (sections: Basic, Technical, Marketing, Channels, Locales), main content z formularzami, right sidebar (history, related products, integration status)
- Provenance badges małe (12px) ale klikalne — pokazują tooltip
- Lock icon obok każdego pola

**Wow factor:**
> *"Pierwszy raz widzę dokładnie skąd jest każda wartość. Mogę zablokować swoją edycję tak, że dostawca jej nie nadpisze. Formularz pokazuje tylko 12 relevantnych pól, nie 200 pustych."*

---

### US-003: Sprawdzenie completeness — które produkty są niepełne

**Persona:** Catalog Manager
**As a:** Catalog Manager
**I want to:** widzieć w liście produktów, które są **niepełne** (czerwone), **częściowo pełne** (żółte), **pełne** (zielone), z możliwością klikania w czerwone żeby zobaczyć **co brakuje**
**So that:** zacznę dnia od najpilniejszych zadań, nie przeglądam ręcznie po jednym

**Priority:** must-have
**Epic mapping:** 0.3 Catalog (completeness rules), 0.6 Admin UI (lista + filtr)
**Estimate:** 8-12h

**Acceptance Criteria:**
- [ ] Lista produktów ma kolumnę **completeness** z kolorowym wskaźnikiem (czerwony <50%, żółty 50-90%, zielony >90%)
- [ ] Mogę filtrować po completeness: "tylko czerwone", "tylko żółte", "wszystkie"
- [ ] Mogę sortować po completeness (rosnąco/malejąco)
- [ ] Klik w produkt z czerwonym statusem → otwiera edit z **highlighted brakującymi atrybutami**
- [ ] Completeness rules są **per-channel** (Shopify wymaga title+description+price+image, BaseLinker wymaga innych pól)
- [ ] Widzę completeness aggregated:
  - [ ] **Per family** ("Czujniki: avg 73%, 230 czerwonych")
  - [ ] **Per category** ("Hydraulika: avg 89%, 12 czerwonych")
  - [ ] **Per channel** ("Shopify: avg 65%", "BaseLinker: avg 91%")
- [ ] Owner widzi globalny completeness w dashboard

**UX notes:**
- Lista produktów: kolumna z mini progress bar + procent
- Filter w toolbar: chips "Wszystkie / Czerwone / Żółte / Zielone"
- W edit: brakujące pola są highlighted (czerwona ramka), lista "X brakujących pól" w sidebar

**Wow factor:**
> *"Zaczynam dzień od listy 230 czerwonych produktów. W ciągu 2h domykam 50, statystyka rośnie z 73% na 78%. Po raz pierwszy mam KPI mojej pracy."*

---

### US-004: Bulk edit atrybutów dla wielu produktów

**Persona:** Catalog Manager
**As a:** Catalog Manager
**I want to:** zmienić wartość atrybutu (np. kategoria, marka, status) dla **wielu produktów na raz**, zamiast edytować każdy ręcznie
**So that:** zaktualizować 50 produktów Festo do nowej kategorii w 30 sekund zamiast 50 minut

**Priority:** must-have
**Epic mapping:** 0.6 Admin UI (bulk actions), 0.3 Catalog (bulk update logic)
**Estimate:** 6-10h

**Acceptance Criteria:**
- [ ] W liście produktów mogę zaznaczyć **wiele** (checkbox per wiersz + select-all w header)
- [ ] Toolbar pokazuje "X selected" + button "Bulk edit"
- [ ] Klik "Bulk edit" → modal z wyborem akcji:
  - [ ] **Change attribute value** — wybierz atrybut + nową wartość → preview ile produktów dotknie → confirm
  - [ ] **Add to category**
  - [ ] **Remove from category**
  - [ ] **Change status** (enable/disable)
  - [ ] **Change family** (tylko gdy bezpieczne — nowa rodzina ma kompatybilne atrybuty, ostrzeżenie jeśli traci atrybuty)
- [ ] Przed wykonaniem: **preview diff** — pokazuje pierwsze 5 produktów ze starym i nowym stanem
- [ ] Po wykonaniu: **success summary** + **rollback** (do 24h)
- [ ] **Provenance** każdej bulk akcji: `provenance: 'manual'`, `provenance_meta: { bulk_action_id: '...', user: 'kasia', timestamp: ... }`
- [ ] Bulk edit dla >100 produktów leci przez Symfony Messenger (async), z progress bar w UI

**UX notes:**
- Bulk akcje **ukryte** dopóki nic nie zaznaczone — toolbar zmienia wygląd po zaznaczeniu pierwszego
- Modal z dwoma kolumnami: "What to change" + "Preview"

**Wow factor:**
> *"Wszystkie 30 produktów Festo do nowej kategorii w 30 sekund. Z preview, z rollback, z historią. W Excelu robiłam to godzinami z błędami."*

---

### US-005: Dodanie nowego atrybutu/rodziny przez agenta (Cmd+K)

**Persona:** Catalog Manager
**As a:** Catalog Manager
**I want to:** dodać nowy atrybut lub rodzinę produktów **wpisując komendę naturalnym językiem do agenta** (Cmd+K), zamiast czekać tydzień na IT
**So that:** mogę reagować na potrzeby biznesu w godzinach, nie tygodniach

**Priority:** must-have (MVP-Beta-Min)
**Epic mapping:** 0.7 Agent layer
**Estimate:** 12-16h (z architektury, MVP-Beta-Min minimum)

**Acceptance Criteria:**
- [ ] Cmd+K (lub Ctrl+K) otwiera command palette z polem tekstowym
- [ ] Mogę wpisać polski tekst, np.:
  - [ ] *"dodaj atrybut waga opakowania, liczba w kg, do rodziny Elektronika, wymagany"*
  - [ ] *"utwórz rodzinę Czujniki ATEX z atrybutami klasa wybuchowa, IP, zakres temperatur"*
  - [ ] *"dodaj atrybut Producent do wszystkich rodzin"*
- [ ] Agent (Claude Sonnet via Anthropic SDK) **planuje akcje**, pokazuje **proposed changes** w diff modal:
  - [ ] Nowy atrybut: kod, typ, walidacja, opcje (jeśli select)
  - [ ] Przypisanie do rodziny (jeśli dotyczy)
  - [ ] Tłumaczenia label PL/EN (agent generuje propozycje)
- [ ] Mogę **zaakceptować, zmodyfikować, odrzucić** każdą zaproponowaną zmianę
- [ ] Po akceptacji: zmiana zapisana w `pending_changes` queue, Owner (lub uprawniony użytkownik) zatwierdza w **Inbox agenta** (US-010)
- [ ] **Twarde limity** z architektury sekcja 8.5: 50 tool calls/h/user, 10/agent_run, $20/dzień/tenant — wyświetlane w UI palette gdy się zbliżamy
- [ ] **Audit log**: prompt, plan, akcje, czas wykonania, koszt w tokenach i USD
- [ ] **Streaming** odpowiedzi (MVP-Beta-Full) lub non-streaming wait (MVP-Beta-Min)

**UX notes:**
- Cmd+K = command palette w stylu Linear / Raycast / GitHub
- Position: center-top, max-w-2xl, max-h-96 (po expand)
- Animacje: subtle, nie distracting
- Po wpisaniu pierwszych 3 znaków: agent zaczyna parsing, pokazuje "thinking..." indicator

**Wow factor:**
> *"Wpisałam 'dodaj atrybut klasa szczelności IP do rodziny Czujniki, opcje IP54 IP65 IP67 IP68 IP69K' i było gotowe w 30 sekund. Agent zaproponował kompletny plan, ja kliknęłam Akceptuj. IT nie zaangażowane, marketing nie czekał."*

---

### US-006: Pisanie opisów SEO i treści marketingowych per locale

**Persona:** Catalog Manager + Marketing Specialist (shared)
**As a:** Catalog Manager / Marketing Specialist
**I want to:** pisać opisy produktów w **wielu językach (PL/EN)** i **wielu kanałach** (web sklep ma SEO z 800 znaków, BaseLinker ma 200, własna karta techniczna ma 1500)
**So that:** każdy kanał dostaje content dopasowany, bez copy-paste między 5 systemami

**Priority:** must-have
**Epic mapping:** 0.3 Catalog (scopable+localizable atrybuty), 0.6 Admin UI (formularz)
**Estimate:** 8-12h (MVP); +6-10h faza 2 (agent generuje opisy)

**Acceptance Criteria:**
- [ ] Atrybuty typu **text** mogą być **localizable** (per-locale) i **scopable** (per-channel) — definiowane przy tworzeniu atrybutu
- [ ] W formularzu produktu, atrybut localizable ma **tabs** (PL | EN | DE | …)
- [ ] W tabach atrybut scopable ma **sub-tabs** (Web | BaseLinker | Datasheet | …)
- [ ] **Rich text editor** dla długich opisów (TipTap lub podobny — bold, italic, lists, links, ale NIE images/embeds w MVP)
- [ ] **Character counter** per kanał z limitami:
  - [ ] BaseLinker: max 2000 chars (SEO description)
  - [ ] Shopify: max 5000 chars (description HTML)
  - [ ] Custom datasheet: bez limitu
- [ ] **Preview** jak opis wygląda w docelowym kanale (rendering)
- [ ] **Diff** między starym a nowym opisem (przed save)
- [ ] **Faza 2 (post-MVP):** agent help — *"wygeneruj opis SEO PL z atrybutów technicznych"* — agent czyta atrybuty produktu i pisze draft, Marketing edytuje i zapisuje

**UX notes:**
- Tabs PL/EN sticky pod headerem produktu
- Rich text editor zajmuje 60% szerokości, 40% to preview/help sidebar
- Character counter: zielone (poniżej 80%), żółte (80-95%), czerwone (>95%)

**Wow factor:**
> *"Mam jeden ekran do pisania opisów dla wszystkich kanałów i języków. Preview pokazuje jak wygląda na sklepie i w BaseLinkerze. W fazie 2 agent generuje pierwszą wersję — ja tylko polish."*

---

### US-007: Zarządzanie kategoriami (drzewo, drag-drop)

**Persona:** Catalog Manager + Marketing Specialist
**As a:** Catalog Manager / Marketing Specialist
**I want to:** widzieć i edytować **drzewo kategorii** (hierarchia) z możliwością drag-drop produktów i podkategorii
**So that:** mogę reorganizować strukturę katalogu szybko, bez ręcznego edytowania per-produkt

**Priority:** must-have
**Epic mapping:** 0.6 Admin UI (Resource Categories tree view), 0.3 Catalog (ltree)
**Estimate:** 10-14h

**Acceptance Criteria:**
- [ ] Widok drzewa kategorii (lewo) + lista produktów wybranej kategorii (prawo)
- [ ] **Drag-drop** w drzewie kategorii — przesuwanie kategorii w hierarchii
- [ ] **Drag-drop** produktów z listy do innej kategorii w drzewie
- [ ] Tworzenie nowej kategorii (right-click menu lub button)
- [ ] Zmiana nazwy kategorii inline (double-click)
- [ ] Usunięcie kategorii — z ostrzeżeniem co stanie się z produktami (przeniesienie do parent? do "Uncategorized"?)
- [ ] **Multi-locale name** — tabs PL/EN dla nazwy kategorii
- [ ] **Smart collections** (faza 1): kategorie definiowane regułą atrybutów (np. "Wszystkie produkty marki Festo z kategorii Pneumatyka")
- [ ] **ltree path** widoczny opcjonalnie (`root.electronics.sensors.inductive`) dla zaawansowanych
- [ ] **Performance:** drzewo z 200+ kategoriami ładuje się <500ms

**UX notes:**
- Tree component z React DnD lub dnd-kit
- Wcięcie 20px per poziom
- Hover row pokazuje liczbę produktów w kategorii

**Wow factor:**
> *"Reorganizuję kategorie drag-drop, jak w Findersie. Przesunięcie 200 produktów z Hydraulika → Pneumatyka to 3 sekundy."*

---

### US-008: Trigger publikacji na kanały + status

**Persona:** Catalog Manager
**As a:** Catalog Manager
**I want to:** **wysłać** produkty na konkretne kanały (BaseLinker, Shopify, własny sklep) i **monitorować status** (sukces, błąd, w trakcie)
**So that:** wiem co się dzieje z produktami po edycji, nie zgaduję

**Priority:** must-have
**Epic mapping:** 0.8 BaseLinker integration, 0.9 Shopify integration, 0.6 Admin UI (sync_jobs panel)
**Estimate:** 8-12h (UI w Admin) + 26-34h (integracje BaseLinker + Shopify, z planu)

**Acceptance Criteria:**
- [ ] W liście produktów: kolumna **"Channels"** pokazuje status per kanał (zielony/żółty/czerwony)
- [ ] W detail produktu: tab **"Publication"** z listą kanałów + ostatnia synchronizacja + status
- [ ] Mogę **trigger ręczny sync** dla:
  - [ ] Pojedynczego produktu (button "Publish to BaseLinker")
  - [ ] Bulk (zaznacz wiele → Bulk Action → "Publish to channels...")
  - [ ] Cały sklep (Cron ustawiony, ale też manual trigger w panelu Integration)
- [ ] **Sync_jobs panel** (osobna strona "Integrations → Sync Jobs") z listą wszystkich syncs:
  - [ ] Status (running, success, failed, partial)
  - [ ] Stats (X new, Y updated, Z errors)
  - [ ] Progress bar dla running
  - [ ] Możliwość rozwinąć failed → lista produktów z błędami → retry per item
- [ ] **Live updates** przez Mercure (SSE) — gdy sync się kończy, status w UI aktualizuje się bez refresh

**UX notes:**
- Lista produktów: ikona kanału (mini logo BaseLinker, Shopify) + status dot
- Sync jobs: tabela z filtrami (status, integration, date range)
- Failed errors: ekspandowalny accordion z detalem (HTTP code, response body, mapping issues)

**Wow factor:**
> *"Po edycji produktów klikam Bulk Publish, widzę progress bar w czasie rzeczywistym. Failed 3 produkty? Klikam Retry na konkretnych. W innych systemach godzinami nie wiedziałam co poszło, co nie."*

---

### US-009: Upload i zarządzanie zdjęciami (DAM lite)

**Persona:** Catalog Manager + Marketing Specialist
**As a:** Catalog Manager / Marketing Specialist
**I want to:** wgrać zdjęcia produktów (drag-drop, multi-file) i przypisać je do produktów, z automatycznym resize do thumbnails
**So that:** każdy produkt ma odpowiednie zdjęcia bez ręcznego wgrywania w 3 systemach

**Priority:** must-have (MVP)
**Epic mapping:** 0.3 Asset domain model, 0.6 Admin UI (DAM screen + product detail)
**Estimate:** 10-14h (MVP plain upload + thumbnails); +12-16h faza 2 (transformacje, AI metadata)

**Acceptance Criteria:**
- [ ] Strona "Assets" — drag-drop multi-file upload (jpg, png, webp, max 10 MB per plik)
- [ ] Po upload: automatyczne **thumbnails** (200x200, 800x800) generowane przez Imagick
- [ ] Storage przez Flysystem na MinIO (lokalny) lub S3 (cloud)
- [ ] Każdy asset ma metadane: nazwa pliku, MIME, rozmiar, daty, przypisane produkty
- [ ] W detail produktu — sekcja "Images" z drag-drop:
  - [ ] Główne zdjęcie (1) — duże, wyświetlane jako default
  - [ ] Galeria (do 10) — thumbnails, kolejność drag-drop
  - [ ] Linkowanie istniejącego asseta (search + select)
  - [ ] Upload nowego inline
- [ ] **Provenance** assetów: kto upload, kiedy
- [ ] **Faza 2:** AI metadata extraction (alt text, tags z modelu vision), variants generation (różne formaty per kanał)

**UX notes:**
- Lightbox preview po kliknięciu thumbnail
- Bulk upload: progress per plik, możliwość anulowania w trakcie
- Filter assets: by date, by extension, by linked product

**Wow factor:**
> *"Wgrywam 50 zdjęć drag-drop, system robi thumbnails automatycznie, mogę przypisać hurtowo do produktów. W Shopify+BaseLinker+sklepie własnym wgrywałam każde zdjęcie 3 razy."*

---

### US-010: Inbox agenta — pending changes, accept/reject

**Persona:** Catalog Manager + Owner (super-admin)
**As a:** Catalog Manager / Owner
**I want to:** widzieć **wszystkie pending changes proponowane przez agenta** w jednym miejscu, z możliwością przeglądania diff i akceptowania/odrzucania
**So that:** agent nie wykonuje destruktywnych operacji bez ludzkiej zgody, a ja mam wgląd w jego propozycje

**Priority:** must-have (MVP-Beta-Min — minimum) / nice-to-have (MVP-Beta-Full — rich diff)
**Epic mapping:** 0.7 Agent layer (Pending changes queue + UI inbox)
**Estimate:** 6-10h (MVP-Beta-Min minimum) + 7-9h (MVP-Beta-Full rich)

**Acceptance Criteria (MVP-Beta-Min):**
- [ ] Strona "Agent → Inbox" z listą pending changes
- [ ] Każdy item: typ (create_attribute, assign_attribute_to_family, ...), opis akcji, prompt który ją wywołał, kto, kiedy
- [ ] Klik w item → **simple diff modal**: lista pól, stare → nowe wartości
- [ ] Buttons **Accept**, **Reject**, **Modify** (Modify edit'uje proposal w formularzu)
- [ ] Po Accept: agent wykonuje akcję w transakcji, status item zmienia się na "Applied"
- [ ] Po Reject: status "Rejected", powód (opcjonalnie, freeform text)
- [ ] **Audit log**: każda decyzja (Accept/Reject/Modify) logowana z timestamp i user'em
- [ ] **Real-time** przez Mercure SSE — nowy pending change pojawia się bez refresh

**Acceptance Criteria (MVP-Beta-Full):**
- [ ] **Rich diff modal**: kolorowanie semantyczne (zielone added, czerwone removed, żółte changed)
- [ ] Preview "before" i "after" w dwóch kolumnach
- [ ] Multi-step accept (jeśli proposal ma wiele kroków, mogę zaakceptować część)
- [ ] **Provenance badges** wskazujące "to pole zmienione przez agenta na podstawie promptu X dnia Y"

**UX notes:**
- Inbox UI inspirowane GitHub PRs lub Linear inbox — lista po lewej, detail po prawej
- Badge z liczbą pending w sidebar nawigacji ("Inbox (3)")

**Wow factor:**
> *"Agent proponuje zmiany, ja widzę dokładnie co chce zrobić, akceptuję jednym kliknięciem albo odrzucam. Mam pełną kontrolę nad tym co system robi w moim imieniu."*

---

## 5. User Stories — pozostałe persony (lighter w MVP)

### 5.1 Owner (Tomasz)

**US-011 (Dashboard):** Główny ekran z KPI (SKU active/total, completeness avg, sync status, last edited products, pending agent changes).
**US-012 (Mobile read-only):** Dashboard responsywny dla mobile (Tomasz na wakacjach z telefonu).
**US-013 (Audit log):** Tabela z audit log (kto co zmienił, filter by date/user/entity).

**Estimate łącznie:** 12-16h (Epic 0.11.10 + nowe).

### 5.2 Marketing Specialist (Magda)

W większości używa tych samych workflow co Catalog Manager (US-006, US-007). MVP nie dodaje osobnych stories dla Marketingu — pełen flow w fazie 1+ (analytics dashboard, bulk SEO opisy z agentem).

### 5.2a Imports MVP — szczegółowy backlog user stories (epik 0.13 / UI-09)

US-001 traktuje import jako jeden monolityczny ticket (16-22h). Real implementacja epiku 0.13 / UI-09 (`Project Plan/UI/feature-imports.md`) rozłożyła ten zakres na **15 atomowych ticketów** (IMP-01..IMP-15) z explicit DoD per ticket. Poniżej user stories US-IMP-001..014 mapują flow użytkownika na tę implementację — pełne acceptance criteria + brainstorming decisions w `feature-imports.md`.

| ID | Persona | Story (skrót) | Mapowanie ticket |
|---|---|---|---|
| **US-IMP-001** | Kasia | Wybieram plik xlsx/csv + locale + (opcjonalnie) ZIP zdjęć w Step 1 wizard'a, system pokazuje preview headerów. | IMP-08 #449 + IMP-10 #451 |
| **US-IMP-002** | Kasia | Auto-mapping kolumn przez rules-based dictionary PL/EN (top ~30 atrybutów × 5-10 synonimów) — Step 2 pokazuje 12/15 dopasowanych + ręcznie domapuję 3. | IMP-02 #443 + IMP-10 #451 |
| **US-IMP-003** | Kasia | W trakcie mapping'u brakuje atrybutu — klikam *„+ Stwórz nowy atrybut"*, deep-link do `/modeling/attributes/new` z preserved state, wracam i mapping zachowany. | IMP-10 #451 |
| **US-IMP-004** | Kasia | Step 3 walidacja: KPI *„247 OK / 33 błędy"* + top-10 errors + dialog *„Pokaż wszystkie 33"* + radiogroup *„Co dalej"* + download CSV raportu. | IMP-03 #444 + IMP-11 #452 |
| **US-IMP-005** | Marcin (founder) | **Dogfooding** — migracja katalogu IdoSell ~2k SKU jako pierwszy real-world test. Findings → `agent/lessons.md`. **Status:** odsunięte poza marathon (brak realnego export'u w czasie epiku); gate przed *„imports gotowe"*. | IMP-14 #455 (deferred) |
| **US-IMP-006** | Kasia | Step 4 confirm + opcjonalny *„Utwórz pgBackRest backup"* checkbox (status polling co 5s, blokuje *„Uruchom"* do completed). | IMP-06 #447 + IMP-11 #452 |
| **US-IMP-007** | Kasia | Klikam *„▶ Uruchom import"* (sync <50 rows / async 50+) → Mercure SSE pokazuje live progress: bar + counters + ETA + current SKU. | IMP-04 #445 + IMP-12 #453 |
| **US-IMP-008** | Kasia | Po imporcie: results screen z KPI (✅ N OK / ⚠️ M pominięte / czas) + download CSV + deep-link *„Zobacz zaimportowane produkty"* (filter `import_session_id`). | IMP-05 #446 + IMP-12 #453 |
| **US-IMP-009** | Kasia | *„Wycofaj import"* w 24h window → confirm dialog + cascade warning jeśli published do channels → soft delete obiektów z `import_session_id`. | IMP-05 #446 + IMP-12 #453 |
| **US-IMP-010** | Kasia | Email notification po zakończeniu importu jeśli runtime > 5 min (klient zamknął kartę). | IMP-04 #445 |
| **US-IMP-011** | Kasia | Profil importu z smart memory (mapping + locale + encoding + delimiter) — *„Use saved profile"* w Step 1, *„Save as profile"* checkbox w Step 4. | IMP-07 #448 + IMP-10 #451 |
| **US-IMP-012** | Kasia | Profile manager modal (Sheet) — lista profili usera z Last used / # imports / edit / delete + disclaimer *„Edycja modyfikuje TYLKO przyszłe importy"*. | IMP-13 #454 |
| **US-IMP-013** | Kasia | Lista wszystkich importów (Step 0) z filtrami (Status, Date range), badges status, 3-dot menu (View report / Rollback / Re-run / Delete). | IMP-09 #450 |
| **US-IMP-014** | Kasia | Smoke test E2E na żywym backendzie: upload festo-q2-2026.xlsx → mapping → validation → confirm → progress → results → rollback. | IMP-14 #455 |

**Mapowanie do epiku 6.X:** wszystkie US-IMP-001..014 idą do **epiku 0.13 / UI-09** (sub-tab Imports w epiku 04 Publikacje, `Project Plan/02-plan-projektu-pim.md` §3.7). US-001 z sekcji §4 traktuję jako legacy entry point — szczegół w `feature-imports.md` + tabeli powyżej.

### 5.3 IT/Integration Specialist (Piotr)

**US-014 (Integration panel):** Lista wszystkich integracji + status + last sync + live error log.
**US-015 (Add integration wizard):** Konfiguracja nowej integracji w UI (credentials, mapowania, test sync).
**US-016 (Per-item retry):** W sync jobs panel — failed items widoczne, retry one-click.
**US-017 (API Configurator):** Generowanie kluczy API dla zewnętrznych konsumentów + scope per profile.

**Estimate łącznie:** 14-18h (Epic 0.10 + nowe ticket'y w 0.8/0.9).

---

## 6. Mapowanie user stories → epiki techniczne

| User Story | Epik | Priorytet sub-fazy |
|---|---|---|
| US-001 Import (legacy entry) | **0.13 / UI-09 (zaimplementowane)** — szczegół w US-IMP-001..014 (§5.2a) i `Project Plan/UI/feature-imports.md` | MVP-Final + 0.13 |
| US-002 Edycja produktu | 0.6.2 + 0.3.4 | MVP-Alpha |
| US-003 Completeness | 0.3.8 + 0.6.2 | MVP-Alpha |
| US-004 Bulk edit | 0.6.2 (extension) | MVP-Final |
| US-005 Cmd+K agent | 0.7 (MVP-Beta-Min) | MVP-Beta-Min |
| US-006 SEO opisy | 0.3.1 + 0.6.2 | MVP-Alpha |
| US-007 Kategorie tree | 0.6.5 + 0.3.3 | MVP-Alpha |
| US-008 Publish + status | 0.8 + 0.9 + 0.6.6 | MVP-Final |
| US-009 DAM upload | 0.3.7 + 0.6.7 | MVP-Final |
| US-010 Inbox agenta | 0.7 (Beta-Min minimum, Beta-Full rich) | MVP-Beta-Min / Beta-Full |
| US-011 Dashboard | 0.11.10 (analytics dashboard) | MVP-Final |
| US-012 Mobile dashboard | 0.11.9 (a11y + responsive) | MVP-Final |
| US-013 Audit log | 0.11.4 | MVP-Final |
| US-014 Integration panel | 0.8.6 + 0.9.7 | MVP-Final |
| US-015 Add integration wizard | 0.8.4 + 0.9.5 | MVP-Final |
| US-016 Per-item retry | 0.8.6 + 0.9.7 | MVP-Final |
| US-017 API Configurator | 0.10 (Epic całość) | MVP-Final |

**Total dodatkowych godzin user-facing UI** (ponad core epików technicznych): ~30-50h, mieści się w aktualnym budżecie MVP-Final (70-94h).

---

## 7. Success criteria pierwszego pilota

Pilot uznajemy za **udany** gdy **w 30 dni od deploy** osiągamy:

1. **Catalog Manager** raportuje:
   - 50%+ skrócenie czasu na import nowego dostawcy
   - Co najmniej 5 atrybutów dodanych przez agenta (Cmd+K) bez angażowania IT
   - 80%+ produktów ma completeness >90%
2. **Owner** raportuje:
   - Codziennie korzysta z dashboard (>= 5 dni/tydz)
   - Wykonuje co najmniej 1 self-service edycję produktu/tydzień
3. **IT/Integration**:
   - 0 critical sync incidents w 30 dni
   - Co najmniej 1 nowa integracja skonfigurowana przez UI (nie kod)
4. **NPS / qualitative** od pilota:
   - "Polecam" lub "raczej polecam" (NPS 7+)
   - Cytuje **wow moment** spontanicznie w wywiadzie

Brak któregokolwiek z tych — retrospektywa, korekta, drugi pilot.

---

## 8. Następne kroki

1. **Sprint 0 (40-55h, gate decision)** — vertical slice walidujący stack (sekcja 3.0 planu projektu)
2. **MVP-Alpha (80-110h)** — backend + API + minimal admin (epiki 0.1-0.6)
3. **MVP-Beta-Min (12-16h)** — Cmd+K + Inbox agenta minimum (US-005, US-010 minimum)
4. **MVP-Final (70-94h)** — pełen scope user stories + integracje + hardening
5. **MVP-Beta-Full (13-19h, opcjonalnie)** — rich diff modal, streaming, provenance badges

**Po MVP-Alpha** — kontrakt z UX designerem na pełen Figma (wireframes + prototyp + design system) — ten dokument + persony są inputem do briefa.

**Tickety GitHub Issues** — rozpisanie Sprint 0 + epików 0.1-0.6 (czysto techniczne) jest **niezależne od user stories** i może iść równolegle. Tickety 0.6+ (Admin UI, Agent UX, Integracje) **wymagają** tego dokumentu jako inputu.

---

*Koniec dokumentu funkcjonalności MVP. Dokument żyjący — aktualizowany przy walidacji z pierwszym pilotem i każdą iteracją produktową.*
