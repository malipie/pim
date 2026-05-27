# Tickety — Modelowanie: Powiązania bez seedu (Option Y — korekta MODR-02/06/07)

**Typ dokumentu:** Backlog ticketów — ready-to-paste GitHub Issues
**Status:** Draft — supersedes MODR-02 (#924), MODR-06 (#928), MODR-07 (#929) z pliku [`feature-modeling-relations-ux-tickets.md`](feature-modeling-relations-ux-tickets.md)
**Data:** 2026-05-26
**Powiązane:**
- [`feature-modeling-relations-ux-tickets.md`](feature-modeling-relations-ux-tickets.md) — pierwotny batch MODR-01..11 (część stale aktualna)
- [ADR-014](../01-architektura-pim.md) — *„Model dystrybucji atrybutów + relacje obiekt↔obiekt"*
- [`feature-modeling-data-model.md`](feature-modeling-data-model.md) — mini-spec implementacyjny (kontrakt, §3.5 — wymaga update'u)

> **Cel:** decyzja **Option Y** — pełne odseedowanie grupy „Powiązania". User świadomie tworzy grupę atrybutów (dowolnej nazwy) z `display_mode=tab`, wrzuca atrybuty typu `relation`. Brak seedowanej systemowej grupy „Powiązania". Brak flagi `has_relations`. Brak „magicznego" pojawiania się zakładki. Pełna symetria z resztą atrybutów: relacja to typ atrybutu jak każdy inny — placement po grupie, grupa tworzona świadomie przez usera.
>
> **Kontekst decyzji (2026-05-26):** seedowana grupa Powiązań tworzyła problem discoverability — user nie miał jak odkryć, że dodanie atrybutu typu `relation` zmaterializuje zakładkę. Flaga `has_relations` zostałaby pułapką proliferacji flag (`has_pricing`, `has_translations`...). Y eliminuje magię — każda zakładka istnieje, bo grupa została świadomie utworzona z `display_mode=tab`.
>
> **POZA ZAKRESEM:** Multimedia (idzie ścieżką **Droga B — Moduł Media Library**, osobny refactor) i Kategorie (osobny System Module). Ten batch dotyczy WYŁĄCZNIE Powiązań.
>
> **4 tickety, ~17-26h** (~3-5 dni solo dev).

---

## Konwencje

- Numer: `MODRC-NN`. Po utworzeniu w GitHub: dopisany numer issue.
- Pola: Typ (Conventional Commits) / Epik / Estymacja / Dependencies / Supersedes / Risk flags / Cel / Scope / Acceptance criteria / Files affected / Testing / DoD.
- Tytuł issue po angielsku (Conventional Commits), opis po polsku.
- Labels: `modeling` + `adr-014` + `epik-ui-08` + `option-y` + risk flag jeśli dotyczy.
- **Reguła testów:** każdy ticket w DoD ma WSZYSTKIE testy na zielono — co się da pokryć. Backend: PHPUnit + ApiTestCase. Frontend z widoczną zmianą: Vitest + Playwright E2E + manual smoke test (SMOKE TEST RULE, CLAUDE.md).

## Graf zależności

```
MODRC-01 (kasuj seed grupy „Powiązania" — supersedes MODR-02 dla Powiązań) ── foundation
        │
        ├── MODRC-02 (konfigurator atrybutu relation: brak defaultu, inline create grupy)
        │       (supersedes MODR-07)
        │
        ├── MODRC-03 (system section „Powiązania zwrotne" dla reverse relations)
        │       (supersedes MODR-06)
        │
        └── MODRC-04 (docs: §3.5 + ADR-014 + lessons.md — decyzja Y)
                (supersedes/extends MODR-11)
```

---

## MODRC-01 / #1067: refactor(catalog): remove seeded "Powiązania" AttributeGroup — un-seed, supersedes MODR-02

**Typ:** `refactor` | **Epik:** UI-08 | **Estymacja:** **2-4h**

**Supersedes:** MODR-02 (#924) dla części Powiązań. (Część Multimedia z MODR-02 idzie osobnym torem przez Moduł Media Library — nie ten batch.)

**Dependencies:** Blocks MODRC-02, MODRC-03. Blocked by — brak.

**Risk flags:**
- Jeśli seedowana grupa `relations` istnieje w bazie i ma przypięte atrybuty typu `relation` — migracja musi zdecydować, co z nimi zrobić: (a) zostawić atrybuty bez grupy (orphaned), (b) przepisać do grupy „Niezgrupowane" / "Default", (c) zostawić grupę ale zdjąć z niej built-in flag (user może ją usunąć ręcznie). Decyzja w pre-flight.
- Pre-flight: zweryfikować obecny stan — czy MODR-02 (#924) został zmergowany? Jeśli tak, grupa `relations` jest w DB i trzeba ją odseedować. Jeśli nie — MODRC-01 po prostu odwołuje plan MODR-02 dla Powiązań.

**Cel:** Usunąć systemową seedowaną grupę „Powiązania" jako built-in byt. Każda grupa atrybutów (w tym ta, w której user trzyma relacje) musi być utworzona świadomie przez usera. Brak hardcoded i brak seed dla Powiązań.

**Scope:**
- Jeśli MODR-02 zmergowane: migracja DOWN seedu grupy `relations` — usunąć grupę i jej built-in flag.
- Jeśli MODR-02 niezmergowane: usuń scope „grupa relations" z planu MODR-02; zostaje (lub anuluj cały MODR-02 jeśli nic więcej w nim nie ma poza Multimedia, która idzie osobno).
- Decyzja migracyjna dla istniejących atrybutów `relation` przypisanych do skasowanej grupy (jeden z wariantów a/b/c — udokumentuj wybór w PR description).
- Build-in flag na żadnej grupie typu „Powiązania" nie istnieje po tym ticket.

**Acceptance criteria:**
- [ ] AC-1: Brak seedowanej grupy `relations`/„Powiązania" w bazie po migracji
- [ ] AC-2: Brak built-in flagi na jakiejkolwiek grupie powiązanej z relacjami
- [ ] AC-3: Istniejące atrybuty `relation` zachowane (nie skasowane) — przepisane do grupy default / zostawione orphaned, zgodnie z decyzją migracyjną
- [ ] AC-4: Migracja DOWN przywraca poprzedni stan (lub udokumentowane czemu nieodwracalna)
- [ ] AC-5: form-schema dla obiektów z atrybutami `relation` — atrybuty nadal renderują się (w nowej grupie lub orphaned, nie znikają)
- [ ] AC-6: Cross-tenant — migracja izolowana per tenant (TenantFilter)

**Files affected:** `src/Catalog/Doctrine/Migrations/Version*.php`, seed/fixtures AttributeGroup (usunąć wpis dla `relations`).

**Testing:** Integration (migracja UP/DOWN + weryfikacja stanu) + ApiTestCase (form-schema renderuje atrybuty `relation` po migracji) + manual smoke test (otwórz produkt z atrybutami relation, sprawdź czy są widoczne).

**DoD:** AC + wszystkie testy zielone + smoke test (paste przed/po form-schema) + PHPStan max + CI green + decyzja migracyjna udokumentowana w PR.

---

## MODRC-02 / #1068: feat(admin): relation attribute configurator — no default group, inline create-group action

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **3-5h**

**Supersedes:** MODR-07 (#929).

**Dependencies:** Blocked by MODRC-01, MOD-13 (#905 — konfigurator atrybutu relation istnieje).

**Risk flags:**
- Inline create grupy z poziomu konfiguratora atrybutu = mały sub-flow. Musi respektować walidacje nazwy grupy + `display_mode`.
- Bez domyślnej grupy user może świadomie zostawić atrybut bez przypisania → atrybut „orphaned" w UI. Trzeba zdecydować, czy walidacja wymaga grupy.

**Cel:** Konfigurator atrybutu typu `relation` w Modelowaniu **nie pre-selectuje żadnej grupy** — user wybiera z istniejących grup ObjectType, albo tworzy nową inline. Brak magicznego defaultu „Powiązania".

**Scope:**
- W konfiguratorze atrybutu (MOD-13), gdy `type = relation` — selektor grupy bez domyślnego wyboru. User musi świadomie wybrać.
- Akcja „+ Utwórz nową grupę" w selektorze — otwiera inline mini-form (nazwa grupy + `display_mode` toggle tab/stacked + opcjonalnie position).
- Po utworzeniu nowej grupy → grupa zapisana, atrybut auto-przypięty do niej.
- Walidacja: atrybut musi mieć grupę (zapis bez grupy → 422). Brak orphaned atrybutów na poziomie persystencji.
- i18n: stringi przez `t()`, brak literałów.
- **Decyzja:** ten sam pattern stosuje się do KAŻDEGO typu atrybutu, nie tylko `relation`. Konsystencja — nie ma typu atrybutu z magicznym domyślnym przypisaniem do grupy.

**Acceptance criteria:**
- [ ] AC-1: Wybór typu `relation` → selektor grupy bez pre-selectu
- [ ] AC-2: User wybiera istniejącą grupę → zapis OK
- [ ] AC-3: User klika „+ Utwórz nową grupę" → inline form z nazwą + display_mode
- [ ] AC-4: Nowa grupa zapisana, atrybut auto-podpięty do niej
- [ ] AC-5: Zapis bez wybranej grupy → 422 z czytelnym komunikatem
- [ ] AC-6: Stringi przez `t()` (brak literałów)
- [ ] AC-7: E2E — pełen flow „utwórz atrybut relation → utwórz nową grupę inline → zapis → grupa i atrybut widoczne na karcie obiektu"

**Files affected:** `apps/admin/src/components/modeling/RelationConfigPanel.tsx` (lub aktualny konfigurator), nowy `InlineCreateGroupForm.tsx`, walidator backend (`AttributeController` / serwis).

**Testing:** Unit (Vitest — logika selektora + walidacja braku defaultu) + ApiTestCase (zapis atrybutu bez grupy → 422) + E2E Playwright (pełen flow z inline create grupy) + manual smoke test.

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + CI green.

---

## MODRC-03 / #1069: feat(admin): system "Powiązania zwrotne" section — auto-rendered when object is target of reverse relations

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **6-8h**

**Supersedes:** MODR-06 (#928).

**Dependencies:** Blocked by MOD-07 (#899 — reverse relations endpoint), MODRC-01 (#TBD).

**Risk flags:**
- Reverse relations nie należą do żadnej user-managed grupy — wymagają systemowego domu. To jedyna „magiczna" sekcja w systemie, ale uzasadniona: user nie może z góry zaprojektować grupy dla powiązań, które dopiero inni do niego stworzą.
- Sekcja jest **systemowa**, read-only, oddzielona wizualnie od user-defined zakładek.
- Reverse lookup przy 200k+ SKU — performance po indeksie `idx_object_relations_target` (z MOD-02).

**Cel:** Wprowadzić systemową sekcję „Powiązania zwrotne" (lub „Linki do tego obiektu") — pojawia się **tylko gdy obiekt jest celem przynajmniej jednej relacji**. Niezależna od user-defined grup atrybutów. Read-only.

**Scope:**
- Komponent `SystemReverseRelationsSection` — renderuje listę reverse pogrupowaną per (source ObjectType, atrybut źródłowy).
- Renderuje się jako **wirtualna zakładka** „Powiązania zwrotne" (lub wizualnie oddzielona sekcja na końcu listy zakładek — alternatywa do rozważenia w PR description).
- **Widoczność:** zakładka pojawia się gdy zapytanie `GET /api/objects/{id}/relations/reverse` zwraca ≥1 rekord. Brak reverse → brak zakładki.
- **Wizualna distinguishability:** sekcja oznaczona ikoną „system" / inną stylistyką niż user-defined zakładki, żeby user widział: „to nie jest część mojego modelu, to systemowy widok".
- Read-only: brak akcji edycji w tej sekcji (edycja po stronie źródła relacji).
- Klik w pozycję reverse → otwiera obiekt źródłowy.
- Performance — query po `idx_object_relations_target`, target <100ms dla dataset 50k+ powiązań.
- i18n: nazwa zakładki i nagłówki sekcji przez `t()`.

**Acceptance criteria:**
- [ ] AC-1: Obiekt bez reverse → zakładka „Powiązania zwrotne" NIE pojawia się
- [ ] AC-2: Obiekt z ≥1 reverse → zakładka „Powiązania zwrotne" pojawia się, renderuje listę
- [ ] AC-3: Reverse pogrupowane per source ObjectType + atrybut źródłowy
- [ ] AC-4: Sekcja wizualnie odróżnialna od user-defined zakładek (ikona/etykieta „system")
- [ ] AC-5: Klik w pozycję reverse → nawigacja do obiektu źródłowego
- [ ] AC-6: Brak akcji edycji w tej sekcji (read-only enforced)
- [ ] AC-7: Performance — reverse lookup <100ms na dataset 50k+ powiązań
- [ ] AC-8: E2E — utwórz obiekt A z relacją do B → otwórz B → zakładka „Powiązania zwrotne" widoczna z wpisem o A

**Files affected:** `apps/admin/src/components/object-editor/SystemReverseRelationsSection.tsx` (nowy), integracja z rendererem karty obiektu (logika widoczności zakładki), ewentualnie lekki endpoint `GET /api/objects/{id}/relations/reverse/count` jeśli pełne pobranie po to tylko, by zdecydować o widoczności, jest zbyt drogie.

**Testing:** Unit (Vitest — logika widoczności + render) + Integration/ApiTestCase (reverse endpoint) + Performance (benchmark) + E2E Playwright (US-MOD-005 analog) + manual smoke test + axe-core (sekcja systemowa dostępna).

**DoD:** AC + wszystkie testy zielone + E2E + smoke test + benchmark w PR + axe-core pass + CI green.

---

## MODRC-04 / #1070: docs(modeling): §3.5 mini-spec + ADR-014 + lessons.md — Option Y (full un-seed)

**Typ:** `docs` | **Epik:** UI-08 | **Estymacja:** **2-3h**

**Supersedes/extends:** MODR-11 (#933).

**Dependencies:** Równolegle z MODRC-01..03 (brak blokad implementacyjnych).

**Cel:** Zaktualizować dokumentację, żeby odzwierciedlała finalną decyzję Option Y — brak seedu grupy Powiązania, brak flagi `has_relations`, systemowa sekcja „Powiązania zwrotne".

**Scope:**
- **`feature-modeling-data-model.md` §3.5** — przeredagować:
  - Skreślić „seedowana grupa Powiązania" / „domyślna grupa dla relacji".
  - Zapisać: relacja to zwykły typ atrybutu, placement po user-defined grupie + `display_mode`, ŻADNA grupa nie jest seedowana ani built-in dla relacji.
  - Reverse relations renderują się w **systemowej sekcji „Powiązania zwrotne"** (jedyna wirtualna zakładka), widoczna gdy obiekt jest targetem.
- **`feature-modeling-data-model.md` §11 (Poza zakresem)** — dopisać świadomie odrzucone:
  - (a) Flaga `has_relations` na ObjectType — odrzucona jako pułapka proliferacji flag.
  - (b) Seed grupy „Powiązania" jako built-in — odrzucony jako anti-pattern discoverability (magiczne pojawianie się zakładki).
  - (c) „Magiczna" widoczność zakładki przy populacji — odrzucona.
- **ADR-014** — krótka nota uzupełniająca: placement relacji rozstrzygnięty przez Option Y (full un-seed, system reverse section).
- **`agent/lessons.md`** — wpis świadomego odejścia: „2026-05-26 — MODR-02/06/07 zastąpione przez MODRC-01..03 (Option Y). Decyzja: zero seedu/flag dla Powiązań; tylko systemowa sekcja `Powiązania zwrotne` jest auto-generowana. Powód: discoverability + symetria z innymi typami atrybutów."
- **Cross-reference** do tego batcha (`feature-modeling-relations-option-y-tickets.md`) z `feature-modeling-relations-ux-tickets.md` (nota „MODR-02/06/07 superseded przez MODRC-01..03").

**Acceptance criteria:**
- [ ] AC-1: §3.5 przeredagowane — brak wzmianek o seedowanej grupie Powiązań
- [ ] AC-2: §3.5 zawiera opis systemowej sekcji „Powiązania zwrotne" + warunki widoczności
- [ ] AC-3: §11 zawiera 3 odrzucone alternatywy (flaga, seed, magiczna widoczność)
- [ ] AC-4: ADR-014 ma notę o Option Y
- [ ] AC-5: `agent/lessons.md` ma wpis świadomego odejścia z datą i powodem
- [ ] AC-6: `feature-modeling-relations-ux-tickets.md` ma notę o supersedowaniu MODR-02/06/07

**Files affected:** `Project Plan/UI/feature-modeling-data-model.md`, `Project Plan/01-architektura-pim.md` (ADR-014), `agent/lessons.md`, `Project Plan/UI/feature-modeling-relations-ux-tickets.md` (nota o supersede).

**Testing:** N/A (dokumentacja) — review spójności z decyzjami.

**DoD:** AC + review + merge.

---

## Podsumowanie

| Ticket | Estymacja | Warstwa | Supersedes |
|---|---|---|---|
| MODRC-01 kasuj seed grupy Powiązania | 2-4h | Backend | MODR-02 (#924, część Powiązań) |
| MODRC-02 konfigurator relacji bez defaultu + inline create | 3-5h | Frontend + backend walidacja | MODR-07 (#929) |
| MODRC-03 system section „Powiązania zwrotne" | 6-8h | Frontend + perf | MODR-06 (#928) |
| MODRC-04 docs §3.5 + ADR-014 + lessons | 2-3h | Docs | MODR-11 (#933) — rozszerza |
| **TOTAL** | **~13-20h** | **4 tickety** | |

~3-4 dni solo dev.

**Sugerowana kolejność:** MODRC-01 (foundation, kasacja seedu) → MODRC-02 + MODRC-03 (równolegle, frontend) → MODRC-04 (docs, równolegle).

**Co NIE wchodzi w ten batch:**
- Multimedia → idzie ścieżką Droga B (Moduł Media Library) — osobny refactor.
- Kategorie → osobny System Module (osobny refactor).
- Multimedia/Powiązania jako AttributeGroup część MODR-02 — Multimedia idzie do Modułu, Powiązania kasowane tym batchem.

**Decyzje świadomie odrzucone (zarejestrowane w MODRC-04):**
- Flaga `has_relations` na ObjectType (proliferacja flag → Pimcore Classes anti-pattern).
- Seed grupy „Powiązania" jako built-in (anti-pattern discoverability — magiczne tabs).
- „Magiczna" widoczność zakładki przy populacji atrybutami (user nie ma jak odkryć tej reguły).

---

*Backlog wygenerowany 2026-05-26 jako korekta Option Y. Agent kodujący: utwórz GitHub Issues (tytuł = nagłówek `## MODRC-NN:` bez prefiksu, body = treść ticketu, labels `modeling`+`adr-014`+`epik-ui-08`+`option-y`+`supersedes`). Per ticket dotykający >3 plików — Plan Mode przed implementacją (CLAUDE.md). Każdy ticket: wszystkie testy zielone w DoD.*
