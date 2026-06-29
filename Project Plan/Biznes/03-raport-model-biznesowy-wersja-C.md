# Raport decyzyjny — model biznesowy dla Cortex PIM (WERSJA 3 / krytyczna)

> **Po co ta wersja:** operator słusznie zauważył, że Wersja B była napisana pod tezę (50k + 32 zasady → „twój preferowany kierunek jest najlepszy"). Tę wersję zbudowano po przepuszczeniu rekomendacji B przez **radę LLM (5 niezależnych advisorów + peer-review + chairman)** z jawnym zadaniem: znajdźcie, co jest nie tak.
> **Data:** 2026-06-14. **Poprzednie:** `01-...md` (A — hybryda usługowa), `02-...wersja-B.md` (B — viral product-led). Modele: `Model-finansowy-PIM.xlsx`, `...wersjaB.xlsx`.
> **Status modeli finansowych:** zdegradowane do „scenariuszy warunkowych" — patrz sekcja 7. To projekcje na niezwalidowanych założeniach, nie prognozy.

---

## 1. Streszczenie wykonawcze

Rada jednogłośnie potwierdziła podejrzenie operatora: **Wersja B była dopasowana pod tezę.** Co więcej, obie wcześniejsze wersje (A i B) popełniały ten sam grzech — optymalizowały *strategię wzrostu* (jak dojść do 10 mln), zanim rozstrzygnięto dwie rzeczy, które mogą **wyzerować cały projekt niezależnie od strategii**:

1. **Czy masz prawo to sprzedawać** (konflikt z Ideo to nie etyczny przypis — to potencjalne roszczenie do własności Cortex). *To była ślepa plamka wszystkich trzech wcześniejszych wersji.*
2. **Czy produkt w ogóle działa** na żywym katalogu — pisany przez AI „system of record", którego solo-nie-programista nie umie zdebugować, u pierwszego klienta za 45k.

**Rekomendacja Wersji 3: porzuć framing „growth-first". Przejdź na „legal-first + validation-first".** Dopiero po przejściu tych dwóch bramek pytanie „produkt vs usługi vs viral wedge" w ogóle ma sens — i wtedy odpowiedź jest skromniejsza i bezpieczniejsza niż B: **jeden płatny design-partner + ręczna walidacja, potem dystrybucja**, a wedge wraca najwyżej jako *późniejszy lead-gen*, nie jako rdzeń biznesu.

To nie jest raport mówiący „twój pomysł jest zły". To raport mówiący: **rozwiązujesz zadania w złej kolejności, a dwa zadania bramkujące pominąłeś.**

---

## 2. Czego rada nie kupiła w Wersji B (demontaż)

**Błąd kategorii „viral wedge → platforma 45k".** Nie istnieje lejek między impulsowym kupującym tani, jednorazowy tool a komitetem kupującym PIM za 45k w cyklu 3–9 miesięcy (procurement, migracja, security review). To dwie różne firmy sklejone jedną prezentacją. Model B „domykał" rok 1, bo cicho zakładał, że ~11 wedge-kupujących zamieni się w subskrypcje 45k w 12 miesięcy — co przeczy realiom sprzedaży B2B.

**„Viral" + „no free plan + hard paywall" + „bez twarzy" gryzą się wzajemnie.** Viralność to swobodne dzielenie (bo darmowe/zabawne/łatwe). Zamknięte drzwi z paywallem i anonimowym frontem to anty-viral *i* anty-zaufanie w B2B, gdzie kupujący chcą wiedzieć, kto trzyma ich dane. Trzy zasady z listy zadziałały przeciw sobie.

**32 zasady to playbook dla tooli za $29 z zerowym kosztem przełączenia** — nie dla sześciocyfrowej infrastruktury B2B z długim cyklem. Import GTM z jednego świata do drugiego to błąd dressed jako inspiracja. (Zasady są użyteczne dla *landingu/wedge'a*, nie dla modelu biznesowego całości — to jedyne, co z nich zostaje.)

**Virality zwiększa ryzyko, nie zmniejsza.** Jeśli produkt zepsuje się u pierwszego klienta, viralowy ruch sprawia, że dzieje się to *publicznie, na skalę, przy konkurencie (Ideo) patrzącym*.

**„Proxy twarz teraz, Marcin po etat-exit"** to nie rozwiązanie konfliktu z Ideo — to dowód, że konflikt jest *nierozwiązany*. Dodatkowo: w dniu rzucenia etatu znika poduszka pensji — dokładnie wtedy, gdy przychód jest najbardziej kruchy.

---

## 3. Bramka 0 (NADRZĘDNA) — prawo / IP / zakaz konkurencji

To jednogłośna, najmocniejsza ślepa plamka wykryta w peer-review rady. **Budując konkurencyjny PIM będąc zatrudnionym w software housie wdrażającym PIM, ryzykujesz, że Ideo ma prawne roszczenie do samego aktywa** — przez klauzule IP-assignment, pracę opartą o wiedzę/zasoby pracodawcy, lub zakaz konkurencji. Praca „po godzinach" i „proxy twarz" nie zrywają tej ekspozycji automatycznie.

Konsekwencja: jeśli to ryzyko się zmaterializuje, **żadna strategia (A/B/cokolwiek) nie ma znaczenia — produkt może nie być Twój do sprzedania.** Dlatego to bramka 0, przed wszystkim innym.

**Działanie:** zanim napiszesz kolejną linijkę i zanim wydasz złotówkę z 50k — daj umowę o pracę i ewentualny zakaz konkurencji do przeglądu **polskiemu prawnikowi IT (prawo pracy + IP)**. Koszt rzędu kilkuset–kilku tys. zł. To najtańsza polisa w całym przedsięwzięciu.

---

## 4. Bramka 1 — czy produkt w ogóle działa (validation-first)

Druga rzecz, którą A i B pomijały: **nie masz dowodu, że Cortex działa na realnym katalogu w produkcji.** To, a nie wybór modelu, jest „problemem na poniedziałek rano". Solo + agent AI + „system of record" + brak umiejętności debugowania = ryzyko, że pierwszy poważny klient jest jednocześnie pierwszym testem produkcyjnym.

**Sekwencja walidacji (najszybsza droga do 300k *i* do dowodu jednocześnie):**
1. **Zatrzymaj dosypywanie funkcji na 60%.** Wybierz 3 workflow, które realny kupujący PIM robi codziennie: import, edycja/wzbogacanie, eksport do jednego kanału.
2. **Znajdź JEDNEGO przyjaznego design-partnera teraz** — stromy rabat / pilotaż, Ty robisz białą rękawiczką migrację jego danych ręcznie. **Ta ręczna migracja JEST Twoim QA.**
3. **Puść jego realny katalog przez system na 30 dni.** Loguj każdą awarię. Naprawiaj. To zamienia „AI to napisał, może paść" w „działało w produkcji, oto uptime".
4. Ten jeden płatny pilot + 2–3 referencje doprowadzą do 300k szybciej niż viralowy tool wpuszczający obcych w nieprzetestowane oprogramowanie.

Dopiero po przejściu Bramki 1 ryzyko techniczne przestaje dominować decyzję biznesową.

---

## 5. Przeramowanie celu — 10 mln jest zapożyczone, nie wyprowadzone

Rada (First Principles) trafnie wskazała: **nikt nie zinterrogował celu 10 mln w roku 7.** A to on dyktuje całą resztę — wymusza zespół ~9 osób, ekspansję CEE/EU i „viralowy premium funnel", którego sam zacząłeś podejrzewać.

Pytanie do świadomego rozstrzygnięcia (sekcja 11): czy celem jest **10 mln top-line** (ścieżka VC-like: zespół, ekspansja, ryzyko), czy np. **trwały zysk rzędu 500k–1 mln zł/rok** (ścieżka boring/sticky/low-churn, mały zespół, niski churn, brak przymusu virality)? Te dwa cele dają **przeciwne** strategie. Wersje A i B milcząco przyjęły pierwszy. Zanim wybierzesz model, wybierz cel — bo model jest tylko jego konsekwencją.

---

## 6. Gdzie rada się spierała (uczciwie, bez wygładzania)

- **Co z wedgem?** Contrarian: zabij, wybierz jedną firmę. Executor: odłóż jako *lead-gen po* walidacji rdzenia. Expansionist: wedge może być prawdziwym produktem (data moat — każde użycie to oznaczony przykład „bałagan → czyste dane", którego Akeneo/Pimcore nie odtworzą). **Rozstrzygnięcie zależy od Bramki 1:** jeśli rdzeń PIM przejdzie walidację — wedge jako lead-gen (Executor). Jeśli nie — wedge może być jedynym sensownym produktem (Expansionist), ale to *inna firma* niż PIM.
- **Czy w ogóle PIM?** First Principles: „może to nie powinien być PIM" (zbyt duże ryzyko cudzych danych dla solo-nie-programisty). Executor: PIM jest OK, tylko wykonaj walidację porządnie. To najgłębszy spór — i rozstrzyga go wynik Bramki 1 na realnym kliencie, nie kolejny raport.

---

## 7. Status modeli finansowych (A i B)

Oba modele (`Model-finansowy-PIM.xlsx`, `...wersjaB.xlsx`) pozostają użyteczne **jako kalkulatory scenariuszy warunkowych**, nie jako prognozy. Każda liczba w nich (ACV 45k, churn 22%, konwersja 4%, „206 klientów") jest założeniem, którego **nie zwalidowano ani jednym klientem**. Dopóki Bramka 1 nie dostarczy realnych danych (faktyczny ACV pilota, realny czas wdrożenia, realny churn), traktuj wyniki jako „co musiałoby być prawdą", a nie „co będzie". Po pilocie — wróć i podmień żółte pola na liczby z rynku.

---

## 8. Zrewidowana rekomendacja i sekwencja (z bramkami)

**Model docelowy: validation-first, product-led, skromny — NIE viral-growth-first.** Konkretnie:

- **Bramka 0 — prawo (teraz, dni).** Przegląd umowy/zakazu konkurencji u prawnika IT. Bez zielonego światła — STOP, zanim cokolwiek innego.
- **Bramka 1 — walidacja (1–3 mc).** Jeden płatny design-partner, ręczna migracja = QA, 30 dni na realnym katalogu, log/fix, 2–3 referencje. To jest Twoje pierwsze ~300k *i* dowód działania.
- **Faza 2 — dystrybucja (po Bramce 1).** Dopiero teraz: pricing (na bazie realnego ACV pilota, nie zgadywanego 45k), tani skalowalny kanał (programmatic SEO + integracje BaseLinker/Allegro/Shopify), ewentualnie **wedge jako lead-gen** (nie jako rdzeń). Zasady viralowe stosuj do *landingu i wedge'a*, nie do modelu całości.
- **Faza 3 — skala i decyzja o celu.** Jeśli wybrałeś cel 10 mln → ekspansja CEE/EU + zespół. Jeśli „trwały zysk" → zostań mały, sticky, niskchurnowy. Decyzję o etat-exit podejmij na liczbach (przychód ~pensja + runway), nie na ambicji.

Usługi (wersja A) i wedge (wersja B) **nie są silnikiem** — są co najwyżej narzędziami w Fazie 2, każde z twardym capem.

---

## 9. Ryzyka (zaktualizowane po radzie)

| Ryzyko | Waga | Status / mitygacja |
|---|---|---|
| IP / zakaz konkurencji (Ideo może rościć Cortex) | **Krytyczne** | Bramka 0 — prawnik przed wszystkim. |
| Niezwalidowane oprogramowanie pisane przez AI (awaria u klienta) | **Krytyczne** | Bramka 1 — design partner + ręczne QA + 30 dni. |
| 50k ≈ równowartość jednej nieudanej migracji; brak ubezpieczenia OC | Wysokie | Limit odpowiedzialności w umowie pilota; OC zawodowe; nie skalować przed Bramką 1. |
| Cel 10 mln zapożyczony → wymusza ryzykowny growth | Wysokie | Sekcja 5 — wybierz cel świadomie. |
| Brak twarzy/marki osobistej (Ideo) | Średnie | Proxy/fazowo — ale to konsekwencja Bramki 0, nie obejście. |
| Rynek PL za mały na 10 mln | Średnie | Aktualne; rozstrzyga się dopiero po wyborze celu. |
| Potwierdzanie własnej tezy (confirmation bias) | Średnie | Ta wersja; powtarzać council przy każdej dużej decyzji. |

---

## 10. Jedna rzecz do zrobienia najpierw

**Daj umowę o pracę i ewentualny zakaz konkurencji do przeglądu polskiemu prawnikowi IT — zanim napiszesz kolejną linijkę kodu lub wydasz złotówkę z 50k.** To jedyna rzecz, która może unieważnić całe przedsięwzięcie niezależnie od jakości strategii, więc bramkuje wszystko inne.

(Równolegle, gdy tylko prawo da zielone światło: zacznij szukać jednego design-partnera pod Bramkę 1.)

---

## 11. Otwarte pytania do operatora (rozstrzygnij przed dalszą pracą)
1. **Cel: 10 mln top-line czy trwały zysk ~500k–1 mln/rok?** (przeciwne strategie — sekcja 5).
2. **Status prawny:** czy masz już jakąkolwiek wiedzę o treści umowy/zakazu konkurencji z Ideo? (determinuje, czy w ogóle ruszamy).
3. **Czy akceptujesz validation-first** (wolniejszy start, najpierw 1 pilot) zamiast growth-first z B?
4. Czy „trwały zysk, mały zespół" jest dla Ciebie akceptowalnym sukcesem — czy 10 mln to twarde must?

---

## Załącznik — jak powstała ta wersja
Rada LLM, 5 advisorów (Contrarian, First Principles, Expansionist, Outsider, Executor) + anonimowy peer-review + synteza chairmana. Najmocniejsza perspektywa: **Executor** (validation-first, design partner jako QA). Największa ślepa plamka: **Expansionist** (agent layer jako kategoria — ta sama pochlebność co B). Jednogłośne odkrycie peer-review, którego nie widział żaden pojedynczy advisor: **ryzyko IP/zakazu konkurencji jako roszczenie do samego aktywa.** Źródła rynkowe — patrz `01-raport-model-biznesowy.md`.
