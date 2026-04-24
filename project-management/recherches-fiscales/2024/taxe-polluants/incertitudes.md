# Zones grises et points à valider — Taxe annuelle sur les émissions de polluants atmosphériques — Exercice 2024

> Ce document détaille les incertitudes, zones grises et décisions à confiance basse ou moyenne identifiées au cours de l'instruction de la taxe polluants pour 2024. Il alimente le fichier transverse `recherches-fiscales/incertitudes.md` qui en présente la synthèse.
>
> **Auteur** : Micha MEGRET (prestataire)
> **Convention de numérotation** : `Z-AAAA-NNN` où `AAAA` = année fiscale concernée, `NNN` = numéro séquentiel par année.

---

## Z-2024-002 — Qualification du modèle Floty au regard de l'exonération LCD (renvoi par parallélisme avec la taxe CO₂)

L'exonération de location de courte durée (LCD) impacte les **deux taxes simultanément** : CIBS art. L. 421-129 (taxe CO₂) et CIBS art. L. 421-141 (taxe polluants), avec un texte rigoureusement parallèle. L'incertitude Z-2024-002 a été ouverte lors de l'instruction de la taxe CO₂ et concerne donc également la taxe polluants par parallélisme.

Le détail consolidé est tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-002). Pour rappel synthétique :

- **Lecture définitive** : l'exonération LCD est appliquée systématiquement par Floty selon une mécanique de **cumul annuel par couple (véhicule, entreprise utilisatrice)**, conforme à L. 421-129 + L. 421-141 + BOFiP § 180 et à la pratique de Renaud (sans redressement fiscal).
- Cumul annuel ≤ 30 jours pour un couple → couple entièrement exonéré (les deux taxes simultanément).
- Cumul annuel > 30 jours → pas d'exonération, taxes (CO₂ ET polluants) dues au prorata du cumul.
- **Statut** : **Résolu — 23/04/2026** par clarification directe avec le client.

**Conséquence pour la taxe polluants** : les exemples chiffrés du présent sous-dossier (notamment Exemple E dans `decisions.md` Décision 5) restent valables comme illustration arithmétique du calcul polluants ; leur interprétation pratique au regard du modèle Floty s'inscrit désormais dans la mécanique de cumul par couple. Voir `taxes-rules/2024.md` R-2024-021 pour la définition complète de la règle (commune CO₂ + polluants).

---

## Z-2024-007 — Classement des véhicules hybrides Diesel-électrique en catégorie polluants

- **Localisation** : `recherches.md` § 6.3, § 7 (Q2) ; `decisions.md` Décision 3. **Désormais consolidé en règle d'implémentation Floty dans `2024/cas-particuliers/recherches.md` § 6 et `2024/cas-particuliers/decisions.md` Décision 4.**
- **Nature de l'incertitude** : L'article L. 421-134, 2° du CIBS définit la catégorie 1 par les véhicules « alimentés par un moteur thermique à allumage commandé » (donc essence ou gaz, hybride essence inclus). Les véhicules **hybrides combinant un moteur Diesel et un moteur électrique** (technologie peu répandue mais commercialisée par certains constructeurs premium en 2018-2022, par exemple Mercedes Classe E 300de, Volvo XC90 Twin Engine) n'ont pas de moteur thermique à allumage commandé et tombent — par lecture stricte du texte — en catégorie « véhicules les plus polluants » (500 € en 2024). Aucune source primaire ne traite explicitement ce cas de figure. Les sources tertiaires (S9 FNA, S11 Guichet Carte Grise) confirment l'exclusion des Diesels en général sans mentionner spécifiquement les hybrides Diesel.
- **Notre choix actuel** : par lecture stricte de l'article L. 421-134 et par cohérence avec la vignette Crit'Air (un hybride Diesel a généralement Crit'Air 2 et non 1), classement en « véhicules les plus polluants » → 500 €. Application du principe de prudence (méthodologie § 8.3 — la lecture la plus majorante en cas de doute).
- **Précisions issues de l'instruction des cas particuliers** :
  - Vérification d'absence d'assouplissement réglementaire effectuée — aucune disposition (article CIBS, BOFiP, notice DGFiP, arrêté ministériel) n'a été identifiée prévoyant un traitement particulier pour les hybrides Diesel-électrique. Cette absence est cohérente avec l'esprit de la disposition (les hybrides Diesel ont une part de leur fonctionnement assurée par un moteur Diesel, qui émet par construction davantage d'oxydes d'azote et de particules fines).
  - Algorithme `categorie_polluants_etendu` formalisé avec ajout du champ Floty `type_moteur_thermique_sous_jacent` (énumération {Essence, Diesel, sans objet}), conditionnel pour les motorisations « Hybride non rechargeable » et « Hybride rechargeable ».
  - Garde-fou UI : sélecteur obligatoire pour ce champ lors de la saisie d'un véhicule hybride, avec explication contextuelle.
  - Test unitaire : Mercedes Classe E 300de hybride rechargeable Diesel + électrique → catégorie « véhicules les plus polluants » → 500 €.
- **Conséquence si erroné** : sur-imposition de 400 € par véhicule hybride Diesel et par an (500 - 100). Population concernée très restreinte dans une flotte d'entreprise française type.
- **Action attendue** : Validation expert-comptable. Question précise : « Pour un véhicule hybride combinant moteur Diesel et moteur électrique homologué Euro 6, quelle catégorie de la taxe annuelle polluants atmosphériques s'applique : catégorie 1 ou catégorie « véhicules les plus polluants » ? ».
- **Statut** : **Ouvert** (consolidé, règle Floty documentée — validation EC souhaitable pour clôture)

---

## Z-2024-008 — Vérification de l'exemple chiffré BOFiP § 290 (taxe polluants)

- **Localisation** : `recherches.md` § 3.9, § 6.1, § 7 (Q1).
- **Nature de l'incertitude** : L'exemple chiffré officiel BOFiP § 290 énonce un résultat de **32,5 €** pour les hypothèses : véhicule de catégorie 1 (tarif 100 €), 75 % d'utilisation pour activité non exonérée, coefficient pondérateur 50 % (kilométrage 30 000 km). Le calcul littéral `0,75 × 0,50 × 100 = 37,5 €` donne 37,5 € et non 32,5 €. Hypothèse plausible : le pourcentage d'affectation à l'activité non exonérée est en réalité de 65 % et non 75 % (`0,65 × 0,50 × 100 = 32,5 €` ✓), ce qui suggère une erreur de paraphrase. Aucune erreur de mécanique fondamentale.
- **Notre choix actuel** : la mécanique de calcul `Tarif × Prorata × [Coefficient pondérateur] × [% activité non exonérée]` est par ailleurs **directement énoncée** par la notice DGFiP S1 partie IV ligne N. Floty s'appuie sur cette formulation, qui est non ambiguë.
- **Conséquence si erroné** : aucune impactant Floty V1 (le mécanisme « activité partielle non exonérée × coefficient pondérateur » est hors périmètre Floty V1).
- **Action attendue** : Vérification documentaire en seconde passe. Sans urgence opérationnelle.
- **Statut** : **Ouvert** (point documentaire, faible impact opérationnel).

---

## Z-2024-009 — Garde-fou de cohérence Crit'Air vs motorisation+Euro

- **Localisation** : `decisions.md` Décision 4 ; `recherches.md` § 7 (Q4).
- **Nature de l'incertitude** : Le BOFiP § 270 établit la correspondance entre les trois catégories CIBS et les vignettes Crit'Air. Cette correspondance peut être utilisée comme **garde-fou de cohérence** : si l'algorithme de classement (basé sur motorisation + norme Euro) aboutit à une catégorie X et que la vignette Crit'Air saisie suggère une catégorie Y différente, comment résoudre le conflit ? Choix produit, pas d'arbitrage juridique direct par les sources primaires.
- **Notre choix actuel** : l'algorithme s'appuie principalement sur motorisation + norme Euro (données du certificat d'immatriculation, fiables). La vignette Crit'Air sert d'**avertissement UI** en cas d'incohérence (sans bloquer le calcul). L'utilisateur peut alors corriger ses données ou conserver la catégorie calculée par défaut.
- **Conséquence si erroné** : à la marge — un véhicule mal qualifié sera signalé par le garde-fou. Mais si l'utilisateur ignore l'avertissement, risque résiduel de mauvais classement.
- **Action attendue** : Confirmation du choix UX par Renaud + suivi qualité dès les premiers calculs réels.
- **Statut** : **Ouvert** (point UX/produit, à clore en phase de développement).

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 23/04/2026 | Micha MEGRET | Création initiale lors de la refonte de l'organisation des incertitudes (déplacement depuis le fichier global `recherches-fiscales/incertitudes.md`). Contient Z-2024-007 à Z-2024-009, soit les 3 incertitudes issues de l'instruction de la taxe polluants 2024. |
| 0.2 | 23/04/2026 | Micha MEGRET | Z-2024-007 (« Hybrides Diesel-électrique ») consolidée par l'instruction du sous-dossier `2024/cas-particuliers/`. Statut maintenu **Ouvert** (validation expert-comptable encore souhaitable car le cas n'est pas explicitement traité par les sources primaires), mais la règle d'implémentation Floty est désormais entièrement documentée (algorithme `categorie_polluants_etendu`, ajout du champ `type_moteur_thermique_sous_jacent`, garde-fou UI, exemple chiffré). Vérification d'absence d'assouplissement réglementaire effectuée — aucune disposition contradictoire identifiée. |
| 0.3 | 23/04/2026 | Micha MEGRET | **Ajout d'un renvoi par parallélisme pour Z-2024-002** (qualification du modèle Floty au regard de l'exonération LCD), correction d'une omission méthodologique : l'exonération LCD impacte simultanément la taxe CO₂ (L. 421-129) et la taxe polluants (L. 421-141), donc l'incertitude — ouverte initialement dans le sous-dossier taxe-co2 — devait également apparaître ici par renvoi. Statut **Résolu — 23/04/2026** (cohérent avec les autres sous-dossiers). |
