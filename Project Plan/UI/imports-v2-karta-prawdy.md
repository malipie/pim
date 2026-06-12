# Imports v2 — karta prawdy UI (IMP2-1.1 / #1463)

> Co w UI importów jest REALNE na danym etapie przebudowy (ADR-0019). Aktualizować przy merge ticketów IMP2.
> Stan po NUI-10 (#1452) / NUI-11 (#1453): wizard 6 kroków i widok sesji istnieją NA STARYM silniku.

| Element UI | Stan (2026-06-12) | Realne od |
|---|---|---|
| Wizard 6 kroków (Źródło→…→Start) | renderuje się; backend = stary silnik | — |
| Tryby CREATE/UPDATE/UPSERT w kroku Reguły | **dekoracja** — silnik robi wyłącznie create, duplikat SKU blokuje wiersz | #1465 |
| Kubełek „Aktualizacje" w Podglądzie | **kłamie** (silnik nie umie update) | #1465 + #1492 |
| Dry-run pełnego pliku | sync, cały plik w jednym requeście | #1492 (dwupoziomowy) |
| Pauza / wznów / anuluj | brak w UI (endpointy BE nieobsługiwane przez worker) | #1479 |
| Rollback | działa dla CREATE; **nie cofa update'ów**; ghost-docs w Meili | #1480 |
| Zdjęcia (URL/ZIP) | brak pobierania (pola martwe) | #1475 / #1476 |
| Mapping: duplikaty/puste nagłówki | gubione (mapping po nazwie) | #1489 (po indeksie) |
| Kolumny `code.channel` | **korupcja**: suffix zapisywany jako locale | #1469 |
| Warianty / relacje / multi-kategorie | parent_sku skip; relacje bez ObjectRelation; 1 kategoria | #1470 / #1471 |
| Profile: zapis z wizarda / prefill | niedziałające (stan lokalny) | #1491 |
| Harmonogramy run-now / źródła SFTP | stemple bez sesji / stub „ok" | #1495 / #1496 |
| Pobierz raport CSV / eksport profilu | ✅ działa (fetch+Bearer, #1500) | done |
| Live progress (Mercure) | ✅ działa; topic z configu po #1502 | done |
