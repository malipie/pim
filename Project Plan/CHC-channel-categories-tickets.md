# Epik CHC — Channel Categories

**Data:** 2026-06-05 (przepisane 2026-06-06)
**Kontekst:** Rozdzielenie kategorii schema-driving (master) od kategorii nawigacyjnych per kanał. Wynika z sesji architektonicznej + LLM Council review.
**Priorytet:** MVP — fundament + bezpieczeństwo danych PRZED pilotem. Node mapping — Faza 1.

---

## Zaakceptowane decyzje architektoniczne

1. **Osobna tabela `channel_category_nodes`** dla kategorii nawigacyjnych kanałów — NIE flaga `is_schema_driver` na istniejącej tabeli `objects`. Kategorie master zostają niezmienione. `EffectiveAttributeGroupResolver` nigdy nie widzi nowej tabeli — zero ryzyka pomyłki.

2. **Osobna tabela `object_channel_placements`** dla przypisań produktów do kanałowych węzłów nawigacyjnych — NIE rozszerzenie pivotu `object_categories`. Tabela master pozostaje niezmieniona.

3. **Brak zakładki „Publikacja"** — channel placements widoczne inline w zakładce **„Kategorie"** na karcie produktu. Górna sekcja: picker kategorii master. Dolna sekcja: „Gdzie trafia na kanałach".

4. **Schema drift detection: asynchroniczny** — wykrywanie driftu triggerowane przez zdarzenie przeniesienia kategorii (Messenger job), NIE przy każdym otwarciu karty produktu.

5. **Filtrowanie w node mapping UI** po `ChannelObjectTypeMapping` — lewy panel split-view pokazuje tylko kategorie ObjectType, które kanał faktycznie publikuje. Brak nowych tabel — odczyt z istniejącej encji.

---

## Zależności

```
CHC-01 (nowa tabela channel_category_nodes)
  → CHC-02 (nowa tabela object_channel_placements)
      → CHC-03 (zakładka Kategorie + placements inline)
  → CHC-05 (warning przed przeniesieniem kategorii)
      → CHC-04 (schema snapshot + async drift)

CHC-06 → CHC-07 → CHC-08 (Faza 1, po pilocie)
```

---

## GRUPA A — Fundament architektoniczny (MVP)

---

### CHC-01 — Nowa tabela `channel_category_nodes` + CRUD API + usunięcie starego pickera z UI

**Dlaczego:**
Kategorie kanałowe (drzewo Allegro, Shopify, BaseLinker itp.) potrzebują własnej tabeli. Mają inny kształt niż kategorie master: mają `external_code` (ID w zewnętrznym systemie), `channel_id`, nie mają grup atrybutów. Wspólna tabela z flagą byłaby mieszaniem dwóch różnych rzeczy.

**Schema nowej tabeli:**
```sql
CREATE TABLE channel_category_nodes (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  channel_id    UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
  parent_id     UUID NULLABLE REFERENCES channel_category_nodes(id) ON DELETE CASCADE,
  code          VARCHAR(128) NOT NULL,
  label         JSONB NOT NULL DEFAULT '{}',   -- {"pl": "RTV i AGD", "en": "Electronics"}
  path          LTREE NOT NULL,                -- dla szybkich zapytań drzewiastych
  external_code VARCHAR(255) NULLABLE,         -- ID kategorii w zewnętrznym systemie (np. "123456" dla Allegro)
  created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE (tenant_id, channel_id, code)
);
CREATE INDEX channel_category_nodes_path_idx    ON channel_category_nodes USING GIST (path);
CREATE INDEX channel_category_nodes_channel_idx ON channel_category_nodes (channel_id);
```

**`external_code` — jak to działa dla operatora:**
Operator budując drzewo kanału (np. Allegro) ręcznie wpisuje ID kategorii z zewnętrznego systemu na każdym węźle. Robi to raz przy konfiguracji kanału. Integracja przy eksporcie czyta `external_code` węzła i używa go jako ID kategorii w zewnętrznym systemie — operator nigdy nie wpisuje go na produkcie.

Przykład: węzeł „RTV > Telewizory" ma `external_code = "123456"`. Produkt zmapowany do tego węzła → integracja wysyła go na Allegro do kategorii 123456. Zmiana `external_code` na węźle aktualizuje wszystkie produkty automatycznie — zero edycji per produkt.

**Aktualizacja Channel:**
- `Channel.categoryTreeRootId` (UUID) — dotychczas soft FK do `objects.id`. Po CHC wskazuje na `channel_category_nodes.id` (root węzeł drzewa kanału). Zmiana: soft FK UUID pozostaje (brak Doctrine relation), zmienia się tylko semantyka — validator `ChannelCategoryRootValidator` zostaje zastąpiony nowym który sprawdza `channel_category_nodes`.

**Scope:**

1. Migracja: CREATE TABLE jak wyżej
2. Aktualizacja `Channel` entity + validator
3. **Usuń z UI** pole „Korzeń kategorii" z formularza tworzenia/edycji kanału (`/settings/channels/new`). Root tworzony automatycznie przy pierwszym użyciu zakładki „Struktura nawigacyjna".
4. API endpoints:
   - `POST /api/channels/{channelId}/navigation-tree` — tworzy root węzeł, ustawia `channel.categoryTreeRootId`
   - `POST /api/channels/{channelId}/navigation-tree/nodes` — dodaje węzeł, wymaga `parent_id`
   - `PATCH /api/channels/{channelId}/navigation-tree/nodes/{nodeId}` — edycja label + external_code
   - `DELETE /api/channels/{channelId}/navigation-tree/nodes/{nodeId}` — usuwa węzeł i potomków
   - `GET /api/channels/{channelId}/navigation-tree` — flat list z path dla renderowania drzewa
5. Encja `ChannelCategoryNode` + Doctrine ORM mapping + LtreeType (już istnieje w projekcie)

**Acceptance criteria:**
- [ ] Migracja up/down bez błędów
- [ ] Pełny CRUD przez API z walidacją (parent musi należeć do tego samego kanału)
- [ ] Stary picker „Korzeń kategorii" usunięty z UI tworzenia/edycji kanału
- [ ] `EffectiveAttributeGroupResolver` — żadnych zmian, nie widzi nowej tabeli
- [ ] ApiTestCase: create root → add nodes → get tree → delete node
- [ ] PHPStan max: 0 errors
- [ ] Smoke test: kompletny flow przez curl

**Szacunek:** 8-10h
**Blokuje:** CHC-02, CHC-05

---

### CHC-02 — Nowa tabela `object_channel_placements`

**Dlaczego:**
Produkt musi wiedzieć w jakim węźle nawigacyjnym kanału się znajduje (żeby integracja wiedziała gdzie go wysłać). To jest oddzielny byt od przypisania do kategorii master — inna semantyka, inna tabela.

**Schema:**
```sql
CREATE TABLE object_channel_placements (
  id                       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id                UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  object_id                UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
  channel_id               UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
  channel_category_node_id UUID NOT NULL REFERENCES channel_category_nodes(id) ON DELETE CASCADE,
  source                   VARCHAR(16) NOT NULL DEFAULT 'manual',  -- 'manual' | 'auto'
  created_at               TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at               TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE (tenant_id, object_id, channel_id)  -- jeden placement per produkt per kanał
);
CREATE INDEX ocp_object_idx  ON object_channel_placements (object_id);
CREATE INDEX ocp_channel_idx ON object_channel_placements (channel_id);
```

**Scope:**

1. Migracja: CREATE TABLE jak wyżej
2. Encja `ObjectChannelPlacement` + Doctrine ORM
3. Repozytorium `ObjectChannelPlacementRepository`:
   - `findByObject(CatalogObject $object): array` — wszystkie placements produktu
   - `findByObjectAndChannel(CatalogObject $object, Uuid $channelId): ?ObjectChannelPlacement`
   - `upsert(...)` — create or update
4. `object_categories` — **bez zmian** (tabela master pozostaje czysta)
5. `findPrimary()` w `ObjectCategoryRepository` — **bez zmian**

**Acceptance criteria:**
- [ ] Migracja up/down
- [ ] UNIQUE constraint działa (jeden placement per obiekt per kanał)
- [ ] Repozytorium: testy dla findByObject, findByObjectAndChannel, upsert
- [ ] `object_categories` — zero zmian, istniejące testy przechodzą
- [ ] PHPStan max: 0 errors

**Szacunek:** 3-4h
**Zależy od:** CHC-01
**Blokuje:** CHC-03

---

### CHC-03 — Rozszerzenie zakładki „Kategorie" o channel placements inline

**Dlaczego:**
Operator widzi w jednym miejscu: do jakiej kategorii master należy produkt (steruje formularzem) i gdzie ten produkt trafia na każdym kanale (nawigacja/syndykacja). Zero nowych zakładek.

**UX (zaakceptowany):**
```
┌─────────────────────────────────────────────────┐
│  KATEGORIA                                      │
│                                                 │
│  📁 RTV > Telewizory          [Zmień]           │
│  (steruje formularzem produktu)                 │
│                                                 │
│  ─────────────────────────────────────────────  │
│  GDZIE TRAFIA NA KANAŁACH                       │
│                                                 │
│  Allegro     RTV i AGD > Telewizory  (auto) ✓  │
│  Shopify     Electronics > TVs       (auto) ✓  │
│  BaseLinker  ⚠ brak mapowania        [Przypisz] │
│                                                 │
│              [Nadpisz przypisanie ›]            │
└─────────────────────────────────────────────────┘
```
Przy 5+ kanałach: sekcja „Gdzie trafia na kanałach" domyślnie zwinięta, widoczne tylko wpisy z ⚠.

**Scope:**

**Backend:**
1. `GET /api/objects/{id}/channel-placements` — lista per kanał:
   ```json
   [
     {
       "channelId": "...", "channelCode": "allegro",
       "channelLabel": {"pl": "Allegro"},
       "placement": {
         "nodeId": "...", "nodePath": "RTV i AGD > Telewizory", "source": "manual"
       }
     },
     {
       "channelId": "...", "channelCode": "baselinker",
       "channelLabel": {"pl": "BaseLinker"},
       "placement": null
     }
   ]
   ```
2. `PUT /api/objects/{id}/channel-placements/{channelId}` — ręczne przypisanie:
   - Body: `{"nodeId": "UUID"}` — węzeł z `channel_category_nodes` tego kanału
   - Upsert w `object_channel_placements` z `source='manual'`
3. `DELETE /api/objects/{id}/channel-placements/{channelId}` — usuwa placement. W MVP: produkt dostaje status ⚠ brak mapowania. W Faza 1 (gdy CHC-07 istnieje): produkt wraca do source='auto' i handler przywraca mapowanie.

**Frontend:**
1. W zakładce „Kategorie" — sekcja „Gdzie trafia na kanałach" poniżej pickera master
2. Wiersze per kanał: nazwa kanału, path węzła lub ⚠, status `(auto)`/`(ręcznie)`/`⚠ brak`
3. `[Przypisz]`/`[Nadpisz]` → modal z tree-pickerem węzłów kanału (`GET /api/channels/{id}/navigation-tree`)
4. `[Przywróć automatyczne]` → DELETE endpoint
5. Przy 5+ kanałach: zwijanie z licznikiem ⚠

**Acceptance criteria:**
- [ ] GET endpoint zwraca dane dla wszystkich kanałów tenanta
- [ ] PUT: walidacja że node należy do właściwego kanału
- [ ] DELETE: usuwa placement
- [ ] ⚠ widoczny dla kanałów bez przypisania
- [ ] Modal picker z drzewem kanału działa
- [ ] Przy 5+: zwijanie działa, widoczne tylko ⚠
- [ ] Playwright E2E: zmień placement → odśwież → nowy placement widoczny
- [ ] ApiTestCase: pełny flow PUT + GET + DELETE
- [ ] Smoke test na pim.localhost

**Szacunek:** 10-14h
**Zależy od:** CHC-01, CHC-02

---

## GRUPA B — Bezpieczeństwo danych (krytyczne przed pilotem)

---

### CHC-05 — Warning przed przeniesieniem kategorii

**Dlaczego:**
Operator musi wiedzieć PRZED przeniesieniem kategorii ile produktów dotknie zmiana i jakie pola się zmienią. Po potwierdzeniu system triggeruje asynchroniczne wykrywanie driftu (CHC-04).

**Scope:**

1. `GET /api/categories/{id}/move-impact?targetParentId={UUID}`:
   ```json
   {
     "affectedObjectsCount": 3200,
     "schemaWillChange": true,
     "addedGroupLabels":   [{"pl": "Parametry techniczne"}],
     "removedGroupLabels": [{"pl": "Wymiary opakowań"}]
   }
   ```
   - Liczba produktów: COUNT przez ltree (`path <@ currentPath`)
   - Zmiana schematu: porównanie `resolveForCategoryPreview(type, currentParent)` vs `resolveForCategoryPreview(type, targetParent)`

2. `PATCH /api/categories/{id}` (zmiana `parentId`):
   - Gdy `affectedObjectsCount > 0` i brak `?confirmed=true` → HTTP 409 z body impaktu
   - Z `?confirmed=true` → wykonaj przeniesienie → wyślij `CheckSchemaDriftForCategory` message do Messengera (CHC-04 handler to odbierze)

3. Frontend: drag-drop lub zmiana parenta → jeśli `affectedObjectsCount > 0` → modal:
   - Liczba produktów
   - Lista pól które znikną / pojawią się (gdy `schemaWillChange=true`)
   - [Anuluj] i [Tak, przenieś i oznacz do przeglądu]

**Acceptance criteria:**
- [ ] GET move-impact: poprawna liczba produktów + poprawna lista zmienionych grup
- [ ] PATCH bez `confirmed` przy produktach → 409 z danymi impaktu
- [ ] PATCH z `confirmed` → przeniesienie + Messenger message wysłana
- [ ] Modal pojawia się w UI przed przeniesieniem
- [ ] ApiTestCase: pełny flow
- [ ] Smoke test: przenieś kategorię z produktami → potwierdź → sprawdź `schema_drift` po chwili

**Szacunek:** 6-8h
**Zależy od:** CHC-01
**Blokuje:** CHC-04

---

### CHC-04 — Schema snapshot + asynchroniczne wykrywanie driftu

**Dlaczego:**
Gdy ktoś przeniesie kategorię w drzewie master, schemat produktów w tej kategorii może się zmienić — pojawią się nowe pola, znikną stare. Dane wypełnione w starych polach zostają w bazie ale formularz ich nie pokazuje. Operator powinien o tym wiedzieć.

**Ważne: wykrywanie driftu działa asynchronicznie** — triggerowane przez zdarzenie przeniesienia kategorii (CHC-05), NIE przy każdym otwarciu karty produktu. Zero wpływu na wydajność codziennej pracy.

**Scope:**

**1. Kolumny na `objects`:**
```sql
ALTER TABLE objects ADD COLUMN schema_snapshot JSONB NULLABLE;
ALTER TABLE objects ADD COLUMN schema_drift    BOOLEAN NOT NULL DEFAULT FALSE;
```
`schema_snapshot`: `{attributeGroupIds: [...], capturedAt: "ISO8601", masterCategoryId: "UUID"}`

**2. Listener `SchemaSnapshotListener` — zapis snapshotu (synchroniczny, jednorazowy):**
- Trigger: event `ObjectAttributesChanged` (już istnieje)
- Jeśli `schema_snapshot IS NULL` → zapisz snapshot aktualnego schematu. Tylko raz — jeśli snapshot istnieje, listener nic nie robi.

**3. Handler `CheckSchemaDriftHandler` — wykrywanie driftu (asynchroniczny, Messenger):**
- Trigger: `CheckSchemaDriftForCategory` message wysyłana przez CHC-05 po przeniesieniu kategorii
- Handler pobiera wszystkie produkty z kategorii (i potomnych) → dla każdego porównuje `schema_snapshot.attributeGroupIds` z aktualnym wynikiem `EffectiveAttributeGroupResolver`
- Jeśli różnica → ustaw `schema_drift = true`
- Handler działa w tle, operator nie czeka

**4. API:**
- `GET /api/objects/{id}` — dodaj `"schemaDrift": true/false` do odpowiedzi
- `POST /api/objects/{id}/schema-drift/acknowledge` — operator potwierdza: `schema_drift=false`, snapshot aktualizowany

**5. Frontend:**
- Baner w górnej części zakładki **„Kategorie"** gdy `schemaDrift=true`:
  > „Schemat tego produktu zmienił się po ostatnim wypełnieniu. Sprawdź czy wszystkie dane są kompletne."
  > [Rozumiem, zaktualizuj schemat]
- Kliknięcie → `acknowledge` endpoint → baner znika

**Acceptance criteria:**
- [ ] Pierwsze wypełnienie produktu → snapshot zapisany
- [ ] Przeniesienie kategorii (CHC-05 confirmed) → Messenger message wysłana → handler ustawia `schema_drift=true` dla dotkniętych produktów
- [ ] Baner widoczny w zakładce „Kategorie" gdy `schema_drift=true`
- [ ] Baner NIE pojawia się przy otwieraniu produktu bez driftu (zero dodatkowych zapytań przy page load)
- [ ] Acknowledge endpoint resetuje flagę i aktualizuje snapshot
- [ ] Produkt bez żadnych wartości: `schema_snapshot=null`, `schema_drift=false` — brak banerów
- [ ] PHPUnit: listener, handler (mock resolver)
- [ ] ApiTestCase: acknowledge endpoint
- [ ] Smoke test

**Szacunek:** 8-10h
**Zależy od:** CHC-05

---

## GRUPA C — Node mapping (Faza 1, po pierwszym kliencie)

*Wdrożyć po feedbacku z pierwszego pilota.*

---

### CHC-06 — Tabela `channel_category_node_mappings` + CRUD API

**Dlaczego:**
Przy 50k produktów ręczne ustawianie channel placement per produkt (CHC-03) to niemożliwe. Node mapping definiuje raz: „kategoria master Telewizory → węzeł Allegro RTV > Telewizory". System auto-assignuje wszystkie produkty.

**Schema:**
```sql
CREATE TABLE channel_category_node_mappings (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id        UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  channel_id       UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
  master_cat_id    UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
  channel_node_ids UUID[] NOT NULL DEFAULT '{}',  -- M:N po stronie kanału
  UNIQUE (tenant_id, channel_id, master_cat_id)
);
```
`channel_node_ids`: tablica — jeden master może mapować na wiele węzłów kanału (np. "Laptopy" → Allegro "Komputery > Laptopy" I "Elektronika > Laptopy").

**API:**
- `GET /api/channels/{channelId}/node-mappings`
- `PUT /api/channels/{channelId}/node-mappings/{masterCategoryId}` — body: `{"nodeIds": [...]}`
- `DELETE /api/channels/{channelId}/node-mappings/{masterCategoryId}`
- Walidacje: `masterCategoryId` musi być kategorią master, każdy nodeId musi należeć do drzewa kanału

**Acceptance criteria:**
- [ ] Migracja up/down
- [ ] Pełny CRUD przez API
- [ ] Walidacje: zły typ kategorii → 422, węzeł z innego kanału → 422
- [ ] ApiTestCase
- [ ] PHPStan max: 0 errors

**Szacunek:** 4-6h
**Blokuje:** CHC-07, CHC-08

---

### CHC-07 — Auto-assignment: Messenger handler po przypisaniu kategorii master

**Dlaczego:**
Gdy operator przypisuje kategorię master do produktu → system sprawdza node mappings → auto-tworzy channel placements dla wszystkich kanałów. Operator nie musi nic robić ręcznie.

**Scope:**

1. Trigger: event `ObjectPrimaryCategoryAssigned`
2. Handler `AutoAssignChannelPlacementsHandler`:
   - Pobierz `channel_category_node_mappings` dla `master_cat_id`
   - Dla każdego mapowania:
     - Jeśli placement istnieje z `source='manual'` → skip (ręczne ma priorytet)
     - Jeśli brak lub `source='auto'` → upsert `object_channel_placements` z `source='auto'`
3. `[Przywróć automatyczne]` w UI (CHC-03) → ustaw `source='auto'` → handler przywróci mapping

**Acceptance criteria:**
- [ ] Przypisanie primary category → handler działa asynchronicznie
- [ ] Placements tworzone tylko dla kanałów z aktywnym mappingiem
- [ ] `source='manual'` nie jest nadpisywane
- [ ] PHPUnit: handler (mock repo)
- [ ] Integration test: assign → sprawdź object_channel_placements
- [ ] Smoke test: dodaj produkt do kategorii z mappingiem → sprawdź sekcję „Gdzie trafia na kanałach"

**Szacunek:** 6-8h
**Zależy od:** CHC-06, CHC-03

---

### CHC-08 — UI split-view mapowania węzłów (Master Tree ↔ Channel Tree)

**Dlaczego:**
Operator musi w jednym miejscu połączyć swoje kategorie master z węzłami kanału. Bez UI mapowanie istnieje tylko w API.

**UX:**
```
┌─────────────────────────────────┬────────────────────────────────┐
│  TWOJE KATEGORIE (master)       │  KATEGORIE ALLEGRO             │
│                                 │                                │
│  [Produkty] [Usługi]            │  Elektronika                   │
│  ↑ tylko ObjectType z           │    └ RTV i AGD                 │
│    ChannelObjectTypeMapping      │        └ Telewizory ◄──────┐  │
│                                 │   Moda                     │  │
│  📁 RTV > Telewizory  [Mapuj ►]─┘    └ Obuwie               │  │
│  📁 Obuwie            [Mapuj ►]──────────────────────────────┘  │
│  📁 Odzież            [+ Mapuj]                                  │
└─────────────────────────────────┴────────────────────────────────┘
```

**Scope:**
1. Zakładka „Mapowanie" w ustawieniach kanału
2. Lewy panel: kategorie master filtrowane po `ChannelObjectTypeMapping` → tabs per ObjectType (tylko te które kanał publikuje). Odczyt z istniejącej encji — brak nowych tabel.
3. Prawy panel: drzewo `channel_category_nodes` kanału
4. `[Mapuj]` → modal picker z drzewa kanału → M:N (można wybrać wiele węzłów)
5. Badge per węzeł: ile produktów dotkniętych
6. Bulk: [Wyczyść wszystkie mapowania kanału] z potwierdzeniem

**Acceptance criteria:**
- [ ] Lewy panel filtruje po ObjectType z `ChannelObjectTypeMapping`
- [ ] M:N mapowanie: jeden master → wiele węzłów kanału
- [ ] Po zapisaniu: CHC-08 handler auto-assignuje produkty
- [ ] Sekcja „Gdzie trafia na kanałach" (CHC-03) aktualizuje się
- [ ] Playwright E2E: split-view → mapuj → sprawdź na produkcie

**Szacunek:** 10-14h
**Zależy od:** CHC-06, CHC-07

---

## Podsumowanie

| Ticket | Opis | Szacunek | Priorytet |
|--------|------|----------|-----------|
| CHC-01 | Tabela `channel_category_nodes` + CRUD API + usunięcie starego pickera | 8-10h | MVP |
| CHC-02 | Tabela `object_channel_placements` | 3-4h | MVP |
| CHC-03 | Zakładka Kategorie — channel placements inline | 10-14h | MVP |
| CHC-04 | Schema snapshot + async drift detection | 8-10h | **PRZED PILOTEM** |
| CHC-05 | Warning przed przeniesieniem kategorii | 6-8h | **PRZED PILOTEM** |
| CHC-06 | Tabela node_mappings + CRUD API | 4-6h | Faza 1 |
| CHC-07 | Auto-assignment Messenger handler | 6-8h | Faza 1 |
| CHC-08 | UI split-view mapowania | 10-14h | Faza 1 |

**MVP (CHC-01..03):** ~21-28h
**Przed pilotem (CHC-04..05):** ~14-18h — **nie startuj pilota bez tych dwóch**
**Faza 1 (CHC-06..08):** ~20-28h — po feedbacku z pilota
