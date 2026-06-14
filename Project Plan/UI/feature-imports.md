# Feature — Import produktów (CSV / Excel + zdjęcia)

> ## ⚠️ SUPERSEDED (2026-06-12)
> Silnik importu jest **przebudowywany** wg [`feature-imports-v2.md`](feature-imports-v2.md) (epik IMP2, #1499).
> Sekcje silnika tego dokumentu (IMP-01..IMP-15) są **historyczne** — **nie implementować wg tej wersji**.
> Aktualny kontrakt silnika (tryby CREATE/UPDATE/UPSERT, gramatyka kolumn `code.locale.channel`, envelope JSONB, warianty/relacje/galerie, izolacja błędów per wiersz, golden round-trip CSV+XLSX) opisuje `feature-imports-v2.md` i jest realizowany w ticketach IMP2-1.x. Mapa „stary ticket → następca IMP2-*" w komentarzu zbiorczym epika #1499.

## Status: 🟢 zaimplementowane (epik 0.13 / UI-09 — IMP-01..IMP-15 merged 2026-05-07)

> **Część epiku 04 Publikacje** — sub-tab "Imports". Standalone dokument, bo feature jest na tyle obszerny, że zasługuje na własną iterację designu.
> **Decyzje brainstormingowe** zamknięte 2026-05-03 w sesji Senior PM. Plik rozszerza placeholder z `Project Plan/UI/epik-04-publikacje.md`.
> **Implementacja:** epik 0.13 / UI-09 — 15 atomowych ticketów (IMP-01..IMP-15, #442–#456) zmergowanych do `main` 2026-05-06/07. Dogfooding US-IMP-005 (katalog IdoSell ~2k SKU) **odsunięte** poza ten epik — wymaga realnego export'u IdoSell, który nie był dostępny w czasie marathon'u; gate przed deklaracją *„imports gotowe na pierwszy real-world"* nadal aktywny. Followup-y w `agent/lessons.md` § 2026-05-07.

---

## 1. Cel feature'a

Self-service import nowych produktów z plików **Excel/CSV** + opcjonalnie zdjęcia (linki HTTP lub ZIP) w **MVP**. Operator (Kasia) ma w UI flow:

1. Wybiera plik + opcjonalnie ZIP zdjęć + locale.
2. System auto-mapuje kolumny przez **rules-based dictionary PL/EN** (~30 atrybutów × 5-10 synonimów).
3. User mapuje ręcznie nieprzetłumaczone kolumny.
4. Walidacja preview — *„247 OK, 33 błędy → [pobierz raport]"*.
5. Confirm + opcjonalny manual backup pgBackRest.
6. Import async via Symfony Messenger z progress bar SSE.
7. Po imporcie: raport + button *„Wycofaj import"* (24h window).

**Out of scope MVP:** UPDATE istniejących produktów, import kategorii/marek, recurring imports (cron), AI auto-mapping, variants z wide format, multi-locale w jednym pliku.

**Rozróżnienie z Excel-as-service** (z PRD § 9.1): Excel-as-service to **paid services offering** dla klientów którzy nie chcą sami onboardować się — operator firmy (Marcin / przyszły specjalista) używa wewnętrznie agenta. Self-service import (ten dokument) to **wbudowany feature MVP** dla klientów którzy chcą onboardować samodzielnie.

## 2. Persony

| Persona | Rola | Częstość |
|---|---|---|
| **Kasia, 32** (Catalog Manager) | Primary — uruchamia importy 1-3× w tygodniu (nowy dostawca / nowa kolekcja) | Codziennie/co kilka dni |
| **Magda, 29** (Marketing) | Secondary — import opisów SEO z osobnego pliku (po MVP) | Sporadycznie |
| **Marcin (founder dogfooding)** | First user — migracja katalogu IdoSell (2k SKU) jako pierwszy real-world import test | Pierwsze tygodnie po MVP |

## 3. Brainstorming decisions snapshot (2026-05-03)

| Obszar | Decyzja |
|---|---|
| Profile importu — scope | **(c) Mapping + smart memory** — mapping + zapamiętane ostatnie wartości (encoding, delimiter, locale) jako defaults, edytowalne per import. |
| Profile sharing | **(b) Solo per user** — każdy ma własne profile (jak Saved Views w epiku 02). |
| Auto-mapping algorithm | **(a) Rules-based dictionary PL/EN**. Top ~30 atrybutów × 5-10 synonimów. Free, fast, deterministic. **Bez AI w MVP** — AI-mapping kandydat na Fazę 2. |
| Per-locale mapping | **(c) Single locale per import** — klient wybiera locale na początku, multi-locale = osobne importy. |
| Backup + rollback | **(a) Per-import session "soft rollback"** — `import_session_id` UUID, rollback = soft delete created objects. **+ opcjonalny manual button** *„Utwórz pgBackRest backup przed importem"* w Step 4. |
| Multiple images per produkt | **(b) Numbered columns** — `image_1`, `image_2`, ..., `image_10`. Pierwsza = main, reszta = galeria. |
| ZIP convention | **(b) Excel column → ZIP filename mapping** — kolumna `image_1` zawiera nazwę pliku, system szuka w ZIP. |
| Walidacja błędów | **(a) Skip + log + raport** — wiersz błędny → skip, log do raport CSV, import nie aborts. |
| Encoding | **UTF-8 + Windows-1250 + auto-detect** z BOM. User może override z dropdown. |
| Async threshold | **<50 rows sync, 50+ async** via Symfony Messenger. **5000+ email po zakończeniu.** |
| ADD only, duplicate SKU | **Skip + warning** w raporcie. UPDATE w Fazie 1. |
| MVP scope ObjectType | **Tylko `kind=product`** w UI. Generic engine pod spodem (categories/brands w Fazie 1). |
| Variants | Tylko **master rows** w MVP. Variants w detail produktu ręcznie. |
| Lokalizacja UI | **Sub-tab w epiku 04 Publikacje**: `Imports / Exports / Integracje / API Configurator`. |
| Trigger z Produktów | **TAK** — empty state CTA *„📥 Importuj z Excel/CSV"* + button *„Imports"* w toolbar listy produktów. |

---

## 4. User flow — 4-step wizard + post-import

```
[Imports list view]
    ├─ "Nowy import" button
    │
    └─→ Step 1: Upload pliku
        ↓
        Step 2: Mapping kolumn  
        ↓
        Step 3: Walidacja preview (dry-run)
        ↓
        Step 4: Confirm + opcjonalny backup
        ↓
        [Import in progress] — async, Mercure SSE
        ↓
        [Import results] — raport + rollback button
        ↓
        Powrót do [Imports list view]
```

---

## 5. Wireframes per ekran

### 5.1 Imports list view (sub-tab Publikacje)

```
┌─ Publikacje ─────────────────────────────────────────────────────────────┐
│ [Imports]  [Exports]  [Integracje]  [API Configurator]                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  IMPORTY                                          [+ Nowy import] [⋮ Profile]
│  ───────────────────────────────────────────────────────────────────    │
│                                                                          │
│  Filtry: [Status ▼]  [Date range]  [User ▼]                            │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐│
│  │ Date     │ Plik                  │ Status   │ Stats         │ Akcje ││
│  ├────────────────────────────────────────────────────────────────────┤│
│  │ 03.05    │ festo-q2-2026.xlsx   │ ✅ OK    │ 247 / 247    │ ⋮     ││
│  │ 02.05    │ bosch-spring.csv     │ ⚠️ Part. │ 280 / 313    │ ⋮ ↶  ││
│  │ 01.05    │ italian-supplier.xlsx│ ❌ Failed│ 0 / 142      │ ⋮     ││
│  │ 28.04    │ klima-import.xlsx    │ ✅ OK    │ 1247 / 1247  │ ⋮     ││
│  │ 25.04    │ proback-test.csv     │ 🔄 Run.. │ 532 / 1850   │ ⋮ ⏸   ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Showing 1-50 of 89 imports                  [‹ Prev] [Next ›]          │
└─────────────────────────────────────────────────────────────────────────┘
```

**Akcje per row (3-dot menu):**
- View report (jeśli partial/failed) — modal z log'ami błędów + link CSV download.
- Rollback — *„Wycofaj import"* (jeśli w window 24h, success/partial).
- Re-run with same profile — używa zapisanego mapping (jeśli profile attached).
- Delete (po 7 dniach lub manual).

**Status badges:**
- ✅ **OK** — wszystkie wiersze zaimportowane.
- ⚠️ **Partial** — N OK, M błędów (klikalne → raport).
- ❌ **Failed** — abort (rare, np. corrupted plik).
- 🔄 **Running** — w toku, progress widoczny po klik.
- 🛑 **Cancelled** — anulowane przez user.
- ↶ **Rolled back** — wycofane.

### 5.2 Step 1 — Upload pliku

```
┌─ Nowy import → Step 1: Upload ───────────────────────────────────────────┐
│                                                                           │
│  ●●○○  Upload  Mapping  Validation  Confirm                              │
│  ─────                                                                    │
│                                                                           │
│  Plik produktów (CSV / Excel)                                             │
│  ┌─────────────────────────────────────────────────────────────┐        │
│  │                                                              │        │
│  │              📥 Przeciągnij plik tutaj                       │        │
│  │              lub [Wybierz plik]                              │        │
│  │                                                              │        │
│  │              Akceptowane: .xlsx, .csv (max 50 MB)            │        │
│  │                                                              │        │
│  └─────────────────────────────────────────────────────────────┘        │
│                                                                           │
│  ☑ festo-q2-2026.xlsx  (2.4 MB, 247 wierszy detected)                    │
│                                                                           │
│  ─── Konfiguracja ──────────────────────────────────────────              │
│                                                                           │
│  Locale tego pliku:           [Polski (pl_PL) ▼]                         │
│  Encoding:                     [Auto (Windows-1250 detected) ▼]          │
│  Delimiter (CSV only):         [Auto (semicolon detected) ▼]             │
│                                                                           │
│  ─── Zdjęcia (opcjonalnie) ─────────────────────────────────              │
│                                                                           │
│  Źródło zdjęć:                                                            │
│    ◉ Linki HTTP w pliku (kolumny image_1, image_2...)                    │
│    ○ Plik ZIP z lokalnymi zdjęciami                                       │
│    ○ Brak zdjęć w tym imporcie                                            │
│                                                                           │
│  [Drag-drop ZIP file]    (max 500 MB)                                     │
│                                                                           │
│  ─── Profil importu ────────────────────────────────────────              │
│                                                                           │
│  ◉ Nowy profil (skonfiguruj poniżej)                                     │
│  ○ Użyj zapisanego: [Festo XLSX 2026 ▼]                                  │
│                                                                           │
│  Po imporcie — zapisz jako profil:                                        │
│  ☐ [Nazwa profilu              ] (do reuse'u przy następnych importach)  │
│                                                                           │
│                                                       [Anuluj]  [Dalej →]│
└───────────────────────────────────────────────────────────────────────────┘
```

**Inteligencja:**
- File parsing on upload: detect encoding (BOM + heuristic), detect delimiter (CSV), count rows, sample first 5.
- *„247 wierszy detected"* — instant feedback po upload.
- Locale defaults z user's last import (smart memory).
- ZIP file size pre-check: jeśli >500 MB → ostrzeżenie + opcja split.

### 5.3 Step 2 — Mapping kolumn

```
┌─ Nowy import → Step 2: Mapping kolumn ──────────────────────────────────┐
│                                                                          │
│  ●●●○  Upload  Mapping  Validation  Confirm                             │
│  ─────                                                                   │
│                                                                          │
│  Auto-mapping zakończony: 12/15 kolumn dopasowanych. Zmapuj 3 ręcznie.  │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐│
│  │ Kolumna źródła │ Sample value      │ Mapping                │ Req. ││
│  ├────────────────────────────────────────────────────────────────────┤│
│  │ Kod produktu   │ "BSC-1234"        │ [sku ▼] ✓ auto         │ ✓   ││
│  │ Nazwa          │ "Czujnik X-200"   │ [name ▼] ✓ auto        │ ✓   ││
│  │ Cena netto     │ "245.50"          │ [price ▼] ✓ auto       │     ││
│  │ Producent      │ "Festo"           │ [brand ▼] ✓ auto       │     ││
│  │ Kategoria      │ "Pneumatyka"      │ [category ▼] ✓ auto    │     ││
│  │ EAN            │ "5901234567890"   │ [ean ▼] ✓ auto         │     ││
│  │ Description PL │ "Long opis..."    │ [description ▼] ✓ auto │     ││
│  │ image_1        │ "bsc1234.jpg"     │ [main_image ▼] ✓ auto  │     ││
│  │ image_2        │ "bsc1234_2.jpg"   │ [gallery_2 ▼] ✓ auto   │     ││
│  │ image_3        │ "bsc1234_3.jpg"   │ [gallery_3 ▼] ✓ auto   │     ││
│  │ Średnica zewn. │ "12.5 mm"         │ [— wybierz ▼] ⚠ manual │     ││
│  │ IP_class       │ "IP67"            │ [ip_class ▼] ✓ auto    │     ││
│  │ Numer Festo    │ "ABC-987"         │ [— wybierz ▼] ⚠ manual │     ││
│  │ Stara cena     │ "299.00"          │ [Skip ▼]               │     ││
│  │ Notatki wewn.  │ "Promo Q2"        │ [Skip ▼]               │     ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Legenda: ✓ auto = rozpoznane przez słownik    ⚠ manual = wybierz      │
│           Skip = kolumna pomijana w imporcie                            │
│                                                                          │
│  💡 Nie znajdujesz odpowiedniego atrybutu? [+ Stwórz nowy atrybut]      │
│       (przekierowanie do Modelowania, mapping zachowany)                │
│                                                                          │
│                                       [← Wstecz]  [Anuluj]  [Dalej →]   │
└──────────────────────────────────────────────────────────────────────────┘
```

**Mapping engine (rules-based):**

```yaml
sku:
  aliases: [sku, kod, kod produktu, kod prod, id, indeks, index, art_nr, article_number, manufacturer_part, mpn]
  
name:
  aliases: [name, nazwa, tytul, tytuł, title, product_name, nazwa_produktu]
  
price:
  aliases: [price, cena, cena netto, cena brutto, net_price, gross_price, koszt]

brand:
  aliases: [brand, marka, producent, manufacturer, maker, vendor, dostawca]

ean:
  aliases: [ean, ean13, gtin, kod kreskowy, barcode]

description:
  aliases: [description, opis, opis_produktu, long_description, full_description]

short_description:
  aliases: [short_description, krotki_opis, lead, intro, summary]

category:
  aliases: [category, kategoria, kategorie, kat, group, grupa]

main_image:
  aliases: [main_image, image_1, zdjecie_glowne, zdjecie_główne, foto, foto_glowne, image]

gallery_2..gallery_10:
  aliases: [image_2, image_3, ..., zdjecie_2, foto_2, gallery_image_2]

# ... ~30 atrybutów total z 5-10 aliasami każdy
```

**Algorytm:**
1. Lower-case header z pliku.
2. Strip whitespace + special chars (`_`, `-`, `.`, spaces).
3. Lookup w dictionary — exact match → mapping.
4. Jeśli brak — fuzzy match (Levenshtein < 2) → suggest jako *„Czy może chodzi o..."*.
5. Jeśli brak — `manual: user wybiera`.

**Custom attributes z Modelowania:**
- Klient w Modelowaniu (epik 08) zdefiniował `nfz_code`, `procedure_duration` — te atrybuty są w dropdown'ie targetu.
- *„+ Stwórz nowy atrybut"* button — deep-link do Modelowania, po stworzeniu atrybutu user wraca do step 2 z preserved state mapping.

**Skip column:** klient explicit ignoruje kolumnę (np. *„Stara cena"*, *„Notatki wewn."*) — system nie dotyka.

### 5.4 Step 3 — Walidacja preview (dry-run)

```
┌─ Nowy import → Step 3: Walidacja ───────────────────────────────────────┐
│                                                                          │
│  ●●●●  Upload  Mapping  Validation  Confirm                             │
│  ─────                                                                   │
│                                                                          │
│  Wynik dry-run:                                                          │
│                                                                          │
│  ┌──────────────────────┬──────────────────────┐                       │
│  │  ✅ 247               │  ⚠️ 33                │                       │
│  │  produktów OK         │  błędów / ostrzeżeń   │                       │
│  └──────────────────────┴──────────────────────┘                       │
│                                                                          │
│  Top 10 błędów (pełen raport po imporcie):                              │
│  ┌────────────────────────────────────────────────────────────────────┐│
│  │ Wiersz │ SKU         │ Typ błędu        │ Komunikat              ││
│  ├────────────────────────────────────────────────────────────────────┤│
│  │ 12     │ BSC-1234    │ Duplicate SKU   │ Już w bazie, pominięty ││
│  │ 47     │ —           │ Missing required│ Brak SKU                ││
│  │ 89     │ TST-001     │ Invalid type    │ Cena: "abc" nie liczba  ││
│  │ 134    │ ZWR-998     │ Invalid type    │ EAN: 12 cyfr (wymaga 13)││
│  │ 145    │ IMP-555     │ Image not found │ image_1 = "missing.jpg"││
│  │ 167    │ XYZ-002     │ Duplicate SKU   │ Już w bazie, pominięty ││
│  │ 198    │ CAT-789     │ Missing required│ Brak Name              ││
│  │ 220    │ —           │ Missing required│ Brak SKU                ││
│  │ 235    │ DEF-456     │ Invalid value   │ Brand "AAA" nie istnieje││
│  │ 240    │ GHI-789     │ Invalid value   │ Category "ZZZ" nie ist..││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  ☐ Pokaż wszystkie 33 błędy (modal z tabelą + filter)                   │
│                                                                          │
│  Co dalej?                                                               │
│    ◉ Zaimportuj 247 OK, pomiń 33 błędne (zalecane)                      │
│    ○ Wróć do mappingu, popraw błędy, sprawdź ponownie                   │
│                                                                          │
│                                       [← Wstecz]  [Anuluj]  [Dalej →]   │
└──────────────────────────────────────────────────────────────────────────┘
```

**Walidacje per typ błędu:**

| Typ | Opis | Akcja |
|---|---|---|
| **Missing required** | Brak SKU / Name / wymagany atrybut | Skip row, log error |
| **Duplicate SKU (in file)** | 2 wiersze z tym samym SKU w pliku | Skip drugi+, log warning |
| **Duplicate SKU (in DB)** | SKU już w bazie | Skip, log info (MVP add-only) |
| **Invalid type** | Price = "abc", EAN = 12 cyfr, date format off | Skip row, log error |
| **Invalid value** | Brand "AAA" nie istnieje, Category "ZZZ" nie ma | Skip row, log error |
| **Image not found** | Link 404 / ZIP file missing | Importuj produkt bez zdjęcia, log warning |
| **Image format unsupported** | .heic, .raw, etc. | Skip image, log warning |

### 5.5 Step 4 — Confirm + opcjonalny backup

```
┌─ Nowy import → Step 4: Confirm ─────────────────────────────────────────┐
│                                                                          │
│  ●●●●●  Upload  Mapping  Validation  Confirm                            │
│  ─────                                                                   │
│                                                                          │
│  Podsumowanie:                                                           │
│  ┌────────────────────────────────────────────────────────────────────┐│
│  │  Plik:           festo-q2-2026.xlsx (2.4 MB)                       ││
│  │  Locale:         Polski (pl_PL)                                    ││
│  │  Encoding:       Windows-1250                                      ││
│  │  Mapowania:      14 z 15 kolumn                                    ││
│  │  Skip:           1 kolumna ("Stara cena", "Notatki wewn.")        ││
│  │  Zdjęcia:        Linki HTTP (z kolumn image_1...image_5)           ││
│  │  Profil:         Zapisz jako "Festo Q2 2026" ✓                    ││
│  │                                                                     ││
│  │  Do importu:     247 produktów (+ 33 błędy do pominięcia)         ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  ─── Backup przed importem (opcjonalny) ──────────────────              │
│                                                                          │
│  ☐ Utwórz pełen backup bazy danych (pgBackRest snapshot)                │
│       💡 Zalecane dla importów >1000 produktów                          │
│       ⏱ Backup zajmie 5-30 minut                                        │
│       Bez backup'u: i tak dostępny soft rollback przez 24h              │
│                                                                          │
│  ─── Powiadomienie ──────────────────────────────────────              │
│                                                                          │
│  ☑ Wyślij email po zakończeniu importu (>5 minut runtime)              │
│  ☐ Webhook do zewnętrznego URL (Faza 1+)                               │
│                                                                          │
│                                                                          │
│  ⚠️ Akcja jest finalna. Możesz wycofać import w 24h.                    │
│                                                                          │
│                       [← Wstecz]  [Anuluj]  [▶ Uruchom import]          │
└──────────────────────────────────────────────────────────────────────────┘
```

### 5.6 Import in progress

```
┌─ Import w toku ────────────────────────────────────────────────────────┐
│                                                                         │
│  festo-q2-2026.xlsx                                                    │
│                                                                         │
│  Postęp:                                                                │
│  ┌─────────────────────────────────────────────────────────┐          │
│  │ ████████████████████░░░░░░░░░░░░░░░ 156 / 247 (63%)     │          │
│  └─────────────────────────────────────────────────────────┘          │
│                                                                         │
│  ⏱ Pozostały czas:    ~ 1 minuta 20 sekund                              │
│  ⏱ Czas wykonania:    2 minuty 47 sekund                                │
│                                                                         │
│  ─── Live status ──────────────────────────────────────────             │
│                                                                         │
│  ✅ 142 produkty zaimportowane                                          │
│  ⚠️ 12 ostrzeżeń (pokaż...)                                             │
│  ⏳ Aktualnie: pobieranie zdjęć dla SKU "BSC-1456"                      │
│                                                                         │
│  💡 Możesz zamknąć tę kartę. System wyśle email po zakończeniu.        │
│                                                                         │
│                                                  [⏸ Pauza]  [🛑 Anuluj] │
└─────────────────────────────────────────────────────────────────────────┘
```

**Mechanika:**
- **Mercure SSE channel** `imports.{user_id}` z eventami: `progress`, `row_processed`, `error`, `completed`.
- Live counter aktualizowany w real-time bez page refresh.
- **Pauza** — handler kończy aktualny chunk (np. 100 products), zapisuje state, czeka. Resume button.
- **Anuluj** — graceful shutdown, status `cancelled`, soft delete created products.

### 5.7 Import results

```
┌─ Import zakończony ──────────────────────────────────────────────────────┐
│                                                                           │
│  festo-q2-2026.xlsx                                       3 minuty 24 sek│
│                                                                           │
│  ┌──────────────────────┬──────────────────────┬──────────────────────┐│
│  │  ✅ 247              │  ⚠️ 33                │  ⏱ 3:24             ││
│  │  produkty             │  pominiętych          │  czas wykonania      ││
│  │  zaimportowane        │  (z błędami)          │                      ││
│  └──────────────────────┴──────────────────────┴──────────────────────┘│
│                                                                           │
│  ─── Co zaimportowano ────────────────────────────────────              │
│  • 247 produktów: kategoria "Pneumatyka", brand "Festo"                  │
│  • 1235 zdjęć pobranych z linków HTTP (98%)                              │
│  • 12 zdjęć nie udało się pobrać (404 / timeout)                        │
│                                                                           │
│  ─── Co pominięto ──────────────────────────────────────                │
│  • 18 wierszy z duplicate SKU (już w bazie)                              │
│  • 8 wierszy z brakiem required field (SKU lub Name)                     │
│  • 7 wierszy z invalid type (np. price="abc")                            │
│                                                                           │
│  ─── Akcje ────────────────────────────────────────────                 │
│                                                                           │
│  [📥 Pobierz raport CSV (33 błędy)]                                      │
│  [👁  Zobacz zaimportowane produkty]  (deep-link do Produkty + filter)   │
│  [↶  Wycofaj import]                                                     │
│      ⏰ Dostępne do: 2026-05-04 18:30 (jutro 18:30)                      │
│                                                                           │
│  [Zamknij]                                                                │
└───────────────────────────────────────────────────────────────────────────┘
```

**Raport CSV — format:**

```csv
row_number,sku,error_type,error_message,column,value
12,BSC-1234,duplicate_sku,"SKU already in database",sku,BSC-1234
47,,missing_required,"SKU is required",sku,
89,TST-001,invalid_type,"Price must be a number","Cena netto",abc
134,ZWR-998,invalid_type,"EAN must be 13 digits",ean,1234567890
145,IMP-555,image_not_found,"image_1 file 'missing.jpg' not in ZIP",image_1,missing.jpg
...
```

### 5.8 Profile manager (modal z toolbar)

```
┌─ Moje profile importu ───────────────────────────────────────────────────┐
│                                                                           │
│  ┌────────────────────────────────────────────────────────────────────┐│
│  │ Profil                  │ Ostatnio użyty │ # importów │ Akcje      ││
│  ├────────────────────────────────────────────────────────────────────┤│
│  │ Festo Q2 2026          │ Dziś           │ 4          │ ✏️ 🗑       ││
│  │ Bosch standardowy      │ 2 dni temu     │ 12         │ ✏️ 🗑       ││
│  │ Włoski dostawca v3     │ Tydzień temu   │ 3          │ ✏️ 🗑       ││
│  │ Klima generic          │ Miesiąc temu   │ 1          │ ✏️ 🗑       ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                           │
│  Edycja profilu modyfikuje TYLKO przyszłe importy. Nie wpływa na      │
│  poprzednie.                                                              │
│                                                                           │
│                                                              [Zamknij]   │
└───────────────────────────────────────────────────────────────────────────┘
```

---

## 6. User stories

| ID | Persona | Story |
|---|---|---|
| US-IMP-001 | Kasia | Import 50 nowych produktów Festo z Excel'a + zdjęcia z linków HTTP — 4-step wizard, success bez błędów. |
| US-IMP-002 | Kasia | Import 500 produktów z włoskiego dostawcy + ZIP zdjęć po nazwach plików (kolumna `image_1` = "abc123.jpg"). |
| US-IMP-003 | Kasia | Import używający zapisanego profilu *„Włoski dostawca v3"* — pomija mapping, ide bezpośrednio do walidacji. |
| US-IMP-004 | Kasia | Import 280 produktów, 33 błędy w preview — klient widzi top 10 + możliwość download CSV przed potwierdzeniem. |
| US-IMP-005 | Marcin (dogfooding) | Migracja katalogu IdoSell (2000 active SKU) — pierwszy real-world test importu z prawdziwymi danymi. |
| US-IMP-006 | Kasia | Po imporcie zauważa złą kategorię — klika *„Wycofaj import"* w 24h, system soft-deletuje 247 produktów. |
| US-IMP-007 | Kasia | Przed importem 5000+ produktów klika *„Utwórz backup pgBackRest"* — system robi snapshot (5-15 min), potem rusza import. |
| US-IMP-008 | Kasia | Auto-mapping rozpoznaje 12/15 kolumn ze słownika PL/EN — zapisuje 30 min ręcznego klikania. |
| US-IMP-009 | Kasia | Auto-mapping NIE rozpoznaje *„Numer katalogowy producenta"* — user wybiera ręcznie z dropdown'u (cała lista dostępnych atrybutów ObjectType `product`). |
| US-IMP-010 | Kasia | Custom atrybut potrzebny — klient klika *„+ Stwórz nowy atrybut"*, deep-link do Modelowania, po powrocie mapping zachowany. |
| US-IMP-011 | Kasia | Import w toku 1850 wierszy — Kasia zamyka kartę, idzie na obiad, dostaje email po 12 min *„Import zakończony, 1820 OK"*. |
| US-IMP-012 | Kasia | Import nie udał się (corrupted ZIP) — system aborts, nic nie tworzy w bazie, raport pokazuje przyczynę. |
| US-IMP-013 | Magda (Faza 1) | Import opisów SEO z osobnego pliku jako UPDATE istniejących produktów (mapowanie po SKU). |
| US-IMP-014 | Kasia (Faza 1) | Recurring import (cron) — codzienne sync z Excel'a Festo na FTP. |

---

## 7. Business rules / edge cases

### 7.1 Pliki

- **Max plik:** 50 MB. Powyżej — reject z message *„Plik za duży. Split na mniejsze."*.
- **Excel formats:** `.xlsx` only. **Nie wspieramy:** `.xls` (binary), `.xlsm` (z makrami), `.xlsb`. Reject + suggest *„Konwertuj do .xlsx"*.
- **Multi-sheet Excel:** używamy *pierwszego arkusza*. Jeśli klient ma plik z 3 arkuszami — komunikat *„Tylko pierwszy arkusz zostanie zaimportowany. Kontynuuj?"*.
- **Empty file:** reject, message *„Plik jest pusty lub uszkodzony"*.
- **No header row:** reject, message *„Pierwszy wiersz musi zawierać nagłówki kolumn"*.
- **Header row z nullami:** OK — kolumna z null header ma auto-skip mapping.

### 7.2 Encoding

- **Auto-detect order:** UTF-8 BOM → UTF-8 → Windows-1250 → fallback z user prompt.
- **Mixed encoding** (rare): wykrywamy heuristycznie, suggest user override.
- **CP1250 specific PL chars** (ą, ć, ę, ł, ń, ó, ś, ź, ż): obsługiwane natywnie po user choice encoding.

### 7.3 ZIP file

- **Max ZIP:** 500 MB. Powyżej — reject + suggest split.
- **ZIP z password:** unsupported (reject).
- **ZIP z folder structure:** OK — system flat-walka (pliki w `images/`, `pictures/` etc. są dostępne po nazwie).
- **Filename mismatch:** Excel column `image_1 = "BSC1234.jpg"`, ZIP zawiera `Bsc1234.JPG` → **case-insensitive match**.
- **Filename z polish chars:** `czujnik_ciśnienia.jpg` — OK (UTF-8 nazwy plików).
- **Same filename multiple times in Excel** (np. `image_1` różne SKU = "common.jpg") — OK, używamy tego samego pliku.

### 7.4 Image links

- **HTTP/HTTPS only.** Inne protokoły reject (np. `ftp://`, `file://`).
- **Max image:** 10 MB per file. Powyżej — skip + log warning.
- **Download timeout:** 30 sekund per image. Timeout → skip + log.
- **Redirect chain:** max 3 redirects.
- **404 / 403:** skip image + log warning, NIE abort import.
- **Format validation:** content-type sniff + ext check. Akceptujemy: jpg, jpeg, png, webp. Reject: heic, raw, gif (animated), bmp, tiff (Faza 1+).
- **Concurrent downloads:** max 10 jednocześnie (per import session) — żeby nie zalewać sieci.

### 7.5 Walidacja per row

- **Required fields** zdefiniowane w `ObjectTypeAttribute.required` (z Modelowania) — domyślnie: `sku`, `name`.
- **Unique fields** zdefiniowane w `Attribute.unique_value` — domyślnie: `sku`, `ean`.
- **Type validation** per `Attribute.type`: number → numeric, date → ISO 8601, select → value w `Attribute.options`.
- **Custom validation rules** z `Attribute.validation_rules JSONB` (regex, min/max).
- **Cross-attribute validation** — *out of scope MVP* (Faza 1+).

### 7.6 Concurrent imports

- **Same user:** allow + warn *„Już jeden import w toku"*. Drugi importation queue'd.
- **Different users:** allow simultaneous (każdy ma swój `import_session_id`).
- **Same product affected** (rare — race condition w Add-only mode): drugi import dostaje duplicate SKU error → skip.

### 7.7 Rollback

- **Window:** 24h od zakończenia importu (configurable per tenant w settings, max 7 dni).
- **Co się usuwa:** wszystkie objects z `import_session_id = X`. Soft delete (z możliwością restore w 7 dniach przez admin), potem hard delete.
- **Co NIE się usuwa:** assets pobrane z linków HTTP / ZIP (mogłyby być reusable). Klient ręcznie usuwa w DAM.
- **Side effects rollback:** sync handlers (Shopify / BaseLinker) — usuwa published products z kanałów też (cascade). Wymaga confirm modal: *„Cofnięcie usunie produkty z X kanałów. Kontynuuj?"*.
- **Po window:** rollback button disabled. Klient w razie potrzeby ręcznie deletuje produkty (bulk delete w epiku 02).

### 7.8 Manual pgBackRest backup

- **Trigger:** klient checkbox w Step 4. NIE automatyczny.
- **Mechanika:** API call `POST /api/backups`, status `pending` → `running` → `completed/failed`.
- **UI block:** wizard pokazuje *„Backup w toku..."* z progress, import button locked do completion.
- **Czas:** zwykle 5-30 min, zależy od size DB.
- **Rate limit:** max 1 backup/godz/tenant (żeby nie zabić disku).
- **Cost:** 0 zł — wbudowane w plan (pgBackRest stub w Sprint 0).
- **Restore:** *out of scope* manual UI, idzie przez admin runbook (Faza 1 Phase 1).

---

## 8. Dependency na backend

### 8.1 Encje + tabele (delta vs aktualnego planu)

```sql
-- Profile importu (per user)
CREATE TABLE import_profiles (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),
    name VARCHAR(255) NOT NULL,
    target_object_type_id UUID NOT NULL REFERENCES object_types(id),  -- np. product
    column_mapping JSONB NOT NULL,                                     -- {column_name: attribute_id}
    locale VARCHAR(8),
    encoding VARCHAR(32),
    delimiter VARCHAR(4),
    image_source VARCHAR(16),                                          -- 'http' | 'zip' | 'none'
    image_zip_naming_convention VARCHAR(64),
    custom_validation_rules JSONB,
    last_used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, user_id, name)
);

-- Sesje importu (audit trail per import)
CREATE TABLE import_sessions (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),
    profile_id UUID REFERENCES import_profiles(id),
    file_name VARCHAR(255) NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    zip_file_name VARCHAR(255),
    zip_file_size_bytes BIGINT,
    target_object_type_id UUID NOT NULL REFERENCES object_types(id),
    status VARCHAR(32) NOT NULL,                  -- pending, running, paused, success, partial, failed, cancelled, rolled_back
    total_rows INTEGER,
    success_count INTEGER NOT NULL DEFAULT 0,
    error_count INTEGER NOT NULL DEFAULT 0,
    images_downloaded INTEGER NOT NULL DEFAULT 0,
    images_failed INTEGER NOT NULL DEFAULT 0,
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    rollback_until TIMESTAMPTZ,                   -- 24h od completed_at
    rolled_back_at TIMESTAMPTZ,
    backup_snapshot_id UUID REFERENCES backups(id),  -- jeśli klient zrobił manual backup
    error_message TEXT
);

-- Logi per row (do raportu CSV + UI preview)
CREATE TABLE import_logs (
    id UUID PRIMARY KEY,
    import_session_id UUID NOT NULL REFERENCES import_sessions(id) ON DELETE CASCADE,
    row_number INTEGER NOT NULL,
    sku VARCHAR(128),
    level VARCHAR(8) NOT NULL,                    -- info | warning | error
    error_type VARCHAR(32),                       -- duplicate_sku | missing_required | invalid_type | image_not_found | ...
    message TEXT NOT NULL,
    column_name VARCHAR(128),
    column_value TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_import_logs_session ON import_logs(import_session_id);

-- Backupy (manual pgBackRest snapshots)
CREATE TABLE backups (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    triggered_by_user_id UUID NOT NULL REFERENCES users(id),
    triggered_by_action VARCHAR(32),              -- 'manual' | 'pre_import' | 'scheduled'
    pgbackrest_label VARCHAR(255),
    status VARCHAR(32) NOT NULL,                  -- pending, running, completed, failed
    size_bytes BIGINT,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ,
    error_message TEXT
);

-- Update: objects (dodanie import tracking)
ALTER TABLE objects ADD COLUMN import_session_id UUID REFERENCES import_sessions(id);
CREATE INDEX idx_objects_import_session ON objects(import_session_id) WHERE import_session_id IS NOT NULL;

-- Update: object_values (dodanie provenance import meta — już istnieje provenance flag, dochodzi import_session_id w meta)
-- (provenance_meta JSONB już zaplanowane w ADR-006)
```

### 8.2 API endpoints

| Endpoint | Metoda | Cel |
|---|---|---|
| `/api/import-sessions` | POST | Start import (multipart/form-data: file, zip_file?, profile_id?, locale, encoding, delimiter, mapping JSON, do_backup) |
| `/api/import-sessions` | GET | Lista user's import sessions z filters |
| `/api/import-sessions/{id}` | GET | Status + counts + file metadata |
| `/api/import-sessions/{id}/logs` | GET | Paginated logs (filter level, error_type) |
| `/api/import-sessions/{id}/report.csv` | GET | Download raport CSV |
| `/api/import-sessions/{id}/rollback` | POST | Trigger soft rollback |
| `/api/import-sessions/{id}/cancel` | POST | Anuluj running import |
| `/api/import-sessions/{id}/pause` | POST | Pauza running import |
| `/api/import-sessions/{id}/resume` | POST | Resume paused |
| `/api/import-sessions/auto-map` | POST | Body: column_headers, sample_values, target_object_type_id → suggested_mapping |
| `/api/import-sessions/dictionary` | GET | Cached rules-based dictionary (JSON) |
| `/api/import-sessions/validate-dry-run` | POST | Walidacja preview bez tworzenia objects |
| `/api/import-profiles` | POST/GET | CRUD profile (per user) |
| `/api/import-profiles/{id}` | PATCH/DELETE | Edit/delete profile |
| `/api/backups` | POST | Trigger manual pgBackRest snapshot |
| `/api/backups/{id}` | GET | Backup status |

### 8.3 Symfony Messenger handlers

- `ImportFileParseHandler` — parsuje plik (PhpSpreadsheet dla xlsx, league/csv dla csv), zwraca rows + headers do session.
- `ImportRunHandler` extends `AbstractBatchHandler` (ADR-006 z `EntityManager::clear()` per chunk N=200) — async batch processing.
- `ImageDownloadHandler` — concurrent download (max 10 parallel) z timeout, retry, content-type check.
- `ZipExtractHandler` — extracts ZIP do temporary storage, mapping nazwy pliku → asset entity.
- `ImportRollbackHandler` — soft delete objects + cascade do object_values + delete linked assets jeśli requested.
- `BackupSnapshotHandler` — wraps pgBackRest CLI, async progress reporting via Mercure.

### 8.4 Doctrine listeners

- `ImportSessionListener` — przy `Object` post-persist gdy `import_session_id` set, inkrementuje `import_sessions.success_count`.
- `ImportRollbackListener` — przy `Object` soft-delete sprawdza window 24h, blokuje rollback po expiration.

### 8.5 Mercure SSE channels

- `imports.{user_id}` — wszystkie imports tego usera (lista live updates).
- `imports.{session_id}` — pojedynczy import progress (subscribed gdy user otwiera detail).

### 8.6 Storage

- **Uploaded files:** MinIO bucket `imports-{tenant_id}` z TTL 7 dni (auto-clean stare pliki).
- **ZIP extracted:** temporary directory `/var/imports/{session_id}/` w container, cleanup post-import.
- **Backup snapshots:** pgBackRest repo (z architektury sekcji 12.3a).

---

## 9. Komponenty Refine + shadcn

### 9.1 Refine resources

- `Refine.Resource("import-sessions")` — list, show, create.
- `Refine.Resource("import-profiles")` — full CRUD.
- Custom hook `useImportProgress(sessionId)` — Mercure SSE subscribe with auto-cleanup.
- Custom hook `useDictionary()` — cached dictionary fetch (5 min TTL).

### 9.2 shadcn components

- `Form`, `Input`, `Select`, `Button`, `Checkbox`, `RadioGroup`, `Combobox` (shadcn).
- `Tabs` — sub-tabs Imports / Exports / Integracje / API Configurator.
- `Table`, `DataTable` (TanStack) — list view + mapping table.
- `Dialog` — confirm modals (rollback, cancel).
- `Sheet` — Step wizard fullscreen overlay.
- `Progress` — import progress bar.
- `Alert` — błędy walidacji, ostrzeżenia.
- `Card` — KPI boxes (success / errors / runtime).

### 9.3 Custom components

| Komponent | Rola |
|---|---|
| `ImportWizard` | 4-step wizard z state management + step validation |
| `FileDropzone` | Multi-file drag-drop (CSV/Excel + ZIP), progress, preview |
| `MappingTable` | Auto-mapping table z dropdown picker per row + skip option |
| `MappingPreview` | Sample values per kolumna (first 3 rows) |
| `ValidationResults` | Top 10 errors + show all modal + CSV download |
| `ImportProgress` | Live progress bar + counters z Mercure SSE |
| `RollbackButton` | Z 24h window check + cascade warning modal |
| `ImportProfileManager` | Modal CRUD profili usera |
| `BackupTriggerCheckbox` | Opcjonalny manual backup z status indicator |
| `EncodingDetector` | Auto-detect encoding z user override dropdown |
| `LocalePicker` | Dropdown z available locales (z tenant config) |

---

## 10. Open questions

- [ ] **Manual pgBackRest UI** — w MVP czy Faza 1? Sprint 0 ma stub. UI wrapper 4-6h. Decyzja: **MVP** (Marcin chce checkbox w wizard).
- [ ] **AI auto-mapping (Faza 2)** — czy hybrid jak proponowałem na początku? Marcin wybrał (a) rules-only w MVP, można rozszerzyć w Fazie 2 jako *„Spytaj AI o mapping"* button. Notuję jako Faza 2 candidate.
- [ ] **Email notification** — po jakim runtime threshold? Sugeruję **5 minut** (krótsze nie warte email'a). Ale customizable per user w Settings (Faza 1).
- [ ] **Import w tle vs foreground** — gdy user zamyka tab, importation kontynuuje w background. Po powrocie user widzi status. OK?
- [ ] **Per-tenant dictionary** — czy klient enterprise może rozszerzyć słownik aliasów? Faza 1+ kandydat.
- [ ] **Multi-locale w jednym pliku** — Faza 1+ — jak rozpoznawać kolumny per-locale (`name_pl`, `name_en` → `name.pl`, `name.en`)?
- [ ] **Recurring import** (cron) — Faza 1 — UI dla scheduling z FTP / S3 source?
- [ ] **Variants import** — wide format (`color_red_size_M_price`) czy parent_sku column? Faza 1+ decision.
- [ ] **Webhook integration** — alert na Slack / Teams / custom URL po imporcie? Faza 1+ kandydat.
- [ ] **CSV BOM handling** — auto-strip czy preserve? Default: strip.
- [ ] **Excel z formulae** — evaluate vs cached values? Default: cached values (PhpSpreadsheet domyślny).
- [ ] **Empty cells** — różnica między null vs ""? Default: null.

---

## 11. Wpływ na backend roadmap (delta vs `Project Plan/02-plan-projektu-pim.md`)

Feature Imports wpływa na:

- **Epik 0.4 (API Platform — exposing entities)** — dochodzą endpointy: `import-sessions` CRUD + custom (rollback, cancel, auto-map, validate-dry-run), `import-profiles` CRUD, `backups` trigger. **Estymacja: +12-16h** ponad obecny scope.
- **Epik 0.6 (Admin UI — core CRUD)** — dochodzi 4-step wizard + management views. **Estymacja: +25-35h** dla pełen UI flow.
- **Epik 0.10 (API Configurator)** — Imports jest 4-tym sub-tab'em, scope rośnie z obecnych 28-44h do **+15-20h** (sam Imports).
- **Epik 0.11 (Hardening)** — manual pgBackRest UI button + rate limit + audit log. **Estymacja: +6-10h**.
- **Backend handlers (poza istniejącymi epikami)** — Symfony Messenger handlers + image download + ZIP extract + dictionary builder. **Estymacja: +20-30h**.

**Total impact na Faza 0:** **+78-111h**. Aktualny budżet z PRD ~310-440h (po MVP-Beta-Demo + ADR-009/010/011/012 + epik 02 produkty) → **~390-550h**.

To jest *znaczący* dodatek. Marcin akceptuje +50-80h scope (PRD § 12.1) — przekraczamy ten zakres. **Decyzja:** rozważyć **przesunięcie części Imports na Fazę 1** (np. recurring imports, custom validation rules, advanced ZIP features) lub **akceptować wzrost scope** o ~30-40h ponad PRD limit.

**Drivery scope'u:**
- 4-step wizard UI: ~16-20h.
- Auto-mapping dictionary build (top 30 atrybutów × 5-10 synonimów): ~10-14h.
- File parsing (PhpSpreadsheet + league/csv) z error handling: ~6-8h.
- Image download workers + ZIP extraction: ~10-14h.
- Async Messenger + Mercure SSE: ~6-8h.
- Rollback functionality: ~4-6h.
- Manual pgBackRest UI: ~4-6h.
- Profile management CRUD: ~4-6h.
- Validation engine + error categorization: ~6-8h.
- Tests E2E (100 / 500 / 5000 rows): ~6-8h.

---

## 12. Co dalej

1. **Walidacja koncepcji** z Marcinem — czy 4-step wizard to dobry flow, czy wolisz mniej/więcej kroków.
2. **POC dictionary** — zbuduj słownik PL/EN dla top 30 atrybutów w Sprint 1, przetestuj na 5 sample plikach od potencjalnych klientów.
3. **Wireframes w Figma** — przekazać external UX designer'owi (z PRD § 13.5).
4. **Klikalny prototyp** — przed implementacją, walidacja flow z Kasią/Marcinem.
5. **Decyzja scope MVP vs Faza 1** — czy akceptujemy +78-111h scope, czy przesuwamy część na Fazę 1.
6. **Aktualizacja `Project Plan/02-plan-projektu-pim.md`** — epik 0.4, 0.6, 0.10, 0.11 estymacji + ewentualne nowe ryzyko *„R-29 Imports scope creep"*.
7. **Aktualizacja `Project Plan/03-funkcjonalnosci-mvp.md`** — dodać user stories US-IMP-001 do US-IMP-014.
8. **Update `epik-04-publikacje.md`** — z placeholder'a do *„szczegół"* z linkiem do tego dokumentu.

---

## 13. Delivery snapshot — 2026-05-07

| Ticket | PR | Scope (highlights) |
|---|---|---|
| **IMP-01** | #457 | Schema (`import_sessions`, `import_profiles`, `import_logs`, `backups` + `objects.import_session_id`), entities + voters + audit + composer deps (PhpSpreadsheet 5.7, league/csv 9.28). |
| **IMP-02** | #458 | `FileParserService` (xlsx + csv, encoding/delimiter detection), `MappingDictionaryService` (PL/EN YAML), `AutoMapper` (exact + Levenshtein), `POST /api/import-sessions/auto-map`. |
| **IMP-03** | #459 | `ImportValidationService` (5 typy błędów per spec §5.4), `POST /api/import-sessions/validate-dry-run`, sync small-import (<50 rows) reuse. |
| **IMP-04** | #460 | `ImportRunHandler extends AbstractBatchHandler` (chunk=200), `POST /api/import-sessions` async via Symfony Messenger, Mercure progress (`imports.{user_id}` + `imports.{session_id}`). **Image download / ZIP extract odsunięte** do follow-up'u (poza chunked happy path scope). |
| **IMP-05** | #461 | `ImportRollbackService` (24h window soft delete) + `POST /api/import-sessions/{id}/rollback` + `GET /api/import-sessions/{id}/report.csv`. |
| **IMP-06** | #462 | `BackupSnapshotHandler` async wraps `pgbackrest` CLI via `Symfony\Process`. `POST /api/backups` + state machine + rate limiter (1/h/tenant). |
| **IMP-07** | #463 | `ImportProfile` ApiPlatform CRUD + per-user voter + cross-user isolation tests. |
| **IMP-08** | #464 | Frontend foundation: shadcn primitives (Stepper, Progress, Combobox, DataTable), `FileDropzone`, Refine resources, i18n keyspace. |
| **IMP-09** | #465 | Imports list view + Publikacje sub-tab + enable "Importuj z Excel/CSV" CTA na empty-state-products. |
| **IMP-10** | #466 | Wizard Step 1 (Upload) + Step 2 (Mapping) + deep-link "+ Stwórz nowy atrybut" do `/modeling/attributes` z preserved state. |
| **IMP-11** | #467 | Wizard Step 3 (Validation) + Step 4 (Confirm) + `BackupTriggerCheckbox` z polling. |
| **IMP-12** | #468 | `useImportProgress` (Mercure SSE) + combined progress/results screen + `RollbackButton` z 24h window check. |
| **IMP-13** | #469 | Profile manager modal (Sheet) z DataTable + inline edit. |
| **IMP-14** | #470 | E2E smoke (`apps/admin/e2e/imports.spec.ts`) + `agent/lessons.md` § 2026-05-07 (11 lekcji). **Dogfooding US-IMP-005 odsunięte** — wymaga realnego export'u IdoSell, niedostępne w marathon. |
| **IMP-15** | #(this) | Plan/PRD update (status flip), `epik-04-publikacje.md` link, `03-funkcjonalnosci-mvp.md` US-IMP-001..014. |

**Świadome odejścia od planu** (do follow-up'u):
- **Image download** + **ZIP extract** (IMP-04 plan §IMP-04) — handler dispatchuje proces ale realnego download'u nie wykonuje. Wymaga: kolejka `imports.images`, `ImageDownloadHandler`, `ZipExtractHandler`. Wycena follow-up: 6-8h.
- **Dogfooding US-IMP-005** (IMP-14) — gate przed *„działa na realnych danych"* nadal otwarty.
- **6 dodatkowych Playwright spec'ów** (z planu IMP-14: 100 rows happy path, 500 rows + ZIP, rollback, async 5000, duplicate SKU) — zachowane jako 1 smoke spec; follow-up: rozbudowa do 6 spec'ów po dogfooding'u (kiedy fixture'y realne).
- **Performance benchmark 5k rows < 256 MB** (IMP-14) — pomijany w marathonie (brak fixture 5k); follow-up razem z dogfooding'iem.

---

*Plik wersjonowany w `Project Plan/UI/`. Status: zaimplementowane. Następna iteracja: dogfooding katalogu IdoSell (Marcin) + image download/ZIP follow-up + rozbudowa E2E suite.*
