# VIEW-IMP-05 — Wizard refactor

Epik: **UI-11**. Start: 2026-05-12.

## Cel

Re-style wizard `/integrations/imports/new` wg `Import-nowy.html` design — stepper UI na górze, lepsze copy, 2-col mapping layout. **Tylko FE refactor.** BE bez zmian.

## Pliki

- `WizardStepper.tsx` (nowy) — bogatszy stepper niż istniejący `<Stepper>`.
- `ImportWizardPage.tsx` — header + WizardStepper.
- `StepUpload.tsx` / `StepMapping.tsx` / `StepValidation.tsx` / `StepConfirm.tsx` — re-style.
- `locales pl/en` — `imports.wizard.steps.*` descriptions.

## Quality gates

Biome + TS + Vite + Playwright (1 spec/1 login).
