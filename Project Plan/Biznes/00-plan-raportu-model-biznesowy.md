# Plan raportu — wybór modelu biznesowego dla Cortex PIM

> **Status:** plan do akceptacji (research NIE wykonany). Po akceptacji → wykonanie wg sekcji 9.
> **Autor planu:** sesja 2026-06-14. **Operator:** Marcin (solo product owner).
> **Typ dokumentu:** plan deliverable'u — definiuje cel, zakres, metodologię i strukturę raportu, który ma zapaść decyzję modelową.

---

## 0. TL;DR planu (60 sekund)

Raport ma odpowiedzieć na **jedno pytanie decyzyjne**: jaki model biznesowy przyjąć dla Cortex PIM, żeby osiągnąć **300 000 zł przychodu w roku 1** i **10 000 000 zł przychodu w roku 7**, startując z pozycji solo product ownera robiącego produkt po godzinach z agentem AI.

Decyzja sprowadza się do osi:
- **Opcja A — Software/Product:** firma sprzedająca oprogramowanie, fokus na SMB + okazjonalne „złote strzały" enterprise.
- **Opcja B — Usługi:** firma wdrożeniowo-usługowa, zarabiająca na wdrożeniach + maintenance + SLA dla średnich/dużych.
- **Warianty hybrydowe/fazowe** (dopuszczone decyzją operatora): np. start usługowy → ewolucja w produkt, albo product-led z usługami premium.

Raport NIE jest esejem — to **analiza decyzyjna oparta na researchu** (jak robią to inni + matematyka przychodu + ograniczenia startowe), kończąca się **jedną rekomendowaną ścieżką** z uzasadnieniem i warunkami brzegowymi.

**Kluczowe napięcie do rozstrzygnięcia w raporcie:** cel 10 mln zł w roku 7 prawdopodobnie zderza się z ograniczeniami startowymi (solo, po godzinach, rynek PL, brak marki osobistej, kapitał 50–100k dopiero „gdy widać światełko"). Raport musi pokazać, **który model i jaki rynek (PL vs szerzej) w ogóle domyka tę matematykę** — i czy 10 mln jest realne bez zatrudnienia zespołu / porzucenia etatu.

---

## 1. Cel raportu i pytanie decyzyjne

### 1.1. Pytanie główne
> Jaki model biznesowy (A / B / hybryda / ścieżka fazowa) maksymalizuje prawdopodobieństwo osiągnięcia 300k zł (rok 1) i 10 mln zł (rok 7) przychodu ze sprzedaży Cortex PIM, przy danych ograniczeniach startowych?

### 1.2. Pytania pomocnicze (raport musi na nie odpowiedzieć po drodze)
1. Jak realnie zarabiają firmy PIM i software house'y w tej przestrzeni — jakie modele przychodowe, ceny, struktury kosztów?
2. Kto jest konkurencją (globalną i w PL) i jaki model przyjęli — produkt, usługi, hybryda?
3. Jaka jest matematyka każdego modelu: ilu klientów / jaki ACV / jaki churn jest potrzebny, żeby trafić w 300k i 10 mln?
4. Który model jest wykonalny **po godzinach przez solo PO z agentem** — a który wymaga zespołu/etatu/kapitału?
5. Czy cel 10 mln zł jest osiągalny tylko w PL, czy wymusza wyjście na CEE/EU?
6. Jakie ryzyka (w tym konflikt z Ideo, ryzyko produkcyjne pierwszego klienta) różnicują opcje?

### 1.3. Co NIE jest celem tego raportu
- Plan kampanii marketingowej (osobny wątek, świadomie odłożony — raport tylko **zarysuje implikacje** wybranego modelu dla późniejszego marketingu).
- Szczegółowy pricing/packaging produktu (to konsekwencja decyzji, nie jej przedmiot).
- Decyzje architektoniczne/produktowe (są w `Project Plan/` i handoffach).

---

## 2. Punkt startowy — ograniczenia jako twarde wejście do analizy

Każda opcja w raporcie MUSI być przepuszczona przez ten filtr. To nie jest tło — to kryteria odrzucenia.

| # | Ograniczenie | Implikacja dla analizy |
|---|---|---|
| 1 | **Solo PO, bez umiejętności programowania**, produkt budowany z agentem AC. Brak pewności, że nie „wykrzaczy się" na produkcji u pierwszego klienta. | Ryzyko techniczne/operacyjne. Model usługowy z SLA = zobowiązanie do niezawodności, której solo+agent może nie udźwignąć. Model produktowy SMB = mniejsza ekspozycja na pojedynczy SLA. |
| 2 | **Marcin pracuje na etacie w Ideo** (duża firma IT), produkt **może wchodzić w konflikt** z ofertą Ideo. | Traktowane jako **czynnik ryzyka** (nie osobna sekcja). Wpływa na: kanały sprzedaży (nie te same co Ideo?), widoczność, możliwość użycia marki osobistej, tempo. |
| 3 | **Ciepła posada, dobre zarobki** (>2 średnie krajowe), ułożona sytuacja rodzinna i finansowa. **Może wyłożyć 50–100k w perspektywie ~2 lat — ale dopiero „gdy widać światełko w tunelu".** | Kapitał warunkowy, nie z góry. Model musi być **bootstrap-friendly na starcie**, z opcją dokapitalizowania po walidacji. Wyklucza modele wymagające dużego CAPEX/zespołu od dnia 1. |
| 4 | **Projekt po godzinach.** Szansa na dobór freelancerów do wsparcia. | Twardy limit czasu założyciela. Model musi skalować się przez automatyzację/freelancerów, nie przez czas Marcina. Usługi = czasochłonne (delivery), produkt = front-loaded effort. |
| 5 | **Marketing później**, ale: ograniczony budżet, **brak zaangażowania marki osobistej/wizerunku**, mało czasu → potrzeba ultra-kreatywnych, tanich podejść. | Model musi być realny przy **niskim CAC i bez founder-led growth**. To mocno różnicuje A vs B (produkt SMB potrzebuje skalowalnego pozyskania; usługi mogą iść relacyjnie/niszowo). |

**Zasada przewodnia raportu:** rekomendacja musi być wykonalna *w ramach tych ograniczeń*, a nie w świecie idealnym. Każda opcja dostaje ocenę „fit do ograniczeń startowych".

---

## 3. Zakres rynku — do rozstrzygnięcia w raporcie (nie z góry)

Decyzja operatora: **priorytet Polska, ale realnie — z otwartością na „idź szerzej", jeśli to wynika z analizy.**

Dlatego rynek **nie jest założeniem, tylko jednym z wyników raportu.** Raport rozstrzyga to matematyką przychodu:

- **Hipoteza do przetestowania:** czy 10 mln zł/rok w roku 7 jest osiągalne w samej Polsce przy realnym TAM/SAM dla PIM?
- Jeśli **tak** → rekomendacja PL-first, niższe ryzyko, prostszy go-to-market.
- Jeśli **nie** (rynek PL za mały dla 10 mln w danym modelu) → raport pokazuje, **w którym momencie i jak** wejście na CEE/EU staje się konieczne, i co to oznacza dla modelu (język produktu, wsparcie, sprzedaż zdalna).

Research konkurencji prowadzony **dwuwarstwowo**: (1) Polska — partnerzy Akeneo/Pimcore w PL, polskie software house'y e-commerce, polscy dostawcy PIM/feed management; (2) globalni gracze (Akeneo, Pimcore, Salsify, inriver, Plytix, Sales Layer, Productsup) jako benchmark modeli i cen — bez założenia, że tam wchodzimy, ale po to, żeby zrozumieć ekonomię kategorii.

---

## 4. Research — co konkretnie zbadać (rdzeń raportu)

### 4.1. Modele przychodowe w kategorii PIM
- Jak monetyzują liderzy: SaaS subskrypcja (per SKU? per user? per kanał? tiers?), open-core, usługi wdrożeniowe, partner/reseller ecosystem.
- Realne widełki cenowe (entry SMB vs enterprise) — benchmark do matematyki w sekcji 5.
- Udział przychodów z licencji vs usług/wdrożeń u graczy hybrydowych (np. Pimcore: open-source core + enterprise + partnerzy wdrożeniowi).

### 4.2. Konkurencja — mapa
- **Globalni produktowi:** Akeneo, Pimcore, Salsify, inriver, Plytix, Sales Layer, Productsup — model, pozycjonowanie, segment, pricing, kanał.
- **Polska:** kto sprzedaje/wdraża PIM w PL (partnerzy, software house'y), czy są lokalne produkty, jak wyceniają wdrożenia.
- **Sąsiednie kategorie:** feed management, e-commerce integrators, agencje Edito/Magento/Shopify — bo to potencjalni konkurenci ORAZ potencjalni partnerzy/kanał.
- Dla każdego: jednolinijkowa diagnoza modelu (produkt / usługi / hybryda) + dla kogo + jak zarabia.

### 4.3. Wzorce „jak inni to zrobili" — case studies
- Solo/mała załoga budująca SaaS B2B (bootstrap, low-marketing) — jak doszli do 1. i do skali (np. micro-SaaS, indie B2B).
- Software house, który zrobił własny produkt obok usług (jak Pimcore wyszedł z agencji) — i odwrotnie.
- Modele „usługi finansują produkt" (services-funded product) — kiedy działają, kiedy zabijają produkt.
- Antywzorce: dlaczego solo-founderom nie wychodzi enterprise sales / nie wychodzi services scaling po godzinach.

### 4.4. Ekonomia obu modeli (benchmarki branżowe)
- **Produkt SaaS:** typowy ACV w SMB PIM, churn, CAC, czas do break-even, ile klientów = jaki MRR.
- **Usługi:** typowa wartość wdrożenia PIM, marża na wdrożeniu, wartość kontraktu maintenance/SLA, ile FTE potrzeba na obsługę N klientów.

---

## 5. Matematyka przychodu — model finansowy (serce decyzji)

Raport buduje **bottom-up model dla każdej opcji** i sprawdza, która domyka 300k (rok 1) i 10 mln (rok 7). To jest miejsce, gdzie opcje wygrywają lub odpadają.

### 5.1. Co policzyć dla każdej opcji
- **Rok 1 → 300k zł:** ilu klientów × jaki ACV/projekt to daje? Czy to realne w 12 mc solo+po godzinach?
- **Rok 7 → 10 mln zł:** jaka liczba klientów / wielkość zespołu / rynek jest potrzebna? Co musi być prawdą po drodze (lata 2–6)?
- **Ścieżka przyrostu** (rok 1→7): krzywa, nie skok. Realny CAGR, retention, upsell.
- **Próg zespołu:** w którym roku model wymaga 1., 2., N-tego człowieka (freelancer/etat)? Kiedy zmusza Marcina do rzucenia etatu?
- **Próg kapitału:** kiedy potrzebne jest 50–100k i na co.

### 5.2. Scenariusze do policzenia (szkielet)
| Opcja | Rok 1 (300k) | Rok 7 (10M) | Główne założenie do walidacji |
|---|---|---|---|
| A — Produkt SMB | np. ~15–30 klientów × ~10–20k ACV | np. ~400–800 klientów lub mix + enterprise | Czy da się pozyskać tylu klientów bez founder-led marketingu, na rynku PL? |
| A' — Produkt + „złote strzały" enterprise | kilku SMB + 1 enterprise deal | mniej klientów, wyższy ACV | Czy solo+agent udźwignie enterprise SLA i sprzedaż? |
| B — Usługi/wdrożenia + SLA | 2–4 wdrożenia + maintenance | agencja N osób, portfel SLA | Czy 10M usługowo = budowa agencji (konflikt z „po godzinach" i z Ideo)? |
| H — Hybryda fazowa (usługi→produkt lub product+services) | usługi finansują produkt | produkt skaluje, usługi jako premium | Czy da się nie wpaść w „services trap" (usługi zżerają czas na produkt)? |

> Liczby w tabeli są **placeholderami** — raport wypełni je benchmarkami z sekcji 4 i jawnymi założeniami. Każda komórka = jawna kalkulacja, nie „mniej więcej".

### 5.3. Test wykonalności (reality check)
Dla każdej domykającej się matematycznie opcji — nałożenie ograniczeń z sekcji 2:
- Czy mieści się w czasie „po godzinach"? Do którego roku?
- Czy wymaga rzucenia etatu? Kiedy? (to moment „światełka w tunelu" dla kapitału)
- Czy wchodzi w konflikt z Ideo bardziej/mniej?

---

## 6. Framework decyzyjny — jak raport wybierze

Raport nie kończy się „to zależy". Stosuje **jawną punktację** opcji wg ważonych kryteriów:

| Kryterium | Waga (do ustalenia) | Co ocenia |
|---|---|---|
| Domknięcie matematyki (300k / 10M) | wysoka | Czy w ogóle trafia w cele przychodowe |
| Fit do ograniczeń startowych (sekcja 2) | wysoka | Solo, po godzinach, kapitał warunkowy, brak marki osobistej |
| Ryzyko techniczne (produkcja, SLA) | średnia/wysoka | Ekspozycja na „wykrzaczy się u 1. klienta" |
| Konflikt z Ideo | średnia | Im mniejszy, tym lepiej (czynnik ryzyka) |
| Odwracalność / opcjonalność | średnia | Czy zła decyzja jest kosztowna do skorygowania |
| Czas do pierwszego przychodu | średnia | Jak szybko 1. faktura (walidacja + morale + kapitał) |
| Skalowalność bez czasu założyciela | wysoka | Czy rośnie przez automaty/freelancerów, nie przez godziny Marcina |

Wynik: **ranking opcji + jedna rekomendacja + warunki brzegowe** („idź A, ale jeśli X to przełącz na H").

---

## 7. Ryzyka i czynniki różnicujące (w tym Ideo)

Sekcja ryzyk obejmuje (jako czynniki, nie osobne rozdziały):
- **Ideo / konflikt interesów:** nakładanie się oferty, IP, umowa o pracę / ewentualny zakaz konkurencji, ryzyko reputacyjne, ograniczenie kanałów i widoczności. Jak każda opcja zwiększa/zmniejsza tę ekspozycję.
- **Ryzyko produkcyjne pierwszego klienta:** solo+agent, brak pewności stabilności. Mocniej uderza w model usługowy/SLA.
- **Ryzyko koncentracji:** mało klientów o wysokim ACV (usługi/enterprise) = wrażliwość na utratę jednego.
- **Ryzyko „services trap":** usługi zżerają czas, produkt nigdy nie dojrzewa.
- **Ryzyko rynku PL za małego** dla 10 mln → przymus ekspansji, na którą może brakować zasobów.
- **Ryzyko wypalenia/czasu** — po godzinach + etat + rodzina.
- **Ryzyko marketingowe** — brak marki osobistej + niski budżet vs konieczność pozyskania klientów (mocniejsze w modelu produktowym SMB).

---

## 8. Struktura docelowego raportu (spis treści deliverable'u)

Raport powstanie wg tego układu (po akceptacji planu):

1. **Streszczenie wykonawcze** — pytanie, rekomendacja, warunki brzegowe (1 strona).
2. **Punkt startowy i ograniczenia** — z czego startujemy (sekcja 2 tego planu).
3. **Krajobraz rynku i konkurencji** — mapa graczy PL + global, modele, ceny (research 4.1–4.2).
4. **Jak robią to inni** — wzorce i case studies, wnioski przenośne (research 4.3).
5. **Ekonomia kategorii** — benchmarki ACV/churn/CAC/wartość wdrożeń (research 4.4).
6. **Model finansowy** — bottom-up dla A / A' / B / H, test 300k i 10M (sekcja 5).
7. **Rynek: PL czy szerzej** — rozstrzygnięcie na bazie modelu (sekcja 3).
8. **Analiza opcji wg frameworku** — punktacja, mocne/słabe strony każdej (sekcja 6).
9. **Ryzyka** — w tym Ideo jako czynnik (sekcja 7).
10. **Rekomendacja** — jedna ścieżka, uzasadnienie, kamienie milowe rok 1→7, warunki przełączenia.
11. **Implikacje dla późniejszego marketingu** — krótki zarys (nie plan), bo zależy od modelu.
12. **Załączniki** — arkusz kalkulacyjny modelu finansowego, źródła.

**Format deliverable'u (do potwierdzenia przy wykonaniu):** główny raport jako `.docx` lub `.md` + osobny `.xlsx` z modelem finansowym (żeby Marcin mógł podmieniać założenia). Domyślnie proponuję **`.md` w repo** (spójność z `Project Plan/`) **+ `.xlsx` model**.

---

## 9. Plan wykonania raportu (kolejność prac — po akceptacji)

| Etap | Działanie | Wynik |
|---|---|---|
| E1 | Research rynkowo-konkurencyjny (sekcja 4.1–4.2): web search liderów + PL, zebranie cen i modeli | Notatki + tabela konkurencji |
| E2 | Research wzorców i case studies (4.3) + benchmarki ekonomii (4.4) | Wnioski przenośne + liczby do modelu |
| E3 | Budowa modelu finansowego (sekcja 5) w `.xlsx` — bottom-up A/A'/B/H, test 300k i 10M | Arkusz + wniosek o domknięciu celów |
| E4 | Rozstrzygnięcie rynku PL vs szerzej (sekcja 3) na bazie modelu | Sekcja 7 raportu |
| E5 | Punktacja opcji wg frameworku (sekcja 6) + ryzyka (sekcja 7) | Sekcje 8–9 |
| E6 | Synteza: rekomendacja + roadmapa rok 1→7 + warunki przełączenia | Sekcja 10 |
| E7 | Złożenie raportu (sekcja 8) + **weryfikacja**: sprawdzenie liczb, źródeł, kontrargumentów (opcjonalnie subagent/„council") | Finalny `.md` + `.xlsx` |

**Weryfikacja (obowiązkowa, krok E7):** fact-check liczb benchmarkowych, sanity-check matematyki modelu, świadome przejście przez kontrargumenty dla rekomendowanej opcji. Rozważyć przepuszczenie rekomendacji przez `llm-council` (stress-test decyzji) przed finalizacją.

---

## 10. Założenia i wejścia potrzebne od Marcina (do uzupełnienia przed/wczas E3)

Żeby model finansowy był realny, raport będzie potrzebował (lub przyjmie jawne założenia, jeśli brak odpowiedzi):
1. **Definicja „przychodu"** — przychód firmy czy zysk dla Marcina? 10 mln to top-line czy near-profit?
2. **Horyzont czasu Marcina** — do kiedy realnie „po godzinach", a od kiedy gotów rozważyć pełne zaangażowanie/etat-exit.
3. **Tolerancja na zespół** — czy docelowo OK budować agencję/zespół (kluczowe dla opcji B i dla 10M), czy preferencja „mały zespół, wysoka automatyzacja".
4. **Próg „światełka w tunelu"** — jaki konkretny sygnał (np. X klientów / Y MRR) odblokowuje kapitał 50–100k.
5. **Granice konfliktu z Ideo** — czy są formalne ograniczenia (umowa, zakaz konkurencji), które trzeba uznać za twarde.

> Te wejścia nie blokują startu researchu (E1–E2), ale są potrzebne najpóźniej na E3 (model finansowy). Można je zebrać krótkim follow-upem.

---

## 11. Świadome decyzje przyjęte w tym planie
- **Hybrydy/fazy dopuszczone** (decyzja operatora) — framework ocenia A, A', B oraz H, nie wymusza binarności.
- **Rynek nie jest założeniem** — PL-priorytet, ale „idź szerzej" rozstrzyga matematyka, nie przeczucie.
- **Ideo = czynnik ryzyka**, nie osobna sekcja prawno-biznesowa (decyzja operatora).
- **Marketing tylko zarysowany** — pełny plan kampanii to osobny, późniejszy deliverable.
- **Cel decyzyjny nadrzędny:** raport ma *zapaść decyzję*, nie tylko opisać krajobraz.
