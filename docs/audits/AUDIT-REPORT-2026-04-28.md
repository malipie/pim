# PIM Architecture Audit Report
Date: 2026-04-29
Source: AUDIT-CHECKLIST.md

## 0. Wykryty stan projektu

Discovery command result:

PHP files: 107
composer.json: yes
turbo.json: yes

Klasyfikacja wg checklisty:

Stadium 2 — Faza 1 MVP w toku

Zakres audyt
u:
- STR
- DDD
- BC
- DB
- API
- RT

Reguły production‑only oznaczone jako N/A.

---

## 1. Podsumowanie wykonawcze

### Liczba naruszeń

CRITICAL: 2
HIGH: 5
MEDIUM: 4
LOW: 1
INFO: 4
BLOCKED: 1

### Top 5 problemów architektury

1. Domain zależy od Doctrine ORM (DDD‑001, DDD‑006)
2. Struktura src/ zawiera moduły niebędące Bounded Context
3. Brak portów domenowych dla repository
4. Brak generowania typów API z OpenAPI
5. Brak warstwy Contracts w BC

---

## 2. Wyniki szczegółowe

### STR-001
Severity: HIGH
Status: FAIL
Opis: brak katalogu packages/api-types (zastąpiony przez packages/shared-types).

### STR-002
Severity: HIGH
Status: PASS

### STR-003
Severity: CRITICAL
Status: FAIL
Opis: katalogi w src/ niezgodne z BC:
- ApiConfigurator
- Benchmark
- DataFixtures
- Maintenance
- Messaging
- Observability
- Story

### STR-004
Severity: HIGH
Status: FAIL
Opis: brak warstwy Contracts w BC.

### STR-005
Severity: MEDIUM
Status: FAIL
Opis: brak README w Catalog i Identity.

### STR-006
Severity: LOW
Status: INFO
Opis: brak .claude/CLAUDE.md (repo używa CLAUDE.md w root).

### DDD-001
Severity: CRITICAL
Status: FAIL
Opis: Domain zależy od Doctrine ORM.

### DDD-002
Severity: CRITICAL
Status: PASS

### DDD-003
Severity: CRITICAL
Status: PASS

### DDD-004
Severity: HIGH
Status: PASS

### DDD-005
Severity: MEDIUM
Status: PASS

### DDD-006
Severity: HIGH
Status: FAIL
Opis: encje domenowe używają atrybutów ORM.

### DDD-007
Severity: MEDIUM
Status: INFO
Opis: brak XML mapping – nie można zweryfikować UUID strategy.

### DDD-008
Severity: HIGH
Status: FAIL
Opis: brak RepositoryInterface w Domain.

### DDD-009
Severity: HIGH
Status: INFO
Opis: brak katalogów Contracts.

### DDD-010
Severity: CRITICAL
Status: BLOCKED
Powód: brak deptrac.yaml i narzędzia deptrac.

### DDD-011
Severity: LOW
Status: FAIL
Opis: brak Shared\\Domain\\AggregateRoot.

### DB-001
Severity: CRITICAL
Status: PASS

### DB-002
Severity: HIGH
Status: PASS

### DB-003
Severity: HIGH
Status: PASS

### DB-004
Severity: MEDIUM
Status: PASS

### DB-005
Severity: MEDIUM
Status: PASS

### DB-006
Severity: HIGH
Status: N/A

### DB-007
Severity: LOW
Status: PASS

### API-001
Severity: HIGH
Status: PASS

### API-002
Severity: MEDIUM
Status: FAIL
Opis: niektóre ApiResource nie mają Provider/Processor.

### API-003
Severity: MEDIUM
Status: FAIL
Opis: brak snapshot OpenAPI w docs/api.

### API-004
Severity: HIGH
Status: FAIL
Opis: packages/api-types nie generowane z OpenAPI.

### RT-001
Severity: CRITICAL
Status: PASS

### RT-002
Severity: HIGH
Status: INFO
Opis: brak AbstractBatchHandler – brak dowodu na bulk handlers.

---

## 3. Lista naruszeń posortowana po severity

CRITICAL
- STR-003
- DDD-001

HIGH
- STR-001
- STR-004
- DDD-006
- DDD-008
- API-004

MEDIUM
- STR-005
- API-002
- API-003
- DDD-005

LOW
- DDD-011

---

## 5. Caveats

1. Reguła DDD‑010 nie mogła być zweryfikowana z powodu braku deptrac.
2. UUID strategy nie była w pełni sprawdzalna bez XML mapping.
3. Struktura src/ zawiera moduły techniczne, które mogą być świadomym wyborem projektu.
4. Repo używa CLAUDE.md w root zamiast .claude/CLAUDE.md.
