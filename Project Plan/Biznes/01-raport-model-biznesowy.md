# Raport decyzyjny — model biznesowy dla Cortex PIM

> **Cel:** rozstrzygnąć, jaki model biznesowy przyjąć, by osiągnąć **300 000 zł przychodu w roku 1** i **10 000 000 zł w roku 7**.
> **Data:** 2026-06-14. **Metoda:** research multiagentowy (konkurencja, wzorce bootstrap, unit economics, rynek PL) + model finansowy bottom-up (`Model-finansowy-PIM.xlsx`).
> **Załącznik:** `Model-finansowy-PIM.xlsx` — przeliczalny model 7-letni dla trzech scenariuszy.

---

## 1. Streszczenie wykonawcze

**Rekomendacja: model hybrydowy fazowy (H) — produkt SaaS jako docelowy charakter firmy, usługi wdrożeniowe jako most cashowy i maszyna walidacyjna w latach 1–3, z twardym capem na usługi i marką bezosobową.**

Research i model finansowy zgodnie pokazują trzy rzeczy:

1. **Cel 300k zł w roku 1 jest osiągalny w każdym z modeli** — różni je tylko ryzyko i to, czy budują aktywo na przyszłość.
2. **Cel 10 mln zł w roku 7 NIE domyka się ani czystym produktem (bez budżetu marketingowego), ani czystymi usługami (bez dużego zespołu)** — domyka się jedynie hybrydą, i to pod warunkiem ekspansji poza Polskę od ~roku 3–4.
3. **Profil założyciela (solo, po godzinach, etat w Ideo, brak marki osobistej, kapitał warunkowy) eliminuje dwie skrajności:** czysty produkt SMB jest zbyt wolny przy zerowym marketingu, a czysty model usługowy nie skaluje do 10 mln bez 25–30 osób i wprost koliduje z pracodawcą.

Model finansowy (liczby z załącznika):

| Model | Przychód rok 1 | Przychód rok 7 | Realizacja celu R7 | FTE rok 7 |
|---|---|---|---|---|
| A — Produkt SaaS | 300 000 zł | ~9,3 mln zł | 93% | ~3–5 |
| B — Usługi + SLA | ~425 000 zł | ~4,8 mln zł | 48% | ~16 |
| **H — Hybryda fazowa** | **~575 000 zł** | **~10,9 mln zł** | **109%** | **~8–12** |

Hybryda jest jedyną ścieżką, która jednocześnie: daje natychmiastowy cash (rok 1 ponad cel), buduje skalowalne aktywo recurring (~93% przychodu roku 7 to subskrypcje + maintenance), i mieści się w realnym zespole 8–12 osób zamiast 25–30.

**Najważniejszy warunek powodzenia, wynikający z napięcia w researchu:** usługi muszą być prowadzone tak, by **nie** zamienić się w agencję (services trap) **i** by **nie** eksponować Marcina na konflikt z Ideo. Rozwiązanie: usługi jako **produktyzowany, płatny onboarding/migracja własnego PIM** (dostarczany przez freelancerów, nie przez twarz Marcina), a nie founder-led consulting w kanale agencyjnym, który pokrywa się z ofertą Ideo.

---

## 2. Punkt startowy i przyjęte założenia

Raport respektuje pięć ograniczeń startowych jako twarde kryteria (nie tło):

1. Solo product owner, kod pisze agent AI, brak pewności stabilności produkcyjnej u pierwszego klienta.
2. Etat w Ideo (software house e-commerce/PIM) — produkt może kolidować z ofertą pracodawcy.
3. Ciepła posada, dobre zarobki; kapitał **50–100k zł dostępny dopiero „gdy widać światełko w tunelu"**.
4. Projekt po godzinach; możliwy dobór freelancerów.
5. Marketing później; ograniczony budżet, **bez marki osobistej**, potrzeba tanich, kreatywnych podejść.

Ponieważ raport jest wykonywany od razu, przyjęto jawnie pięć założeń (do potwierdzenia — sekcja 12):

- **„Przychód" = top-line firmy** (nie zysk Marcina). Cele 300k/10 mln traktowane jako obrót.
- **Budowa małego zespołu jest dopuszczalna**, ale minimalizowana — preferencja „mały zespół + automatyzacja + freelancerzy".
- **„Światełko w tunelu" = pierwsze ~10 płacących/recurringowych klientów lub ~30k zł MRR** — to próg odblokowujący kapitał 50–100k.
- **Etat zostaje** dopóki przychód z produktu+usług nie zbliży się do pensji (wzorzec sprawdzony niżej).
- **Granice konfliktu z Ideo** wymagają weryfikacji własnej umowy o pracę/zakazu konkurencji przez prawnika — raport traktuje to jako ryzyko, nie poradę prawną.

---

## 3. Krajobraz rynku i konkurencji

Rynek PIM ma dwa wyraźne, skrajne wzorce monetyzacji, między którymi trzeba się ustawić.

**Wzorzec enterprise / open-core + usługi (Akeneo, Pimcore, Salsify, inriver, Productsup).** Ceny liczone per liczba produktów/SKU + użytkownicy + kanały + locale. Akeneo Growth od ~$45k/rok, Enterprise realnie $80–150k+/rok; Salsify w największych wdrożeniach $30–50k/miesiąc; inriver $25–90k+/rok. Pimcore to open-source core (darmowy do €5 mln przychodu klienta) + licencja €8,4k/€25,2k rocznie, ale **realny przychód ekosystemu to godziny wdrożeniowe partnerów, nie SaaS**. Ten wzorzec wymaga działu sprzedaży i sieci integratorów — **niedostępny dla solo-foundera**.

**Wzorzec SMB self-serve (Plytix, Sales Layer, Catsy, Ergonode).** Transparentny cennik, niska bariera. Plytix: free do 1000 SKU, Pro od $499/mc, liczone per SKU + AI credits (nie per user). Sales Layer od ~$1000/mc. Catsy od $599/mc. **Ergonode** — gracz polskiego pochodzenia, „open-SaaS", free + €299/€699/€1999/mc — to najbliższy lokalny konkurent produktowy, już dokapitalizowany (5 mln zł od twórcy eobuwie) i ekspandujący na US/EMEA. **Ten wzorzec jest replikowalny dla małego zespołu** — niskie CAC, brak sprzedaży enterprise, przychód rośnie z liczbą klientów.

**Polska.** Brak silnego rodzimego produktu poza Ergonode. Rynek PL to przede wszystkim **wdrożenia obcych silników** (Akeneo, Pimcore) przez software house'y — Univio/Unity Group, Spyrosoft, LemonMind, Fast White Cat, e-point oraz **Ideo**. Wdrożenia wyceniane jako projekty od kilkudziesięciu do kilkuset tys. zł. BaseLinker zajmuje sąsiednią niszę multichannel/feed (~30 tys. firm e-commerce) i częściowo „zjada" potrzebę zarządzania danymi u polskiego SMB.

**Luka rynkowa dla Cortex:** nie „lepszy Akeneo" (przegrana wojna na enterprise), lecz **SMB self-serve z agentic-first jako różnicownikiem**. Żaden z graczy SMB nie ma realnie wbudowanego, modyfikowalnego przez LLM schematu jako core UX, a gracze enterprise nie skopiują tego szybko (blokuje ich governance). Model do skopiowania to **Plytix: per-SKU + AI credits, free tier jako lejek, brak commitów** — bo minimalizuje CAC, którego solo nie sfinansuje przez handlowców.

---

## 4. Jak robią to inni — wzorce i case studies

**Bootstrap B2B SaaS wygrywa dystrybucją przez treść i kanał, nie reklamą.** Bannerbear (solo, dev-tool API) doszedł do ~$400k/mc na SEO + obsesyjnej dokumentacji API — istotny precedens dla pozycjonowania „API-first / agentic-first" jako kanału marketingowego zastępującego budżet i markę osobistą. Ale: potrzebował ~12 miesięcy pełnego zaangażowania do pierwszych $10k MRR. Przy zerowym marketingu i etacie krzywa jest jeszcze płaska na starcie — to argument przeciw czystemu produktowi na start.

**Services-funded product działa, gdy usługi karmią ten sam rdzeń.** Pimcore wyrósł jako wewnętrzny stack agencji i skomercjalizował się dopiero po walidacji na własnych wdrożeniach. Mailchimp był side-projectem agencji web — pełne przejście na produkt nastąpiło, gdy zaczął zarabiać więcej niż agencja. 37signals/Basecamp: konsulting → produkt z własnej potrzeby → wyjście z usług. **Wspólny mianownik: usługi finansują i walidują, ale są trampoliną, nie celem.**

**„Services trap" — kiedy zabija.** Gdy łatwy cash z customu odciąga od produktu, a biznes „wygląda jak SaaS, ale skaluje się jak konsulting" — liniowo z liczbą ludzi. Dodatkowa pułapka: założyciel dostarcza usługę „na czuja", klienci oczekują personalnego dotyku, którego nie da się oddelegować.

**Antywzorce dla tego profilu:** (a) enterprise sales solo praktycznie nie istnieje (cykle wielomiesięczne, dziesiątki demo) — dyskwalifikujące przy pracy po godzinach; (b) usługi „po godzinach" załamują się po 2–3 wdrożeniach, gdy kalendarz pęka; (c) product-led bez budżetu bywa za wolny.

**Kiedy rzucić etat:** sygnał ilościowy, nie emocjonalny — 6–12 miesięcy runway + przychód zbliżony do pensji. Wzorzec zwycięzców (Mailchimp/Basecamp): full-time dopiero, gdy side-project zarabia więcej niż dotychczasowe źródło.

---

## 5. Ekonomia kategorii — benchmarki do modelu

**SaaS B2B SMB:** churn 20–43%/rok (base ~30%), mid-market lepiej (~2%/mc); zdrowe LTV:CAC 3–5:1; CAC payback <12–18 mc; ACV mid-market $6–40k/rok (24–160k zł). Pozycjonowanie „alternatywa Akeneo/Pimcore dla SMB" to realnie **ACV 23–77k zł/rok** (poniżej Akeneo Growth, powyżej Plytix free). Krytyczne: w 2025+ CAC rośnie ~14% r/r, NRR się kompresuje — pozyskanie jest dziś droższe.

**Usługi/wdrożenia:** wartość wdrożenia PIM — mały 18–95k zł, średni 130–260k zł, duży 260k–1 mln+ zł. Stawki software house PL: 1,1–2,5k zł/dzień (40–60% taniej niż Europa Zachodnia). Marża brutto professional services 30–60% (base 35%; produktyzowane +10–15 pp). Maintenance/SLA 15–25% wartości wdrożenia rocznie (base 18%).

**Produktywność:** solo realnie ogarnia 3–6 małych lub 1–2 średnie wdrożenia/rok; zespół 2–3 os. — 6–12 małych lub 3–5 średnich. W SaaS 1 CS FTE obsługuje 50–150 kont SMB; solo founder utrzyma operacyjnie 20–50 self-serve klientów zanim support stanie się wąskim gardłem.

**Wniosek unit-economics:** czysty SaaS ma lepszą marżę teoretyczną (80%+), ale dla solo bez budżetu CAC jest pułapką — churn 30% oznacza, że baza przecieka szybciej, niż da się ją zbudować organicznie. Usługi dają natychmiastowy zdrowy cash (marża 35%, leady z sieci/SEO, zero CAC), ale rosną liniowo z zespołem. **Najzdrowszy miks na 10 mln zł: ~60% recurring (SaaS + maintenance) + ~40% wdrożenia, zespół 8–12 FTE** — mniejszy niż czyste usługi i mniej wrażliwy na CAC niż czysty SaaS, bo wdrożenia same generują pipeline kont SaaS.

---

## 6. Model finansowy — test celów (300k / 10 mln)

Pełne, przeliczalne liczby w `Model-finansowy-PIM.xlsx` (założenia: ACV bazowe 30k zł, churn SaaS 30% / hybryda 25%, wartość wdrożenia 120k zł, marża 35%, maintenance 18%).

**Scenariusz A — Produkt SaaS.** Rok 1: 300k zł = ~10 klientów × 30k ACV (osiągalne organicznie). Rok 7: ~9,3 mln zł, ale wymaga **~310 aktywnych klientów**, co przy churn 30% oznacza **~545 pozyskanych klientów łącznie** i ~120 nowych rocznie w stanie ustalonym. Bez budżetu marketingowego to główne ryzyko wykonalności.

**Scenariusz B — Usługi + SLA.** Rok 1: ~425k zł = 2–3 wdrożenia (realne dla solo + freelancer). Rok 7: tylko **~4,8 mln zł przy już 16 FTE** — pełne 10 mln tą ścieżką wymaga ~50+ wdrożeń/rok i 25–30+ FTE. To sprzeczne z pracą po godzinach, kapitałem warunkowym i maksymalizuje konflikt z Ideo.

**Scenariusz H — Hybryda fazowa.** Rok 1: ~575k zł (usługi dają cash ponad cel 300k). Rok 7: **~10,9 mln zł, ~93% recurring**, przy ~315 aktywnych klientach SaaS i zaledwie ~4 FTE delivery usług (łącznie ~8–12 FTE z supportem i freelancerami). Usługi karmią lejek kont SaaS i poprawiają retencję (klienci z wdrożenia bardziej „sticky" → churn 25% zamiast 30%).

**Wspólny wniosek:** baza ~310–315 klientów SaaS jest potrzebna w A i H — i tu wchodzi pytanie o rynek.

---

## 7. Rynek — Polska czy szerzej

Lejek dla Polski (estymacja na bazie ~75 tys. e-sklepów, ~30 tys. firm w BaseLinker, segmentu producentów/dystrybutorów B2B):

- **TAM PL** (firmy, dla których PIM ma sens — ≥1–2 tys. SKU lub kilka kanałów): ~8–15 tys. podmiotów, ~150–250 mln zł.
- **SAM PL** (SMB/mid dopasowany do oferty, bez enterprise idącego do Akeneo/Pimcore): ~4–6 tys. firm, ~60–120 mln zł.
- **SOM 3–5 lat** dla nowego gracza bez marki: ~1–3% SAM → **~1,5–4 mln zł ARR z samej Polski.**

**Rozstrzygnięcie:** 300k zł (rok 1) i przychód rzędu 1,5–4 mln zł (lata 1–4) są osiągalne **z samej Polski**. Ale **10 mln zł w roku 7 to ~5–8% SAM PL** — przy aktywnym, dokapitalizowanym Ergonode i enterprisach idących do Akeneo/Pimcore jest to nierealne bez (a) wejścia na CEE/EU od ~roku 3–4, lub (b) rozszerzenia o sąsiednie wartości (feed management, AI content, syndykacja) podnoszące ACV i TAM. Sam fakt, że Ergonode (PL-born) pivotował na US/UK/EMEA, jest dowodem, że pułap PL dla pure-play PIM jest niewystarczający na ambitny cel.

Dobra wiadomość: produkt API-first/headless jest geo-agnostyczny — ekspansja EU to marketing i integracje lokalne, nie przepisywanie systemu. **PL = rynek-walidator i cashflow lat 1–3; CEE/EU = warunek konieczny celu 10 mln.**

---

## 8. Analiza opcji wg frameworku decyzyjnego

Ocena 1–5 (5 = najlepiej) wg kryteriów z planu.

| Kryterium | A — Produkt | B — Usługi | H — Hybryda |
|---|---|---|---|
| Domknięcie matematyki (300k / 10M) | 3 (10M ryzykowne bez marketingu) | 1 (10M = 25–30 FTE) | 5 |
| Fit do ograniczeń (solo, po godzinach, kapitał) | 3 | 2 | 4 |
| Ryzyko techniczne (produkcja, SLA) | 4 (mała ekspozycja/klient) | 2 (SLA = zobowiązanie) | 3 |
| Konflikt z Ideo | 5 (anonimowy brand) | 1 (twarz + kanał agencyjny) | 3 (jeśli usługi produktyzowane/anonimowe) |
| Odwracalność / opcjonalność | 4 | 3 | 4 |
| Czas do pierwszego przychodu | 2 (wolny start) | 5 (natychmiastowy cash) | 5 |
| Skalowalność bez czasu założyciela | 5 | 1 | 4 |
| **Suma** | **26** | **15** | **28** |

Hybryda wygrywa, bo łączy szybki cash i walidację usług (mocna strona B) ze skalowalnym aktywem produktu (mocna strona A), neutralizując ich główne wady — pod warunkiem dyscypliny capa na usługi i bezosobowej marki.

---

## 9. Ryzyka i czynniki różnicujące

**Konflikt z Ideo (kluczowy czynnik).** Ideo aktywnie doradza i wdraża PIM (Pimcore/Akeneo) — własny produkt to bezpośrednia styczność. Najskuteczniejszy klasyczny kanał B2B (partnerstwa agencyjne + founder-led content o PIM) to **dokładnie kanał pracodawcy**. Stąd: marka bezosobowa, brak twarzy Marcina, świadome odpuszczenie kanału agencyjnego dopóki trwa etat. To realny koszt (wyklucza najtańszy founder-led growth), ale obniżalny przez product-led + programmatic SEO + integracje. **Treść umowy o pracę/zakazu konkurencji wymaga weryfikacji prawnej.**

**Services trap.** Usługi mogą zżreć czas i zatrzymać produkt. Mitygacja: twardy cap godzin na usługi, każde wdrożenie musi rodzić ficzer rdzenia, custom kod poza roadmapą = „nie", delivery przez freelancerów.

**Ryzyko techniczne produkcji** (solo + agent). Mocniej uderza w model usługowy/SLA — kolejny argument, by usługi były produktyzowanym onboardingiem własnego, kontrolowanego produktu, nie dowolnym customem.

**CAC i churn w SaaS** — bez maszyny marketingowej baza przecieka; ekspansja CEE/EU podnosi koszt i złożoność.

**Ryzyko czasu/wypalenia** — etat + rodzina + projekt po godzinach. Cap na usługi i automatyzacja są tu zabezpieczeniem, nie tylko strategią.

**Ryzyko rynku PL** — za mały na 10 mln; przymus ekspansji, na którą zasoby mogą nie wystarczyć w zakładanym tempie.

---

## 10. Rekomendacja i roadmapa rok 1→7

**Decyzja: Hybryda fazowa (H), z produktem SaaS SMB self-serve jako docelowym charakterem firmy i usługami jako produktyzowanym, bezosobowym mostem cashowym.**

**Faza 1 (rok 1, etat trwa) — walidacja i cash.**
2–3 płatne wdrożenia własnego Cortex PIM (delivery: Marcin orkiestruje + freelancerzy), każde karmi rdzeń produktu i staje się kontem referencyjnym SaaS. Równolegle: free tier + pierwsze konta self-serve. Cel: ~300–575k zł, ~10 płacących/recurringowych klientów = „światełko w tunelu" odblokowujące kapitał 50–100k. Marka firmowa, bez twarzy.

**Faza 2 (lata 2–3) — przesunięcie mixu ku recurring.**
Pricing per-SKU + AI credits (wzorzec Plytix). Programmatic SEO + integracje (BaseLinker/Allegro/Shopify) jako główny kanał. Usługi utrzymane na stałym, niskim poziomie (5–6 wdrożeń/rok) wyłącznie jako lejek kont SaaS. Decyzja o rzuceniu etatu, gdy przychód zbliży się do pensji + 6–12 mc runway.

**Faza 3 (lata 4–7) — skala i ekspansja.**
Wejście CEE/EU (warunek konieczny 10 mln). Recurring (SaaS + maintenance) dominuje (~93% w roku 7). Zespół 8–12 FTE (support, product, część delivery), nie agencja 25–30 os.

**Warunki przełączenia (tripwires modelu biznesowego):**
- Jeśli po roku 1 self-serve nie rusza, a usługi idą łatwo → **nie** rozbudowywać usług w nieskończoność (to droga do B i konfliktu z Ideo); zamiast tego rewizja produktu/pozycjonowania.
- Jeśli CAC SaaS okaże się niemożliwy do utrzymania organicznie → rozważyć węższą wertykalę (np. „PIM dla [branża]") zamiast szerokiego SMB.
- Jeśli konflikt z Ideo eskaluje → przyspieszyć decyzję o pełnym oddzieleniu (rzucenie etatu) lub o jeszcze silniejszej anonimizacji marki.

---

## 11. Implikacje dla późniejszego marketingu (zarys, nie plan)

Wybór modelu H + ograniczenie braku marki osobistej wskazują kierunek tanich, kreatywnych kanałów do rozwinięcia w osobnym dokumencie:

- **Programmatic SEO** — szablon + dataset generuje setki landingów („PIM dla [branża]", „integracja PIM × [platforma]", „migracja z Akeneo").
- **Integracja-as-channel** — obecność w ekosystemach BaseLinker/Allegro/Shopify jako featured app = darmowy top-of-funnel tam, gdzie ICP już są.
- **Free micro-narzędzia** brandowane firmowo (generator opisów AI, walidator feedów, kalkulator kompletności katalogu) jako lejek.
- **API-first / dokumentacja jako marketing** (wzorzec Bannerbear) — zastępuje markę osobistą jakością techniczną.
- **Open-source komponent / community** pod brandem produktu (wzorzec Ergonode) — buduje zaufanie bez twarzy.

Wszystkie działają anonimowo, pod marką firmową — co jednocześnie obsługuje ograniczenie marki osobistej i minimalizuje ekspozycję na Ideo.

---

## 12. Wejścia do potwierdzenia (uściślają model)

Model używa założeń z sekcji 2. Potwierdzenie poniższych pozwoli go dostroić:
1. „Przychód 10 mln" = top-line czy near-profit? (zmienia wymaganą skalę).
2. Akceptacja budowy zespołu 8–12 osób w latach 4–7? (warunek H i 10 mln).
3. Konkretny próg „światełka w tunelu" odblokowujący kapitał 50–100k.
4. Realny horyzont „po godzinach" vs. gotowość do etat-exit.
5. Twarde granice umowy z Ideo (weryfikacja prawna).

---

## Źródła

Research przeprowadzony przez agentów (czerwiec 2026). Kluczowe źródła:

- Konkurencja/pricing: [GetApp Akeneo](https://www.getapp.com/operations-management-software/a/akeneo-pim/pricing/), [Credencys — Pimcore pricing](https://www.credencys.com/blog/pimcore-pricing-explained/), [Plytix pricing](https://www.plytix.com/pricing/), [Sales Layer](https://www.saleslayer.com/pricing), [inriver — PIM costs](https://www.inriver.com/resources/pim-software-costs/), [Ergonode pricing](https://www.ergonode.com/product-information-management/pricing), [ITQlick — PIM Suite](https://www.itqlick.com/pim-suite/pricing).
- Wzorce/case studies: [Pimcore (Wikipedia)](https://en.wikipedia.org/wiki/Pimcore), [Mailchimp bootstrap](https://medium.com/@LadyF/how-mailchimp-bootstrapped-to-a-12-billion-exit-without-vc-funding-lessons-for-founders-6c5ad328f029), [37signals (Wikipedia)](https://en.wikipedia.org/wiki/37signals), [services trap](https://www.startupfolsom.org/blog/service-as-a-software-the-startup-trap-that-looks-like-saas-but-scales-like-consulting), [bootstrapped SaaS niches](https://entrepreneurloop.com/bootstrapped-saas-niches-solo-founders/).
- Unit economics: [Vitally — churn](https://www.vitally.io/post/saas-churn-benchmarks), [Optifai — LTV/churn](https://optif.ai/learn/questions/b2b-saas-ltv-benchmark/), [Maxio 2025 benchmarks](https://www.maxio.com/resources/2025-saas-benchmarks-report), [Growcode — Akeneo impl. cost](https://www.growcode.com/blog/how-much-does-it-cost-to-implement-akeneos-pim/), [ARDURA — PL dev rates 2026](https://ardura.consulting/blog/software-development-rates-poland-2026/), [Intigate — maintenance cost](https://www.intigatetechnologies.com/software-maintenance-cost-per-year/).
- Rynek PL / ryzyko: [ewp.pl — liczba e-sklepów PL](https://ewp.pl/liczba-sklepow-internetowych-w-polsce-rosnie-mimo-presji-globalnych-platform/), [base.com — BaseLinker](https://base.com/pl-PL/blog/gdzie-mozesz-sprzedawac-dzieki-baselinkerowi/), [Ergonode (Wikipedia)](https://en.wikipedia.org/wiki/Ergonode), [fintek.pl — inwestycja w Ergonode](https://fintek.pl/tworca-eobuwie-inwestuje-5-mln-pln-w-polski-startup-ergonode/), [ifirma — zakaz konkurencji IT](https://www.ifirma.pl/blog/zakaz-konkurencji-w-umowach-pracowniczych-branza-it/), [averi.ai — programmatic SEO B2B](https://www.averi.ai/blog/programmatic-seo-for-b2b-saas-startups-the-complete-2026-playbook).

*Uwaga: ceny enterprise (Salsify, inriver, Sales Layer) to estymaty agregatorów — vendorzy nie publikują cenników. Stawki PL i produktywność FTE to widełki rynkowe B2B, nie dane wyłącznie PIM-owe.*
