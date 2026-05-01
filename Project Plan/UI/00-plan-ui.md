# Plan UI — druga część projektu PIM

> **Cel:** zaprojektowanie pełnego UI dla wszystkich przepływów operatorskich i adminowych systemu PIM, na bazie [PRD](../PRD/PRD-PIM.md) i [pełnego podsumowania wywiadu PRD](../PRD/sesja-pelna-podsumowanie.md).
> **Status:** W TRAKCIE — Modelowanie (epik 08) detal'owany; pozostałe 7 epików jako placeholdery do iteracyjnego rozpisania.
> **Stack UI:** React 19 + Vite 6 + Refine.dev + shadcn/ui (Radix + Tailwind) + React Router 7 + React Hook Form + Zod (per `CLAUDE.md` § Stack i ADR-005).

---

## 1. Cele projektowania UI

1. **Wow-factor demo dla pierwszego pilota** — UI musi sprzedawać produkt w pierwszych 30 sekundach (Cmd+K, agentic feel, polish wykonania).
2. **Self-dogfooding ready** — Marcin używa PIM-u na własnym IdoSell (2k SKU) + Shopify (200 SKU). UI ma działać dla niego *od dnia 1*.
3. **Persona-first** — każdy widok zaprojektowany pod konkretną personę (Kasia / Magda / Piotr / Tomasz / Adam) z `Project Plan/03-funkcjonalnosci-mvp.md` § 3.
4. **Polish accessibility** — WCAG 2.1 AA od MVP-Final (epik 0.11.9 backendu). shadcn na Radix daje a11y za darmo, ale custom widgety wymagają walidacji axe-core.
5. **i18n od dnia 1** — wszystkie user-facing stringi przez `t()` (react-i18next), klucze angielskie, tłumaczenia w `pl/en/` (możliwe dalej).
6. **Demo na małym ekranie** — Tomasz (Owner) ma dashboard responsive z poziomu telefonu (read-only). Reszta widoków desktop-first, ale nie *desktop-only*.

## 2. Personas — kto z UI korzysta

| Persona | Rola | Główne zakładki | Z PRD |
|---|---|---|---|
| **Tomasz, 48** | Owner / CEO / super-admin | Dashboard, Audit log, Settings | § 4.2 |
| **Kasia, 32** | Catalog Manager (main user) | Produkty, Multimedia, Publikacje, Workflow | § 4.2 |
| **Magda, 29** | Marketing Specialist | Produkty (opisy SEO), Multimedia, Publikacje | § 4.2 |
| **Piotr, 38** | IT/Integration Specialist | Publikacje, Ustawienia (integracje, API keys) | § 4.2 |
| **Adam, 40** ⭐ NEW | Architekt informacji (typowy użytkownik Modelowania) | **Modelowanie** + Ustawienia | brakuje w PRD — wprowadzony przy projektowaniu UI |
| **Marcin (dogfooding)** | Founder + first user | wszystkie | § 4.2 |

**Uproszczenie MVP — brak role gating dla Modelowania.** W obecnej wersji **każdy zalogowany użytkownik widzi i może używać zakładki Modelowanie**, niezależnie od roli. Adam jest *typową* personą tego widoku (architectural mindset, rzadziej loguje się niż Kasia), ale UX nie chowa zakładki.

To jest **świadome uproszczenie** dla MVP:
- Mniej kompleksowości wokół permission model'u w pierwszych miesiącach.
- Mid-market klient w pierwszych 2-3 osobowych zespołach często nie ma dedykowanego Model Admina — Kasia / Marcin / Tomasz robi to sam.
- W praktyce zmiany w Modelowaniu są *rzadkie* (raz na 1-2 tygodnie), więc free-for-all nie generuje chaosu.

**Co dochodzi w przyszłości** (Faza 1+ kandydat ADR-013):
- Granularne permissions per persona (Kasia widzi *tylko* Object Types do których ma `view`, nie modyfikuje schematu).
- Audit log każdej zmiany w Modelowaniu (od dnia 1 *jest* — z Doctrine AuditBundle, sekcja 7 archi).
- Approval flow dla destrukcyjnych operacji (delete ObjectType, change attribute type).

**Adam (NEW persona) — Architekt informacji:**
- 35-45 lat, doświadczenie 8-15 lat w IT/data engineering / business analysis.
- W mniejszych firmach to *Marcin sam* (założyciel + dogfooding), w większych mid-market — dedykowany Head of Data lub fractional przez agencję wdrożeniową.
- Zna struktury danych (relacyjne + JSON), nie programuje codziennie, ale rozumie schema migrations.
- **Frekwencja użycia:** Adam (lub osoba w roli Adama) loguje się raz na 1-2 tygodnie (gdy biznes potrzebuje nowej kategorii / nowego atrybutu / nowego typu obiektu). Nie codziennie.
- W MVP UX nie chroni przed Kasią pomyłkowo edytującą model — *konwencja* + audit log są wystarczające. Jeśli okaże się to problemem w pilotach, wprowadzamy permissions w Fazie 1.

## 3. Struktura menu — operator + admin

### 3.1 Menu główne (sidebar)

```
┌─────────────────────┐
│ 🏠 Dashboard        │  ← epik-01, wszyscy
│ 📦 Produkty         │  ← epik-02, Kasia/Magda primary
│ 🛠  Usługi           │  ← epik-03, Kasia (custom ObjectType "service")
│ 📤 Publikacje       │  ← epik-04, Kasia/Piotr
│ 🖼  Multimedia       │  ← epik-05, Kasia/Magda
│ ⚙️  Workflow         │  ← epik-06, Kasia/Tomasz
│ ⚙️  Ustawienia       │  ← epik-07, Tomasz/Piotr
├─────────────────────┤
│ ⚙️  Modelowanie      │  ← epik-08, dostępne dla wszystkich (MVP — bez role gating)
└─────────────────────┘
```

**Ważne:** *Usługi* (epik-03) to **przykład custom ObjectType** stworzonego w Modelowaniu. W praktyce klient w branży medycznej / fryzjerskiej / SaaS doda własny ObjectType, a system automatycznie wygeneruje pozycję menu pod nią. *Usługi* to konkret dla pierwszych pilotów (medyczne, fryzjerskie) oraz dla Marcina (dogfooding może objąć custom kind).

### 3.2 Generic mechanizm menu — `ObjectType` jako pozycja

Pozycje 2/3 (*Produkty*, *Usługi*) są **dynamicznie generowane na podstawie tabeli `object_types`** (z ADR-009):
- `kind=product` → Produkty (predefiniowany).
- `kind=category` → NIE pojawia się jako menu (Categories są zarządzane *wewnątrz* Modelowania + jako tree view embedded w Produktach).
- `kind=asset` → Multimedia (predefiniowany, ale UX wyspecjalizowany).
- `kind=brand` → Marki (zarządzane w Modelowaniu jako lookup, nie jako top-level menu).
- `kind=service`, `kind=location`, `kind=event`, `kind=*custom*` → dynamicznie dodawane do menu między *Produkty* a *Publikacje*.

Implikacja UX: gdy Adam w Modelowaniu dodaje nowy ObjectType `Subscription` (kind=custom), Kasia po refresh strony widzi nową pozycję *„Subskrypcje"* w sidebar między Usługami a Publikacjami. To jest *"agentic-first" effect* dla samego UI — system rośnie elastycznie, bez deploya kodu.

## 4. Zasady projektowe (cross-epic)

### 4.1 Spójność wizualna
- **Layout:** Refine layout standard (sidebar + topbar + content area + optional right sidebar dla detail/preview).
- **Typografia:** shadcn defaults (Inter font). Nagłówki H1-H4, body text 14px, mono dla kodów / SKU.
- **Kolory:** dark/light mode (Tailwind tokens). Provenance colors:
  - 🟢 manual — green-500
  - 🔵 import — blue-500
  - 🟣 agent — purple-500 (Faza 2 only)
  - ⚫ integration — gray-500
- **Ikony:** Lucide (z `lucide-react`). Konsystentny rozmiar (16px inline, 20px buttons, 24px sidebar).

### 4.2 Wzorce interakcji
- **Cmd+K command palette** — globalny, dostępny zewszad (Faza 1 baseline, Beta-Demo MVP).
- **Right sidebar detail/preview** — alternatywa do modal, dla edit/preview na liście.
- **Bulk actions** — toolbar pojawia się po zaznaczeniu pierwszego rekordu.
- **Auto-save z debounce** — 3s lub Cmd+S, *nie* explicit Save button (poza modal'ami).
- **Diff modal przed save** — dla zmian wpływających na >1 rekord lub krytycznych pól.
- **Provenance badges** — przy każdym polu w formularzu, klikalne tooltip.
- **Inline validation** — Zod + React Hook Form, błędy pod polem.
- **Keyboard shortcuts** — `?` pokazuje dostępne shortcut'y dla widoku.

### 4.3 Stany kraytkowe (loading / empty / error)
- **Loading:** skeleton screens (shadcn `Skeleton` component), nie spinnery.
- **Empty state:** ilustracja + CTA *„dodaj pierwszy X"*.
- **Error:** alert + retry button + link do logu (Sentry trace ID dla Pro/Enterprise tier).

### 4.4 Mobile
- **Dashboard mobile-friendly** (Tomasz read-only).
- **Reszta desktop-first**, ale nie blokujemy mobile (responsive grid + breakpoint sm/md/lg/xl).
- **Touch targets ≥44px** dla mobile-friendly sekcji.

## 5. Roadmap UI (mapowanie na fazy backendu)

| Faza UI | Co wchodzi | Mapping na MVP/Faza 1/2 |
|---|---|---|
| **Szkielet** | Layout, sidebar, routing, login/auth, base widoki (placeholder content) | MVP-Alpha epik 0.6.1 |
| **Modelowanie** | Pełna zakładka Modelowanie (epik-08), widoki Object Types / Attributes / Attribute Groups / Categories | MVP-Alpha epik 0.3 + 0.6 |
| **Produkty + Multimedia** | Lista produktów, dynamiczny formularz edycji, DAM lite, provenance badges | MVP-Alpha + MVP-Final |
| **Publikacje** | Sync jobs panel, integracje (BaseLinker/Shopify), API Configurator, generator feedów | MVP-Final epik 0.10 + Faza 1 |
| **Workflow + Settings** | Workflow stanów (Faza 1), settings (users, roles, integrations, API keys, locale, BYOK) | Faza 1 epik 0.11 |
| **Dashboard** | KPI widget, sync status, completeness charts | MVP-Final epik 0.11.10 |
| **Polish** | Cmd+K full, agentic flows, dark mode, mobile breakpoints | Faza 2 + iteracje |

**Sequencing:**
1. **Najpierw szkielet** (Faza UI A) — żeby wszystkie ścieżki nawigacyjne były skonsumowalne, z placeholder content.
2. **Modelowanie + Produkty** (Faza UI B) — *to jest kościec produktu*. Bez Modelowania klient nie zacznie używać. Bez Produktów nie ma co modelować.
3. **Multimedia + Publikacje** (Faza UI C) — żeby end-to-end flow był live (od stworzenia produktu po publikację na Shopify).
4. **Workflow + Settings** (Faza UI D) — operacyjna dojrzałość (multi-user, auth, integracje, BYOK).
5. **Dashboard** (Faza UI E) — *„Tomasz patrzy na liczby"* — to jest *polerka* dla pitch'u, nie *foundational*.

Implikacja: Modelowanie i Produkty są **dwoma najważniejszymi epikami UI**. Wszystko inne to dodatek.

## 6. Konwencje per epik

Każdy plik `epik-XX-NAZWA.md` ma:

```markdown
# Epik XX — [Nazwa]

## Status: [placeholder | szkic | szczegół | ready-to-build]

## 1. Cel epiku
[1-2 zdania]

## 2. Persony
[lista person z PRD § 4 — które używają tej zakładki]

## 3. Kluczowe widoki
[wireframes / ASCII mock-upy / linki do Figmy]

## 4. User stories
[lista US-EPXX-001, US-EPXX-002, ...]

## 5. Business rules / edge cases
[jak system zachowuje się w nietypowych sytuacjach]

## 6. Dependency na backend
[które ADR / encje / endpointy z Project Plan/01-architektura-pim.md są wymagane]

## 7. Komponenty Refine + shadcn
[lista komponentów do wybrania / customizacji]

## 8. Open questions
[lista TBD / TODO]
```

## 7. Lista epików — status

| # | Epik | Status | Plik | Persona główna | GitHub tracking |
|---|------|--------|------|---|---|
| 01 | Dashboard | 🔵 placeholder | [`epik-01-dashboard.md`](epik-01-dashboard.md) | Tomasz (Owner) | — |
| **02** | **Produkty** | 🟢 **szczegół (brainstorming zamknięty 2026-04-30)** | [`epik-02-produkty.md`](epik-02-produkty.md) | Kasia (Catalog Manager) | — |
| 03 | Usługi | 🔵 placeholder | [`epik-03-uslugi.md`](epik-03-uslugi.md) | Kasia + custom rola per branża | — |
| 04 | Publikacje | 🔵 placeholder | [`epik-04-publikacje.md`](epik-04-publikacje.md) | Kasia + Piotr (IT) | — |
| 05 | Multimedia | 🔵 placeholder | [`epik-05-multimedia.md`](epik-05-multimedia.md) | Kasia + Magda (Marketing) | — |
| 06 | Workflow | 🔵 placeholder | [`epik-06-workflow.md`](epik-06-workflow.md) | Kasia + Tomasz | — |
| 07 | Ustawienia | 🔵 placeholder | [`epik-07-ustawienia.md`](epik-07-ustawienia.md) | Tomasz + Piotr | — |
| **08** | **Modelowanie** | 🟢 **szczegół — backlog GitHub utworzony 2026-05-01** | [`epik-08-modelowanie.md`](epik-08-modelowanie.md) | **Adam (NEW)** | label `epik-UI-08` (#255 META + #256–#270 sub-tickety, 16 issues, ~60-80h) |

**Legenda:**
- 🔵 placeholder — szkielet pliku z TODO listą, gotowy do iteracyjnego rozpisania.
- 🟡 szkic — pierwsza wersja widoków + user stories, niezamknięta.
- 🟢 szczegół — pełen design, gotowy do wireframe'owania w Figma.
- ⚫ ready-to-build — Figma + frontend dev może implementować.

## 8. Otwarte kwestie planu UI

1. **Brand i naming produktu** — projekt wciąż „PIM" (working name). Wpływa na logo, kolory, typografię. Otwarte z PRD § 14.2.
2. **External UX designer — kontrakt czy in-house?** — w PRD § 13.5 wzmiankowane *„external UX designer 10-20h kontrakt"*. Decyzja przed Fazą UI B (Modelowanie + Produkty).
3. **Dark mode w MVP czy Faza 2?** — shadcn ma to w core, koszt to ~4-6h dyscypliny przy implementacji. Otwarte.
4. **Mobile breakpoints — które widoki musi obsługiwać mobile?** — Dashboard tak, Produkty edit tak (Tomasz w terenie sprawdza), reszta desktop-only.
5. **Figma vs ASCII wireframes vs klikalny prototyp** — dla Modelowania (epik 08) zaczynam od ASCII (najszybsze), Figma dochodzi gdy designer wchodzi.
6. **Komponentowy ślad** — czy używamy `shadcn-blocks` (gotowe layouty) czy budujemy own — decyzja per epik.
7. **Storybook** — czy wprowadzamy od dnia 1 (dyscyplina komponentów) czy później (gdy library urośnie).
8. **i18n strategia** — `react-i18next` (z `CLAUDE.md`), ale kto pisze tłumaczenia w MVP (PL + EN minimum)? Marcin sam czy crowdsource?

## 9. Powiązane dokumenty

- [`PRD-PIM.md`](../PRD/PRD-PIM.md) — pełen PRD produktu, w szczególności § 4 (persony) i § 5 (model danych).
- [`sesja-pelna-podsumowanie.md`](../PRD/sesja-pelna-podsumowanie.md) — kontekst decyzji produktowych i UX.
- [`Project Plan/01-architektura-pim.md`](../../Project%20Plan/01-architektura-pim.md) — backend + ADR-y, które kierują UI (szczególnie ADR-005 stack frontend, ADR-009 ObjectType).
- [`Project Plan/03-funkcjonalnosci-mvp.md`](../../Project%20Plan/03-funkcjonalnosci-mvp.md) — pełne persony + 17 user stories MVP.
- [`Zrodla/PIMCore/objects-pimcore.md`](../PIMCore/objects-pimcore.md) — analiza referencyjnego modelu PIMCore (kontekst dla Modelowania).

---

*Plan wersjonowany w `Zrodla/UI/`. Iteracje per epik, każdy w osobnym pliku. Master plan aktualizuje się w sekcji 7 (status epików) gdy któryś dojrzewa.*
