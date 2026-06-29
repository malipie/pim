# Audyt półmetkowy PIM — prompty dla agenta (Etap 1: raport, Etap 2: naprawy)

> Utworzone 2026-06-09. Cel operatora: produkcyjny SaaS — zero wycieków danych, zero wykrzaczeń, zero zapaści wydajności.
> Etap 1 wklej jako jeden prompt do świeżej sesji agenta. Etap 2 dopiero PO odebraniu i przejrzeniu raportu.

---
---

## ETAP 1 — PROMPT (audyt read-only, raport dowodowy)

```
Jesteś principal security engineerem i staff architektem wykonującym KRYTYCZNY audyt połówkowy
projektu PIM przed wypuszczeniem go jako SaaS. Twoja postawa: adwersarz, nie kolega. Zakładasz,
że kod zawiera błędy, dopóki nie udowodnisz inaczej. Zero kurtuazji, zero „wygląda dobrze" —
każda opinia musi mieć dowód (plik:linia, output komendy, response HTTP). Chwal wyłącznie to,
co zweryfikowałeś empirycznie.

Przeczytaj najpierw: CLAUDE.md, agent/current_status.md, agent/lessons.md,
Project Plan/01-architektura-pim.md (ADR-y), Project Plan/PRD/PRD-PIM-rbac.md,
docs/api/jsonb-schemas.md.

== TRYB PRACY (NIENEGOCJOWALNE) ==
- AUDYT JEST READ-ONLY: nie zmieniasz żadnego pliku aplikacji, nie naprawiasz znalezisk, nie
  zamykasz issues, nie dotykasz migracji. Jedyne zapisy: nowy katalog docs/audit/2026-06/
  (raporty) + ewentualne skrypty pomocnicze w docs/audit/2026-06/probes/ (jawnie oznaczone).
- Wolno (i należy) uruchamiać: stack lokalny (pnpm stack:up), seedy/fixtures, testy, narzędzia
  statyczne, benchmarki, curl/HTTP probes na https://pim.localhost. NIE uruchamiaj niczego
  przeciwko środowiskom innym niż lokalny stack.
- Pracuj subagentami per domena (A–M poniżej), żeby utrzymać czysty kontekst; każdy subagent
  zwraca findings w ustalonym formacie, Ty agregujesz i deduplikujesz.
- Jeśli stack/test/tool nie działa — to też jest finding (kategoria „auditability"), nie powód
  do pominięcia domeny.
- Sekrety znalezione w kodzie/historii git: do raportu trafia LOKALIZACJA i typ, nigdy wartość.

== SKALA OCEN ==
- CRITICAL — cross-tenant data leak, auth bypass, SQLi/RCE, trwała utrata danych, sekret w repo.
  Pojedynczy CRITICAL = projekt nie nadaje się do wypuszczenia.
- HIGH — eskalacja uprawnień w obrębie tenanta, IDOR, brak limitów umożliwiający DoS, OOM/crash
  workera na realnym wolumenie, brak backupu/odtwarzalności.
- MEDIUM — błędy poprawności danych, brakujące walidacje, wydajność degradująca UX na 50k SKU,
  luki w testach krytycznych ścieżek.
- LOW — dług techniczny, spójność, higiena.
Każdy finding dodatkowo: confidence (confirmed = odtworzyłem dowodem / probable / needs-review).

== DOMENY AUDYTU ==

A. IZOLACJA MULTI-TENANT (najwyższy priorytet — to jest produkt SaaS)
   1. Inwentarz wszystkich tabel domenowych: czy każda ma tenant_id NOT NULL? (zapytanie do
      information_schema na żywej bazie — dowód w raporcie).
   2. TenantFilter: które encje są objęte, które wyłączone i dlaczego; każdy `disable` filtra
      w kodzie = pozycja do przeglądu.
   3. RAW SQL BYPASS: znajdź KAŻDE użycie surowego SQL/QueryBuilder z ręcznym WHERE
      (FilterDslResolver::toCountSql, zapytania ltree, attributes_indexed GIN, raporty KPI,
      eksporty/importy, Meilisearch sync) i zweryfikuj, że tenant_id jest tam wymuszany.
   4. Postgres RLS (ticket #654): czy policies istnieją i są ENABLED/FORCED na żywej bazie?
      Czy connection user nie jest właścicielem tabel (RLS bypass)? Dowód: \d+ / pg_policies.
   5. FRANKENPHP WORKER STATE LEAK: przeszukaj kod pod static properties, memoizację w
      serwisach singleton, request-scoped dane (tenant context, security token, locale)
      trzymane w polach serwisów — wszystko co może przeciec MIĘDZY requestami w worker mode.
      Zweryfikuj reset tenant contextu per request. To klasyczna droga do cross-tenant leaku.
   6. Zasoby poza Postgresem: Meilisearch (czy indeksy/filtry są tenant-scoped?), Redis (klucze
      z prefixem tenanta? cache współdzielony?), MinIO (czy presigned URL nie pozwala odgadnąć/
      enumerować plików innego tenanta? polityka bucketów?), Mercure (czy topiki są autoryzowane
      per tenant/user, czy ktokolwiek może subskrybować cudze progress eventy?).
   7. PRÓBA EMPIRYCZNA (obowiązkowa): na żywym stacku utwórz 2 tenanty + po 1 userze, wykonaj
      macierz curl: każdy kluczowy endpoint (objects, attributes, exports, imports, users,
      media, channels) jako tenant A próbujący czytać/pisać zasoby tenanta B (po ID, po liście,
      po wyszukiwarce, po download URL). Oczekiwane: 403/404/0 wyników. Każdy wyciek = CRITICAL.

B. AUTORYZACJA / RBAC
   1. Pełen inwentarz route'ów (debug:router) vs pokrycie #[RequiresPermission] / IsGranted /
      security.yaml. Tabela: endpoint → wymagana permission → realnie egzekwowana? Luka
      „Phase 6 retrofit" (~60 endpointów pre-RBAC) — wylistuj KAŻDY niezabezpieczony endpoint
      z oceną co realnie wystawia.
   2. PUBLIC_ACCESS w security.yaml: każdy publiczny endpoint uzasadniony? (magic link, reset
      hasła, accept invite — sprawdź tokeny: entropia, TTL, jednorazowość, rate limit).
   3. IDOR: endpointy przyjmujące ID — czy weryfikują ownership/tenant poza samym filtrem ORM?
   4. Eskalacja w obrębie tenanta: czy edytor może nadać sobie rolę admina (endpoint ról/
      userów), zmienić cudze API tokeny, obejść field-level permissions przez PATCH surowych
      pól lub przez eksport (eksport potrafi ominąć attribute-level permissions? sprawdź!).
   5. JWT/API tokens: algorytm, TTL, refresh, revocation, storage po stronie SPA (localStorage
      vs cookie httponly), logout invalidation, token w URL-ach (presigned/SSE)?
   6. MFA/SSO: state/nonce/PKCE w OAuth, walidacja podpisu SAML, możliwość ominięcia MFA.
   7. Super Admin / break-glass: czy bypass jest logowany, czy panel operatora wystawia coś
      bez auth.

C. INJECTION I WALIDACJA WEJŚCIA
   1. SQLi: wszystkie surowe zapytania (szczególnie dynamiczne ORDER BY/kolumny z FilterDSL,
      ltree path building, JSONB path queries) — parametryzacja czy konkatenacja?
   2. Import plików: parsing CSV/XLSX — formula injection przy re-eksporcie, zip-bomby, limity
      rozmiaru/wierszy, typ MIME vs treść, ścieżki plików (path traversal w MinIO keys).
   3. Eksport: CSV/Excel formula injection (`=cmd`, `+`, `-`, `@` na początku komórki) —
      czy wartości są escapowane?
   4. XSS: grep po dangerouslySetInnerHTML / v-html / ręcznym budowaniu HTML; rich-text
      atrybuty (jeśli są) — sanityzacja serwerowa czy tylko kliencka?
   5. SSRF: wszystkie miejsca gdzie backend pobiera URL podany przez usera (importy z URL,
      webhooks, health checks integracji).
   6. Deserializacja/unserialize, eval, dynamiczne nazwy klas z inputu.
   7. Walidacja JSONB envelope (docs/api/jsonb-schemas.md): czy writer wymusza kontrakt, czy
      można zapisać dowolny śmieć do object_values.value i wykrzaczyć readery/eksporty?

D. SEKRETY I KONFIGURACJA
   1. Skan repo + PEŁNEJ historii git (gitleaks lub trufflehog — zainstaluj w sandboxie jeśli
      brak) pod klucze/hasła/tokeny. Każde trafienie = CRITICAL (rotacja!).
   2. .env/.env.local w .gitignore? Domyślne hasła (admin@demo/changeme, postgres, minio,
      mercure JWT key) — czy ścieżka produkcyjna wymusza ich zmianę? Jak wygląda provisioning
      sekretów na prod (Symfony secrets vault użyty realnie?).
   3. Caddy/FrankenPhp config: nagłówki bezpieczeństwa (CSP, HSTS, X-Frame-Options,
      X-Content-Type-Options, Referrer-Policy), brak CORS zgodnie z architekturą (jeśli gdzieś
      jest Access-Control-Allow-Origin — finding), TLS settings, limity rozmiaru request body.
   4. Debug/dev surfaces: profiler, /api/docs, debug toolbar, Mailpit, Adminer — co z tego
      byłoby wystawione na prod przy obecnej konfiguracji?
   5. Logi: czy logujemy PII/sekrety/tokeny (grep po logger calls z wrażliwymi polami)?
      doctrine.dbal.logging w prod?

E. FRANKENPHP / PAMIĘĆ / STABILNOŚĆ PROCESÓW
   1. Każdy Messenger handler: AbstractBatchHandler lub flush+clear? (custom PHPStan rule ma
      to łapać — zweryfikuj że rule działa i nie ma supressów).
   2. Długie procesy (import 50k, eksport 100k, Meilisearch reindex, attributes-indexed
      rebuild): przejrzyj pod akumulację pamięci, brak iterate(), rosnące tablice, event
      listeners zbierające stan.
   3. URUCHOM benchmarki (ExportBenchmarkCommand, BulkImportBenchmarkCommand lub seed 50k+)
      z pomiarem peak memory — raport liczbowy. Limit z architektury: 256MB/worker.
   4. Crash-safety: co się dzieje z sesją importu/eksportu gdy worker padnie w połowie? Retry/
      dead-letter skonfigurowane? Idempotencja handlerów (podwójne przetworzenie wiadomości)?
   5. Graceful degradation: Meilisearch down, Redis down, MinIO down, Mercure down — które
      ścieżki UI/API się wykrzaczają twardo (500) zamiast degradować?

F. WYDAJNOŚĆ I SKALA (cel: 50k SKU / 200+ atrybutów / 3 locale / 5 kanałów, sufit 200k)
   1. Zasiej katalog w skali (użyj istniejących seederów/benchmarków; jeśli brak — wygeneruj
      ~50k obiektów z wartościami EAV) i zmierz: lista produktów (zimna/ciepła), wyszukiwanie
      z 3 warunkami FilterDSL, otwarcie karty produktu z 150+ atrybutami, completeness,
      eksport 50k, import 10k. Liczby do raportu (p50/p95).
   2. EXPLAIN ANALYZE krytycznych zapytań: lista z filtrem JSONB (GIN używany?), drzewo
      kategorii (GiST/ltree), object_values per obiekt, KPI eksportów. Brakujące indeksy
      = finding z konkretnym CREATE INDEX.
   3. N+1: włącz SQL logging na ścieżkach listy/karty/eksportu — policz zapytania per request.
   4. Paginacja: czy listy >1000 mają cursor-based (reguła architektury)? OFFSET na dużych
      tabelach?
   5. Frontend: rozmiar bundla (pnpm build --report jeśli jest), lazy loading route'ów,
      re-rendery dużych tabel (universal list przy 100+ kolumnach), brak wirtualizacji tam
      gdzie potrzebna.
   6. Redis/cache: co jest cachowane, jakie TTL, stampede protection przy zimnym starcie?

G. INTEGRALNOŚĆ DANYCH
   1. Migracje: każda ma działający down()? Destrukcyjne migracje (DROP) z backfillem?
      Spróbuj migrate → rollback → migrate na świeżej bazie.
   2. FK + ON DELETE: osierocone rekordy (object_values po usunięciu atrybutu/obiektu,
      placements po usunięciu kanału)? Sprawdź realne constrainty na żywej bazie.
   3. Transakcyjność: operacje wielokrokowe (import wiersza, zmiana schematu + reindex,
      placement + mapping) — atomowe czy częściowo zapisywalne? Event dispatch przed czy po
      commit (race z async handlerami)?
   4. Współbieżność: dwóch userów edytuje ten sam produkt — last-write-wins? Optimistic
      locking? Unique constrainty (kody, SKU) egzekwowane w DB czy tylko w walidatorze?
   5. attributes_indexed denormalizacja: co gwarantuje spójność z object_values? Drift możliwy?

H. API CONTRACT I BŁĘDY
   1. RFC 7807 wszędzie? Czy błędy 500 wyciekają stack trace / SQL / ścieżki?
   2. Spójność OpenAPI ze stanem faktycznym (regen i diff ze snapshotem).
   3. Rate limiting: istnieje JAKIKOLWIEK? (login brute-force, API tokens, preflight count,
      magic link). Brak limitu na auth = HIGH.
   4. Limity payloadów: max rozmiar PATCH z wartościami, max liczba kolumn eksportu, max
      warunków filtra — co się dzieje przy 10MB JSON?

I. FRONTEND SECURITY & JAKOŚĆ
   1. Storage tokenów, wylogowanie, obsługa 401/403 (czy UI nie „udaje" uprawnień które
      backend i tak odrzuci — i odwrotnie: czy COKOLWIEK jest egzekwowane tylko w UI?).
   2. pnpm audit + przegląd dependencji o znanych CVE; wersje React/Vite/Refine aktualne?
   3. Stan błędów: error boundaries, obsługa odrzuconych promise (unhandledrejection).
   4. i18n leaks: surowe klucze/literały w UI (próbka, nie pełen audyt — pełny jest w DoD).

J. TESTY I CI (auditability)
   1. Realne pokrycie krytycznych ścieżek: izolacja tenantów, permissions, import/eksport,
      auth — wylistuj scenariusze których NIE MA w testach (gap analysis), nie tylko %.
   2. Czy CI gates faktycznie failują (sprawdź konfigurację: continue-on-error, allow-failure,
      pomijane joby)? Mutacyjnie: czy PHPStan/Deptrac/Biome obejmują cały kod czy mają
      wykluczenia maskujące problemy?
   3. Flaky tests / fixme / skip — inwentarz.

K. BACKUP / DR / OPERACJE
   1. Backup: pgBackRest planowany w 0.11 — co istnieje DZIŚ? Czy da się odtworzyć bazę +
      MinIO + konfigurację z zera? (RTO/RPO = brak → HIGH dla SaaS).
   2. Audit log: które operacje wrażliwe (zmiany ról, eksporty danych, logowania, break-glass)
      zostawiają trwały ślad?
   3. RODO-minimum dla SaaS B2B: usunięcie danych tenanta (offboarding), eksport danych
      tenanta, retencja logów/eksportów (forever retention z PRD eksportów vs RODO — oceń).

L. ARCHITEKTURA I DŁUG
   1. Deptrac: realne naruszenia bounded contexts (uruchom, raportuj).
   2. Martwy kod: deprecated families/products tables, object_associations, stare komponenty —
      inwentarz do skasowania.
   3. Zgodność z ADR-ami (009/013/014): miejsca gdzie kod łamie przyjęte decyzje.
   4. Spójność wzorców: ile równoległych sposobów robienia tego samego (np. fetch w FE,
      kontrolery custom vs API Platform) — ocena ryzyka dryfu.

M. DEVELOPER EXPERIENCE / PRZEKAZYWALNOŚĆ REPO (priorytet operatora — równy bezpieczeństwu)
   Pytanie audytowe: „dostaję to repo jako senior PHP/React i mam je rozwijać — czy chcę,
   czy uciekam?". Kod pisany w dużej mierze przez LLM — szukaj typowych LLM-smells.
   1. ONBOARDING EMPIRYCZNY (obowiązkowy): osobny subagent z CZYSTYM kontekstem (bez wiedzy
      z tej sesji, tylko repo) wykonuje: (a) setup od zera wyłącznie wg README/docs — zmierz
      kroki, czas, każdą rzecz której musiał się domyślić; (b) mały feature end-to-end
      (np. nowe pole na encji Channel: migracja → API → UI → test) — raport friction points:
      czego nie dało się znaleźć, co zaskoczyło, gdzie dokumentacja kłamie. To jest test
      prawdy o repo.
   2. Struktura projektu: czy układ monorepo/bundli/features jest przewidywalny (podobne
      rzeczy w podobnych miejscach)? Czy nazwa pliku/klasy pozwala zgadnąć zawartość?
      Miejsca-śmietniki (utils, helpers, misc, components/ bez podziału)?
   3. Metryki jakości (uruchom narzędzia, liczby do raportu): rozmiary klas/metod/komponentów
      (top 20 największych), cognitive complexity (phpmetrics / phpstan + eslint-complexity),
      duplikacja kodu (phpcpd + jscpd — % i najgorsze klastry), głębokość zagnieżdżeń,
      pliki >500 linii w FE (kandydaci na rozbicie).
   4. LLM-code smells: nadmiarowe komentarze opisujące oczywistości, martwe abstrakcje
      (interfejs z jedną implementacją bez powodu), kopiuj-wklej z drobnymi mutacjami zamiast
      ekstrakcji, niespójne idiomy między plikami z różnych sesji (np. 3 style obsługi błędów),
      „defensive clutter" (try/catch połykające wyjątki), nieużywane propsy/parametry.
   5. Nazewnictwo i język: konsekwencja EN w kodzie (per CLAUDE.md), spójność słownika
      domenowego z glossariuszem (ObjectType vs Family — czy stare nazwy straszą w kodzie?).
   6. Dokumentacja dla developera: README (czy działa?), docs/ — co istnieje vs co jest
      potrzebne nowemu dev (architektura wysokopoziomowa, jak dodać endpoint, jak dodać typ
      atrybutu, konwencje, jak uruchomić testy); PHPDoc/TSDoc na publicznych API serwisów.
   7. Testy jako dokumentacja: czy z testów da się zrozumieć zachowanie? Testy-atrapy
      (asercje nic nie sprawdzające)?
   8. Git/PR hygiene: czytelność historii, wielkość PR-ów, czy commit messages opisują „why".
   9. Tooling DX: czas zimnego startu stacka, czas testów, hot-reload, czy pre-commit/CI
      dają szybki feedback; rzeczy które frustrują w codziennej pętli pracy.
   10. Werdykt domeny: ocena 1-10 „developer adoption risk" + lista 10 rzeczy, które
       naprawić, żeby senior nie uciekł w pierwszym tygodniu.

== NARZĘDZIA (zainstaluj w sandboxie czego brakuje) ==
composer audit, pnpm audit, PHPStan (max, bez nowych baseline'ów!), Deptrac, semgrep
(reguły: p/php, p/security-audit, p/react, p/sql-injection), gitleaks LUB trufflehog (z historią),
psql do inspekcji RLS/indeksów, curl/httpie do probes, istniejące komendy benchmark,
phpmetrics + phpcpd (jakość/duplikacja PHP), jscpd (duplikacja TS/TSX), cloc (rozmiary).
Każde narzędzie: pełen output zapisz do docs/audit/2026-06/raw/.

== DELIVERABLES (wszystko po polsku, commit na branch audit/2026-06-midpoint + PR, bez merge) ==
1. docs/audit/2026-06/00-executive-summary.md — max 2 strony: DWA werdykty — (a) GO/NO-GO
   dla ścieżki do SaaS (bezpieczeństwo/stabilność/wydajność), (b) „developer adoption risk"
   1-10 (czy można oddać repo programistom bez wstydu) — plus top 10 ryzyk, liczby (findings
   per severity), co MUSI być naprawione przed jakimkolwiek pilotem z realnymi danymi.
2. docs/audit/2026-06/01-findings.md — rejestr WSZYSTKICH findings:
   AUD-NNN | tytuł | severity | confidence | domena | lokalizacja (plik:linia / endpoint) |
   dowód (output/response) | scenariusz ataku lub awarii | rekomendacja naprawy | estymata.
   Sortowanie: severity, potem domena. ŻADNEGO findingu bez dowodu lub ścieżki weryfikacji.
3. docs/audit/2026-06/02-domain-reports/ — raport per domena A–M (metodyka: co sprawdzono,
   jak, czego nie udało się sprawdzić i dlaczego — luki audytu też raportuj).
4. docs/audit/2026-06/03-fix-plan.md — plan napraw falami:
   Wave 0 (przed jakimkolwiek demo z realnymi danymi), Wave 1 (przed pilotem),
   Wave 2 (przed publicznym SaaS), Wave 3 (higiena). Per finding: proponowany ticket
   (tytuł + zakres 2-3 zdania) — gotowe do założenia issues w Etapie 2.
5. docs/audit/2026-06/raw/ — surowe outputy narzędzi i probes (curl transcripts).
6. Wpis w agent/current_status.md (audyt wykonany, link, liczby) — bez zmian w lessons.md
   (lessons uzupełnimy po naprawach).

== KRYTERIA JAKOŚCI TWOJEJ PRACY ==
- Findings odtwarzalne: ktokolwiek powtórzy Twój dowód i dostanie ten sam wynik.
- Brak teatru: jeśli czegoś nie zdążyłeś/nie mogłeś sprawdzić — sekcja „NIEZBADANE" w
  executive summary, a nie cisza.
- Priorytetyzuj głębię nad szerokością w kolejności: A → B → C → M → E → F → D → G → reszta.
  (M jest priorytetem operatora na równi z bezpieczeństwem — repo ma być przekazywalne
  prawdziwym programistom bez wstydu.)
  Lepiej w pełni dowiedziona izolacja tenantów niż powierzchowne „sprawdziłem wszystko".
- Liczby zamiast przymiotników: „wolne" = p95 4.2s na 50k SKU, nie „wydaje się wolne".
```

---
---

## ETAP 2 — PROMPT (naprawy; uruchom PO przejrzeniu raportu i akceptacji planu fal)

```
Przeczytaj docs/audit/2026-06/ (executive summary, rejestr findings, fix-plan). Operator
zaakceptował plan fal [TU EWENTUALNE KOREKTY OPERATORA].

Zadanie:
1. Załóż GitHub Issues z 03-fix-plan.md: label epik-AUD (utwórz), jeden issue per finding lub
   per spójna paczka findings (max 3 powiązane w jednym), tytuł `AUD-NNN: <tytuł>`, body:
   pełny finding (severity, dowód, scenariusz, rekomendacja) + link do raportu. Milestone'y:
   audit-wave-0/1/2/3. Tabela ID→#issue na koniec.
2. Pracuj przez CAŁĄ Wave 0, potem Wave 1 (EPIK MARATHON RULE: każdy ticket = osobny branch +
   PR + CI + merge; Wave 2/3 dopiero po potwierdzeniu operatora).
3. Reguła dla każdej naprawy bezpieczeństwa: NAJPIERW test który odtwarza podatność (failing),
   POTEM fix, test zielony zostaje jako regresja. Dla wydajności: benchmark przed/po w PR body.
4. SMOKE TEST RULE + CLOSED MEANS CLOSED obowiązują: close ticketu = dowód na żywym stacku, że
   exploit/problem z findingu już nie działa (powtórz oryginalny probe z raportu — przed: leak,
   po: 403/404 — oba transcripty w close comment).
5. Po każdej fali: aktualizacja 01-findings.md (status fixed/wontfix z uzasadnieniem),
   current_status.md, lessons.md (wzorce błędów które do tego doprowadziły).
6. Niczego nie wyciszaj: zakaz baseline'ów PHPStan, zakaz skip testów, zakaz obniżania reguł.
```

---

## Notatki operacyjne dla Marcina

- Etap 1 odpal w świeżej sesji (czysty kontekst), najlepiej na czas audytu nic nie mergować.
- Raport przejrzyj osobiście ZANIM puścisz Etap 2 — zwłaszcza fix-plan (agent mógł źle wycenić
  wagę czegoś biznesowo); skoryguj plan w miejscu [TU EWENTUALNE KOREKTY OPERATORA].
- Spodziewaj się findings w: retrofit RBAC (znana luka Phase 6), rate limiting (prawdopodobnie
  brak), backup (planowany dopiero w 0.11), forever-retention eksportów vs RODO.
- Audyt warto powtórzyć skrócony (same probes A+B) przed pierwszym pilotem z realnymi danymi.
```
