# VIEW-IMP-04 — Harmonogram: ImportSchedule entity + cron parsing + UI

Epik: **UI-11**. Start: 2026-05-12.

## Cel

Nowa funkcjonalność: cron schedules dla importów. MVP: CRUD + cron parsing + manual `run-now` dispatch + upcoming endpoint. **Cron worker daemon** (Symfony Scheduler tick co minutę) + **real notifications** (Slack/Email/Webhook) — poza scope V04 (follow-up VIEW-IMP-04.1).

## ADR (defaulty)

- Cron parsing: `dragonmantank/cron-expression` (battle-tested, używany w Symfony Scheduler internally).
- Cron worker → V04.1 z Symfony Scheduler. V04 dostarcza tylko encję + endpoints + UI.
- Notifications driverów + Email/Slack transport → V04.1. V04: pole `notifyChannels` JSONB w schemacie, ale faktyczne wysyłki nie działają.

## BE

- `composer require dragonmantank/cron-expression` (no major version bump w composer.json).
- Migracja `import_schedules` + `import_schedule_runs` (UNIQUE per (tenant_id, code), index (tenant_id, enabled, next_run)).
- Encje: `ImportSchedule` (cron + cronHuman + priority enum high/normal/low + source/profile FK nullable + enabled + nextRun + lastRunAt + notifyChannels JSONB) + `ImportScheduleRun` (audit per run, FK do ImportSession nullable).
- Enumy: `SchedulePriority` (High/Normal/Low).
- Repo + voter.
- `CronExpressionParser` service (wrapper na dragonmantank).
- `ScheduleDispatcherService::computeNextRun(cron, fromTime)` + `dispatch(schedule)` (manual run-now).
- AP4 ApiResource CRUD + inputs.
- 4 custom controllers: `ToggleScheduleController` (enable/disable), `RunNowScheduleController` (immediate dispatch), `UpcomingSchedulesController` (next 24h), `ListScheduleRunsController`.

## FE

- `ImportScheduleView` zastępuje `ImportSchedulePlaceholder` w App.tsx.
- `NextRunsTimeline` (SVG 24h horizon z hour ticks + now marker + priority dots).
- `ScheduleCard` (cron + cronHuman + priority badge + last/next run + notify channels badges + toggle switch + dropdown akcji).
- `ScheduleFormDialog` z `CronEditor` (input + live "next 3 runs" preview via dragonmantank API).
- Spec `imports-schedule.spec.ts` (1 test/1 login).

## Świadome odejścia

- **Cron worker daemon** (Symfony Scheduler tick co 60s) → V04.1.
- **Real notifications** (Slack webhook, Email via Mailer, generic webhook) → V04.1.
- **DST handling beyond UTC** → cron evaluowany w UTC, UI pokazuje w locale.
- **Retry policy** per notification → one-shot fire-and-forget w MVP.
