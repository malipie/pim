# DOMENA L — Architektura i Dług Techniczny

**Data:** 2026-06-16
**Audytor:** subagent L (adwersarski audyt przed-SaaS)
**Zakres:** bounded contexts (deptrac), martwy kod (ADR-009 deprecated), zgodność z ADR 009/013/014, spójność wzorców (FE fetch, controllery vs API Platform, error handling).
**Tryb:** READ-ONLY. Brak edycji plików aplikacji, brak operacji DB poza SELECT.

---

## 1. Metodyka — co i jak sprawdzono

| Obszar | Metoda | Dowód |
|---|---|---|
| Naruszenia BC | odczyt `raw/deptrac.txt` + `raw/deptrac-config.txt` (288 linii) | analiza skip_violations |
| Martwy kod ADR-009 | `rg -i "Family\|families\|product_values\|ObjectAssociation"` w `apps/api/src` | 0 trafień (poza false-positive) |
| Tabele DB deprecated | `psql ... pg_tables` + rowcounts (READ-ONLY SELECT) | 86 tabel, 3 sieroty |
| Timeline migracji | `rg` w `apps/api/migrations/*.php` (97 migracji) | DROP/CREATE association |
| Routing AP vs custom | `rg "#[Route]"` / `#[ApiResource]` / State providers per BC | 117 plików Route, 2 ApiResource |
| Wzorce FE | `rg jsonFetch\|useQuery\|axios\|fetch(` w `apps/admin/src` | 138 / 55 / 0 / 6 plików |
| Error handling | `rg JsonResponse\|HttpException\|application/problem` | 253 / 1030 / 25 |
| Dług statyczny | `raw/phpstan.txt`, `raw/jscpd-api.log`, `raw/jscpd-admin.log` | 13.27% / 4.19% dup |
| ADR | odczyt `docs/adr/0012-0019` | cytaty |

### Czego NIE dało się sprawdzić (luki audytu)
- **Deptrac uncovered=5099** — większość `src/` NIE jest objęta regułami warstw (16 kolektorów pokrywa wąski wycinek). Nie da się z samego deptrac stwierdzić, że BC poza Catalog/Channel/Asset/Identity/Import/Export/Search/Shared są zdyscyplinowane — bo żadna reguła ich nie ocenia. To luka **w samym narzędziu fitness**, nie w moim audycie.
- **Runtime audit dh-auditor** — nie uruchamiałem mutacji (READ-ONLY), więc nie potwierdziłem empirycznie że auditor NIE próbuje pisać do sierot `*_audit`. Wniosek o sierotach oparty na konfiguracji + schemacie DB, nie na żywym flushu.
- **jscpd duplikaty** — nie zweryfikowałem manualnie każdego z 1142 klonów; oparłem się na agregacie i top-15 plikach.
- **Kompletność reguł deptrac dla Search/Backup** — Backup w ogóle nie ma warstwy; nie wiem czy łamie ringfence, bo nikt go nie mierzy.

---

## 2. Findings (z dowodami)

### L-01 [MEDIUM] Deptrac „0 violations" jest zielony WYŁĄCZNIE dzięki 286 zbaseline'owanym realnym przeciekom warstw (Export/Import → Catalog_Internals)

**Dowód** — `raw/deptrac.txt`:
```
Violations           0
Skipped violations   286
Uncovered            5099
```
`raw/deptrac-config.txt` linie 240-466: `skip_violations` zawiera 56 kluczy source-class, każdy listujący 1..N zbaseline'owanych zależności. To NIE szum — to realne przecieki:
- `ExportBuilder` → `Catalog\Domain\Entity\CatalogObject`, `ObjectValue`, `Attribute`, 4× `*RepositoryInterface` (linie 280-288)
- `ImportRunHandler` → `Catalog\Application\BatchValueWriter`, `BulkContext`, `Catalog\Domain\Entity\*`, `Asset\Domain\Repository\AssetRepositoryInterface` (linie 430-457)
- ~30 Import `*Controller` → `Identity\Domain\Entity\User` (linie 374-427) — każdy kontroler Import sięga bezpośrednio do encji User Identity zamiast przez Contract.

**Atak/awaria:** Import i Export są de facto sprzężone z wnętrzem Catalog. Każda zmiana w `Catalog\Domain\Entity\CatalogObject`/`ObjectValue`/repozytoriach łamie Import/Export bez ostrzeżenia kompilatora warstw. Gate architektoniczny pokazuje „GREEN", więc operator nie widzi że ringfence Import↔Catalog faktycznie nie istnieje. Dokumentowany dług (#1466 „shared writer core") — ale otwarty, więc to stan na dziś.

**Rekomendacja:** dokończyć #1466 (shared writer core przez `Catalog\Contracts`) — to spali większość 286. Dla ~30 Import controllerów → `User`: wprowadzić `Identity\Contracts\Query\CurrentUserSummary` zamiast `use Identity\Domain\Entity\User`. Do tego czasu traktować zielony deptrac jako warunkowy.

---

### L-02 [MEDIUM] Reguła architektoniczna „Wszystko przez API Platform" (CLAUDE.md pkt 3) jest faktycznie odwrócona — 117 plików z custom `#[Route]` vs 2 deklaracje `#[ApiResource]`

**Dowód:**
```
#[Route] occurrences: 244   (w 117 plikach)
#[ApiResource] files: 2     (ObjectKindRouter.php, KindAwareSerializerContextBuilder.php — to konfiguratory AP, nie zasoby domenowe)
ApiPlatform State Providers/Processors: 13 plików
```
Rozkład `#[Route]` per BC: Catalog 44, Identity 30, Import 18, Asset 7, Export 5, Channel 4, Search 3, ApiConfigurator 2, Backup 2, Shared 1.

CLAUDE.md pkt 3: *„Wszystko przez API Platform (REST + GraphQL + JSON-LD jednocześnie). Custom REST tylko gdy API Platform nie wystarczy."* Realny stan to odwrotność reguły: dominują custom Symfony controllery, API Platform użyte marginalnie (13 processorów/providerów + 2 konfiguratory).

**Konsekwencje:** API-first deklarowane jako wyróżnik produktu, ale powierzchnia API to ręcznie pisane endpointy bez jednolitego JSON-LD/GraphQL/hydra, bez auto-OpenAPI z resource metadata, z ręczną paginacją/filtrowaniem per kontroler. GraphQL (obiecany w stacku) praktycznie nie istnieje dla zasobów domenowych. Integratorzy zewnętrzni (BaseLinker/Shopify w Fazie 1) dostaną niespójny, ręcznie utrzymywany kontrakt.

**Rekomendacja:** świadoma decyzja — albo (a) zaktualizować CLAUDE.md/ADR że MVP poszedł custom-controller-first i API Platform jest opcjonalne (uczciwość dokumentacji), albo (b) zaplanować retrofit krytycznych zasobów (Product/Category/Asset) na ApiResource przed otwarciem API dla partnerów. Brak ADR uzasadniającego to odwrócenie reguły — to nieudokumentowany dryf.

---

### L-03 [MEDIUM] Trzy współistniejące wzorce pobierania danych w admin FE — dryf bez wymuszenia jednego

**Dowód** (`apps/admin/src`):
```
jsonFetch:                  138 plików  (helper scentralizowany: apps/admin/src/lib/http.ts — pozytyw)
useQuery/useMutation:        55 plików  (TanStack Query)
Refine useList/useOne/...:   50 plików  (data provider Refine)
axios:                        0 plików
raw fetch():                  6 plików
```
3 równoległe sposoby na to samo (ręczny jsonFetch+useEffect/useState, TanStack useQuery, Refine hooks). `jsonFetch` jest scentralizowany (1 helper) — to dobrze — ale wybór warstwy cache/invalidacji jest per-deweloper.

**Atak/awaria:** stan cache rozjeżdża się — dane invalidowane przez `queryClient` w jednym ekranie nie odświeżają ekranów na `jsonFetch`. To dokładnie wzorzec opisany w lekcjach operatora (`feedback_useeffect_to_usequery_pattern` — „stale-data bug"). 138 plików na jsonFetch to 138 potencjalnych miejsc gdzie dane są nieświeże po mutacji gdzie indziej. Pozytyw: 0 plików miesza jsonFetch z useQuery w jednym pliku (każdy plik wewnętrznie spójny), ale baza jako całość ma 3 modele.

**Rekomendacja:** ustalić jeden domyślny wzorzec (rekomendacja: TanStack Query nad jsonFetch dla danych z invalidacją; Refine hooks dla list zasobów). Lint rule / ADR „nowy kod = useQuery". Migracja 138 plików — duża, ale priorytetowo ekrany które reagują na mutacje gdzie indziej.

---

### L-04 [LOW] Sieroty schematu DB po ADR-014: `object_associations_audit` + `association_types_audit` (encje usunięte, tabele audit zostały)

**Dowód:**
- Migracja `Version20260524110000.php` (ADR-014 / MOD-02 #894) — komentarz l.11-14: *„object_relations replaces object_associations ... pre-ADR-014 association infrastructure ... is dormant"*; DROP-uje tabele BAZOWE: `DROP TABLE IF EXISTS object_associations` (l.84), `DROP TABLE IF EXISTS association_types` (l.87).
- DB potwierdza: bazowe `object_associations`/`association_types` NIE istnieją; istnieją tylko `object_relations`, `product_assets`, `assets`.
- ALE tabele `*_audit` zostały: `psql` → `object_associations_audit` (0 wierszy), `association_types_audit` (0 wierszy).
- Tabele audit utworzone w `Version20260430092112.php` (l.54+). Żadna późniejsza migracja ich NIE DROP-uje: `rg "DROP TABLE.*object_associations_audit"` w migracjach → tylko w `down()` migracji 0430092112, nigdy w forward-path.
- `dh_auditor.yaml` (16 audytowanych encji) NIE zawiera żadnej encji Association — czyli nikt już do nich nie pisze.

**Atak/awaria:** brak ryzyka runtime (0 wierszy, brak triggerów PG — `pg_trigger ILIKE '%assoc%'` puste). To czysty śmieć schematu: 2 puste tabele wprowadzające w błąd przy inspekcji DB i sugerujące żywą funkcję asocjacji, która została wycofana.

**Rekomendacja:** migracja sprzątająca `DROP TABLE IF EXISTS object_associations_audit, association_types_audit`. Niski priorytet (kosmetyka), ale higiena przed SaaS.

---

### L-05 [LOW] `Backup` BC całkowicie poza fitness-gate deptrac (12 plików PHP, 0 reguł) + Search/Tooling z jawnie dozwolonym dostępem do Internals

**Dowód:**
- `apps/api/src/Backup` = 12 plików PHP; `grep -c Backup raw/deptrac-config.txt` → **0**. Backup nie ma własnej warstwy ani nie jest w żadnym kolektorze → ląduje w `Uncovered=5099`. Żadna reguła nie pilnuje czy Backup łamie ringfence.
- `Search` layer ma w ruleset (config l.190-196) JAWNIE: `Catalog_Internals` + `Identity_Internals` jako dozwolone zależności — czyli ringfence dla Search jest oficjalnie wyłączony w konfiguracji (nie baseline, tylko reguła).
- `Tooling` (config l.217-228) może sięgać do WSZYSTKICH `*_Internals` (Catalog/Channel/Asset/Identity) — to dotyczy `DataFixtures`, `Benchmark`, `Story`, `PHPStan` (uzasadnione), ale rozmywa egzekwowanie.
- `Integration`/`Agent`/`ApiConfigurator` to puste/forward-compatible kolektory (config l.93-105).

**Atak/awaria:** nowy kod w `Backup` (operacje na backupach DB — wrażliwe) może importować dowolne wnętrze dowolnego BC i CI tego nie wychwyci. `Search` może (legalnie wg reguły) zalgnąć się z wnętrzem Catalog i Identity — przy zmianie tych encji Search milcząco pęka.

**Rekomendacja:** dodać kolektor `Backup` do deptrac z ruleset `Shared + *_Contracts`. Zawęzić Search do Contracts gdzie możliwe (projekcja read-model zamiast `Catalog_Internals`). Zaadresować `Uncovered=5099` — dopisać kolektory dla wszystkich `src/<BC>` żeby fitness gate pokrywał całość, nie wycinek.

---

## 3. Pozytywy (zweryfikowane empirycznie — nie deklaratywne)

- **PHPStan level max (10), zero błędów, BRAK baseline masking.** `apps/api/phpstan-baseline.neon` = `ignoreErrors: []` (pusty). Tylko 6 grup `ignoreErrors` w `phpstan.dist.neon`, 4 test-only, 2 wąsko uzasadnione w src (`raw/phpstan.txt`). W całym `src/` tylko **3** `@phpstan-ignore`. To nie jest dług ukryty pod baseline.
- **Martwy kod ADR-009 (Family/products/product_values) FAKTYCZNIE usunięty.** `rg "Family\|families\|product_values"` w `apps/api/src` → 0 realnych trafień (pozostałe to słowo „family" w komentarzach: kategoria atrybutu `brand,family,color` w FilterDslResolver, „password hasher family" w TOTP). Brak encji/repo/migracji deprecated w forward-path. ObjectType/CatalogObject/ObjectValue rzeczywiście przejęły.
- **Niski dług deklaratywny:** 4 TODO/FIXME/HACK + 2 `@deprecated` w całym `src` (api+admin). To bardzo czysto jak na 57k LOC PHP + 58k LOC TS.
- **jsonFetch scentralizowany** w `apps/admin/src/lib/http.ts` (jeden helper, nie 138 kopii).
- **Duplikacja admin FE niska: 4.19%** (200 klonów / 3079 linii) — `raw/jscpd-admin.log`.

## 4. Dług do nadrobienia (priorytetyzacja)

1. **#1466 shared writer core** — spala L-01 (286 skip_violations). Otwarte.
2. **Decyzja API Platform vs custom controllers** (L-02) — ADR lub aktualizacja CLAUDE.md. Nieudokumentowany dryf.
3. **Ujednolicenie FE fetch** (L-03) — ADR + lint, migracja 138 plików jsonFetch.
4. **jscpd-api 13.27% duplikacji** (1142 klony) — top: `ApiProfileProcessor` (41), Bulk handlers, ApiPlatform filters/state, ~5 Catalog controllerów. Boilerplate processorów AP + custom controllerów. Średni dług.
5. **Sprzątanie sierot audit + pokrycie deptrac** (L-04, L-05) — niski priorytet, higiena.
