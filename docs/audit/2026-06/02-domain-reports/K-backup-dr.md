# Audyt domeny K — Backup / DR / Operacje

Data: 2026-06-16. Tryb: adwersarski, read-only. Środowisko: stack żywy (`https://pim.localhost`),
DB `pim`, MinIO live. Stanza pgBackRest `pim`.

## Metodyka — co i jak sprawdzono

### Materiał statyczny (kod + konfiguracja)
- `scripts/pim-backup-restore.sh`, `scripts/test-pgbackrest-restore.sh` — orchestrator restore + test DoD (Sprint 0 #15).
- `docker/postgres/*` — `Dockerfile`, `pgbackrest.conf`, `start-pim.sh`, `pim-init-backup.sh`, `pim-cron.sh`, `pim-restore-test.sh`.
- `docker-compose.yml` linie 223–356 (database + pgbackrest, minio, minio-tls, minio-init).
- `apps/api/src/Backup/**` — entity `Backup`, `BackupSnapshotHandler`, `PgBackRestRunner`, kontrolery Trigger/Get.
- Audit: `apps/api/src/Identity/Infrastructure/Audit/AuditLogListener.php`, `AuditLogRequestMapper.php`,
  `AuditLog` entity, `BreakGlassController.php`, `config/packages/dh_auditor.yaml`.
- RODO/offboarding: `PurgeDeletedTenantsCommand.php`, `config/packages/flysystem.yaml`,
  raw `db-fk-ondelete.txt`, `db-tables-tenant.txt`.
- Runbooki: `docs/runbook/restore.md`, `docs/runbook/disaster-recovery.md`.

### Weryfikacja empiryczna (runtime, read-only)
- `pgbackrest --stanza=pim info` w kontenerze `database`.
- Stan crona: `ps`, `crontab -l -u postgres`, `cat /etc/crontabs/postgres`, `ls /var/spool/cron/cronstamps/`,
  `crond --help` (dcron 4.5).
- Logi pgBackRest: `init.log`, brak `cron.log`, spool dir.
- MinIO: `mc ls --recursive` repo backupu + archiwum WAL, `mc version info` bucketów, `mc ls local/`.
- Image build date: `docker image inspect pim-database:local`.
- DB rowcounts: `audit_logs`, `export_logs`, `users_audit`, `roles_audit`, `tenants_audit`, `backups_audit`.
- FK ON DELETE breakdown na `tenants` (z raw + potwierdzenie liczb).

### Czego NIE dało się sprawdzić (luki audytu)
- **Nie wykonano realnego restore** (`pim-backup-restore.sh` jest destrukcyjny — wycina `$PGDATA`).
  Odtwarzalność oceniona z metadanych pgBackRest (`status: ok`, ciągłość WAL, history files), nie z faktycznego replaya 49-dniowego WAL.
- **Środowisko produkcyjne nieznane** — ocena dotyczy lokalnego compose. Czy prod ma osobny S3 dla repo
  vs assetów (separacja awarii) — niesprawdzalne stąd.
- **Symfony Scheduler / zewnętrzny scheduler na prod** — brak w repo; nie wykluczam wpisu cron poza repo na prod.
- **Realny test `pim:tenants:purge-deleted` na niepustym tenancie** — niewykonany (zakaz DELETE). Wniosek z FK + braku pre-cleanup w kodzie = predykcja.
- **Czy `mc mirror`/replikacja MinIO jest skonfigurowana na prod** — brak w repo, lokalnie brak; prod nieznany.

---

## Findings (z dowodami)

### K1 [CRITICAL] Cron backupu martwy od 49 dni — najnowszy backup bazowy z 2026-04-28
`pgbackrest --stanza=pim info` (2026-06-16 19:09 UTC):
```
full backup: 20260428-070020F   timestamp 2026-04-28 07:00:20+00
incr backup: 20260428-070020F_20260428-070810I   timestamp 2026-04-28 07:08:10+00
```
To jedyne backupy bazowe. MinIO `pim-backups/pim/backup/pim/` — najnowszy obiekt `2026-04-28 07:08:13 UTC`.
Stack był uruchamiany 2026-06-13/14/16 (`init.log`), więc to nie kwestia wyłączonego stacku.

Dowody że cron NIGDY nie odpalił backupu:
- Brak pliku `cron.log` (`tail /var/log/pgbackrest/cron.log` → NO cron.log). `pim-cron.sh` zawsze loguje "starting backup".
- `/var/spool/cron/cronstamps/` PUSTY, datowany `Apr 28 06:51` — dcron tworzy cronstamp dla każdego przetworzonego crontaba; brak `cronstamps/postgres` = dcron nigdy nie wykonał crontaba `postgres`.
- crond działa (`10 root crond -b -L /dev/stderr`), crontab istnieje (`/etc/crontabs/postgres`, 74 B).

Skutek: RPO bazowe = 49 dni. Continuous archiving WAL żyje (patrz K2), więc PITR teoretycznie możliwy, ale tylko replaying 49 dni WAL na 49-dniowej bazie.

### K2 [HIGH] Obraz `pim-database:local` zbudowany 7 tygodni temu — runtime crontab to stara 1-liniowa wersja, niezgodna z Dockerfile
`docker image inspect pim-database:local` → `Created: 2026-04-28T13:12:04`.
Runtime crontab (`crontab -l -u postgres`):
```
0 * * * * /usr/local/bin/pim-cron.sh >> /var/log/pgbackrest/cron.log 2>&1
```
Dockerfile (`docker/postgres/Dockerfile` linie 49–53) definiuje TRZY wpisy z argumentami:
```
0 2 * * 0 pim-cron.sh full
0 2 * * 1-6 pim-cron.sh diff
30 3 * * 6 pim-restore-test.sh
```
`pim-cron.sh` wywoływany BEZ argumentu (jak w runtime) ustawia `TYPE=incr` (linia 16) — niezgodne z intencją full/diff. Co istotniejsze: cotygodniowy automated restore-test (`pim-restore-test.sh`, Saturday 03:30) — mechanizm wczesnego wykrycia korupcji backupu — **nie jest w runtime crontabie w ogóle**. Obraz wymaga `docker compose build database`. Drift kod→runtime ukrywa, że "ulepszenia" z `pgbackrest.conf`/Dockerfile nie działają.

### K3 [CRITICAL] MinIO bez backupu i bez wersjonowania — repo backupu bazy leży w TYM SAMYM MinIO co assety (single point of failure)
`mc version info`:
```
local/pim-assets is un-versioned
local/pim-backups is un-versioned
```
Brak `mc mirror`/replikacji/wersjonowania w `docker/`, `scripts/`, `apps/api/config` (grep: 0 trafień).
pgBackRest backupuje WYŁĄCZNIE Postgres; repo trafia do bucketu `pim-backups` w tym samym MinIO instance.
Assety DAM (`pim-assets`, `<tenant-uuid>/<asset-uuid>/...`), eksporty (`pim-exports`), importy (`pim-imports`) nie mają żadnej kopii.
Utrata wolumenu `minio_data` = jednoczesna utrata wszystkich assetów + wszystkich backupów bazy. Dla SaaS to brak odtwarzalności assetów i jednoczesny SPOF dla DR bazy.

### K4 [HIGH] Offboarding tenanta (hard-delete) niewykonalny przy obecnych FK — 24× ON DELETE RESTRICT
`PurgeDeletedTenantsCommand` (linie 138–143) wykonuje wyłącznie `$this->tenants->remove($tenant)` bez pre-cleanup zależności.
PHPDoc twierdzi "CASCADE on FKs takes care of dependent rows", ale FK na `tenants` (raw `db-fk-ondelete.txt`):
```
24 RESTRICT   13 CASCADE   1 SET NULL
```
RESTRICT obejmuje m.in. `objects`, `object_values`, `users`, `assets`, `attributes`, `channels`,
`import_*`, `export_*`, `api_keys`, `object_relations`, `object_types`, `backups`.
Każdy tenant z jakimikolwiek danymi (czyli każdy realny) → `DELETE FROM tenants` rzuci foreign-key-violation. Hard-delete nigdy się nie uda. Brak testu (`rg PurgeDeletedTenants apps/api/tests` → 0 trafień). RODO art. 17 (right to erasure) niespełnione dla bazy.

### K5 [HIGH] Brak kaskady do MinIO przy offboardingu — assety/eksporty/importy tenanta zostają w storage na zawsze
Żaden kod nie kasuje obiektów MinIO przy delete tenanta (grep w `Asset`/`Export`/`Import` po `tenant.*delete`/`bucket`/`deleteObjects` → 0). `PurgeDeletedTenantsCommand` dotyka tylko bazy.
`flysystem.yaml:9-11`: izolacja tenanta po prefiksie ścieżki (`<tenant-uuid>/...`), RLS nie obejmuje storage.
Po offboardingu pliki binarne tenanta (zdjęcia produktów = potencjalnie PII/dane osobowe, eksporty z danymi) pozostają w MinIO bezterminowo. RODO art. 17 niespełnione dla obiektów.

### K6 [MEDIUM] Eksporty z "forever retention" + brak jakiegokolwiek enforcement retencji
`flysystem.yaml:43-44`:
```
# Forever retention for paid tiers (PRD §11.7); Free tier 7d cleanup
# lands with sessions API (EXP-08) + scheduled command.
```
Free-tier 7d cleanup oraz scheduled command jeszcze nie istnieją. Eksporty (`<tenant_id>/<session_id>.<format>`,
mogące zawierać pełne dane produktowe/PII) gromadzą się bezterminowo. Konflikt z zasadą minimalizacji RODO i z runbookiem DR, który sam ostrzega o GDPR (disaster-recovery.md:234 "Retention windows shorter than 30 days trigger the GDPR breach...").

### K7 [MEDIUM] Commandy retencji/offboardingu nie są nigdzie schedulowane
`pim:audit:cleanup` (`Shared/Infrastructure/Maintenance/AuditLogCleanupCommand.php`, retencja 365d per dh_auditor.yaml:13)
oraz `pim:tenants:purge-deleted` są "designed to be invoked from cron", ale:
- Symfony Scheduler nieużywany (`rg AsSchedule|RecurringMessage|ScheduleProviderInterface` → 0).
- Brak wpisu cron w `docker/` / `apps/api/config` (`rg audit:cleanup|purge-deleted` → 0 poza komentarzem dokumentacyjnym).
Retencja audytu i hard-delete tenantów nie egzekwowane automatycznie — wymaga ręcznego uruchomienia operatora. Dla SaaS = audit_logs rośnie bez granic (już 50140 wierszy), a okno soft-delete nigdy się nie zamyka samo.

### K8 [MEDIUM] Audit generyczny nie zapisuje diffu (old/new value zawsze null); dane produktowe poza audytem
`AuditLogListener.php:93-94` — `oldValue: null, newValue: null` dla każdego requestu; listener łapie tylko
metadane HTTP (metoda, route, status→granted/denied/n_a, IP, UA). Brak "co dokładnie zmieniono".
`dh_auditor.yaml:25-33` — `CatalogObject`, `ObjectValue`, `Association` (dane produktowe) świadomie NIE audytowane
("intentionally NOT tracked here yet"). Zmiana wartości atrybutu produktu nie zostawia śladu kto/kiedy/co.
Eksport danych jako proces nie ma dedykowanego audit eventu poza generycznym HTTP logiem (export_logs=0 wierszy, brak `data_export` flagi w audit_logs).
Dla forensyki incydentu SaaS to luka: można stwierdzić "ktoś wszedł na endpoint", ale nie "zmienił X z A na B".

### K9 [LOW] Brak runbooka break-glass mimo odwołania w konstytucji projektu
CLAUDE.md deklaruje `docs/operations/break-glass-runbook.md` jako utrzymywany — plik nie istnieje
(`ls` → No such file). Sam mechanizm break-glass jest solidny i w pełni audytowany (patrz "Co działa"), ale recovery zablokowanego Ownera bez spisanej procedury zwiększa MTTR podczas incydentu.

---

## Co zweryfikowano że DZIAŁA (zasłużona pochwała)
- **Continuous WAL archiving żyje**: najnowszy segment `000000030000007C0000008A` zarchiwizowany 2026-06-16 19:09:34 UTC, kadencja ~60s (`archive_timeout=60`). `pgbackrest info` → `status: ok`. History files `00000002.history`, `00000003.history` obecne — metadane timeline ciągłe. (Niweluje część K1: PITR do "teraz" technicznie możliwy, choć na 49-dniowej bazie.)
- **Break-glass w pełni audytowany** (`BreakGlassController`): każda próba (sukces I porażka) → `audit_logs` z `special_flags=["SUPER_ADMIN_RECOVERY"]`, `cross_tenant_access=true`, reason (min. 10 znaków), target, MFA wymagane, rate-limit 5/24h liczony PRZED weryfikacją TOTP (anti-brute-force).
- **dh_auditor diff-audit dla encji wrażliwych**: `users_audit`=150, `roles_audit`=605, `tenants_audit`=2, `backups_audit`=6, `attributes_audit`=46 — schema RBAC + Tenant + Attribute mają pełny diff (old/new).
- **audit_logs aktywny**: 50140 wierszy, listener `kernel.response` pisze per request (login/role-change/eksport łapane jako metadane HTTP, trwały ślad).
- **Runbooki DR istnieją** (`restore.md`, `disaster-recovery.md`) z deklarowanym RPO ≤5min / RTO ≤15min (cel po #106) i świadomością GDPR breach window.
- **Soft-delete tenanta zaimplementowany** (`status='deleted'` + `deleted_at`, 30d grace, `--dry-run` default-safe) — projekt offboardingu jest, blokuje go tylko FK (K4) + brak storage cascade (K5).
- **State machine backupu poprawny** (`Backup` entity): pending→running→completed/failed z guard `ensureTransitionable`; handler zapisuje failed z komunikatem błędu.
- **Trigger backupu chroniony**: `backup:write` (super_admin only) + rate-limit 1/h/tenant (`TriggerBackupController:66-73`).

## Ocena RPO/RTO (realny stan vs deklaracja)
- Deklaracja (disaster-recovery.md:57-58, prod po #106): RPO ≤5min (WAL), RTO ≤15min.
- Realny stan lokalny: RPO bazy = 49 dni do ostatniego full/diff; PITR przez WAL żywy, ale wymaga replaya 49 dni WAL na starej bazie przez 2 timeline switche → realne RTO godziny + ryzyko niepowodzenia replaya (nieprzetestowane — K2 wyłączył automated restore-test).
