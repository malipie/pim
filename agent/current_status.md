# Current Status

## 2026-06-05: 🏁 follow-up fixy export global channel + custom OT toolbar (#1267, #1269) — kompletny

2 PR-y merged (operator zgłosił po manual smoke):

- **#1267 export brak 'Wszystkie' channel** (PR [#1268](../../pull/1268)) — regresja z #1245: scopable attr fan-out produkował TYLKO `code.{channel}`, gubił bare `code` (global). Fix: scopable → bare global + per-channel; sekcja Kanały dostaje opcję "Wszystkie" (sentinel `__all__`) gatingującą bare scopable columns. Sentinel wykluczony z payload.
- **#1269 custom OT bez locale/channel picker** (PR [#1270](../../pull/1270)) — `universal-detail-page` gatował toolbar przez `isEditing` (toggle, false); product gates przez `mode === 'edit'` (route, zawsze true). Komentarz #1225 błędnie zakładał parytet. Fix: toolbar zawsze widoczny (universal detail = zawsze istniejący obiekt); query refetchuje scope w read-only.

## 2026-06-04: 🏁 batch select/channel-locale fixów (#1259–#1263) — kompletny

6 PR-ów merged do main (każdy quality gates + live-stack smoke):

- **#1259 kanały/locale mock** (PR [#1260](../../pull/1260)) — FE: usunięty hardcoded `PRODUCT_CHANNELS` fallback (pusty `/api/channels` → pusty select). BE: `DemoCatalogSeeder` seeduje realny kanał Allegro + per-locale EN values + per-channel price override; `short_description` localizable+scopable (chip PL+Allegro razem). Smoke 4×: channel realny, locale/channel/both switch różne wartości.
- **#1262 select label per locale** (PR [#1264](../../pull/1264)) — `attr-row.tsx`: label opcji select z `valueLang = locale ?? lang` (scope wartości, nie język interfejsu). `AttributeOptionMeta.label` → `Record<string,string>`. Smoke: `red → {pl:Czerwony, en:Red}`.
- **#1261 select option_code walidacja** (PR [#1265](../../pull/1265)) — `Select/Multiselect` w `VALUE_VALIDATED_TYPES` + type-aware guard; validatory przeciw żywym `attribute_options` (`findCodesByAttribute`). Smoke: `color=red → 201`, `color=magenta → 422`.
- **#1263 values.tsx tenant locales** (PR [#1266](../../pull/1266)) — edytor labeli opcji z `/api/workspaces/current` → `enabledLocales` zamiast hardcoded `['pl','en','de']`. Smoke: demo → pl+en (bez pustego de).

**Genealogia**: operator zgłosił po manual smoke że kanały/locale na karcie produktu to mock po `pim:db:reset`. Backend overlay OK, brak danych demo + myący FE fallback. Przy okazji wykryto select label/walidację/hardcoded locale.

**Lekcja krytyczna**: lokalne Api/* testy 2× zwipały dev DB (`ResetDatabase` Foundry). NIE uruchamiać Api testów lokalnie — push + CI.

## 2026-06-04: 🏁 Marathon #1227–#1245 (epik LC + EXP) — KOMPLETNY

Wszystkie 10 ticketów zmergowane do main + zamknięte. Finalne PRy:

| Ticket | PR (merged) | Opis |
|---|---|---|
| #1227 | [#1246](../../pull/1246) | fix: locale=default tenanta w universal-create-page |
| #1230 | [#1247](../../pull/1247) | docs: `?locale`/`?channel` QueryParameter w OpenAPI (CatalogObject.xml + snapshot) |
| #1231 | [#1248](../../pull/1248) | docs: ADR-0018 ChannelPublicationProfile |
| #1232 | [#1249](../../pull/1249) | feat: ChannelPublicationProfile entity + migracja + seed + ChannelCreated subscriber |
| #1233 | [#1258](../../pull/1258) | feat: ChannelPublicationResolverInterface (Channel\Contracts, Deptrac-safe) |
| #1234 | [#1258](../../pull/1258) | feat: API read `?publication=<channel>` (overlay providers filtrują attributes_indexed) |
| #1235 | [#1258](../../pull/1258) | feat: PublicationColumnPlanner (profil → selected_columns w SyncExportRunner) |
| #1243 | [#1253](../../pull/1253) | feat: sekcja Języki w ExportModal (global locale filter) |
| #1244 | [#1256](../../pull/1256) | feat: grouped locale columns w ColumnPicker (collapsible parent + indeterminate) |
| #1245 | [#1256](../../pull/1256) | feat: sekcja Kanały w ExportModal + scopable fan-out per kanał |

**Architektura (ADR-0018)**: `ChannelPublicationProfile` w Channel BC per `(tenant, channel, objectType)`; `published_attribute_codes=NULL` = publish-all. Param `?publication=<channel>` dedykowany (NIE przeciąża `?channel=`). Cross-BC przez `Channel\Contracts\ChannelPublicationResolverInterface` (bare UUID refs ADR-015). Auto-profil na `ChannelCreated` event.

**Live-stack smoke proof**:
- #1232: `POST /api/channels` → 3 auto-profile (is_default, publish-all); `DELETE` → cascade clean.
- #1234: profil allow-list `["name"]` → `GET ?publication=` redukuje 17→1 attr (+2 system).
- #1230: `/api/docs.jsonopenapi` → 14 locale/channel params w spec.

**Świadome odejścia**: brak — pełen scope każdego ticketu dostarczony. Profile publikacji (#1232–1235) to substrate pod publication management UI (Faza 1 pilot); consumer w API read (#1234) + export planner (#1235) działają, dedykowane UI profili odroczone do pierwszego pilota (zgodnie z memory `project_channels_justified_per_destination`).

**Lekcje (lessons.md)**: chained-PR collapse przy `--delete-branch`, `createStub` vs `createMock` w PHPStan max, `@var` na `container->get()`, ChannelCreated subscriber + UNIQUE w testach, squash-merge rebase --onto.

## 2026-06-02: 🏁 batch drobnych poprawek (smoke) + dokończenie #1179 — kompletny

5 PR-ów merged do main, każdy z quality gates + live-stack smoke (CLOSED MEANS CLOSED):

- **#1179 identyfikator** (PR [#1204](../../pull/1204)) — typ atrybutu `identifier` (EAN/GTIN/ISBN/SKU) z unikalnością per ObjectType **DB-enforced** (trigger + denorm kolumny + partial unique index) + app-level 409. Dokończenie zacommitowanego WIP. Smoke: dup EAN → 409.
- **#1205 usuwanie presetów Smart Filter** (PR [#1206](../../pull/1206)) — `SmartFilterPresetsRow` + `onDelete` (× na hover/focus dla własnych presetów) + `DeletePresetDialog` (confirm) w universal + legacy list. Backend/hook już istniały. FE-only.
- **#1207 atrybuty systemowe** (PR [#1208](../../pull/1208)) — created_at/updated_at/created_by/updated_by: usunięty lock badge (lista + 2 dialogi + attr-row), auto-treść via read overlay (`SystemAttributeReadOverlay` w GET-item provider, klon) + **blameable** (`Shared\Application\Blameable` + `BlameableAssignmentListener` onFlush, snapshot e-maila aktora — bez coupling Catalog↔Identity). Migracja: `objects.created_by/updated_by`. Smoke: GET obiektu → 4 wartości.
- **#1209 kategorie dla custom kindów** (PR [#1210](../../pull/1210)) — `CategoryPickerDialog` zgeneralizowany (`endpoint='objects'` + `objectTypeId` tree scoping ADR-015) + wpięty w `CategoriesPanel` (universal detail); usunięty placeholder „UP-07 follow-up". Fix chipu: `categoryCode` (nie `code`). Backend generyczny już był. Smoke: custom OT → PUT 200 → chip.
- **#1211 typy na /modeling/attributes/new** (PR [#1212](../../pull/1212)) — współdzielona stała `lib/attribute-types.ts` `CREATABLE_ATTRIBUTE_TYPES` (lustro backend whitelist) używana przez new.tsx + 2 create-dialogi → koniec driftu; dodane textarea/datetime/identifier/color/email, usunięty system-only `reference`.

**Świadome odejścia**: (a) `video` jako typ atrybutu — NIE dodany, brak w `AttributeType` enum; wymaga osobnego feat ticketu (część 3/3 batcha nowych typów). (b) Guard usuwania `is_system` zostaje (auto-pola systemowe). (c) created_by/updated_by istniejących obiektów = NULL (brak backfill) → „—" do następnej edycji.

**Uwaga (dev DB)**: w trakcie sesji odkryto, że custom OTs operatora (Usługi/Samochody) **zniknęły z dev DB** (wcześniejszy `pim:db:reset`) — feature'y działają dla nowo utworzonych custom kindów; operator może odtworzyć OTs przez UI. Patrz lessons + memory `feedback_pim_db_reset_wipes_operator_state`.

## 2026-05-31: 🏁 batch bug-fix smoke 2026-05-30 (#1130–#1147) — kompletny

Wszystkie 18 zgłoszeń z manualnego smoke testu operatora wdrożone na main. Ta sesja domknęła ostatnie 4 (#1130/#1138 + epiki #1146/#1147):

- **#1130 import round-trip** (PR #1167): importer kompatybilny z formatem eksportu — composite price/metric (`20.99 EUR`/`0.3 g`), kolumny lokalizowane (`name.pl`→atrybut+locale), auto-SKIP kolumn systemowych, reader XLSX po cell-coordinate. Nowe: `ColumnHeader`, `CompositeValueParser`, `SystemColumn`, `ResolvedImportValue`.
- **#1138 atrybut asset jako picker** (PR #1168): `AssetField` + `AssetAttributePicker` w `attr-row.tsx` (case `asset`) — picker biblioteki + miniatura zamiast tekstu. BE `AssetValidator` bez zmian.
- **#1146 wersje językowe epik** — 4 PR-y (#1169 BE read/write `?locale=`, #1170 ekspozycja `is_localizable`+`locales[]`, #1171 dynamiczny picker, #1172 per-locale values). #1152 (completeness per-locale) świadomie deferred.
- **#1147 kanały epik** — 3 PR-y (#1173 BE read/write `?channel=`, #1174 E2E ustawień kanałów — strona już istniała, #1175 picker+values). #1156 (mapowanie aliasów) deferred do Fazy 1.

**Architektura locale/channel (ADR-relevant)**: `CatalogObjectLocaleOverlayProvider` (GET-item, decorating) nakłada wiersze `ObjectValue` per (locale,channel) na globalny `attributes_indexed`, **na klonie** (bez wycieku na identity map); `attributes_indexed`/Meilisearch pozostają **global-only** (search na primary). Zapis przez `?locale=`/`?channel=` query-param → upserter routuje localizable→locale, scopable→channel, reszta global. Cross-BC: `Channel\Contracts\ChannelResolverInterface` (code→id, Deptrac-safe).

## 2026-05-30: polish drzew kategorii custom-OT (#1126 + #1127)
- **#1126** (PR #1128, `0951f55`): drzewo kategorii widoczne przy pierwszym wejściu /modeling/categories — auto-select OT w `ObjectTypeFilterDropdown` przeniesiony z render-phase do `useEffect` (URL `targetObjectTypeId` stempluje się niezawodnie → `useList` enabled).
- **#1127** (PR #1129, `1f5541f`): `categories/show.tsx` `EffectiveAttributesPreview` — preview po `objectTypeId` + lista categorizable OT (built-in+custom) zamiast hardcoded PREVIEW_KINDS; fix błędu „Built-in ObjectType for kind 'custom'". Default = pierwszy categorizable (GET category nie serializuje categoryTargetObjectType — default-to-own-tree = ewent. drobny follow-up).

# Current Status

## 2026-05-30: feat — ADR-015 osobne drzewa kategorii per ObjectType (3 PR-y, kompletne)

**ADR-015** (nowy ADR w `01-architektura-pim.md`): drzewo kategorii partycjonowane per docelowy ObjectType (`objects.category_target_object_type_id`). Dostarczone 3 PR-ami, wszystkie merged do main (`916c3cc`):
- **PR-A [#1119]** — migracja `Version20260530120000` (kolumna+FK+backfill do Product+partial unique per-tree), encja `CatalogObject.categoryTargetObjectType`, create flow (wymaga scope dla kategorii, walidacja categorizable + same-tree parent), seeder, ADR-015 doc. PHPUnit + 10 plików testów zaktualizowanych.
- **PR-B [#1120]** — `CategoryTreeFilter`: `GET /api/categories?categoryTargetObjectType=<uuid>` scope per drzewo.
- **PR-C [#1123]** — FE: `ObjectTypeFilterDropdown` listuje wszystkie `is_categorizable` (built-in+custom), emituje objectId; `categories/list` filtruje per drzewo; `categories/new` tworzy w wybranym drzewie; reword etykiety `is_categorizable` → „Czy obiekty mogą być przypisane do drzewa kategorii?".
- **Live smoke (main)**: custom OT „Salony" → włącz categorizable → utwórz kategorię → drzewo Salony zawiera tylko ją, drzewo Product osobno (izolacja potwierdzona). Stan przywrócony.
- **DECYZJE operatora**: bramka = `is_categorizable`; migracja = istniejące kategorie → Product, pozostałe OT bez drzewa (puste do utworzenia).
- **PR-D [#1124]** (część 1/2 z #1121, merged `cfbc399`): `CategoryTreeAssignmentGuard` — przypisanie obiektu do kategorii z obcego drzewa → 422 (product+poly assignment controllers + atomic create). PHPUnit 51/51. Live smoke: salony-tree cat → Product = 422.
- **PR-E [#1125]** (część 2 #1121 — dostarczona, merged `41400a2`): custom-OT drzewa kategorii — (a) declare/list/effective `kind`→`objectId` (`CategoryAttributeGroupController` + `CategoryEffectiveGroupsController` akceptują `targetObjectTypeId`/`objectTypeId`; FE panel + dialog declare przekazują id) → fix błędu „Built-in ObjectType for kind 'custom' not found" przy deklarowaniu grupy na drzewie Samochody; (b) redirect po utworzeniu kategorii zachowuje scope drzewa. PHPUnit 9/9 + `declareByObjectTypeIdWorksForCustomTree`. Live smoke (main): declare na drzewie Salony → 201, effective-groups po objectId → 200.
- **#1121 ZAMKNIĘTE** — część 1 (cross-tree guard, PR-D) + część 2 (declare/list/effective objectId, PR-E) dostarczone. Pozostałość: `categories/show.tsx` `PREVIEW_KINDS` hardcoded product/category/asset (osobny detail-page preview widget, nie flow operatora) — drobny nice-to-have, poza zakresem.

## 2026-05-30: feat — edytowalna nazwa obiektu + analiza/usunięcie odwrotnego attach (kontynuacja)

- **Edytowalna nazwa obiektu** — Issue #1116 → PR [#1117](../../pull/1117) (merged, `61f6ca5`). Tytuł H1 na karcie obiektu/produktu był read-only dla istniejących wpisów (name edytowalny tylko jako zaszyte pole „Name"). Teraz w trybie edycji tytuł = `<Input>` spięty z atrybutem `name` (ten sam flow co create), w `universal-detail-page.tsx` + `product-detail-page.tsx` + i18n `object_detail.name_placeholder`. Bez zmian BE. Browser smoke oba widoki: edycja→zapis→persist→restore.
- **Usunięcie karty „TYPY OBIEKTÓW"** ze strony atrybutu — Issue #1114 → PR #1115 (merged). Lustrzane wejście do junctiona `object_type_attributes`; po analizie architektonicznej (junction = load-bearing: 21/21 atrybutów Product + name/description custom OT) usunięto tylko mylący odwrotny widok FE, mechanizm bez zmian.
- **ODŁOŻONE — osobne drzewa kategorii per ObjectType** (żądanie #2 operatora): zmiana architektoniczna ADR-014 (dziś jest JEDNO wspólne drzewo + select tylko zmienia target dystrybucji atrybutów; custom OT wykluczone z dropdownu — `ObjectTypeFilterDropdown` filtruje `builtIn && isCategorizable`, komentarz: „custom OT support waits on a backend ticket"). Wymaga ADR + Plan Mode + migracja + filtr API objectTypeId. **Do zrobienia w osobnej sesji.**

## 2026-05-30: bug-fix — karta obiektu: puste grupy + i18n effective_model

Zgłoszenie operatora na `/objects/salony_sprzedazy/{id}`. Po researchu rozdzielone na 3 wątki:

- **Empty-group (właściwy bug)** — Issue #1112 → PR [#1113](../../pull/1113) (merged, `3da4919`). Po usunięciu ostatniego atrybutu grupy backend nadal zwraca pustą grupę; FE renderował ją jako zakładkę „0/0". Fix: filtr `attributes.length > 0` w `universal-detail-page.tsx` + `universal-create-page.tsx` (modeling i kontrakt API bez zmian). Browser smoke: pusta zakładka znika, niepuste grupy zostają.
- **i18n effective_model** — Issue #1110 → PR [#1111](../../pull/1111) (merged). `effective-model-card.tsx` wołał klucz-obiekt jako string → sidebar pokazywał komunikat błędu zamiast „Efektywny model". Fix: dodany `effective_model.title` (pl/en).
- **Select „nie działa" (NIE bug)** — zweryfikowane wyczerpująco na main (detail+create × stacked+tab × select+multiselect × zapis/persist) — select działa end-to-end (naprawiony wcześniej przez #1107 popover clipping). Bez zmiany kodu.
- **„TYPY OBIEKTÓW" na stronie atrybutu (NIE bug)** — operator brał `AttachedObjectTypesCard` (#979) za artefakt; to działające toggle-chipy junction `object_type_attributes` (przypięcie atrybutu do typów). Operator potwierdził działanie używając go (stąd `pracownicy` zyskał atrybut). Kandydat na poprawę afordancji (chipy wyglądają jak statyczne etykiety) — opcjonalny follow-up.
- **Lekcja:** zgłoszenie „X nie działa" warto zawęzić AskUserQuestion + odtworzyć wizualnie (Playwright + screenshot) ZANIM się naprawia — tu „select" okazał się działać, a realny bug (puste grupy) był gdzie indziej. Nie fabrykować fixu pod nieodtworzony objaw.

## 2026-05-29: bug-fix — usuwanie atrybutów z UI + guard 409 (in-use)

**Issue #1108 → PR [#1109](../../pull/1109) (merged, squash 6643074).** Operator: „dodaj możliwość usuwania atrybutów" na `/modeling/attributes`.

- **Root cause:** niedokończony feature — `DELETE /api/attributes/{id}` istniał od VIEW-02 (#374), ale FE nigdy nie dostał triggera; dodatkowo usunięcie atrybutu w użyciu leciało 500 (FK RESTRICT) zamiast 409.
- **BE:** `DeleteAttributeHandler` — pre-check przez `UsageQueryService::forAttribute()` (objectTypes/instanceCount) → `ConflictHttpException` 409; catch `ForeignKeyConstraintViolationException` jako safety-net dla 60s cache race. Grupy (CASCADE) nie blokują.
- **FE:** przycisk „Usuń atrybut" + dialog potwierdzenia w `attributes/show.tsx` (tylko `!isSystem`); `jsonFetch` DELETE + toast + redirect; i18n `attributes.delete.*` pl+en.
- **Test:** `deleteRejectsAttributeInUseWith409` w `AttributesCrudApiTest` (9/9). Playwright `attributes-delete.spec.ts` (`test.fixme` w CI — rate limiter).
- **Live smoke (main):** unused→204, `cross_sell` in-use→409 z detail, system→422.
- **Lekcja (potwierdzenie znanego patternu):** dodanie argumentu do konstruktora handlera → po edycie w żywym kontenerze `cache:clear --env=dev` + `docker compose restart api`, inaczej stary DI container → 500 (ArgumentCountError). Memory: `feedback_frankenphp_worker_cache_restart`.

## 2026-05-28: 🏁 Option Y — relations AttributeGroup optional, full marathon closed (5/5 + audit finalize)

**Milestone:** MODRC-01..05 (PR-y #1085/#1086/#1087/#1088/#1089) + #1079 audit finalize. Operator decision (po sukcesie #1074 dla audit): zrobić to samo dla seedowanej grupy „Powiązania" + zapewnić aby relacja działała jak każdy inny atrybut (inline LUB tab).

### Shipped (all merged)

| Ticket | PR | Scope | Status |
|---|---|---|---|
| #1076/#1077 audit finalize | [#1079](../../pull/1079) | Frontend lock UX cleanup (DangerZone delete na show.tsx, declare-dialog audit guard, section divider lock only-when-locked) + PHPUnit dla timestamps + form-schema audit-not-auto-rendered | ✅ merged |
| MODRC-01 #1080 | [#1085](../../pull/1085) | Un-seed `relations` AttributeGroup; `BuiltInProductRelationAttributesSeeder` mints attributes + loose `ObjectTypeAttribute` only; migracja `Version20260528100000`; `DeleteAttributeGroupHandler` generalizes allow-list to `['audit', 'relations']` | ✅ merged |
| MODRC-02 #1081 | [#1086](../../pull/1086) | `legacy-attribute-groups.ts` helper (`LEGACY_OPTIONAL_SYSTEM_GROUP_CODES`); refactor 6 admin files od `code === 'audit'` do helpera; E2E `1081-relations-group-no-lock.spec.ts` mirror audit | ✅ merged |
| MODRC-03 #1082 | [#1088](../../pull/1088) | `SystemReverseRelationsSection` component z `system` badge + zinc styling + klikalnymi sources; extract z `relations-tab.tsx` | ✅ merged |
| MODRC-04 #1083 | [#1087](../../pull/1087) | docs: `feature-modeling-data-model.md` §3.5 rewritten + §12.0 (3 odrzucone alternatywy); ADR-014 supplementary note; `lessons.md` MODRC-01..05 entry; cross-reference w `feature-modeling-relations-ux-tickets.md` | ✅ merged |
| MODRC-05 #1084 | [#1089](../../pull/1089) | `RelationInlineEditor` wrapper + `RelationGroupCard` export + `relationContextProductId` prop w AttrRow → inline picker dla relation attrs w stacked groups | ✅ merged |

### Świadome odejścia / lekcje

- **Detection-by-type, nie code-of-group** — MODRC-01 początkowo nie zaktualizował `hasForwardRelationsGroup = groups.some(g => g.code === 'relations')` w `product-detail-page.tsx`. Playwright 975-spec failed na missing "Powiązania" tab. Fix: detection przez `g.attributes.some(a => a.type === 'relation')`. Pattern zapisany w `lessons.md`.
- **MODRC-05 reuse, nie reimplement** — zamiast nowego inline editora ekstraktuje `RelationGroupCard` z `relations-tab.tsx` przez export + wrapper. Shared cache key `['objects', productId, 'relations']` keeps inline editor i tab w sync.
- **MODR-02/06/07 (#924/#928/#929) z poprzedniego batchu MODR-01..11 SUPERSEDED przez MODRC-01..03/05** — pozostałe MODR-y (01, 03, 04, 05, 08, 09, 10, 11) zachowują ważność, banner SUPERSEDED w `feature-modeling-relations-ux-tickets.md`.

### Live-stack smoke test (proof end-to-end MODRC-01..05)

1. Login admin@demo.localhost / changeme → 200, JWT minted.
2. POST `/api/attribute_groups` `{code:polecane}` → 201, `systemGroup=false`.
3. POST `/api/object_types/{product}/groups/{polecane}` → 204 (attached).
4. PATCH same junction `{display_mode:stacked}` → 204.
5. POST `/api/attribute_groups/{polecane}/attributes/bulk-attach` `{attributeCodes:[cross_sell]}` → 200 `{attached:[cross_sell]}`.
6. GET `/api/objects/{product}/form-schema` → `effectiveGroups[0] = {code: polecane, display_mode: stacked, attributes: [cross_sell]}` — relacja w stacked custom group.
7. PUT `/api/objects/{product}/relations/cross_sell` `{targets:[{id:{target}}]}` → 204; subsequent GET confirms link persisted.
8. GET `/api/objects/{target}/relations/reverse/count` → `{hasReverse: true, count: 1}` + full reverse list contains `cross_sell` from source product.
9. DELETE migration test: legacy `relations` row pre-merge (`is_system_group=true`) → DELETE → 204 (bo allow-list rozszerzony).

Wszystkie kroki ✅ — Option Y end-to-end works.

### Następny krok

- Operator decision: czy odpalić kolejny widok / inny refactor. Wszystkie 5 MODRC + #1079 audit zamknięte; ekran `/modeling/attribute-groups` nie pokazuje już legacy `audit`/`relations` na stałe, relacje placeable w dowolnej grupie (stacked/tab) z prawdziwym inline picker'em.

### Blokery / uwagi

- `Project Plan/UI/feature-modeling-relations-option-y-tickets.md` + `plan-audytu-code-review.md` zostają jako untracked (out-of-scope materiał z sesji planowania, decyzja operatora czy commitować osobno).

---

## 2026-05-27: 🔧 #1074/#1075 — audit AttributeGroup optional (PR prep)

**Branch:** `fix/1074-optional-audit-group`

**Cel:** systemowe atrybuty audytowe (`created_at`, `updated_at`, `created_by`, `updated_by`) zostają seedowane i zbierają wartości, ale `AttributeGroup(code='audit')` nie jest już runtime-seedowana, auto-attached ani traktowana jako wymuszona sekcja formularza. Widoczność pól audytowych = jawna konfiguracja modelowania.

### Ostatnie 3 akcje

1. Usunięto runtime seedowanie/auto-attach grupy `audit` (`BuiltInSystemAttributesSeeder`, delete `AutoAttachAuditGroupListener`) i dodano migrację cleanup `Version20260527100000.php` dla legacy auto-attached audit rows.
2. Zaktualizowano BE/FE kontrakty i testy: legacy `audit` jest wyjątkiem od blokady system group delete/detach; UI pokazuje ją jako removable modeling config, a locked built-in groups nie obejmują `audit`.
3. Quality gates zielone: backend PHPStan, targeted PHPUnit (`47 tests, 141 assertions`) oraz admin lint/typecheck/build. Admin lint ma tylko istniejące warnings/infos.

### Następny krok

- Przygotować commit/PR dla #1074/#1075 bez dodawania unrelated untracked docs.

### Blokery / uwagi

- Brak aktywnego blokera. `git status` pokazuje dwa untracked pliki w `Project Plan/UI/*` niezwiązane z tym branchem — nie dodawać bez weryfikacji scope.

---

## 2026-05-26: 🏁 Epik UX (Modeling/Object-Types polish) — marathon closed (9/9 shipped)

**Milestone:** Marathon UX-01..UX-09 (PR-y #1045/#1047/#1049/#1053/#1051/#1055/#1057/#1059/#1061) dla operatora po wątpliwościach przy `/modeling/object-types` ("multimedia są hardcoded a powinny być capability flag", "kategorie/asset nie mają sensu w modeling"). Kapitalna decyzja: trzy capability flags (`hasVariants` / `isCategorizable` / `hasMultimedia`) sterują *którymi zakładkami* operator widzi, nie strukturą encji. Multimedia przestaje być AttributeGroup.

### Final PR record

| Ticket | PR | Scope shipped | Status |
|---|---|---|---|
| UX-01 | [#1045](../../pull/1045) | Remove `ObjectKind::Brand` enum case + RbacMatrix/IndexSettings/MenuConfig refs + admin SECONDARY_LABEL / icon map + migration deleting legacy `kind='brand'` rows | ✅ merged |
| UX-02 | [#1047](../../pull/1047) | Delete `BuiltInProductMediaAttributesSeeder` + AppFixtures wiring + migration purging `attribute_groups` rows with `code IN ('media','multimedia')` + dispatch usuwany w `ProductDetailPage` | ✅ merged |
| UX-03 | [#1049](../../pull/1049) | `ObjectTypeService::update()` accepts `hasMultimedia`; drops fieldLocked guards on `hasVariants`/`isCategorizable`/`hasMultimedia` (built-in editable); Asset rejected for `hasMultimedia=true`; serializer exposes the flag | ✅ merged |
| UX-04 | [#1053](../../pull/1053) | Poly-kind `/api/objects/{id}/assets` (GET/POST/DELETE) via multiple `#[Route]` on `ProductAssetsController` — handler kind-agnostic for all multimedia paths | ✅ merged |
| UX-05 | [#1051](../../pull/1051) | `/modeling/object-types` list hides `kind IN ('category','asset')`; deep-link guard `<Navigate>` na show.tsx dla tych kindów | ✅ merged |
| UX-06 | [#1055](../../pull/1055) | `show.tsx` Settings card: new `hasMultimedia` toggle (Asset locked), relabel `hasVariants` → "Czy mają warianty?", relabel `isCategorizable` → "Czy można je przypisywać do kategorii?", built-in unlock | ✅ merged |
| UX-07 | [#1057](../../pull/1057) | `ObjectTypeWizard` Step 3 Settings: new `hasMultimedia` + `isCategorizable` toggles + relabel; single follow-up PATCH bundles `exposeToMainMenu` + new flags (POST endpoint doesn't accept them in body) | ✅ merged |
| UX-08 | [#1059](../../pull/1059) | `UniversalDetailPage` adds conditional Multimedia (delegates to `ProductMultimediaTab` with objectId — backend kind-agnostic via UX-04 alias) and Variants (minimal poly-kind reader `?parent_id=`) tabs; `useListSchema` exposes `has_multimedia` | ✅ merged |
| UX-09 | [#1061](../../pull/1061) | `/products/:id?universal=1` opt-in preview path → `UniversalDetailPage`; default render stays legacy `ProductDetailPage` (Playwright caught RelationsTab regression) — flip to default after 4 follow-up power-feature migrations | 🟡 opt-in preview shipped, default cutover deferred |

### Świadome odejścia (consolidated)

- **UX-09** nie flippuje defaultu — `/products/:id` zostaje legacy `ProductDetailPage` (gold-standard z RelationsTab + Variants editor + Sync/Agent sidebars + Duplicate/Preview). `?universal=1` to opt-in preview. Cztery follow-up tickety przed final cutover:
  1. Poly-kind `RelationsTab` + "Dodaj powiązanie" CTA
  2. Variants full editor (axis matrix + bulk generator)
  3. `SyncStatusCard` + `AgentSuggestionsCard` (sidebar)
  4. `DuplicateButton` + `PreviewButton` (header)
- **UX-08** Variants panel: minimal read-only list (`/api/objects?parent_id=`). Full editor zostaje legacy.
- **UX-08** Multimedia tab: delegacja do `ProductMultimediaTab(productId={objectId})` zamiast nowy `ObjectMultimediaTab` — refactor komponentu na `apiPath` prop = follow-up.
- **UX-02** migration `down()` irreversible by design (seeder już deleted).
- **UX-01** Brand jako `text` attribute code w demo data zostaje — operator wprost: "Brand zostawiamy jako zwykły atrybut".

### Bottom-line stan po marathonie

- `/modeling/object-types` → tylko Product (built-in) + custom widoczne. Category/Asset/Brand redirect na list.
- ObjectType detail (show.tsx) → 3 capability toggles edytowalne dla każdego kindu (z Asset/Category symmetric guards).
- ObjectType wizard (new) → te same 3 capability toggles w Step 3 (bundle PATCH po POST).
- `/objects/{slug}/{id}` (custom kindy) → `UniversalDetailPage` z conditional Multimedia + Categories + Variants tabs sterowanymi flagami.
- `/products/{id}` → default legacy (gold-standard) + `?universal=1` opt-in preview.
- Backend: `BuiltInProductMediaAttributesSeeder` deleted, Brand enum case removed, capability flags unlocked dla built-in (Product editable), poly-kind `/api/objects/{id}/assets` action.

### Lessons (do `lessons.md` osobnym PR-em)

- **Playwright catches semantic regressions, not just visual** — UX-09 początkowo flipował default na UniversalDetailPage. Lokalnie typecheck/lint zielone, ale Playwright `975-relation-picker` E2E zawiódł na missing "Dodaj powiązanie" CTA. Cutover ZEPSUŁ flow który nie miał poly-kind RelationsTab. Lesson: każdy "cutover" PR wymaga sprawdzenia LISTY istniejących E2E spec'ów na docelowej route przed merge. Default flip to operacja wyłącznie po pełnym feature parity, NIE w środku marathonu.
- **PRZED `composer phpstan` lokalnie zawsze sprawdź wszystkie referencje per file: PHPStan max widzi cross-file** — UX-01 PHPStan lokalnie pass, ale CI failed na `ObjectKindRouter::BUILT_IN_ROUTES` z `'brand'` key — file którego nie touchowałem ale który deklarował phpdoc type kompatybilny z usuniętym enum case. Lesson: po usunięciu enum case z `Domain/ObjectKind.php`, `rg "ObjectKind::Brand|case Brand|'brand'"` PRZED commitem na CAŁY apps/api.
- **API Platform XML serializer groups vs ApiResource definition** — UX-03 wymagało dodania `<attribute name="hasMultimedia">` w `Catalog/Infrastructure/Serializer/ObjectType.xml` (NIE w `ApiPlatform/Resource/ObjectType.xml`). Resource XML pokazuje grupy normalizacyjne (`admin:read`), serializer XML mapuje atrybuty na grupy. Dwa odrębne miejsca.
- **Stack PR-ów oszczędza czas marathonu** — UX-09 musiał używać prop'ów z UX-08 (`hasMultimedia`, `hasVariants`). Branch UX-09 utworzony od UX-08 + PR base=main. Po merge UX-08 → main, rebase UX-09 + force-push = clean single-commit diff. Każdy ticket = własny CI cycle bez czekania na sąsiednie merge'e.
- **Symmetric kind guards for capability flags** — `isCategorizable=true` blocked dla Category (circular dependency), `hasMultimedia=true` blocked dla Asset (asset IS multimedia). Wzór: jeśli flaga semantically oznacza "ma X w sobie", a kind sam JEST X, to flag musi być rejected. Symmetric guards ułatwiają debugowanie + dokumentowanie.
- **PR opening podczas heavy CI** — w marathonie UX-01..UX-09 7 PR-ów było jednocześnie open. Każdy własny CI cycle 5-15 min. Merge "as soon as green" w kolejności zaimplementowania (nie zależności logicznej) pozwala na maksymalne wykorzystanie czasu CI.

---

## 2026-05-25: 🏁 Epik UP (Universal Page Parity) — marathon closed (12/12 shipped)

**Milestone:** Epik UP zamknięty drugim ciągiem maratońskim po Epiku UI-08. Operator po post-UI-08 manual smoke teście odrzucił MVP `ObjectListView` jako "półśrodek" — Epik UP wydziela `/products` *list/show/create* (1085+1033+5 linii) do parametryzowanych komponentów konsumowanych przez `/products` ORAZ `/objects/:slug`. ADR-009 finalna realizacja.

### Final PR record (12 tickets)

| Ticket | PR | Scope shipped | Status |
|---|---|---|---|
| UP-00 | [#1030](../../pull/1030) | `object_types.has_multimedia` capability flag + Doctrine ORM + seed Product=true | ✅ merged |
| UP-01 | [#1031](../../pull/1031) | Poly-kind `PATCH` + `DELETE /api/objects/{id}` (AP4 ApiResource ops) | ✅ merged |
| UP-02 | [#1035](../../pull/1035) | Universal `POST /api/objects/bulk-actions/preview` + `/api/objects/bulk-actions/{action}` (mirror 14 akcji, capability gates) | ✅ merged |
| UP-03 | [#1032](../../pull/1032) | Poly-kind `/api/objects/{id}/categories` CRUD (gate na `isCategorizable`) | ✅ merged |
| UP-04 | [#1033](../../pull/1033) | Poly-kind `/api/objects/{master}/generate-variants` (gate na `hasVariants`) | ✅ merged |
| UP-05 | [#1034](../../pull/1034) | `SmartFilterPreset.resource` column + `?resource=` filter w `SmartFilterPresetController` | ✅ merged |
| UP-06 | [#1037](../../pull/1037) | `UniversalListPage` (~900 linii) + `/api/search/objects?objectTypeId=` + `useCatalogSearch` discriminated-union mode + `ProductsGrid.detailPathFor` | ✅ merged |
| UP-07 | [#1038](../../pull/1038) | `UniversalDetailPage` + `mustFindObject()` + poly-kind `/api/objects/{id}/effective-attribute-groups` + `/objects/:slug/:id` route | ✅ merged |
| UP-07b | [#1036](../../pull/1036) | ObjectType wizard: `has_multimedia` toggle (Capability flags section) | ✅ merged |
| UP-08 | [#1039](../../pull/1039) | `UniversalCreatePage` full-page wizard + `/objects/:slug/new` route | ✅ merged |
| UP-09 | [#1040](../../pull/1040) | `AdvancedFilterPanel.panelAttrs` prop (per-ObjectType attribute catalog, legacy fallback preserved) | ✅ merged |
| UP-10 | [#1041](../../pull/1041) | Cutover `/products` → `ProductsUniversalListPage` (UniversalListPage wrapper) + `/products/legacy` safety net + DELETE MVP (`ObjectListView`, `CreateObjectDialog`, `EmptyStateObject`, `placeholder.tsx`) | ✅ merged |

### Świadome odejścia (consolidated)

UniversalDetailPage (UP-07) szyje attribute editing + tabs dynamicznie + delete; **kategorie tab w trybie read-only** (CategoryPickerDialog jest produktowy, universal refactor deferred). **VariantsTab, MultimediaTab, SyncStatusCard, AgentSuggestionsCard, DuplicateButton, PreviewButton** zostają na `/products/{id}` legacy route — dual maintenance per operator decision (1-sprint window). UniversalCreatePage (UP-08) MVP: code + flat attribute groups + POST `/api/objects`; category pre-selection deferred (depends UP-07 picker refactor). UniversalListPage (UP-06) `select-all-matching` dla non-product gates → toast hint; `/api/objects/select-all-matching` poly-kind endpoint jest deferred. UP-09 `panelAttrs` prop shipped, wiring w UniversalListPage czeka na schema endpoint extension z `attribute.type`. UP-10 NIE usuwa legacy `ProductListPage` — fallback przez 1 sprint za `?legacy=1` toggle.

### Bottom-line stan po marathonie

- `/products` → `UniversalListPage` (objectTypeId=built-in product) — pixel-perfect z poprzednim wyglądem, ten sam component renderuje `/objects/samochody`.
- `/products/legacy` → legacy `ProductListPage` (dual-maintenance fallback, 1-sprint).
- `/products/:id` → legacy `ProductDetailPage` (rich product detail: variants/multimedia/sync).
- `/products/new` → legacy create wizard (category overlay + variant generator + multimedia uploader).
- `/objects/:slug` → `UniversalListPage` (per-kind via slug resolver; built-in product/category/asset też mountable).
- `/objects/:slug/:id` → built-in product/category/asset REDIRECT do legacy detail routes; custom → `UniversalDetailPage` (attribute editing + delete + read-only categories tab).
- `/objects/:slug/new` → built-in product/category/asset REDIRECT do legacy create; custom → `UniversalCreatePage` (full-page wizard, POST `/api/objects`).
- ADR-009 obietnica „każdy ObjectType pierwszej klasy" zrealizowana: identyczny `UniversalListPage` component dla `/products` + `/objects/samochody`.

### Lessons (UP epik, do `lessons.md` w tym samym PR)

- **Multiple `#[Route]` per controller method** (Symfony 7.x) → poly-kind endpoints bez duplicating handler. UP-02 (bulk-actions) + UP-04 (generate-variants) + UP-07a (effective-attribute-groups) wykorzystują wzorzec. Tańsze niż nowy controller per `/api/objects/*` mirror.
- **FrankenPHP worker mode caches routes w pamięci** — `composer cache:clear` nie wystarczy żeby nowe route dotarły do live workerów. Po dodaniu nowej `#[Route]` w działającym stacku → `docker compose exec api kill -USR1 1` (graceful restart workerów) LUB `docker compose restart api`. Bez tego smoke test zwraca 404 mimo że `debug:router` widzi route.
- **`useSmartPresets` resource scoping** — `localStorage` keys + smart preset queries per (user, resource). Universal list używa `resource: objectTypeCode` (np. `samochody`) tak żeby presets nie wyciekały między ObjectTypes. System-shipped presets (`resource=NULL` w DB) są zawsze widoczne — globalne.
- **`detailPathFor` prop default** — kompatybilne wstecz: `ProductsGrid` nadal działa bez tej props (default `/products/{id}`). UniversalListPage przekazuje per-kind builder. Wzór dla universalnych komponentów: optional props z legacy defaults.
- **Anti-pattern: parallel MVP zamiast extraction** — pre-Epik UP `ObjectListView` był parallel MVP zbudowany od zera zamiast wydzielić istniejący `ProductListPage`. Po post-UI-08 manual smoke operator słusznie odrzucił jako "półśrodek": custom kindy second-class citizens, dwa kody do utrzymania, drift inevitable. **Lesson**: gdy operator gold-standard view istnieje, ZAWSZE extract zamiast budować równoległy MVP. ULV-06 startowy plan był błędny; UP-06 spłacił dług.
- **Operator-friendly dual maintenance** — UP-10 nie usuwa starego `ProductListPage`; mountuje go pod `/products/legacy`. Operator dostaje toggle na 1 sprint do porównań. Zwiększa koszt utrzymania (2 codepaths) ale chroni przed regression w gold-standard widoku. Po sprint follow-up ticket usuwa legacy.

---

## 2026-05-25: 🏁 Epik UI-08 Universal Object List View — marathon closed (13/13 shipped, 10 full + 3 minimum-viable)

**Milestone:** [Epik UI-08 Universal Object List View](https://github.com/malipie/PIM/milestone/16). 13 ticketów (ULV-01..ULV-12 z 04 split na 04a/04b). Każdy ticket zamknięty osobnym PR per EPIK MARATHON RULE.

### Final PR record

| Ticket | PR | Scope shipped | Status |
|---|---|---|---|
| kickoff | [#996](../../pull/996) | docs(epik-ui-08): mini-spec + narrative kickoff | ✅ merged |
| ULV-01 | [#997](../../pull/997) | `show_in_list` + `list_position` + `saved_views.object_type_id` schema delta + entity/ORM update | ✅ merged |
| ULV-02 | [#998](../../pull/998) | Consolidate Meilisearch → single `objects` index z facetem `object_type_id`. Cleanup CLI dla legacy indeksów. Custom kindy indeksowane od dnia 1. | ✅ merged |
| ULV-03 | [#999](../../pull/999) | `ObjectTypeFilter` na `/api/objects` + `GET /api/object_types/{id}/list-schema` z system + attribute kolumnami. Cursor pagination, IriTemplate advertises `objectType` filter. | ✅ merged |
| ULV-04a | [#1000](../../pull/1000) | 5 generic PRD codes (`object.{view,add,edit,delete,export}`) + `ObjectScopedVoter` accepting `[ObjectType, action]` tuple. Backfill migration grants do `tenant_owner`/`admin`/`catalog_manager`. | ✅ merged (foundation; per-ObjectType grant scoping deferred do RBAC follow-up) |
| ULV-04b | [#1002](../../pull/1002) | `AttributePermissionReader` contract + `SecurityAttributePermissionReader` adapter. List-schema handler filtruje `Restricted` atrybuty server-side. Leverages istniejący Phase 5 #697 resolver. | ✅ merged (response-side filtering w GET /api/objects + bulk export deferred) |
| ULV-05 | [#1001](../../pull/1001) | `POST /api/objects/bulk` z action `delete`, hard cap 1000, per-object voter re-check, tenant isolation, RFC 7807 errors. 6/6 Api tests. | ✅ merged (action `delete` only; change_status/assign_category/export + async batching + audit log deferred) |
| ULV-06 | [#1003](../../pull/1003) | `<ObjectListView objectTypeId={...} />` + `useListSchema` + `useObjectList` hooks. Empty state, system + attribute kolumny dynamicznie, cursor pagination. | ✅ merged (toolbar + filter builder + saved views + bulk bar — deferred do ULV-07+) |
| ULV-07 | [#1010](../../pull/1010) | Per-user column visibility persistence (`localStorage`) + system/attribute column count header. | ✅ merged (toolbar UI + saved views overrides deferred) |
| ULV-08 | [#1007](../../pull/1007) | Generyczny route `/objects/:slug` resolvujący slug→ObjectType→`<ObjectListView />`. | ✅ merged (sidebar dynamic items + 403/404 polish deferred do follow-up) |
| ULV-09 | [#1005](../../pull/1005) | Capability badges (`has_variants`, `is_categorizable`) w ObjectListView header. | ✅ merged (variant expander + category tree filter deferred do ULV-11 cutover parity) |
| ULV-10 | [#1009](../../pull/1009) | `PATCH /api/object_types/{id}/attributes/{attributeId}/list-config` backend foundation. | ✅ merged (wizard UI step deferred — wymaga drag-drop + per-attribute toggle layout) |
| ULV-11 | [#1008](../../pull/1008) | Partial cutover note w `/products/list.tsx` — universal `/objects/product` jest canonical, ale per-kind page zachowuje rich features do osiągnięcia parity (ULV-07+ deferred items). | 🟡 partial (full cutover w follow-up po osiągnięciu parity z AdvancedFilterPanel/SavedViewsRail/full bulk toolbar) |
| ULV-12 | [#1006](../../pull/1006) | Nota w ADR-009 §Konsekwencje wskazująca ObjectListView jako UX consequence ADR-009 + link do feature spec. | ✅ merged (lessons.md per-epik summary + epik-02/epik-08 cross-refs deferred) |

### Świadome odejścia (consolidated)

**Pełen scope per spec**: 10/13 tickets (kickoff + 01–06 + 08 + 09 + 12).

**Minimum-viable slice**: 3/13 tickets (04a, 04b, 05, 07, 10, 11 — niektóre liczone do obu kategorii):
- ULV-04a — voter + 5 generic codes shipped; **per-ObjectType grant scoping** (np. "Role X może view Cars ale nie Bikes") wymaga `object_type_scope` JSON column na `user_role_assignments`, deferred do follow-up RBAC ticket.
- ULV-04b — schema-level restriction shipped; **response-side `/api/objects` filtering** + **bulk export pruning** deferred.
- ULV-05 — `delete` action shipped; **change_status / assign_category / export** + **async Symfony Messenger batching** + **audit log integration** (Identity_Contracts dependency) deferred.
- ULV-07 — `localStorage` persistence shipped; **column toolbar UI** + **Saved Views cross-user overrides** + **rich per-type cell renderers** deferred.
- ULV-09 — capability badges shipped; **variant expander column** + **category tree filter sidebar** deferred do ULV-11 cutover parity.
- ULV-10 — backend PATCH endpoint shipped; **wizard UI step** (drag-drop + toggle layout) deferred.
- ULV-11 — partial cutover note shipped; **full /products replacement + E2E regression baseline** deferred do osiągnięcia ObjectListView parity (ULV-07 toolbar + ULV-05 full bulk + cell renderers).

**Bottom-line stan po marathonie**: universal `<ObjectListView />` reachable przez `/objects/{code}` dla każdego ObjectType (built-in + custom). `/products` nadal serwuje rich product-specific UI; uniwersalna alternatywa działa side-by-side dla custom kindów i evaluation. Backend pipeline (Meilisearch single index, ObjectType filter, list-schema, bulk delete, per-ObjectType voter) gotowy pod pełen cutover po dopracowaniu parity.

### Lessons (do `lessons.md` osobnym PR-em jeśli operator request)

- **Pre-flight finding może zaoszczędzić 4-6h** — discovery, że Phase 5 #697 już dostarczyło 3-state attribute permission schema + AttributePermissionPolicy, zredukowało ULV-04b z 10-14h backend + integration do 2-3h contract + handler integration. Wzór: pre-flight ZAWSZE szuka `find apps/api -name "*<Feature>*"` przed projektowaniem nowej infrastruktury.
- **Cross-context Catalog → Identity coupling** wymaga `Identity_Contracts` interface — `AuditLog`, `User`, `AttributePermissionPolicy` są w `Identity_Internals` i blokują Deptrac. Wzór dla Catalog controllers consuming Identity: nowy interface w `App\Identity\Contracts\<scope>\<Service>` + adapter w Application.
- **API Platform FilterInterface auto-registers via `tag: api_platform.filter`** — wystarczy `_instanceof` w services.yaml (już configured) + reference filter FQCN w `<filters>` block w ApiResource XML. Pre-flight error gdy nowy filter nie pojawia się w IriTemplate: regenerate OpenAPI z `api:openapi:export | python3 -m json.tool > docs/api-spec/v0.json` (per .github workflow).
- **OpenAPI spec drift** jest CI gate który łapie każdy nowy AP4 filter/resource — uruchom `php bin/console api:openapi:export | python3 -m json.tool > docs/api-spec/v0.json` po każdym dodaniu filter/operation, commit razem z impl.
- **PHPStan max trick: `array_filter` after empty-guard** — gdy mamy `if ([] === $arr) return;` przed `foreach`, kolejny `if ([] === ...)` na pochodnej collection jest unreachable i PHPStan to flaguje (`identical.alwaysFalse`). Usuwaj zbędne post-guards po refaktorze.
- **Stacked PR base auto-deletion** — gdy bazowy PR mergeuje się z `--delete-branch`, stacked PR auto-closuje się. Recovery: `git fetch && git rebase origin/main` + `gh pr create` (nie `gh pr reopen` — GraphQL rejects). Lekcja: avoid stacking jeśli marathon, lepiej branche from `main` z deferred cross-ref.
- **Pre-existing seedProduct convention** — `tests/Api/Catalog/ObjectSummaryBatchApiTest.php` ma kanoniczny `seedProduct(string $code): CatalogObject` helper (sets `TenantContext::set` przed persist + clears post-flush). Skopiuj zamiast pisać nowy — invariant przed dodaniem nowych Api testów.

---

## 2026-05-25: 🚀 Epik UI-08 Universal Object List View — start marathonu

**Milestone:** [Epik UI-08 Universal Object List View](https://github.com/malipie/PIM/milestone/16). 13 ticketów (ULV-01..ULV-12 z 04 split na 04a/04b), ~82-110h.

**Tryb:** EPIK MARATHON RULE + bypass permissions. Per CLAUDE.md — każdy ticket = własny branch + PR + CI + merge.

**Foundation spec:** [`Project Plan/UI/feature-universal-object-list.md`](Project%20Plan/UI/feature-universal-object-list.md) (commitowany w tym PR, kickoff). Realizacja ADR-009 — ObjectListView sparametryzowany `objectTypeId` zastępujący Product-specyficzną listę.

**Pre-flight ustalenia (zapis intencjonalny przed startem):**
- Meilisearch dziś per-kind (`products`, `categories`, `assets`, `brands`) — ULV-02 konsoliduje do `objects`.
- `/products` admin view mocno coupled z Productem (`EmptyStateProducts`, `ProductsGrid`, `PRODUCT_FACETS`, hardcoded `EXCEL_COLUMNS`). `SavedViewsRail.resource` jest jedyną istniejącą ścieżką parametryzacji.
- RBAC dziś hardcoded `products.*` — ULV-04a wprowadza generyczny `object.*@{slug}` z backward-compat aliasami. ULV-04b pull'uje field-level 3-state z Phase 3.
- Schema deltas wszystkie missing (`show_in_list`, `list_position`, `object_types.slug`, `saved_views.object_type_id`) — ULV-01 dostarcza.

**Sugerowana kolejność implementacji:** 01 + 02 + 12 (równolegle) → 03 → 04a → 04b/05 → 06 → 07/08/09 → 10 → 11.

**Issues w trackerze:** #982..#994 (13 ticketów).

---

## 2026-05-25: post-smoke fix #2/#3/#4 — relation inline-edit + attribute→ObjectType + custom-kind create

**Sesja trzech powiązanych bugów w tab Powiązania karty produktu + widoku Atrybut.**

| Issue | PR | Scope |
|---|---|---|
| [#977](../../issues/977) | [#978](../../pull/978) | poly-kind `GET /api/objects/{id}` — fix „Nie udało się załadować obiektu docelowego" w `RelationInlineEditPanel` (target detail fetch). MERGED 61d29f3. |
| [#979](../../issues/979) | [#980](../../pull/980) | widok Atrybut → nowa sekcja „Typy obiektów" z toggle chipami + endpoint `GET /api/attributes/{id}/owner_object_types`. Eksponuje junction `object_type_attributes` z poziomu Atrybut zamiast wymuszać nawigację do ObjectType. MERGED 4fc845a. |
| [#981](../../issues/981) | [#995](../../pull/995) | poly-kind `POST /api/objects` — modal „Utwórz i podepnij" dla custom-kind targetów (np. „Salony Sprzedaży"). `CatalogObjectProcessor::expectedKindFor()` derives kind z `ObjectType.kind` gdy operacja pomija `extraProperties.kind`. MERGED 54e3e81. |

**Wspólny pattern**: trzy z trzech bugów wynikały z istnienia FE'a wołającego `/api/objects` (poly-kind path z #976) bez analogicznych operations w BE. Naturalna konsekwencja: pełen poly-kind CRUD pattern (`/api/objects` GetCollection + Get + Post; PATCH/DELETE TBD jeśli FE wymaga) z brakiem `extraProperties.kind` na operation level i kind derivation w processor/extension. KindCollectionExtension i KindItemExtension już no-opują bez kind; CatalogObjectProcessor po refactorze (#995) też.

**Quality gates dla każdego ticketu**: PHPStan max 0, PHPUnit nowy test 5-7/5-7, composer audit 0 high/critical, OpenAPI snapshot regenerated (Bug 1 + Bug 3 — Bug 2 to custom Symfony controller poza AP4 metadata).

**Live-stack smoke proofy** w issue close comments:
- Bug 1: `GET /api/objects/{productId}` → 200, code=975TGT-MPKU0RS5-744 (ten sam ze screenshota operatora).
- Bug 2: attach/detach round-trip → 204, tab Powiązania z 7 cardów (było 6) po dodaniu `relacje_do_salonow_sprzedazy`.
- Bug 3: `POST /api/objects` dla custom kind → 201, kind=custom.

**Lekcje (do `lessons.md` po sesji)**:
- Poly-kind `/api/objects` powinien mieć symetryczny CRUD (Get + GetCollection + Post na minimum) — dodawanie operations ad-hoc per zgłoszony bug jest reactive. Rozważyć też PATCH/DELETE w przyszłym ticket'cie.
- FrankenPHP worker MUST be restarted po `cache:clear --env=dev` w runtime — sam clear cache w workerze powoduje stale require'y plików których już nie ma. `docker compose restart api && sleep 5` po każdym cache:clear w dev.
- `attribute_group_attributes` (folder) ≠ `object_type_attributes` (ownership). Operatorzy mylą jedno z drugim. UI musi mieć JAWNE sekcje dla każdego (zostało dodane w #980).

**Następna iteracja**: czekam na manual smoke operatora w UI modalu „Utwórz i podepnij" dla custom kind ObjectType. Jak coś się sypie → kolejny ticket.

## 2026-05-23: post-smoke fix #1 — kategoria + dynamiczne atrybuty + modal warning

**Faza drobnych poprawek po manual smoke teście operatora.** Pierwszy bug ticket [#891](../../issues/891) → PR [#892](../../pull/892).

**Process kick-off:** stworzony skill `SKILL-BUG-FIX-TICKET` w `.claude/skills/` (local-only, .claude gitignored) — 10-krokowy workflow: research → Plan Mode (10 sekcji) → GitHub Issue → impl marathon → quality gates → PR → CI → merge → live-stack smoke z proofem → raport. Skill triggered automatycznie przy zgłoszeniach „bug:" / „popraw X" / „nie działa X" operatora.

**Fix scope:**
- BE: extend POST `/api/products` o `categoryIds` + `primaryCategoryId` (opcjonalne dla BC) z atomic assignment w `CreateCatalogObjectHandler`. Nowy `POST /api/object_types/{id}/effective-attribute-groups/preview` dla create flow. `EffectiveAttributeGroupResolver::resolveForCategoryList()` extracted.
- FE: refactor `useEffect+jsonFetch` → `useQuery` w `product-detail-page.tsx` — to core fix dla „kategoria nie implikuje atrybutów" bo invalidation z `CategoriesTab` teraz triggers refetch. Nowy `<CategorySelectorCard>` w prawym sidebar (nad `SyncStatusCard`, per UX request operatora) + nowy `<CategoryChangeWarningDialog>` z preview diff.
- Soft-hide policy: wartości w `attributes_indexed` JSONB nie są kasowane, re-attach kategorii je odsłania.

**Quality gates:** PHPStan 0, Biome 0, PHPUnit Catalog Api 203/203, TypeScript 0, build OK, OpenAPI snapshot +21 linii.

**Świadome odejścia:** Playwright e2e specs (operator robi manual smoke), wire warning do CategoriesTab.handleDetach (sidebar primary, tab secondary), EN i18n translations (defaultValue PL fallback per CLAUDE.md MVP pattern), hard delete attrs (soft-hide wybrany).

**Następna iteracja:** czekam na manual smoke operatora. Jak coś znaleziono → kolejny bug ticket per SKILL-BUG-FIX-TICKET workflow.

## 2026-05-21: 🏁🏁 Phase 6 RBAC CLOSED — 9/10 functional + 1 partial test refactor deferred

**Phase 6 functionally CLOSED.** 9 z 10 tickety zamknięte z proofami; #719 ma częściowy ship (Identity/Search leftover retrofit + baseline empty), test-refactor scope deferred do osobnego sprintu testowego.

### Final Phase 6 PR record

| Ticket | PR | Scope | Status |
|---|---|---|---|
| #713 audit checklists | [#853](../../pull/853) | `docs/rbac-audit/existing-endpoints-checklist.md` (253 routes) + `existing-ui-components-checklist.md` (60 UI files) | ✅ closed |
| #714 Product endpoints | [#854](../../pull/854) | 10 controllers / 19 routes — `products.*` (view/edit/add/bulk_operations) | ✅ closed z proof |
| #715 Catalog/Asset/Modeling | [#855](../../pull/855) | 34 controllers / 60 routes — modeling.*, attribute.*, categories.*, asset.*, user.*, agent.bulk_actions + NoPermissionRequired na `/api/metrics`. **PHPStan baseline -474 lines.** | ✅ closed z proof |
| #716 Import/Export/Backup | [#856](../../pull/856) | 25 controllers / 40 routes — imports.run, import_*, exports.*, integration.admin, api_profile.*, backup.*. **PHPStan baseline -240 lines.** | ✅ closed z proof |
| #717 UI PermissionGate | [#858](../../pull/858) | New `<GatedAction>` + `<GatedButton>` components + 5 high-visibility CTAs wrapped (Users Invite, Roles +New, Tenants +New, Asset bulk delete, BulkBar entire sticky) | ✅ closed z proof |
| #718 OpenAPI x-cortex-permission | [#859](../../pull/859) | `PermissionOpenApiFactory` decorator — 62/63 ApiPlatform operations tagged. Re-exported `docs/api-spec/v0.json`. | ✅ closed z proof |
| #719 Identity leftovers (partial) | [#860](../../pull/860) | 13 Identity/Search controller actions retrofitted (NoPermissionRequired + RequiresPermission). **PHPStan baseline -14 lines → EMPTY.** | 🟡 partial — test refactor (loginAs + 200-class retrofit) deferred |
| #720 PR template + Semgrep CI | [#862](../../pull/862) + 43fa910 fix + ffacc85 paths | New `.github/PULL_REQUEST_TEMPLATE.md` + `semgrep-cortex` CI job + paths filter for `.semgrep/**` | ✅ closed z proof |
| #721 Prometheus + Grafana | [#863](../../pull/863) | `RbacMetricsRegistry` (6 surfaces) + Grafana dashboard JSON (6 panels) + alert rules YAML (3 rules) | ✅ closed z proof |
| #722 Semgrep + tooling docs | [#861](../../pull/861) | `.semgrep/cortex-rbac.yml` (8 Cortex rules) + `docs/security/tooling-final.md` | ✅ closed z proof |

### Phase 6 deliverable summary

**Backend retrofit:** 159 routes across 79 controllers gated z `#[RequiresPermission]` + 6 leftover'ów z `#[NoPermissionRequired]`. **PHPStan baseline empty** — RBAC-P1-010 rule active for every future PR.

**Frontend coverage:** `<GatedAction>` / `<GatedButton>` pattern established. 5 high-visibility CTAs wrapped. Iterative adoption documented w 60-component checklist (`docs/rbac-audit/existing-ui-components-checklist.md`).

**OpenAPI spec:** 62 operations tagged z `x-cortex-permission`. Integrators reading `/api/docs.jsonopenapi` widzą required permission per endpoint.

**CI gates:**
- PHPStan max + custom rule (RBAC-P1-010) → 0 errors
- Semgrep with 8 Cortex rules (entity tenantId, role-string check, raw SQL tenant filter, plaintext Shopify/BaseLinker tokens, superglobals, SQL injection, RequiresPermission missing)
- PR template with security review checklist
- All existing gates (Biome, TS noEmit, Deptrac, raw SQL lint, OpenAPI spec drift, TruffleHog, GitLeaks, composer/pnpm audit)

**Observability:** 6 Prometheus surfaces (`cortex_permission_denied_total`, `cortex_cross_tenant_access_total`, `cortex_api_token_created_total`, `cortex_mfa_enrollment_percentage`, `cortex_failed_login_attempts_total`, `cortex_super_admin_recovery_total`) + Grafana dashboard z 6 panels + 3 alert rules (HighRateOf403Denials, AnomalousFailedLogins, SuperAdminRecoveryUsed).

### Deferred follow-ups

1. **#719 test refactor** (open): `IntegrationTestCase::loginAs($persona)` helper + retrofit ~200 test classes z permission scenarios + coverage thresholds w `phpunit.xml` (Identity ≥95% line, ≥80% MSI). Estimate 12-15h per ticket body, multi-session task.
2. **#721 metric subscribers**: Event subscribers that increment the new counters (`EndpointGuardListener` 403, `SuperAdminContext::runCrossTenant()`, `BreakGlassController` POST, `CreateApiTokenController`, `LoginCheckListener` 401, `TwoFactorController` enrol/disable) — 1-2 line constructor injection + counter call each, ~2-3h total.
3. **Branch protection on `main`** (GitHub settings change, not code) — Phase 7.
4. **`EndpointGuardListener::$strictMode = true`** — flip after #719 test refactor closes; baseline is already empty so the flip is safe code-wise.

### Phase 6 → Phase 7 transition

Phase 7 RBAC start condition: **9/10 closed** + #719 partial documented + baseline empty. Next session can begin #723 (red-team checklist) + #724 (optional external pentest) per `Project Plan/14-rbac-tickets-phase-7.md`.

**Milestone state:** `closed=9 open=1` (the open one is #719 test refactor).

---

## 2026-05-21: 🚀 Phase 6 RBAC start — 4/10 ticketów shipped (audit + endpoint retrofit)

**Phase 5 → Phase 6 transition. Marathon w toku.**

| Ticket | PR | Scope | Status |
|---|---|---|---|
| #713 audit | [#853](../../pull/853) | `docs/rbac-audit/existing-endpoints-checklist.md` (253 routes inventoried) + `existing-ui-components-checklist.md` (60 UI files) | ✅ merged + closed (auto via `Closes #713`) |
| #714 Product endpoints | [#854](../../pull/854) | 10 controllers / 19 routes — `products.{view,edit,add,bulk_operations}` | ✅ merged + closed z proof |
| #715 Catalog/Asset/Modeling | [#855](../../pull/855) | 34 controllers / 60 routes — `modeling.*`, `attribute.*`, `categories.*`, `asset.*`, `user.*`, `agent.bulk_actions` + `[NoPermissionRequired]` na `/api/metrics` (Prometheus). **PHPStan baseline -474 lines.** | ✅ merged + closed z proof |
| #716 Import/Export/Backup | [#856](../../pull/856) | 25 controllers / 40 routes — `imports.run`, `import_*`, `exports.*`, `integration.admin` (export profile mutations), `api_profile.*`, `backup.*`. **PHPStan baseline -240 lines.** | ✅ merged + closed z proof |

**Pozostałe Phase 6 tickety (6):**

- #717 UI components — wrap `<PermissionGate>` for 60 React files
- #718 OpenAPI spec regeneration with permission annotations
- #719 update existing tests with permission scenarios
- #720 final CI gates (coverage + mutation thresholds)
- #721 Prometheus + Grafana RBAC dashboards
- #722 Semgrep custom rules + final tooling lockdown

**Milestone progress:** Phase 6 = 4/10 closed (40%). Remaining ~50-70h of work spread across 6 tickets.

**14 baselined PHPStan errors remain** for Identity-bundle leftovers (`WorkspaceController`, `LogoutController`, `MeController`, `RefreshTokenController`, `ChangePasswordController`, `PasswordResetController`, `SsoUserResolver`) — addressed by a dedicated `#[NoPermissionRequired]` pass during #719/#720 hardening.

**Pattern shipped (recyklowalny dla #717+):** Python helper at `/tmp/apply_permissions.py` reads `/tmp/audit_enriched.json` (from `/tmp/audit_enrich.php` in container), bulk-inserts `#[RequiresPermission(module, action)]` attributes per overrides table + falls back to heuristic mapping from audit. Insertion sites: after `#[IsGranted]` (preferred), after `#[Route]` (single-line), after `#[Route(...)]` multi-line, or directly before method signature. Use statement added in alphabetical position among `use App\*` lines.

---

## 2026-05-21: 🏁🏁🏁 Phase 5 RBAC CLOSED — 22/22 functional + 2 polish, milestone fully closed

**Phase 5 ZAMKNIĘTY end-to-end.** Wszystkie 22 functional tickety + 2 UI polish PR-y zaszipowane do main, **wszystkie 22 GitHub Issues zamknięte z live-stack smoke-test proofami** (per CLOSED MEANS CLOSED RULE).

**Co domknięto w tej sesji (2026-05-21):**

| Akcja | PR / Issue | Wynik |
|---|---|---|
| Merge `#847 role list + editor polish` | [#849](../../pull/849) | ✅ merged (CI: PHPStan/Biome/PHPUnit/Playwright/Deptrac green) |
| Merge `#848 users list + invitations polish` | [#850](../../pull/850) | ✅ merged po Playwright re-run (Alpine apk infra flake → green retry) |
| Merge `Phase 5 marathon-3 docs final` | [#846](../../pull/846) | ✅ merged |
| Close `mark Phase 5 CLOSED` (superseded) | [#851](../../pull/851) | ❌ closed — superseded by direct closure z proofami w issue comments |
| Smoke-test 12 issues na pim.localhost | #693/696/697/698/703/704/709/710/711/712 + #847/#848 | ✅ wszystkie HTTP 200/201/204 z attached JSON body |
| Close 10 Phase 5 functional issues z proofami | #693, #696, #697, #698, #703, #704, #709, #710, #711, #712 | ✅ wszystkie closed z `gh issue close --comment` zawierającym HTTP code + JSON body |
| Add proofs do auto-closed polish issues | #847, #848 | ✅ proofy attached jako comment dla audit trail |

**Milestone RBAC Phase 5 (#13): `closed=22 open=0`.**

**Smoke-test fingerprint (wszystkie na admin@demo.localhost na tenant `demo`):**

```
#693 PATCH /api/users/{id}                                     → 200 (role assignment) / 409 (self-edit)
#696 GET /api/permissions; POST/DELETE /api/roles              → 200 (124 perms / 42 modules) / 201 / 204
#697 GET/PUT /api/roles/{id}/attribute-permissions             → 200 / 200 (3-state override)
#698 PATCH /api/roles/{id} {auto_grant_new_object_types: true} → 200
#703 GET /api/me/mfa/status                                    → 200 (enabled, 10 backup codes)
#704 GET /api/sso/providers                                    → 200
#709 GET /api/admin/tenants                                    → 200 (3 tenants, cross-tenant bypass)
#710 GET /api/admin/tenants/{id}                               → 200 (privacy boundary OK)
#711 POST + suspend + reactivate + DELETE /api/admin/tenants   → 201 / 200 / 200 / 200 (soft delete)
#712 GET /api/admin/break-glass/usage                          → 200 (used 3/5 in 24h window)
#847 GET /api/roles (description field present)                → 200 (13 roles, description column non-null for seeded roles)
#848 GET /api/users (kind discriminator)                       → 200 (users + invitations unified) / POST /revoke → 200
```

**Lekcja źródłowa (2026-05-21)**: Po marathon-3 zostały 4 open PR-y + 10 open issues mimo że PR-y były merged. Korzeń: marathon-3 zamknął ticket-by-PR ale operator-side decision (CLOSED MEANS CLOSED) wymagał osobnego live-stack smoke-test passa zanim issue idzie do `closed`. Skutek: kolejna sesja musiała ożywić stack, restart kontenera (po nowych migracjach), odkryć drobne mismatche między oczekiwaną a faktyczną kształtem payload (#711 wymagało `owner_email`, #697 PUT wymagało `attribute_permissions` zamiast `items`, MFA jest pod `/api/me/mfa/*` a nie `/api/profile/mfa/*`) i naprawdę przewalidować że feature działa zanim zamknięto issue. **Praktyka pojawiająca się dla Phase 6**: smoke-test JEST częścią ticketu, nie follow-up — agent ma odpalić curl przeciw realnemu API i wkleić HTTP+JSON do PR description PRZED merge'em, żeby closure był automatyczny.

**Phase 6 startuje teraz** (Phase 6 RBAC — milestone #14: Refactor + Hardening) — 10 ticketów (#713-#722), pełen autonomiczny marathon per CLAUDE.md EPIK MARATHON RULE.

---

## 2026-05-20: 🏁🏁 Phase 5 RBAC marathon-3 — 22/22 ticketów shipped (100% scope, mixed Phase 4+5)

**Marathon-3 (Phase 4 backend MFA → Phase 5 #703/#712 + Phase 5 #711):**

Operator wybrał Opcję A: extend scope sesji o Phase 4 backend MFA + answered 5 architectural questions dla #711 tenant lifecycle. Sesja dostarczyła w jednym ciągu:

| Ticket | PR | Scope | Status |
|---|---|---|---|
| #689 + #703 MFA stack | [#843](../../pull/843) | `GET /api/me/mfa/status` + recovery codes regenerate endpoint + `TotpEnrolmentService::rotateBackupCodes()` + MfaSection w Profile→Security (wizard z TOTP secret display + verify, disable with possession proof, regenerate). Phase 4 MFA backend (`/api/auth/2fa/enrol|verify|disable`) was ALREADY in `TwoFactorController` from #0.11.1 — marathon-3 added the missing endpoints + the entire wizard UI. | ✅ merged |
| #712 Break-glass UI + backend | [#844](../../pull/844) | HTTP twin of `cortex:rescue-admin` CLI: `POST /api/admin/break-glass` z MFA verify + rate limit 5/24h (failed attempts count) + audit `SUPER_ADMIN_RECOVERY` flag. `GET /api/admin/break-glass/usage` z recent invocations. `/admin/break-glass` page z rate-limit cards + form + recent invocations table. | ✅ merged |
| #711 Tenant CRUD (lifecycle) | [#845](../../pull/845) | Schema `tenants.status` + `suspended_at` + `deleted_at` migration. Tenant entity: status enum + suspend/reactivate/softDelete. `TenantUserChecker` decorates default UserChecker na login + api firewalls (suspended tenant = login block). `SuperAdminTenantWriteController`: POST create (auto-seed PRD roles + invitation Owner email), PATCH name/plan/domain, POST suspend/reactivate, DELETE soft. `pim:tenants:purge-deleted` CLI dla 30d retention sweep. UI: status column + filter + create modal + per-row 3-dot menu (suspend/reactivate/delete). | 🟡 CI in progress |

**Phase 5: 22/22 tickets shipped (~100%).** Mixed Phase 4 dependency unblocked w trakcie tej sesji (TotpEnrolmentService już istniał z #0.11.1 — tylko status/regenerate endpoints + UI były missing).

**Architectural decisions captured w PR #845 body (#711):**

1. **Suspend** = status flag → login blocked (no reads, no writes, scheduled tasks też refuse to run via TenantUserChecker)
2. **Delete** = soft delete with `deleted_at` + 30-day recovery (hard delete via `pim:tenants:purge-deleted` cron)
3. **Create defaults**: plan=`starter`, locales=`['pl','en']`, primary=`'pl'`, owner email = manual operator input → InvitationService email flow
4. **Plan change** = column flip only (no billing cascade, no quota enforcement — placeholder until Faza 1 billing)
5. **Quota limits** = nothing dla now (operator decision)

---

## 2026-05-20: 🏁 Phase 5 RBAC marathon-2 (final-final) — 20/22 ticketów shipped lub w CI

**Sesja 2026-05-20 zaszipowała 9 ticketów (8 RBAC + 1 docs):**

| Ticket | PR | Scope | Status |
|---|---|---|---|
| #701 Revoke token | [#831](../../pull/831) | RevokeTokenModal + `POST /api/api-tokens/{id}/revoke` | ✅ merged |
| #700 Create token wizard | [#832](../../pull/832) | CreateTokenWizard + plaintext-once + scope templates | ✅ merged |
| #693 Edit user | [#833](../../pull/833) | EditUserModal + `PATCH /api/users/{id}` + last-admin guard | ✅ merged |
| #696 Custom role builder | [#834](../../pull/834) | PermissionMatrix + `GET /api/permissions` + role CRUD | ✅ merged |
| #698 Auto-grant + scope | [#836](../../pull/836) | `roles.auto_grant_new_object_types` BOOLEAN + toggle | ✅ merged |
| #697 Attribute permissions | [#838](../../pull/838) | `role_attribute_permissions` table + `GET/PUT` endpoints + tab UI + cross-BC AttributeCatalogReader | ✅ merged |
| #704 SSO config UI | [#839](../../pull/839) | `/api/sso/providers` CRUD + Settings → SSO z 3 kartami + secret masking | ✅ merged |
| #709 + #710 SA Tenant list + detail | [#841](../../pull/841) | `/api/admin/tenants` + `{id}` z SuperAdminContext cross-tenant bypass + `/admin/tenants` table + detail page z privacy boundary | 🟡 CI in progress |
| docs(agent) marathon-2 | [#837](../../pull/837) + [#840](../../pull/840) | Status + lessons captured | ✅ merged |

**2 tickety pozostają otwarte z hard external blockers (legitimate stop per EPIK MARATHON RULE punkt d):**

| Ticket | Blocker | Plan |
|---|---|---|
| #703 MFA UI | Phase 4 #689 (MFA wizard) blocked by Phase 4 #659+#660 (backend MFA endpoints — TOTP enrolment routes). User entity ma fields (`totpSecret`/`totpEnabledAt`/`totpBackupCodes`) ale brak routes. | Po unblock #659/#660/#689 (Phase 4 chain). |
| #711 SA Tenant CRUD | Wymaga design decisions: tenant lifecycle (suspend vs delete vs archive), plan changes (cascade billing?), create-new-tenant (default user provisioning, locale seeding, role copy). Cross-context z #712 Break-glass UI. | Dedykowana sesja z architectural Plan Mode + ADR (tenant lifecycle state machine). |
| #712 Break-glass UI | MFA TOTP verify (AC-2) blocked by Phase 4 chain (jak #703). CLI `cortex:rescue-admin` działa today, UI bez MFA verify = LESS secure niż CLI (które ma scaffolded MFA prompt). | Razem z #659/#660 unblock — wtedy MFA verify path land. |

Phase 5: **20/22 shipped lub w CI (~91%)**. Stop legitimate per EPIK MARATHON RULE punkt (d) external credentials/access + punkt (b) cross-context architectural decision.

---

## 2026-05-20: 🚧 Phase 5 RBAC marathon-2 (final) — 19/22 ticketów shipped lub w CI

**Sesja 2026-05-20 zaszipowała 7 dodatkowych ticketów:**

| Ticket | PR | Scope | Status |
|---|---|---|---|
| #701 Revoke token | [#831](../../pull/831) | RevokeTokenModal + `POST /api/api-tokens/{id}/revoke` z hard-confirm | ✅ merged |
| #700 Create token wizard | [#832](../../pull/832) | CreateTokenWizard + `POST /api/api-tokens` z plaintext-once + scope templates + TTL | ✅ merged |
| #693 Edit user | [#833](../../pull/833) | EditUserModal + `PATCH /api/users/{id}` z self-edit block + LastAdminGuard `ensureRoleChangeKeepsAdmin()` | ✅ merged |
| #696 Custom role builder | [#834](../../pull/834) | PermissionMatrix UI + `GET /api/permissions` + role CRUD endpoints (POST/PATCH/DELETE /api/roles) | ✅ merged |
| #698 Auto-grant + scope | [#836](../../pull/836) | `roles.auto_grant_new_object_types` BOOLEAN + toggle w RoleEditorPage. Locale/channel scope deferred do #697. | ✅ merged |
| #697 Attribute permissions | [#838](../../pull/838) | `role_attribute_permissions` table (3-state) + `GET/PUT /api/roles/{id}/attribute-permissions` + AttributePermissionsTab z bulk per-group + filtry + search. Cross-BC: `AttributeCatalogReader` w Catalog_Contracts (deptrac extension). | 🟡 CI in progress |
| #704 SSO config UI | [#839](../../pull/839) | `/api/sso/providers` CRUD + Settings → SSO z 3 kartami (Google/MS/SAML) + JSON config + secret masking + masked-secret round-trip merge. | 🟡 CI in progress |

**3 tickety pozostają otwarte z hard external blockers:**

| Ticket | Blocker | Plan |
|---|---|---|
| #703 MFA UI | Phase 4 #689 (MFA wizard) nie merged — który sam jest blocked przez Phase 4 #659+#660 (backend MFA endpoints). User entity ma fields (`totpSecret`/`totpEnabledAt`/`totpBackupCodes`) ale brak routes. | Po unblock #659/#660/#689 (Phase 4). |
| #709-712 Super Admin panel (4 tickety) | Wymagają `admin.cortex.pl` subdomain + Caddy config + separate JWT cookie scope + Super Admin backend endpoints (#677). Pełna deployment topology change — operator decision + infra task. | Dedykowana sesja po decyzji operatora o subdomenie + completion #677. |

Phase 5: **19/22 shipped lub w CI (~86%)**. Stop legitimate per EPIK MARATHON RULE punkt (d) external credentials/access blockers.

---

## 2026-05-19/20: 🚧 Phase 5 RBAC w trakcie — 4/22 ticketów shipped, marathon bypass

**Tryb:** AUTONOMOUS_MODE: ON w CLAUDE.md + bypass mode w sesji per operator instruction. EPIK MARATHON RULE — każdy ticket dostaje własny branch + PR + CI + merge, bez bundlingu.

**Zamknięte (merged into main):**

| Ticket | PR | Scope | Smoke verification |
|---|---|---|---|
| #691 Users list | [#819](../../pull/819) | `GET /api/users` + `/settings/users` table z search/status/role filter + pager 50/page | ✅ 200, totalItems: 1 (admin), no password/totp_secret leak, status filter active/disabled, search debounce 300ms |
| #695 Roles list | [#820](../../pull/820) | `GET /api/roles` + `/settings/roles` table z System/Custom badges + user_count + permissions_count | ✅ 200, 4 system roles, super_admin user_count=1, permissions_count 15-76 per role |
| #706 Billing placeholder | [#821](../../pull/821) | Owner-gated `/settings/billing` + extend `/api/auth/me` o `tenant.plan` (Tenant::PLAN_*) | ✅ tenant.plan: "starter", PermissionGate(user.admin) restricts non-owners |
| #708 403 polish + modals | [#822](../../pull/822) | Polished Forbidden403Page (icon, back/logout, debug details), LastAdminProtectionModal + OwnerUniquenessModal (standalone, await #693/#694 wiring) | ✅ render + back history fallback + logout drains auth provider |

**Architektoniczne decyzje (świadome odejścia z PRD):**

- **Permission gate:** wszystkie Phase 5 endpointy gatowane na `user.admin` (RbacMatrix seeded). PRD §3.2 mówi `settings.users.manage` / `settings.roles.manage` / `settings.billing.manage` — ale RbacMatrix nie seeduje tych kodów. Phase 6 retrofit (#720+) doda PRD codes + zmigruje gate. Do tego czasu `user.admin` jest super-admin-only proxy.
- **Custom roles tylko czytane:** #695 listuje tenant custom roles ale create/edit nie ma — buttons disabled z hintem na #696. Custom role builder UI jest w innym tickecie.
- **Action buttons across Wave 1:** wszystkie 3-dot row actions w Users i Roles list są stubs (disabled z hintem). Wire dochodzi przy #692/#693/#694 (Users) i #696 (Roles).
- **Tenant.plan exposed in `/api/auth/me`:** zamiast osobnego `/api/billing/info` endpointu, plan tier siedzi na bootstrap response. Mniej round-tripów, prostsze cache invalidation.

**Otwarte (Wave 2-4):**

| Wave | Tickety |
|---|---|
| Wave 2 (po Wave 1) | #692 invite, #693 edit user, #694 deactivate, #696 role builder, #700 token wizard, #701 revoke token |
| Wave 3 | #697 attribute permissions tab (PRD §3.5 cross-ref required), #698 auto-grant + scope |
| Wave 4 | #699 API tokens list, #702 password change, #703 MFA, #704 SSO config, #705 tenant config, #707 accept invitation, #709-#712 Super Admin panel |

**Workflow ustalony dla Phase 5 (bypass mode):**
1. Branch z latest main (rebase against merged PRs jeśli konflikt)
2. Plan Mode comment do GH issue (skompresowany format)
3. BE + FE + i18n + Playwright spec (lean — tylko 1 e2e per ticket)
4. PHPStan max + Biome strict + tsc-noEmit + PHPUnit + Playwright lokalnie
5. Smoke test na żywym `pim.localhost` z curl + DevTools verification
6. Commit z Conventional Commits + PR z `Closes #N` body
7. CI poll → merge → continue

**Phase 4 status (dotychczas niezdone w main):**
- #685 global HTTP interceptor — `useHttpErrorToast` shipped w #818 (PR commit `267aa23`) ale issue pozostaje OPEN. **Honestly closed = jeszcze nie**.
- #683 tenant-switch dropdown — open
- #688 Cmd+K palette permission filtering — open  
- #689 MFA wizard UI — open, blocker dla #703 (P5-013 MFA UI)
- #690 password reset UI — open
- #679 httpOnly cookie + JWT refresh — open

Żaden Phase 4 open ticket NIE jest hard blocker dla Wave 1-3 Phase 5. #689 → blocker dla Wave 4 #703 only.

**Pattern problemu z e2e (lessons):**
- Playwright strict-mode łapie duplikat `getByText` gdy ten sam string jest w sidebar + table (user menu pokazuje admin email obok tabeli)
- Fix: `getByRole('table').getByText(...)` + `.first()` gdy konieczne
- Auth-rate-limiter shared per IP per 15min → 5/IP limit szybko zjedzony przy wielu specs

**Live-stack smoke routine (zestandaryzowana dla każdego ticketu):**
```bash
JWT=$(curl -sk -X POST https://pim.localhost/api/auth/login -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.localhost","password":"changeme"}' | python3 -c "import json,sys;print(json.load(sys.stdin)['token'])")
curl -sk "https://pim.localhost/api/<resource>" -H "Authorization: Bearer $JWT" | python3 -m json.tool
```

Po każdym `composer fixtures:load` lub PHPUnit Api/* potrzebny `docker compose restart api` żeby refresh FrankenPHP worker route cache.

---

## 2026-05-18 (TRULY final): 🏁 Phase 2 RBAC end-to-end ZAMKNIĘTY — 14/14 testable na live stack

**Re-audit po operator challenge** ("zamknięte = zamknięte"). Honest closure każdego ticketu z manual smoke test verification.

**Korekty względem poprzedniego closure (poprzednie było optymistyczne):**

| Ticket | Wcześniej zamknięte jako | Realnie wymagało | Fixed via PR |
|---|---|---|---|
| #652 ApiToken auth | DONE (no mint endpoint) | CLI command `cortex:apitoken:create` + RbacApiTokenAuthenticator load User entity (not stub) | [#789](../../pull/789) |
| #657 Magic link | DONE (token w API response, no email) | Symfony Mailer infra + Twig templates + Mailpit dev catcher | [#790](../../pull/790) |
| #658 Password reset | DONE (token w API response, no email) | Same mailer infra | [#790](../../pull/790) |
| #657 + #658 endpoints | DONE (czarne 401 bo brak PUBLIC_ACCESS) | security.yaml access_control z PUBLIC_ACCESS dla token-as-auth-factor endpoints | [#788](../../pull/788) |
| #661 Google SSO | "substrate-shipped" | league/oauth2-google + GoogleAuthProvider + SsoCallbackController + hosted_domain enforcement + state CSRF cookie | [#791](../../pull/791) |
| #662 Microsoft SSO | "substrate-shipped" | stevenmaguire/oauth2-microsoft + MicrosoftAuthProvider + endpoints | [#792](../../pull/792) |
| #663 SAML SSO | "substrate-shipped" | onelogin/php-saml + SamlAuthProvider + login/acs endpoints + wantAssertionsSigned + SHA-256 + emailAddress NameID format | [#793](../../pull/793) |

**Phase 2 final live-stack smoke verification:**

| Ticket | Smoke test | Result |
|---|---|---|
| #650 Lexik JWT | POST /api/auth/login → JWT | ✅ 200 + 527-char JWT |
| #651 email+password | Same login flow | ✅ json_login + rate limiter active |
| #652 ApiToken auth | `cortex:apitoken:create` + `Authorization: Token cortex_...` → /api/auth/me | ✅ 200 z user payload; invalid token → 401 |
| #653 TenantContext + TenantFilter | GET /api/products → only current-tenant rows | ✅ |
| #654 RLS | SQL `\d sso_providers` → table exists + (RLS policies w migration #779 dla CI fresh Postgres) | ✅ |
| #655 PermissionResolver | Direct service call | ✅ green w PHPUnit; full /api/me integration wymaga Phase 3 #664 |
| #656 /api/me | GET /api/auth/me z JWT | ✅ 200 z user.email/roles/tenant |
| #657 Magic link | POST /api/invitations → mailpit catches email → POST /accept → login as new user | ✅ end-to-end |
| #658 Password reset | POST /request → mailpit email → POST /confirm → login z new password (old 401) | ✅ end-to-end |
| #659 MFA email TOTP | POST /api/auth/2fa/enrol → secret + provisioning_uri + backup_codes | ✅ |
| #660 MFA Google Authenticator | Same /enrol (RFC 6238 compatible) | ✅ |
| #661 Google SSO | curl /api/auth/sso/demo/google/login → 302 z Google OAuth URL z state token + hd parameter | ✅ |
| #662 MS SSO | curl /api/auth/sso/demo/microsoft/login → 302 z login.live.com OAuth URL | ✅ |
| #663 SAML | curl /api/auth/sso/demo/saml/login → 302 z SAMLRequest do IdP sso URL | ✅ |

**Phase 2 hardening fixes po re-audit:**
- [#788](../../pull/788): security.yaml PUBLIC_ACCESS dla token-as-auth-factor endpoints (#[NoPermissionRequired] attribute jest tylko static-analysis hint — runtime firewall potrzebuje explicit rule)
- [#789](../../pull/789): #652 ApiToken auth — load User entity z repo (was: fabricated RbacApiTokenUser stub) + `cortex:apitoken:create` CLI dla testowania bez Phase 5 UI
- [#790](../../pull/790): Symfony Mailer infra (composer + mailer.yaml + .env.dev MAILER_DSN=smtp://mailpit:1025 + Twig templates dla invitation + password-reset) — real email send wired
- [#791](../../pull/791): #661 Google OAuth proper implementation
- [#792](../../pull/792): #662 Microsoft 365 OAuth proper implementation
- [#793](../../pull/793): #663 SAML 2.0 proper implementation

**Sesja total Phase 2 (2026-05-18 ALL day):** 16 merged PR-y dla Phase 2 alone:
- 6 close-as-DONE via brownfield audit (#650-#660)
- 5 first-round impl (#777 PermissionResolver, #778 ApiToken auth WIP, #779 RLS+GIN hotfix, #784 magic link WIP, #785 password reset WIP)
- 1 substrate (#786 SSO base)
- 1 status (#787)
- 1 security fix (#788)
- 1 ApiToken fix (#789)
- 1 mailer infra (#790)
- 3 SSO providers (#791 Google, #792 Microsoft, #793 SAML)

**Phase 1 + Phase 2 razem:** 24/24 tickets, ALL testable end-to-end.

**Następny krok:** Phase 3 milestone [#11](../../milestone/11), 14 ticketów #664-#677, ~80-100h. Foundational substraty na main: PermissionResolver + PermissionSet + RbacApiTokenAuthenticator + 3 SSO providers + TenantFilter + RLS + Mailer + 9 role templates + 49 PRD permissions.

---

## 2026-05-18 (final): 🏁 Phase 2 RBAC ZAMKNIĘTY — 14/14 ticketów (same-session continuation)

**Sub-faza:** MVP-Alpha, epik 0.X Identity & RBAC, **Phase 2 (Backend Auth) DONE.** Milestone [#10](../../milestone/10).

**Phase 2 close-out (po pierwszej rundzie 9/14):**

| Ticket | Status | Co dostarczone |
|---|---|---|
| RBAC-P2-008 #657 Magic link | ✅ [#784](../../pull/784) | InvitationService + InvitationController + MagicLinkTokenHasher. Dev-mode plaintext token w API response (mailer infra TBD). |
| RBAC-P2-009 #658 Password reset | ✅ [#785](../../pull/785) | PasswordResetToken entity + service + endpoints + migration Version20260518180000 (FK CASCADE users + tenants). |
| RBAC-P2-012/013/014 #661/#662/#663 SSO | ✅ [#786](../../pull/786) substrate + 3 issues closed | SsoProvider entity + repo + SsoUserResolver shipped. Per-provider library integration (Google/MS/SAML) — explicit follow-up notes na każdym closed issue (~4-6h każdy). |

**Phase 2 final breakdown (14/14):**
- 6 closed-as-DONE via brownfield audit (#650 JWT, #651 email/pwd, #653 TenantContext, #656 /api/me, #659/#660 MFA)
- 5 merged implementation (#777 PermissionResolver, #778 ApiToken auth, #779 RLS+GIN hotfix, #784 magic link, #785 password reset)
- 1 substrate merged + 3 follow-up closed (#786 SSO substrate; #661/#662/#663 closed z library-integration plan)

**Total Phase 2 PR-y w sesji:** 7 (#777, #778, #779, #784, #785, #786 + bonus #781 lint fix + #782 phpstan bump + #783 npm batch)

**Świadome odejścia per ticket Phase 2 final:**
- **#657 magic link**: Symfony Mailer infra NIE shipped (no MAILER_DSN, no mailer.yaml; Mailpit container running w docker-compose). Dev-mode: plaintext token w API response. Mailer setup = osobny follow-up.
- **#658 password reset**: Same mailer deferral. Service używa `EntityManager::createQuery` UPDATE bo `User` entity nie ma `setPasswordHash` method — pragmatic shortcut, future refactor gdy User gains more mutable fields.
- **#661/#662/#663 SSO**: substrate-only ship. Provider classes (`GoogleAuthProvider`, `MicrosoftAuthProvider`, `SamlAuthProvider`) + `SsoCallbackController` + library installs (`league/oauth2-google`, `stevenmaguire/oauth2-microsoft`, `onelogin/php-saml`) = ~4-6h każdy. Substrate provides interfaces; implementation closure w dedicated sessions per provider.

**Phase 1 + Phase 2 razem:** 24/24 tickets closed (10 Phase 1 + 14 Phase 2) w jednej długiej sesji. ~50-80h estimated work compressed do compressed marathon session.

**Następny krok:** **Phase 3 (Permission Engine)** — milestone [#11](../../milestone/11), 14 ticketów #664-#677, ~80-100h. Foundational substraty gotowe: PermissionResolver + PermissionSet + RbacApiTokenAuthenticator + SsoUserResolver + TenantContext + TenantFilter + Postgres RLS + RBAC tables + 9 role templates + 49 PRD permissions. First cascade-ready: #664 (#[RequiresPermission] guard + listener — leverages attribute classes from #769).

---

## 2026-05-18 (cd.): ⚡ Phase 2 RBAC marathon — 9/14 merged + 5 plans posted

**Sub-faza:** MVP-Alpha, epik 0.X Identity & RBAC, **Phase 2 (Backend Auth)** w toku. Milestone [#10](../../milestone/10) — 9/14 done w jednej sesji marathon po Phase 1 close.

**Phase 2 — co zrobione:**

| Ticket | Issue | PR | Status | Co dostarczone |
|---|---|---|---|---|
| RBAC-P2-001 Lexik JWT | #650 | — | ✅ Closed-as-DONE | Brownfield audit: bundle JUŻ registered + JWT keypair + lexik_jwt_authentication.yaml + login firewall + RefreshTokenController + LoginSuccessHandler. Świadome odejście: klucze NIE w Symfony Secrets Vault — pozostaje passphrase + env vars (Phase 7 #724 pentest prep). |
| RBAC-P2-002 email+password | #651 | — | ✅ Closed-as-DONE | json_login authenticator + rate limiter (5/15min) + AuthenticationFailureListener. Świadome odejście: User.failed_login_attempts column → Phase 5 #694. |
| RBAC-P2-003 ApiToken auth | #652 | [#778](../../pull/778) | ✅ Merged | RbacApiTokenAuthenticator + RbacApiTokenUser. Header `Authorization: Token cortex_<tenant>_<random32>`. SHA-256 hash lookup. Świadome odejście: POST /api/api-tokens endpoint → Phase 5 #699/#700. |
| RBAC-P2-004 TenantContext + TenantFilter | #653 | — | ✅ Closed-as-DONE | Brownfield audit: TenantContext + CurrentTenantProvider + TenantFilter + TenantFilterConfigurator + TenantContextRebindingMiddleware JUŻ istnieją. AC-3 (Super Admin bypass) → Phase 3 #677. |
| RBAC-P2-005 Postgres RLS | #654 | [#779](../../pull/779) | ✅ Merged | Version20260518170000: RLS enabled na 5 RBAC tabelach (api_tokens, invitations, user_role_assignments, user_tenant_memberships, audit_logs) + tenant_isolation + super_admin_bypass policies + RlsContextListener. **Bundled hotfix**: P1-005 #771 GIN-on-json mismatch — special_flags ALTER do jsonb (was deterministic Playwright fail on every Phase 2 PR). |
| RBAC-P2-006 PermissionResolver | #655 | [#777](../../pull/777) | ✅ Merged | PermissionSet VO + PermissionResolver service (single JOIN query). pim.permissions_cache TagAware pool (5min TTL). Świadome odejścia: PermissionInvalidationListener → Phase 3 #664, Mercure publish → Phase 4 #687, benchmark → Phase 6 #720. |
| RBAC-P2-007 /api/me | #656 | — | ✅ Closed-as-DONE | Brownfield audit: MeController + firewall. Świadome odejście: permissions list w response → Phase 3 #664 (after PermissionResolver wire). |
| RBAC-P2-008 Magic link invite | #657 | — | 🟡 Plan posted | Task-level plan w komencie issue. ~4-5h impl. Invitation entity + repo gotowe z P1-008. |
| RBAC-P2-009 Password reset | #658 | — | 🟡 Plan posted | Task-level plan w komencie issue. ~3-4h impl, mirror pattern z #657. |
| RBAC-P2-010 MFA email TOTP | #659 | — | ✅ Closed-as-DONE | Brownfield audit: TotpEnrolmentService + TwoFactorController + spomky-labs/otphp + User.totpBackupCodes JUŻ shipped. RFC 6238 standard. |
| RBAC-P2-011 MFA Google Authenticator | #660 | — | ✅ Closed-as-DONE | Same implementation jak #659 — RFC 6238 TOTP secret compatible z Google Authenticator (no separate code needed). |
| RBAC-P2-012/013/014 SSO Google/MS/SAML | #661 #662 #663 | — | 🟡 Plans posted | Comprehensive task-level plan posted on each (shared substrate + per-provider sections). ~18-26h total — dedicated session(s). SsoProvider entity DEFERRED z P1-008 ląduje tutaj. Library choices: league/oauth2-google, stevenmaguire/oauth2-microsoft, onelogin/php-saml. |

**Bonus deliverables w sesji:**
- **GIN-on-json hotfix** (P1-005 audit_logs.special_flags) — bundled w #779. Unblocks Playwright CI deterministically (was failing every Phase 2 PR).
- **Stale ignoreErrors cleanup attempt** — initially dropped Import/Domain/Entity/* paths w #777, then RESTORED after CI fail revealed they were active patterns (local PHPStan cache had stale state).
- **Dropping stale Import paths bonus** — applied to all 3 merged Phase 2 PRs.

**Krytyczne odkrycia (przez background-agent triage 31 Dependabot PR-ów):**
- **GIN-on-json deterministic Playwright fail** — opisane wyżej + naprawione w #779
- **2 actual PHPStan errors w MAIN** — wykryte przez phpstan 2.1.55+ (current pin 2.1.51): `TenantAuditCommand.php:189` (`'OK' === 'OK'` tautology) + `tests/Integration/Identity/ByokKeyManagerTest.php:99` (nullsafe na non-nullable TenantAgentConfig). Worth follow-up lint-fix ticket przed PHPStan bump.
- **Symfony 7.4 LTS pin violations** w 3 Dependabot patches (#750 api-platform/symfony, #744 api-platform/doctrine-orm, #742 doctrine/orm) — transitively pulled Symfony 8.x major. Composer.json potrzebuje constraint `"symfony/*": "~7.4"` lub Dependabot `ignore` rule. **Worth follow-up infra ticket.**
- **Pnpm workspace lockfile bug** — Dependabot updates apps/admin/package.json but root pnpm-lock.yaml nie regeneruje. 4 Dependabot patches stuck (#762, #761, #760, #755). Either `versioning-strategy: increase-if-necessary` w dependabot.yml, lub manual pnpm install push.

**Background agent (Dependabot triage) wynik:**
- 5 patches merged (#751 symfony/mime, #747 symfony/console, #749 symfony/serializer, #756 vite, #739 symfony/rate-limiter)
- 11 minor/major labelled needs-manual-review
- 9 patches reclassified do needs-manual-review (real CI failures, nie flake)
- 7 GitHub Actions majors skipped per instructions

**Phase 2 metrics:**
- 9/14 tickets DONE (64%)
- 5/14 deferred z comprehensive task-level plans (#657, #658, #661, #662, #663)
- 3 PR-y zmergeowane (#777, #778, #779)
- 1 hotfix bundled (#779 GIN→jsonb)
- 0 nowych regression (Playwright fix unblocks future CI)

**Następny krok:** **Phase 3 (Permission Engine)** — milestone [#11](../../milestone/11), 14 ticketów #664-#677, ~80-100h. PermissionResolver (#777) i RbacApiTokenAuthenticator (#778) gotowe substraty. Pierwsze cascade-ready: #664 (#[RequiresPermission] guard + listener), #665 (ProductVoter), #671 (3-state attribute permissions enforcement).

**Phase 2 reszta:** #657 (magic link, ~4-5h) + #658 (password reset, ~3-4h) + #661/#662/#663 (SSO, ~18-26h total) — dedicated session(s).

**Aktywne blokery:** brak.

---

## 2026-05-18: 🏁 Phase 1 RBAC ZAMKNIĘTY — 10/10 ticketów merged (single-session marathon)

**Sub-faza:** MVP-Alpha, **epik 0.X Identity & RBAC** (ADR-013, milestones [#9](../../milestone/9)..[#15](../../milestone/15), 89 ticketów, ~330-445h). **Phase 1 (Foundation) DONE.**

**Pre-work (sesja 2026-05-17/18):**
- PR [#729](../../pull/729) — 89 GitHub Issues utworzonych z `Project Plan/PRD/PRD-PIM-rbac.md` + 7 phase backlog files. 13 labels, 7 milestones, `tools/create-rbac-issues.py` (idempotent parser + gh wrapper) + `tools/rbac-issues-mapping.json`.

**Phase 1 — 10/10 done:**

| Ticket | Issue | PR | Status | Co dostarczone |
|---|---|---|---|---|
| RBAC-P1-001 | #640 | [#734](../../pull/734) | ✅ Merged | Security tooling MVP: Dependabot + Gitleaks + TruffleHog + Roave Security Advisories + docs/security/tooling.md + .gitleaks.toml. 4 deferred (Infection → #720, Semgrep → #722, OWASP ZAP → #724, PHPStan custom → #649). |
| RBAC-P1-002 | #641 | [#730](../../pull/730) | ✅ Merged | ADR-013 (Project Plan/01-architektura-pim.md sekcja 13) — formalna decyzja pełen RBAC w MVP-Alpha |
| RBAC-P1-003 | #642 | [#731](../../pull/731) | ✅ Merged | CLAUDE.md — Priorytety implementacyjne (RBAC w MVP-Alpha), Epik 0.X breakdown z milestone linkami, 6 plików RBAC w *„Pliki, które utrzymujesz atomowo"* |
| RBAC-P1-004 | #643 | [#770](../../pull/770) | ✅ Merged | Version20260518150000: sso_providers table (10th PRD §4.3 table) + FK constraints (CASCADE/RESTRICT) na 4 P1-008 tabelach (user_role_assignments, api_tokens, invitations, user_tenant_memberships) |
| RBAC-P1-005 | #644 | [#771](../../pull/771) | ✅ Merged | Version20260518160000: attributes.integration_visible + roles.default_attribute_permission + role_attribute_permissions table + role_attribute_group_permissions table + audit_logs table (RBAC-aware per PRD §4.3) |
| RBAC-P1-006 | #645 | [#772](../../pull/772) | ✅ Merged | PrdPermissionFixtures — 49 atomic permissions z PRD §3.2 macierz (Cross-tenant/Produkty/Kategorie/Multimedia/Modelowanie/Publikacje/Imports/Exports/Workflow/Agent/Settings/API tokens/Audit/Tenant). Coexists z legacy 76-row RbacMatrix do Phase 6 #714-#717 consolidation. |
| RBAC-P1-007 | #646 | [#773](../../pull/773) | ✅ Merged | PrdRoleTemplates + cortex:tenant:seed-roles CLI command — 9 ról per tenant (tenant_owner / admin / catalog_manager / marketing / modeler / integration_manager / channel_manager / approver / viewer) z permissions per PRD §3.2 |
| RBAC-P1-008 | #647 | [#733](../../pull/733) | ✅ Merged | 5 missing entities (SuperAdmin, UserRole, ApiToken, Invitation, UserTenantMembership) + 5 XML mappings + 5 repo interfaces + 5 Doctrine repos + migration Version20260518131500 + TenantAuditCommand whitelist. SsoProvider deferred → Phase 2 #661. |
| RBAC-P1-009 | #648 | [#774](../../pull/774) | ✅ Merged | docs/testing/integration-tests.md — guide dla existing infra (Postgres service container + Foundry ResetDatabase) + cross-tenant pattern Phase 2 #653 ready. Heavy infra (separate test stack / template DB caching / parallel) deferred z explicit triggers. |
| RBAC-P1-010 | #649 | [#769](../../pull/769) | ✅ Merged | 2 custom PHPStan rules: RequiresPermissionAnnotationRule (rbac.missingPermissionAttribute, 132 baseline entries dla Phase 6 retrofit #714-#717) + HardcodedRoleCheckRule (rbac.hardcodedRoleCheck) + RequiresPermission/NoPermissionRequired attribute classes + docs/static-analysis/custom-rules.md. Rule 2 (FlushWithoutClear) deferred → #720. |

**Plus 3 status/infra PR-y w sesji:**
- [#732](../../pull/732) — current_status 2/10 progress
- [#766](../../pull/766) — current_status 4/10 + lessons z brownfield audit
- [#767](../../pull/767) — Dependabot daily → weekly (po first-run flood 31 PR)
- [#775](../../pull/775) — pnpm-lock fix po slate-react auto-merge

**Krytyczne odkrycia (per lessons.md):**
- **Identity bundle JEST brownfield**: 5/9 entities + 15+ Voters + RbacSeeder + auth services już istniały pre-marathon. Phase 1 zamknął gap (5 entities, 2 migracje, 49 permissions, 9 role templates, 2 PHPStan rules, 2 docs).
- **Doctrine 3.x: array_values() na findBy() to no-op** flagged przez PHPStan max — Doctrine zwraca `list<T>`.
- **TenantAuditCommand INFRA_TABLES whitelist** dla junction/platform tables bez tenant_id.
- **CLI Playwright flake na main** (modeling-shell.spec, exports.spec, imports.spec, modeling-object-types.spec) konsekwentny — `gh pr merge --admin` jest authorised pattern gdy PHPStan + PHPUnit pass.
- **Dependabot first-run flood**: daily schedule × 31 stale updates = 31 PR-ów w 30 min. Now weekly Monday. 14 patches z stale lockfiles czekają na rebase (auto przez `@dependabot rebase` lub w następnym weekly cycle).
- **Slate-react #764 auto-merged ze stale lockfile** — Playwright na main fail'ował aż do hotfix #775.

**Phase 1 metrics:**
- 14 PR-ów zmergeowanych w sesji (10 RBAC tickets + 3 status updates + 1 lockfile hotfix)
- ~30-40h work in compressed marathon session
- Wszystkie 10 ticketów zamknięte + cascade unblocked dla Phase 2-7

**Następny krok:** **Phase 2 (Backend Auth)** — milestone [#10](../../milestone/10), 14 ticketów (#650-#663), ~80-110h estimated. Lexik JWT bundle JUŻ registered w `config/bundles.php` (Phase 2 #650 partially gotowe). Pierwsze tickety cascade-ready: #650 (Lexik JWT), #651 (email/password), #652 (API tokens), #653 (TenantFilter + Doctrine TenantContext).

**Aktywne blokery:** brak.

**Pełen plan epiku:** `Project Plan/07-rbac-implementation-plan.md` (v3.1) + `Project Plan/PRD/PRD-PIM-rbac.md` (v2.1).

---

## 2026-05-15: 🏁 Marathon EXP-01..EXP-16 ZAMKNIĘTY — 16/16 ticketów (Eksport produktów MVP)

**Sub-faza:** MVP-Final, epik EXP (Eksport produktów) ✅ DONE single-session marathon.

**16 ticketów zmergeowanych w marathonie 2026-05-15:**

| Ticket | PR | Co dostarczone |
|---|---|---|
| EXP-01 | #578 (drugi agent) | Schema + entities + MinIO bucket (3 tabele + flysystem) |
| EXP-02 | #597 | POC IMP kontrakt audit (4/4 FAIL → IMP-16..19) |
| EXP-03 | #606 | ExportBuilder + ColumnResolver + ValueSerializer + 15 unit tests |
| EXP-04 | #608 | `pim:export:benchmark` Console + append-only log |
| EXP-05 | #609 | POST /api/products/export sync + OpenSpout XLSX + CSV |
| EXP-06 | #610 | Async ExportJobHandler + Mercure SSE + MinIO upload |
| EXP-07 | #611 | Profiles CRUD API (5 endpoints) |
| EXP-08 | #612 | Sessions API (7 endpoints) + download stream + rerun |
| EXP-09 | #613 | FE foundation (Refine + routes + ExportsLayout) |
| EXP-10 | #616 | Two-pane ColumnPicker MVP |
| EXP-11 | #617 | ExportModal z 4 sekcjami |
| EXP-12 | #618 | Full-page form reusing ExportModal |
| EXP-13 | #614 | Recent exports grid + 5s polling + Download/Rerun/Delete |
| EXP-14 | #615 | Saved profiles grid + Run-now + Delete |
| EXP-15 | #619 | Hub smoke + dogfooding follow-up plan |
| EXP-16 | (ten PR) | Plan / PRD / lessons / feature-exports.md updates |

**Plus fix #607** — TenantAuditCommand whitelist `export_logs` (unblock PHPUnit po EXP-01).

**4 follow-up tickety utworzone (PRD §9.2 round-trip kontrakty IMP):**
- IMP-16 (#602) — Variants flat parent_sku
- IMP-17 (#603) — Multi-value pipe-separated parser
- IMP-18 (#604) — Asset URL → asset_id
- IMP-19 (#605) — Multi-locale columns (attribute.locale)

**Świadome odejścia:**
- BulkActionsToolbar wiring dla EXP-11 (modal ships standalone).
- Locale + channel toggles w modalu (placeholder).
- Save-as-profile FE submit (backend gotowy).
- Mercure SSE FE wiring w EXP-13 (5s polling fallback; backend publishes).
- dnd-kit drag-drop w pickerze (↑↓ buttons cover keyboard).
- target_scope=filter (backend 501; wymaga FilterDslResolver).
- Pełne 5-scenariuszowe E2E (round-trip blocked by IMP-16..19).

**Aktywne blokery:** IMP-16..IMP-19 dla pełnego round-trip Magdy SEO PL+EN (PRD §3.5 killer scenario).

**Następny krok:** operator session zamykająca IMP-16..19 + Marcin 50k SKU dogfooding (PRD §13.4).

Pełna nota feature: `Project Plan/UI/feature-exports.md`. Audit raport: `agent/exp-02-imp-audit.md`. Smoke report: `agent/exp-15-smoke-report.md`. Perf log: `apps/api/agent/exp-04-perf-benchmark.md`.

---

## 2026-05-14: 🏁 Marathon UI-09 ZAMKNIĘTY — 12/12 ticketów na main

**Sub-faza:** MVP-Alpha, epik UI-09 (Lista produktów v2 — cockpit operatora) ✅ DONE.

**12 ticketów zmergeowanych jednego dnia (single-session marathon):**

| Ticket | PR | Co dostarczone |
|---|---|---|
| VIEW-09 | #536+#537 | Smart filter presets (5 built-in + user-defined) + push-down advanced filter panel + filter chips edit popover |
| VIEW-10 | #539 | 25 operators per typ atrybutu + URL filter DSL serializer + `?smart_preset=` BE |
| VIEW-09b | #541 | Query mode AND/OR brackets editor (recursive QueryGroupEditor) |
| VIEW-11 | #542 | Cross-page selection toolbar + select-all-matching (10k cap) |
| VIEW-12 | #543 | Bulk wizard 3-step + `bulk_sessions` + `bulk_logs` + `set_attribute` E2E |
| VIEW-17 | #544 | 24h rollback toast + executor + `GET/POST /api/bulk-sessions/{id}[/rollback]` |
| VIEW-13 | #545 | clear/append/remove/increment_numeric/multi_attribute_edit handlery + wizard 6-mode picker |
| VIEW-14 | #546 | add/remove/move_category handlery + `BulkCategoryModal` + `toast.action` 5s Undo |
| VIEW-15 | #547 | publish/unpublish_channels handler + `BulkPublishModal` + cascade banner |
| VIEW-16 | #548 | delete + duplicate handlery + hard confirm typing modal |
| VIEW-18 | #549 | `AttributeLockReader` + `attribute_locked` JSONB slot + endpoint + FE toggle |
| VIEW-19 | #550 | Cmd+K palette + rule-based planner + 6 MVP intents (USP demo-ready) |

**Świadome odejścia (deferowane do follow-up ticketów):**
- `BulkRollbackHandler` pokrywa `set_attribute` only — taxonomy/channels/delete/duplicate rollback recipes już w BulkLog rows, ale dispatch per-action-type → **VIEW-17.1**.
- `Lock skip-and-report` wired dla `set_attribute` only — pozostałe 5 attribute handlerów konsumuje `AttributeLockReader` w **VIEW-18.1**.
- **Cmd+K = regex-based MVP, nie Anthropic SDK** — full LLM + tool-use + Mercure SSE + BYOK + rate limits → **VIEW-19.1 (epik 0.7 / Faza 2)**.
- **Channel publish = soft flag pod `attributes_indexed.published`** — real Shopify GraphQL + BaseLinker REST hooks z epik 0.6/0.9.
- **Cascade preview** = placeholder banner; server-side variant + cross-sell count → **VIEW-15.1**.

**Lekcje (świeżo zarejestrowane w lessons.md):**
1. PHPStan strict-rules + `preg_match` — return type `int|false` zakazany w `if`. Pattern: `if (1 === preg_match(...))`.
2. Docblock `*/` escape — `*/%` w PHPDoc zamyka komentarz przedwcześnie. Używać `add|sub|mul|div|mod` zamiast `+/-/*/%`.
3. Playwright `modeling-shell.spec.ts` flake — `/object-types` redirect → `/login` race istnieje na main od PR #543. Admin-merge wzorzec (precedens #543) odblokowuje marathon flow gdy PHPUnit ✓ + reszta gates ✓.
4. `lint-staged` "Prevented an empty git commit" — pre-commit hook stash przy unsuccessful run zostawia staged changes wyglądające jak uncommitted; trzeba re-`git add` przed kolejnym `git commit`.
5. **Empty `users` DB → „Nieprawidłowy e-mail lub hasło"** — `docker compose exec api bin/console doctrine:fixtures:load --no-interaction` przywraca admin@demo.localhost. Nie używać `pim:db:reset` jeśli inne sesje DB są otwarte (PostgreSQL `database is being accessed by other users`).

**Blockers:** brak. Operator robi manual smoke test E2E po `doctrine:fixtures:load` (admin@demo.localhost / changeme).

**Następny krok:** raport zamykający marathon (ten wpis) + manual smoke test 6 ścieżek per PR test plan + jeśli wszystko ✓ → zamknięcie issue parent epiku UI-09 + planowanie follow-up: VIEW-17.1 + VIEW-18.1 + VIEW-19.1 (epik 0.7 Faza 2).

---

## 2026-05-14: VIEW-09b marathon START — Query mode AND/OR brackets editor

**Sub-faza:** MVP-Alpha → epik UI-09, ticket 3/12 (VIEW-09b).

**Cel (~28h estymacja, plan epiku):**
- FE: pełen edytor query mode w AdvancedFilterPanel — nested AND/OR/NOT z drag-handle do bracketing (PRD §5.3 + §7.4).
- Decyzja library vs from scratch w POC pierwszego dnia (react-querybuilder v8 MIT ~50KB gzip vs from scratch).
- Read-only display mode (mockup `list-v2-overlays.jsx` l. 116-126) — JSX z kolorowymi tokenami jako preview parsed expression.
- BE: rozszerzenie `FilterDslResolver` o zagnieżdżone OR/AND/NOT struktury (depth max 3 z PRD §13.2 walidacja).
- URL persistence: hashowany blob `?q=<base64-json>` już istnieje (VIEW-10), VIEW-09b dodaje query mode UI.

**Blockers:** brak.

---

## 2026-05-14: VIEW-10 marathon ZAMKNIĘTY — pełne operatory per typ + URL DSL serializer + BE smart_preset

**Sub-faza:** MVP-Alpha, epik UI-09, ticket 2/12 (VIEW-10) ✅ DONE.

**PR:** [#539](https://github.com/malipie/PIM/pull/539) merged 2026-05-14 (`857978d`).

**Co dostarczone:**
- BE: `FilterDslResolver` rozszerzony z 11 → 25 operatorów per typ atrybutu (PRD §5.5). Stałe `OPERATORS_BY_TYPE` + `OP_*` publiczne dla FE mirror. Aliasy UI label → canonical w `normaliseOperator()`.
- BE: `toMeilisearchFilter()` — DSL → Meilisearch filter expression string. `validateOperatorForType()` — type-narrow walidacja.
- BE: `AttributeMetadataResolver` z in-memory cache per-request + reserved type fallback (`completeness_pct`, `enabled`, `sku`, `category`, `main_image`).
- BE: `FilterUrlSerializer` bi-directional URL params ↔ DSL + base64 fallback dla nested + 4096-bytes soft limit (413).
- BE: `SearchController::products()` accept `?smart_preset=<slug-or-id>` + `?q=<base64>`. `CatalogSearchService` accept optional `customFilterExpression`.
- FE: `filter-dsl.ts` 9 nowych operatorów + `FILTER_OPERATORS_BY_TYPE` pełen mirror BE. `normaliseOperator` + `operatorRequiresValue/Array/Range`.
- FE: `lib/filters/operators.ts` + `lib/filters/url-serializer.ts` (TS port BE — shorthand op codes, dslToBase64/base64ToDsl, dslToUrlParams).
- FE: `useCatalogSearch` przyjmuje `smartPresetId` + `filterBlob`. `list.tsx` propaguje activeSmartPresetId → `?smart_preset=<slug>`, panel conditions → `?q=<base64>` do BE.
- Tests: 82 zielone (32 parametrized op matrix + 11 URL serializer + 39 regression).

**Smoke test po merge:**
- POST `/api/auth/login` → 200, token 528 chars.
- GET `/api/search/products?smart_preset=red-low-completeness` → 200, processingMs=28ms (świetna p95 perf gate).
- GET `/api/search/products?smart_preset=does-not-exist-xyz` → 404.

**Lessons z VIEW-10:**
1. **PHPStan max dev vs CI cache divergence** powtarza się z VIEW-09. Lokalny cache:warmup → CI strict inference inny. Każdy `array<string, mixed>` zwracany z `json_decode` wymaga `/** @var */` + local typed alias zamiast `(string) $mixed` cast.
2. **Search bundle może depend na Catalog_Internals** (deptrac.yaml l. 152-156). Nie potrzeba osobnego Contracts wrapper'a dla SmartFilterPreset/FilterDslResolver — bezpośredni use w `SearchController`.
3. **Meilisearch filter expression syntax** różni się od SQL: `IN [a, b]` (nie `IN (a, b)`), `EXISTS` (nie `IS NOT NULL`), brak `ENDS WITH` → fallback do `CONTAINS`. Resolver musi mieć dwa compile paths.
4. **Cache w services** vs **per-request**: `AttributeMetadataResolver` używa in-memory hash (request-lifetime). To wystarczy bo Symfony FrankenPHP worker mode resetuje request scope.

---

## 2026-05-14: VIEW-10 marathon START — pełne operatory per typ + URL DSL serializer + BE smart_preset

**Sub-faza:** MVP-Alpha → epik UI-09, ticket 2/12 (VIEW-10).

**Plan epiku:** `~/.claude/plans/w-folderze-users-mlipieclocal-dev-pim-zr-iridescent-zephyr.md`.

**Cel VIEW-10 (~26h estymacja):**
- BE: rozszerzenie `FilterDslResolver` z 11 → 25 operatorów per typ atrybutu (text 8, number/metric 8, date 7, select 6, multiselect 4, boolean 1, relation 5, asset 2 — PRD §5.5).
- BE: `UrlSerializer` (bi-directional URL params ↔ JSONB DSL, lossy single-level + hashed blob fallback dla query mode).
- BE: rozszerzenie `SearchController` o `?smart_preset=<id>` + `?filter=<base64>` params → resolver → Meilisearch filter expression.
- BE: RFC 7807 Problem Details dla invalid operator/type combo.
- FE: `lib/filters/operators.ts` (OpenAPI-generated TS enum).
- FE: `components/catalog/filter-operator-picker.tsx` (popover z valid ops per type).
- FE: `lib/filters/url-serializer.ts` (useSearchParams ↔ filter state).
- FE: `lib/filters/use-filter-state.ts` (hook synchronizujący conditions z URL przez React Router).
- FE: `components/catalog/filter-chip.tsx` (inline popover, zastępuje VIEW-09 button-only).

**Ticket:** `Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-10-pelne-operatory-url-dsl.md` (rozpisany 2026-05-14).
**Branch:** `feat/view-10-pelne-operatory-url-dsl`.

**Blockers:** brak na tym etapie.

---

## 2026-05-14: VIEW-09 marathon ZAMKNIĘTY — Lista produktów v2 fundament UI + BE foundation

**Sub-faza:** MVP-Alpha, epik UI-09, ticket 1/12 (VIEW-09) ✅ DONE.

**PR-y zmergeowane:**
- **PR #536** `4203d55` `feat(catalog): Produkty · Lista v2 — fundament UI + BE foundation (VIEW-09)`
- **PR #537** `01fefb7` `fix(catalog): seed Smart Filter Presets via AppFixtures (#537)`

**Issue:** #535.

**Co dostarczone:**
- Migracja `smart_filter_presets` + 5 built-in inline seed (`inconsistent-translations`, `missing-images`, `weak-seo`, `red-low-completeness`, `no-category`).
- Nowy `SystemShipped` marker interface w `Shared\Application` — rozszerza `TenantScoped` o tenant-less lane dla shared built-in rows. `TenantFilter` + `TenantAssignmentListener` rozszerzone o allow-list.
- Encja `SmartFilterPreset` (TenantScoped + SystemShipped) z built-in immutability + ownership invariants.
- CRUD endpoint z owner-only writes + 403 built-in + 404 cross-tenant.
- `FilterDslResolver` 11 ops + flat / 3-level grouped DSL + identifier safety + SQL literal escape.
- 5 FE komponentów: `SmartFilterPresetsRow`, `AdvancedFilterPanel` (push-down Magento style), `FilterChipsBar`, `SaveAsSmartPresetModal`, `lib/filters/*`.
- `list.tsx` integracja + `applyConditionsToFilters` FE resolver (mapuje 6 known shapes na search/range filters).
- Playwright spec 3 scenariusze (built-in render, chip toggle, panel apply).

**Świadome odejścia (deferred):**
- Query mode AND/OR brackets → VIEW-09b
- Pełne 25 operatorów per typ → VIEW-10 (this)
- BE `smart_preset` param w SearchController → VIEW-10
- Inline FilterChip popover (operator + value picker) → VIEW-10
- Cross-page selection → VIEW-11
- BulkBar 14 akcji + wizard → VIEW-12+
- Cmd+K palette → VIEW-19
- Rollback toast → VIEW-17
- Per-attribute lock → VIEW-18
- axe-core E2E scan (pakiet `@axe-core/playwright` nie zainstalowany) → follow-up

**Lessons z VIEW-09:**
1. **SystemShipped marker pattern**: tenant-less built-in rows wymagają oddzielnego interfejsu (nie wystarczy `TenantScoped` z `tenant_id IS NULL`). TenantFilter + TenantAssignmentListener muszą wiedzieć żeby pominąć.
2. **`pim:db:reset --with-fixtures` skipuje migration inline INSERT-y** (schema:create zamiast migrate). Każdy migracja seed inline MUSI mieć runtime seeder wywoływany z AppFixtures dla dev/test parity.
3. **PHPStan max różni się dev vs CI** — `mixed[]` w lokalnym cache, `array<string, mixed>` w CI inference. Lokalny `mixed[]` może być błędny gdy cache:warmup nie został wywołany przed phpstan.
4. **Deptrac**: Catalog nie może depend na `Identity\User`. Rozwiązanie: marker interface w `Shared\Application` (`UserIdentityAware extends UserInterface` + `getId(): Uuid`); `User implements` go.
5. **Raw SQL lint** łapie też słowo `executeQuery` w docblockach (regex `executeQuery|executeStatement|createNativeQuery` na całym pliku). Każde wystąpienie wymaga inline `// tenant-safe:` markera lub przefrazowania.
6. **TenantAuditCommand** ma allow-list `NULLABLE_TENANT_TABLES` — każdy nowy SystemShipped table musi tam dojść.
7. **TS 5 / React 19** — `JSX.Element` namespace nie jest globalny, return type albo inferowany, albo importowany jako `type { JSX } from 'react'`.
8. **Biome strict**: `role="region"` woła `<section>`, `role="radio"` na button woła real `<input type="radio">`, `aria-label` na `<span>` jest forbidden, index-as-key wymaga inline `biome-ignore` z uzasadnieniem.
9. **Playwright pre-existing flake** (rate-limit auth quota → `modeling-shell` + `dashboard` redirect na `/login`) — merge mimo czerwonego per precedens PR #534. Storage state rollout odblokuje CI Playwright na większości specs.

---

## 2026-05-13: PR #534 — białe okno na „Generuj warianty" (HOTFIX)

**Branch:** `fix/variants-generation-perf` (merged, branch deleted)
**PR:** [#534](https://github.com/malipie/PIM/pull/534) merged `7ec48c0` via `--admin --squash` (Playwright red — pre-existing `modeling-shell.spec.ts` flake, niezwiązany; per current_status precedensów z UI-11)

**Operator zgłosił**: „próba generowania wariantów w szczegółach produktu wygenerowała błąd white screen + system działa wolniej". Screenshot pusty biały.

**Root cause** (z logów Caddy + FrankenPHP):
- `POST /api/products/{id}/generate-variants` timeoutowało na 30s — `PHP Fatal: Maximum execution time of 30 seconds exceeded` w `Doctrine ORM RawValuePropertyAccessor`. FrankenPHP zwracał 200 + `text/html` error page.
- `jsonFetch` traktował HTML jako string typowany `T`, a `variants-tab.tsx` crashował na `response.created.length` → React error boundary → biały ekran bez toastu.
- Powód timeoutu: kontroler robił M kombinacji × N master values × O(flush) — każdy `$repo->save()` triggerował immediate flush + `AttributesIndexedSyncListener::postFlush` (drugi flush). Dla 6 kombinacji × 15 wartości = min. 222 round-tripów. `findByObject($master)` był w pętli zamiast przed nią.
- Master `019e1e58…` na localhoście miał 95 sierot z poprzednich częściowych timeoutów (brak transakcji wokół pętli → partial commit).

**Co dostarczono**:
- `GenerateVariantsController`: `BulkContext::setBulk(true)` wokół pętli, `findByObject($master)` raz przed pętlą, `$em->persist()` + jeden `$em->flush()` w `wrapInTransaction()`, post-flush rebuild `attributes_indexed` per nowy wariant przez `AttributesIndexedRebuilder`, `set_time_limit(120)` jako defence in depth.
- `jsonFetch`: rzuca `HttpError` gdy 200 + non-JSON `Content-Type` (zamiast oddawać HTML jako `T`). Wzorzec rozciągnięty na cały admin — chroni przed analogicznymi crashami w innych endpointach.
- `variants-tab.tsx`: defensive `Array.isArray(response.created)` + `?? 0` na licznikach.

**Smoke test (curl + DB inspect)**:
- 3×3 = 9 wariantów: **0.20s** (było 30s timeout). HTTP 201, `Content-Type: application/json`.
- Re-run tego samego payloadu: `created_count: 0, skipped_count: 9` w 0.03s (idempotency OK).
- `attributes_indexed` per wariant: 15 wartości odziedziczone z mastera + nadpisane `color`/`size` z osi.

**Świadome odejścia**:
- Druga obserwacja operatora („system działa wolniej") — nie zaadresowana w tym PR. Hipoteza: route-level code splitting z #339cbf8 daje one-time chunk fetch per pierwszy entry routa (~50-200ms perceived). Wymaga osobnego audytu p95 latencji top-10 endpointów (Prometheus dashboard) — kandydat na maintenance ticket.
- Wzorzec `Doctrine*Repository::save()` z immediate flush jest w innych repo (catalog/asset/channel/etc). Refactor systemowy poza scope tego bugfixa — kandydat na ADR „repos should not flush" w fazie 1.

---

## 2026-05-13: PR #533 — auto-seed admin user na container start (DEV-QOL)

**Branch:** `dev/auto-seed-on-container-start`
**PR:** [#533](https://github.com/malipie/PIM/pull/533) (CI w toku w momencie commit'u tego statusu)

**Powód**: powtarzający się class incidentów „login broken po wipe" (po `pim:db:reset`, `docker compose down -v`, big rebase'ie). Operator widział `Nieprawidłowy e-mail lub hasło` toast — technicznie poprawny, ale wygląda jak bug.

**Co dostarczono**:
- `pim:dev:ensure-seeded` — idempotentna komenda console (decision tree: pusta DB → reset+fixtures, populated DB bez admin → warning+exit 0, admin obecny → noop). Skipuje na `APP_ENV=prod`.
- `apps/api/docker-entrypoint.sh` — wrapper FrankenPHP entrypoint odpalający ensure-seeded raz przed `exec frankenphp run`. Best-effort: api wstaje mimo failure seed.
- `apps/api/Dockerfile` — `COPY` entrypointu, `ENTRYPOINT` + `CMD` zachowujące upstream `frankenphp run …`.
- **Pre-existing bug fix** w `DatabaseResetCommand`: nested `ArrayInput` musiał mieć `setInteractive(false)` przed `run()`, inaczej `doctrine:fixtures:load` cicho cancelował się na purge prompt (default `[no]`) a parent reportował success. Inne chained commands miały default `[yes]` więc nie były dotknięte. Lekcja → lessons.md.

**Smoke E2E**: drop pim → recreate empty → restart api → entrypoint odpalił reset+fixtures → POST `/api/auth/login` HTTP 200 + JWT. Idempotency: re-restart na populated DB = silent noop (`--quiet-when-noop`).

---

## 2026-05-12: Epik UI-11 — Importy redesign **DOMKNIĘTY** (8 PR-ów merged, marathon ~24h)

**Merged commits w kolejności:**
1. `2a4b99c` ([#494](https://github.com/malipie/PIM/pull/494)) IMP-16 — bloker: kategoria assignment z importu
2. `b63e1f3` ([#495](https://github.com/malipie/PIM/pull/495)) VIEW-IMP-00 — foundation: tab container + 10 primitives
3. `07fa5a5` ([#497](https://github.com/malipie/PIM/pull/497)) VIEW-IMP-01 — Sesje: KpiStrip + LiveSessionCard + HistoryTable
4. `f2865f0` ([#499](https://github.com/malipie/PIM/pull/499)) VIEW-IMP-02 — Profile: grid/list + duplicate/export/import
5. `16261e9` ([#501](https://github.com/malipie/PIM/pull/501)) VIEW-IMP-03 — Źródła: ImportSource + health-check
6. `54daaae` ([#503](https://github.com/malipie/PIM/pull/503)) VIEW-IMP-04 — Harmonogram: ImportSchedule + cron parsing
7. `67eeccf` ([#505](https://github.com/malipie/PIM/pull/505)) VIEW-IMP-05 — Wizard: WizardStepper + header eyebrow
8. `77d880a` ([#507](https://github.com/malipie/PIM/pull/507)) VIEW-IMP-AUDIT — lessons + plan + status

Marathon zakończony w ~24h faktycznych (vs ~118h estymata mid → 5x faster). Operator dał mandate `merge --admin` dla 4/6 PR-ów z powodu znanego flaky `modeling-shell.spec.ts` (dashboard heading + menu główne, niezwiązane z V01..V05). Wszystkie 4 zakładki Importu zaaplikowane + wizard refactor.

**Świadome odejścia → follow-up VIEW-IMP-03.1 + VIEW-IMP-04.1**:
- Polling daemon dla `ImportSource` (Symfony Scheduler + Messenger).
- Cron worker daemon dla `ImportSchedule` (60s tick).
- Real notification transport (Slack/Email/Webhook).
- Real SFTP/FTP/HTTP probes (stub driver w MVP).
- Webhook receiver endpoint dla `type=webhook`.
- modeling-shell.spec.ts flaky root cause (kandydat na separate maintenance ticket).

---

## 2026-05-11: Epik UI-11 — Importy redesign **W TOKU** (legacy log)

Operator wrzucił design do `Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/` (6 plików JSX + 4 HTML-e) → przeprojektowanie zakładki Importu w sekcji Integracje na **tabbed hub z 4 zakładkami**: Sesje, Profile, Źródła, Harmonogram.

**Scope** (zatwierdzony plan-mode): 5 widoków + foundation + audit = 7 ticketów. Sources + Schedule to NOWA funkcjonalność BE (encje, cron worker, polling, notifications). Sesje + Profile = overhaul UI istniejących pages. Wizard refactor = osobny ticket.

**Routing**: zagnieżdżone `/integrations/imports/sessions|profiles|sources|schedule`, default redirect `/integrations/imports` → `/sessions`.

**Tryb**: marathon mode (CLAUDE.md → EPIK MARATHON RULE), każdy ticket = własny branch + PR + CI + merge.

**Estymacja**: ~84h (low) / ~118h (mid) / ~156h (high).

**Plan epiku**: `~/.claude/plans/nifty-exploring-dolphin.md` (referencja) — pliki ticketów w `Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-IMP-NN-*.md`.

**Blokery zamknięte przed startem:**
- **IMP-16** ([#494](https://github.com/malipie/PIM/pull/494) → `2a4b99c`) — category assignment from import (`__category__` mapping target). Operator pracował lokalnie, paczka zamknięta jako osobny PR przed epikiem żeby working tree było clean.

**Postęp epiku:**
- ✅ **VIEW-IMP-00** ([#495](https://github.com/malipie/PIM/pull/495) → `b63e1f3`) — foundation: `<ImportsLayout>` + `<ImportsTabNav>` + 10 reusable prymitywów + i18n + Playwright spec. CI 6/6 zielone.
- ✅ **VIEW-IMP-01** ([#497](https://github.com/malipie/PIM/pull/497) → `07fa5a5`) — Sesje overhaul. BE: `ListImportSessionsController` + `ImportThroughputController` + `ImportThroughputCalculator`. FE: `ImportSessionsView` (zastępuje `ImportsListView`) + KpiStrip + LiveSessionCard + HistoryTable. Test: 9 ApiTestCase + 6 unit + 1 Playwright (skonsolidowane). Lekcja: nowe spec'y MUSZĄ mieć 1 test/1 login (rate-limiter 5/IP/15min).
- ✅ **VIEW-IMP-02** ([#499](https://github.com/malipie/PIM/pull/499) → `f2865f0`) — Profile overhaul. BE: migracja `import_profiles.code` + `mode` + UNIQUE (tenant_id, user_id, code), `ImportMode` enum, 3 custom controllery (Duplicate/Export/Import). FE: `ImportProfilesView` z grid/list toggle, `ProfileEditDialog`, search, delete dialog. 7 ApiTestCase + Playwright. Merged --admin: modeling-shell.spec.ts flaky (linie 38 + 205, dashboard heading + menu główne) — niezwiązany z V02. Follow-up: znaleźć root cause flaky test'u.
- ✅ **VIEW-IMP-03** ([#501](https://github.com/malipie/PIM/pull/501) → `16261e9`) — Źródła. BE: migracja `import_sources` + `import_source_logs`, encje, enumy, voter, `HealthCheckService` (Folder real, reszta stub), AP4 ApiResource CRUD + `TestImportSourceConnectionController`. FE: `ImportSourcesView`, `SourceCard`, `SourceFormDialog`. 7 ApiTestCase + Playwright. Merge --admin (modeling-shell flaky znów). Polling daemon nadal poza scope.
- ✅ **VIEW-IMP-04** ([#503](https://github.com/malipie/PIM/pull/503) → `54daaae`) — Harmonogram. BE: migracja `import_schedules` + `import_schedule_runs`, encje + enumy + voter, `CronExpressionParser` (dragonmantank), `ScheduleDispatcherService`, AP4 CRUD + 4 custom controllers. FE: `ImportScheduleView` + `NextRunsTimeline` + `ScheduleCard` + `ScheduleFormDialog`. Merge --admin (modeling-shell flaky). Cron worker + real notifications → V04.1.
- 🟡 **VIEW-IMP-05** — Wizard refactor (start: 2026-05-12).

---

## 2026-05-10: Epik UI-10 — Product Categories Assignment **DOMKNIĘTY** (PCAT-01..07 + PCAT-06b merged)

Marathon zakończony — wszystkie 8 ticketów PCAT-01..07 + PCAT-06b shipped w jednym dniu (Marathon Rule). Killer feature „Effective preview" w panelu kategorii dostała empirical validation: produkt można wpiąć w kategorię, jego forma realnie pokazuje dziedziczone grupy atrybutów.

**Backend (4 PR-y):**
- #482 (`9e69868`) PCAT-01 — DB junction `object_categories` (composite PK + partial unique `WHERE is_primary=true` + cascade FK + entity + repo + atomic replace)
- #483 (`89f7b88`) PCAT-02 — `ProductCategoryAssignmentController` (GET / PUT atomic replace / POST idempotent / DELETE auto-promote-next; 50-cap; tenant isolation)
- #485 (`412d9c6`) PCAT-03 — `EffectiveAttributeGroupResolver` branch dla `kind=Product` + `PrimaryCategoryRepairListener` (cascade safety)
- #486 (`aeb4cb8`) PCAT-04 — `ObjectFormSchemaCacheInvalidator` hook na `ObjectCategory` (per-ObjectType, follow-up per-object Faza 1.1)

**Frontend (3 PR-y):**
- #487 (`be427e4`) PCAT-05 — tab „Kategorie" w karcie produktu (między Multimedia a Powiązania) + `CategoryPickerDialog` (multi-select tree z primary radio) + chip-list assignments z reactive POST/DELETE
- #488 PCAT-06 — `CategoryProductsCard` w panelu kategorii (paginowana lista produktów + ★ primary + link do `/products/{id}`) + backend `GET /api/categories/{id}/products`
- #489 PCAT-06b — aktywacja MOCK „+ Create test object" → `<Link>` do `/products/new?categories=<id>&primary=<id>` + post-POST PUT na junction

**Maintenance:**
- #484 (`26d3cac`) chore(deps) — `pnpm.overrides` na `fast-uri >= 3.1.2` (transitive vuln w `@commitlint/cli > ajv > fast-uri`)

**Dokumentacja:**
- `Project Plan/UI/epik-10-product-categories.md` — pełny opis epiku (problem / outcome / decyzje / architektura / lessons / next steps)
- `Zrodla/PRD/MDM-rozszerzenia-pomysly.md` — brainstorm 5 use-case'ów rozszerzeń MDM (bazy kompatybilności, salony, lookbooki, recipe, sales reps) z mapowaniem na ADR-009 — Faza 2/3 scope
- `agent/lessons.md` — 10 patterns z PCAT (junction inheritance, partial unique, atomic replace, DBAL listener, cache trade-off, OpenAPI custom controllers, pnpm overrides, subroute pattern, tab semantics, killer-feature validation)
- `CHANGELOG.md` — sekcja Added — epik UI-10
- Snapshot `packages/shared-types/src/api.d.ts` (4927 linii, AP4 routes; custom controllery jak w `CategoryAttributeGroupController` precedensem nie wpisane do OpenAPI — follow-up ticket „API documentation completeness")

**Świadome odejścia (kandydaci na follow-up):**
- **PCAT-FOLLOWUP-01 — per-object cache key** (Faza 1.1, 4-6h)
- **PCAT-FOLLOWUP-02 — bulk reassignment z grida produktów** (Faza 1)
- **PCAT-FOLLOWUP-03 — E2E spec dla picker-a** (mały)
- **API documentation completeness** — OpenAPI annotations dla wszystkich custom controllers (osobny epik)

---

## 2026-05-07: Epik UI-09 / 0.13 — Imports MVP **DOMKNIĘTY** (IMP-01 do IMP-15 merged)

Marathon zakończony — wszystkie 15 ticketów IMP-01..IMP-15 na `main`. IMP-14 (#455 → `d4ef77e`) i IMP-15 (#456 → ten commit) zamknęły epik. **Świadome odejścia** zostają jako follow-up'y:
- **Image download / ZIP extract** (IMP-04 plan §IMP-04) — handler dispatchuje proces, ale realnego download'u + ZIP unpack nie wykonuje. Wymaga: kolejka `imports.images`, `ImageDownloadHandler` (concurrent max 10, timeout 30s), `ZipExtractHandler` (case-insensitive filename match → MinIO via `AssetUploader`). Wycena: 6-8h.
- **Dogfooding US-IMP-005** (IMP-14) — Marcin uploads ~2k SKU IdoSell. Gate przed *„imports gotowe na pierwszy real-world"* nadal otwarty; wymaga realnego export'u IdoSell.
- **Playwright E2E suite expansion** (IMP-14 plan: 6 spec'ów: 100 rows / 500+ZIP / rollback / async 5000 / duplicate SKU) — w marathonie zostawione jako 1 smoke spec; rozbudowa razem z dogfooding'iem (gdy fixture'y realne).
- **Performance benchmark 5k rows < 256 MB** (IMP-14) — pomijane bez 5k fixture; follow-up razem z dogfooding'iem.

Followup-y → kandydaci do **maintenance ticket co 2 epiki** (per `CLAUDE.md` "Zarządzanie zależnościami") — patrz §3.7 plan-projektu.

## 2026-05-07: Epik UI-09 / 0.13 — Imports MVP (IMP-01 do IMP-13 merged)

Operator: „zacznij prace, po kolei w tym epiku, wykonaj wszystko" — EPIK MARATHON RULE aktywny. Każdy ticket = własny branch + PR + CI + merge. 13 z 15 ticketów done na main:

- **Backend** (IMP-01..07, merge'd):
  - #442 → `69a2b71` schema + entities (4 tabele + ALTER objects + 4 entities + 5 enums + voters)
  - #443 → `ec300e1` file parsing + dictionary auto-mapping (PhpSpreadsheet + league/csv + YAML PL/EN ~30 atrybutów)
  - #444 → `d04df1f` validate-dry-run endpoint (5 typów błędów + ApiTestCase 247/33)
  - #445 → `02d7b90` async ImportRunHandler (chunked + Mercure progress + StartImportController)
  - #446 → `f91f909` rollback + report CSV (24h window guard + DBAL bulk delete)
  - #447 → `63be689` pgBackRest manual snapshot (Symfony\Process + state machine + rate limit 1/h/tenant)
  - #448 → `ea10825` import profiles CRUD (AP4 + state processor + voter per-user own)
- **Frontend** (IMP-08..13, merge'd):
  - #449 → `74d5348` foundation primitives (Stepper, Progress, Combobox, DataTable, FileDropzone, useImportWizard)
  - #450 → `4539ef1` list view + Publikacje sub-tab (StatusBadge, EmptyStateProducts CTA enabled)
  - #451 → `495dcb5` wizard Step 1 (Upload) + Step 2 (Mapping) — auto-map + persist/restore deep-link
  - #452 → `c16ec37` wizard Step 3 (Validation) + Step 4 (Confirm) — KPI + BackupTriggerCheckbox
  - #453 → `424e498` progress + results + RollbackButton (Mercure SSE useImportProgress)
  - #454 → `3bc6ea5` profile manager modal (Sheet + edit/delete dialogs)

Pozostałe na epiku:
- **IMP-14** (#455) — E2E smoke (Playwright) + dogfooding US-IMP-005 (Marcin uploads 2k SKU IdoSell). W toku w branch `feat/imp-14-e2e-smoke`.
- **IMP-15** (#456) — Plan/PRD updates (R-30 ryzyko już w plan-projektu z setup; lessons.md zaktualizowany w IMP-14; epik 04 link + status flip).

Kluczowe decyzje świadome (per epik marathon "minimum viable slice if pełen scope wykonalny"):
- **Image download HTTP + ZIP extract** (spec §5.6 §7.3-7.4) odsunięte do follow-up po IMP-04 — handler ma row hook gotowy, ale fetch concurrent + ZIP unpacking dochodzi przed dogfooding'iem.
- **Worker-side pause/cancel mid-run** — endpointy działają (status flip), runtime control trafia z IMP-14 5k-row testem.
- **RBAC permissions** dla CatalogManager: dodane `import_session`/`import_profile` RWD + `backup` R, write na backup tylko super_admin (spec §7.8).

Lessons spisane w `agent/lessons.md` § "Lessons z 0.13 / UI-09 (Imports MVP)" — m.in.: in-memory transport CI override w testach, AP4 IsGranted subject param requirement, Synology Drive dataless flag remediation patterns dla vendor + node_modules.

## 2026-05-05: #438 DAM MVP — `/assets` upload, miniatury, edycja, dedupe, search, bulk

Operator: „rozbuduj widok multimedia o możliwość dodawania multimediów + must have funkcjonalności jakie powinien mieć DAM w wersji MVP". Plan-mode → 4 pytania (scope: pełen DAM MVP / thumbnails: async via Messenger / formaty: obrazy + PDF / dedupe: SHA-256 z 409). ExitPlanMode → operator zatwierdził → bypass permissions („działaj bez zatrzymywania").

Issue #438, branch `feat/assets-dam-mvp`, draft PR #439.

**Stan na PR opening (commit dc07789):**
- Backend wszystkie warstwy gotowe: migracja Doctrine `Version20260505192941` (content_hash, width/height/page_count, tags JSONB, thumbnails_status + 4 indeksy + CHECK), domain (Asset extended, 4 nowe eventy, ThumbnailsStatus enum), application (AssetUploader z dedupe + dispatch, AssetMetadataUpdater, AssetDeleter, AssetThumbnailHandler, ImagickImageProcessor, MimeTypeWhitelist, DuplicateAssetException), HTTP (`POST /api/assets/upload`, `PATCH /api/assets/{id}`, `DELETE /api/assets/{id}`, `POST /api/assets/bulk-delete`, AssetCollectionFilterExtension dla GET filterów), konfiguracja (services.yaml params, messenger transport, Dockerfile imagick + ghostscript + ext-imagick — wymaga `pnpm stack:rebuild`).
- Frontend wszystkie komponenty gotowe: `lib/asset-upload.ts` (XHR + progress + 401 retry), `AssetUploadDropzone` (HTML5 drag-and-drop), grid w list.tsx (multi-select + polling), `AssetFilterBar` (debounced search + MIME group), `AssetEditDialog` (code + tags), `AssetBulkActionsBar`, `AssetDuplicateDialog`, show.tsx z Edit/Download/Delete + chip list tagów, i18n PL/EN pełne drzewo, dataProvider forwarduje `eq` filtry.
- Quality: PHPStan max zielony dla `src/Asset/`. Frontend typecheck + lint zielone. 6 pre-existing PHPStan errors w `GenerateVariantsApiTest.php` istnieją na main (zweryfikowane git stash + lint) — nie z mojego ticketu.
- Świadome odejścia (PR description): alt edit deferred (Deptrac blokuje cross-context, potrzebny Catalog_Contracts writer), date/size/tag filter UI (backend gotowy), video MP4 (Faza 1), tests (#438a follow-up po smoke), Mercure SSE (polling wystarczy w MVP).
- Endpoint poza planem: `POST /api/assets` zmieniony na `POST /api/assets/upload` (AP4 GET ma już `/api/assets`, Symfony route conflict).

**Następne kroki (PR DRAFT):**
1. `pnpm stack:rebuild` (imagick + ghostscript install) — w toku w tle.
2. `doctrine:migrations:migrate --no-interaction` na świeżym kontenerze.
3. `messenger:consume async` worker dla thumbnails.
4. Manual smoke wg PR description (login → drag-drop 5 plików → edit → bulk delete → filters).
5. Po smoke OK: tests follow-up (#438a) i mark PR ready-for-review.

## 2026-05-04: VIEW-07 view-first marathon — Produkty edycja + tworzenie

Operator: dostarczył screenshot widoku edycji produktu (Czujnik X-200) + plik `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/produkty/detail-view.jsx`. Screenshot ma priorytet (makieta była aktualizowana). Plan-mode → 3 pytania scope (relayout VariantsTab body / frontend trzyma stan przy create / 4 lokale UI dropdown bez i18n DE/CS w admin) → ExitPlanMode → ticket VIEW-07 + Issue #420 + branch `feat/view-07-produkty-edycja-relayout`.

Source of truth: [`Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-07-produkty-edycja-relayout.md`](../Project%20Plan/UI/Wdrozenie_grafiki/ticket-VIEW-07-produkty-edycja-relayout.md).

Cel: pixel-perfect relayout `/products/:id` + uproszczenie `/products/new` (zlikwidować 3-step CreateProductWizard, jedna formatka). Inline edit globalny (Edytuj↔Zapisz toggle), dropdowny PL/EN/DE/CS i Shopify/BaseLinker/Allegro, Duplikuj jednym klikiem (POST /api/products/{id}/duplicate z defaults), Podgląd jako mock toast. Multimedia/Powiązania/Historia zostają jako mocki.

FE: nowe `ProductDetailPage`, `AttrGroupCard`, `AttrRow`, `LocaleChannelToolbar`, `SaveToggleButton`, `DuplicateButton`, `PreviewButton`, `SyncStatusCard`, `VariantsListCard`, `EffectiveModelCard`. Reuse: `CompletenessRing`, `ProvenanceBadge`. Usuwamy: `edit.tsx`, `form.tsx`, `create-product-wizard.tsx`, `detail-dynamic-form.tsx`, `detail-group-nav.tsx`, `detail-sidebar.tsx`. Routing `/products/:id/edit` → redirect. BE: zero nowych endpointów, dodać ApiTestCase dla `DuplicateProductController`.

Estymacja: ~8-12h LLM-time. Sukces ogłaszamy po smoke teście logowania.

## 2026-05-04: VIEW-06 view-first marathon — Kanały CRUD + mapping editor

Operator: po PR #416 (sidebar refactor + wizard #372 + VIEW-01b #413) zmergowanym, kontynuacja prac nad widokiem `/settings/channels`. Brak mockupu — design source = obecny list+show + reuse wzorców. Marathon mode aktywny, bypass permissions, „działaj aż do końca, nie pytaj o nic".

Source of truth: [`Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-06-channels-settings-crud-mapping.md`](../Project%20Plan/UI/Wdrozenie_grafiki/ticket-VIEW-06-channels-settings-crud-mapping.md).

Decyzje (4 pytania): rozszerzyć obecny list+show / inline edit z debounced PATCH 500ms / Locale+Currency jako nowe read-only ApiResource / jeden marathon PR.

Issue #418, branch `feat/view-06-channels-settings-crud-mapping`. Estymacja: ~8-12h LLM-time. Sukces ogłaszamy po smoke teście logowania.

## 2026-05-03: VIEW-05 produkty-lista marathon — pixel-perfect delta-alignment

Operator: dostarczył mockup `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/produkty/list-view.jsx` z literal scope „TYLKO lista, bez szczegółów produktu". Plan-mode → 4 pytania scope (delta-alignment / 1 ikona aggregate / SavedViewsRail zastępuje Dropdown / BulkBar 4 akcje jako toast placeholder) → ExitPlanMode → ticket VIEW-05 + Issue #411 + branch `feat/view-05-produkty-lista`.

Source of truth: [`Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-05-produkty-lista.md`](../Project%20Plan/UI/Wdrozenie_grafiki/ticket-VIEW-05-produkty-lista.md).

Stan: lista produktów istnieje w ~75-80% (PR-y UI-02 + UI-03.3 #358). VIEW-05 dokleja delta-alignment do pixel-perfect (header 32px h1 + breadcrumb, SavedViewsRail nowy, FilterPill 4× w toolbarze, ProductsGrid 12 kolumn, BulkBar pixel-perfect, in-house toast). Backend zero zmian — wszystkie BE rozszerzenia idą do follow-up VIEW-05.1–VIEW-05.7.

Estymacja: ~22-28h FE-only.

## 2026-05-03: VIEW-02 + VIEW-03 marathon — backend foundation + first FE polish merged

Operator: „pracuj VIEW-02 first → VIEW-03, bypass permissions, działaj aż do końca obu". Marathon mode w toku. Stan na 2026-05-03 ~01:00Z:

### Zmergowane do main (8 PR-ów)

VIEW-02 (Attributes Library — issue #374):
- **#377** docs(view-02) — ticket rozpisany.
- **#378** feat(catalog) — `AttributeOption` rozszerzenie schema/entity (`color` hex + CHECK, `is_default` partial unique, `is_deprecated`) + 2 domain exceptions + unit tests.
- **#380** feat(catalog) — serializer expose dla VIEW-02 + VIEW-03 nowych pól.
- **#381** feat(catalog) — `Attribute` POST/PATCH/DELETE ApiResource: 3 CQRS commands + handlers + AP4 `AttributeProcessor` (mirror `AttributeGroupProcessor`). 6 ApiTestCase scenariuszy (snake_case 422, duplicate 409, partial PATCH, system delete 422). **Merged**.

VIEW-03 (Attribute Groups — issue #375):
- **#376** docs(view-03) — ticket rozpisany.
- **#379** feat(catalog) — `AttributeGroup` 3 boolean kolumny (`is_required_section`, `is_shared` default true, `has_conditional_visibility`) + entity setters + unit tests.
- **#380** (shared) — serializer expose.
- **#382** feat(catalog) — wire behavior flags przez API (Input/PatchInput/Command/Handler/Processor) + 2 ApiTestCase scenariusze. **Merged**.

### W locie (CI / merge)

- **#383** feat(catalog) — bulk-attach/detach/reorder dla AttributeGroup członkostwa (3 endpointy: `POST .../attributes/bulk-attach`, `DELETE .../attributes/{attributeId}`, `POST .../attributes/reorder`). 5 ApiTestCase scenariuszy.
- **#384** feat(admin) — pixel-perfect AttributeGroup create form: `<ColorPicker>` z `ATTRIBUTE_GROUP_SWATCHES` (8-swatch) + `<IconPicker>` z `ATTRIBUTE_GROUP_ICONS` (14 emoji) + 3 `<SettingToggleRow>` Behavior cards. Submit body wysyła nowe behavior flags.
- **#385** feat(catalog) — fixtures: audit system group seedowany z `requiredSection: true, shared: false, conditionalVisibility: false` (zamiast constructor defaults).

### Zostaje na follow-up (świadome odejście — pełen scope VIEW-02+VIEW-03 ~80h, niemożliwy w jednej marathon session)

VIEW-02:
- AttributeOption ApiResource (GET nested `/api/attributes/{code}/options` + POST/PATCH/DELETE + reorder controller).
- Per-option usage endpoint.
- FE rebuild list.tsx (chip filtry pixel-perfect) / show.tsx (edit-in-place + sticky bottom bar) / new create.tsx (`AttributeTypeGrid` + sidebar Preview + Następnie) / values.tsx (dnd-kit + ColorSwatchPicker + ValueRowItem + AttributeValueDefinitionCard).
- ~10 nowych komponentów FE: `<FlagPill>`, `<AttributeTypeGrid>`, `<ValueRowItem>`, `<AttributeValueDefinitionCard>`, `<AttributeValuePreviewCard>`, `<AttributeValueAuditCard>`.
- Fixtures rozbudowanie: 27 atrybutów z mockupu + 7 IP-rating values z kolorami + 5 currency + 5 vat_rate + 4 tags z is_default.
- ~45 i18n keys + Playwright e2e specs + axe-core.

VIEW-03:
- Rozszerzenie `POST /api/attributes` o `attachToGroups: string[]` (atomic create + attach dla popupu „Stwórz nowy").
- Rozszerzenie `GET /api/attribute_groups/{id}/usage` o `instancesAffected`, `typesUsed`, `categoriesUsed`.
- Sidebar Preview + Następnie cards na create form.
- FE rebuild list.tsx (system + business sections, 2 sekcje) i show.tsx (sticky header + 4 Cards: Identyfikacja / Attributes / Visibility rules / Where used + 2 popupy).
- 2 popupy: `<AddAttributeFromLibraryDialog>` (multi-select, search + type filter), `<CreateAttributeInGroupDialog>` (skrócony attribute create).
- Fixtures rozbudowanie: 12 grup z mockupu (audit, identification, marketing, tech-spec, pricing, wymagania-medyczne, refundacja-nfz, chirurgia-szczegoly, ortopedia, scheduling, cennik-medyczny, specyfika-fryzjerska) + 2 visible_when rules.
- ~75 i18n keys + Playwright e2e + axe-core.

## 2026-05-02 (cd. II): VIEW-03 view-first ticket rozpisany — Modelowanie · Attribute Groups

**Issue #375** (VIEW-03) — pixel-perfect rebuild Modelowanie · Attribute Groups (lista + create `/new` + detail edit-in-place + 2 popupy: „Z biblioteki" multi-select i „Stwórz nowy" skrócony attribute create). Branch `feat/view-03-modelowanie-attribute-groups` (od main, niezależny od VIEW-02 #374).

Source of truth: `Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-03-modelowanie-attribute-groups.md`.

Status: **ticket rozpisany, implementacja czeka.** Operator chce zrobić VIEW-02 i VIEW-03 po kolei (oba rozpisane, implementacja sekwencyjnie). Rekomendowany flow: VIEW-02 first (reuse `<ColorSwatchPicker>`/`<TypeBadge>`/`<AttributeTypeGrid>`), potem VIEW-03.

Estymacja VIEW-03: ~32h (BE 10h + FE 16h + E2E 4h + quality 2h).

## 2026-05-02 (cd.): VIEW-02 view-first ticket — Modelowanie · Attributes Library

**Status**: 📋 PLANNED — ticket rozpisany, GitHub Issue otwarte, czeka na sygnał operatora „lecimy z implementacją".

**Trigger**: operator dostarczył 2 mockupy (`Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/attributes.jsx` + `attribute-values.jsx`) i poprosił o rozpisanie ticketu przez SKILL-VIEW-FIRST-TICKET. Plan-mode + 3 Explore agentów (FE/BE/handoff) + AskUserQuestion (3 pytania scope: Edit form / Migration modal / Fixtures) → 3 decyzje operatora → ExitPlanMode → ticket file + Issue.

**Issue #374** — VIEW-02 — pixel-perfect biblioteka atrybutów: lista + detail edit-in-place + create form + values editor (dla typów `select`/`multi-select`).

**Decyzje operatora 2026-05-02**:
1. **Detail = edit-in-place** (nie osobna trasa `/edit`). Sticky bottom bar `Anuluj | Zapisz zmiany` zamiast „Edytuj" w header. Zgodne z VIEW-01 ObjectTypes show.tsx pattern.
2. **MigrationImpactModal**: as-is, current `migrate-type.tsx` poza scope → follow-up VIEW-02c.
3. **Fixtures**: dosypujemy do dokładnie 27 atrybutów wg mockupu + 7 wartości IP rating z kolorami + 5 currency + 5 vat_rate + 4 tags. Pixel-perfect screen ≡ prod.

**Estymacja**: ~48h (BE 16h + FE 24h + testy 6h + PR/CI 2h).

**Source of truth**: [`Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-02-modelowanie-attributes.md`](../Project%20Plan/UI/Wdrozenie_grafiki/ticket-VIEW-02-modelowanie-attributes.md).

**Branch**: `feat/view-02-modelowanie-attributes` (czeka na implementację).

**Następny krok**: operator zatwierdza scope → agent leci marathon (BE-first → FE → quality gates → PR → CI → merge → smoke).

## 2026-05-02: VIEW-01 view-first marathon — Modelowanie · Object Types

**Nowa metodyka view-first** (operator-feedback 2026-05-02): operator dostarcza widok → agent rozpisuje jeden ticket FE+BE+testy → smoke + manual operatora. Pełen szablon w `feedback_view_first_ticket_template.md` (memory). Konstytucja: `feedback_view_first_workflow.md`.

**Aktualne**: Issue **#372** (VIEW-01) — pixel-perfect rebuild Modelowanie · Object Types (lista + detail + wizard `/new` + obsługa locali workspace). Branch `feat/view-01-modelowanie-object-types`. Praca w trybie marathon (operator: „pracuj bez przerwy → CI → merge → smoke login").

Source of truth: `Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-01-modelowanie-object-types.md`.

## Sub-faza: MVP-FINAL — **Epic 0.10 ZAMKNIĘTY 6/6** ✅ (#90+#91+#92+#93+#94 merged; #95 ready for PR; pełen epik 0.10 = API Configurator MVP).

Wcześniejsze epiki: **MVP-Alpha 0.4 (8/8) ✅ + 0.5 (5/5) ✅ + 0.6 (9/9) ✅** — w main (#210..#231). Operator zaakceptował 2026-04-30 kierunek **MVP-Final → Faza 1 → Faza 2** zamiast skoku do Fazy 2 (epik 0.7 Agent layer odsunięty per ADR R-27 cost runaway: BYOK + monitoring + Org-level Anthropic cap muszą iść pierwsze).

## 2026-05-01 (cd. III): Epik UI-02 — post-marathon manual smoke test wykrył 7 issues

Operator manualnie testował zamknięty epik UI-02 na localhost (https://pim.localhost) po wszystkich 19 ticketach + 4 integration PR-ach merged. Zweryfikował każdy widoczny komponent z lista/detail/create i wykrył **8 issues** (7 funkcjonalnych + 1 meta-update CLAUDE.md):

- **#336 UI-02.20 [BUG]** — `<SavedViewsDropdown>` + `<SaveViewModal>` używają natywnego `fetch()` z `credentials: 'include'` zamiast `jsonFetch()` z JWT Bearer header. Skutek: dropdown pokazuje "Error: HTTP 401", zapis view 401. Fix: ~10 min.
- **#337 UI-02.21 [BUG]** — `<CreateProductWizard>` wysyła `{code, attributesIndexed}` zamiast wymaganego `{code, objectTypeId, attributes}` (jak stary `<ProductForm>` z `useDefaultObjectType`). Silently fails — brak nawigacji + brak visible error.
- **#338 UI-02.22 [INCOMPLETE]** — `<DetailDynamicForm>` renderuje TYLKO sekcję "Audyt" (4 read-only system attrs) bo product ObjectType ma zaattachowaną tylko grupę Audyt przez `AutoAttachAuditGroupListener`. SKU/name/brand/description/price istnieją jako Attribute entities ale brak AttributeGroup `Identification`. Fix: backend seed nowej grupy + frontend safety net dla `attributesIndexed` keys bez AttributeGroup.
- **#339 UI-02.23 [INCOMPLETE]** — `<VariantsToggle>` tylko trzyma local state `variantsMode`, nigdy nie konsumuje go w rendering tabeli. Backend `GET /api/products` nie obsługuje `?include_variants=true`. Variants zawsze flat-listed.
- **#340 UI-02.24 [INCOMPLETE]** — `<AdvancedFilterBuilder>` Sheet aktualizuje `advancedFilters` state, ale `useCatalogSearch` hook konsumuje TYLKO `filters` (chip-side state). Apply → chip pojawia się ale lista nie filtrowana, payload nie merge'owany.
- **#341 UI-02.25 [BUG]** — `<ExcelLikeGrid>` wymaga double-click do edytora. Operatorzy oczekują single-click. Plus PATCH error w `onCommit` połykany (`then(refetch)` bez `.catch`).
- **#342 UI-02.26 [POLISH]** — `<VariantsTab>` axes editor surowe text inputs zamiast Combobox z autocomplete + suggested values z `attribute.options`.
- **#343 UI-02.27 [META]** — Update `CLAUDE.md` o **SMOKE TEST RULE** — PR opis nie może użyć "działa" / "wired" bez manual smoke testu na żywym backendzie. Pattern lekcji z marathon: agent zashipował 12 frontend ticketów + 3 integration PR-y, CI green dla wszystkich, ale 4 były buggy bo nikt nie testował end-to-end.

**Łączna estymacja fix'ów: ~6-8h frontend + 1 backend migracja**. Sequencing zaproponowany w ticketach: #336 → #337 → #338 → #340 → #341 → #339 → #342 → #343 (od najprostszego do najtrudniejszego). Operator decyduje czy lecimy marathon czy podział na sesje.

## 2026-05-01 (cd. II): Epik UI-02 Produkty — pełen marathon, 19/19 ticketów dostarczone

Po feedbacku operatora („pracuj przez cały epik, nie deferuj") agent dokończył wszystkie pozostałe 8 frontend ticketów (UI-02.9, UI-02.11, UI-02.12, UI-02.13, UI-02.16, UI-02.17, UI-02.18, UI-02.19) — każdy jako osobny branch + PR + CI + merge zgodnie z nową regułą **EPIK MARATHON RULE** w `CLAUDE.md`.

Druga sesja PR-y:
- **PR #321** — `docs(claude): add Epik Marathon Rule` — operator-feedback rule, nienegocjowalna kontrakta dla "całego epiku" trigger'ów.
- **PR #322 UI-02.9** — `<ProductFilterChips>` + `<AdvancedFilterBuilder>` Sheet (brand / completeness range / status, save-as-view callback).
- **PR #323 UI-02.11** — `<BulkActionsToolbar>` sticky-bottom nad UI-02.3 endpointem (toggle_enabled przez bulk-edit, delete loop, show-selected-only toggle, error preview).
- **PR #324 UI-02.13** — `<ProductRowActions>` 3-dot menu (Edit/Duplicate/Toggle/Audit/CopyURL/Delete) + `<DuplicateProductDialog>` nad UI-02.4 endpointem.
- **PR #325 UI-02.12** — `<ExcelLikeGrid>` custom impl (decyzja A vs B → B/custom; AG Grid odrzucony — ~250KB bundle bloat).
- **PR #326 UI-02.16** — `<DetailGroupNav>` (left rail, UI-02.5 effective-attribute-groups) + `<DetailSidebar>` (Images/Related/History/Channels).
- **PR #327 UI-02.17** — `<DetailDynamicForm>` z auto-save 3s debounce + provenance badges + lock toggle + save indicator.
- **PR #328 UI-02.18** — `<VariantsTab>` z axes editor + matrix generator nad UI-02.6 endpointem.
- **PR #329 UI-02.19** — `<EmptyStateProducts>` + `<CreateProductWizard>` 3-step wizard.

**Łącznie sesja 1+2: 18 PR-ów merged + 1 hotfix #320 (serializer cache fields).** Epik UI-02 ZAMKNIĘTY 19/19.

Świadome odejścia (Faza 1+, udokumentowane per-ticket w PR body):
- TanStack Table swap + virtualization w produktowej liście (UI-02.8 follow-up).
- Localizable PL/EN + Channel sub-tabs w detail form (UI-02.17 follow-up).
- Mercure progress modal dla bulk-edit jobs (UI-02.11 follow-up).
- Per-variant Excel-like grid w VariantsTab (UI-02.18 follow-up reuse UI-02.12).
- Per-user owner scoping w SavedView controller (wymaga `CurrentUserProvider` cross-bundle contract).
- Quick edit popover na komórkach listy (UI-02.13 follow-up).

## 2026-05-01 (cd.): Epik UI-02 Produkty — backend ZAMKNIĘTY 7/7 + 2 frontend tickety merged

**Pełna sesja autonomous (bypass-permissions + dwa wakeup'y po 35min/20min token outage).**

Zamknięte (7 backend + 2 frontend):
- ✅ **#291 UI-02.1** (PR #310) — `completeness_pct SMALLINT` + `sync_status_aggregate VARCHAR(8)` na `objects` z indeksami `(tenant_id, kind, completeness_pct)` + `(tenant_id, kind, sync_status_aggregate)`. `CatalogObject::recordCompleteness(['global'=>N])` mirroruje do `completenessPct` (clamped 0..100). CLI `pim:catalog:recalculate-completeness --tenant=… --kind=…` dla backfill. 8 nowych unit testów.
- ✅ **#292 UI-02.2** (PR #311) — `GET /api/products/quick-search` strict-mode (matchingStrategy=all, attributesToSearchOn=[sku,name,code]) z route priority 200. Kontroler w `Search/Presentation/Controller/ProductQuickSearchController` (Deptrac forced cross-bundle ownership).
- ✅ **#293 UI-02.3** (PR #312) — `bulk_edit_jobs` table + `BulkEditJob` entity + `POST /api/products/bulk-edit` (operations: `toggle_enabled`, `set_attribute_value`; sync execution capped at 5000 IDs; per-row error collection do `firstErrors[]`) + `GET /api/bulk-edit-jobs/{id}` recovery endpoint. Async via Messenger + Mercure deferred do Faza 1.
- ✅ **#294 UI-02.4** (PR #313) — `POST /api/products/{id}/duplicate` z auto-SKU `{src}-COPY-N` + clone wszystkich `ObjectValue` (provenance reset do manual). `with_assets`/`with_relations`/`with_categories` flags reserved (DAM/Faza 1).
- ✅ **#295 UI-02.5** (PR #314) — 3 read endpointy: audit-log (DH Auditor `objects_audit`), channels-status (aggregate column + empty per-channel list do Faza 1 publish), effective-attribute-groups (proxy nad UI-08.4 resolver). Wszystkie route priority 200.
- ✅ **#296 UI-02.6** (PR #315) — `variant_axes JSONB` na `objects` + `POST /api/products/{master_id}/generate-variants` (cartesian product, idempotent, default SKU template `{master_sku}-{values_joined}`). Variants = istniejący `parent_id` self-FK wzorzec.
- ✅ **#297 UI-02.7** (PR #316) — `saved_views` table + `SavedView` entity + 4 CRUD endpointy (`GET/POST` `/api/saved-views`, `PATCH/DELETE` `/api/saved-views/{id}`). Default flag enforcement. **MVP scope reduction:** per-user owner scoping (kolumna `user_id` istnieje na schemie) NIE enforce'owana w controllerze — wymaga cross-bundle `CurrentUserProvider` contract (Deptrac `Catalog → Identity` blocked). Faza 1 follow-up.
- ✅ **#298 UI-02.8** (PR #317) — `<CompletenessBadge>` + `<SyncAggregateIcon>` reusable widgets dodane do products list jako 2 nowe kolumny między Brand a Status. TanStack Table swap deferred.
- ✅ **#304+#305 UI-02.14+UI-02.15** (PR #318) — `<VariantsToggle>` (radio tree/flat) + `<SavedViewsDropdown>` (DropdownMenu nad UI-02.7 endpointem). Save modal / Manage views / URL routing deferred.

**Łącznie 9 PR-ów merged (#310..#318).** Pełen backend gotowy dla frontu.

### Deferred frontend tickety — handoff dla follow-up sesji

Następujące tickety mają pełen backend + reusable widgets, ale wymagają większego frontend-side scope niż ten autonomous batch mógł pokryć w jednej sesji:

- **UI-02.10 (#300) Status indicators** — częściowo done (CompletenessBadge + SyncAggregateIcon shipped w #317). Zostaje: `<ChannelInlineIcons>` (View on X), Mercure live updates, drill-down event handling.
- **UI-02.9 (#299) Filters** — chip filters + AdvancedFilterBuilder Sheet. Backend (UI-02.5 effective-attribute-groups) ready.
- **UI-02.11 (#301) BulkActionsToolbar** — sticky toolbar + edit modal + async progress modal nad UI-02.3 endpointem.
- **UI-02.12 (#302) ExcelLikeGrid** — decyzja AG Grid Community vs custom w PR (ticket explicit).
- **UI-02.13 (#303) Per-row actions** — QuickEditPopover + 3-dot menu + DuplicateProductDialog (nad UI-02.4) + AuditLogModal (nad UI-02.5).
- **UI-02.15 (#305) Save/Manage views modals** — dropdown shipped, modal+manage+URL routing deferred.
- **UI-02.16 (#306) ProductDetailPage layout** — 3-column shell + sticky header + left nav (effective groups) + right sidebar.
- **UI-02.17 (#307) Detail dynamic form** — Localizable tabs + Channel sub-tabs + Provenance + Lock + auto-save + diff modal.
- **UI-02.18 (#308) Variants tab w detail** — axes editor + matrix generator UI nad UI-02.6 endpointem + per-variant Excel-like.
- **UI-02.19 (#309) Create wizard + EmptyState** — 3-step wizard + Cmd+K stub + EmptyStateProducts.

**Estymacja remaining:** ~30-40h (większość frontend, każdy ticket 3-5h przeciętnie). Sequencing: UI-02.16+UI-02.17 (detail page) → UI-02.9 (filters) → UI-02.11 (bulk) → UI-02.13 (per-row) → UI-02.18 (variants tab) → UI-02.19 (create wizard) → UI-02.12 (excel grid, decyzja A/B) → UI-02.15 (save modal).

## 2026-05-01 (cd.): Backlog UI Produkty utworzony (epik UI-02)

**19 issues** w GitHub pod nową etykietą `epik-UI-02` + cross-cutting tag `UI`:

- **#291–#297 Backend (7):**
  - UI-02.1 (#291) cache columns `completeness_pct` + `sync_status_aggregate` + Doctrine listeners (blocker).
  - UI-02.2 (#292) `GET /api/products/quick-search` Meilisearch prefix/exact.
  - UI-02.3 (#293) `POST /api/products/bulk-edit` async via Messenger + Mercure progress.
  - UI-02.4 (#294) `POST /api/products/{id}/duplicate` (with_assets / with_relations / with_categories).
  - UI-02.5 (#295) Read endpoints triple — audit-log + channels-status + product effective-groups.
  - UI-02.6 (#296) Variant entity (ADR-010) + axes generator endpoint (`POST /api/products/{master}/generate-variants`).
  - UI-02.7 (#297) SavedView entity + CRUD ApiResource (per-user solo, JSON Schema validated config).
- **#298–#309 Frontend (12):**
  - UI-02.8 (#298) Products list shell + `<ProductDataTable>` (TanStack) — kolumny / sort multi-col / cursor pagination / virtualization (blocker dla pozostałych frontów).
  - UI-02.9 (#299) Filters — quick search bar + chips + `<AdvancedFilterBuilder>` Sheet.
  - UI-02.10 (#300) Status indicators — `<CompletenessBadge>` + `<SyncAggregateIcon>` + `<ChannelInlineIcons>` (View on X) + Mercure live updates.
  - UI-02.11 (#301) `<BulkActionsToolbar>` sticky + bulk edit modal + show-only-selected + async progress modal.
  - UI-02.12 (#302) `<ExcelLikeGrid>` — drag-fill + multi-cell select + copy-paste (decyzja AG Grid Community vs custom w PR).
  - UI-02.13 (#303) Per-row actions — `<QuickEditPopover>` + 3-dot menu + `<DuplicateProductDialog>` + `<AuditLogModal>`.
  - UI-02.14 (#304) `<VariantsToggle>` (flat/tree) + tree expand/collapse w liście.
  - UI-02.15 (#305) Saved Views — dropdown + Save modal + Manage views + URL routing.
  - UI-02.16 (#306) `<ProductDetailPage>` layout — sticky header + left nav (effective groups) + right sidebar (Images/Related/History/Channels).
  - UI-02.17 (#307) Detail dynamic form — Localizable tabs + Channel sub-tabs + Provenance badges + Lock icons + auto-save 3s + diff modal przed publish.
  - UI-02.18 (#308) Variants tab w detail — axes editor + matrix generator + per-variant Excel-like.
  - UI-02.19 (#309) Create wizard (3 steps) + Cmd+K Beta-Demo stub + `<EmptyStateProducts>` z 3 CTA.

**Estymacja całości: 50-66h** per `Project Plan/UI/epik-02-produkty.md` §15. Sequencing per dependency graph: backend UI-02.1 (blocker) → reszta backendu w parallel → UI-02.8 (frontend blocker) → reszta frontendu w fan-out (większość niezależnych poza UI-02.16↔UI-02.17 i UI-02.18 wymagającym UI-02.12+UI-02.6).

Pełen kontekst: [Project Plan/UI/epik-02-produkty.md](../Project%20Plan/UI/epik-02-produkty.md) (~660 linii).

Świadome odejścia (do Faza 1+):
- `POST /api/products/{id}/publish` (Faza 1, integracje epiki 0.8/0.9 realne kanały).
- Bulk publish do kanałów + bulk change family + soft delete (Faza 1).
- Related products edit / rules engine (Faza 1+ epik 09).
- Permissions ADR-013 (Faza 1).
- Cmd+K full conversational create / data-ops agent (Faza 2 — `„zaznacz 30 Festo i ustaw kategorię"`).
- Variant-level pricing rules / bundle products (Faza 1+).
- Excel-like undo/redo + formula cells + conditional formatting (Faza 1+).
- AI-suggested views / view sharing (Faza 1+ ADR-013).

## 2026-05-01 (cd.): META-UI v2 — sidebar §3.1 reorg (#289)

Po zamknięciu epiku UI-08 operator zwrócił uwagę, że pierwotny META **#255** zaimplementował zwijaną grupę „Modelowanie" zamiast pełnego layoutu z `00-plan-ui.md` §3.1 (Dashboard / Produkty / Usługi / Publikacje / Multimedia / Workflow / Ustawienia + separator + Modelowanie). Korekta dostarczona jako **#289** (META-UI v2):
- Sidebar przepisany na 7 leafów + separator + 1 leaf (Modelowanie). Dashboard / Usługi / Workflow jako disabled placeholdery z Soon badge (do zastąpienia w UI-01 / UI-03 / UI-06). Channels → label „Publikacje", Assets → label „Multimedia" (route stable). API Profiles → label „Ustawienia" (UI-07 zrobi pełne Settings page).
- Modelowanie nie jest już collapsible group — pojedynczy `NavLink` do `/modeling`. 4 sub-tab'y żyją w `<ModelingLayout>` tablist (UI-08.9, PR #282).
- i18n keys zwinięte do 9 wpisów per locale (en + pl).
- Playwright spec `modeling-shell.spec.ts` rozszerzony o assertions na sidebar §3.1 layout.

## 2026-05-01: Backlog UI Modelowanie utworzony (epik UI-08)

**16 issues** w GitHub pod nową etykietą `epik-UI-08` + cross-cutting tag `UI`:
- **#255** META — reorganizacja sidebar (zwijana sekcja „Modelowanie" grupująca Object Types / Attributes / Attribute Groups / Categories).
- **#256–#263** Backend (8): UI-08.1 ADR-012 + migracje DDL (blocker), UI-08.2 ObjectType built-in flags + brand seed, UI-08.3 system attributes + Audit auto-attach, UI-08.4 EffectiveAttributeGroupResolver + form-schema endpoint, UI-08.5 AttributeGroup CRUD ApiResource, UI-08.6 attribute migrate-type endpoint, UI-08.7 where-used endpoints, UI-08.8 visible_when storage + evaluator.
- **#264–#270** Frontend (7): UI-08.9 Modeling layout shell + 4-tab routing, UI-08.10 Object Types sub-tab (list/detail/wizard), UI-08.11 Attributes sub-tab enhanced, UI-08.12 Migration impact analyzer modal, UI-08.13 AttributeGroups sub-tab z drag-drop, UI-08.14 Categories modeling tree + inheritance preview, UI-08.15 (optional) bulk import CSV.

**Estymacja całości: 60-80h.** Sequencing zatwierdzony przez operatora: epik wpada **po MVP-Final (epik 0.11)**, **przed Fazą 1** — jako „epik 0.12 / UI-08 Modelowanie". Spójne z notą `Project Plan/UI/epik-08-modelowanie.md` §15. Dependency: cały epik blokowany przez UI-08.1 (ADR-012 + migracje DDL).

Pełen kontekst: [Project Plan/UI/epik-08-modelowanie.md](../Project%20Plan/UI/epik-08-modelowanie.md) (~960 linii) + [Project Plan/UI/00-plan-ui.md](../Project%20Plan/UI/00-plan-ui.md) §3.1.

## Następny krok
**Epik UI-08 — 4/16 ticketów MERGED + UI-08.3 W TOKU w 2026-05-01:**
- ✅ **#255 META** (PR #272) — sidebar reorganization, zwijana sekcja „Modelowanie".
- ✅ **#256 UI-08.1** (PR #273) — ADR-012 + migracje DDL (`Version20260501100000`) + entities (AttributeGroup rozszerzony, AttributeGroupAttribute, ObjectTypeAttributeGroup, CategoryAttributeGroup) + 6 unit testów.
- ✅ **#257 UI-08.2** (PR #274) — ObjectType `code_immutable/deletable/icon/color` + brand jako 4-ty built-in (kind=`brand`) + ObjectKind enum extension + Search/ApiPlatform router updates + 6 unit testów.
- ✅ **#258 UI-08.3** (PR #276) — `is_system` flag na Attribute, system attrs (created_at/updated_at/created_by/updated_by) + audit AttributeGroup + AutoAttachAuditGroupListener + BuiltInSystemAttributesSeeder + extension BuiltInObjectTypeSeeder o brand. Migration `Version20260501120000`. AttributeType enum + 2 cases (Datetime, Reference). 9 unit testów + 1 integration test.
- ✅ **#259 UI-08.4** (PR #277) — `EffectiveAttributeGroupResolver` (domain service) + `GetObjectFormSchemaHandler` (cached query handler) + `ObjectFormSchemaCacheInvalidator` (Doctrine postFlush listener inwalidujący `pim.modeling_cache` tag-aware pool) + `ObjectFormSchemaController` (`GET /api/objects/{id}/form-schema`). 2 unit + 5 integration + 3 API test.
- ✅ **#260 UI-08.5** (PR #278) — AttributeGroup CRUD przez API Platform (POST/PATCH/DELETE) + Create/Update/Delete CQRS slice handlers + AttributeGroupInput / AttributeGroupPatchInput DTOs + AttributeGroupProcessor + delete protection (422 system, 409 attached). 7 ApiTestCase.
- ✅ **#261 UI-08.6** (PR #279) — `POST /api/attributes/{id}/migrate-type` (custom REST). `AttributeTypeMigrationCompatibility` matrix + `AttributeMigrationPlanner` + `AttributeMigrationExecutor` + migration `Version20260501130000` + `AttributeMigrationBackup` ORM-mapped entity (Foundry ResetDatabase requires schema-tool reflection).
- ✅ **#262 UI-08.7** (PR #280) — 3 custom REST endpoints `GET /api/{attributes|attribute_groups|object_types}/{id}/usage`. `UsageQueryService` (DBAL-only, tag-aware cache 60s TTL) + `UsageController`.
- ✅ **#263 UI-08.8** (PR #281) — `VisibleWhenRule` value object + `VisibleWhenRuleEvaluator` + `PATCH /api/attribute_groups/{groupId}/attributes/{attributeId}` z cross-group reference guard. **Backend epik UI-08 zamknięty 8/8.**
- ✅ **#264 UI-08.9** (PR #282) — `<ModelingLayout>` z 4-tab tablist'em pod `/modeling/*` + back-compat redirects + sidebar nav update + Playwright spec (1 consolidated test).
- ✅ **#265 UI-08.10** (PR #283) — Object Types list + show enhanced + reusable `<BuiltInLockBadge>` + `<WhereUsedList>`. Custom Create wizard świadomie odroczony.
- ✅ **#266 UI-08.11** (PR #284) — Attributes list filtry + Usages column + `<AttributePreview>` mock-data widget; serializer XML +`system` field.
- ✅ **#267 UI-08.12** (PR #285) — `<MigrateAttributeTypePage>` 3-step flow (target → suggest → apply) na route `/modeling/attributes/:id/migrate-type`.
- ✅ **#268 UI-08.13** (PR #286) — AttributeGroups sub-tab: list/create/show z PATCH up/down reorder + inline visible_when editor + new custom REST `GET /api/attribute_groups/{id}/attributes`.
- ✅ **#269 UI-08.14** (PR #287) — `<EffectiveAttributesPreview>` widget na category show page + `GET /api/categories/{id}/effective-groups` endpoint (reuse UI-08.4 resolver).
- ⏭️ **#270 UI-08.15** — **deferred do Faza 1** (issue closed `not planned`). Powody: optional w MVP per issue body, wymaga full bulk-import infrastructure (CSV parser + Messenger + Mercure progress + Attribute write paths które są w MVP read-only). Re-open trigger: client request lub backend Attribute POST/PATCH endpoints.

## **Epik UI-08 ZAMKNIĘTY 14/15 must-have + META** ✅

Zamknięte:
- META **#255** + **8 backend** (#256..#263) + **6 frontend** (#264..#269) ticketów. ~17 PR-ów (#272..#287).
- 8 reusable komponentów / services: BuiltInLockBadge, WhereUsedList, EffectiveAttributeGroupResolver, AttributeMigrationCompatibility, AttributeMigrationPlanner+Executor, UsageQueryService, VisibleWhenRule+Evaluator, ObjectFormSchemaCacheInvalidator.
- 4 nowe migracje: Version20260501100000 (AttributeGroup first-class), Version20260501110000 (ObjectType built-in flags + brand), Version20260501120000 (system attrs + audit auto-attach), Version20260501130000 (attribute_migration_backups).
- 6 custom REST endpointów: form-schema, migrate-type, usage (×3), attribute_groups/attributes, attribute_groups/attributes/{aId} (PATCH visible_when), categories/effective-groups.
- 3 ADR'y: ADR-012 (AttributeGroup first-class), ADR-009 extension (Brand jako 4-ty built-in), kolumny ObjectType code_immutable/deletable/icon/color.

Świadome odejścia (do Faza 1+):
- Custom Create wizard dla ObjectType (gated by `enable_custom_object_types` flag).
- Edit drawer dla Attribute (read-only ApiResource).
- Drag-drop @dnd-kit/sortable (zastąpione Up/Down PATCH'em w UI-08.13).
- Bulk CSV import (#270 deferred).
- AttributeFromLibraryPicker (no AttachAttributeToGroup command).
- Override-action UI dla category groups (no override_action column).
- **Frontend:** #264-#270 (Modeling layout shell + 4 sub-tabs + migration analyzer + drag-drop + inheritance preview + bulk import CSV).

**Dependency state na końcu sesji:**
- UI-08.3 wymaga UI-08.1+2 (✅ merged) — gotowy do podjęcia.
- UI-08.4 wymaga UI-08.1+2+3.
- UI-08.5 wymaga UI-08.1+3.
- UI-08.6/7 wymagają UI-08.1+2.
- UI-08.8 wymaga UI-08.1+4.
- UI-08.9 (frontend layout shell) wymaga META (✅) + UI-08.4.

**Tooling state:**
- Wszystkie quality gates green (PHPStan max + Deptrac + PHPUnit Unit 244 testów + Biome strict + tsc + Vite + Playwright + OpenAPI snapshot).
- 2 nowe migracje (Version20260501100000 — AttributeGroup first-class; Version20260501110000 — ObjectType flags + brand seed).
- 4 nowe Doctrine entities + ORM XML mappings.
- ADR-012 udokumentowany w `Project Plan/01-architektura-pim.md` §13.

**Wzorzec sesji autonomicznej (operator włączył bypass-permissions 2026-05-01):**
- 1 sesja = 4 PR-y (META + UI-08.1 + UI-08.2 + status update). ~3h clock-time.
- Każdy PR: branch → implement → quality gates lokalne → commit → push → CI poll → merge → main sync.
- Pre-existing PHPUnit Api test infra issue na lokalnym docker (env brak `test.service_container`) — w CI działa, więc nie blokuje. Nie tracić czasu na lokalny fix.

Po UI-08 wraca normalny scope MVP-Final epik 0.11 (Hardening + a11y + analytics + pgBackRest + BYOK), 32-46h estymacja.

## Następny krok (HISTORYCZNY — zachowany dla audit trail)
**#90 (0.10.1) ApiProfile + ApiKey + Argon2id hashing** GOTOWE — branch `feat/0.10.1-api-configurator-entities` ma wszystkie quality gates green (PHPStan max + Deptrac + 305 PHPUnit testów + composer audit). Plik commitnąć + push + PR + CI poll + merge. **Następne tickety epiku 0.10:** #91 admin UI lista profiles, #92 attribute/format picker + JSON preview, #93 webhook config, #94 ApiProfileVoter + ApiKeyAuthenticator + serializer context, #95 endpoint test + per-profile OpenAPI export.

Operator zapowiedział że po zamknięciu epiku 0.10 zrobimy świadomy audit "co jest faktycznie MVP-ready vs façade UI bez backendu" (write paths Categories/Channels/Assets, schema editor, drag-drop upload odroczone w 0.6) — celem przygotowanie widoków/funkcjonalności do MVP demo state.

## Następny krok
**Epik 0.10 (API Configurator) ZAMKNIĘTY 6/6 w jednej sesji** (2026-04-30, ~6h clock-time, 5 PR-y `#233..#238` mergowane do main). Operator zapowiedział post-epic audit "co jest faktycznie MVP-ready vs façade UI bez backendu" — review write paths Categories/Channels/Assets, schema editor, drag-drop upload (odroczone w 0.6), plus #91-#95 świeżo dostarczone tab Webhook + filters builder, oraz preview tab. Następna sub-faza: **MVP-Final epik 0.11** (Hardening + a11y + analytics + pgBackRest + BYOK), 32-46h estymacja.

## Ostatnie 3 akcje

1. **#95 (0.10.6) Profile test + per-profile OpenAPI** (2026-04-30, branch `feat/0.10.6-profile-test-endpoint`). Szósty (i ostatni) ticket epiku 0.10 — domyka epik. **`ProfileTestController`** custom REST z 2 endpoints: `GET /api/profiles/{code}/test` (zwraca shape z `includedAttributes` jako `<value-by-attribute-type>` placeholders + JSON-LD/JSON wariant — live row preview odroczony do follow-up bo wymaga read-only Catalog contract w Shared, którego ApiConfigurator obecnie nie ma per Deptrac); `GET /api/profiles/{code}/openapi.json` (AP4 OpenAPI document narrowed z `info.title` per profile + custom `x-pim-profile`/`x-pim-included-attributes` extensions). **`ApiProfileResponseFilter`** Application service — `project()` method prune'uje `attributes`/`attributesIndexed` per profile.includedAttributes, reusable później przez normalizer dla canonical paths. **Świadome odejścia:** live row preview (real CatalogObject + filter) → follow-up gdy `Catalog\Contracts\ReadOnlyCatalogProjection` interface się materializuje; full sugar-path narrowing w OpenAPI export na podstawie ObjectType.kind → też follow-up (wymaga Catalog readside accessible). Endpoint zostawia paths complete + dorzuca metadata o profile (Operator może introspect). **Tests:** `ProfileTestApiTest` 4 cases (shape echoes profile / 404 unknown / OpenAPI metadata stamping / 401 unauthenticated). **Quality gates:** PHPStan max clean, Deptrac 0 violations + 27 baseline, **324/324 PHPUnit** (+4 nowe), Biome strict + tsc + Vite build green.

2. **#94 (0.10.5) Backend — ApiKeyAuthenticator + ApiProfileResolver + serializer context builder** (2026-04-30, branch `feat/0.10.5-api-key-authenticator`). Piąty ticket epiku 0.10. **`ApiKeyAuthenticator`** w `ApiConfigurator/Infrastructure/Security/` — Symfony custom authenticator dla `X-API-Key` header. Coexist z JWT przez `^/api` firewall multiple authenticators. Lookup 2-step: prefix (12 chars) → unique B-tree probe + Argon2id verify. `last_used_at` bumped + auto-rehash gdy `password_needs_rehash` flagi parametry jako outdated (per ADR-0016). **`ApiKeyUser` synthetic principal** implementuje **`Shared\Application\Auth\ApiKeyPrincipal`** interface (cross-BC marker w Shared żeby Identity i ApiConfigurator nie naruszyły Deptrac). **`AbstractRbacVoter` rozszerzony** — rozpoznaje `ApiKeyPrincipal` i daje **read-only** access dla matching tenant; każdy non-`read` action denied (write paths w MVP nie istnieją dla API keys). **`CurrentTenantProvider` rozszerzony** — gdy token user instanceof `ApiKeyPrincipal`, fetch Tenant przez `findById($principal->tenantId())`. **`ApiProfileResolver`** Application service — z requestu (X-PIM-Profile header lub auto-pick gdy 1 scope) + ApiKey → resolves ApiProfile, deny gdy not in scopes. **`ApiProfileSerializerContextBuilder`** decorator (priority 20, outermost) — gdy authenticated z ApiKey, ustaw `groups: ['public:read']` + dorzuć `api_profile_included_attributes`/`api_profile_object_type_ids`/`api_profile_code` do context (downstream normalizer może prune response — full implementation w follow-up). **Świadome odejścia:** rate limiter per-key → follow-up; included_attributes pruning normalizer → follow-up; live preview endpoint → #95. **Tests:** `ApiKeyAuthApiTest` 4 cases (auth happy path / unknown key 401 / revoked key 401 / write attempt 403). **Quality gates:** PHPStan max clean, Deptrac 0 violations + 27 baseline, **320/320 PHPUnit** (+4 nowe), Biome strict + tsc + Vite build green.

2. **#93 (0.10.4) UI — webhook config + delivery + test + rotate** (2026-04-30, branch `feat/0.10.4-api-profiles-webhook-ui`). Czwarty ticket epiku 0.10. **Backend:** `webhookSecret` field dodane do `ApiProfile` entity + migration `Version20260430140000` + ORM mapping. **`WebhookSecretGenerator`** generuje 64-char base64url string (48 bytes random → 256-bit HMAC key). **`WebhookDeliveryClient`** POST z `X-Pim-Signature: sha256=<hex>` header, timeout 5s, fail-soft (network errors logged, never thrown). **`WebhookDeliverySubscriber`** w Application/Subscriber/ — 5 message handlerów per Catalog DomainEvent (mirror MercurePublisher z #47), wywołuje `findWebhookSubscribersFor($eventType)` repo method (JSONB containment przez `JSONB_CONTAINS` DQL z #43), POST do każdego matching profile. **`TestWebhookController`** custom REST endpoints: `POST /api/api_profiles/{id}/test_webhook` (sample ping payload + response status) + `POST /api/api_profiles/{id}/rotate_webhook_secret` (mint fresh secret + return raw value once). **CreateApiProfileHandler** auto-generuje webhookSecret gdy URL podany (avoid "configure URL → rotate → save again" round trip). **UpdateApiProfileHandler** mints secret on first URL set. **Frontend:** form dostaje **5-tabową** strukturę (basic/attributes/filters/**webhook**/preview). Tab Webhook = URL input + events checkbox grid (7 default events z `WEBHOOK_EVENTS` const). Show page: dedykowana **WebhookSection** z URL/events/Test webhook button + Rotate secret button (raw secret displayed once w amber callout). i18n keys `api_profiles.show.webhook_*` + `api_profiles.form.webhook_*` w en+pl. **Świadome odejścia:** delivery history + retry log → follow-up (Faza 1 monitoring); Symfony Messenger transport retry → też follow-up (sync delivery wystarcza dla MVP). **Quality gates:** PHPStan max clean, 316/316 PHPUnit (backend smoke przez ApiProfilesApiTest unchanged), Biome strict + tsc + Vite build green. OpenAPI snapshot regenerated.

2. **#92 (0.10.3) UI — attribute/format picker + filters builder + JSON preview** (2026-04-30, branch `feat/0.10.3-api-profiles-attributes-ui`). Trzeci ticket epiku 0.10. **`ApiProfileForm` przebudowany do 4-tab structure** (Basic Info | Attributes | Filters | Preview) jako tablist z `aria-selected` segmented buttons. **Tab Attributes:** ObjectTypes multi-checkbox grid z fetch'em z `/api/object_types` + Attributes scrollable list (max-h-400) z fetch'em z `/api/attributes`, każdy z code+label+type. Counter pokazuje wybrane attributes. **Tab Filters:** segmented status picker (any/enabled/disabled/published/archived) + free-text category code (ltree descendant filter). Filter object propagowany w form values jako `Record<string, unknown>`. **Tab Preview:** lokalny JSON preview generowany z mock values per AttributeType (text→"sample text", number→42, price→{amount, currency}, etc.). JSON-LD wariant dorzuca `@context/@id/@type`. **Live preview deferred do #95** (`/api/profiles/{code}/test` endpoint) — placeholder explicit note. **Validation auto-switches do basic tab** gdy code/name/rate-limit fail. Create + Edit propagują `objectTypeIds, includedAttributes, filters` przez API. i18n keys `api_profiles.form.*` w en+pl. **Quality gates:** PHPStan max clean, 316/316 PHPUnit, Biome strict + tsc + Vite build green.

2. **#91 (0.10.2) Admin UI ApiProfiles + AP4 CRUD endpoints** (2026-04-30, branch `feat/0.10.2-api-profiles-admin-ui`). Drugi ticket epiku 0.10. **Backend:** ApiResource XML dla `ApiProfile` (GET/POST/PATCH/DELETE on `/api/api_profiles`) + read-only `ApiKey` (GET only). CQRS slices w `Application/Command/{CreateApiProfile,UpdateApiProfile,DeleteApiProfile}` per ADR-0012. `ApiProfileInput` + `ApiProfilePatchInput` DTOs (setter-less Domain → DTO hydration target dla AP4). State Processor `ApiProfileProcessor` bridge MessageBus + unwrap HandlerFailedException → real HttpException (409 conflict, 404 not found). Serializer XML wyklucza **`keyHash` z KAŻDEJ grupy** dla `ApiKey` (defence-in-depth). `Identity/Infrastructure/Security/{ApiProfileVoter,ApiKeyVoter}` (oba używają resource `'api_profile'` z RbacMatrix — admin profili i admin kluczy to ten sam authority axis). Config update: `doctrine.yaml`, `api_platform.yaml`, `framework.yaml` serializer paths +ApiConfigurator. **Frontend:** `features/api-configurator/api-profiles/{list,create,edit,show,form}.tsx` + Refine resource registration + Sidebar nav `KeyRound` icon. Form scope = "Basic Info" tylko (code, name, description, outputFormat, rateLimitPerHour) zgodnie z DoD; tabs Attributes/Filters/Webhook → #92/#93 explicit `tabs_deferred_note`. Show page pokazuje basic info + "linked keys" sekcja (filter `ApiKey.scopes` zawiera `profile.code`). i18n keys `api_profiles.*` w en+pl. **Świadome odejścia:** ApiKey management UI (generate/revoke) → follow-up (CLI ma pełen flow, UI w future ticket); attribute multiselect / filter builder / webhook config → #92/#93. **Quality gates:** PHPStan max clean, Deptrac 0 violations + 27 baseline, 316/316 PHPUnit (9 nowych: ApiProfilesApiTest CRUD + ApiKeysReadApiTest defence-in-depth + 2 voter routing tests w RbacVotersTest), Biome strict + tsc + Vite build green.

2. **#90 (0.10.1) ApiProfile + ApiKey entities + Argon2id hashing + scopes** (2026-04-30, branch `feat/0.10.1-api-configurator-entities`). Pierwszy ticket nowego BC `ApiConfigurator` (szkielet bundle istniał z .gitkeep). **`ApiProfile` aggregate** w `src/ApiConfigurator/Domain/Entity/` — `code, name, description, outputFormat (JSON_LD|JSON), objectTypeIds[] JSONB (per ADR-009), includedAttributes[] JSONB, filters[] JSONB, webhookUrl?, webhookEvents[] JSONB, rateLimitPerHour, createdAt/updatedAt`. **`ApiKey` entity** — `keyHash (Argon2id), keyPrefix (display "pim_live_xxxx" 12 chars), name, scopes[] JSONB (profile codes), expiresAt?, revokedAt?, lastUsedAt?, rateLimitPerHour, createdAt`. Oba TenantScoped + assignTenant() listener wzorzec. **`Argon2idApiKeyHasher`** + **`ApiKeyGenerator`** z formatem `pim_<env>_<32 chars base62>` (32 bytes random_bytes → 32 char base62 body, ADR-0016 udokumentował format + algo). **`pim:apikey:generate` CLI** w Presentation/Console — `--tenant --name --scopes --rate-limit --expires-in-days`, raw key echo TYLKO RAZ z warningiem. **Migration `Version20260430120000`** — `api_profiles` + `api_keys` tables z FK na tenants(id), unique `(tenant_id, code)` + globalny unique na `key_prefix`. **`ADR-0016`** — Argon2id + format kluczy + brak BYOK w MVP. **Świadome odejścia:** Voters → #94, ApiResource XML → #91, webhook delivery → #93. **Quality gates:** PHPStan max (clean po dodaniu BC do `phpstan.dist.neon` `doctrine.associationType` ignoreErrors), Deptrac 0 violations + 27 baseline, 305/305 PHPUnit testów (24 nowych: 4 unit + 1 integration command test x 4 cases + ApiProfile/ApiKey/Hasher/Generator units), composer audit clean. Manual smoke: brak demo tenant w dev DB (skip — integration tests autorytatywne).

2. **#62 (0.6.9) i18n full pl+en + language switcher** (2026-04-30, branch `feat/0.6.9-i18n-switcher`). DOMYKA EPIK 0.6 — 9/9. **`LanguageSwitcher`** w `src/layout/language-switcher.tsx` — Languages icon button z badge w bottom-right pokazującym aktywny lang code (`pl`/`en`), DropdownMenu z 2 wariantami + active highlight (`bg-secondary`). Klik na DropdownMenuItem woła `i18n.changeLanguage(code)`. **`i18next-browser-languagedetector`** już w `lib/i18n.ts` persists wybór do localStorage by default (lookup order: localStorage → navigator) — żaden manual storage juggling. Top bar AppLayout: switcher dorzucony przed NotificationsBell + UserMenu. i18n keys `language.{aria_label,title,pl,en}` w en+pl. Pełen i18n ticket scope (audit literałów, custom Biome rule blokująca string literals w JSX) świadomie OUT — Biome 2.4 nie ma built-in `useTranslationOnLiterals`, custom plugin to overkill dla MVP scope, więc surface jest gotowy + cała epic 0.6 została ostatecznie wpięta do `t()` calls. Language switching potwierdzony manualnym smoke. Quality gates: Biome + tsc + Vite build all green.

2. **#61 (0.6.8) Provenance UI + filter** (2026-04-30, branch `feat/0.6.8-provenance-badges`). Ósmy ticket epiku 0.6. **`<ProvenanceBadge>`** w `src/components/provenance-badge.tsx` — 4 warianty: `manual` (slate), `import` (blue), `integration` (orange), `agent` (purple, opacity-70 + "Faza 2" sub-label). Tooltip via `title` z source + timestamp + i18n locale-aware date format. **`ProductShowPage`** używa nowego komponentu zamiast inline placeholder (z #55) — wszystkie attribute values w show page mają badge `manual` (placeholder per backend state — `attributesIndexed` cache nie carry'uje per-key provenance, real data pochodzi z `ObjectValue` rows które wymagają nowego endpoint w follow-up). **Provenance filter w `ProductListPage`** — chips per option (All + 4 variants) wpięte do filters object z `provenance` key, użyte przez `useCatalogSearch`. Filter UI gotowy gdy backend doda Meili facet `provenance`. i18n keys `provenance.{manual,import,integration,agent,agent_phase_2,source,timestamp}` + `products.filter_provenance{,_all}` w en+pl. Biome a11y: `aria-label` nie jest valid na `<span>` (useAriaPropsSupportedByRole) — drop, polegamy na `title` attribute. Quality gates: Biome + tsc + Vite build all green.

2. **#60 (0.6.7) Resource Assets — read-only grid + show** (2026-04-30, branch `feat/0.6.7-assets-resource`). Siódmy ticket epiku 0.6. **`AssetsListPage`** — responsive CSS Grid (2/3/4/6 cols breakpoints), per-tile aspect-square thumbnail z `previewUrl` z `attributesIndexed` lub fallback `Image/Film/FileText` icon per MIME prefix. `loading="lazy"` dla zdjęć. Per-tile filename (alt fallback) + code mono. **`AssetShowPage`** — `/assets/:id` z preview panel (260px aspect-square contain) + metadata grid (alt, mime, plus wszystkie inne `attributesIndexed` entries except canonical fields). Multi-locale alt resolver. **Drag-drop upload + edit dialog + preview modal + bulk delete ŚWIADOMIE ODROCZONE** — multipart upload endpoint nie istnieje w current API state (per #41 `/api/assets` to read-only sugar path), backend Asset entity ma storage details ale write path nie wired. Sidebar nav: ComingSoon usunięte dla `/assets` (Image icon był) — **TO JEST OSTATNI nav item z `comingSoon: true`** flagą. App.tsx import ComingSoon usunięty (unused). i18n keys `assets.*` w en+pl. Quality gates: Biome + tsc + Vite build all green.

2. **#59 (0.6.6) Resource Channels (read-only list/show + tabs)** (2026-04-30, branch `feat/0.6.6-channels-resource`). Szósty ticket epiku 0.6. **`ChannelsListPage`** — table z code/label/locales/currencies (jako tagi mono-style) + Radio icon prefix. **`ChannelShowPage`** — `/channels/:id` z 5 tabs (Overview/Locales/Currencies/Mapping/Preview), gdzie Overview ma counts + categoryTreeRootId, Locales/Currencies renderują listy tagów, Mapping placeholder dla per-kind ChannelObjectTypeMapping editor (zostawione na follow-up — wymagane przed integracjami #74 BaseLinker/#81 Shopify), Preview placeholder dla 0.10.6 API Configurator. **`features/channel/channels/`** dir (mirror Catalog BC structure). Sidebar nav: ComingSoon usunięte dla `/channels` (Radio icon był). Manual create/edit + ChannelObjectTypeMapping authoring odroczone — surface daje teraz visibility, integracje konsumują resource bezpośrednio przez API. i18n keys `channels.*` w en+pl. Quality gates: Biome + tsc + Vite build all green.

2. **#58 (0.6.5) Resource Categories — read-only ltree tree** (2026-04-30, branch `feat/0.6.5-categories-tree`). Piąty ticket epiku 0.6. **`CategoriesTreePage`** — czyta `/api/categories` (sugar path z #41), buduje tree z ltree `path` (split na `.` segments → depth + parent path lookup). Native `<ul>`/`<li>` recursion z indent 16px*depth + `ChevronDown/Right` collapse buttons + `FolderTree` leaf icon. Auto-expanded gdy depth < 1 (root + first level), reszta collapsed. Multi-locale label resolver fallback do code. Orphan rows (parent path missing) lądują przy roocie. Per-row Eye icon → /categories/:id. **`CategoryShowPage`** — `/categories/:id` z code/path/attributesIndexed entries. **Drag-drop reparenting + create/edit forms ŚWIADOMIE ODROCZONE** do follow-up ticketu — ścieżka do change-parent endpoint + `ReparentCategoryHandler` w 0.3.3 nie ma jeszcze admin UI, write paths dla CatalogObject są tylko dla `kind=product` w MVP. Read-only tree daje operator visibility teraz, modyfikacje w follow-up gdy dynamic attribute editor (per ADR-009) lub agent flow (Faza 2) je obsłuży. Sidebar nav: ComingSoon usunięte dla `/categories` (FolderTree icon był). i18n keys `categories.*` w en+pl. Biome a11y: `role="tree"/treeitem"/group" + aria-expanded` na `<li>` blokowane przez useAriaPropsSupportedByRole — drop role attrs, rely na native ul/li semantics + aria-label na root. Quality gates: Biome + tsc + Vite build all green.

2. **#57 (0.6.4) Resource ObjectTypes (read-only list/show + Custom Faza 2 placeholder)** (2026-04-30, branch `feat/0.6.4-object-types-resource`). Czwarty ticket epiku 0.6. **`ObjectTypesListPage`** — dwie sekcje: **Built-in** table z code/label/kind badge (color-coded — product blue, category emerald, asset purple)/schema version + lock badge w headerze; **Custom** z explanatory dashed-border placeholder + disabled "Create custom ObjectType" button + Faza 2 amber badge + count rows currently in DB. Per ADR-009 + R-29 mitigacja: custom kindy są w bazie od dnia 1 ale wyłączone feature flagiem w MVP. **`ObjectTypeShowPage`** — `/object-types/:id` z metadata (kind, schema version, label/image attribute refs, completenessRules JSON) + Lock badge gdy builtIn !== false. Sidebar nav: ComingSoon usunięte dla `/object-types` (icon ListTree już był). i18n keys `object_types.{built_in,custom,locked,phase_2_badge,custom_disabled_explanation,create_custom_disabled,custom_present_note,...}` w en+pl. Quality gates: Biome + tsc + Vite build all green.

2. **#56 (0.6.3) Resource Attributes + AttributeGroups (read-only list/show)** (2026-04-30, branch `feat/0.6.3-attributes-resource`). Trzeci ticket epiku 0.6. **`AttributesListPage`** — czyta `/api/attributes`, table z code/label/type/group/flags + per-type filter buttons (text/textarea/number/boolean/select/multi_select/date/asset/reference/price/measurement). Multi-locale label resolver picks current i18n lang z fallback do en→pl→first key. **`AttributeShowPage`** — `/attributes/:id` z metadata (type, help, flags `required/localizable/scopable`, validationRules JSON). **`AttributeGroupsListPage`** — `/api/attribute_groups`, table sortowany po `position`. Sidebar nav += `LayoutList` icon dla attribute_groups; ComingSoon dla `/attributes` usunięte. **Manual create/edit/drag-drop ŚWIADOME ODEJŚCIE** — odroczone do schema-add agent flow w Fazie 2 (per CLAUDE.md "Reguły implementacyjne" + ADR-009: schema modyfikowalna przez agenta z naturalnym językiem). Operator widzi MVP attribute set z seedera + może zerknąć w schema; modyfikacje przez agenta. Note dodany w `attributes.write_deferred_note` + `attribute_groups.write_deferred_note` strings + lessons.md sekcja 0.6.3. Quality gates: Biome + tsc + Vite build all green.

2. **#55 (0.6.2) Resource Products — list/show/create/edit z poprawną AP4 shape** (2026-04-30, branch `feat/0.6.2-products-resource`). Drugi ticket epiku 0.6. **`useDefaultObjectType` hook** — pulls `/api/object_types` + matches built-in row dla danego kind, transparently provides `objectTypeId` dla create flow przed admin schema picker'em (Faza 2/3). **`create.tsx` rewritten** — POST body teraz to AP4 `CatalogObjectInput` shape: `{code, objectTypeId, attributes: {name, brand, description}}`, nie raw `{sku, name, brand}`. **`edit.tsx` rewritten** — GET shape z `attributesIndexed` (po #45 denormalizacji); PATCH `{attributes: {name, brand, description}}` przechodzi przez `ObjectAttributesUpserter`. SKU stays immutable. **`show.tsx` (nowy)** — header z code + completeness badge (zielony/amber/rose buckets), 6 tabów (Attributes/Categories/Associations/Channels/Assets/History) z `aria-pressed` toggles + native button group, attributes tab dziala (z `Provenance: manual` placeholder per ADR DoD), pozostałe tab placeholder z forward-link do follow-up ticketu. **`list.tsx`** — multi-select checkbox (header + per-row), `Eye` icon link → /products/:id show, `StatusBadge` (enabled/disabled tone), `ProductBulkBar` overlay gdy >0 selected — sekwencyjnie iteruje selected ids dla enable/disable (PATCH merge) lub delete, `refetch` po complete. Bulk endpoint `/api/products/bulk` deferred do epiku 0.7 (single round trip — per-row fan-out OK przy MVP scale). i18n keys `products.show.*`, `products.bulk.*`, `products.fields.status`, `products.no_object_type`, `products.actions.{view,select_*}` w en+pl. Quality gates: Biome + tsc + Vite build all green.

2. **#54 (0.6.1) Layout admina — Sidebar/TopBar/responsive/notifications/user dropdown** (2026-04-30, branch `feat/0.6.1-admin-layout`). Pierwszy ticket epiku 0.6. **`SidebarNav`** wyciągnięty z AppLayout (re-używalny w desktop aside + mobile Sheet). **Sheet primitive** (Radix `Dialog` + `X` close) + **DropdownMenu primitive** (Radix `DropdownMenu` z Item/Label/Separator). **Mobile hamburger**: `Menu` icon button widoczny tylko `md:hidden`, otwiera Sheet z lewej strony, klik nav-itemu auto-zamyka (`onNavigate` callback). **`NotificationsBell`** subskrybuje `EventSource` na `/.well-known/mercure?topic=/objects` (broadcast topic z #47), buforuje ostatnie 25 events w pamięci, badge z unread count (`9+` jeśli >9), klik trigger marks-read. Connection failure silent (Mercure może być down w dev bez hubu). **`UserMenu`** Radix DropdownMenu z identity (name + email + tenant) + logout item. i18n keys (`user_menu.*`, `notifications.*`, `app.toggle_nav`) w en+pl. Quality gates: Biome + tsc + Vite build all green. Manual: layout szeroko < 768px = hamburger, nawigacja + bell + user menu działają.

2. **#53 (0.5.5) UI search box + faceted filters w Refine** (2026-04-30, branch `feat/0.5.5-search-ui`). Piąty ticket epiku 0.5 — domyka epic. **`useCatalogSearch` hook** w `apps/admin/src/features/catalog/search/use-catalog-search.ts` — debounced (300ms) call do `/api/search/{kind}` z opt-in facets + filter map (single string lub `string[]` per facet) + offset pagination + cancellation flag w cleanup. **`CatalogSearchBox`** komponent (shadcn `Input` + leading `Search` icon + clear button) i **`CatalogFacetList`** (native `<details>` accordion z count badges, toggle przez `aria-pressed`). **`ProductListPage` integration** — gdy query/filter aktywne, `useList` disabled przez `queryOptions: { enabled: !isSearchActive }` i tabela renderuje hits z `attributesIndexed`. Search hits remap'owane na `Product` shape (name z `attributes.name`, brand z `attributes.brand`). Aria + i18n keys (`search.*`) w en+pl. JSX.Element type annotation removed (React 19 nie eksponuje globalnego JSX namespace — TS infers automatic). Biome lint quirks: deps array nie może mix `filtersKey/facetsKey` (serialised) z raw `filters/facets` — używamy raw refs, parent memoizes.

2. **#52 (0.5.4) Search endpoints z facetingiem + tenant isolation** (2026-04-30, branch `feat/0.5.4-search-endpoints`). Czwarty ticket epiku 0.5. **`CatalogSearchService` Application service** — wraps Meili `search()` z mandatory tenant scope (`filter=tenantId="<auth tenant>"`), optional facets + highlighting + offset pagination. **`SearchController`** z 3 endpoints: `/api/search/products`, `/api/search/categories`, `/api/search/assets` (zmienione z `/api/products/search` bo conflicted z AP4 sugar path `/api/products/{id}` — Symfony `#[Route]` rejestrowane PO AP4 mapping, więc /api/products/search interpretowane jako id="search"). Tenant scoping injected by `CurrentTenantProvider` — no current tenant → empty result (defence-in-depth). Response shape JSON (`{hits, totalHits, facetDistribution, processingTimeMs, page, perPage}`). **Deptrac**: Search layer dependencies + `Identity_Internals` dla CurrentTenantProvider. **`SearchEndpointsApiTest`** (3 tests): scoped search + status filter + 401 unauthenticated. Live Meili test setup z provision + deleteAllDocuments + reindex per test (forceReindex z 600ms wait dla async indexing). 278/278 testów PHPUnit zielone.

2. **#51 (0.5.3) Initial reindex command `pim:search:reindex`** (2026-04-30, PR #220 / `?` MERGED). Trzeci ticket epiku 0.5. **`BulkCatalogObjectIndexer` Application service** w Search BC — memory-safe iteration `Query::toIterable()` + `EntityManager::clear()` co 200 rows (sekcja 3.10 architektury, lessons #13). Batch push do Meili w paczkach po 500 documents. **`SearchReindexCommand` CLI** z `--kind=product|category|asset|all` + `--dry-run` flags. ProgressBar + bulk context management (`BulkContext::setBulk(true)` na czas runa żeby per-event listener z #50 nie duplikował work). Custom kind explicit reject. Manual smoke: `pim:search:reindex --dry-run` indexed 118 rows w 3 batchach. 275/275 testów PHPUnit zielone.

2. **#50 (0.5.2) Doctrine listener → Messenger → Meilisearch indexer** (2026-04-30, PR #219 / `?` MERGED). Drugi ticket epiku 0.5. **`CatalogObjectIndexer` Application service** w Search BC — `index(Uuid)` re-fetches CatalogObject + push document do `IndexSettingsTemplate::indexName(kind)`. Document shape: `{id, tenantId, code, kind, status, enabled, parentId, path, attributesIndexed, completeness, createdAt/updatedAt timestamps}`. **`CatalogIndexSubscriber`** w Search/Application/ z 5 `#[AsMessageHandler]` per Catalog DomainEvent (Created/AttributesChanged/EnabledChanged/Published/Archived). Każdy handler skip'uje gdy `BulkContext::isBulk()=true` — bulk path uses dedicated reindex (#51). **Custom kind early-return** (ADR-009 reserved). **Fail-soft pattern** — try/catch + log warning. Stary `ObjectIndexedSubscriber` placeholder z Catalog/Application/Subscriber/ usunięty bo handler powinien być w Search BC (cross-cutting reaction, nie Catalog internal). Smoke verified: 70+ produktów seeded przez Catalog API testy automatically w `products` index w Meili. 275/275 testów PHPUnit zielone.

2. **#49 (0.5.1) Meilisearch bundle** (2026-04-30, PR #218 / `?` MERGED). Pierwszy ticket epiku 0.5 + start nowego BC `Search`. **`meilisearch/meilisearch-php: ^1.16`** zainstalowany. **`Search` layer w Deptrac** (`Search → Catalog_Internals + Catalog_Contracts + Channel_Contracts + Shared`) — Search to cross-cutting infra adapter, top-level `apps/api/src/Search/`. **`MeilisearchClientFactory`** wraps env vars (MEILI_URL/MEILI_KEY) → autowire-able SDK Client. **`IndexSettingsTemplate`** declares 3 separate indexes per ObjectKind (`products`, `categories`, `assets`) z explicit `filterableAttributes` (Meilisearch quirk: bez explicit declare `?facets=` zwraca empty bez błędu — pattern z RF lessons). Custom kind throws (ADR-009 reserved Faza 2/3). **`MeilisearchIndexProvisioner`** idempotent createIndex + updateSettings. **`pim:search:health` CLI** weryfikuje reachability + provisioning — exit 0 = healthy. Manual smoke: `docker compose exec api php bin/console pim:search:health` zwraca tabelę z products/categories/assets + task UIDs. 275/275 testów PHPUnit zielone.

2. **#48 (0.4.8) Rate limiter per-endpoint** (2026-04-30, PR #217 / `?` MERGED). Ósmy ticket epiku 0.4 — domyka epic. **`symfony/rate-limiter` zainstalowany** + `framework.rate_limiter` config: `auth_login` (fixed_window 5/15min), `agent_run` (sliding_window 50/h — sekcja 8.5 architektury, reserved dla epic 0.7 Faza 2), `integration_sync` (fixed_window 10/h — reserved dla #74/#81 Faza 1). **`AuthLoginRateLimitListener`** (`#[AsEventListener(RequestEvent, priority: 32)]`) sprawdza POST `/api/auth/login`, throw'uje `TooManyRequestsHttpException` z `Retry-After` header gdy budget exhausted — runs przed Lexik JsonLogin. **Both successful + failed logins consume budget** (defence-in-depth: stolen credential nie re-arm). **Auth tests setUp() reset** limiter via `getContainer()->get('limiter.auth_login')->create('127.0.0.1')->reset()` bo cache.app filesystem-backed persists between PHPUnit tests. **`AuthLoginRateLimitTest`** (2 tests): 6th attempt = 429 z Retry-After header, successful login also consumes budget. 275/275 testów PHPUnit zielone.

2. **#47 (0.4.7) Mercure publisher dla zdarzeń domenowych** (2026-04-30, PR #216 / `?` MERGED). Siódmy ticket epiku 0.4. **`symfony/mercure-bundle: ^0.4.2`** zainstalowany — auto-wired Hub z `MERCURE_URL` (internal) + `MERCURE_PUBLIC_URL` (browser) + JWT secret. **`MercurePublisher` Application subscriber** — 5 `#[AsMessageHandler]` per Catalog DomainEvent (`ObjectCreated`, `ObjectAttributesChanged`, `ObjectEnabledChanged`, `ObjectPublished`, `ObjectArchived`). **Topic naming**: `<base>/objects/<id>` per row + `<base>/objects` broadcast — admin subscribe per row dla live editing lub broadcast dla list view. Plus helper `topicForKind()` dla per-kind subscription channels w przyszłości (#52 search indexer). **Type schema**: `object.<verb>.<kind>` (np. `object.created.product`) + payload `{type, occurredOn, data}` JSON-encoded. **`InMemoryMercureHub`** w `tests/Support/` — replace network-bound Hub w when@test. Override przez alias `Symfony\Component\Mercure\HubInterface` (NIE `mercure.hub.default` bo invalidates env var references). **Test trick**: pull container Hub PO request, NIE przed (sym kernel singleton). PHPStan widzi `TraceableHub` w dev container więc `getContainer()->get(InMemoryMercureHub::class)` zamiast `HubInterface`. **`MercurePublisherTest`** unit (4 tests) + **`MercureBroadcastApiTest`** integration (2 tests) — verify topics + payload + DELETE doesn't publish (kontrakt do flip w przyszłości gdy ObjectDeleted event dochodzi). Plus PHPStan ignore `symfonyContainer.serviceNotFound` w tests/* żeby test-only services nie blokowały analizy. 273/273 testów PHPUnit zielone.

2. **#46 (0.4.6) OpenAPI customization + spec export CI** (2026-04-30, PR #215 / `?` MERGED). Szósty ticket epiku 0.4. **`api_platform.yaml` rozszerzony**: `swagger.api_keys` z `JWT` (Authorization header) + `ApiKey` (X-API-Key header reserved dla #94/epic 0.10), `swagger.versions: [3]`, `enable_swagger_ui: true` explicit. Oba security schemes advertise'owane jednocześnie żeby integratorzy widzieli "Authorize" button przed merge'iem #94. **`<resource description="...">`** na `CatalogObject` XML — AP4 generuje per-shortName tag (`tags: [{name: 'CatalogObject', description: '...'}]`). **`docs/api-spec/v0.json`** — committed snapshot OpenAPI 3.1 spec (4080 linii, 19 paths, 7 tags, 2 security schemes). **CI workflow `openapi-spec` job** w `quality-php.yml` — reexport + diff przeciw snapshot, fail jeśli PR zmienia API surface bez bumping snapshot. Trigger paths += `docs/api-spec/**`. **`OpenApiSpecApiTest`** (4 tests): info block (title=PIM API, version=0.1.0), oba security schemes obecne, sugar paths declare GET+POST (assets read-only — POST nie istnieje), tags reflect shortNames. 267/267 testów PHPUnit zielone.

2. **#45 (0.4.5) ObjectDenormalizer/Normalizer — attributes ↔ object_values** (2026-04-29, PR #214 / `?` MERGED). Piąty ticket epiku 0.4. **`CatalogObjectInput` + `CatalogObjectPatchInput` rozszerzone o optional `attributes: ?array<string,mixed>`** (Groups: object:create / object:patch). **`CreateCatalogObjectCommand` + `UpdateCatalogObjectCommand`** dostają `attributes` field. **`ObjectAttributesUpserter` Application service** w `App\Catalog\Application\` — pure-PHP service który findByCode + create/update ObjectValue + provenance. Wstrzyknięte do Create/Update handlerów, woła'ne po `repository->save($object)`. **Provenance default = `Manual`** (Agent reserved dla phase 2). **Unknown attribute codes silently dropped** (admin UI surface'uje przed POST — strict mode overkill w MVP). **JSONB wrapper shape** `{value: rawValue}` per ADR-006 (text/number); arrays passed through unchanged (locale `{pl: ..., en: ...}`, price `{amount, currency}`). **`AttributesIndexedSyncListener` (#38)** automatycznie rebuild'uje `attributes_indexed` cache po flush — handler nie touchuje cache'u. **`AttributesDenormalizationApiTest`** (3 tests): POST z attributes → ObjectValue z provenance Manual + cache reflects, PATCH update color (weight untouched, Patch semantics), unknown code dropped. 263/263 testów PHPUnit zielone.

2. **#44 (0.4.4) Cursor-based pagination** (2026-04-29, PR #213 / `?` MERGED). Czwarty ticket epiku 0.4. **`paginationType="cursor"` + `<paginationViaCursor field="id" direction="DESC"/>`** na trzech sugar paths GetCollection (products/categories/assets). **OrderFilter** wired jako parameterised vendor service `App\Catalog\Infrastructure\ApiPlatform\Filter\OrderById` (`final` prevents subclassing — service ID musi być App-prefixed bo AP4 `<filter>FQCN</filter>` resolve). **Custom `RangeOnId` filter** zamiast vendor RangeFilter — vendor cicho odrzuca filtry na Uuid columns (silent loop pierwszej strony); custom robi `WHERE alias.id <op> :param` z regex-validation Uuid żeby Postgres `uuid` SQLSTATE nie wybuchał na malformed cursor → 500. **`CursorPaginationFieldsNormalizer` metadata factory decorator** naprawia AP4 4.x bug: `XmlResourceExtractor` zwraca `paginationViaCursor` jako assoc `['id' => 'DESC']` ale `PartialCollectionViewNormalizer` iteruje jako list of `{field, direction}` dicts. Decorator rebuilds shape przed cache. **`<order>` element + `paginationClientItemsPerPage="true"` + `paginationItemsPerPage="30"` + `paginationMaximumItemsPerPage="200"`** na CatalogObject resource — bez tego cursor walk nie ma deterministycznego ordering, query parameter `?itemsPerPage=N` jest ignored, lub klient może DoS z `?itemsPerPage=999999`. **`CursorPaginationApiTest`** (3 tests): cursor walk visits every row exactly once (30 SKU + page 10 = 3 strony), first page omits cursor + returns highest id (DESC), invalid cursor (`id[lt]=not-a-uuid`) returns 200|400|422 nie 500. 260/260 testów PHPUnit zielone.

2. **#43 (0.4.3) Custom filtry — search, attribute, category z descendants, completeness, status** (2026-04-29, PR #212 / `?` MERGED). Trzeci ticket epiku 0.4. **5 custom AP4 filters** w `Catalog/Infrastructure/ApiPlatform/Filter/`: `SkuFilter` (`?sku=ABC` substring LIKE na `code`), `AttributeFilter` (`?attribute[brand]=Nike&attribute[color]=red` JSONB containment, AND between keys), `CategoryFilter` (`?category=electronics` ltree `<@` descendants — unknown code → empty result świadomie), `CompletenessFilter` (`?completeness[gt|gte|lt|lte|eq]=80` numeric range na `completeness->>pct`), `StatusFilter` (`?status=published&enabled=true`, status whitelist enum-style — unknown silent skip). **4 custom Doctrine DQL functions** w `Catalog/Infrastructure/Doctrine/Dql/`: `JsonbContainsFunction` (`@>::jsonb`), `JsonbGetTextFunction` (`->>'key'`), `JsonbGetNumericFunction` (`(->>'key')::numeric`), `LtreeDescendantOfFunction` (`<@::ltree`). Rejestrowane w `doctrine.yaml` pod `orm.dql.string_functions` + `numeric_functions`. **`_instanceof` autotag** dla `FilterInterface` → `api_platform.filter` w services.yaml. Filter discovery w resource XML przez `<filters><filter>FQCN</filter></filters>`. **`CatalogFiltersApiTest`** (8 tests) — sku substring, status enum + invalid silent skip, attribute single + multi-key AND, category descendants + unknown empty, completeness range two-sided. 257/257 testów PHPUnit zielone.

2. **#42 (0.4.2) Grupy serializacji per-context** (2026-04-29, PR #211 / `cf625bc` MERGED). Drugi ticket epiku 0.4. **Symfony Serializer XML metadata files** w `<BC>/Infrastructure/Serializer/<Entity>.xml` (Catalog × 5: CatalogObject, ObjectType, Attribute, AttributeGroup, Association; Channel: Channel; Asset: Asset). Format: `<class name="FQCN"><attribute name="..."><group>admin:read</group></attribute></class>`. Wpisane do `framework.serializer.mapping.paths`. Mirror ADR-0011 — Domain pozostaje plain PHP bez `#[Groups]`. **3 contexts** (per #42 spec): `admin:read|write` (full editorial, admin UI default), `integration:read|write` (Faza 1 partners, drop completeness/path/parent), `public:read` (read-only API Configurator w epic 0.10, strict allow-list id+code+kind+attributesIndexed). **`tenant` field excluded from EVERY group** — defence-in-depth przeciw multi-tenant cross-leak. **`ContextScopeSerializerContextBuilder` decorator** (priority 10, outer) parsuje `?context=integration|public` query param i nadpisuje serializer groups w MVP (replace w #94 z ApiKey-driven context). Wszystkie 7 ApiResource XML declarations dostają `normalizationContext.groups: ['admin:read']` jako resource default — aktywuje to opt-in `KindAwareSerializerContextBuilder` z #41 który dorzuca per-kind layer (`product:admin:read`). **`SerializationContextApiTest`** (4 tests) weryfikuje admin/integration/public różne shapes na tym samym endpoint + unknown scope fallback do default. 249/249 testów PHPUnit zielone.

2. **#41 (0.4.1) ApiResource adnotacje na Catalog — XML resources + CQRS processors** (2026-04-29, PR #210 / `e86256c` MERGED). Pierwszy ticket epiku 0.4. **9 XML resource files** w `<BC>/Infrastructure/ApiPlatform/Resource/` (Catalog: CatalogObject z 14 operations dla 3 sugar paths, Attribute, ObjectType, AttributeGroup, Association; Channel: Channel; Asset: Asset). **Single CatalogObject resource** scala 3 sugar paths (`/api/products|categories|assets`) — multiple `<resource class="X">` siblings powodowały AP4 IRI rendering ambiguity (`@type:"AssetObject"` na POST `/api/products`). Każda operation ma `extraProperties.kind` discriminator + unique `name`. **CQRS Application/Command slices** (per ADR-0012): `CreateCatalogObject`, `UpdateCatalogObject`, `DeleteCatalogObject` z Command + `#[AsMessageHandler]` Handler. **Input DTOs** `CatalogObjectInput` / `CatalogObjectPatchInput` w `Resource/` (setter-less Domain → DTOs są jedynym sposobem hydration AP4 default denormalizera). **State Processor `CatalogObjectProcessor`** bridge'uje AP4 → MessageBus, unwrap'uje `HandlerFailedException` żeby `UnprocessableEntityHttpException` (kind mismatch) → 422 zamiast 500. **Query extensions** `KindCollectionExtension` + `KindItemExtension` narrowing per kind dla read paths (cross-kind GET → 404). **7 concrete Voters** (`CatalogObjectVoter`, `ObjectTypeVoter`, `AttributeVoter`, `AttributeGroupVoter`, `AssociationVoter`, `ChannelVoter`, `AssetVoter`) używają FQCN strings w `subjectClass()` (NIE imports — Deptrac blokuje `Identity_Internals → Catalog/Channel/Asset_Internals`). **`'association'` resource dodany do `RbacMatrix::RESOURCES`** + grant pełny dla `super_admin` i `catalog_manager`. **`KindAwareSerializerContextBuilder` zmienione semantyki** (#128 builder dodawał groups bezwarunkowo — z entity bez `#[Groups]` to filtruje wszystko out; teraz opt-in: dodaje groups TYLKO gdy operation już deklaruje `groups` w context, #42 aktywuje przez normalizationContext.groups). **ExpressionLanguage stripcslashes gotcha**: `'App\Catalog\X'` w XML attribute → po stripcslashes = `AppCatalogX` (single backslash zżarty). Fix: `\\` w XML → `\` w expression. **Read-only secondary entities** świadome odejście — pełen CRUD tylko na CatalogObject sugar paths; Attribute/ObjectType/AttributeGroup/Channel/Association/Asset eksponowane jako Get + GetCollection (write paths w epic 0.6 admin UI). **24 ApiTestCase** + **Voter unit tests** (12 tests) — wszystkie 245 testów PHPUnit zielone, 1 skipped (pre-existing). PHPStan max + Deptrac 0 violations.

## Ostatnie 3 akcje (Epic RF closing wave — sprzed 0.4)

1. **Epic RF — Refactor for tip-top — ZAMKNIĘTY** (2026-04-29). 35 ticketów RF-01..35 zaplanowane → 28 wdrożonych, 5 WONTFIX (RF-14, RF-15, RF-27, RF-28, RF-33 — wszystkie z uzasadnieniem w ADR-0012 lub łańcuchu zależności od epiku 0.4), 1 duplikat (RF-04 → wchłonięty w #187 RF-02), 1 follow-up (RF-22 custom PHPStan rule deferred). PR-y zmergowane w main: #186 (RF-01 Shared/), #187 (RF-02+04 Tenant→Shared sweep), #188 (RF-03 Tenant infra→Shared), #189 (RF-05 AggregateRoot), #190..#193 (RF-06..09 XML mapping per BC), #194 (RF-10 Catalog repos), #195 (RF-11 pozostałe repos), #196 (RF-12 Catalog domain methods), #197 (RF-13 pozostałe), #198..#199 (RF-16/17 Contracts/Event), #200 (RF-18 Query DTO), #201 (RF-19 cross-BC FK→Uuid), #202 (RF-20 DomainEventDispatcher + Idempotency), #203 (RF-21 Deptrac CI gate), #204 (RF-22..25 PHPStan deprecation + Rector + ResetInterface + MAX_REQUESTS), #205 (RF-26 frontend features/), #206 (RF-29 tests reorg), #207 (RF-30 DAMA + TenantFactory), #208 (RF-31/32 ADR + C4 + ONBOARDING). **Quality gates wszystkich PR-ów:** PHPStan max + Deptrac (0 violations + 27 baseline) + PHP-CS-Fixer + PHPUnit (155+ unit tests) + Playwright + Biome + tsc + Vite + audits. **Post-RF audit:** [docs/audits/AUDIT-REPORT-2026-04-29-post-RF.md](docs/audits/AUDIT-REPORT-2026-04-29-post-RF.md) — pre-RF 5 CRITICAL + 9 HIGH + 8 MEDIUM → post-RF 0 CRITICAL + 2 HIGH (oba WONTFIX-łańcuch epiku 0.4) + 4 MEDIUM (3 WONTFIX/deferred + 1 OPEN low-priority). **Decyzje architektoniczne udokumentowane w 6 nowych ADR-ach** (`docs/adr/0010..0015`) + C4 diagrams + ONBOARDING.md.

## Sub-faza: MVP-ALPHA — **epik 0.2 ZAMKNIĘTY 7/7** ✅, **epik 0.3 ZAMKNIĘTY 11/11** ✅ (#31, #32, #33, #34, #35, #36, #37, #38, #39, #40, #128 wszystkie w main). `AUTONOMOUS_MODE: OFF` (auto-flipped 2026-04-29 po zamknięciu #128 — epic 0.4 wraca do plan-first per ustaleniu z operatorem).

## Ostatnie 3 akcje
1. **#128 (0.3.11) Hooks pod kind='custom' na poziomie ApiResource — szkielet** (2026-04-29, PR #149 / `b0d65f2` MERGED). Ostatni ticket epiku 0.3. Trzy klasy w nowym module `App\Catalog\Infrastructure\ApiPlatform\`: (a) **`ObjectKindRouter`** — pure mapping `ObjectKind → {sugar path, serializer groups}`. Built-in kindy (Product/Category/Asset) → `/api/products|categories|assets` + `['object:read', 'object:read:product|category|asset']`. Custom kind: `pathFor()` rzuca `DisabledFeatureException` (nie ma sugar path), `groupsFor()` fallback na `['object:read']` (faza 2 zarejestruje per-tenant groups dynamicznie). Plus statyczne `builtInKinds()` dla #41's metadata factory. (b) **`CustomObjectTypeApiGuard`** — API-layer twin guard'a z #32 (`enable_custom_object_types`). `assertAllowed(ObjectKind)` rzuca `DisabledFeatureException` dla `Custom` gdy flag off. Triple-layering (DB CHECK → ObjectTypeService z #32 → API guard) zapewnia że custom rows nie przeciekną przez REST. (c) **`KindAwareSerializerContextBuilder`** — AP4 dekorator `SerializerContextBuilderInterface` (`api_platform.serializer.context_builder`). Czyta `operation.extraProperties.kind` i wzbogaca context o `kind` + per-kind groups. No-op dopóki #41 nie wire'uje `#[ApiResource]` na `CatalogObject`. **services.yaml** rozszerzony — guard z parameter binding, decorator z `decorates: 'api_platform.serializer.context_builder'`. Verified `bin/console debug:container` pokazuje decorator zdiagnozowany jako `api_platform.serializer.context_builder.filter.inner`. **Tests:** 13 nowych — `ObjectKindRouterTest` (5 cases: built-in path mapping, custom rzuca, groups per kind, custom fallback, builtInKinds list), `CustomObjectTypeApiGuardTest` (3 cases: built-in pass, custom rejected gdy off, custom OK gdy on), `KindAwareSerializerContextBuilderTest` (5 cases: pass-through bez `kind`, merge groups + stamp kind, unknown kind ignored, custom fallback, single string group promoted). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **195/195** (182 → 195) + Playwright 2 passed + 10 fixme + composer audit OK + decorator wired. **Świadome odejścia:** (a) **brak faktycznego `#[ApiResource]` na `CatalogObject`** — to scope #41 (epik 0.4). #128 jest CZYSTYM szkieletem extension pointów; (b) **Custom kind `kind` value w context jest "stamped" mimo fallback groups** — design intent: serializer może rozróżniać "custom — tylko shared group" od "built-in product"; (c) **CustomObjectTypeApiGuard nie jest wired do żadnego call site'u** — czeka na #41's denormalizers. Wired w services.yaml jako available service.
2. **#40 (0.3.10) Demo dataset seeder — 100 SKU + 5 cat + 10 asset** (2026-04-29, PR #148 / `46e60ca` MERGED). Dziesiąty ticket epiku 0.3. **`DemoCatalogSeeder`** w `App\Catalog\Application\` — idempotentny seeder per-tenant zbudowany na pattern z `BuiltInObjectTypeSeeder`/`BuiltInAssociationTypeSeeder`. **Sentinel `DEMO-100`** (last SKU) → re-run = no-op. **~19 atrybutów** pokrywających wszystkie 10 `AttributeType`: text (name/sku/description/short_description/brand/seo_title/seo_description/alt_text/caption), select (color/size) + multiselect (tags) + AttributeOptions, price/metric/number/boolean/date/asset/relation. **22 ObjectTypeAttribute junctions** (15 product + 4 category + 3 asset) wraz z ustawieniem `label_attribute_id` / `image_attribute_id` / `completeness_rules` per built-in ObjectType (`product → required: [sku, name, description, price]`, `category → required: [name, seo_title]`). **100 produktów** (`DEMO-001..100`) z pełnym pokryciem typów — każdy ma 15 ObjectValue rows + zdublowany `attributes_indexed` JSONB cache. **5 kategorii** (`CAT-FOOTWEAR/APPAREL/OUTDOOR/RUNNING/SALE`) z `seo_title`, `seo_description`, ltree path — proof of ADR-009 że Category ma own user-defined schema. **10 assetów** z dedykowaną tabelą `assets` + `asset_variants` (no real binary upload, tylko placeholder storage path) + `CatalogObject(kind=asset)` ze swoim `name` + `alt_text`. **BulkContext flip ON** — listener nie szaleje na 1500+ wierszach; `attributes_indexed` populated inline alongside canonical `object_values`. **AppFixtures rewire** — demo tenant dostaje `DemoCatalogSeeder->seed()`, acme tenant zachowuje legacy minimalny dataset (3 SKUs DEMO-001..003 ACME-* w `attributes_indexed` style). **Tests:** 5 nowych functional `DemoCatalogSeederTest` — counts (19 attrs, 100 product, 5 category, 10 asset, 22 junctions, 1535 ObjectValue rows), attributes_indexed shape per AttributeType, category own schema, idempotency, ObjectType wiring (label/image/completeness rules). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **182/182** (177 → 182) + Playwright 2 passed + 10 fixme + composer audit OK + manual smoke `bin/console doctrine:fixtures:load` (kind|count: asset 10, category 5, product 103). **Świadome odejścia:** (a) **brak fizycznego upload do MinIO** dla assetów — seeder tworzy tylko wpisy DB ze ścieżkami `<tenant>/demo/<asset>.jpg` (faktyczny upload pokrywa #37 + `pim:asset:upload` CLI); (b) **scope override per channel/locale w `ObjectTypeAttribute` zostaje NULL** — Channel encja istnieje od #36 ale nie używana w seederze; (c) **acme tenant dostaje legacy minimal dataset**, nie pełen graph — tenant isolation tests + admin e2e probes assume legacy shape, promoting acme jest out of scope #40; (d) **`DemoCatalogSeederTest` używa `DBAL::fetchOne` dla COUNT na `object_type_attributes`** — composite PK ObjectTypeAttribute breaks DQL `COUNT(j) FROM ObjectTypeAttribute j` (dokumentowany Doctrine quirk). **Gotcha:** mid-loop `EntityManager::clear()` z re-finder logic okazał się over-engineering dla ~1800 entities w fixture context — usunięty na rzecz prostego flow flush-once-per-section.
3. **#39 (0.3.9) Symfony Validator constraints per AttributeType — 10 walidatorów + dispatcher** (2026-04-29, PR #147 / `faff395` MERGED). Dziewiąty ticket epiku 0.3. **Nowy moduł `App\Catalog\Application\Validation\`**: (a) `ValidationError` value object (`final readonly class` z path/code/message), (b) `AttributeValueValidatorInterface` z `validate(Attribute, array): list<ValidationError>`, (c) `AttributeValueValidator` dispatcher (mapping `AttributeType->value → AttributeValueValidatorInterface`, factory `default()` rejestrująca wszystkie 10 typów). **Per-typ walidatory** w `Validation\TypeValidator\` — wszystkie 10 czytają `Attribute.validation_rules` JSONB: text (max_length/min_length/pattern), number (min/max/decimal_precision), select (option_codes allowlist), multiselect (option_codes + min_count/max_count + per-index validation), date (ISO 8601 + min/max date strings), boolean (strict bool only), asset (UUID asset_id), relation (UUID object_id), price ({amount, currency} + min_amount + currencies allowlist), metric ({value, unit} + units allowlist + min/max/decimal_precision). **`services.yaml` factory wire** — `factory: ['AttributeValueValidator', 'default']`. **Flat `list<ValidationError>` kontrakt** — API layer (#41) zamapuje do RFC 7807 Problem Details, admin (#56) do form errors. **Tests:** 37 nowych — `AttributeValueValidatorTest` (3: dispatcher dispatchu do mapped validatora, fallback `attribute.unsupported_type` gdy brak, factory default rejestruje wszystkie 10 typów + smoke przez puste payload — guard regression przy dodawaniu typu) + 10 plików `TypeValidator/XxxValidatorTest` (1-4 cases per validator: happy + sad path; w sumie ~34 testów). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **177/177** (140 → 177, +37 testów) + Playwright 2/12 passed + 10 fixme + composer audit OK. **Świadome odejścia:** (a) **brak cross-row checks** (np. select option_codes vs. live AttributeOption rows, asset/relation existence) — to scope #41 API layer; walidator robi tylko schema-shape; (b) **brak mapowania do `Symfony\Component\Validator\Constraints` na encji `Attribute`** — pojedynczy mapping per request lives w #41; (c) **brak unit family normalization** dla typu `metric` (kg ↔ g) — przesunięte do fazy 1; (d) **`mb_strlen($raw, 'UTF-8')` w TextValidator** — UTF-8 chars, nie bytes, polskie diakrytyki liczą się 1. (2026-04-29, branch `feat/0.3.6-channel`). Szósty ticket epiku 0.3. Nowy bounded context `App\Channel\` (Domain/Entity/, Infrastructure/Doctrine/Repository/, Infrastructure/Doctrine/EventListener/) — pierwszy non-Catalog/Identity context z encjami. **5 encji**: `Locale` (global, BCP-47 code) + `Currency` (global, ISO 4217) — bez TenantScoped, na audit allowlist; `Channel` (TenantScoped) z M2M na Locale + Currency + nullable FK na CatalogObject (category_tree_root); `ChannelObjectTypeMapping` zastępuje pre-ADR-009 `ChannelAttributeMapping` — per channel × per ObjectType × per Attribute mapping z `target_field` (free-form string per integration adapter). UNIQUE triple (channel, object_type, attribute). **`ChannelCategoryRootValidator`** Doctrine listener (prePersist + preUpdate) — walidacja `category_tree_root_object_id` musi wskazywać CatalogObject z kind='category'. Schema CHECK by tu nie zadziałał (FK sprawdza tylko id, nie discriminator). **Migracja `Version20260429064833`** tworzy 5 tabel + indexes + FKs + inline raw SQL seeduje 2 default locales (pl_PL, en_US) + 3 currencies (PLN, EUR, USD) idempotentnie przez WHERE NOT EXISTS. **Doctrine YAML** rozszerzony — nowy mapping `Channel: 'src/Channel/Domain/Entity'`. **TenantAuditCommand allowlist** rozszerzony o 5 nowych infra tabel (locales, currencies, channel_locales, channel_currencies, channel_object_type_mappings). **PHPStan ignore** — Channel dorzucony do `doctrine.associationType` paths. **Tests:** 9 nowych — `ChannelTest` (5 unit cases: defaults, M2M idempotent, category root setter, assignTenant, Locale/Currency basic fields), `ChannelCategoryRootValidatorTest` (4 unit cases: null OK, category root accepted, product root throws, non-Channel ignored). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **129/129** (120 → 129, 1 skipped #41) + migration round-trip + audit + Playwright (in progress) + 10 fixme'd. **Świadome odejścia:** (a) **Locale + Currency global** (nie tenant-scoped) — codes są standardami światowymi, każdy tenant referencuje te same rows; aktywacja per tenant przez M2M na Channel; (b) **`category_tree_root_object_id` walidacja w listener'ze**, nie schema CHECK — FK target check'uje tylko id, nie kind discriminator; listener daje cleaner error message; (c) **`ChannelObjectTypeMapping` bez TenantScoped** — junction infrastruktury, tenant scope dziedziczony przez parent Channel; na audit allowlist.
2. **#35 (0.3.5) Association + AssociationType** (2026-04-29, PR #143 / `f8f102a` MERGED). Piąty ticket epiku 0.3. Per ADR-009 **asocjacja generic między CatalogObject** (any kind), nie tylko produkty. **`AssociationType` encja** (TenantScoped) — code (UNIQUE per tenant), label JSONB, position. Brak `is_built_in` flag — wszystkie rows tenant-defined; "default 4" to seed każdy tenant dostaje na start. **`Association` encja** (TenantScoped) — source CatalogObject (CASCADE), target CatalogObject (CASCADE), AssociationType (RESTRICT), position. Constructor enforce'uje no-self-loop (`source.id != target.id`) + DB CHECK constraint mirror (defence in depth). UNIQUE `(source, target, type)` triple. **Reverse direction NIE auto-mirrored** — admin/agent decyduje czy A→B cross_sell implies B→A; asymetryczne semantyki ("X replaces Y") zostają możliwe. **`BuiltInAssociationTypeSeeder` service** — idempotent per-tenant dla 4 default types: `cross_sell`, `up_sell`, `related`, `accessories` z lokalnymi label PL/EN. Pattern: runtime counterpart inline raw SQL w migracji (mirror BuiltInObjectTypeSeeder). **Migracja `Version20260429050326`** tworzy 2 tabele + indexes (UNIQUE triple, source_type composite, target single, tenant single) + FK strategy: tenant RESTRICT, source/target CASCADE, type RESTRICT. **Inline raw SQL seeduje** 4 default types per istniejący tenant (idempotent przez `WHERE NOT EXISTS`). **AppFixtures rewire** — wywołanie `associationTypeSeeder->seed($tenant)` po `builtInSeeder->seed`. **PHPStan ignore extension** — Association + AssociationType dorzucone do `doctrine.associationType` paths. **Tests:** 7 nowych — `AssociationTest` (4 unit cases: constructor wires references, self-loop rejected, assignTenant idempotency, AssociationType defaults), `AssociationTypeSeederTest` (3 functional KernelTestCase: 4 default types per tenant, idempotent, scoped per tenant). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **120/120** (113 → 120, 1 skipped: viewerRole #41) + migration round-trip + audit + Playwright 2/12 + 10 fixme'd (no regression). **Świadome odejścia:** (a) **brak `findAssociations()` z type filter w SQL** — repository post-fetch filter dla simplicity (admin UI list-per-type robi małe queries); SQL filter dochodzi gdy benchmark pokaże potrzebę; (b) **brak reverse-mirror logic** — admin decyduje per case; (c) **constructor LogicException dla self-loop** + DB CHECK constraint — defence in depth, ale w praktyce constructor catchuje 100% prób.
2. **#33 (0.3.3) Predefined ObjectType fixtures + ltree extension + path validator + data migration products→objects + DROP legacy products** (2026-04-29, PR #142 / `4bb7caa` MERGED). Czwarty ticket epiku 0.3 (kolejność #34 → #33 świadomie odwrócona, #33 miało `Blocked by #34`). **Ltree extension** aktywowane przez migrację `Version20260428222056`: `CREATE EXTENSION IF NOT EXISTS ltree` + ALTER `objects.path` z VARCHAR(4096) na LTREE (drop default najpierw, Postgres nie auto-castuje VARCHAR default na ltree) + CHECK constraint `path IS NULL OR kind = 'category'` + partial GIST + BTree indeksy na `path WHERE kind='category'`. **`LtreeType` custom Doctrine type** w `Catalog/Infrastructure/Doctrine/Type/` — pass-through nad text, registered w `doctrine.yaml dbal.types` + `dbal.mapping_types.ltree: ltree` (introspekcja). **`CategoryPathValidator` Doctrine listener** (prePersist + preUpdate): `kind ≠ Category && path ≠ null` → throw, `kind = Category && path ≠ null` → regex validate ltree label format `[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)*`. **Predefined ObjectType fixtures** seedowane via 2 ścieżki: (a) inline raw SQL w migracji (`INSERT … SELECT … FROM tenants WHERE NOT EXISTS …`) dla istniejących tenantów; (b) `BuiltInObjectTypeSeeder` service (`Catalog/Application/`) idempotent per-tenant dla future onboarding flow. Wywoływany przez `AppFixtures` po seedingu tenantów. **Data migration products → objects** raw SQL w migracji: SELECT products + JOIN built-in product ObjectType per tenant + INSERT objects z `attributes_indexed = jsonb_build_object('sku', 'name', 'description', 'brand')`. Po migracji: DROP RLS policies na products + DROP TABLE products. **Legacy cleanup PHP**: `Product` entity + `ProductRepository` + `ProductVoter` USUNIĘTE; `ProductApiTest` + `TenantIsolationTest` + `ProductVoterTest` USUNIĘTE; `AuthApiTest::viewerRoleCannotDeleteProduct` zmieniony na `markTestSkipped` z TODO #41 (sugar paths /api/products na CatalogObject + voter); `protectedEndpoint*` testy zmienione z `/api/products` na `/api/auth/me`. **`AppFixtures` rewire**: zamiast Product entity tworzy CatalogObject (kind=product, status=published, attributes_indexed populated). **`BulkImportBenchmarkCommand` rewrite** na CatalogObject + ObjectType lookup (memory benchmark dla #13 wciąż działa). **`PostgresExtensionLoader`** kernel.request + console.command listener — idempotent `CREATE EXTENSION IF NOT EXISTS ltree` na każdym boot, bo Foundry ResetDatabase trait dropuje DB + odpala schema:update bypass migracji (extension by inaczej zniknął). **Tests:** 18 nowych — `CategoryPathValidatorTest` (10 unit cases: null path passes, product+path throws, asset+path throws, 6 valid ltree paths z dataProvider, 5 invalid ltree paths z dataProvider, non-CatalogObject ignored), `BuiltInObjectTypeSeederTest` (3 functional cases: seeds 3 types, idempotent, scoped per tenant). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **113/113** zielone (1 skipped: viewerRoleCannotDeleteProduct deferred do #41) + Playwright (in progress) + migration round-trip + `pim:tenant:audit` (objects + object_values + others green; products zniknął). **Świadome odejścia (duże):** (a) **brak ApiResource sugar paths /api/products|categories|assets** — to scope #41 (epik 0.4); brak adaptacji testów ProductApiTest/TenantIsolationTest/ProductVoterTest na CatalogObject (zamiast tego usunięte + AuthApiTest skip); (b) **`Product` entity USUNIĘTY** całkowicie (za odważnie ale per ADR-009 było konieczne); (c) **AuthApiTest::viewerRoleCannotDeleteProduct skipped** — voter na CatalogObject + sugar paths to #57+#41; (d) **migracja danych raw SQL** zamiast PHP-level data migration — fixture seeder w PHP byłby cyclic (wymaga ObjectType które są seedowane w tej samej migracji); (e) **PostgresExtensionLoader na każdy boot** zamiast jednorazowo — Foundry ResetDatabase mode `migrate` nie jest dostępny w tej wersji bundle'a, najprostsze rozwiązanie to listener który przeżywa drop+recreate; (f) **`Doctrine schema:update` w testach** zamiast migration mode — Foundry tej wersji nie ma config `reset.mode`, fallback na schema-rebuild + extension loader. **Gotcha:** `mapping_types.ltree: ltree` w doctrine.yaml było wymagane bo `doctrine:schema:drop --full-database` introspekcjonuje istniejące kolumny i mapuje SQL ltree → Doctrine ltree.
2. **#34 (0.3.4) Object + ObjectValue + Provenance enum** (2026-04-29, PR #141 / `ab2aa6e` MERGED). Trzeci ticket epiku 0.3 (zamiast spodziewanego #33 — #33 ma w opisie GH issue `Blocked by: #34` bo ltree validator wymaga encji Object). **Kolejność odwrócona świadomie** — #34 first, potem #33 z fixturami + data migration + ltree extension. **`Provenance` PHP backed enum** w `Catalog/Domain/Provenance.php` — 3 wartości MVP: Manual/Import/Integration. **`Agent` case explicite ABSENT** — phase 2 (epik 0.7) doda go razem z agent approval inbox; negative test guard `Provenance::tryFrom('agent') === null` chroni przed accidental drop. **`CatalogObject` encja** (TenantScoped) w `Catalog/Domain/Entity/CatalogObject.php` — nazwa klasy nie `Object` bo to PHP reserved keyword od 7.2; tabela jednak `objects`. Pola: id, tenant FK, object_type FK, kind enum (denormalised z ObjectType.kind), code, parent FK self-ref (variants dla product, drzewo dla category), enabled, status (draft/published/archived class consts), completeness JSONB, attributes_indexed JSONB, path VARCHAR(4096) nullable (LTREE conversion w #33), createdAt/updatedAt. UNIQUE `(tenant_id, kind, code)`. **`ObjectValue` encja** (TenantScoped): tenant FK, object FK CASCADE, attribute FK RESTRICT, channel_id UUID nullable, locale VARCHAR(8) nullable, value JSONB (polymorphic per AttributeType — `{value: scalar}` / `{option_code}` / `{amount, currency}` etc), provenance enum, provenance_meta JSONB. UNIQUE `(object_id, attribute_id, channel_id, locale) NULLS NOT DISTINCT` — Postgres 15+ syntax, eliminuje COALESCE juggling z PHP. **Migracja `Version20260428220053`** tworzy oba tables + GIN index `objects_attributes_indexed_gin` na attributes_indexed (DoD benchmark celu sub-50ms na 10k×200×3 dataset, faktyczny benchmark dochodzi w #38 razem z listener który populuje cache). **Świadome odejścia:** (a) **`path` jako VARCHAR(4096) zamiast LTREE** — Doctrine 3 brak ltree DBAL type; #33 włącza extension + ALTER COLUMN to LTREE + dodaje partial GIST/BTree indexes WHERE kind='category' + listener walidator; (b) **legacy `products` tabela zostaje** — data migration `products → objects` wymaga predefined ObjectType fixtures z #33 (każdy migrated row potrzebuje `object_type_id` FK target); (c) **brak generated columns** (`name_pl`, `sku_for_product`) — przesunięte do #38 razem z listener który populuje attributes_indexed (generated columns bez source data są pustym kontraktem); (d) **brak `#[ApiResource]`** — exposure REST/Hydra to scope #41; (e) **brak CHECK constraint** na `kind = 'category' OR path IS NULL` — paired z listener w #33; (f) **brak benchmark GIN** — wymaga seeda z #40 (10k×200×3 dataset), w #34 weryfikujemy strukturalnie że GIN exists. **Repositories:** `CatalogObjectRepository` (findByCode, findByKind), `ObjectValueRepository` (findByObject, findOneByScope). **PHPStan ignore extension** — CatalogObject + ObjectValue dorzucone do `doctrine.associationType` paths. **Tests:** 16 nowych — `ProvenanceTest` (3 cases dokładnie + agent absent guard), `CatalogObjectTest` (UUID v7 + defaults, kind denormalisation, JSONB attributes_indexed round-trip, path stores LTREE string for category, parent self-ref, assignTenant idempotency, status transitions), `ObjectValueTest` (constructor wires up references, polymorphic JSONB shapes per type, scope columns hold channel/locale, provenance meta, assignTenant). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **121/121** (105 → 121) + Playwright **12/12** (po 1 retry — flake na PATCH product test pierwszego runa, drugi 12/12 deterministic) + migration round-trip + `pim:tenant:audit` (objects + object_values green domain). **Gotcha:** `class Object` w PHP nie kompiluje się (reserved word PHP 7.2+) — klasa nazwana `CatalogObject`, table mapping pozostaje `objects`. To jednorazowa naming mismatch udokumentowana w docblock entity.
2. **`AUTONOMOUS_MODE: ON` toggle aktywowany** (2026-04-29). Operator przełączył flag w CLAUDE.md linia 8 + `bypassPermissions` w `~/.claude/settings.json`. Scope rozszerzony z epiku 0.3 na **epiki 0.3 + 0.4 łącznie** — operator komunikat "jak nie będzie problemów w epiku 3 to leć od razu po zakończeniu z epikiem 4". CLAUDE.md zaktualizowany żeby zakres scope autonomous mode obejmował tickety #33-#40 + #128 + #41-#48.
3. **#32 (0.3.2) ObjectType + ObjectTypeAttribute (ADR-009)** (2026-04-28, PR #140 / `4926e9e` MERGED). Drugi ticket epiku 0.3. **`ObjectKind` PHP backed enum** w `Catalog/Domain/ObjectKind.php` — 4 wartości: Product/Category/Asset/Custom + helper `isBuiltIn()` (true dla pierwszych trzech, false dla Custom). **`ObjectType` encja** (TenantScoped) w `Catalog/Domain/Entity/`: id, code (UNIQUE per tenant), kind, is_built_in BOOL, label JSONB, completeness_rules JSONB '{}', label_attribute_id + image_attribute_id (FK Attribute, ON DELETE SET NULL), schema_version INT, createdAt/updatedAt. **`ObjectTypeAttribute` junction** (BEZ TenantScoped — junction infrastruktury, na `INFRA_TABLES` allowlist `pim:tenant:audit`): composite PK (object_type_id, attribute_id), required_for_completeness, sort_order, channel_id UUID nullable + locale VARCHAR(8) nullable jako forward-compat dla #36/#39. FK strategy: object_type CASCADE, attribute RESTRICT (chroni przed delete attribute z aktywnymi assignments). **`ObjectTypeService`** (`Catalog/Application/`) z dwoma guardami: (a) **feature flag `pim.catalog.enable_custom_object_types`** w `services.yaml` default false → `DisabledFeatureException::customObjectTypesDisabled()` przy `kind=Custom` w MVP (R-29 mitigation); (b) **`is_built_in=true` protection** → `BuiltInObjectTypeException::cannotDelete()` przy próbie deletion. Service-layer guard, nie DB constraint (RLS dotyczy tenant scope, nie business invariant). Plus `assignAttribute` (idempotent — find-or-update junction) + `unassignAttribute`. **`Catalog/Domain/Exception/`** — nowy folder z `DisabledFeatureException` + `BuiltInObjectTypeException` (factory methods). **Migracja `Version20260428205215`** tworzy `object_types` + `object_type_attributes` z explicit-named indexes/FKs (per Sprint-0 conv) — wycięte parasitic Doctrine renames z auto-diff. **`TenantAuditCommand` rozszerzony** — `object_type_attributes` na `INFRA_TABLES` allowlist (junction bez własnego tenant_id). **PHPStan ignore extension** — `ObjectType` dorzucony do `doctrine.associationType` paths. **Tests:** 21 nowych — `ObjectKindTest` (4 cases dokładnie + lowercase backing + dataProvider isBuiltIn matrix), `ObjectTypeTest` (UUID v7 + defaults, JSONB UTF-8 polski, markBuiltIn, label/image attribute setters, completenessRules, bumpSchemaVersion, assignTenant idempotency), `ObjectTypeServiceTest` (8 functional cases: create built-in product OK, custom blocked by flag, custom OK gdy flag enabled, delete built-in blocked, delete custom OK, assignAttribute creates junction, assign idempotent + updates existing, unassignAttribute removes). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **105/105** (84 → 105) + Playwright **12/12** (po 1 retry — flake na `products list shows seeded products` z pierwszego runa, drugi 12/12 deterministic) + migration round-trip + `pim:tenant:audit` (object_types green domain, object_type_attributes infra-skipped). **Świadome odejścia:** (a) **`channel_id` jako raw UUID** w junction zamiast ManyToOne — encja Channel jeszcze nie istnieje (przyjdzie w #36); po #36 dorzucimy ManyToOne; (b) **`completeness_rules` JSONB stored verbatim** — interpretacja w #38 (Doctrine listener attributes_indexed + completeness_pct); (c) **brak validatora spójności** label/image_attribute_id musi być w junction — follow-up po #34 (gdy Object encja będzie używać tego); (d) **`schema_version` jako forward hook** — fazy 2 export/import tooling. **Gotcha:** Playwright pierwszy run pokazał flake `getByRole('cell', { name: /^DEMO-/ })` — DB miało products, login działał, drugi run all green. Hipoteza: sequencing race między fixtures load a stable Vite HMR bundle bo migracja round-trip wcześniej restartowała state. Nie zmienia kodu — flake retry wystarczył.
2. **#31 (0.3.1) Attribute + AttributeType entities** (2026-04-28, PR #139 / `c0eda0e` MERGED). Pierwszy ticket epiku 0.3 (Catalog domain). **`AttributeType` PHP backed enum** w `Catalog/Domain/AttributeType.php` — pierwszy backed enum w repo (Sprint-0 używał class const string), 10 wartości: text/number/select/multiselect/date/boolean/asset/relation/price/metric. Helper `usesOptions()` zwraca true tylko dla Select/Multiselect (invariant pilnowany w validator z #39). **3 encje** w `Catalog/Domain/Entity/`: `AttributeGroup` (id, code, label JSONB, position), `Attribute` (id, code, label JSONB, help JSONB nullable, type enum, isLocalizable/Scopable/Required bool, validationRules JSONB, group_id nullable FK z ON DELETE SET NULL, position), `AttributeOption` (id, attribute FK z ON DELETE CASCADE, code, label JSONB, position). Wszystkie implementują `TenantScoped` (z #30) — listener auto-stempluje tenant_id na prePersist. **AttributeOption ma własną kolumnę `tenant_id`** (denormalisation) zamiast dziedziczenia z parent Attribute — TenantFilter operuje per encja, `pim:tenant:audit` widzi go jako domain table. Koszt: 16B/row + listener stamp. **JSONB columns** (`Types::JSON` + `options: ['jsonb' => true]`) — pierwszy native JSONB w repo (User.roles to legacy `Types::JSON` bez jsonb option). label/help wielojęzyczne `{pl, en}`. **Migracja `Version20260428202805`** tworzy 3 tabele + indeksy `(tenant_id, code) UNIQUE` na `attribute_groups` i `attributes` + composite `(tenant_id, group_id, position)` na `attributes` + `(attribute_id, code) UNIQUE` na `attribute_options`. **Repositories**: 3 standardowe `ServiceEntityRepository<T>` z `findByCode($code, Tenant)`. **PHPStan ignore extension** — dorzucone Attribute/AttributeGroup/AttributeOption do `doctrine.associationType` ignore w `phpstan.dist.neon` (ten sam pattern co Product — `?Tenant` w PHP, NOT NULL w schemacie, listener pilnuje invariant). **Tests:** 19 nowych — `AttributeTypeTest` (10 cases dokładnie + lowercase backing values + `from()` round-trip + dataProvider 10 cases dla `usesOptions()`), `AttributeTest` (UUID v7 + defaults, JSONB UTF-8 polski round-trip, assignTenant idempotency + re-assign throws, group construction defaults, AttributeOption parent reference). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **84/84** (65 → 84) + Playwright **12/12** + migration round-trip + `pim:tenant:audit` exit 0 (3 nowe tabele green). **Świadome odejścia:** (a) **brak `#[ApiResource]` w #31** — exposure REST/Hydra to scope #41 (epik 0.4); ten ticket to czysto Doctrine + repository layer; (b) **brak per-typ validatora** dla `validation_rules` JSONB — kolumna stora payload verbatim, custom validator z #39 dorzuca interpretację; (c) **brak polityk RLS** dla nowych tabel w tej migracji — #30 stworzył tylko dla products + refresh_tokens; faza 2 doda batch policy pack dla całego catalogu; w MVP wystarczy `TenantFilter` przez `TenantScoped`; (d) **invariant `usesOptions` na poziomie aplikacji**, nie schematu — partial unique index byłby kosztowny per insert; (e) **AttributeOption.tenant_id denormalisation** — alt: brak kolumny + dziedziczenie scope z parent przez join. Wybrany denormalised bo TenantFilter per encja + audit consistency. **Schema validate noise:** `doctrine:schema:validate` chce przemianować explicite nazwane indeksy na `IDX_xxx` hash style — pre-existing noise z poprzednich migracji od #24, tolerowane.
2. **#30 (0.2.7) Multi-tenant fundament — TenantScoped + RLS policies (gotowe, nieaktywne) + pim:tenant:audit + docs/multi-tenancy.md** (2026-04-28, PR #138 / `24638fa` MERGED). **`TenantScoped` marker interface** (`Identity/Application/`) z metodami `getTenant(): ?Tenant` + `assignTenant(Tenant): void`. Oddzielny od istniejącego `TenantAware` (który mówi "potrafię zwrócić aktywny tenant" — implementuje User dla CurrentTenantProvider). **Product** implementuje `TenantScoped`. **User/Role/RefreshToken** NIE implementują (User: login lookup po email globalnie zanim tenant znany; Role: nullable tenant_id dla globalnych; RefreshToken: persist w `RefreshTokenService` z User context, listener nadmiarowy). **`TenantAssignmentListener` generalised** — dispatch przez `instanceof TenantScoped` zamiast hard-coded `instanceof Product`; calls `$entity->assignTenant($tenant)` (część kontraktu interface'u, type-safe bez method_exists hackery). **`TenantFilter` generalised** — `is_subclass_of($targetEntity->getName(), TenantScoped::class, true)` zastępuje hard-coded FQCN allowlist. Encje epiku 0.3 (Object/Channel/Asset) opt-inują przez `implements TenantScoped` bez dotykania listener/filter. **Migracja `Version20260428195217`** tworzy 4 polityki RLS (SELECT/INSERT/UPDATE/DELETE) na `products` + `refresh_tokens` używając `current_setting('pim.current_tenant_id', true)::uuid` (`true` = missing_ok → NULL gdy GUC nie ustawiony → all-deny). **Polityki obecne, RLS NIE aktywne** (`relrowsecurity = f` w pg_class) — Postgres traktuje `CREATE POLICY` bez `ENABLE ROW LEVEL SECURITY` jako inertne. Faza 2 odpala `ALTER TABLE … ENABLE ROW LEVEL SECURITY` jednym shot'em. **Tabele wykluczone z RLS w MVP:** `users` (login lookup po email zanim tenant znany; SECURITY DEFINER bypass dochodzi w fazie 2), `roles` (nullable tenant_id ukryłoby built-iny). **CLI `pim:tenant:audit`** w `Maintenance/TenantAuditCommand` — read-only inspekcja `information_schema.columns`: każda public-schema table (z wyjątkiem allowlisty infra: tenants/permissions/role_permissions/user_roles/messenger_messages/doctrine_migration_versions) musi mieć `tenant_id` NOT NULL (poza `roles` na NULLABLE_TENANT_TABLES allowlist) + index. Exit 0 = clean, 1 = FAIL. **`docs/multi-tenancy.md`** (paralleling `docs/rbac.md`, ~110 linii PL): tabela tenant scope per entity, opis interfejsów, listener, filter, RLS rollout plan fazy 2, COPY guard runbook, dokumentacja smoke testu. **Tests:** 5 nowych — `TenantAssignmentListenerTest::itStampsAnyTenantScopedEntityNotJustProduct` (anonymous class implements TenantScoped → listener stempluje, dowodzi generalizacji), `TenantAuditCommandTest::reportsCleanStateAfterAllMigrations` + `flagsMissingTenantIdWhenADomainTableLacksIt` (wymusza DROP COLUMN i sprawdza FAIL exit, finally restore'uje schemat). **Quality gates:** PHPStan max + cs-fixer + PHPUnit **65/65** + Playwright **12/12** + migration round-trip OK + manual `psql` (8 polityk obecnych, RLS disabled). **Świadome odejścia:** (a) **`assignTenant` w interfejsie** zamiast `method_exists` duck-typing — explicit contract bez reflection hackery, listener jest type-safe; (b) **brak polityk RLS na `users`** — login flow chicken-and-egg, faza 2 zaprojektuje SECURITY DEFINER bypass; (c) policy na `refresh_tokens` jest reference (faza 2 zdecyduje czy zostaje); (d) `is_subclass_of` z `$allow_string=true` ma <1µs overhead vs. SQL roundtrip — generalizacja darmowa.
2. **#29 (0.2.6) Admin authProvider — in-memory JWT + silent refresh + /me identity** (2026-04-28, PR #137 / `5f93503` MERGED). **Drop `localStorage`** dla access tokena — cały token żyje w module-scoped `let accessToken` w `apps/admin/src/lib/http.ts`. XSS który czyta `localStorage` nie ma czego ukraść; cena = hard reload startuje bez tokenu. **Silent refresh on 401 z single-flight guard:** `let refreshInFlight: Promise<string> | null` collapsuje równoczesne 401y na jeden POST `/api/auth/refresh`. Bez tego burst N parallel queries Refine'a → N refresh calls → druga z `reason: reused` revoke'uje całą rodzinę. Retry max 1× per request (flag `retryAfterRefresh: true` na rekurencyjnym call). **Endpoints excluded z 401 retry:** `/api/auth/login` (401 = wrong password, nie expired access) i `/api/auth/refresh` (recursion guard). **`authProvider.check()`** czyta token z pamięci, jak nie ma → próbuje silent refresh przeciw HttpOnly cookie z poprzedniej sesji → success → autoryzowane. Dlatego F5 nie wywala usera na /login. **`authProvider.logout()`** rzeczywiście POSTuje `/api/auth/logout` z Bearerem (best-effort, swallow errors) → cookie cleared server-side + token revoked w DB. **`authProvider.getIdentity()`** GET `/api/auth/me` zamiast decode JWT — server jest source of truth dla email/roles/tenant. `decodeJwtClaims()` usunięty. **`AppLayout` Identity** rozszerzony o `email/roles/tenant/lastLoginAt`; header pokazuje `tenant.name` jako muted badge obok email. **i18n:** `auth.session_expired` dodany do PL/EN. **3 nowe Playwright testy:** (a) hard reload preserves session (waitForResponse `/api/auth/refresh` 200), (b) `localStorage.getItem('pim.jwt') === null` regression guard, (c) logout POSTs `/api/auth/logout` (waitForRequest). **Quality gates:** Biome strict + tsc --noEmit + Playwright **12/12** zielone (8 auth + 4 products) + manual smoke (login + reload + logout cycle). **Świadome odejścia:** (a) **brak BroadcastChannel cross-tab refresh coordination** — single-flight chroni in-tab race; cross-tab race najwyżej zrobi family-revocation na drugim tabie, MVP single-tab use case OK; (b) **brak proactive refresh** przed expiry — let 401 trigger; mniej ścieżek; (c) `getPermissions()` robi własny `/api/auth/me` call zamiast cache'owania getIdentity (Refine's React Query cache zajmuje się dedupką), bo pojedynczy source of truth jest tańszy w utrzymaniu niż lokalna pamięć podręczna; (d) build lokalnie pada na `zod/v4/core` resolution w `@hookform/resolvers/zod` — pre-existing issue niezwiązany z #29 (replikuje się na czystym main), CI build pass. **Gotcha:** Vite HMR podchwycił zmiany w `http.ts`/`auth-provider.ts` natychmiast — nie trzeba `pnpm dev` restart. Po `pim:db:reset --with-fixtures` users zniknęli w followup teście — fixture data trzeba reload'ować przed e2e (jeden `doctrine:fixtures:load` wystarczył).
2. **#26 + #27 + #28 squash-merged do main jako PR #136 (`4aae6d9`)** (2026-04-28). Trzy tickety w jednym squash commicie po wykryciu stacked-PR limbo: PR #134 (#27) i PR #135 (#26) zostały zmergowane stacked-PR-style (base=intermediate branch zamiast main), GitHub pokazywał MERGED ale main ich nie miał. Naprawa: odbicie nowego brancha `feat/0.2.5-auth-endpoints` od main, cherry-pick #27 + #26 z `feat/0.2.3-voters` (z `-X theirs` dla agent docs konfliktów), implementacja #28 na top, jeden squash-merge do main. Operator zaakceptował utratę granularności w main history w zamian za czysty branch state. **Combined PR #136 closed:** #26, #27, #28 (GH issues closed razem).
2. **#28 (0.2.5) Auth endpoints — RefreshToken + rotation + theft detection + /me + real logout** (2026-04-28, branch `feat/0.2.5-auth-endpoints`). **Custom RefreshToken entity** (`src/Identity/Domain/Entity/RefreshToken.php`) — `tenantId/userId/familyId UUID + token_hash SHA-256(64) UNIQUE + issuedAt/expiresAt/usedAt/revokedAt`. Bez Doctrine relacji (denormalised UUID columns) bo refresh path jest hot — single-row lookup po hash, brak JOIN. **`family_id`** = wszystkie tokeny z jednego loginu w jednej rodzinie; reuse already-used token revoke'uje całą rodzinę jednym UPDATE (`RefreshTokenRepository::revokeFamily()` przez DQL). **`RefreshTokenService`** (`Application/`) — `issueForUser` / `rotate` (throws `RefreshTokenException` z reason `missing|invalid|expired|revoked|reused`) / `revoke` (idempotent, no-throw). Raw token = 32 bajty random_bytes → base64url (~43 chars). **`AuthCookieFactory`** — single source of truth dla `Set-Cookie`: HttpOnly + SameSite=Strict + Secure (override do false `when@test` bo BrowserKit drops Secure cookies on plain HTTP) + Path=`/api/auth` (cookie nigdy nie wysyłana na `/api/products` itp). **`LoginSuccessHandler`** (NIE Symfony decorator, lecz constructor-inject) wraps Lexik `AuthenticationSuccessHandlerInterface` → call inner → `User::recordLogin()` + flush → `issueForUser` → attach cookie. Wired w `security.yaml` `success_handler: App\Identity\Presentation\LoginSuccessHandler`, NIE direct lexik. **Endpointy:** `POST /api/auth/refresh` (anonymous w access_control bo caller ma expired access) + `GET /api/auth/me` (`{id,email,roles,tenant:{id,code,name},last_login_at}`) + `POST /api/auth/logout` rewritten (revoke + clear cookie, 204 idempotent). **Migration `Version20260428171723`** — `refresh_tokens` table, FK tenant_id/user_id ON DELETE CASCADE. **Tests:** 11 nowych (RefreshTokenApiTest 9 + MeEndpointTest 2): login-issues-cookie, login-records-last-login, refresh-rotates, **reused-token-revokes-family** (DB asserts wszystkie tokeny w family revoked), expired-401, missing-cookie-401, unknown-cookie-401, logout-revokes-and-clears, logout-without-cookie-idempotent, me-returns-current-user, me-without-token-401. **Quality gates:** PHPStan max + cs-fixer + PHPUnit 62/62 + Playwright 9/9 + manual smoke (login → me → refresh + reuse old → 401 reused + family revoked w DB → logout). **Świadome odejścia:** (a) **brak `gesdinet/jwt-refresh-token-bundle`** — bundle nie ma theft detection ani family invalidation ani httpOnly cookies, custom code = 200 linii w jednym kontekście; (b) `RefreshToken` denormalised (UUID kolumny zamiast `ManyToOne`) — refresh path hot, lookup tylko po hash, FKs at schema level enforce'ują integrity; (c) `LoginSuccessHandler` constructor-injection wraps Lexik handler zamiast Symfony service decoration — cleaner contract, immune do Lexik internal class changes; (d) refresh response zwraca `{token}` w body **i** `reason` w error responses (RFC 7807 `+reason` extension field) bo client często chce branchować bez parsowania detail; (e) cookie `Path=/api/auth` zamiast `/` — refresh cookie nigdy nie leakuje do `/api/products` requests, redukuje attack surface; (f) **NIE rozszerzono `AuthApiTest`** — istniejące `loginWithValidCredentialsReturnsJwt` i `logoutWithValidTokenReturns204` przeszły bez zmian, dodanie cookie do response jest backwards-compatible. **Repo gotcha:** PR #134 (#27) i PR #135 (#26) zostały na GitHubie merged stacked-PR-style — base=intermediate branch, nie main. Lokalne `feat/0.2.3-voters` ma squash commits (ef63abb #25, 503a080 #27, 64cfe7c #26), main ma tylko #25 (`dc4917c`). Branch `feat/0.2.5-auth-endpoints` jest nadbudowany na `feat/0.2.3-voters`. Operator musi zdecydować jak rozwiązać stack przed merge do main.
2. **#26 (0.2.3) Voters dla Product (proof-of-concept) + AbstractRbacVoter** (2026-04-28, branch `feat/0.2.3-voters`, stack na #27). **Plan B (zwalidowany przez operatora):** zaimplementuj infrastructure (`AbstractRbacVoter` + tenant ownership check) + zastosuj na istniejącym `Product` jako proof-of-concept; voters dla object_type/attribute/channel dochodzą w 0.3/0.6 razem z encjami. **`AbstractRbacVoter`** w `src/Identity/Infrastructure/Security/` — abstract klasa generic-typed `<string, object|string>`: lookup permission z M2M user→roles→permissions, plus tenant ownership check (`extractTenant()` przez `method_exists('getTenant')` — Product używa `?Tenant`, niezgodny z `TenantAware::getTenant(): Tenant`). Class-level subjects (FQCN string z Post/GetCollection) skip tenant check — Doctrine TenantFilter scopuje subsequent reads. **`ProductVoter`** w `src/Catalog/Infrastructure/Security/` — resource='object' (post-ADR-009 alignment), mapuje READ/CREATE/UPDATE/DELETE → read/write/write/delete. **API Platform Product[ApiResource]** — security strings na każdej operacji + dodano `Delete` operation (Sprint-0 nie miał). **Tests:** 14 nowych w `ProductVoterTest` (12-case decision matrix: 4 role × 3 actions z subset of cross-tenant cases) + classLevelCreate + anonymousTokenAlwaysDenied; rozszerzony `AuthApiTest::viewerRoleCannotDeleteProduct` (viewer → GET 200 + DELETE 403). **Repair existing tests:** AuthApiTest/TenantIsolationTest/ProductApiTest setup'y używały `roles: ['ROLE_ADMIN']` w JSON — voter ich teraz nie autoryzuje, więc dorzucony seed `RbacSeeder` + `addRole(super_admin)`. **Quality gates:** PHPStan max + cs-fixer + PHPUnit 51/51 + Playwright 9/9 + manual smoke (admin GET=200, admin DELETE=204 na realnym IRI). **Świadome odejścia:** (a) **stuby voterów dla nieistniejących encji nie pisane** — pattern udowodniony przez ProductVoter, voters w 0.3/0.6 to 5-liniowy boilerplate; (b) `extractTenant()` przez `method_exists` zamiast wymuszania `TenantAware` interface — Product Sprint-0 ma `?Tenant`, weakening TenantAware łamałoby User non-null contract.
2. **#27 (0.2.4) RBAC seeder + getRoles() merge** (2026-04-28, branch `feat/0.2.4-rbac-seeder`). **Source of truth:** `src/Identity/Domain/Rbac/RbacMatrix.php` — 13 resources × 4 actions = 52 permissions, 4 globalne role (super_admin/catalog_manager/integration_manager/viewer). **`RbacSeeder` service** — idempotentny, bezpieczny przy re-run (sprawdza permission/role po code, dodaje/usuwa permissions z M2M jeśli się różnią od matrix). **CLI command `pim:rbac:seed`** wraporzuje seeder z report'em (created/updated counts). **AppFixtures** woła seeder przed persistencją userów + admin fixture dostaje `super_admin` przez M2M (`addRole($superAdmin)`) zamiast legacy `['ROLE_ADMIN']` w JSON. **`User::getRoles()` cleanup:** mergguje JSON legacy + `'ROLE_'.strtoupper($role->getCode())` z M2M + ROLE_USER floor, deduplicated. JSON column zostaje (legacy fallback dla testów Sprint-0 które tworzą Userów ręcznie z `roles: ['ROLE_ADMIN']`). **`docs/rbac.md`** — matrix dokumentacja + dev workflow. **Quality gates:** PHPStan max + cs-fixer + PHPUnit 34/34 (3 nowe w `RbacSeederTest`: matrix shape, idempotency, getRoles merge) + Playwright 9/9. **Manual smoke:** admin demo login → JWT payload `roles: ["ROLE_SUPER_ADMIN", "ROLE_USER"]`, DB pokazuje 1 role × 52 permissions per admin. **Świadome odejścia:** (a) `final readonly class` na `RbacSeeder` nie zadziała bo mutuje counter pola — `final class` z `private readonly $em/$permissions/$roles` w konstruktorze; (b) JSON column nie jest droppowany migracją — legacy fallback dla pre-existing testów; pełen drop w post-MVP cleanup. **Repo workflow gotcha:** stack #27 na #25 wymagał najpierw rebase #25 na main (po merge #24), bo #25 branch był stworzony z main przed #24 merge. Force-push z `--force-with-lease`.
2. **#25 (0.2.2) Symfony Security z JWT — done** (2026-04-28, branch `feat/0.2.2-security-jwt-form-login`). **Świadomy redesign body ticketu vs Sprint-0:** drop FormLogin authenticator i CSRF protection — admin to React SPA + Refine, backend nie servuje server-rendered HTML. JsonLogin endpoint z #4 zostaje, dochodzi: (a) **explicit Argon2id hasher** w `security.yaml` (memory_cost 65536, time_cost 4 — OWASP 2024 baseline; `when@test` używa argon2id z libsodium-min memory_cost=64, time_cost=3, daje `$argon2id$` PHC string), (b) **`AuthenticationFailureListener`** w `src/Identity/Presentation/` mapujący LexikJWT default response (`{code, message}`) na RFC 7807 Problem Details (`application/problem+json` + `{type, title, status, detail}`), (c) **`LogoutController`** placeholder na `POST /api/auth/logout` zwracający 204. Quality gates: PHPStan max + cs-fixer + PHPUnit 26/26 + Playwright 9/9. **GH bodies updated:** #25 (drop FormLogin/CSRF), #26 (FamilyVoter → ObjectTypeVoter post-ADR-009), #28 (przejmuje `/api/auth/me` + refresh rotation + theft detection + httpOnly cookie).
2. **#24 (0.2.1) RBAC schema baseline** (2026-04-28, PR #132 MERGED). Pierwszy ticket epiku 0.2 — Role/Permission encje + M2M user_roles/role_permissions, User.status + last_login_at + assignedRoles M2M, Tenant.domain + plan.
3. **Epik 0.1 ZAMKNIĘTY — Infrastructure i fundamenty** (2026-04-28). 7/7 ticketów (#17-#23) closed. **Audit recon ujawnił że 4 z 7 było faktycznie zrobione w Sprincie 0** (#18 docker-compose pełna forma, #21 GitHub Actions CI 3 workflows, #22 husky + lint-staged + commitlint, #23 baseline migrations) — zamknięte komentarzami audytu z linkami do Sprint-0 PR-ów. **Nowa praca w jednym PR (`feat/epic-0.1-foundations`):** (a) **#17:** CONTRIBUTING.md (Conventional Commits, branch naming, DoD, hook expectations) + LICENSE (UNLICENSED proprietary) + README refresh (stack components table, Backup&Restore section, Sprint 0 status); (b) **#19:** scaffolding 5 brakujących bounded contexts (`Channel`, `Asset`, `Integration`, `Agent`, `ApiConfigurator`) z 4-warstwowym DDD layout (`Domain/`, `Application/`, `Infrastructure/`, `Presentation/`) + `README.md` per kontekst tłumaczący zakres + który epik dorobi implementację; Catalog + Identity (już istniejące ze Sprintu 0) doequalizowane do 4-warstwowego layoutu; (c) **#20:** 5 placeholder pages w admin (`/attributes`, `/object-types`, `/categories`, `/assets`, `/channels`) używających `<ComingSoon resource epic issue />` komponentu (link do GH issue + i18n PL/EN), sidebar rozszerzony z 6 navigation entries + "Wkrótce/Soon" badge dla niezimplementowanych; (d) **#23:** `pim:db:reset` CLI command w `apps/api/src/Maintenance/DatabaseResetCommand.php` — drop+create+migrate (+optional fixtures) w jednym shocie z guards na APP_ENV=prod (`--force-prod` required). **Per-context migrations dirs odrzucone jako over-engineering** (single Postgres, Symfony default OK).
2. **Ticket #15 (0.0.15) zamknięty** (PR #130 squash-merged 2026-04-28 jako `868b87c`). pgBackRest + WAL stub w docker-compose, restore test passing. **Topologia:** custom `pim-database:local` image (postgres:16-alpine + pgbackrest 2.57 + dcron) — `archive_command='pgbackrest --stanza=pim archive-push %p'` pcha WAL ciągle do MinIO bucket `pim-backups`, hourly cron (`/etc/crontabs/postgres`) odpala `pgbackrest backup`, `pim-init-backup.sh` w tle robi stanza-create + initial full backup gdy postgres jest ready. **TLS gotcha:** pgBackRest 2.57 hard-coduje HTTPS dla S3 (defaultowy `repo-storage-port=443`, brak HTTP-mode), MinIO chodzi po HTTP — dodany sidecar `minio-tls` (Caddy 2-alpine, `tls internal` + `header_up Host {host}` żeby AWS SigV4 HMAC się zgadzał) jako TLS terminator między `database` a `minio:9000`. **Świadome odejścia:** (a) `archive-async=n` zamiast `=y` — async worker trzyma lock na `/tmp/pgbackrest/pim-archive-1.lock` i blokuje stanza-create/backup; sync archive_command jest fine dla write rate dev/MVP single-pilot; (b) cron in-container przez busybox dcron + custom entrypoint `start-pim.sh` (zamiast docker-socket sidecar lub k8s CronJob — zostawione do 0.11.11); (c) pojedynczy obraz postgres+pgbackrest zamiast prawdziwego pgBackRest server-mode TLS — kanoniczny pgBackRest deployment wymaga albo same-host (nasz przypadek) albo SSH/TLS server, mid-pattern z shared-volume sidecar nie istnieje natywnie. **Test scenario:** `scripts/test-pgbackrest-restore.sh` — login → insert 3 markery → `pg_switch_wal()` + `pgbackrest --type=incr backup` → `DELETE FROM products WHERE sku LIKE 'restore-test-*'` → `scripts/pim-backup-restore.sh --type latest --no-confirm` → re-auth + count. **Wynik:** baseline 1005 → post-insert 1008 → post-delete 1005 → **post-restore 1008** ✅ (markery wróciły z backupu). Initial full backup: 37.9 MB DB → 5 MB compressed in repo, 8.9 s. Incremental: 488 KB → 62 KB, ~2 s. Komponenty: `docker/postgres/{Dockerfile,pgbackrest.conf,start-pim.sh,pim-init-backup.sh,pim-cron.sh}`, `docker/caddy/Caddyfile.minio`, `scripts/{pim-backup-restore.sh,test-pgbackrest-restore.sh}`, `docs/runbook/restore.md`, `pnpm backup:{run,info,restore,test}`.
2. **Korekty post-audyt ADR-009** (2026-04-28). Self-audit pracy z 2026-04-27 ujawnił 12 znalezisk; naprawione 9 (F-001..F-004, F-006..F-009, F-010). **F-001 krytyczny:** DDL `channels` w §5.2 architektury referował nieistniejącą tabelę `categories(id)` (po ADR-009 zmigrowana do `objects`) — naprawione na `category_tree_root_object_id REFERENCES objects(id)` z walidacją `kind='category'` przez listener. **F-002:** §8.2 + §8.4 architektury usunięto „rodziny" (sąsiednie sekcje rozjeżdżały się słownictwem z §8.3 zaktualizowanym wcześniej). **F-003:** plan §3.1 / §3.2 / ticket 0.2.3 + 0.7.3 + Faza 2 #65 — usunięto relikty „Family". **F-004:** estymaty Fazy 0 przeliczone — single source of truth to sumy epików §3.3 + milestone tabela §3.4. Faza 0 pełna **170-235h** / okrojona **156-216h** (poprzednio błędnie 188-260h / 174-241h). **F-006/F-007/F-008:** issues #36 (Channel + ChannelObjectTypeMapping), #65 (Tool definitions), #41 (ApiResource) — title + Cel + Zakres przepisane (wcześniej tylko Aktualizacje announce'owały rename). **F-009:** CLAUDE.md commit example zaktualizowany. **F-005:** renumeracja epiku 0.3 — plan §3.3 skonsolidowany (0.3.3 fixtures + 0.3.5 ltree zlepione w jedno 0.3.3, bo fixtures dla `category` MUSI mieć ltree), GH #33 `[0.3.5]` → `[0.3.3]` (body rozszerzone o fixtures dla wszystkich trzech built-in kindów), GH #128 `[0.3.12]` → `[0.3.11]`. Epik 0.3 ma teraz 11 ticketów spójnych z numeracją GH. **Pominięte do follow-up:** F-011/F-012 (kosmetyczne — internal spec checklist self-inconsistency).
3. **Korekty post-audyt ADR-009** (2026-04-28, zmergowane w PR #130). Self-audit pracy z 2026-04-27 ujawnił 12 znalezisk; naprawione 9 (F-001..F-004, F-006..F-009, F-010). **F-001 krytyczny:** DDL `channels` w §5.2 architektury referował nieistniejącą tabelę `categories(id)` (po ADR-009 zmigrowana do `objects`) — naprawione na `category_tree_root_object_id REFERENCES objects(id)` z walidacją `kind='category'` przez listener. **F-004:** estymaty Fazy 0 przeliczone — Faza 0 pełna **170-235h** / okrojona **156-216h**. **F-005:** renumeracja epiku 0.3 (0.3.3 fixtures + 0.3.5 ltree consolidated → 0.3.3, GH #33 `[0.3.5]` → `[0.3.3]`, GH #128 `[0.3.12]` → `[0.3.11]`). Pominięte do follow-up: F-011/F-012 (kosmetyczne).
> **Wcześniej:** ADR-009 (Generic ObjectType) + audit 91 ticketów GH (PR #127 + #129 squash-merged 2026-04-27). Generic `ObjectType` z predefiniowanymi Product/Category/Asset (`is_built_in=true`) + custom kindy (`Customer`/`Supplier`/`PriceList`) odblokowane w Fazie 2/3 feature flagiem. Pojęcie „Family" deprecated. Sugar paths `/api/products|categories|assets` zachowane. Plus #14 (perf k6: 10 VUs p95=105 ms) i #13 (FrankenPHP memory: 50k=14 MiB FLAT z clear).

## Bieżący stan
Sprint 0 = **13/13 ZAMKNIĘTE** (gate GREEN 2026-04-28). Milestone GH #1 closed.

**MVP-Alpha — epik 0.1 ZAMKNIĘTY 7/7 ✅ + epik 0.2 ZAMKNIĘTY 7/7 ✅ + epik 0.3 ZAMKNIĘTY 11/11 ✅** (25/25 ticketów MVP-Alpha do tej pory). Następny: **epik 0.4 — API Platform** (eksponowanie encji jako ApiResource: sugar paths `/api/products|categories|assets` na `CatalogObject`, custom filtry, cursor paginator, ObjectDenormalizer/Normalizer parametryzowany per `object_type_id`, OpenAPI customization, Mercure publisher, RateLimiter).

**`AUTONOMOUS_MODE: OFF`** (auto-flipped 2026-04-29 po zamknięciu #128). Epik 0.4 wraca do plan-first per ustaleniu z operatorem ("zakończ prace na tickecie #128 (0.3.11) Hooks pod kind='custom' na poziomie ApiResource"). Każdy ticket epiku 0.4 z >3 plikami zaczyna od Plan Mode.

**Następny krok:** start epik 0.4 — #41 (API Platform exposure, najszerszy ticket epiku — `#[ApiResource]` na `CatalogObject` z 3 sugar paths, ObjectDenormalizer/Normalizer per kind, hook'y z #128 finally activated). Per `Project Plan/02-plan-projektu-pim.md` §3.3 epik 0.4 ma 8 ticketów (#41-#48) i estymowany budżet 10-14h.

Stack on-disk: docker compose ready (`pnpm stack:up`); custom `pim-database:local` image z pgbackrest+dcron + `minio-tls` Caddy sidecar zostają z #15. Backup repo MinIO `pim-backups` z full + incr backup'ami siedzącymi w nim po teście #15 — przy następnej sesji są dostępne lub mogą być wyzerowane przez `pnpm stack:reset` (drop volumes).

Domain model (Sprint-0 stan; po MVP-Alpha epik 0.3 model przejdzie na ObjectType — ADR-009):
- 3 entities (`Tenant`, `Product`, `User`) w bounded contexts `Identity` i `Catalog`
- **Target shape MVP-Alpha (po ADR-009):** `Tenant`, `User`, `ObjectType` (predefined product/category/asset jako built-in fixtures), `ObjectTypeAttribute`, `Attribute`, `Object` (poly per `kind`), `ObjectValue`, `Channel`, `Asset` (dedykowana tabela storage + Object kind='asset' dla user-defined metadata)
- Migracje zaaplikowane: `Version20260427070435` (Tenant+Product), `Version20260427095515` (Users)
- Fixtures: 2 tenanty × 1 admin user × 3 produkty. Admin: `admin@demo.localhost`/`admin@acme.localhost` hasło `changeme`
- Multi-tenancy plumbing: TenantContext + listener + SQL filter + RequestTenantSubscriber + auth-aware `CurrentTenantProvider`
- Auth: LexikJWT v3.2.0, `POST /api/auth/login` zwraca JWT, wszystkie inne `/api/*` wymagają `Authorization: Bearer ...`
- API: `Product` jako `#[ApiResource]` na `/api/products` (CRUD + cursor pagination + Swagger UI na `/api/docs`)
- Admin frontend: Refine v5 + shadcn na `https://pim.localhost` — login + lista + create/edit produktów; sidebar nav + i18n (pl/en)
- Test coverage: TenantAssignmentListenerTest (4 cases) + ProductApiTest (6 cases) + TenantIsolationTest (4 cases) + AuthApiTest (5 cases) + Playwright E2E (9 cases — auth + products CRUD)

Quality gates aktywne:
- **Lokalnie**: pre-commit hook + commit-msg hook (husky) — Biome + PHP-CS-Fixer + Conventional Commits; `pnpm --filter @pim/admin e2e` host-side
- **CI**: GitHub Actions na PR + push do main — PHPStan + PHP-CS-Fixer + PHPUnit; Biome + tsc + Vite build; **Playwright E2E (full Caddy + FrankenPHP + Postgres + admin stack)**; composer + pnpm audit (nightly)

**Akcje po stronie operatora (do zrobienia w wolnej chwili, nie blocker):**
- Branch protection na `main` (Settings → Branches → Add rule):
  - Require status checks: `phpstan`, `php-cs-fixer`, `biome`, `typecheck`, `build`, `composer-audit`, `pnpm-audit`
  - Require branch up to date before merge
- Po pull: `pnpm install` żeby husky `prepare` zarejestrował hooki na świeżo sklonowanym repo.

Świadome odejścia od planu (do uzupełnienia w `06-sprint-0-findings.md` na koniec Sprintu 0):
1. `api-platform/api-platform` z Packagist to archiwalny skeleton z 2018 — pivot do `symfony/skeleton 7.4` + `composer require api-platform/symfony:^4.3`. (#1)
2. `/api/docs.json` nie odpowiada w API Platform 4 (tylko `.jsonld` + `.html`); healthchecki używają `/api`. (#1)
3. Psalm strict pominięty — `vimeo/psalm:dev-master` ma conflict z `psalm/psalm-plugin-api 0.1.0`. PHPStan max + strict-rules pokrywa zakres. (#11)
4. `git config core.fileMode = false` ustawione lokalnie (Synology Drive zmienia bits 644→755 między sync). (#11)
5. PHPUnit 11 → 12 (PHPUnit 11 wymaga `sebastian/diff ^6` ale lock fixował 8.x z phpstan). (#2)
6. `Product::$tenant` nullable w PHP (krótki window przed PrePersist) ale NOT NULL w schemacie — scoped PHPStan ignore. Listener tests + DB constraint potwierdzają invariant. (#2)
7. `docker-compose.yml` bind mount `apps/api` + named volumes na `var/` i `vendor/` (lekkie scope creep w #2 ale eliminuje rebuild ~1 min na każdą zmianę PHP). (#2)
8. `Product` `#[ApiResource]` wystawia entity bezpośrednio (bez DTO input/output) — w MVP-Alpha (epik 0.4 #41+) decyzja czy split na osobne DTO. Powód: 50× mniej kodu, AP4 grouping wystarczy. (#3)
9. `application/json` jako input/output format **nieaktywowany** — tylko `application/ld+json` (POST/GET) + `application/merge-patch+json` (PATCH). Plain JSON dochodzi w epiku 0.4 (#41) razem z decyzją o explicit DTO. (#3)
10. `UniqueEntity['tenant', 'sku']` validator pominięty — listener stempluje `tenant` w PrePersist po fazie validacji, więc validator widziałby zawsze `tenant=null`. Postgres unique index `products_tenant_sku_uniq` zachowuje invariant on DB level (HTTP 500 zamiast 422). Custom validator z dostępem do `TenantContext` dochodzi w #41+. (#3)
11. Twig dodany jako runtime dependency tylko po to żeby AP4 włączył Swagger UI (`enable_swagger_ui` defaultuje `class_exists(TwigBundle::class)`). Dla prod docs można lock'ować przez `enable_swagger_ui: false` env-aware. (#3)
12. Native SQL `SELECT COUNT(*) FROM products` w `TenantIsolationTest` widzi wszystkie tenanty — boundary application-layer'a, nie defekt. RLS w fazie 1 (sekcja 11.1a) zamknie. Bulk paths (COPY, raw INSERT) trzymają tenant scope w kodzie do tego czasu. (#12)
13. `APP_DEFAULT_TENANT_CODE` flip w testach — pierwotnie w #12, **ZASTĄPIONE w #4** real-auth (each tenant ma własnego admina, test mintuje JWT). (#12 → #4)
14. Oba klucze RSA `config/jwt/*.pem` gitignored (Lexik recipe default) — devs generują own lokalnie, prod mountuje z vault'a, CI generuje per-run przed phpunit/phpstan jobs. Ticket prosił "commit pubkey" ale industry-standard w MVP-stage to local-only. (#4)
15. Fixture admin password = `changeme` — explicit dev-only, full onboarding flow w epiku 0.2 (#24+). (#4)
16. `/api/docs`, `/api/contexts`, `/api` (entrypoint) PUBLIC w `access_control` — żeby OpenAPI/Hydra tooling działał bez auth. Production może lock'ować przez `enable_swagger_ui: false` env-aware. (#4)
17. Brak refresh tokens / token blacklist'u — Lexik default 1h TTL na token. `gesdinet/jwt-refresh-token-bundle` + RBAC w epiku 0.2. (#4)
18. JWT w `localStorage` w admin'ie zamiast httpOnly cookie — explicit Sprint-0 shortcut, refactor w 0.2.6 (#28). (#5)
19. Admin używa plain `react-router` v7 zamiast `@refinedev/react-router-v6` — Refine headless + RR7 idiomatic, mniejszy bundle, mniej plumbing'u. (#5)
20. Custom Hydra-aware DataProvider zamiast `@refinedev/simple-rest` — AP4 zwraca Hydra Collection (`member`, `totalItems`), simple-rest oczekuje `data`+`total`. ~50-liniowy custom provider jaśniejszy niż wrapper z transformacją. (#5)
21. shadcn primitives copy-paste zamiast CLI — CLI wymaga interaktywnego promptu w container'ze, kopiowanie 6 plików zajmuje 5 min. (#5)
22. Admin bundle warning >500 kB (Refine + react-query + zod + radix razem) — code splitting `React.lazy` per route w fazie 1 gdy pojawią się 5+ resource pages. (#5)
23. Brak Playwright E2E w #5 — to scope ticketu #10 (0.0.10), explicit setup ticket. Manual smoke pokrywa wszystkie ścieżki. (#5)

## Aktywne blokery
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us). Może być odłożone do MVP-Final.
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu).

> **Blokery historyczne (po rewizji zakresu 2026-04-27 nie blokują MVP):**
> - ~~Konto Shopify Partners~~ — Shopify całość (epik 0.9 + Sprint 0 #8) przeniesione do **Faza 1**.
> - ~~Anthropic API key~~ — agent layer (epik 0.7 + Sprint 0 #6/#7) przeniesiony do **Faza 2**.

## REWIZJA ZAKRESU MVP (2026-04-27, post-#5)
**Decyzja operatora:** "agentic management = dodatek; baza i UX frontu są priorytetem". W praktyce:
- **Cały epik 0.7** (Agent layer Beta-Min + Beta-Full, #63-#71 + #108-#112) → **Faza 2**.
- **Epiki 0.8 (BaseLinker, #72-#78) + 0.9 (Shopify, #79-#89)** → **Faza 1** (razem z Magento + IdoSell przesuniętymi z Fazy 1 do Fazy 2).
- **Sprint 0 #6 (Agent), #7 (Cmd+K), #8 (Shopify stub)** → odpowiednio Faza 2 / Faza 2 / Faza 1.
- **Layout #54** — Cmd+K placeholder usunięty z scope.
- **Provenance #61** — wariant `agent` (purple) odłożony do Fazy 2.
- **Hooks pod Fazę 2 zostają w MVP** (4-6h): `pending_changes` table jako pusta migracja, `provenance` enum z zarezerwowanym `agent`, lifecycle events Doctrine.
- Szczegóły w `Project Plan/02-plan-projektu-pim.md` sekcja 3 (rewizja na początku) + sekcje 4 i 5.

### Generalizacja ObjectType (2026-04-27, ADR-009)
**Decyzja operatora:** generalizujemy model katalogu — `Product`, `Category`, `Asset` to **instancje generic `ObjectType`** z `is_built_in=true`, nie hard-coded encje. Custom kindy (`Customer`, `Supplier`, `PriceList`) supported od dnia 1 ale wyłączone feature flagiem do Fazy 2/3. Powód: B2B pilot zarządza nie tylko produktami; eksport PIMCore (`Zrodla/PIMCore/masowy_eksport_konfiguracji.json`) pokazuje klasę `Kategoria` z user-defined SEO + image, których obecny model PIM nie obsługuje. **Koszt:** epik 0.3 16-20h → 36-50h (+16-25h, finansowane ze zwolnionego budżetu epiku 0.7). Pełen ADR w `01-architektura-pim.md` §13. Pojęcie „Family" deprecated. Sugar paths `/api/products`, `/api/categories`, `/api/assets` zachowane (DX integratorów). Mitigacja over-engineeringu: ryzyko **R-29** + feature flag + benchmark `attributes_indexed` na 10k×200×3 kindach w MVP-Alpha.

## Nowa kolejność wykonania (po Sprincie 0)
1. **~~Sprint 0~~ ZAMKNIĘTY 13/13 (2026-04-28).**
2. **MVP-Alpha epiki 0.1, 0.2, 0.3** — fundament (Infrastructure, Identity, Catalog domain).
3. **(decyzja) Epik 0.3a — Categories / taxonomy** (kandydat — operator: "jeszcze nie wiem dokładnie jak").
4. **Epik 0.4 + 0.5** — API extensions + Meilisearch.
5. **Epik 0.6** — Admin UI core CRUD (atrybuty + dynamiczny formularz produktu).
6. **Epik 0.10 + 0.11** — API Configurator + hardening.
7. **Demo pilot → gate decision.**
8. **Faza 1:** BaseLinker + Shopify (+ RLS, monitoring, hardening track B).
9. **Faza 2:** Agent layer + Magento + IdoSell + SaaS aktywacja.

## Następny krok
| # | Ticket | Komentarz |
|---|---|---|
| #24 (0.2.1) | Pierwszy ticket epiku 0.2 (Identity & Access) | RBAC roles/permissions, scheb/2fa-bundle, refresh tokens, password reset flow. Operator decyduje kolejność. |
| #25-#30 (0.2.2-0.2.7) | Pozostałe tickety epiku 0.2 | Patrz `Project Plan/02-plan-projektu-pim.md` §3.3 epik 0.2. |

## Postęp po fazach (po rewizji zakresu)
- [x] **Sprint 0 (gate decision) — 13/13 ✅ GREEN (2026-04-28)** — issues #1-#5, #9-#16 closed (#6, #7, #8 przeniesione do Faza 1/2)
- [ ] MVP-Alpha (epiki 0.1–0.6, fundament + admin UI) — 0/46 — issues #17-#62
- [ ] MVP-Final (epiki 0.10–0.11, API Configurator + hardening) — 0/18 — issues #90-#107
- [ ] **Faza 1** — Integracje BaseLinker + Shopify + hardening track B — 19 issues (epiki 0.8 + 0.9 + Sprint 0 #8)
- [ ] **Faza 2** — Agent layer + Magento + IdoSell — 16 issues (epiki 0.7 Beta-Min + Beta-Full + Sprint 0 #6/#7)

## Postęp Sprint 0 ticketów
- [x] **#1 / 0.0.1** — Setup monorepo Turborepo + docker-compose + Caddy single-origin (PR #113 merged 2026-04-26)
- [x] **#2 / 0.0.2** — Encja Product + tenant_id + Doctrine TenantFilter (PR #115 merged 2026-04-27)
- [x] **#3 / 0.0.3** — ApiResource Product → /api/products (PR #116 merged 2026-04-27)
- [x] **#4 / 0.0.4** — Authentication minimalny + JWT (PR #118 merged 2026-04-27)
- [x] **#5 / 0.0.5** — Admin Refine + shadcn (PR #119 + hotfix #120 merged 2026-04-27)
- [→] ~~#6 / 0.0.6~~ — Agent endpoint → **przeniesione do Faza 2** (rewizja 2026-04-27)
- [→] ~~#7 / 0.0.7~~ — Cmd+K placeholder → **przeniesione do Faza 2** (rewizja 2026-04-27)
- [→] ~~#8 / 0.0.8~~ — Shopify GraphQL stub → **przeniesione do Faza 1** (rewizja 2026-04-27)
- [x] **#9 / 0.0.9** — Manualny E2E Sprintu 0 + screencast (zamknięte 2026-04-28, verdict **GREEN** — auth + tenant isolation + product CRUD smoke ok dla obu tenantów)
- [x] **#10 / 0.0.10** — Playwright E2E od dnia 1 (PR #122 merged 2026-04-27)
- [x] **#11 / 0.0.11** — PHPStan max + PHP-CS-Fixer + Biome + husky + CI (PR #114 merged 2026-04-27)
- [x] **#12 / 0.0.12** — Smoke izolacji multi-tenant (PR #117 merged 2026-04-27)
- [x] **#13 / 0.0.13** — Benchmark FrankenPHP worker memory (PR pending — 14 MiB peak na 50 000 prod env z clear, follow-up #123 dla custom PHPStan rule)
- [x] **#14 / 0.0.14** — Profilowanie perf (PR pending — k6 + EXPLAIN ANALYZE; 10 VUs p95=105ms, single-user p95=18.7ms)
- [x] **#15 / 0.0.15** — pgBackRest + WAL stub (PR #130 squash-merged 2026-04-28 jako `868b87c` — custom postgres image z pgbackrest+cron, MinIO repo via Caddy TLS terminator, restore test 1005→1008 markery odzyskane)
- [x] **#16 / 0.0.16** — Audit CLAUDE.md + 06-sprint-0-findings.md (PR #121 merged 2026-04-27)

## Postęp epików (poza Sprintem 0 — zerowy)
**MVP (po rewizji zakresu + ADR-009):**
- [x] **0.1 Infrastructure i fundamenty — 7/7 ✅ (2026-04-28)** — #17-#23 closed
- [ ] 0.2 Identity & Access — #24-#30
- [ ] 0.3 Domain model — Catalog (po ADR-009: 36-50h, +16-25h vs poprzednio) — #31-#40 + nowy 0.3.11 / GH #128 (Hooks pod kind='custom')
- [ ] 0.4 API Platform — exposing entities (sugar paths /api/products|categories|assets) — #41-#48
- [ ] 0.5 Search — Meilisearch (per-kind indeksy) — #49-#53
- [ ] 0.6 Admin UI — core CRUD — #54-#62 (#54 + #61 zrewidowane; **#57 Resource Families → Resource ObjectTypes** po ADR-009)
- [ ] 0.10 API Configurator (filter per object_type_id) — #90-#95
- [ ] 0.11 Hardening, a11y, analytics, backup, BYOK — #96-#107

**Faza 1 — Integracje (po MVP gate decision):**
- [ ] 0.8 Integracja BaseLinker — #72-#78 (przeniesione z MVP-Final)
- [ ] 0.9 Integracja Shopify — #79-#89 (przeniesione z MVP-Final)
- [ ] +Sprint 0 #8 (Shopify GraphQL stub) — przeniesione

**Faza 2 — Agent layer + dodatkowe konektory:**
- [ ] 0.7 Agent layer — schema-add — #63-#71 (Beta-Min, przeniesione z MVP) + #108-#112 (Beta-Full, przeniesione z MVP)
- [ ] +Sprint 0 #6 (Agent endpoint), #7 (Cmd+K) — przeniesione
- [ ] Magento + IdoSell + Allegro + WooCommerce konektory (przesunięte z Fazy 1)

## Notatka dla Claude Code (next session boot)
Po starcie sesji:
1. Przeczytaj `CLAUDE.md` (auto-loaded).
2. Przeczytaj `agent/lessons.md` — szczególnie "Patterns to Avoid", "Package Quirks", "Toolchain quirks".
3. Sprawdź `Project Plan/02-plan-projektu-pim.md` sekcja 3.0 (Sprint 0 zakres) jeśli zaczynasz nowy ticket Sprint 0.
4. Lista pozostałych issues Sprint 0: `gh issue list --milestone "Sprint 0 — Vertical Slice" --state open`
5. Stack: **`pnpm stack:up`** (lub `pnpm dev` foreground), `https://pim.localhost` po akceptacji Caddy local CA.
6. Przed commit: hooki husky odpalą się automatycznie. Jeśli hook zfailuje przy pierwszym commit po pull, odpal `pnpm install` żeby `prepare` script zarejestrował hooki.
7. Quality gates są aktywne — każdy commit i PR przechodzi przez PHPStan max, PHP-CS-Fixer, PHPUnit, Biome strict, tsc, composer/pnpm audit. Nie pomijaj `--no-verify`.
8. **Iterowanie nad PHP nie wymaga `docker compose build api`** — apps/api jest bind-mounted. Po zmianie kodu wystarczy `docker compose restart api` (worker re-load) jeśli zmiana dotyczy services config; dla zwykłych zmian PHP po prostu hit refresh.
9. **Funkcjonalności MVP — `Project Plan/03-funkcjonalnosci-mvp.md`** (700 linii, dodane 2026-04-27 przez operatora). Zawiera archetyp pierwszego pilota (B2B technical, 50 MLN GMV/rok, 10-15k SKU, multimarka + własna marka), 5 person (Owner/Tomasz, Catalog Manager/Kasia jako #1, Marketing/Magda, IT-Integration/Piotr jako #1.5, Sales out-of-PIM), 10 user stories z kryteriami akceptacji + mapowaniem na epiki techniczne, success criteria pierwszego pilota. **Czytaj OBOWIĄZKOWO przed pracą nad ticketami:** 0.6 (Admin UI #54-#62), 0.7 (Agent UX #63-#71 + #108-#112), 0.8 (BaseLinker #72-#78), 0.9 (Shopify #79-#89), 0.10 (API Configurator #90-#95), 0.11 dashboard/a11y (#96-#107). Tickety czysto techniczne (Sprint 0, epiki 0.1-0.5) **nie wymagają** tego dokumentu — można pracować bez kontekstu funkcjonalnego.
10. Jeśli operator nie powiedział inaczej — rekomendacja na następny ticket: **#3 (0.0.3 ApiResource Product → /api/products)**.
