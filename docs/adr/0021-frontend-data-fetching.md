# ADR-0021 — Strategia pobierania danych w adminie (useQuery jako domyślny mechanizm)

- Status: Accepted
- Data: 2026-06-20
- Kontekst audytu: audyt połówkowy 2026-06, finding AUD-055 (Wave 3 / W3-2, #1608)
- Powiązane: lekcja `feedback_useeffect_to_usequery_pattern`, CLAUDE.md („Polish matters" / API-first), ADR-0020 (kształt powierzchni API)

## Kontekst

Admin (`apps/admin`) pobiera dane na **trzy** współistniejące sposoby:

- **`jsonFetch` + `useState`/`useEffect`** — ~138 plików. Surowy wrapper `fetch` (`src/lib/http.ts`) z ręcznym zarządzaniem `isLoading`/`error`/`refetch`.
- **`useQuery` (TanStack Query)** — ~55 plików. Cache z kluczami, dedup, automatyczna inwalidacja przez `queryClient.invalidateQueries`.
- **Refine data-provider** (`useList`/`useOne`/`useTable`) — ~50 plików. Warstwa CRUD frameworka Refine.

Problem nie jest kosmetyczny. **Mutacje w wielu ekranach inwalidują cache przez `queryClient`** (`invalidateQueries` używane w kilkudziesięciu komponentach katalogu — `attributes/show`, `object-types/show`, `attribute-groups/show`, taby produktu itd.). Ekran, który ładuje te same dane przez `jsonFetch` + `useEffect`, **nie reaguje na inwalidację** — `useEffect` z zależnością `[]` (albo `[refetch]`) odpala się raz przy mount i nie wie nic o `queryClient`. Efekt: lista nie odświeża się po `create`/`delete`/`update` wykonanym gdzie indziej, operator widzi nieaktualny stan do ręcznego F5.

To dokładnie wzorzec z lekcji `feedback_useeffect_to_usequery_pattern`: *„gdy dane są inwalidowane przez `queryClient` w innym miejscu, a `useEffect`+`jsonFetch` je ładuje, to bug stale-data — refaktor do `useQuery`"*.

`jsonFetch` sam w sobie zostaje — jest poprawnym, jednoźródłowym (single-origin Caddy) transportem HTTP z obsługą silent-refresh JWT (#29). Jest **warstwą transportu**, nie warstwą cache. `useQuery` ma go wołać w `queryFn`.

## Decyzja

1. **Nowy kod reagujący na dane = `useQuery` (TanStack Query).** Każdy nowy ekran/komponent, który czyta dane serwerowe mogące się zmienić wskutek mutacji (listy, detale, liczniki, statusy), używa `useQuery` z jawnym `queryKey`. `queryFn` woła `jsonFetch` (transport zostaje jednolity).
2. **`jsonFetch` + `useEffect` jest deprecated dla danych reagujących na mutacje.** Dozwolone pozostaje wyłącznie dla:
   - **akcji komendowych** (POST/PATCH/DELETE wywoływane z handlerów — mutacje, nie odczyt),
   - **odczytów jednorazowych, niereaktywnych** (np. pobranie pliku, eksport blob, parse-preview uploadu), które nie są współdzielone z żadnym `queryKey`.
3. **Refine zostaje tam, gdzie już jest.** Nie migrujemy istniejących ekranów Refine → useQuery i nie wprowadzamy Refine w nowych miejscach, gdzie go nie ma. Trzy mechanizmy redukujemy do dwóch w długim horyzoncie (`useQuery` + Refine), eliminując `jsonFetch`+`useEffect` jako wzorzec odczytu.
4. **Egzekwowanie przyrostowe (stop-the-bleeding), nie big-bang.** Pełna migracja ~138 plików to robota klasy L i NIE jest treścią tego ticketu. Zamiast tego:
   - skrypt CI `scripts/lint-jsonfetch-useeffect.sh` **zlicza** pliki z współwystępującym `jsonFetch` + `useEffect` i pilnuje **nierosnącego progu** (baseline zamrożony w skrypcie). Nowy plik z tym wzorcem = czerwone CI, dopóki autor nie użyje `useQuery` albo świadomie nie podniesie baseline z uzasadnieniem.
   - migracja istniejących plików idzie priorytetowo: **najpierw ekrany mutation-reactive** (listy odświeżane po create/delete, detale inwalidowane z innych ekranów), reszta oportunistycznie przy okazji dotykania pliku.

## Konsekwencje

**Pozytywne**

- Znika cała klasa bugów stale-data: ekran na `useQuery` z właściwym `queryKey` odświeża się automatycznie, gdy mutacja gdziekolwiek zawoła `invalidateQueries` na tym kluczu.
- Mniej kodu boilerplate (`isLoading`/`error`/`refetch` znika z komponentu), dedup zapytań i cache za darmo.
- Bramka CI hamuje przyrost długu — liczba ekranów z anty-wzorcem może tylko maleć.

**Negatywne / dług**

- ~136 plików nadal na `jsonFetch`+`useEffect` po tym ticketcie. To **świadomie odłożony** dług (migracja L), wytropiony i zamrożony progiem, nie ukryty. Tracking w osobnym tickecie utrzymaniowym.
- Baseline w skrypcie wymaga aktualizacji przy każdej migracji w dół (intencjonalnie — wymusza ruch wyłącznie w dobrą stronę).

## Roadmap / remediacja

- **Ten ticket (proof-slice):** migracja `settings/locales/index.tsx` (lista lokali z modalem „Dodaj" + inline lifecycle — kanoniczny przypadek listy reagującej na mutacje) z `jsonFetch`+`useEffect` na `useQuery` + `invalidateQueries`, z testem jednostkowym dowodzącym, że inwalidacja odświeża listę.
- **Follow-up (utrzymaniowy, L):** migracja pozostałych ekranów mutation-reactive (priorytet: listy w `settings/*`, `admin/*`, `exports/sessions/*`), schodzenie progiem w `lint-jsonfetch-useeffect.sh` w dół przy każdej migracji.
