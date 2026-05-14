# PRD — Cortex PIM: Lista produktów (filtry, wyszukiwarka, akcje zbiorcze, Cmd+K)

**Typ dokumentu:** Product Requirements Document — **Feature-level** w ramach produktu Cortex PIM
**Klasa produktu:** PIM (Product Information Management) — agentic-first SaaS
**Pozycjonowanie:** Alternatywa dla Akeneo / Pimcore / BaseLinker — operator cockpit + workflow-grade czystość + Cmd+K agent
**Producent:** Marcin Lipiec (projekt prywatny; equity / model biznesowy poza zakresem tego dokumentu)
**Data utworzenia:** 2026-05-13
**Wersja dokumentu:** 1.0
**Autor:** Marcin Lipiec (synteza brainstormingu 4-falowego 2026-05-11)
**Status:** Draft — wymaga walidacji z first design partner przed Sprintem 1

> **Nota o scope dokumentu.** To **feature-PRD** dla *jednego* obszaru produktu (lista produktów + filtry + search + bulk + Cmd+K). Pełen product-PRD dla Cortex PIM (pozycjonowanie, ICP, model biznesowy, multitenant SaaS, pricing) — patrz `Zrodla/PRD/PRD-PIM.md`. Sekcje 3, 4, 11, 12 niniejszego dokumentu zawierają wyciąg / odniesienia do master PRD; sekcje 5–10, 13–14 są feature-specific.
>
> Źródło prawdy detaliczne: [`Project Plan/UI/feature-list-advanced.md`](../UI/feature-list-advanced.md). Ten PRD agreguje, kwestionuje, eksponuje ryzyka.

---

## 1. Streszczenie wykonawcze (TL;DR)

Lista produktów to *cockpit* Catalog Managera — 60% czasu pracy persony Kasi. Cortex PIM dostarcza dla tego ekranu trzy rzeczy, których konkurenci nie łączą w jednym narzędziu: (a) **gęstość BaseLinker** (15-20 kolumn, chip filtry, cross-page select), (b) **czystość Akeneo** (3-step bulk wizard z preview diff, completeness focus, pełne operatory filtrów), (c) **Cmd+K agent** jako natural language interface dla schema-ops + akcji zbiorczych — USP, którego ani Akeneo, ani Pimcore, ani BaseLinker nie ma natywnie. Pimcore-style developer overhead świadomie odrzucony — Kasia nie jest developerem.

Feature obejmuje: 13 akcji zbiorczych z per-typ confirmation flow, soft rollback 24h przez `bulk_session_id`, URL-based filter persistence (shareable + refreshable), 5 built-in rule-based "smart filter" presetów (z **krytyczną notą** marketingową: w MVP to NIE są AI filtry), per-attribute lock skip-and-report, cascade impact summary dla destructive operations, BaseLinker-style cross-page selection.

Backend impact: **+131–182h** ponad obecny budżet (rozłożone między epiki 0.4 / 0.5 / 0.6 / 0.7 / 0.11 — patrz §13). To 3-4× powyżej oryginalnego limitu PRD master §7. Świadoma decyzja właściciela („nie spieszę się, robimy dobrze") — eksponowana w §14 jako ryzyko scope creep R-30.

**Jednozdaniowe pozycjonowanie feature'a:**
*„Cockpit operatora z gęstością BaseLinker, dyscypliną workflow Akeneo, i jedynym na rynku Cmd+K agentem — wszystko z zachowaniem 24h rollback dla każdej zbiorczej zmiany."*

---

## 2. Wizja produktu i motywacja

### 2.1 Dlaczego budujemy ten feature

Lista produktów to **najczęściej odwiedzany ekran** w każdym PIM-ie — Kasia spędza tu 60% czasu pracy (5 h dziennie). Konkurencja rozwiązała każdy z trzech obszarów (gęstość / workflow / AI), ale żadna nie połączyła ich w jednym ekranie bez kompromisów:

- **Pimcore** ma developer-grade kontrolę (ExtJS grid z 30+ konfigurowalnymi kolumnami, Object Class hierarchy, scripty), ale wymaga programisty do codziennej pracy. Kasia (32, bez backgroundu IT) nie da rady.
- **Akeneo** ma fenomenalny 3-step bulk wizard z preview diff (signature feature, branża to skopiowała) i smart filter sidebar z pełnymi operatorami — ale to *workflow tool*, nie *cockpit*. Brak chip filtrów compact w toolbarze, brak select-all-matching w jednym kliknięciu, AI features tylko w paid Enterprise.
- **BaseLinker** ma operator cockpit (gęstość, chipy, action bar inline, ~30 akcji per typ obiektu), ale nie ma natywnego rollback'u dla bulk, nie ma cross-locale completeness detection, nie ma preview diff przed Apply, i jest *integratorem multi-channel*, nie pełnoprawnym PIM-em.

Cortex łączy trzy rzeczy w jednym ekranie: chip filtry compact + Advanced push-down panel z pełnymi operatorami + 3-step wizard z preview diff + 24h rollback per `bulk_session_id` + Cmd+K agent jako *„alternative input method"* (nie bypass — przechodzi przez ten sam wizard / preview / approval flow co manual).

### 2.2 Dlaczego teraz (timing)

Trzy zbiegające się czynniki w 2026:

1. **Anthropic Claude Sonnet 4.5** (model dostępny od kwartałów, ceny spadły) — czyni Cmd+K agent technicznie realistycznym dla MVP (poprzednio koszt $0.10+/command był zaporowy; dziś $0.002–0.008).
2. **Operator cockpit jako oczekiwanie rynku** — BaseLinker udowodnił w Polsce że e-commerce operatorzy chcą gęstego UI; Shopify Plus przejęło ten wzorzec; tradycyjny PIM (Akeneo workflow-first) zaczyna wyglądać archaicznie dla buyer person Marcina/Kasi.
3. **API Platform 4 + Refine.dev 4 + shadcn/ui** — stack dojrzał wystarczająco żeby zbudować custom power UI w 95-130h (dominant cost feature'a) bez zaprzęgania zespołu Pimcore-grade.

### 2.3 Wizja 3-letnia tego feature'a

Lista produktów w Cortex za 3 lata to **referencyjny screen dla agentic-first PIM** — narzędzie, które ludzie pokazują jako *„Cmd+K w PIM-ie to to, co Linear zrobił dla project management"*. Konkretnie w 3-letnim horyzoncie:

- **Cmd+K rozszerzony** z natural language filter queries (Faza 1), cross-channel inconsistency detection (Faza 1), LLM-driven bulk content generation (Faza 2).
- **Real-time collaboration** na zaznaczeniach — Magda i Kasia widzą wzajemnie zaznaczone produkty + locks (Faza 2-3).
- **AI quality dashboard** — completeness, consistency, freshness scoring per kanał z propozycjami fixów (Faza 2).
- **Custom user-defined smart filter presets** zapisywane jako shared entities (Faza 1).

### 2.4 North Star Metric (feature-level)

**Średni czas wykonania zadania "zmodyfikuj 50 atrybutów Brand z Bosch na Festo"** — od momentu wejścia na listę do potwierdzenia Apply.

- Pimcore: ~8-12 minut (search + selected + mass action + brak preview, ryzyko błędu = restart).
- Akeneo: ~3-4 minuty (smart filter + select + wizard 3-step + preview + Apply).
- BaseLinker: ~2-3 minuty (chip filter + select + inline bulk edit + simple confirm, ale brak preview = stres).
- **Cortex target: ~90 sekund** (Cmd+K *„dla zaznaczonych ustaw brand na Festo"* + preview diff + Apply). Lub manual: chip filter + select + bulk action + wizard = ~2 min.

Metryka mierzalna w MVP przez session replay (Hotjar / własny instrumentation) na cohorcie design partners.

---

## 3. Pozycjonowanie i różnicowanie (feature-level)

> Master pozycjonowanie produktu Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §3. Poniżej tylko aspekty unikalne dla *tego* feature'a.

### 3.1 Konkurencja bezpośrednia (per feature obszar lista/filtry/bulk)

| Konkurent | Mocne strony (ten obszar) | Słabe strony (ten obszar) | Cena (orientacyjna) | Target |
|-----------|----|---|---|---|
| **Akeneo PIM** | 3-step bulk wizard z preview diff (signature); pełne operatory w Smart Filter sidebar; mass edit attributes wizard | Brak chip filtrów compact w toolbarze; brak operator-grade gęstości UI; brak Cmd+K; AI features (auto-translate, content gen) tylko w paid Enterprise (~€40k+/rok) | Free Community / Enterprise od €40k/rok | Mid-market i enterprise, workflow-first organizacje |
| **Pimcore** | ExtJS grid z 30+ kolumn (ColumnConfigurator); Object Class hierarchy elastyczna; mass operations queue z scripts | Developer overhead — Kasia bez IT nie da rady; brak preview diff; brak natywnego bulk rollback (tylko audit log); brak Cmd+K | Open-source / Enterprise od ~€20k/rok | Enterprise z własnym zespołem dev |
| **BaseLinker** | Operator cockpit — chip filtry compact, action bar inline, ~30 akcji per typ obiektu, cross-page selection toolbar; gęstość 15-20 kolumn | Brak natywnego rollback dla bulk; brak preview diff; brak cross-locale completeness; *integrator multi-channel*, nie pełnoprawny PIM; brak Cmd+K | Od ~399 PLN/miesiąc | Polskie e-commerce SMB, operatorzy multi-channel |
| **Ergonode** | Workflow + completeness, similar do Akeneo ale lżejszy | Brak operator-grade gęstości; brak Cmd+K; mniejszy ecosystem | Cloud od €99/miesiąc | SMB e-commerce |
| **Shopify Plus** (jako benchmark UX, nie PIM) | Bulk Editor inline, gęsty UI, real-time updates | Nie PIM (e-commerce platform z PIM-like features); zamknięty ekosystem | Od $2000/miesiąc | Mid-market e-commerce |

### 3.2 Główna oś różnicowania feature'a

**Cmd+K agent jako *„alternative input method"* dla bulk actions, NIE bypass.** Klient wpisuje *„dla zaznaczonych 30 ustaw kategorię na Pneumatyka"* → Anthropic parsuje intent → generuje tool call → **trafia w ten sam wizard 3-step preview diff** co manual flow → Apply przechodzi przez ten sam handler / audit / rollback. Spójność jest wartością — agent nie zwiększa ryzyka.

Akeneo nie ma. Pimcore nie ma. BaseLinker nie ma. Wiosna 2026 — żaden komercyjny PIM tego nie zaoferował natywnie. To **jest** killer.

### 3.3 Wspierające differentiatory

1. **24h soft rollback per `bulk_session_id`** — analog do Cmd+Z dla bulk operations. Akeneo ma job history (z manual reverse, limited). BaseLinker / Pimcore — brak natywnego. To znacząca redukcja stresu Kasi (decyzja o bulk operation przestaje być terminal).
2. **Hybrid filter UI** — chip compact w toolbarze (BaseLinker pattern) + Advanced push-down panel z grid/query mode (Magento + Akeneo pattern) + URL-based persistence (shareable). Klient wybiera tryb pracy: szybko (chipy), precyzyjnie (grid), power (query AND/OR).
3. **Per-attribute lock z skip-and-report w bulk** — Kasia zabezpiecza ręcznie editowane pola (np. SEO description po manual copywritingu), bulk respektuje lock i raportuje *„247 selected, 230 updated, 17 skipped (locked)"*. Brak force override w MVP = świadoma decyzja, klient un-lock'uje ręcznie. Akeneo ma per-tenant attribute permissions (per-role), ale nie per-instance lock.
4. **Cross-locale completeness mismatch jako quick filter preset** — *„description.pl filled AND description.en empty"* w jednym kliknięciu. Rule-based w MVP, ale **wystarcza** dla typowego pain point persony Magdy (i18n).

### 3.4 Czego ten feature świadomie NIE robi lepiej

- ❌ **Custom scripts mode** (BaseLinker power user feature) — defer do Fazy 3+ lub never. Kasia/Magda nie potrzebują, dla power user'ów (Marcin) Cmd+K wystarcza w 95% przypadków.
- ❌ **Real-time AI semantic search** (*„znajdź mi produkty podobne do TST-001"*) — Faza 1+. MVP to strict prefix/exact + diacritic-insensitive po SKU/name/EAN/brand/tags. Wystarcza dla 90% queries Kasi.
- ❌ **Cross-channel value inconsistency detection** (*„description na Shopify różni się od BaseLinker dla tego SKU"*) — Faza 1-2. Wymaga LLM semantic comparison, kosztuje compute, można poczekać.
- ❌ **AI smart filters z prawdziwym LLM** — w MVP rule-based only (patrz §11 *Krytyczna nota marketingowa*). Brak wow-effect przy podstawowym poziomie, ale uczciwe komunikowanie zamiast misleading "AI-powered".
- ❌ **Multi-user collaborative selection state** (*„Magda widzi że Kasia zaznaczyła te 30"*) — Faza 2-3. MVP solo only.
- ❌ **Per-channel completeness scoring** — flat completeness w MVP (1 pasek 0-100%). Per-channel scoring → Faza 1 (wymaga rules per channel z epiku 04 Publikacje).
- ❌ **Force override locked attribute przez super_admin** — w MVP nie ma. Klient un-lock'uje ręcznie. Faza 1 z confirm modal.
- ❌ **Cmd+K NLP filter queries** (*„pokaż mi produkty z niespójnymi opisami"* jako natural language zamiast preset click) — Faza 1. MVP Cmd+K to schema-ops + bulk actions, nie filter intent.

### 3.5 Killer use case

**Scenariusz „Marcin migracja z IdoSell na nowy PIM"**: Marcin (dogfooding first user) importuje 5000 SKU z IdoSell. Po imporcie 60% produktów ma `brand` w polu `manufacturer` (niespójne mapowanie). Klasyczny PIM (Akeneo): otwórz, smart filter `manufacturer IS NOT EMPTY`, select all, bulk edit *„copy manufacturer → brand"*, czas ~10 min + ryzyko błędu bez Cmd+Z.

Cortex flow: `⌘K` → *„dla wszystkich produktów z manufacturer IS NOT EMPTY skopiuj manufacturer do brand"* → agent parsuje, generuje plan, pokazuje preview diff *„3000 produktów: rozkład wartości manufacturer Festo (800), Bosch (650)... ; preview 5 sample rows: TST-001 manufacturer=Festo → brand=Festo"* → Apply → toast *„3000 produktów updated [Wycofaj 24h]"*. Czas ~90 sekund. **Bezstresowy** — bo Marcin wie, że ma 24h żeby cofnąć.

Akeneo to zrobi w 8-10 minut (smart filter + wizard manual). BaseLinker to zrobi w 3-4 minuty (chip + inline bulk), ale bez preview = stres + ryzyko że brand=Festo set'nie się dla 200 produktów z manufacturer=Festo plus brand już Bosch (override bez preview). Pimcore — Marcin (bez IT skillsa Pimcore-grade) nie poradzi sobie w sensownym czasie.

---

## 4. ICP i persony (w kontekście tego feature'a)

> Master ICP Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §4. Poniżej tylko zawężenie na *tego* feature'a.

### 4.1 ICP — kogo szczególnie obchodzi ten feature

- **Branże:** B2B e-commerce techniczny (Marcin profile — czujniki, pneumatyka, hydraulika), polski retail multi-channel (Kasia profile — Allegro + Shopify + BaseLinker). Mniej krytyczne dla: fashion, FMCG, single-channel butique.
- **Skala asortymentu:** 1000–50 000 SKU (sweet spot). Poniżej 1000 — feature overkill, klient użyje Excel. Powyżej 50k — wymaga dodatkowych optymalizacji (lazy loading 200k+ jest planowany ale wymaga benchmarków Sprint 1).
- **Częstotliwość bulk operations:** ≥5 bulk operations/tydzień. Klient z 1 bulk/miesiąc nie odczuje wartości 24h rollback / wizard preview.
- **Dojrzałość digital:** Klient ma multi-locale (PL + EN minimum) lub multi-channel (≥2 kanały). Smart filter presets (cross-locale completeness) bez multi-locale = bez wartości.

### 4.2 Persony użytkowników tego feature'a

#### Kasia, 32 — Catalog Manager (PRIMARY)
- **Kim jest:** Catalog Manager w polskim B2B e-commerce technicznym, 5 lat doświadczenia, używała wcześniej Magento admin + Excel + BaseLinker. Bez backgroundu IT, ale digital-savvy. Pracuje 8h dziennie w PIM, 60% czasu na liście produktów.
- **Cele:** Znaleźć produkt w ≤10 sekund. Zmienić 50-500 produktów w ≤3 minuty z preview *„rozumiem co się stanie"*. Cofnąć błąd w ≤30 sekund bez czekania na IT.
- **Frustracje dziś:** BaseLinker brak preview = stres przed Apply. Akeneo brak chip filtrów compact w toolbarze = za dużo klików dla typowych zadań. Excel zawsze fallback, ale brak audit / rollback / multi-user.
- **Wskaźnik sukcesu:** Spadek liczby ticketów do IT *„cofnij moją bulk operation"* z N/miesiąc do 0. Spadek czasu na typowe zadanie (50 produktów brand change) z 10 min do 90 sek.

#### Magda, 29 — Marketing / Content Manager (SECONDARY)
- **Kim jest:** Marketing manager odpowiedzialny za content multi-locale (opisy PL+EN), kategoryzacja, kolekcje sezonowe, SEO. 5-10h/tydzień w PIM (głównie bulk content operations).
- **Cele:** Bulk edit description dla 247 produktów Festo z preview diff (rozumie co się stanie). Cross-locale completeness check (gdzie PL filled ale EN empty). URL share z kolegą („otwórz to, zobacz dlaczego dropdown nie działa").
- **Frustracje dziś:** Brak per-locale completeness w typowych PIM-ach (Akeneo Enterprise ma, ale ich budżet nie). Brak shareable URL z filter state — koleżanka musi tłumaczyć kroki *„kliknij to, potem to"*.
- **Wskaźnik sukcesu:** Spadek czasu na *„dodaj EN description tam gdzie tylko PL"* z 2h do 30 min (cross-locale completeness filter + bulk wizard).

#### Marcin — Founder / dogfooding (FIRST USER)
- **Kim jest:** Founder Cortex PIM, prywatny e-commerce B2B (planowana migracja z IdoSell + Shopify). Hands-on, używa Cmd+K agresywnie, regression-testuje każdy nowy feature na własnym katalogu.
- **Cele:** Każdy nowy feature ma „just work" na 5000 SKU realnym katalogu. Cmd+K musi być fast (< 3s) i precise (95%+ intent parsing accuracy na typowych queries).
- **Frustracje dziś:** Generic PIM-y nie mają Cmd+K, więc co dzień klika 50 razy w UI dla zadań które naturalny język załatwia w 1 zdaniu.
- **Wskaźnik sukcesu:** Codziennie 1-2h w Cortex, zero regression bugs po feature shippping (Marcin = manual QA).

#### Tomasz — Owner / CEO (SPORADYCZNY)
- **Kim jest:** Owner / CEO firmy klienta, audit + flagowe produkty edycja, 1-2h tygodniowo w PIM.
- **Cele:** Self-service edycja flagowych produktów (5-10 SKU). Audit overview *„kto co zmienił w tym tygodniu"*. Saved View *„moje 10 produktów"*.
- **Frustracje dziś:** Klasyczne PIM-y są workflow-tool dla teamu, nie owner-friendly. Tomasz chce wejść, znaleźć produkt, edit, wyjść.
- **Wskaźnik sukcesu:** ≤30 sekund od loginu do edytowanego produktu.

### 4.3 Decydent zakupowy vs. użytkownik

- **Decydent zakupowy:** Tomasz (Owner/CEO) — podpisuje umowę, alokuje budżet, robi demo z konkurencją.
- **Daily user:** Kasia (Catalog Manager) — używa 60% czasu pracy. **Jej** opinia decyduje o renewal w roku 2.
- **Champion:** Magda (Marketing) — często pierwsza zauważa pain w bieżącym narzędziu, lobbuje za zmianą.

Ten feature musi w demo wow-fować **Tomasza** (Cmd+K = magic moment) i potem w trial dostarczać codzienną wartość **Kasi** (chip filtry + preview diff + rollback = redukcja stresu).

---

## 5. Model danych (feature-level)

> Master model danych Cortex PIM (ObjectType, Attribute, ObjectValue, attributes_indexed JSONB, ADR-006/009/010/011) — patrz `Zrodla/PRD/PRD-PIM.md` §5 i `Project Plan/01-architektura-pim.md`. Poniżej tylko delta wprowadzona przez ten feature.

### 5.1 Nowe encje wprowadzane przez feature

**`bulk_sessions`** — każda bulk operation tworzy session, służy jako anchor dla rollback w 24h window.

```sql
CREATE TABLE bulk_sessions (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),
    action_type VARCHAR(64) NOT NULL,           -- set_attribute, delete, publish_channels, ...
    target_object_ids UUID[] NOT NULL,
    target_count INTEGER NOT NULL,
    success_count INTEGER NOT NULL DEFAULT 0,
    skipped_count INTEGER NOT NULL DEFAULT 0,   -- locked attrs
    error_count INTEGER NOT NULL DEFAULT 0,
    action_payload JSONB NOT NULL,              -- attribute_id, new_value, channels, ...
    rollback_available_until TIMESTAMPTZ,
    rolled_back_at TIMESTAMPTZ,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ,
    source VARCHAR(16) NOT NULL DEFAULT 'manual', -- manual, cmd_k_agent
    cmd_k_command TEXT                          -- jeśli source=cmd_k_agent
);
```

**`bulk_logs`** — per-produkt log starych wartości (rollback recipe).

```sql
CREATE TABLE bulk_logs (
    id UUID PRIMARY KEY,
    bulk_session_id UUID NOT NULL REFERENCES bulk_sessions(id) ON DELETE CASCADE,
    object_id UUID NOT NULL REFERENCES objects(id),
    attribute_id UUID REFERENCES attributes(id),   -- NULL dla destructive ops
    old_value JSONB,
    new_value JSONB,
    level VARCHAR(8) NOT NULL,                     -- info, warning, error
    message TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_bulk_logs_session ON bulk_logs(bulk_session_id);
CREATE INDEX idx_bulk_logs_object ON bulk_logs(object_id);
```

**`smart_filter_presets`** — rule-based filtry („AI smart filters" w marketingu, faktycznie SQL queries).

```sql
CREATE TABLE smart_filter_presets (
    id UUID PRIMARY KEY,
    tenant_id UUID,                          -- NULL = system-shipped global
    user_id UUID REFERENCES users(id),       -- NULL = tenant-shared (Faza 1+)
    name JSONB NOT NULL,                     -- {"pl": "Niespójne opisy", "en": "..."}
    icon VARCHAR(16),
    query JSONB NOT NULL,                    -- nasz filter DSL
    is_built_in BOOLEAN NOT NULL DEFAULT false,
    sort_order INTEGER DEFAULT 0
);
```

5 built-in presetów seedowanych w MVP: *„Niespójne opisy (PL filled, EN empty)"*, *„Brakujące zdjęcia"*, *„Niepełne SEO"*, *„Czerwone (<50% complete)"*, *„Bez kategorii"*.

**`user_filter_favorites`** — per-user top 10 atrybutów w *„Filtruj po atrybucie"* dropdown.

```sql
CREATE TABLE user_filter_favorites (
    user_id UUID NOT NULL REFERENCES users(id),
    attribute_id UUID NOT NULL REFERENCES attributes(id),
    sort_order INTEGER NOT NULL,
    PRIMARY KEY (user_id, attribute_id)
);
```

### 5.2 Zmiany w istniejących encjach

**`objects` table** — dwie nowe kolumny:

```sql
ALTER TABLE objects ADD COLUMN bulk_session_id UUID REFERENCES bulk_sessions(id);
CREATE INDEX idx_objects_bulk_session ON objects(bulk_session_id) WHERE bulk_session_id IS NOT NULL;

ALTER TABLE objects ADD COLUMN locked_attributes JSONB DEFAULT '[]'::JSONB;
CREATE INDEX idx_objects_locked_attrs ON objects USING GIN(locked_attributes);
```

`bulk_session_id` — last bulk session that touched this object. `locked_attributes` — array of attribute_id zablokowanych w detail view (skip-and-report w bulk).

### 5.3 Filter DSL — format JSONB query

`smart_filter_presets.query` i URL filter params używają tego samego DSL:

```json
{
  "operator": "AND",
  "conditions": [
    {"attribute": "brand", "op": "IS", "value": ["Festo", "Bosch"]},
    {"attribute": "completeness_pct", "op": "<", "value": 50},
    {
      "operator": "OR",
      "conditions": [
        {"attribute": "stock", "op": ">=", "value": 10},
        {"attribute": "date_created", "op": "BETWEEN", "value": ["2026-01-01", "2026-05-11"]}
      ]
    }
  ]
}
```

Płaski grid mode = `operator: AND` z prostą `conditions` listą. Query mode (power user) = zagnieżdżone OR/AND/NOT.

URL serializer = lossy compression DSL → `?brand=Festo,Bosch&completeness=lt:50` (single level only). Power user query → URL z hashowanym blobem `?q=<base64-json>` lub Saved View.

### 5.4 Audit / provenance

- Każdy bulk write w `object_values` setuje `provenance = 'bulk'` (lub `'cmd_k_agent'` jeśli source z palette) z `provenance_meta.bulk_session_id` referencją.
- AuditBundle log per ObjectValue change (Doctrine event listener) — z `user_id`, `timestamp`, `bulk_session_id` reference.
- Rollback creates new audit entries (nie *„usuwa"* starych) — historia jest immutable.

### 5.5 Walidacje per filter operator

| Typ atrybutu | Walidacja operatora |
|---|---|
| `text`, `textarea`, `wysiwyg` | `=`, `≠`, `IS EMPTY`, `IS NOT EMPTY`, `STARTS WITH`, `ENDS WITH`, `CONTAINS`, `NOT CONTAINS` |
| `number`, `metric` | `=`, `≠`, `>`, `<`, `>=`, `<=`, `BETWEEN`, `IS EMPTY`, `IS NOT EMPTY` |
| `date`, `datetime` | `=`, `≠`, `>` (after), `<` (before), `BETWEEN`, `IS EMPTY`, `IS NOT EMPTY` |
| `select` | `=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY` |
| `multiselect` | `CONTAINS`, `NOT CONTAINS`, `IS EMPTY`, `IS NOT EMPTY` |
| `boolean` | `=` (TRUE/FALSE) |
| `relation` | `=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY` |
| `asset` (image, file) | `IS EMPTY`, `IS NOT EMPTY` |

Walidacja po stronie frontu (UI nie pokaże nieprawidłowych operatorów) + backend reject jako Problem Details (RFC 7807) gdy URL ręcznie modyfikowany.

---

## 6. Multikanałowość (kontekst feature'a)

> Master strategia multikanałowa Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §6. Poniżej tylko wpływ na *ten* feature.

### 6.1 Bulk publish / unpublish — semantyka per kanał

- **`bulk publish to channels`** — `POST /api/products/bulk-actions/publish` z body `{target_ids[], channels[]}`. Sync workers per kanał (Shopify / BaseLinker / Allegro / IdoSell w Fazie 1+). Mercure SSE progress per kanał.
- **`bulk unpublish from channels`** — j.w. dla unpublish. Rollback `bulk publish` triggeruje unpublish workers (per Fali 4: *„always unpublish wszystkie"*).
- **Sales w międzyczasie** — produkt unpublish z Shopify gdy istnieje order = `partial` status w raporcie. Klient akceptuje *„buyer może zobaczyć product not found"*. System NIE blokuje rollback'u. Order historyczny retain product line item (Shopify default behavior).

### 6.2 Scopable attributes w filter / bulk

- Filter chip dla scopable atrybutu pokazuje channel: `[description.shopify CONTAINS "premium" ✕]`. Domyślnie filter w aktualnie wybranym channel sub-tab.
- Bulk edit scopable attribute → wizard Step 1 pyta *„Apply do wszystkich kanałów / current channel only / wybranych kanałów?"*. Default: current channel.

### 6.3 Per-channel completeness scoring — OUT OF MVP

Decyzja świadoma: w MVP **flat completeness** (1 pasek 0-100% per produkt). Per-channel scoring (*„90% complete dla Shopify, 60% dla BaseLinker"*) → Faza 1. Wymaga rules per channel z epiku 04 Publikacje (które kanały *wymagają* których atrybutów). Bez tego MVP nie ma jak liczyć per-channel completeness.

---

## 7. DAM i media (kontekst feature'a)

> Master DAM strategia — patrz `Zrodla/PRD/PRD-PIM.md` §7. Tu tylko aspekty list view.

- **Filter `main_image IS EMPTY`** — preset *„Brakujące zdjęcia"* w smart filter presets.
- **Thumbnail w kolumnie grid** — column `🖼` pokazuje 32×32 main_image. Lazy load (viewport-based) dla performance.
- **Bulk delete cascade dla DAM** — `bulk delete` NIE usuwa assetów z DAM (zachowane w MinIO/S3, referencje product↔asset zerwane). Cascade impact modal informuje *„1820 zdjęć NIE zostanie usuniętych (zachowane w DAM)"*.
- **Bulk replace main image / Bulk add to gallery** — OUT OF MVP (Faza 1).

---

## 8. Workflow i jakość danych (feature-level)

### 8.1 Completeness w liście — flat formula

- Kolumna `Compl.` w grid: progress bar 0-100% + procent.
- Color coding: 🔴 <50% / 🟡 50-79% / 🟢 ≥80%.
- Formula: `liczba wypełnionych atrybutów / total atrybutów w ObjectType (z weight 1 dla wszystkich w MVP)`.
- Per-attribute weight — Faza 1 (atrybut SEO ma weight 5, atrybut technical_specs_pdf ma weight 1).
- Per-locale completeness — `attributes_indexed.completeness_per_locale JSONB` z per-locale liczba (PL=80%, EN=20%). Używana w smart filter preset *„Niespójne opisy"* + tooltip pokazuje rozbicie po hover na pasku.

### 8.2 Smart filter presets w MVP — rule-based

| Preset | Query |
|---|---|
| 🌐 *„Niespójne opisy"* | `description.pl IS NOT EMPTY AND description.en IS EMPTY` |
| 📷 *„Brakujące zdjęcia"* | `main_image IS EMPTY` |
| 🔍 *„Niepełne SEO"* | `description IS NOT EMPTY AND meta_description IS EMPTY` |
| 🔴 *„Czerwone (<50% complete)"* | `completeness_pct < 50` |
| 📂 *„Bez kategorii"* | `category IS EMPTY` |

Implementacja: server-side query against `attributes_indexed JSONB` z GIN index (z ADR-006).

### 8.3 Per-attribute lock — provenance dla locked

- Lock UX z epiku 02 §5.1 — 🔓 / 🔒 icon obok pola w detail view, click toggle.
- Lock change → `objects.locked_attributes JSONB` update + audit entry z `lock_changed_at`, `lock_changed_by`, `lock_reason` (opcjonalny komentarz).
- Bulk respect locks → handler checks `IF attribute_id IN object.locked_attributes THEN skip + log`.
- Raport po bulk: *„247 selected, 230 updated, 17 skipped (locked) [pokaż SKUs]"*.

### 8.4 Audit log per bulk operation

- `bulk_sessions` + `bulk_logs` tabele jako primary audit dla bulk.
- AuditBundle (epik 0.11.4) loguje też per-object change w `audit_logs` z `bulk_session_id` reference.
- Retention: `bulk_logs` 7 dni hard delete (rollback window 24h + buffer). `audit_logs` długoterminowe (per tenant retention policy — Faza 1).

---

## 9. Importy, eksporty, integracje (kontekst feature'a)

> Master strategia integracji Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §9 oraz [`feature-imports.md`](../UI/feature-imports.md). Tu tylko interakcja z list view.

- **Import CTA z empty state** — link do `/imports` (epik 04 Publikacje sub-tab Imports).
- **Bulk export** — *„Export selected to CSV/XLSX"* — OUT OF MVP. Faza 1 w epiku 04 Publikacje.
- **Bulk publish to channels** — calls integration adapters (Shopify, BaseLinker — MVP+; Allegro, Magento, IdoSell — Faza 1+). Per-kanał Mercure SSE progress.
- **Cmd+K integration intents** (*„opublikuj zaznaczone na Shopify"*) — mapped na `tool:bulk_publish` (MVP scope).

---

## 10. Strategia AI (feature-level)

> Master AI strategia Cortex PIM (Anthropic, BYOK, limits) — patrz `Zrodla/PRD/PRD-PIM.md` §10 oraz `Project Plan/01-architektura-pim.md` §8.5. Tu tylko aspekty list view.

### 10.1 Co JEST AI w MVP

- ✅ **Cmd+K agent** — Anthropic Claude Sonnet 4.5 (domyślny). Parse user intent → generate tool call JSON → preview diff (z manual flow) → execute przez te same handlers.
- ✅ **Schema-ops intents** (z epiku 08 Beta-Demo) — *„dodaj atrybut IP_class do rodziny Czujniki"* (Marcin osobiście).
- ✅ **Bulk action intents** — *„dla zaznaczonych ustaw kategorię na Pneumatyka"*, *„podbij cenę o 10% dla zaznaczonych"*, *„wyłącz wszystkie zaznaczone"*, *„opublikuj zaznaczone na Shopify"*, *„dodaj do kategorii Promocja dla zaznaczonych"*, *„dla 30 zaznaczonych skopiuj manufacturer do brand"*.

### 10.2 Co NIE JEST AI w MVP (mimo nazwy „smart filters")

- ❌ **„AI smart filter" — Niespójne opisy** itd. — rule-based SQL query, **nie LLM**. Patrz §11 *Krytyczna nota marketingowa* poniżej.

### 10.3 Co JEST AI w Fazie 1+

- ⏳ **NLP filter queries w Cmd+K** — *„pokaż mi produkty z niespójnymi opisami"* jako natural language zamiast preset click.
- ⏳ **Bulk translate** — *„przetłumacz description dla zaznaczonych z PL na EN"* (Faza 2, data-ops agent).
- ⏳ **Bulk generate SEO** — *„wygeneruj opis SEO dla zaznaczonych z atrybutów"* (Faza 2).
- ⏳ **Cross-channel inconsistency detection** — *„description na Shopify różni się od BaseLinker dla tego SKU"* (Faza 1-2, LLM semantic comparison).
- ⏳ **AI quality dashboard** — completeness + consistency + freshness scoring per kanał z propozycjami fixów (Faza 2).
- ⏳ **Real-time AI semantic search** — *„znajdź mi produkty podobne do TST-001"* (Faza 1+, embeddings).
- ⏳ **`delete_bulk` przez Cmd+K** — Faza 1 (cautious — destructive bez full UI flow).

### 10.4 AI a polityka danych klienta

- **BYOK domyślnie** dla Pro/Enterprise — klient płaci swój Anthropic key, dane idą do *jego* organizacji Anthropic (data policy klienta vs Anthropic).
- Marcin's key tylko dla testów + demo dla nowych design partners.
- Limits z architektury §8.5: 50 tool calls/h/user, 10 tool calls/agent_run, $20/dzień/tenant, $300/miesiąc/tenant.

### 10.5 AI cost per Cmd+K command

- Input ~500-2000 tokens (current state + selection + filter context).
- Output ~200-500 tokens (tool call JSON).
- Claude Sonnet 4.5: ~$0.002-0.008 per command.
- Przy 50 commands/dzień/user: $0.10-0.40/dzień. Typowy heavy user (Marcin profile): $3-12/miesiąc per user.

---

## 11. Architektura SaaS (feature-level)

> Master architektura multitenant Cortex PIM (FrankenPHP worker, Doctrine TenantFilter, RLS w Fazie 1) — patrz `Zrodla/PRD/PRD-PIM.md` §11 oraz `Project Plan/01-architektura-pim.md`. Tu tylko aspekty feature'a.

### 11.1 Multi-tenancy izolacja

- Wszystkie nowe encje (`bulk_sessions`, `bulk_logs`, `smart_filter_presets`, `user_filter_favorites`) mają `tenant_id UUID NOT NULL` od dnia 1 (kontrakt CLAUDE.md).
- Doctrine TenantFilter automatic clause `WHERE tenant_id = :current_tenant`.
- `smart_filter_presets` ma `tenant_id NULLABLE` — `NULL` = system-shipped built-in (shared globally, immutable per tenant).
- Postgres RLS aktywowany w Fazie 1 (sekcja 11.1a master architektury, plan 16-24h).

### 11.2 Skala docelowa feature'a

- **Filter query latency target:** p95 <300ms na 200k SKU. GIN index na `objects.attributes_indexed JSONB` (ADR-006) + Meilisearch dla quick search po SKU/name/EAN/brand/tags.
- **Bulk operations:** async threshold >100 produktów (Symfony Messenger). Worker chunk size N=200 z `EntityManager::clear()` per chunk (memory management, CLAUDE.md FrankenPHP rule).
- **Cmd+K response time target:** <3s end-to-end (input → Anthropic API → tool call generation → preview render).
- **Selection state w memory client-side** — React state `selectedIds: string[]`. Limit cross-page selection 10k SKUs (UX warning powyżej; do uzgodnienia §14 open question).

### 11.3 FrankenPHP worker mode — memory safety

- `BulkEditAttributeHandler extends AbstractBatchHandler` — `EntityManager::clear()` per chunk N=200 (custom PHPStan rule blokuje flush-without-clear).
- `BulkRollbackHandler` iteruje `bulk_logs` z Doctrine `iterate()` zamiast `findAll()` (memory-safe dla 10k+ entries).
- Prometheus alert `frankenphp_worker_memory_bytes > 256MB` (z CLAUDE.md).

### 11.4 Mercure SSE channels

- `bulk-operations.{user_id}` — wszystkie bulk operations tego usera (live updates lista).
- `bulk-operations.{session_id}` — pojedyncza operacja progress (subscribed gdy user otwiera detail).
- `cmd-k.{user_id}` — Cmd+K agent stream response (token-by-token typing effect).

### 11.5 Bezpieczeństwo i compliance

- **MVP brak per-action gating** (decyzja Fali 3) — wszyscy userzy mogą bulk delete/publish. Audit log + 24h rollback wystarczą. Faza 1 → ADR-013 per-role permissions.
- **Cmd+K agent rate limits** twarde (CLAUDE.md §8.5) — 50 tool calls/h/user, 10 tool calls/agent_run, $20/dzień/tenant, $300/miesiąc/tenant. Po przekroczeniu agent off do północy UTC.
- **BYOK klucz tenanta** szyfrowany AES-256-GCM (z architektury §8.5).

### 11.6 SLA per feature

| Tier | Filter latency P95 | Bulk operations queue | Cmd+K availability |
|------|--------------------|------------------------|--------------------|
| Free / Trial | <500ms na 1k SKU | Sync only (limit 100 produktów) | Demo limit 10 commands/dzień |
| Starter | <400ms na 10k SKU | Async, max 1 concurrent bulk job | 50 commands/h, BYOK opcjonalne |
| Pro | <300ms na 50k SKU | Async, max 3 concurrent bulk jobs | 50 commands/h, BYOK obowiązkowe |
| Enterprise | <300ms na 200k SKU + dedicated read replicas | Async, unlimited concurrent (kolejkowanie inteligentne) | Custom limits, BYOK + audit retention 12 miesięcy |

---

## 12. Model biznesowy i pricing (feature-level)

> Master pricing Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §12. Tu tylko wpływ tego feature'a na pricing tiers.

### 12.1 Co jest gated per tier

| Tier | Smart filter presets | Saved Views per user | Cmd+K agent | Bulk concurrent jobs | Rollback retention |
|------|-----------------------|----------------------|-------------|----------------------|--------------------|
| Free / Trial | 5 built-in | 3 | 10 commands/dzień (demo) | 1 sync only, ≤100 produktów | 24h |
| Starter | 5 built-in + 5 user-defined (Faza 1) | 10 | 50 commands/h, BYOK opc. | 1 concurrent async | 24h |
| Pro | Unlimited user-defined | Unlimited | 50 commands/h, BYOK obow. | 3 concurrent async | 24h |
| Enterprise | Unlimited + custom tenant-shared | Unlimited + role-based | Custom limits, BYOK + audit | Unlimited (smart queue) | Custom (7-30 dni) |

### 12.2 Wpływ kosztowy AI na pricing

- Cmd+K cost ~$0.002-0.008 per command. Heavy user (50 commands/dzień) = ~$3-12/miesiąc.
- Margin assumption (Pro tier $99/miesiąc): Cmd+K cost <5% revenue per user. Margin OK do ~10 heavy users per tenant (10× $12 = $120 absorbed przez BYOK).
- **BYOK obowiązkowe od Pro** — model unit economics nie domyka się bez tego dla heavy users.

### 12.3 Open business questions (feature-related)

- [ ] Czy Cmd+K w Trial 10 commands/dzień jest zbyt restrictive vs. demo wow-effect? Może 20 commands/Trial-okres total (cap globalny)?
- [ ] Czy rollback 24h jest tier-gatable (7d na Enterprise) vs. universal („zaufanie do produktu" gest)?
- [ ] Cmd+K bulk actions Faza 1+ cut (oszczędność ~12-16h MVP) — czy to akceptowalne, czy USP wymaga MVP shippping?

---

## 13. MVP scope i roadmap (feature-level)

### 13.1 MVP — co MUSI być w pierwszym release feature'a

**Wyszukiwarka i filtry:**
- Quick search po SKU/name/EAN/brand/tags z Enter submit / debounce 800ms (brak typeahead — decyzja Fali 2).
- *„Filtruj po atrybucie"* dropdown z favorite top 10 + link do full attribute modal.
- Chip filter area z edit popover (click chip body) + ✕ kasuje.
- Advanced filter panel push-down sticky-collapsible (Magento style) z grid mode (default).
- Pełne operatory per typ atrybutu (Akeneo-grade) od dnia 1 — decyzja Fali 2.
- 5 built-in smart filter presets (rule-based — patrz §11 *Krytyczna nota*).
- URL-based filter persistence (shareable, refreshable).
- Saved Views integration (entity z epiku 02 §9).

**Akcje zbiorcze:**
- 14 akcji: set/clear/append/remove attribute, multi-attr edit, increment numeric, toggle enabled, add/remove/move category, publish/unpublish channels, delete, duplicate.
- 3-step wizard z preview diff (sample 5 + aggregate counter) dla edit/clear/append/remove/increment.
- Inline toast + Undo 5s dla low-risk (toggle enabled, add/remove category).
- Hard confirm typing N produktów dla destructive (delete).
- Simple modal dla publish/unpublish/duplicate.
- Cascade impact summary modal dla delete + change family (Faza 1).
- 24h soft rollback per `bulk_session_id`.
- Per-attribute lock skip-and-report (raport SKUs).
- BaseLinker-style cross-page selection (toolbar wybór per-page vs select-all-matching).
- Async via Symfony Messenger dla >100 produktów (Mercure SSE progress).

**Cmd+K agent:**
- Trigger ⌘K (Mac) / Ctrl+K (Win/Linux) + button toolbar.
- Palette modal z input + kontekst sekcja (*„30 zaznaczone, filter: Brand=Festo"*) + podpowiedzi + ostatnie komendy.
- Intent parsing przez Anthropic Claude Sonnet 4.5.
- 6 MVP intents: `create_attribute` (schema-ops z epiku 08), `set_bulk_attribute`, `toggle_bulk_enabled`, `increment_bulk_numeric`, `add_remove_category`, `publish_bulk`.
- Selection context naturalne (bez UI explicit chip — decyzja Fali 4).
- Preview diff identyczny z manual flow (spójność).

### 13.2 v1 (3-6 miesięcy po MVP) — Faza 1

- Query mode w Advanced filter panel (AND/OR brackets — power user).
- User-defined smart filter presets („Zapisz Saved View jako Smart Preset").
- Cmd+K NLP filter queries (*„pokaż mi produkty z niespójnymi opisami"*).
- Cmd+K `delete_bulk` intent (z full UI flow safeguards).
- Per-channel completeness scoring (wymaga rules z epiku 04 Publikacje).
- Force override locked attribute przez super_admin (z confirm modal).
- Bulk replace main image / add to gallery.
- Bulk export to CSV/XLSX (w epiku 04 Publikacje).
- Find & replace text w description/name z regex (rules engine).
- ADR-013 per-action role permissions.

### 13.3 v2+ (Faza 2-3) — w roadmapie ale bez commit

- Cmd+K `bulk_translate`, `bulk_generate_seo` (data-ops agent z LLM).
- AI semantic search („produkty podobne do TST-001").
- AI quality dashboard (cross-channel inconsistency, completeness, freshness).
- Real-time collaborative selection state (multi-user lock visibility).
- Custom scripts mode (BaseLinker-style power user) — kandydat lub never.
- Change workflow state w bulk (Symfony Workflow z epiku 06).
- Per-attribute weight w completeness formula.

### 13.4 Pierwszy klient referencyjny / design partner

- **Marcin (dogfooding)** — first user, 5000 SKU realny katalog (IdoSell + Shopify migracja). Zero-day feedback.
- **Design partner #1** — szukany przed Sprintem 1 (PRD §13 ryzyko R-30). Profile: polski B2B techniczny e-commerce, 5-15k SKU, multi-locale (PL + EN), pain point z BaseLinker lub Akeneo. Free Cortex w zamian za 1h/tydzień feedback przez 3 miesiące.

### 13.5 Czas do MVP

- Backend impact: **+131-182h** ponad obecny budżet (3-4× scope creep vs. PRD master §7 limit).
- Rozkład: epik 0.4 +14-20h, epik 0.5 +4-6h, epik 0.6 +95-130h (dominant), epik 0.7 +12-16h, epik 0.11 +6-10h.
- Realna estymacja MVP feature'a w izolacji (założenie reszty epik 02 baseline gotowa): **6-10 tygodni** solo dev pace (Marcin).
- Świadoma decyzja właściciela: *„nie spieszę się, robimy żeby było dobrze"* (cytat PRD master §12.1).

---

## 14. Ryzyka, sprzeczności, otwarte kwestie

### 14.1 Zidentyfikowane ryzyka

| Ryzyko | Prawdopodobieństwo | Wpływ | Mitygacja |
|--------|--------------------|----|-----------|
| **R-30 Scope creep feature'a** — +131-182h vs. PRD limit ~50-80h | Wysokie (już zaistniało) | Wysoki (opóźnienie całego MVP o 6-10 tyg.) | Cut Faza 1: Query mode (~12-16h) + Cmd+K bulk (~12-16h) + Increment numeric + multi-attr bulk edit (~8-10h). Decyzja przed Sprint 1. |
| **R-31 Cmd+K accuracy <80%** intent parsing | Średnie | Średni (USP wow-effect zniweczony) | POC w Sprint 1 z mock agent (rule-based) + benchmark na 50 typowych queries Marcina przed Anthropic integration. |
| **R-32 Anthropic API outage** podczas demo | Niskie | Wysoki (jeden incident = utracony lead) | Fallback do mock agent z disclaimer *„Cmd+K AI offline, użyj manual flow"*. BYOK redundancy (klient ma multi-region). |
| **R-33 „AI smart filters" misleading marketing** — klient płaci za Pro tier oczekując prawdziwego AI | Średnie | Wysoki (refund + bad word-of-mouth) | **Krytyczna nota §11 obowiązkowa** w pitch deck, marketing copy, sales script. Slajd 4-5 pitch deck *„coming in Faza 1"* dla full AI smart filters. |
| **R-34 Bulk rollback brak orderów sync** — `bulk publish` rollback gdy klient ma sprzedaż w międzyczasie | Wysokie | Średni (klient zaskoczony „partial" status) | UX expectation setting w hard confirm modal *„buyer może zobaczyć product not found w 12h window — akceptujesz?"*. Raport CSV z `partial` SKUs. |
| **R-35 Cross-page selection 50k SKUs bulk operation** zalewa worker queue | Niskie | Wysoki (degradacja systemu, OOM workera) | UX warning *„>10k zaznaczonych — operacja zajmie 30+ minut"* + soft limit 10k (Pro tier) / 50k (Enterprise). |
| **R-36 GIN index performance** na `attributes_indexed JSONB` przy 200k+ SKU | Średnie | Średni (filter latency >300ms target) | Benchmark Sprint 1 z syntetycznym 200k SKU dataset. Fallback: dedicated Meilisearch dla filter queries (poza quick search). |
| **R-37 Filter URL params** edge cases z bogato zagnieżdżonymi query | Niskie | Niski (URL >2000 znaków, niektóre proxies tnie) | Hashowany blob `?q=<base64-json>` dla query mode + Saved View jako prawdziwy persistent. |
| **R-38 Per-attribute lock locked-by stale** — user A lock'uje, opuszcza firmę, user B nie może bulk edit | Niskie | Niski | Faza 1: super_admin force override z confirm. MVP: workaround przez ręczny un-lock w detail view. |
| **R-39 Cmd+K cost spike** — heavy user >$20/dzień limit po południu | Średnie | Niski (UX disruption) | Hard limit z architektury §8.5. Komunikat *„dzienny limit AI agenta wyczerpany, dostępny od jutra"*. |

### 14.2 Otwarte kwestie (do uzupełnienia / walidacji)

- [ ] **Bundle size feature'a** — Advanced filter panel + Cmd+K + bulk wizard razem ~150-200 KB. Czy code-split lazy load akceptowalne UX-owo (300ms delay na pierwsze otwarcie panel)?
- [ ] **Operator pełne w Faza 1 czy MVP** — Marcin wybrał Akeneo-style pełne od dnia 1 (~10-14h). Czy *wszystkie* operatory per type w MVP, czy *core* (`=`, `≠`, `IS EMPTY`, `IS NOT EMPTY`) w MVP + reszta Faza 1?
- [ ] **Query mode UX validation** — power user feature, ale UX nie trywialny. POC w Sprint 1? Użyć biblioteki `react-querybuilder` (MIT, ~50KB) czy build from scratch?
- [ ] **Multi-channel filter** — *„description scopable na Shopify ma X"* — jak UX-owo? Default current channel sub-tab + override przez chip?
- [ ] **Smart filter presets — user-defined w MVP?** — w MVP built-in only. Czy *„Zapisz Saved View jako Smart Preset"* (analog Saved Views ale dla filter previews) do MVP czy Faza 1?
- [ ] **Cmd+K NLP filter — Faza 1 release** — kiedy *„pokaż mi produkty z niespójnymi opisami"* jako natural language (vs preset click)? Faza 1 z plate-ai integration?
- [ ] **Bulk rollback dla `publish` — sales sync** — czy mamy `orders` table w bazie (sync z kanałów)? Jeśli nie, *„skip sold-since-publish"* niemożliwe → must accept *„best effort + raport"* approach. Decyzja: akceptujemy *„best effort + raport"* w MVP (z Fali 4).
- [ ] **Select-all-matching limit** — 10k? 50k? 100k? UX warning vs. hard block?
- [ ] **Search performance edge cases** — query z 5+ słowami: *„czujnik indukcyjny Festo 24V IP67"* — strict prefix nie zadziała. Klient potrzebuje *„at least one word matches"* — Meilisearch domyślnie tak działa, ale to nie jest prefix. Reconfirm semantic w Sprint 1 POC.
- [ ] **Mobile UX** — z epiku 02 brak responsive, scroll horizontal. Cmd+K na mobile (touch device) — button w toolbar zamiast keyboard shortcut. Czy mobile MVP scope?
- [ ] **Filter na archived value** (np. brand "Bosch" archived w Modelowaniu) — chip aktywny, ale value picker nie pokazuje. Czy to OK UX czy wymaga forced migration filter chip → new value?
- [ ] **Cmd+K out-of-context queries** (klient w liście wpisuje *„dodaj użytkownika"*) — agent przekierowuje do Settings. Czy zachować selection state po cross-context navigation?
- [ ] **Pricing Cmd+K w Trial** — 10 commands/dzień restrictive vs. 20 commands/Trial-okres total?

### 14.3 Założenia, które trzeba zwalidować

- **Założenie 1:** *„60% czasu Kasi na liście produktów"* — bazuje na heurystyce z konkurencji (Akeneo metryki). Walidacja: session replay Hotjar na design partner #1 w pierwszych 3 miesiącach trial.
- **Założenie 2:** *„Cmd+K accuracy >90% na typowych queries"* — bazuje na Claude Sonnet 4.5 capability dla strukturyzowanych intents. Walidacja: POC w Sprint 1 z 50 typowymi queries Marcina, manual scoring.
- **Założenie 3:** *„24h rollback wystarczy"* — bazuje na intuicji *„większość pomyłek user widzi w godzinach od popełnienia"*. Walidacja: monitoring rollback usage w pierwszych 6 miesiącach (jeśli >20% rollbacków odbywa się w 23-25h window → rozszerzyć do 48h).
- **Założenie 4:** *„BYOK od Pro tier akceptowalne"* — bazuje na unit economics. Walidacja: rozmowy z 5 prospects czy BYOK to dealbreaker (zwłaszcza SMB tier).
- **Założenie 5:** *„Filter URL share działa cross-team"* — założenie że Magda i Kasia mają shared sesje (zalogowane na tych samych URL). Walidacja: czy SSO Faza 3 wymaga zmiany filter persistence schema?
- **Założenie 6:** *„Pełne operatory od dnia 1 = killer differentiator"* — bazuje na Akeneo feedback z forum. Walidacja: pytanie do design partners *„czy chcesz pełne operatory czy wystarczy `=` i `IS EMPTY`?"*. Jeśli >70% mówi *„wystarczy core"* — Faza 1 cut.
- **Założenie 7:** *„Kasia/Magda nie chcą custom scripts mode"* — bazuje na profile persony. Walidacja: explicit pytanie design partners. Jeśli pojawi się popyt, Faza 2 kandydat.

---

## 15. Następne kroki

1. **Walidacja konceptu z first design partner** — przed Sprintem 1. Profile: polski B2B techniczny e-commerce 5-15k SKU multi-locale. Pytania kluczowe: pełne operatory vs. core, Query mode konieczne czy luxury, Cmd+K wow vs. fluff, BYOK acceptable czy dealbreaker.
2. **POC Cmd+K mock w Sprint 1** — rule-based intent parsing (regex + keyword matching) na 50 typowych queries Marcina. Benchmark accuracy. Decyzja: kontynuuj z Anthropic, lub wycofaj feature.
3. **POC operator implementation w Sprint 1** — Akeneo-style pełne operatory per typ. Benchmark complexity (~10-14h) vs. wartość dla design partners.
4. **POC GIN index performance** — syntetyczny 200k SKU dataset, benchmark filter latency p95. Jeśli >500ms → fallback Meilisearch dla filter queries.
5. **Decyzja scope cuts przed Sprintem 1** — Marcin akceptuje +131-182h, ale możliwe:
   - (a) Query mode AND/OR → Faza 1 (~12-16h oszczędność).
   - (b) Cmd+K bulk actions → Faza 1 (~12-16h oszczędność, pozostaje schema-ops only z epiku 08).
   - (c) Increment numeric + multi-attr bulk edit → Faza 1 (~8-10h oszczędność).
   - Rekomendacja: cut (a) i (c), zachowaj (b) — Cmd+K bulk to USP wow-effect dla demo.
6. **Aktualizacja `Project Plan/02-plan-projektu-pim.md`** — epik 0.4/0.5/0.6/0.7/0.11 estymacje + nowe ryzyko **R-30** (List feature scope creep).
7. **Aktualizacja `Project Plan/03-funkcjonalnosci-mvp.md`** — dodać user stories US-LIST-001 do US-LIST-022 (z [`feature-list-advanced.md`](../UI/feature-list-advanced.md) §12).
8. **Aktualizacja pitch deck** — wprowadzić *„coming in Faza 1"* note dla AI smart filters (krytyczna nota §11). Slajd 4 (USP Agentic) — Cmd+K + manual integration + 24h rollback jako triad differentiatorów.
9. **Wireframes Figma** — przekazać external UX designer (PRD master §13.5) — priorytet: Advanced filter panel push-down + Cmd+K palette + bulk wizard Step 3 preview (sample 5 + aggregate).
10. **Walidacja Cortex PIM master PRD spójność** — czy ten feature-PRD jest kompatybilny z roadmap master PRD § 13 (cuts mogą wymagać master update).

---

## 16. Załączniki i powiązane dokumenty

- [`../UI/feature-list-advanced.md`](../UI/feature-list-advanced.md) — **źródło prawdy detaliczne** (wireframes ASCII, SQL schema, 22 user stories, business rules).
- [`../UI/epik-02-produkty.md`](../UI/epik-02-produkty.md) — epik nadrzędny (lista produktów baseline, detail view, Saved Views, Excel-like editing).
- [`../UI/feature-imports.md`](../UI/feature-imports.md) — wzorzec strukturalny (status 🟢 zaimplementowane IMP-01..IMP-15) + sąsiednie feature (importy CSV/XLS/XLSX).
- [`../UI/00-plan-ui.md`](../UI/00-plan-ui.md) — master plan UI.
- [`../../Zrodla/PRD/PRD-PIM.md`](../../Zrodla/PRD/PRD-PIM.md) — **master product PRD Cortex PIM** (pozycjonowanie globalne, ICP, multitenant SaaS, pricing, MVP scope całościowy).
- [`../01-architektura-pim.md`](../01-architektura-pim.md) — architektura (ADR-006 hybrid attribute model, ADR-009 ObjectType, ADR-010 axis-driven variants, ADR-011 per-tenant locale fallback, sekcja 8.5 limits AI).
- [`../02-plan-projektu-pim.md`](../02-plan-projektu-pim.md) — backlog i estymacje (do aktualizacji per §15 punkt 6).
- [`../03-funkcjonalnosci-mvp.md`](../03-funkcjonalnosci-mvp.md) — user stories MVP (do aktualizacji per §15 punkt 7).
- [`../../CLAUDE.md`](../../CLAUDE.md) — konstytucja projektu (memory management, single-origin Caddy, BYOK).

---

*Dokument wygenerowany 2026-05-13 jako synteza brainstormingu 4-falowego (2026-05-11) z [`feature-list-advanced.md`](../UI/feature-list-advanced.md). Status: Draft — wymaga walidacji z design partners (§15 punkt 1), decyzji o scope cuts (§15 punkt 5), i POC w Sprint 1 (§15 punkty 2-4) przed startem implementacji.*
