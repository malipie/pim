# Typy atrybutów — Pimcore vs Akeneo

> Źródła: docs.pimcore.com (2024.x/2025.x), help.akeneo.com (Serenity)
> Data: 2026-05-31

---

## PIMCORE

Pimcore organizuje typy danych w **kategorie**. Każdy typ to osobna klasa PHP dziedzicząca z `Pimcore\Model\DataObject\ClassDefinition\Data`. Typy mogą być używane wewnątrz Class Definition, Object Bricks, Field Collections i Classification Store.

---

### 1. Typy tekstowe (Text Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Input** | Jednolinijkowe pole tekstowe | Długość maksymalna (VARCHAR), wartość domyślna |
| **Textarea** | Wielolinijkowy tekst bez formatowania | Kolumna TEXT w DB |
| **WYSIWYG** | Edytor rich-text (HTML) — przechowuje HTML, obsługuje linki do assetów i dokumentów | Brak walidacji wbudowanej; zależności do assetów śledzone automatycznie |
| **Password** | Pole hasła z ukrytymi znakami; hash algorytmem konfigurowalnym (bcrypt, argon2 itp.) | Algorytm hashowania; długość nie jest konfigurowalna (hash jest zawsze stały) |
| **Input Quantity Value** | Tekst + jednostka miary (wariant Input Quantity Value dla wartości nie czysto numerycznych) | Lista dozwolonych jednostek (globalna lub per-pole) |

---

### 2. Typy numeryczne (Number Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Numeric** | Liczba całkowita lub dziesiętna (DOUBLE w DB) | Wartość domyślna, min/max (opcjonalne) |
| **Numeric Range** | Dwa pola Numeric (minimum + maksimum) | Jak Numeric, para wartości |
| **Slider** | Suwak poziomy lub pionowy (DOUBLE w DB) | **Wymagane**: wartość min, max, krok (step), precyzja dziesiętna; orientacja (horizontal/vertical) |
| **Quantity Value** | Liczba + jednostka miary; obsługuje konwersję jednostek (np. km → m) | Lista globalnych jednostek; możliwość ograniczenia per-pole; konwersja przez bazową jednostkę + współczynnik + offset |
| **Quantity Value Range** | Para pól Quantity Value (min + max) | Jak Quantity Value |

---

### 3. Typy wyboru (Select Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Select** | Lista rozwijana, jedna wartość (VARCHAR) | Opcje: wartość + etykieta wyświetlana; wartość domyślna |
| **Multiselect** | Lista wielokrotnego wyboru (TEXT, wartości CSV) | Opcje: wartość + etykieta; tryb renderowania: list lub tags |
| **Country** | Select z predefiniowanymi kodami krajów ISO | Brak dodatkowych walidacji |
| **Country Multiselect** | Multiselect krajów ISO | Brak dodatkowych walidacji |
| **Language** | Select z lokale systemowymi (ograniczone do aktywnych w systemie) | Opcjonalne: tylko języki aktywne |
| **Language Multiselect** | Multiselect lokale systemowych | Jak Language |
| **User** | Select użytkowników systemu Pimcore (powiązanie z user management) | Brak dodatkowych walidacji |

---

### 4. Typy daty i czasu (Date Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Date** | Pole daty (kalendarz w UI) | Wartość domyślna; zakres min/max (opcjonalne) |
| **Datetime** | Data + godzina | Wartość domyślna; zakres min/max |
| **Time** | Tylko godzina (format HH:MM:SS) | Min/max godzina |
| **Date Range** | Para pól daty (from–to) | Jak Date, dwa pola |

---

### 5. Typy geograficzne (Geographic Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Geopoint** | Para lat/lng; widżet mapy w UI | Brak dodatkowych walidacji; przechowywane w dwóch kolumnach |
| **Geobounds** | Prostokąt geograficzny: NE + SW punkt | Dwie pary lat/lng |
| **Geopolygon** | Dowolna liczba punktów tworzących obszar (LONGTEXT, serialized array) | Brak dodatkowych walidacji |
| **Geopolyline** | Dowolna liczba punktów tworzących linię (LONGTEXT, serialized array) | Brak dodatkowych walidacji |

---

### 6. Typy relacji (Relation Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Many-To-One Relation** | Relacja do jednego elementu Pimcore (dokument, asset, obiekt) | Konfigurowalny typ dozwolonych elementów (object/document/asset) i podtyp |
| **Many-To-Many Relation** | Relacje do wielu elementów Pimcore (dowolny typ) | Konfigurowalny typ; lazy loading |
| **Many-To-Many Object Relation** | Relacje do wielu obiektów (tylko obiekty, nie dokumenty/assety) | Ograniczenie do klas obiektów; lazy loading |
| **Advanced Many-To-One Object Relation** | Relacja do obiektu + metadane na relacji (text, number, select, boolean); tylko jedna dozwolona klasa | Typy i liczba kolumn metadanych; jedna dozwolona klasa |
| **Advanced Many-To-Many Relation** | Relacje do wielu elementów dowolnego typu + metadane (ElementMetadata) | Typy kolumn metadanych |

---

### 7. Typy mediów / obrazów (Media Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Image** | Pojedynczy obraz (link do assetu) | Konfigurowalny miniaturowy widok w UI |
| **Image Gallery** | Kolekcja obrazów | Brak dodatkowej walidacji poza relacją do assetów |
| **Hotspot Image** | Obraz z obszarami klikalnymi (hotspoty/markery z metadanymi) | Metadane hotspota: typ, pozycja, rozmiar |
| **Video** | Link do wideo (asset lub zewnętrzny URL: YouTube, Vimeo, Dailymotion) | Typ źródła (asset/youtube/vimeo/url) |
| **External Image** | Obraz z zewnętrznego URL (przechowuje URL, nie pobiera do assetu) | Brak walidacji URL |

---

### 8. Typy strukturalne (Structural / Container Types)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Block** | Tablica powtarzalnych grup prostych pól wewnątrz obiektu (przechowywane serializowane w DB, nie są oddzielnymi encjami) | Dowolne typy prostych pól wewnątrz; brak walidacji strukturalnej |
| **Field Collections** | Predefiniowane zestawy pól wielokrotnego użytku; każda kolekcja to oddzielna tabela DB; dowolna liczba instancji per obiekt | Własna definicja klasy kolekcji; możliwość ograniczenia dozwolonych typów kolekcji |
| **Object Bricks** | Dodatkowe zestawy atrybutów dokładane do obiektu; jedno na obiekt per typ bricka (w przeciwieństwie do Field Collections) | Własna definicja klasy bricka; możliwość włączenia/wyłączenia per obiekt |
| **Classification Store** | Dynamiczne klucze atrybutów organizowane w grupy i kolekcje; klucz może należeć do wielu grup; przeznaczony do dużej liczby kategorii (>30) | Klucze: dowolny prosty typ danych; soft-delete kluczy (flaga enabled=0); wiele kolekcji per obiekt |
| **Localized Fields** | Kontener dla pól tłumaczonych per locale; wartości przechowywane w osobnej tabeli `object_localized_fields_ID` | Obsługiwane typy wewnątrz: wszystkie proste typy oprócz relacyjnych |

---

### 9. Pozostałe typy (Others)

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Checkbox** | Pole logiczne (TINYINT 0/1) | Wartość domyślna (zaznaczony/odznaczony) |
| **Boolean Select** | Trójstanowy checkbox (checked=1, unchecked=-1, empty=null) — rozwiązuje problem dziedziczenia zwykłego Checkbox | Konfigurowalne etykiety dla yes/no/empty |
| **Link** | URL z tytułem, targetem i parametrami; przechowywany jako serialized TEXT | Brak wbudowanej walidacji URL |
| **RGBA Color** | Kolor w formacie RGBA; picker w UI; hex w DB (dwie kolumny: RGB + Alpha) | Brak dodatkowych walidacji |
| **Encrypted Field** | Szyfruje wartość dowolnego innego pola (AES-256 przez `defuse/php-encryption`) | Wymagany klucz szyfrowania w config; strict mode (exception gdy brak deszyfrowania) |
| **URL Slug** | SEO-friendly URL dla obiektu; Pimcore obsługuje routing per slug | Niedozwolone znaki `?` i `#`; brak wbudowanej walidacji unikalności (globalna w Pimcore) |
| **Table** | Prosta tabela wartości wewnątrz obiektu | Konfigurowalny rozmiar tabeli |
| **Consent** | Zgoda GDPR z datą, timestampem i notką | Brak dodatkowych walidacji |

---

---

## AKENEO (Serenity — wersja SaaS)

Akeneo używa pojęcia **Family** (rodzina) — atrybuty są przypisywane do rodzin, a produkty należą do rodzin. W Serenity dostępnych jest **17 typów atrybutów**.

Każdy atrybut może być:
- **scopable** (różne wartości per kanał)
- **localizable** (różne wartości per locale)
- **locale-specific** (widoczny tylko dla wybranych lokali)
- **read-only** *(Enterprise only)*
- **mandatory** (wymagany przy tworzeniu/aktualizacji produktu; max 5 atrybutów)
- **usable in grid** (jako kolumna lub filtr w liście produktów)

---

### Typy atrybutów Akeneo

| Typ | Opis | Walidacje / konfiguracja |
|---|---|---|
| **Identifier** | Unikalny kod identyfikujący produkt; do 20 identyfikatorów per katalog; jeden z nich jest głównym (SKU by default) — obowiązkowy do tworzenia produktów | **GTIN**: 8, 12, 13 lub 14 cyfr + checksum; wartość unikalna per produkt (nienegocjowalne) |
| **Text** | Jednolinijkowy tekst (VARCHAR 255) | Max liczba znaków (limit 255); reguła walidacji: URL / Email / Regex; **GTIN** (jak wyżej); przeszukiwalny w głównym pasku wyszukiwania (do 20 atrybutów) |
| **Text Area** | Wielolinijkowy tekst (TEXT, do 65 535 znaków); opcjonalny edytor rich-text (WYSIWYG) | Max liczba znaków; rich text editor on/off; usuwanie formatowania HTML przy wklejaniu; lista niedozwolonych znaków (Unicode code points) |
| **Simple Select** | Jednokrotny wybór z predefiniowanej listy opcji | Wartość domyślna; auto-tworzenie nowych opcji podczas importu (opcjonalne) |
| **Multi Select** | Wielokrotny wybór z predefiniowanej listy opcji | Wartość domyślna; auto-tworzenie nowych opcji podczas importu |
| **Yes/No** | Wartość logiczna (boolean) | Wartość domyślna (ustawiana przy tworzeniu produktu z przypisaną rodziną) |
| **Date** | Data (picker kalendarzowy); format: tylko data `YYYY-MM-DD` lub data+czas `YYYY-MM-DD HH:MM` | Min date; max date; format (beta): date / datetime |
| **Number** | Liczba (całkowita lub dziesiętna) | Allow decimals; allow negative values; min value; max value; strategia dziesiętna: zaokrąglenie / stała liczba miejsc / trim zer |
| **Measurement** | Liczba + jednostka miary z przeliczaniem jednostek między kanałami | Allow decimals; allow negative values; min/max value; measurement family (waga, długość, obszar...); domyślna jednostka; strategia dziesiętna |
| **Price** | Wartość ceny per waluta; wyświetlane waluty zależą od aktywnych walut w PIM | Allow decimals; min/max value; zawsze 2 miejsca dziesiętne w eksporcie |
| **Image** | Wgrywany obraz (drag & drop) | Max rozmiar w MB; dozwolone rozszerzenia (gif, jfif, jpeg, jpg, pdf, png, psd, tif, tiff) |
| **File** | Wgrywany plik (drag & drop) | Max rozmiar w MB; dozwolone rozszerzenia (csv, doc, docx, mp3, pdf, ppt, pptx, rtf, svg, txt, wav) |
| **Asset Collection** *(Enterprise only)* | Kolekcja cyfrowych zasobów: obrazy, PDF, wideo (YouTube), łączona z rodziną assetów DAM | Max liczba elementów w kolekcji; powiązana rodzina assetów (asset family) |
| **Reference Entity Simple Link** *(Enterprise only)* | Jeden link do rekordu Reference Entity (bogate dane strukturalne: tekst, obrazy) | Powiązana Reference Entity |
| **Reference Entity Multiple Links** *(Enterprise only)* | Wiele linków do rekordów Reference Entity | Powiązana Reference Entity |
| **Table** *(Enterprise + Growth)* | Wielowymiarowe dane w tabeli z konfigurowalnymi kolumnami różnych typów | Per kolumna: text (max 300 znaków), number (decimals, min/max, default), select (default) |
| **Product Link** | Łączy produkty jako komponenty (Composable Products); nowość Serenity | Brak dodatkowych walidacji |

---

### Właściwości walidacji wspólne dla wszystkich atrybutów Akeneo

| Właściwość | Typy atrybutów | Opis |
|---|---|---|
| Unique value | Identifier, Text, Number | Wartość musi być różna dla każdego produktu |
| Value per channel | wszystkie oprócz Identifier | Różne wartości per kanał |
| Value per locale | wszystkie oprócz Identifier, Price | Różne wartości per locale |
| Read-only | wszystkie *(EE only)* | Edycja tylko przez import/API/reguły |
| Mandatory | Identifier, Simple Select, Number, Ref Entity Single, Text, Text Area, Yes/No | Wymagane przy tworzeniu/aktualizacji; max 5 atrybutów |

---

### Konwersja i migracja typów (Akeneo Serenity, nowość 2025)

Akeneo umożliwia zmianę typu atrybutu bez utraty danych w określonych ścieżkach:

| Z | Na |
|---|---|
| Text | Text Area |
| Number | Text, Text Area |
| Identifier | Text (zachowuje unique) |
| Simple Select | Text, Text Area, Multiselect, Ref Entity Simple/Multi |
| Multiselect | Ref Entity Multi Link |
| Ref Entity Simple | Ref Entity Multi Link |

---

## Podsumowanie porównawcze

| Cecha | Pimcore | Akeneo |
|---|---|---|
| Liczba typów | ~30+ (łącznie z strukturalnymi) | 17 (Serenity); część tylko EE |
| Typy geograficzne | ✅ Geopoint, Geobounds, Geopolygon, Geopolyline | ❌ Brak |
| Rich text / WYSIWYG | ✅ Osobny typ | ✅ Opcja w Text Area |
| Relacje obiekt↔obiekt | ✅ 5 typów z metadanymi | ✅ Reference Entity (EE), Product Link |
| Dynamiczne atrybuty | ✅ Classification Store | ❌ Brak (stała struktura Family) |
| Reusable sets of fields | ✅ Field Collections, Object Bricks | ❌ Brak |
| Szyfrowanie pól | ✅ Encrypted Field | ❌ Brak |
| GTIN walidacja | ❌ Brak wbudowanej | ✅ Na Identifier i Text |
| Regex walidacja | ❌ Brak wbudowanej (custom validator) | ✅ Na Text |
| Przeliczanie jednostek | ✅ Quantity Value (auto-konwersja) | ✅ Measurement (przeliczanie per kanał) |
| Wielowalutowość | ❌ Brak dedykowanego typu | ✅ Price per currency |
| Tłumaczenia atrybutów | ✅ Localized Fields (kontener) | ✅ Właściwość per atrybut (localizable) |
| Zmiana typu atrybutu | ❌ Ręcznie (eksport → usuń → stwórz → import) | ✅ Migracja przez UI (wybrane ścieżki) |

---

*Źródła:*
- [Pimcore Object Data Types](https://docs.pimcore.com/platform/Pimcore/Objects/Object_Classes/Data_Types/)
- [Pimcore Number Types](https://pimcore.com/docs/platform/Pimcore/Objects/Object_Classes/Data_Types/Number_Types/)
- [Pimcore Select Types](https://pimcore.com/docs/pimcore/current/Development_Documentation/Objects/Object_Classes/Data_Types/Select_Types.html)
- [Pimcore Relation Types](https://docs.pimcore.com/pimcore/10.2/Development_Documentation/Objects/Object_Classes/Data_Types/Relation_Types.html)
- [Pimcore Other Types](https://pimcore.com/docs/platform/Pimcore/Objects/Object_Classes/Data_Types/Others/)
- [Pimcore Geographic Types](https://docs.pimcore.com/platform/Pimcore/Objects/Object_Classes/Data_Types/Geographic_Types/)
- [Akeneo — What is an attribute?](https://help.akeneo.com/serenity-your-first-steps-with-akeneo/serenity-what-is-an-attribute)
- [Akeneo — Manage your attributes](https://help.akeneo.com/serenity-build-your-catalog/serenity-manage-your-attributes)
