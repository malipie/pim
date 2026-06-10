# ui-v2 — primitives nowego design systemu (epik EXR)

Komponenty nośne nowego look & feel (eksporty = pierwszy moduł, kolejne migrują
w przyszłych epikach). Tailwind na tokenach z EXR-01 (`src/index.css`):
granatowy neutral = skala `zinc`, CTA = skala `orange` / token `--cta`,
błędy = skala `brick`, sukces = `emerald`.

## Komponenty

| Komponent | Plik | Zastosowanie |
|---|---|---|
| `PageHeader` | `page-header.tsx` | breadcrumb topbara + slot akcji (montowany globalnie w EXR-03) |
| `PillTabs` | `pill-tabs.tsx` | taby strony Eksporty (aktywny = granatowy pill z licznikiem) |
| `KpiCard` | `kpi-card.tsx` | pasek KPI (label uppercase, wartość 28px, sub-line, trend) |
| `StatusPill` | `status-pill.tsx` | status sesji: kropka + label; mapowanie z `ExportStatus` w `status-maps.ts` |
| `ResultBar` | `result-bar.tsx` | belka rozkładu OK/WARN/ERR (emerald/orange/brick) |
| `ProgressBar` | `progress-bar.tsx` | animowany postęp async sesji (shimmer) |
| `ModeBadge` | `mode-badge.tsx` | chip trybu operacji (UPDATE/CREATE/...) |
| `FormatPill` | `format-pill.tsx` | chip formatu pliku (XLSX/CSV/...) |
| `SelectableCard(+Group)` | `selectable-card.tsx` | kafelki wyboru (Krok 1 encje, Krok 2 formaty); radiogroup z klawiaturą |
| `WizardStepper` | `wizard-stepper.tsx` | pasek 4 kroków wizarda (done/active/future) |
| `EmptyState` | `empty-state.tsx` | pusty stan sekcji/tabeli z CTA |
| `Sparkline` | `sparkline.tsx` | mini-trend SVG (port 1:1 z designu) |
| `HealthDot` | `health-dot.tsx` | kropka zdrowia integracji (port 1:1 z designu) |

Zasady: props z TSDoc, **labelki tłumaczone przez `t()`** (komponenty przyjmują
przetłumaczone stringi albo same tłumaczą klucze z namespace `ui_v2`), a11y
(role/aria + focus-ring), zero literałów PL/EN w kodzie.

## Tabela v2 — wzorzec klas (nie komponent)

Tabele nowego designu nie mają dedykowanego komponentu — stosuj klasy:

- **nagłówek:** `text-[11px] uppercase tracking-wider text-zinc-400 font-medium`
- **wiersz:** `hover:bg-zinc-50 transition-colors`
- **separator wierszy:** `divide-y divide-zinc-100` (lub `border-b border-zinc-100`)
- **liczby/kody:** `font-mono num` (tabular-nums z utility `.num`)
- **karta-kontener:** `bg-surface border border-zinc-200 rounded-2xl shadow-card overflow-hidden`

Przykład:

```tsx
<div className="overflow-hidden rounded-2xl border border-zinc-200 bg-surface shadow-card">
  <table className="w-full text-[13px]">
    <thead>
      <tr className="text-left">
        <th className="px-4 py-3 text-[11px] font-medium tracking-wider text-zinc-400 uppercase">Plik</th>
        <th className="px-4 py-3 text-[11px] font-medium tracking-wider text-zinc-400 uppercase">Wiersze</th>
      </tr>
    </thead>
    <tbody className="divide-y divide-zinc-100">
      <tr className="transition-colors hover:bg-zinc-50">
        <td className="px-4 py-3">products_2026-06.xlsx</td>
        <td className="num px-4 py-3 font-mono">12 847</td>
      </tr>
    </tbody>
  </table>
</div>
```

## Testy

`pnpm --filter @pim/admin test` — Vitest + Testing Library (jsdom) +
jest-axe; specy w `src/components/ui-v2/__tests__/`.

Poza zakresem (EXR-02): `StagePipeline` (specyficzny dla importów — przy
redesignie importów), Storybook.
