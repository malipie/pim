# Multimedia (eksplorator plików) — backlog do oprogramowania

> Luki backendowe odkryte przy projektowaniu/realizacji NUI-08 (Multimedia v2).
> Konwencja: każdy element zamockowany w UI ma tu wpis; przy podjęciu tematu wpis → GitHub Issue.
> Design: `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/Multimedia.html`.

## Frontend-only (mock UI — czeka na backend)

- **Pasek magazynu („142 / 500 GB")** — w UI `MockBadge`. Wymaga endpointu quoty/zajętości.
- **Akcja i status „Zatwierdź" (approve)** — karty i drawer pokazują status approved/draft jako mock. Wymaga workflow approve.
- **Bulk „Pobierz" (zip)** — przycisk disabled z tooltipem. Wymaga endpointu paczkowania.
- **Licznik „powiązane produkty" per asset** (karta + sekcja drawera) — jeśli weryfikacja w NUI-08 wykaże brak danych w API → mock; wpis do aktualizacji po tickecie.

## Frontend + nowy endpoint backendowy

- **Quota magazynu**: `GET /api/assets/storage-usage` → `{used_bytes, quota_bytes}` (per tenant; źródło: suma rozmiarów w MinIO/S3 lub agregat z metadanych). Zasila pasek magazynu.
- **Workflow approve assetów**: pole statusu (`draft|approved`) + `PATCH /api/assets/{id}` akcja approve + filtr statusu na liście. Decyzja: osobne pole vs reuse `enabled`.
- **Bulk zip download**: `POST /api/assets/bulk-download` (async job + link do pobrania; duże paczki przez Messenger, pattern jak eksporty).
- **Powiązane produkty per asset**: endpoint zwracający produkty referencjonujące asset (odwrócony indeks po atrybutach typu `asset`); liczniki na kartach + lista pill-i w drawerze.
- **Zagnieżdżone foldery**: model folderu z `parent` (obecnie płaska lista z `GET /api/asset-folders`). Design pokazuje drzewo (Produkty → audio/laptopy/…). Wymaga decyzji modelowej (ltree jak kategorie? osobna encja?) — patrz sekcja niżej.

## Wymaga decyzji architektonicznej

- **Foldery: płaskie vs drzewo** — dziś folder to atrybut/etykieta; drzewo wymaga encji z hierarchią i migracji istniejących przypisań. Kandydat na mały ADR przy podjęciu.
- **CDN/signed URLs** — drawer pokazuje „URL CDN"; dziś serwujemy bezpośrednio. Decyzja o CDN/podpisywanych linkach przy hardeningu (epik 0.11 / Faza 1).
