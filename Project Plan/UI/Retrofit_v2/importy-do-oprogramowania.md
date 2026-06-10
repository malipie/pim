# Importy (kreator 6 kroków + sesje) — backlog do oprogramowania

> Luki backendowe odkryte przy projektowaniu/realizacji NUI-10/NUI-11 (wizard importu v2, widok sesji v2).
> Konwencja: każdy element zamockowany w UI ma tu wpis; przy podjęciu tematu wpis → GitHub Issue.
> Design: `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/Import-nowy.html`, `Import-sesja.html`.

## Frontend-only (mock UI — czeka na backend)

- **Wybór arkusza XLSX** (krok 2) — `ParsePreviewController` zwraca `had_multiple_sheets` + `sheet_name`, ale parsuje pierwszy arkusz; radio wyboru disabled + `MockBadge`.
- **Obsługa pustych komórek** (krok 2: null / pusty string / default z modelu) — brak parametru w backendzie; radio disabled.
- **Kolumny obliczane** (krok 3: modal konkatenacji z separatorami i live preview) — preview działa na `sample_rows` client-side, „Zastosuj" disabled.
- **Tryb insert/update + strategie duplikatów** (krok 4: skip / overwrite / create-variant) — przełączniki disabled; karta opisuje realne zachowanie (upsert po identyfikatorze).
- **Ad-hoc „uruchom import z zapisanego źródła"** (krok 1) — kafel z `MockBadge`, link do Harmonogramu (istnieje tylko `POST /api/import-schedules/{id}/run-now`).
- **Timeline faz z czasami** (widok sesji) — fazy wyprowadzane ze statusu (done/active/pending) bez timestampów per faza.

## Frontend + nowy endpoint backendowy / rozszerzenie kontraktu

- **Wybór arkusza**: param `sheet` w `ParsePreviewController` i `StartImportController` (+ lista arkuszy w odpowiedzi parse-preview).
- **Strategia pustych komórek**: param `empty_cell_strategy: null|empty_string|model_default` w `StartImportController` + obsługa w pipeline wartości.
- **Kolumny obliczane (konkatenacja server-side)**: rozszerzenie `mapping` o wpisy `{type: "computed", source_columns: [...], separator, target_attribute}`; walidacja w dry-run.
- **Tryb importu**: `mode: upsert|insert_only|update_only` w `StartImportController` + raport pominiętych.
- **Strategie duplikatów**: `duplicate_strategy: skip|overwrite|create_variant` (create_variant wymaga decyzji — patrz niżej).
- **Ad-hoc run ze źródła**: `POST /api/import-sources/{id}/run` (pobranie pliku ze źródła FTP/SFTP + start sesji z profilem); dziś wymaga harmonogramu.
- **Timestampy per faza sesji**: kolumny/JSONB `phase_timestamps` w `ImportSession` + eventy Mercure per zmiana fazy → timeline z czasami i czasem trwania faz.
- **Detekcja rozszerzona** (krok 2 designu pokazuje: separator dziesiętny, format dat, line-endings, quote char) — dziś SKIP w UI; jeśli wartościowe → rozszerzyć `ParsePreviewController` o heurystyki detekcji.

## Wymaga decyzji architektonicznej

- **`create_variant` jako strategia duplikatu** — tworzenie wariantu z kolidującego wiersza dotyka modelu wariantów (osie, rodzic) — wymaga ADR-owej decyzji zanim trafi do silnika.
- **Persystencja live logu sesji** — dziś log jest efemeryczny (Mercure, bufor w UI); decyzja czy logować wiersze do `ImportLog`/storage (koszt przy 100k+ wierszy) czy zostawić tylko agregaty.
