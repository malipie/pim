# Domena G — Integralność danych (audyt 2026-06)

Postawa adwersarska. Każdy finding ma dowód: plik:linia, cytat kodu lub output SQL z żywej bazy `pim`.
Tryb read-only — wykonano wyłącznie `SELECT` na bazie i odczyt kodu/migracji.

## Metodyka — co i jak sprawdzono

1. **Migracje (down/destrukcyjność).** Odczyt 20 najnowszych migracji z `apps/api/migrations`
   (`Version20260602100000` … `Version20260615145641`) + 4 starsze destrukcyjne wskazane
   tropem (`Version20260605100000`, `Version20260530120000`, `Version20260531120000`). Sprawdzono
   obecność i kompletność `down()`, lossy reverse, `throwIrreversibleMigrationException`.
   Surowy `migrations-status.txt`: 97 wykonanych, 0 nieosiągalnych, „at latest”.
2. **FK + ON DELETE.** Analiza `raw/db-fk-ondelete.txt` (115 FK). Skrzyżowanie RESTRICT/SET NULL/CASCADE
   z rzeczywistymi ścieżkami usuwania w kodzie: `DeleteAttributeHandler`, `ObjectTypeService::delete`,
   `DoctrineObjectCategoryRepository::replaceForProduct`.
3. **Transakcyjność.** Czytano cały `ImportRunHandler` (1751 linii) — pętla chunked flush/clear,
   checkpoint, dispatch rebuildu; `ImportRollbackService::rollback`; `DomainEventDispatcher`
   (kiedy dispatch względem flush/commit); `config/packages/messenger.yaml` (transporty,
   `doctrine_transaction` middleware, failure_transport).
4. **Współbieżność.** Grep `version="true"` (optimistic lock) w mapowaniach ORM (60 plików);
   `UpdateCatalogObjectHandler` + `RebuildAttributesIndexedHandler` (egzekwowanie wersji);
   unikalność kodów/identyfikatorów — `unique-constraint` w XML + partial unique index w migracjach;
   **weryfikacja empiryczna** istnienia indeksów + triggera w żywej bazie (`pg_indexes`, `pg_trigger`).
5. **Drift attributes_indexed.** Lektura `AttributesIndexedSyncListener` (sync) + `AttributesIndexedRebuilder`
   + `RebuildAttributesIndexedHandler` (async, retry) + komendy `pim:catalog:recalculate-completeness`.
   **Empiryczny test driftu** zapytaniem porównującym global `object_values` z kluczami `attributes_indexed`
   na żywych danych.

## Czego NIE dało się sprawdzić (luki audytu)

- **Empiryczny migrate→rollback** każdej migracji (zwłaszcza lossy `down()`): zakazane (read-only,
  brak prawa do `migrate`/`ALTER`). Ocena `down()` jest statyczna z kodu — nie wykonana.
- **Zachowanie pod realną współbieżnością** (dwóch userów / dwóch workerów jednocześnie):
  nie odpalono testu obciążeniowego ani równoległych requestów. Wnioski o race wynikają z analizy kodu.
- **Czy `messenger:failed` jest monitorowane/retryowane w produkcji** (cron `messenger:failed:retry`,
  alarm Prometheus): poza zakresem czytanego kodu — to konfiguracja ops/deploymentu.
- **Pełen przegląd wszystkich 97 migracji** — przejrzano 24 (20 najnowszych + 4 destrukcyjne).
  Starsze migracje (przed 2026-05-30) nie były czytane linia po linii.
- **Skala driftu w produkcyjnych danych** — testowano lokalną bazę dev (dane seed/demo).
  4 obiekty z driftem to dowód mechanizmu, nie miara skali u klienta.

## Stan ogólny

Fundamenty integralności są zaskakująco solidne jak na ten etap:
- Optimistic locking (`CatalogObject.version`) realnie wired i egzekwowany dwupoziomowo.
- Unikalność SKU/identyfikatorów jest **w DB** (partial unique index + trigger), nie tylko w walidatorze
  — odporna na race walidacja↔insert. Zweryfikowane empirycznie.
- Usuwanie atrybutu obronione (pre-check + catch FK → 409, nie 500/sieroty).
- Domain events dispatchowane w `postFlush` + `doctrine_transaction` middleware = transactional outbox
  dla async (INSERT do kolejki commit-uje z danymi).
- Migracje destrukcyjne **mają** `down()` (poza jedną świadomie nieodwracalną data-migracją).

Słabe punkty leżą w: (a) braku gwarancji/wykrycia spójności denormalizacji `attributes_indexed`
(drift już istnieje w danych), (b) nieatomowym rollbacku importu, (c) lossy `down()` migracji
destrukcyjnych z danymi, (d) niespójnej obsłudze FK RESTRICT przy usuwaniu ObjectType.

---

## Findings

### G-01 [MEDIUM] Drift `attributes_indexed` ↔ `object_values` bez mechanizmu wykrycia — drift już obecny w danych

**Dowód empiryczny** (żywa baza `pim`):

```
-- global object_values vs klucze attributes_indexed
global_value_rows=9607 | indexed_keys_total=9620 | in_values_not_in_index=0 | in_index_not_in_values=13
```

13 osieroconych kluczy w cache dla 4 obiektów. Dwa warianty driftu:

```
ACME-001 | total_values=0 | global_values=0 |
  attributes_indexed = {"sku":"ACME-001","name":"Acme Widget","brand":"Acme","description":"..."}
DEMO-100 | tags w cache {"option_codes":["eco","new"]}, ale 0 wierszy object_values dla atrybutu 'tags' w ŻADNYM scope
```

`ACME-001/002/003` mają `attributes_indexed` wypełniony, lecz **zero** wierszy `object_values` — cache
trzyma dane, których kanon nie zna (dodatkowo w legacy-shape `"sku":"ACME-001"`, nie envelope `{value}`
— nie przeszły migracji ADR-0019). `DEMO-100.tags` to stale key po usunięciu wartości.

**Przyczyna w kodzie:** `RebuildAttributesIndexedHandler::rebuildOneWithRetry`
(`src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php:104-110`) po `MAX_REBUILD_RETRIES=3`
konfliktach wersji **cicho skipuje** obiekt (`logger->warning` + `return`) — wiadomość kończy się
sukcesem, NIE trafia do `failed`. Polega na komentarzu „next ObjectValuesChanged event re-queues it”
(l. 65), ale jeśli obiekt nie zostanie więcej edytowany, drift jest trwały.
`AttributesIndexedRebuilder::rebuild` (`AttributesIndexedRebuilder.php:62-82`) jedyne zapełnia cache;
jedyna reconcyliacja to ręczna komenda `pim:catalog:recalculate-completeness --tenant=X`
(`RecalculateCompletenessCommand.php:108-110`) — per-tenant, manualna, BEZ trybu „detect/report drift”,
bez crona. Brak jakiegokolwiek automatu wykrywania rozbieżności w runtime.

**Atak/awaria:** worker rebuildu skipuje obiekt przy współbieżnej edycji → list view / Meilisearch / completeness
pokazują wartości niezgodne z kanonem `object_values`; nikt się nie dowiaduje bo failure jest „success”.
Eksport produktu (czyta cache) wysyła do kanału dane, których edytor nie pokazuje. Skala rośnie cicho.

---

### G-02 [MEDIUM] Rollback importu (`ImportRollbackService::rollback`) nie jest atomowy — okno częściowego stanu

**Dowód** (`src/Import/Application/Service/ImportRollbackService.php:82-139`): brak `wrapInTransaction`
obejmującego całość. Sekwencja niezależnych commitów:

```
86:  replayUndoLog($session)                       -- flush wewnątrz (transakcja A)
97:  $this->em->flush();                           -- rebuild restored (transakcja B)
111: DELETE FROM object_values WHERE object_id IN (...)  -- executeStatement (transakcja C)
115: DELETE FROM objects WHERE import_session_id = :sid  -- executeStatement (transakcja D)
126: $this->em->clear(); ... 129: markRolledBack() ... 138: save()  -- (transakcja E)
```

**Atak/awaria:** crash/kill workera (FrankenPHP restart, OOM, deploy) między tymi krokami zostawia bazę
w połowicznym rollbacku:
- między B i C: wartości pre-existing przywrócone, ale obiekty utworzone przez import nadal istnieją;
- między C i D: `object_values` skasowane, `objects` zostają → produkty bez wartości (sieroty danych);
- przed E: dane wykasowane, ale status sesji nadal `completed`/`partial` → operator może odpalić rollback
  PONOWNIE (undo-log już zużyty/niespójny).

Per-tenant `BulkOperationLock` chroni przed równoległym importem, ale NIE przed crashem w połowie.
Kontrast: pętla importu (`ImportRunHandler`) świadomie commituje per chunk + checkpoint i przyznaje
status `partial` — tam częściowość jest projektowa i odzyskiwalna; w rollbacku częściowość jest
nieudokumentowana i niewznawialna.

---

### G-03 [MEDIUM] Migracje destrukcyjne z danymi mają lossy / strukturalny `down()` — rollback nie przywraca danych

**Dowód:**

- `Version20260612210000.php:165-169` — `down()` rzuca `throwIrreversibleMigrationException`
  („restore from the pre-migration dump backups/pre-imp2-1.2-*.dump”). Świadoma, udokumentowana
  nieodwracalność data-migracji JSONB. Ryzyko: zależy od istnienia tego dumpu (poza VCS).
- `Version20260607130000.php:34,42` — `up()` robi `ALTER TABLE channels DROP label`; `down()`
  odtwarza `label` tylko z `name` jako `{"pl": name}` → **gubi `en`** i każdy inny klucz envelope.
  Lossy reverse niezgłoszony jako irreversible.
- `Version20260605100000.php:33-35` — `DROP TABLE channel_currencies` + `currencies`; `down()`
  odtwarza schemat i 3 domyślne waluty, ale **traci wszystkie linki channel↔currency** (komentarz l. 21
  przyznaje). Reverse strukturalny, nie danych.
- `Version20260607140000.php:30` — `DROP TABLE channel_locales`; `down()` (l. 35-45) odtwarza pustą
  tabelę — **bindingi przepadają**.
- `Version20260606120000.php:41` — `up()` robi `UPDATE channels SET category_tree_root_object_id = NULL`;
  `down()` (DROP TABLE) **nie przywraca** wyzerowanych wartości.
- `Version20260612230000.php:27-28,45` — `up()` mapuje `mode MERGE/INCREMENT/DELETE → UPSERT`;
  `down()` przywraca tylko DEFAULT, nie pierwotne wartości per-wiersz. Lossy.

**Atak/awaria:** operator robi `migrations:migrate prev` licząc na czysty rollback po nieudanym deployu —
dostaje schemat OK, ale dane (waluty kanałów, bindingi locale, root kategorii, oryginalny tryb importu,
`label.en`) bezpowrotnie utracone. Dla migracji JSONB jedyną siatką jest dump z `backups/`, którego
istnienia migracja nie weryfikuje (memory `pgbackrest cron stale since 2026-04-28` zwiększa ryzyko).

---

### G-04 [LOW] `ObjectTypeService::delete` nie łapie `ForeignKeyConstraintViolationException` — race → 500 zamiast 409

**Dowód** (`src/Catalog/Application/ObjectTypeService.php:227-240`): guard count instancji (`countInstances`)
+ guard built-in, ale `em->remove` + `em->flush` (l. 238-239) **bez** try/catch. Komentarz l. 322-325
sam przyznaje race: „Cheap DBAL count ... could let a delete slip through; we re-check at write time” —
ale FK `objects.object_type_id → object_types RESTRICT` (raw db-fk-ondelete l. 86) backstopem rzuci
`ForeignKeyConstraintViolationException`, która tu nie jest złapana → 500.

Kontrast: `DeleteAttributeHandler` (`DeleteAttributeHandler.php:66-72`) robi DOKŁADNIE tę obronę
(catch FK → 409 `inUseConflict`). ObjectType jest niespójny z tym wzorcem.

**Atak/awaria:** w oknie między count a flush ktoś tworzy obiekt tego ObjectType (lub import) →
delete kończy się 500 (RFC7807 leak / brzydki błąd) zamiast czytelnego 409. Dane nie giną (RESTRICT
trzyma), ale UX/observability cierpi i log zbiera fałszywy „server error”.

---

## Zweryfikowane pozytywy (nie-findingi, potwierdzone dowodem)

- **Optimistic locking realny i egzekwowany.** `CatalogObject.orm.xml:128` `version="true"`;
  `UpdateCatalogObjectHandler.php:41-48` pre-flight check `expectedVersion` → 409 + catch
  `OptimisticLockException` z flush (l. 78-83); `CatalogObjectVersionNormalizer` dosyła `version`
  do klienta. `RebuildAttributesIndexedHandler` poprawnie resetuje EM po konflikcie (l. 102).
- **Unikalność SKU/identyfikatorów w DB.** Empirycznie: `object_values_identifier_uniq` +
  trigger `object_values_sync_identifier_trg` ISTNIEJĄ; `objects_tenant_kind_code_noncat_uniq`
  + `objects_tenant_cat_tree_code_uniq` ISTNIEJĄ. `object_values_scope_uniq` z `nulls_not_distinct`
  (`ObjectValue.orm.xml:11-17`). Race walidacja↔insert złapie DB, nie tylko walidator.
- **Usuwanie atrybutu obronione** (`DeleteAttributeHandler.php:59-72`): pre-check usage + catch FK → 409.
- **Domain events post-commit-safe.** `DomainEventDispatcher.postFlush` (`DomainEventDispatcher.php:40-55`)
  + `doctrine_transaction` jako ostatni middleware (`messenger.yaml`): dla async (Doctrine transport)
  wysyłka to INSERT w tej samej transakcji = transactional outbox; worker nie czyta starych danych.
- **Import: częściowość projektowa + odzyskiwalna.** Checkpoint zapisany w tej samej transakcji co
  wiersze chunka (`ImportRunHandler.php:292-297`); undo-log (IMP2-2.4); dispatch rebuildu PO finalizacji
  (l. 416-430); `import` queue `redeliver_timeout: 14400` + retry 5×backoff.
- **FK ON DELETE w większości sensowne:** wszystkie `object_values/object_relations/object_channel_placements/
  object_categories → objects/channels CASCADE` (sprzątają z parentem); `attribute_id RESTRICT` (chroni
  przed cichym kasowaniem wartości); `objects.parent_id SET NULL` (wariant traci parent, nie ginie).
