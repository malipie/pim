# Epik 05 — Multimedia (DAM)

## Status: 🔵 placeholder

## 1. Cel epiku

Digital Asset Management — zarządzanie zdjęciami, PDF-ami, w przyszłości video / 360° / lookbookami. *Asset* jako predefiniowany ObjectType (`kind=asset`) z dedykowanym UX-em (drag-drop upload, masowe miniaturki, przypisywanie do produktów/usług/kategorii).

## 2. Persony

- **Kasia** — upload zdjęć produktowych z plików dostawcy, link do produktów.
- **Magda** — upload lifestyle photos dla D2C, organizacja w kolekcjach, akceptacja content (Faza 2).
- **Marcin (dogfooding)** — własne zdjęcia produktów IdoSell + Shopify, migracja DAM-a.

## 3. Kluczowe widoki

### 3.1 Asset Library (List view)
- Grid view (default) — thumbnails + metadata overlay (filename, size, linked products count).
- List view (alternative) — Table z filtrowaniem po extension, date, linked product, tag.
- Bulk select → bulk action: link to product, add tag, archive, delete.
- Filters: type (image / PDF / video Faza 1), size range, upload date, by-product, by-tag.
- Search by filename / metadata.

### 3.2 Upload (drag-drop)
- Multi-file drag-drop area (dziaa zewszad — globalny dropzone gdy klient przeciąga plik).
- Progress per plik z możliwością anulowania.
- Auto-rejected: za duży plik (>10 MB), zła ekstensja, duplikat (z opcją *„override"* lub *„keep both"*).
- Po upload — auto-thumbnails 200×200 i 800×800 (Imagick).

### 3.3 Asset detail
- Sticky header: filename, size, dimensions, format.
- Lewa kolumna: preview (image / PDF first page).
- Prawa kolumna: metadata, tags, linked products list (z możliwością unlink), provenance, audit log.
- W trybie edit: rename, retag, replace file (z zachowaniem ID + linked products — *do uzgodnienia*).

### 3.4 Linking do produktów (cross-epic z Produkty)
- W detail produktu: sekcja *„Images"* z drag-drop existing assets + upload inline.
- Główne zdjęcie (main image) — wyróżnione, drag do *„main"* slot.
- Galeria (do 10) — kolejność drag-drop.

### 3.5 PDF support (MVP — z PRD § 9.3)
- Upload + auto-preview pierwszej strony jako thumbnail.
- Open PDF in lightbox (browser PDF viewer).
- Linkowanie do produktów (np. datasheet PDF).

### 3.6 Cloudinary integration (Faza 1)
- Settings widok (*"Use Cloudinary as DAM backend"*) — Piotr konfiguruje API credentials.
- Migration wizard (*"przenieś istniejące asset'y do Cloudinary"*) — Faza 1+.
- Po włączeniu: upload trafia do Cloudinary, transformacje (resize per kanał) generowane on-demand.

## 4. User stories

- US-009: Upload i zarządzanie zdjęciami (DAM lite) — z `Project Plan/03-funkcjonalnosci-mvp.md`.
- **US-EP05-001:** Kasia drag-drop'uje 50 zdjęć, system robi thumbnails automatycznie, łączy z produktami.
- **US-EP05-002:** Magda upload'uje lookbook PDF (10 stron), system generuje preview pierwszej strony, link do kolekcji produktów.
- **US-EP05-003:** Piotr (Faza 1) konfiguruje Cloudinary jako backend — istniejące asset'y migrują, nowe trafiają do Cloudinary.
- _[TODO: AI metadata extraction (alt text, tags) — Faza 2]_
- _[TODO: variant generation per kanał (Shopify 1024×1024, Allegro 1200×1200) — Faza 1]_

## 5. Business rules / edge cases

- _[TODO: replace file — zachowanie ID i linked products czy tworzenie nowego asseta]_
- _[TODO: delete asset linked to N produktów — confirm cascade lub block]_
- _[TODO: storage quota per tier — limity przestrzeni na własnym DAM]_
- _[TODO: PDF max size — limity dla preview generation]_
- _[TODO: video upload — limit 100MB w Fazie 1?]_

## 6. Dependency na backend

- Sekcja 5.2 architektury — encja `Asset` (jako kind=asset ObjectType).
- Flysystem abstraction (sekcja 3.2 archi) — adapter MinIO default, Cloudinary w Fazie 1.
- Imagick (sekcja 12.3 archi) — thumbnails generation.
- ADR-009 — Asset jako predefiniowany ObjectType z extra logiką storage.
- Pattern: każdy Asset ma `storage_path`, custom listenery dla `kind=asset` zarządzają fizycznym uploadem.

## 7. Komponenty Refine + shadcn

- `react-dropzone` (już w stacku) — drag-drop globalny + per-product.
- shadcn `Card`, `Dialog`, `Sheet`, `Tabs`, `AspectRatio`.
- Custom `AssetGrid` — masonry grid z lazy loading (intersection observer).
- Custom `AssetLightbox` — zoom/pan dla obrazów, browser PDF viewer dla PDF.
- Custom `AssetUploader` — z progress per plik + retry per failed.

## 8. Open questions

- [ ] Folder structure — czy DAM ma drzewo folderów / kolekcje / tagi (które bardziej intuicyjne)?
- [ ] Bulk tagging — w grid view zaznaczam → Apply tag → modal — czy szybciej?
- [ ] Video preview — generujemy poster frame on-upload (server-side ffmpeg) czy lazy?
- [ ] Compression — czy auto-optymalizujemy zdjęcia (mozjpeg / squoosh) na upload?
- [ ] Mobile upload — czy z telefonu można upload'ować zdjęcia (Kasia w terenie u dostawcy)?

---

*Plik wersjonowany w `Zrodla/UI/`. Status: placeholder — DAM lite w MVP, Cloudinary adapter w Fazie 1, advanced (AI metadata, video) w Fazie 2.*
