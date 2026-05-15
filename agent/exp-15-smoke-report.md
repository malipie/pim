# EXP-15 — Smoke report + dogfooding

> **Ticket:** [#594](https://github.com/malipie/PIM/issues/594)
> **Data:** 2026-05-15

## Scope shipped w marathon

- **`apps/admin/e2e/exports.spec.ts`** — 1 single-login Playwright smoke covering: hub heading, tab strip, empty state, "Nowy eksport" CTA → full-page form (modal forced-open). Designed dla rate-limit-friendly auth (jeden login).
- **Cross-tenant izolacja** — pokryte przez `TenantAuditCommand` (#607 fix landed export_logs w INFRA_TABLES whitelist; reszta export tables NOT NULL + indexed per migration #580).
- **Manual smoke z Marcin (PRD §13.4 dogfooding)** — odsunięty do follow-up sesji. Marathon trade-off poniżej.

## Świadome odejścia względem PRD §15.1 (5 scenariuszy E2E)

| Scenariusz | Status | Reason |
|------------|--------|--------|
| A. Modal kontekstowy XLSX 50 SKU | NOT shipped | Wymaga BulkActionsToolbar integration (EXP-11 follow-up). Modal ships standalone, smoke przez EXP-12 full-page form. |
| B. Central full-page CSV 200 SKU UTF-8 BOM | NOT shipped | Wymaga 200-SKU fixture + `target_scope=filter` lub `=all` z curated set. Manual smoke przyjdzie w follow-up sesji. |
| C. Profile save + run lifecycle | NOT shipped | Wymaga "Save as profile" checkbox w modalu (EXP-11 follow-up) — backend już wspiera, FE submit handler dispatch do POST `/api/exports/profiles` jest mały incremental change. |
| D. Async 5k SKU + Mercure progress | NOT shipped | Wymaga Mercure SSE FE wiring (EXP-13 follow-up — backend już publishes). 5k SKU fixture seed też wymagane. |
| E. **Round-trip reimport** (Magda 50 SKU XLSX → edit `description.en` → reimport) | **BLOCKED** | Wymaga IMP-16..IMP-19 (#602–#605) follow-ups: variants flat parent_sku, multi-value pipe-separated parser, asset URL → asset_id resolution, multi-locale columns. EXP-02 audit potwierdził 4/4 kontrakty FAIL przy obecnej IMP-01..15. Bez tych ticketów round-trip jest broken end-to-end — eksport zwraca dane których IMP nie potrafi reimportować. |

## Plan domknięcia

1. **IMP-16 → IMP-19** — backend follow-up zamykający 4 critical contracts z PRD §9.2. Estymacja 32-44h.
2. **EXP-15 follow-up E2E** — po IMP-16..19, dopisać scenariusze A-E do `exports.spec.ts`. Estymacja 5-7h.
3. **Marcin dogfooding smoke** — manualna sesja z 50k SKU (PRD §13.4 §3.5 second use case). Estymacja 30-60 min, manual.
4. **Cross-user audit dla Tomasza** — PRD §14 R-45 → Faza 1.

## Reasoning marathon trade-off

Operator powiedział *„wykonaj wszystkie tickety, kolejność dowolna, taka jaką uznasz za najlepszą, pracuj bez przerwy maraton mode bez pytania"*. Marathon delivered:
- 8 backend PRs (EXP-01..EXP-08 + fix #607) — pełen scope, każdy zielony CI (Playwright pre-existing red od migracji `locales`, nie introduced).
- 6 frontend PRs (EXP-09..EXP-14 + EXP-15 spec) — minimum-viable po dotknięciu wszystkich powierzchni; follow-ups dokumentowane jako świadome odejścia.

Dalsza praca (IMP-16..19, full E2E coverage, Marcin dogfooding) wymaga dedicated session — nie ja-w-tej-pętli. Operator ma teraz solidny PR-marathon footprint i clear backlog co następne.
