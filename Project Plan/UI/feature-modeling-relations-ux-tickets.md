# Tickety — Modelowanie: placement atrybutów + UX relacji (Opcja 2)

> **Status: częściowo SUPERSEDED przez Option Y (MODRC-01..05)** — decyzja operatora 2026-05-28. MODR-02 (#924 — seedowana grupa „Powiązania"), MODR-06 (#928 — synthetic Relations tab po code'u grupy) oraz MODR-07 (#929 — pre-zaznaczony default group w konfiguratorze) zostały zastąpione przez MODRC-01 (#1080), MODRC-03 (#1082) i MODRC-02 (#1081) z batcha [`feature-modeling-relations-option-y-tickets.md`](feature-modeling-relations-option-y-tickets.md). Pozostałe MODR-y (01, 03, 04, 05, 08, 09, 10, 11) zachowują ważność.

**Typ dokumentu:** Backlog ticketów — ready-to-paste GitHub Issues
**Status:** Partial — MODR-02 (#924), MODR-06 (#928), MODR-07 (#929) **superseded przez MODRC-01..03** (Option Y, 2026-05-26) — patrz [`feature-modeling-relations-option-y-tickets.md`](feature-modeling-relations-option-y-tickets.md). MODR-11 (#933) **rozszerzony przez MODRC-04** (docs Option Y). Pozostałe tickety MODR-01/03/04/05/08/09/10 nadal aktualne.
**Powiązane:**
- [ADR-014](../01-architektura-pim.md) — *„Model dystrybucji atrybutów + relacje obiekt↔obiekt"*
- [`feature-modeling-data-model.md`](feature-modeling-data-model.md) — mini-spec implementacyjny (kontrakt, §3.5)
- [`feature-modeling-data-model-tickets.md`](feature-modeling-data-model-tickets.md) — batch MOD-01..14 (prerequisite)
- [`epik-08-modelowanie.md`](epik-08-modelowanie.md) — epik UI-08 (nadrzędny)

> **Cel:** rozstrzygnąć nieścisłość §3.5 wykrytą w praktyce (atrybut relacyjny „Smoke test" wyrenderował się inline zamiast w zakładce „Powiązania"). Decyzja: **Opcja 2** — relacja to zwykły atrybut, o placemencie (zakładka vs inline) decyduje wyłącznie AttributeGroup i jej `display_mode`. Nic nie jest hardkodowane jako zakładka.
> **Buduje na** batchu MOD-01..14 (typ atrybutu `relation`, `object_relations`, zakładka „Powiązania", konfigurator) — MODR-05..10 zakładają zmergowany MOD-06/07/12/13.
> **11 ticketów, ~47-70h** (~1.5-2 tygodnie solo dev). Większość to frontend polish + jedna mała schema delta.

---

## Konwencje

- Numer: `MODR-NN`. Po utworzeniu w GitHub: dopisany numer issue (`MODR-01 / #NNN`).
- Pola: Typ (Conventional Commits) / Epik / Estymacja / Dependencies / Risk flags / Cel / Scope / Acceptance criteria / Files affected / Testing / DoD.
- Tytuł issue po angielsku (Conventional Commits), opis po polsku.
- Labels: `modeling` + `adr-014` + `epik-ui-08` + risk flag jeśli dotyczy.
- **Reguła testów (operator, 2026-05-24):** każdy ticket w DoD ma WSZYSTKIE testy na zielono — co się da pokryć. Backend: PHPUnit + ApiTestCase. Frontend z widoczną zmianą: Vitest + Playwright E2E (bez E2E ticket NIE jest done — CLAUDE.md). Frontend integrujący backend: dodatkowo manual smoke test na żywym stacku (`https://pim.localhost`) per SMOKE TEST RULE.

## Graf zależności

```
MODR-01 #923 (backend: display_mode na junction ObjectType×AttributeGroup) ── foundation
        │
        ├── MODR-02 #924 (seed: od-hardkodowanie Multimedia + Powiązania na grupy)
        │       └── MODR-07 #929 (konfigurator relacji: domyślna grupa Powiązania)
        ├── MODR-03 #925 (frontend: renderer — placement wyłącznie po grupie + display_mode)
        └── MODR-04 #926 (frontend: przełącznik tab/stacked w kroku „Atrybuty")

MODR-05 #927 (frontend: ikona/badge relacji inline)              ── niezależny
MODR-06 #928 (frontend: widoczność zakładki „Powiązania" dla reverse) ── po MOD-07 #899
MODR-08 #930 (frontend: widget relacji — rich-preview-card)      ── po MOD-12 #904
MODR-09 #931 (frontend: widget relacji — inline create targetu)  ── po MOD-12 #904
MODR-10 #932 (frontend: widget relacji — inline expand/edit)     ── po MOD-12 #904
MODR-11 #933 (docs: §3.5 mini-spec + ADR-014 + decyzje odrzucone) ── równolegle
```

---

## MODR-01 / #923: feat(catalog): display_mode on ObjectType×AttributeGroup junction

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **4-6h**

**Dependencies:** Blocks MODR-02 (#924), MODR-03 (#925), MODR-04 (#926). Blocked by — brak (foundation).

**Risk flags:**
- Junction ObjectType↔AttributeGroup — **zweryfikować faktyczną nazwę tabeli** przed migracją (kandydat: `object_type_attribute_groups`; jeśli grupy są przypisywane inaczej — `display_mode` idzie na to przypisanie). Kolumna MUSI być na przypisaniu per-ObjectType, nie na globalnym `attribute_groups` — placement jest kontekstowy (ta sama grupa może być zakładką w jednym ObjectType, stackowana w innym).
- Zmiana dotyka kontraktu `form-schema` — serializer musi eksponować `display_mode` per grupa.

**Cel:** Dodać `display_mode` (`tab | stacked`) na przypisaniu AttributeGroup do ObjectType, tak by placement grupy był sterowalny i kontekstowy.

**Scope:**
```sql
-- nazwa junction do potwierdzenia w pre-flight
ALTER TABLE object_type_attribute_groups
    ADD COLUMN display_mode VARCHAR(8) NOT NULL DEFAULT 'tab'
    CHECK (display_mode IN ('tab', 'stacked'));
```
- Entity / mapping junction — nowa property `displayMode` + getter/setter.
- Serializer `form-schema` — eksponuje `display_mode` per grupa atrybutów.
- API `PATCH` przypisania grupy do ObjectType — akceptuje `display_mode`.
- Default `tab` (zachowanie wsteczne — dotychczas grupy renderowane jako zakładki).

**Acceptance criteria:**
- [ ] AC-1: Kolumna `display_mode` dodana, migracja UP+DOWN testowana w testcontainers
- [ ] AC-2: CHECK constraint — wartość spoza `tab|stacked` → constraint violation
- [ ] AC-3: `form-schema` zwraca `display_mode` per grupa
- [ ] AC-4: `PATCH` przypisania grupy zmienia `display_mode`, zmiana persystuje
- [ ] AC-5: `doctrine:schema:validate` pass
- [ ] AC-6: Cross-tenant — zmiana `display_mode` izolowana per tenant (TenantFilter)

**Files affected:** `src/Catalog/Doctrine/Migrations/Version*.php`, entity/mapping junction ObjectType×AttributeGroup, serializer `form-schema`, kontroler przypisania grupy.

**Testing:** Unit (entity) + integration (migracja UP/DOWN + constraint) + ApiTestCase (`form-schema` zawiera `display_mode`, `PATCH` zmienia).

**DoD:** AC + PHPStan max + wszystkie testy zielone + migracja rollback test + CI green.

---

## MODR-02 / #924: refactor(catalog): un-hardcode Multimedia + Powiązania tabs as seeded AttributeGroups

**Typ:** `refactor` | **Epik:** UI-08 | **Estymacja:** **3-5h**

**Dependencies:** Blocks MODR-07 (#929). Blocked by MODR-01 (#923).

**Risk flags:**
- Jeśli „Multimedia" / „Relacje" są obecnie zakładkami hardkodowanymi w rendererze frontu — migracja danych musi przenieść istniejące atrybuty pod nowe seedowane grupy bez utraty przypisań.
- Pre-flight: zweryfikować jak obecnie powstają zakładki „Multimedia" i „Relacje" (hardcoded w komponencie? specjalny enum?). Od tego zależy zakres cleanup.

**Cel:** Usunąć uprzywilejowanie „Multimedia" i „Relacje/Powiązania" jako zakładek hardkodowanych. Mają stać się zwykłymi seedowanymi `AttributeGroup` z `display_mode='tab'` — identycznymi jak każda inna grupa.

**Scope:**
- Seed AttributeGroup `media` (label „Multimedia", `display_mode=tab`) i `relations` (label „Powiązania", `display_mode=tab`) jako wbudowane grupy na ObjectType wymagających (Product itd.).
- Migracja danych — istniejące atrybuty `asset` / `relation` przepięte pod nowe seedowane grupy (jeśli wcześniej były w zakładkach generowanych specjalną ścieżką).
- Usunąć hardkodowaną logikę zakładek „Multimedia"/„Relacje" z renderera (jeśli istnieje) — placement idzie wyłącznie przez grupę + `display_mode` (konsumowane przez MODR-03).
- Built-in flag na seedowanych grupach — blokada przed deletion, jak inne built-in byty.

**Acceptance criteria:**
- [ ] AC-1: Grupy `media` i `relations` istnieją po seed z `display_mode=tab`
- [ ] AC-2: Istniejące atrybuty `asset`/`relation` przypisane do tych grup (test: query po migracji)
- [ ] AC-3: Brak hardkodowanej ścieżki tworzącej zakładkę „Multimedia"/„Relacje" poza mechanizmem grup
- [ ] AC-4: Seedowane grupy oznaczone built-in — próba usunięcia → odrzucona
- [ ] AC-5: Migracja DOWN — rollback path udokumentowany
- [ ] AC-6: form-schema dla istniejącego produktu renderuje te same zakładki co przed migracją (brak regresji UX)

**Files affected:** `src/Catalog/Doctrine/Migrations/Version*.php`, seed/fixtures AttributeGroup, ewentualny cleanup hardkodowanej logiki w rendererze.

**Testing:** Integration (seed + migracja danych UP/DOWN) + ApiTestCase (form-schema po migracji = przed) + manual smoke test (otwórz produkt, zakładki Multimedia/Powiązania nadal są).

**DoD:** AC + wszystkie testy zielone + smoke test (paste przed/po form-schema) + CI green.

---

## MODR-03 / #925: feat(admin): object form renderer — placement by AttributeGroup + display_mode only

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **5-8h**

**Dependencies:** Blocked by MODR-01 (#923), MODR-02 (#924).

**Risk flags:**
- Renderer formularza obiektu dotyka KAŻDEJ karty obiektu — regresja = wszystkie formatki broken. Pełny zestaw testów regresji obowiązkowy.
- Zero routingu placementu po `attribute.type` — atrybut `relation` renderuje się tam, gdzie jego grupa, dokładnie jak `text`/`number`. Tylko *widget* dobierany jest po typie.

**Cel:** Renderer karty obiektu liczy placement wyłącznie po AttributeGroup i jej `display_mode` (`tab` → osobna zakładka, `stacked` → sekcja inline). Relacja jest zwykłym atrybutem — placement po grupie, widget po typie. To realizuje Opcję 2 i kasuje nieścisłość §3.5.

**Scope:**
- Renderer iteruje grupy z `form-schema`; `display_mode=tab` → zakładka, `display_mode=stacked` → sekcja inline na bieżącej zakładce.
- Atrybut typu `relation` w grupie `stacked` → renderuje się inline w tej grupie z widgetem relacji (nie polem tekstowym).
- Atrybut typu `relation` w grupie `tab` → renderuje się w tej zakładce.
- Usunąć wszelkie gałęzie „jeśli typ == relation → wymuś zakładkę Powiązania" — placement nigdy nie zależy od typu.
- Kolejność grup i atrybutów zachowana z `form-schema`.

**Acceptance criteria:**
- [ ] AC-1: Grupa `display_mode=tab` → renderuje się jako osobna zakładka
- [ ] AC-2: Grupa `display_mode=stacked` → renderuje się jako sekcja inline
- [ ] AC-3: Atrybut `relation` w grupie stacked → inline, z widgetem relacji (powtórzenie scenariusza „Smoke test" — teraz zgodne ze spec)
- [ ] AC-4: Atrybut `relation` w grupie tab → w tej zakładce
- [ ] AC-5: Zmiana `display_mode` grupy → karta obiektu reaguje (zakładka ↔ inline)
- [ ] AC-6: Regresja — istniejące formatki produktów renderują się bez zmian wizualnych
- [ ] AC-7: E2E — produkt z grupą tab i grupą stacked, oba renderują się poprawnie

**Files affected:** `apps/admin/src/components/object-editor/DetailDynamicForm.tsx` (lub aktualny renderer karty), komponent zakładek/sekcji.

**Testing:** Unit (Vitest — logika placementu) + E2E Playwright (tab vs stacked, relacja inline) + regresja istniejących E2E formularzy + manual smoke test na żywym stacku.

**DoD:** AC + wszystkie testy zielone + E2E + smoke test (DevTools Network 200, brak błędów w Console) + CI green.

---

## MODR-04 / #926: feat(admin): tab/stacked toggle per AttributeGroup in ObjectType wizard

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by MODR-01 (#923).

**Risk flags:**
- Przełącznik zapisuje `display_mode` na przypisaniu grupy do ObjectType (MODR-01) — nie na globalnej grupie.

**Cel:** W kroku „Atrybuty" wizarda ObjectType — przy każdej AttributeGroup przełącznik `tab` / `stacked`. To jedyne miejsce, w którym user steruje czy grupa jest zakładką.

**Scope:**
- Krok „Atrybuty" wizarda — każda grupa atrybutów ma kontrolkę `tab | stacked` (segmented control / select).
- Zmiana → `PATCH` przypisania grupy (MODR-01 API), optymistyczny update + obsługa błędu.
- Podgląd w panelu „PODGLĄD" wizarda reaguje na zmianę (zakładka ↔ sekcja) — jeśli podgląd istnieje.
- i18n — klucze `pl`/`en` dla etykiet `tab`/`stacked` + tooltip wyjaśniający.

**Acceptance criteria:**
- [ ] AC-1: Każda grupa w kroku „Atrybuty" ma przełącznik tab/stacked
- [ ] AC-2: Zmiana przełącznika → `display_mode` zapisany (integracja z MODR-01)
- [ ] AC-3: Po ponownym otwarciu wizarda — stan przełącznika odzwierciedla zapisany `display_mode`
- [ ] AC-4: Błąd zapisu → komunikat, stan UI nie rozjeżdża się z backendem
- [ ] AC-5: Stringi przez `t()` (brak literałów)
- [ ] AC-6: E2E — ustaw grupę na stacked, zapisz, otwórz kartę obiektu, grupa inline

**Files affected:** `apps/admin/src/components/modeling/ObjectTypeWizard*.tsx` (krok „Atrybuty"), komponent wiersza grupy, pliki i18n `pl/`, `en/`.

**Testing:** Unit (Vitest) + E2E Playwright (toggle → zapis → efekt na karcie obiektu) + manual smoke test.

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + CI green.

---

## MODR-05 / #927: feat(admin): relation attribute visual marker (link icon/badge)

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **2-3h**

**Dependencies:** Blocked by MOD-12 (#904 — zakładka „Powiązania" / komponenty relacji istnieją).

**Risk flags:**
- Brak — czysty UI polish.

**Cel:** Atrybut typu `relation` ma czytelne oznaczenie wizualne (ikona ogniwa/linku przy etykiecie), tak by user odróżnił go od zwykłego pola — szczególnie gdy renderuje się inline w zwykłej grupie (MODR-03).

**Scope:**
- Ikona/badge relacji przy etykiecie atrybutu `relation` — w renderze inline (grupa stacked) i w zakładce (grupa tab).
- Spójne z istniejącym wzorcem badge'y (provenance) — ten sam rozmiar/pozycja.
- Tooltip — krótka informacja „pole linkuje do obiektów: <ObjectType>".
- i18n dla tooltipa.

**Acceptance criteria:**
- [ ] AC-1: Atrybut `relation` inline → ikona relacji przy etykiecie
- [ ] AC-2: Atrybut `relation` w zakładce → ikona relacji przy etykiecie
- [ ] AC-3: Zwykły atrybut (`text`/`number`) → brak ikony relacji
- [ ] AC-4: Tooltip pokazuje target ObjectType, stringi przez `t()`
- [ ] AC-5: axe-core — ikona ma dostępną etykietę (nie sam dekoracyjny element)

**Files affected:** komponent etykiety atrybutu / renderer pola w `apps/admin/src/components/object-editor/`, pliki i18n.

**Testing:** Unit (Vitest — ikona renderuje się dla typu relation) + E2E Playwright (widoczność ikony) + axe-core.

**DoD:** AC + wszystkie testy zielone + E2E + axe-core pass + CI green.

---

## MODR-06 / #928: feat(admin): "Powiązania" tab visibility — group attrs OR reverse relations

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **3-4h**

**Dependencies:** Blocked by MOD-07 (#899 — reverse relations endpoint), MODR-02 (#924 — grupa `relations` seedowana).

**Risk flags:**
- Powiązania zwrotne nie należą do żadnej grupy — bez tej logiki obiekt będący tylko targetem relacji nie pokazałby zakładki, mimo że ma reverse.

**Cel:** Zakładka „Powiązania" jest widoczna, gdy grupa `relations` ma atrybuty **LUB** obiekt ma powiązania zwrotne. Reverse renderują się w tej zakładce nawet gdy grupa nie ma atrybutów forward.

**Scope:**
- Widoczność zakładki „Powiązania" = (grupa `relations` ma ≥1 atrybut) OR (obiekt ma ≥1 powiązanie zwrotne).
- Sygnał o istnieniu reverse — lekki: `count` z `GET .../relations/reverse` lub dedykowany lekki endpoint/flaga (unikać pełnego pobierania reverse tylko po to, by zdecydować o widoczności).
- Gdy zakładka widoczna tylko z powodu reverse → renderuje wyłącznie sekcję read-only „Powiązania zwrotne".

**Acceptance criteria:**
- [ ] AC-1: Obiekt z atrybutem forward `relation` → zakładka „Powiązania" widoczna
- [ ] AC-2: Obiekt bez forward, ale z reverse → zakładka widoczna, renderuje tylko reverse
- [ ] AC-3: Obiekt bez forward i bez reverse → zakładka ukryta
- [ ] AC-4: Dodanie pierwszego powiązania zwrotnego (z innego obiektu) → zakładka pojawia się po odświeżeniu
- [ ] AC-5: E2E — obiekt-target up-sell pokazuje zakładkę „Powiązania" z sekcją reverse

**Files affected:** `apps/admin/src/components/object-editor/RelationsTab.tsx`, logika widoczności zakładek w rendererze, ewentualny lekki endpoint/flaga backend dla `has reverse`.

**Testing:** Unit (Vitest — logika widoczności) + E2E Playwright (3 scenariusze widoczności) + manual smoke test.

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + CI green.

---

## MODR-07 / #929: feat(admin): relation attribute configurator — default group "Powiązania"

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **2-3h**

**Dependencies:** Blocked by MOD-13 (#905 — konfigurator atrybutu `relation`), MODR-02 (#924 — grupa `relations` seedowana).

**Risk flags:**
- Brak — to default, nie przymus. User może wybrać inną grupę.

**Cel:** Przy tworzeniu atrybutu typu `relation` w Modelowaniu konfigurator pre-zaznacza grupę „Powiązania" jako domyślną. Leniwa ścieżka → relacje zbierają się w jednej zakładce. Świadoma ścieżka → user zmienia grupę (np. „Producent" do grupy tożsamości).

**Scope:**
- W konfiguratorze atrybutu (MOD-13) — gdy typ = `relation`, selektor grupy ma domyślnie wybraną grupę `relations`.
- Default nadpisywalny — user może wybrać dowolną inną grupę.
- Hint przy selektorze — „domyślnie w zakładce Powiązania; możesz przenieść do dowolnej grupy".
- i18n dla hinta.

**Acceptance criteria:**
- [ ] AC-1: Wybór typu `relation` → selektor grupy domyślnie = „Powiązania"
- [ ] AC-2: User zmienia grupę na inną → wybór respektowany przy zapisie
- [ ] AC-3: Typ inny niż `relation` → brak wymuszonego defaultu „Powiązania"
- [ ] AC-4: Hint widoczny, stringi przez `t()`
- [ ] AC-5: E2E — utwórz atrybut relation bez zmiany grupy → ląduje w grupie „Powiązania"

**Files affected:** `apps/admin/src/components/modeling/RelationConfigPanel.tsx` / `AttributeEditor.tsx`, pliki i18n.

**Testing:** Unit (Vitest — default selekcji) + E2E Playwright (default + override) + manual smoke test.

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + CI green.

---

## MODR-08 / #930: feat(admin): relation widget — rich preview card mode

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by MOD-12 (#904 — zakładka „Powiązania" / widget relacji).

**Risk flags:**
- Rich preview pobiera dane podglądowe targetów — N powiązań = N obiektów do pokazania. Pobranie batchowe (jeden request po liście ID), nie N requestów.
- Konfiguracja pól podglądu — mała delta na atrybucie `relation`; jeśli pominięta, MVP pokazuje nazwę + miniaturkę po konwencji.

**Cel:** Widget relacji renderuje powiązany obiekt jako **kartę podglądu** (nazwa + miniaturka + 1-2 wybrane pola targetu), nie goły chip. User widzi dość, by się zorientować, bez wychodzenia z obiektu.

**Scope:**
- Tryb „rich preview card" w widgecie relacji — karta z: nazwa targetu, miniaturka (jeśli target ma atrybut `asset`/main image), opcjonalnie 1-2 dodatkowe pola.
- Konfiguracja, które pola targetu pokazać w podglądzie — `relation_preview_fields JSONB` na atrybucie `relation` (lista kodów atrybutów; pusta = default nazwa + miniaturka). Backend: kolumna + akceptacja w `PATCH /api/attributes`. Frontend: edytor listy w konfiguratorze relacji.
- Batchowe pobranie danych podglądu targetów (jeden request po liście ID).
- Klik w kartę → otwiera pełny obiekt targetu.

**Acceptance criteria:**
- [ ] AC-1: Powiązany obiekt renderuje się jako karta (nazwa + miniaturka), nie sam tekst
- [ ] AC-2: `relation_preview_fields` skonfigurowane → karta pokazuje wskazane pola
- [ ] AC-3: `relation_preview_fields` puste → default (nazwa + miniaturka)
- [ ] AC-4: N powiązań → jeden batchowy request danych podglądu (weryfikacja w Network)
- [ ] AC-5: Klik w kartę → nawigacja do pełnego obiektu targetu
- [ ] AC-6: E2E — produkt z relacją many, karty podglądu renderują nazwę + miniaturkę

**Files affected:** `apps/admin/src/components/object-editor/RelationGrid.tsx` / `RelationPicker.tsx`, nowy `RelationPreviewCard.tsx`, konfigurator relacji, migracja `attributes.relation_preview_fields`, `AttributeController`.

**Testing:** Unit (Vitest — render karty) + integration/ApiTestCase (`relation_preview_fields` zapis) + E2E Playwright (karty podglądu) + manual smoke test (batchowy request, status 200).

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + PHPStan max (backend) + CI green.

---

## MODR-09 / #931: feat(admin): relation widget — inline create of target object

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by MOD-12 (#904 — widget relacji).

**Risk flags:**
- Inline create otwiera formularz targetowego ObjectType — musi respektować jego `form-schema` (primary category itd. jeśli kategoryzowalny).
- Nowo utworzony obiekt musi się automatycznie podpiąć jako powiązanie po zapisie.

**Cel:** Z pickera relacji user może utworzyć nowy obiekt targetowy „w locie" — drawer/modal z formularzem, zapis, auto-podpięcie — bez opuszczania edytowanego obiektu.

**Scope:**
- W pickerze relacji akcja „+ Nowy <ObjectType>" → otwiera drawer z formularzem create targetowego ObjectType.
- Formularz w drawerze korzysta z istniejącego flow create (modal create obiektu, MOD-11) — bez duplikacji logiki.
- Po zapisie — nowy obiekt automatycznie dodany jako powiązanie do bieżącego atrybutu `relation`.
- Respektuje cardinality (one → zastępuje, many → dokłada).
- Anulowanie drawera → brak zmian.

**Acceptance criteria:**
- [ ] AC-1: Picker relacji ma akcję „+ Nowy <ObjectType>"
- [ ] AC-2: Drawer renderuje formularz create targetowego ObjectType
- [ ] AC-3: Zapis → nowy obiekt utworzony i auto-podpięty jako powiązanie
- [ ] AC-4: Cardinality respektowane (one zastępuje, many dokłada)
- [ ] AC-5: Anulowanie → brak nowego obiektu, brak powiązania
- [ ] AC-6: Target kategoryzowalny → drawer wymusza primary category (spójne z MOD-11)
- [ ] AC-7: E2E — z karty Samochodu utwórz nowy Salon inline, sprawdź podpięcie

**Files affected:** `apps/admin/src/components/object-editor/RelationPicker.tsx`, nowy `RelationInlineCreateDrawer.tsx`, reuse `CreateObjectModal`.

**Testing:** Unit (Vitest) + E2E Playwright (inline create + auto-podpięcie) + manual smoke test (status 201 na create, powiązanie widoczne).

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + CI green.

---

## MODR-10 / #932: feat(admin): relation widget — inline expand/edit of related object

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by MOD-12 (#904 — widget relacji).

**Risk flags:**
- Najcięższy ticket batcha. Edycja pól powiązanego obiektu in-place — edytujesz *współdzielony* obiekt; zmiana wpływa na wszystkich, którzy go referują. UI musi to jasno komunikować (to NIE jest kopia lokalna).
- Zapis edycji inline = zapis targetowego obiektu, nie powiązania — osobny request do `/api/objects/{targetId}`.
- Konflikt z innymi edycjami — optymistyczny update + obsługa 409/stale.

**Cel:** Kartę powiązanego obiektu w widgecie relacji można rozwinąć i edytować jego pola in-place, bez przechodzenia do osobnego ekranu — UX „formatka w zakładce", przy zachowaniu modelu asocjacji (obiekt współdzielony).

**Scope:**
- Karta powiązanego obiektu (MODR-08) — akcja „rozwiń" odsłania edytowalne pola targetu (podzbiór `form-schema` targetu).
- Edycja pól → zapis do `/api/objects/{targetId}` (zapis obiektu targetu, nie powiązania).
- Wyraźny komunikat UX — „edytujesz współdzielony obiekt <X>; zmiana dotyczy wszystkich powiązań".
- Optymistyczny update + obsługa konfliktu (409/stale data).
- Zwiń → karta wraca do trybu podglądu (MODR-08).
- Tryb expand/edit włączany flagą — domyślnie podgląd (MODR-08), expand opcjonalny.

**Acceptance criteria:**
- [ ] AC-1: Karta powiązanego obiektu ma akcję „rozwiń"
- [ ] AC-2: Rozwinięcie → edytowalne pola targetu
- [ ] AC-3: Edycja + zapis → wartości zapisane na obiekcie targetu (weryfikacja: otwórz target osobno, zmiana widoczna)
- [ ] AC-4: Komunikat o edycji obiektu współdzielonego widoczny
- [ ] AC-5: Konflikt zapisu (stale) → czytelny błąd, brak cichej utraty danych
- [ ] AC-6: Zwiń → powrót do trybu podglądu
- [ ] AC-7: E2E — rozwiń salon na karcie auta, zmień adres, zapisz, zweryfikuj na obiekcie Salonu

**Files affected:** `apps/admin/src/components/object-editor/RelationPreviewCard.tsx` (rozszerzenie o tryb edit), nowy `RelationInlineEditPanel.tsx`.

**Testing:** Unit (Vitest) + E2E Playwright (expand → edit → zapis → weryfikacja na targecie) + manual smoke test (status 200 na PATCH targetu, brak błędów Console) + axe-core.

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + axe-core pass + CI green.

---

## MODR-11 / #933: docs(modeling): update §3.5 mini-spec + ADR-014 — Option 2 + rejected decisions

**Typ:** `docs` | **Epik:** UI-08 | **Estymacja:** **2-3h**

**Dependencies:** Równolegle (brak blokad).

**Cel:** Zsynchronizować dokumentację z decyzją Opcja 2 i zarejestrować świadomie odrzucone alternatywy.

**Scope:**
- `feature-modeling-data-model.md` §3.5 — przeredagować: skreślić „fixed Powiązania tab"; zapisać że zakładka „Powiązania" = render seedowanej grupy `relations`, placement zawsze idzie po AttributeGroup + `display_mode`. Atrybut `relation` to zwykły atrybut — placement po grupie, widget po typie.
- Dopisać edge case widoczności zakładki „Powiązania" (grupa ma atrybuty LUB obiekt ma reverse).
- Dopisać `display_mode` na junction ObjectType×AttributeGroup do schematu §5.
- Sekcja „Poza zakresem" (§11) — dopisać świadomie odrzucone: (a) warstwa Object Template, (b) osobny krok „Objekty" w wizardzie, (c) reguła „relacja zawsze zakładka", (d) mechanizm embed/kompozycji (composition vs association — embed odłożony, trigger: byt nigdy nie współdzielony bez własnej tożsamości), (e) Families à la Akeneo/Pimcore-classes — nie teraz, warunek: resolver pozostaje czystą abstrakcją źródeł nakładki.
- ADR-014 — krótka nota uzupełniająca: placement atrybutów rozstrzygnięty przez `display_mode` grupy (Opcja 2).

**Acceptance criteria:**
- [ ] AC-1: §3.5 przeredagowane — brak sprzeczności „fixed tab" vs „relacja = atrybut"
- [ ] AC-2: `display_mode` w schemacie §5 mini-speca
- [ ] AC-3: Edge case widoczności zakładki „Powiązania" udokumentowany
- [ ] AC-4: §11 zawiera 5 odrzuconych decyzji z uzasadnieniem
- [ ] AC-5: ADR-014 ma notę o Opcji 2
- [ ] AC-6: Cross-reference do tego backlogu (`feature-modeling-relations-ux-tickets.md`)

**Files affected:** `Project Plan/UI/feature-modeling-data-model.md`, `Project Plan/01-architektura-pim.md` (ADR-014).

**Testing:** N/A (dokumentacja) — review spójności z decyzjami.

**DoD:** AC + review + merge.

---

## Podsumowanie

| Ticket | Issue | Estymacja | Warstwa | Punkt |
|---|---|---|---|---|
| MODR-01 display_mode na junction | #923 | 4-6h | Backend foundation | 4 |
| MODR-02 od-hardkodowanie Multimedia/Powiązania | #924 | 3-5h | Backend/seed | 3 |
| MODR-03 renderer — placement po grupie | #925 | 5-8h | Frontend | 1, 2 |
| MODR-04 przełącznik tab/stacked w wizardzie | #926 | 4-6h | Frontend | 5 |
| MODR-05 ikona/badge relacji | #927 | 2-3h | Frontend | 6 |
| MODR-06 widoczność zakładki Powiązania | #928 | 3-4h | Frontend | 7 |
| MODR-07 konfigurator — domyślna grupa | #929 | 2-3h | Frontend | 8 |
| MODR-08 widget — rich preview card | #930 | 8-12h | Full-stack | 9 |
| MODR-09 widget — inline create | #931 | 6-8h | Frontend | 10 |
| MODR-10 widget — inline expand/edit | #932 | 8-12h | Frontend | 11 |
| MODR-11 docs §3.5 + ADR-014 | #933 | 2-3h | Docs | 1 (spec) |
| **TOTAL** | | **~47-70h** | **11 ticketów** | |

~1.5-2 tygodnie solo dev.

**Sugerowana kolejność:** MODR-01 → MODR-02 → MODR-03 + MODR-04 (równolegle) → MODR-05 / MODR-06 / MODR-07 → MODR-08 → MODR-09 / MODR-10 → MODR-11 (równolegle w dowolnym momencie).

**Decyzje odrzucone (NIE tickety — trafiają do §11 mini-speca przez MODR-11):** warstwa Object Template, osobny krok „Objekty" w wizardzie, reguła „relacja zawsze zakładka", mechanizm embed/kompozycji w MVP, Families à la Akeneo/Pimcore-classes.

---

*Backlog wygenerowany 2026-05-24 jako rozstrzygnięcie nieścisłości §3.5 (Opcja 2). Agent kodujący: utwórz GitHub Issues z tych ticketów (tytuł = nagłówek `## MODR-NN:` bez prefiksu numeru, body = treść ticketu, labels `modeling`+`adr-014`+`epik-ui-08`). Per ticket dotykający >3 plików — Plan Mode przed implementacją (CLAUDE.md). Każdy ticket: wszystkie testy zielone w DoD — co się da pokryć.*
