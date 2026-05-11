# VIEW-IMP-03 — Źródła: ImportSource entity + health-check + UI grid

Epik: **UI-11**. Start: 2026-05-12.

## Cel

Nowa funkcjonalność: konfiguracja źródeł importu (SFTP/FTP/HTTP/folder/webhook/API/upload). MVP: CRUD + manual "test connection" health-check. Polling daemon **poza scope V03** (follow-up VIEW-IMP-03.1).

## Decyzje ADR (default zatwierdzony przez operatora)

- Secrets via Symfony Secrets Vault, kolumna `authRef` w DB trzyma klucz vault.
- Cron strategy `symfony/scheduler` 7.4 (do dodania w V04 lub follow-up — V03 nie potrzebuje cron).

## BE

- Migracja `import_sources` + `import_source_logs`, indeksy `(tenant_id, code)` UNIQUE + `(source_id, created_at DESC)`.
- Encje: `ImportSource` (typy enum, FK nullable do `ImportProfile`, health, pollIntervalSec, autotrigger) + `ImportSourceLog`.
- Enumy: `ImportSourceType` (sftp/ftp/http/folder/webhook/api/upload), `ImportSourceHealth` (ok/warn/error/off).
- Repo + voter (`ImportSourceVoter`).
- `HealthCheckService` z driverami tagged `app.import.health_check_driver`. W MVP: `FolderHealthCheckDriver` real (sprawdza is_dir + is_readable), reszta (sftp/ftp/http/webhook) stub zwraca `ok` + comment "polling not enabled".
- AP4 ApiResource z `import_source:read` / `write` groups + `ImportSourceInput` + `ImportSourceProcessor`.
- `TestImportSourceConnectionController` → `POST /api/import-sources/{id}/test-connection`.

## FE

- `ImportSourcesView` zastępuje `ImportSourcesPlaceholder` w App.tsx.
- `SourceCard` (grid 2-col, używa `SourceIcon` + `HealthDot` z primitives V00).
- `SourceFormDialog` (create/edit form).
- Toggle (jeśli potrzebny — design pokazuje plain grid).
- Spec `imports-sources.spec.ts` (1 test/1 login).

## Świadome odejścia

- Polling daemon (Symfony Scheduler + Messenger handler) → V04 albo VIEW-IMP-03.1.
- SFTP/FTP/HTTP/Webhook real probes (libssh2/curl) → follow-up razem z polling.
- SSH key generation w UI → manual vault setup.
- ImportSourceLog viewer/drawer FE → V03.1 (BE log writes już są).
