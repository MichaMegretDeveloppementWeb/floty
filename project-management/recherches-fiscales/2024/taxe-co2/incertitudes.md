# Zones grises et points à valider — Taxe annuelle sur les émissions de CO₂ — Exercice 2024

> Ce document détaille les incertitudes, zones grises et décisions à confiance basse ou moyenne identifiées au cours de l'instruction de la taxe CO₂ pour 2024. Il alimente le fichier transverse `recherches-fiscales/incertitudes.md` qui en présente la synthèse.
>
> **Auteur** : Micha MEGRET (prestataire)
> **Convention de numérotation** : `Z-AAAA-NNN` où `AAAA` = année fiscale concernée, `NNN` = numéro séquentiel par année.

---

## Z-2024-001 — Définition du « jour d'affectation » et traitement des indisponibilités longues

- **Localisation** : `decisions.md` Décision 8 ; `recherches.md` § 5.11, § 7 (Q3). **Désormais consolidé en règle d'implémentation Floty dans `2024/cas-particuliers/recherches.md` § 8 et `2024/cas-particuliers/decisions.md` Décision 5.**
- **Nature de l'incertitude** : Décision à confiance moyenne. Le BOFiP (S3 § 190) cite **uniquement la mise en fourrière** comme motif de réduction du numérateur du prorata. Pour les autres types d'indisponibilité (maintenance, sinistre privé, contrôle technique), le texte ne tranche pas. Notre choix par défaut : **conserver ces jours dans le numérateur** (l'entreprise reste affectataire pendant la réparation). Cette lecture est défendable mais non explicite. À l'inverse, lors d'un sinistre prolongé immobilisant le véhicule plusieurs mois, on pourrait défendre que l'affectation économique est interrompue de fait.
- **Notre choix actuel** : type d'indisponibilité « Fourrière / immobilisation administrative » → réduit le numérateur. Tous les autres types (Maintenance, CT, Sinistre, Autre) → ne réduisent pas le numérateur. Algorithme et garde-fou UI désormais formalisés en règle d'implémentation Floty (`2024/cas-particuliers/`).
- **Conséquence si erroné** : sur-imposition légère pour les véhicules subissant des immobilisations prolongées hors fourrière (sinistre lourd, maintenance étendue). Conforme au principe de prudence (méthodologie § 8.3) : nous majorons par défaut.
- **Action attendue** : Validation expert-comptable. Question précise : « Pour Floty, faut-il assimiler à la fourrière les indisponibilités longues pour sinistre privé majeur (immobilisation > 30 jours) du point de vue du prorata fiscal ? »
- **Statut** : **Ouvert** (consolidé, règle Floty documentée — validation EC nécessaire pour clôture)

---

## Z-2024-002 — Qualification du modèle Floty au regard de l'exonération LCD

- **Localisation** : `recherches.md` § 6.4, § 7 (Q2) ; échos dans `2024/exonerations/recherches.md` § 4.7 et § 8.3, `2024/exonerations/decisions.md` Décision 7, `2024/cas-particuliers/recherches.md` § 7 et `2024/cas-particuliers/decisions.md` Décision 5.
- **Historique de l'incertitude** : initialement formulée comme une incertitude « LLD vs LCD » avec hypothèse de travail par défaut « qualification LLD », elle a été clarifiée le 23/04/2026 après précisions directes du client sur la nature exacte de son montage contractuel.
- **Lecture doctrinale retenue après clarification** : Floty s'appuie sur le régime de l'**exonération de location de courte durée (LCD)** prévu par CIBS art. L. 421-129 (taxe CO₂) et L. 421-141 (taxe polluants), interprété strictement selon la doctrine officielle (BOFiP `BOI-AIS-MOB-10-30-20` § 180) :
  - L'exonération s'évalue **par couple (véhicule, entreprise utilisatrice)**, sur **cumul annuel** des jours de location.
  - Cumul annuel ≤ 30 jours pour le couple → **couple entièrement exonéré** (les deux taxes).
  - Cumul annuel > 30 jours pour le couple → **pas d'exonération**, taxe due au prorata du cumul / 365 (ou 366).
  - Conformité de la pratique avec L. 421-99 (l'entreprise utilisatrice est l'affectataire dès lors qu'elle dispose du véhicule via une location).
- **Conséquence pratique pour Floty** : la mécanique de cumul par couple est intégrée comme **règle fiscale standard** dans le moteur (cf. R-2024-021 dans `taxes-rules/2024.md`). Elle a un fort impact économique pour les entreprises utilisatrices (couples souvent exonérés en cas de rotation des véhicules), mais elle n'altère pas la nature du moteur de calcul, qui reste un calculateur de taxes classique appliquant l'ensemble des règles fiscales documentées.
- **Recommandation pour la conception applicative** : exposer dans les vues d'attribution (notamment vue par entreprise) un compteur visible « jours cumulés sur l'année / impact fiscal estimé » par couple (véhicule, entreprise), afin de rendre transparente la mécanique d'exonération LCD au moment des décisions d'attribution. Cette exigence sera intégrée à la phase de spécification du périmètre MVP.
- **Statut** : **Résolu — 23/04/2026** par clarification du modèle économique de Renaud et confirmation de cohérence avec la doctrine officielle. La pratique de Renaud sur ce point n'a pas fait l'objet de redressement fiscal, ce qui constitue une présomption forte de validité.

---

## Z-2024-003 — Abattement E85 — applicable ou non en 2024 ?

- **Localisation** : `recherches.md` § 5.4, § 7 (Q1). **Tranché dans `2024/abattements/`** (`recherches.md` § 5 et `decisions.md` Décisions 2 et 3).
- **Nature de l'incertitude** : Divergence apparente entre :
  - Cahier des charges Floty § 5.6 : « Abattements pour véhicules E85 : à compter du 1er janvier 2025 ».
  - Cartographie phase 0 : « 2024 : abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) ».
  - Notice DGFiP S1 partie II : ne mentionne pas d'abattement E85 isolé pour 2024 (E85 entre uniquement dans le mécanisme d'exonération hybride § L. 421-125).
- **Notre choix actuel (provisoire)** : **Pas d'abattement E85 isolé en 2024**, conformément au cahier des charges et à la lecture stricte de la notice DGFiP. L'E85 peut bénéficier de l'exonération hybride si combiné à une autre énergie ; sinon plein tarif.
- **Conséquence si erroné** : sur-imposition de 40 % pour les véhicules E85 affectés en 2024. Pour la flotte Renaud, à évaluer en fonction du nombre de véhicules E85 effectivement présents.
- **Action attendue** : Confirmation par recherche dédiée dans `2024/abattements/` + validation expert-comptable. Vérification primaire à faire : article CIBS exactement applicable en 2024 pour les abattements (potentiellement L. 421-XXX inséré ultérieurement par LF 2025).
- **Statut** : **Résolu — 23/04/2026**

### Issue de la clôture (renvoi vers `2024/abattements/`)

L'instruction du sous-dossier `2024/abattements/` a clos cette incertitude par triangulation primaire complète :

- **Conclusion** : l'abattement E85 isolé n'est PAS applicable en 2024.
- **Sources primaires concordantes** :
  - CIBS art. L. 421-125 dans sa version applicable au 31/12/2023 (texte intégral lu sur Légifrance) — décrit une **exonération conditionnelle** (et non un abattement) pour les véhicules combinant E85 avec une autre source d'énergie sous seuils CO₂/PA.
  - BOFiP `BOI-AIS-MOB-10-30-20-20240710` (version applicable à l'exercice 2024) — aucune mention d'un abattement E85 isolé pour 2024.
  - Notice DGFiP n° 2857-FC-NOT-SD édition décembre 2024 — aucune mention d'un abattement E85 isolé pour 2024.
- **Preuve par contraste** : la version postérieure du BOFiP `BOI-AIS-MOB-10-30-20` (publiée à compter du 28/05/2025) énonce au § 240 que l'abattement E85 (40 % CO₂ ou 2 CV PA, plafonds 250 g/km et 12 CV) s'applique « **À compter du 1er janvier 2025** ». Cet abattement résulte de la révision de l'article L. 421-125 du CIBS par la loi de finances pour 2025 (loi n° 2025-127 du 14/02/2025) — le même article changeant donc de nature entre 2024 (exonération) et 2025 (abattement).
- **Origine de l'erreur dans la cartographie phase 0** : confusion entre les deux versions de l'article L. 421-125 (2022-2024 et à compter du 01/01/2025). Une correction documentaire est proposée dans `2024/abattements/decisions.md` Décision 3.
- **Implémentation Floty** : aucune règle « abattement E85 » n'est codée pour 2024. Pour les véhicules carburant à l'E85 :
  - Mono-carburant E85 : ni exonération ni abattement → plein tarif WLTP/NEDC/PA.
  - Combiné avec une autre source d'énergie éligible (électrique, hydrogène, gaz naturel, GPL) : éligible à l'exonération hybride conditionnelle § L. 421-125 (`2024/exonerations/decisions.md` Décision 4), sous réserve des seuils CO₂/PA (60/50/3 ou 120/100/6 selon ancienneté).
- **À traiter en 2025** : `2025/abattements/` instruira la mise en œuvre détaillée de l'abattement E85 issu de la révision de L. 421-125 par la LF 2025.

Confiance de la résolution : **Haute** (triangulation primaire complète et univoque).

---

## Z-2024-004 — Véhicule importé d'occasion (1ère immat. étrangère antérieure à 1ère immat. France)

- **Localisation** : `recherches.md` § 7 (Q6). **Tranché dans `2024/cas-particuliers/`** (`recherches.md` § 4 et `decisions.md` Décision 2).
- **Nature de l'incertitude** : La règle de bascule entre WLTP, NEDC et PA s'appuie sur la **date de 1ère immatriculation en France** ET la **méthode d'homologation** du véhicule. Pour un véhicule homologué à l'origine en NEDC dans un pays européen en 2018, importé en France et immatriculé pour la première fois en France en 2022, faut-il appliquer le barème WLTP (parce que 1ère immat. France ≥ 01/03/2020) ou NEDC (parce que homologué NEDC à l'origine) ?
- **Notre choix actuel** : Suivre la **méthode d'homologation effective** du véhicule (qui figure normalement sur le certificat de conformité). Si le véhicule a été homologué NEDC, on applique NEDC, indépendamment de la date France. Cette lecture s'aligne sur la formulation « véhicules immatriculés en recourant à la méthode WLTP » de la notice S1 — la méthode est l'homologation, pas la date.
- **Conséquence si erroné** : choix de barème incorrect ; impact variable selon les émissions du véhicule.
- **Action attendue** : Sans objet — incertitude clôturée par triangulation primaire complète.
- **Statut** : **Résolu — 23/04/2026**

### Issue de la clôture (renvoi vers `2024/cas-particuliers/`)

L'instruction du sous-dossier `2024/cas-particuliers/` a clos cette incertitude par triangulation primaire complète :

- **Conclusion** : la lecture par défaut documentée dans Z-2024-004 est confirmée. Pour un véhicule homologué NEDC à l'étranger en 2018 et immatriculé en France pour la première fois en 2022, le **barème NEDC** s'applique, conformément à la **méthode d'homologation effective**.
- **Sources primaires concordantes** :
  - **CIBS art. L. 421-119-1** (texte intégral lu sur Légifrance) — formulation littérale du 1° (« véhicules immatriculés en recourant à la méthode WLTP » — condition sur la **méthode**, pas sur la date) et du 2° (réception européenne, immatriculés à compter du 01/06/2004 — sans préciser « en France »). Le pivot conceptuel est la méthode d'homologation effective.
  - **BOFiP** `BOI-AIS-MOB-10-30-20-20240710` § 210-220 — confirme la lecture par méthode d'homologation effective.
  - **Notice DGFiP** n° 2857-FC-NOT-SD partie II.1 — la date du 01/03/2020 est un critère pratique de bascule propre à la France (date à laquelle tous les véhicules neufs immatriculés en France l'étaient en méthode WLTP), non un critère normatif de fond.
- **Implémentation Floty** : algorithme `determiner_bareme` étendu pour utiliser la `date_premiere_immatriculation_origine` (étrangère pour un véhicule importé) dans la condition du 2° de L. 421-119-1, et non la `date_premiere_immatriculation_france`. Voir `2024/cas-particuliers/decisions.md` Décision 2.
- Garde-fou UI pour les véhicules importés : alerte affichant la justification du barème retenu et la date d'origine.
- Test unitaire impératif : véhicule BMW Série 3 NEDC homologué en Allemagne en 2018, immatriculé en France en 2022 → barème NEDC.

Confiance de la résolution : **Haute** (triangulation primaire complète et univoque).

---

## Z-2024-005 — Frontière fiscale M1 / N1 (pick-up 5 places, camionnette dérivée)

- **Localisation** : Cartographie `cartographie-taxes.md` § 6.6 ; `recherches.md` § 5.9, § 7 (Q4). **Tranché dans `2024/cas-particuliers/`** (`recherches.md` § 3 et `decisions.md` Décision 1).
- **Nature de l'incertitude** : Le périmètre véhicules taxables CIBS art. L. 421-2 inclut explicitement certains N1 (« pick-up ≥ 5 places assises », « camionnette ≥ 2 rangs de places affectée au transport de personnes »). Les critères de qualification reposent sur la mention « carrosserie » du certificat d'immatriculation (rubrique J.2) et sur le nombre de places assises. Cas frontière : véhicule N1 type « Camionnette » avec 4 places assises et un peu de transport de personnes — taxable ou non ?
- **Notre choix actuel** : suivre la lettre du CIBS L. 421-2 (« au moins deux rangs de places » ET « affectés au transport de personnes »). Algorithme entièrement formalisé dans Floty (champ `type_fiscal` du véhicule, calculé automatiquement à partir de catégorie réception européenne + carrosserie + places + usage), avec garde-fou UI pour les cas frontières.
- **Conséquence si erroné** : sur- ou sous-inclusion de véhicules dans le calcul de taxe.
- **Action attendue** : Sans objet — incertitude clôturée par triangulation primaire complète. Mise à jour proposée du cahier des charges § 2.1 pour harmonisation avec la lettre du CIBS.
- **Statut** : **Résolu — 23/04/2026**

### Issue de la clôture (renvoi vers `2024/cas-particuliers/`)

L'instruction du sous-dossier `2024/cas-particuliers/` a clos cette incertitude par triangulation primaire complète :

- **Conclusion** : la règle d'implémentation Floty est entièrement documentée — algorithme `qualifier_type_fiscal` calculé à partir des champs carte grise (J.1, J.2, S.1) et des critères déclaratifs (affectation au transport de personnes pour les camionnettes ; usage exclusif remontées mécaniques pour les pick-ups ≥ 5 places ; usage spécial pour les M1).
- **Sources primaires concordantes** :
  - **CIBS art. L. 421-2** (texte intégral lu sur Légifrance) — 1° pour les M1, 2°-a pour les pick-ups N1 ≥ 5 places, 2°-b pour les camionnettes N1 ≥ 2 rangs affectées au transport de personnes.
  - **BOFiP** `BOI-AIS-MOB-10-30-20-20240710` § 60 — précise les critères opérationnels (rubriques J.1, J.2, S.1).
  - **BOFiP** `BOI-AIS-MOB-10-10` — confirme la définition transversale.
  - **Notice DGFiP** n° 2858-FC-NOT-SD partie I.2.a — reproduction administrative.
- 6 cas types documentés couvrant les principales situations frontières (`2024/cas-particuliers/recherches.md` § 3.4).
- Garde-fou UI pour les cas frontières (pick-up exactement 5 places, camionnette avec ou sans transport de personnes, banquette amovible).
- **Mise à jour proposée du cahier des charges § 2.1** : harmoniser le critère « camionnettes ≥ 3 rangs de places » avec la lettre du CIBS (« au moins deux rangs de places ») et ajouter le critère « affecté au transport de personnes ».

Confiance de la résolution : **Haute** (triangulation primaire complète, lecture directe et univoque des sources).

---

## Z-2024-006 — Bascule automatique sur barème PA en cas de donnée CO₂ manquante

- **Localisation** : `recherches.md` § 6.3, § 7 (Q5). **Tranché dans `2024/cas-particuliers/`** (`recherches.md` § 5 et `decisions.md` Décision 3).
- **Nature de l'incertitude** : La notice S1 prévoit explicitement que les véhicules WLTP-éligibles (≥ 01/03/2020) **sans valeur CO₂ déterminée** basculent sur le barème PA. Cette règle est claire en théorie ; en pratique, un véhicule peut avoir une valeur CO₂ « manquante » pour des raisons diverses (carte grise illisible, donnée non saisie dans Floty, véhicule non encore homologué…). Dans Floty, cette bascule doit être automatique mais **transparente** : l'utilisateur doit comprendre pourquoi son véhicule récent est calculé sur PA.
- **Notre choix actuel** : bascule automatique vers PA si la valeur CO₂ attendue est absente, avec **alerte UI claire** au calcul (« Donnée CO₂ manquante — bascule sur barème puissance administrative »). Ne pas bloquer le calcul. Si ni CO₂ ni PA disponibles, message d'erreur bloquant invitant à compléter la fiche véhicule.
- **Conséquence si erroné** : aucune (la règle de bascule est explicite). Risque uniquement UX (utilisateur surpris).
- **Action attendue** : Sans objet — règle juridique explicite + bonne pratique UX désormais formalisées en règle d'implémentation Floty.
- **Statut** : **Résolu — 23/04/2026**

### Issue de la clôture (renvoi vers `2024/cas-particuliers/`)

L'instruction du sous-dossier `2024/cas-particuliers/` a clos cette incertitude :

- **Règle juridique** : explicite et univoque (CIBS art. L. 421-119-1, 3° + BOFiP `BOI-AIS-MOB-10-30-20` § 220 + notice DGFiP n° 2857-FC-NOT-SD partie II.1).
- **Implémentation Floty** : algorithme `determiner_bareme_avec_bascule_pa` qui (i) applique la bascule automatique sur PA si donnée CO₂ manquante mais PA disponible, avec alerte UI claire ; (ii) bloque le calcul (message d'erreur) si ni CO₂ ni PA ne sont disponibles. Voir `2024/cas-particuliers/decisions.md` Décision 3.
- L'alerte UI répond à un enjeu d'auditabilité (l'utilisateur et l'expert-comptable doivent comprendre pourquoi le tarif est élevé sur ce véhicule). L'écart numérique entre tarif PA et tarif WLTP est généralement massif (× 30 à × 100), ce qui crée une incitation forte à corriger la saisie.
- Test unitaire : véhicule WLTP-éligible (1ère immat. France 15/06/2022) sans CO₂ saisi avec PA = 6 CV → bascule sur PA → tarif annuel plein 11 250 €.

Confiance de la résolution : **Haute** (règle juridique explicite + bonne pratique UX documentée).

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 23/04/2026 | Micha MEGRET | Création initiale lors de la refonte de l'organisation des incertitudes (déplacement depuis le fichier global `recherches-fiscales/incertitudes.md`). Contient Z-2024-001 à Z-2024-006, soit les 6 incertitudes issues de l'instruction de la taxe CO₂ 2024. |
| 0.2 | 23/04/2026 | Micha MEGRET | Z-2024-003 (« Abattement E85 en 2024 ») passée au statut **Résolu** suite à l'instruction du sous-dossier `2024/abattements/`. Triangulation primaire complète : aucun abattement E85 isolé applicable en 2024 ; le dispositif n'apparaît qu'à compter du 1er janvier 2025 par révision de l'article L. 421-125 du CIBS. Détail consolidé de l'issue ajouté à l'entrée Z-2024-003. |
| 0.3 | 23/04/2026 | Micha MEGRET | Suite à l'instruction du sous-dossier `2024/cas-particuliers/` : **3 incertitudes passées au statut « Résolu »** : Z-2024-004 (véhicule importé d'occasion — méthode d'homologation effective confirmée), Z-2024-005 (frontière fiscale M1/N1 — algorithme `qualifier_type_fiscal` documenté), Z-2024-006 (bascule automatique sur PA si donnée CO₂ manquante — règle juridique explicite + UX). **2 incertitudes consolidées et maintenues ouvertes** avec mention explicite de leur consolidation en règle d'implémentation Floty : Z-2024-001 (indisponibilités longues hors fourrière — validation EC nécessaire), Z-2024-002 (qualification LLD/LCD — validation EC indispensable, priorité haute). Détail consolidé de la clôture ajouté à chacune des 3 entrées résolues. |
| 0.4 | 23/04/2026 | Micha MEGRET | **Z-2024-002 passée au statut « Résolu »** suite à clarification directe avec Renaud sur la nature exacte du montage contractuel. La lecture retenue n'est plus « LLD par défaut » mais **« LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice) »**, conforme à L. 421-129 + L. 421-141 + BOFiP § 180 et à la pratique de Renaud (sans redressement fiscal). L'incertitude est résolue par clarification du modèle, non par validation expert-comptable. Bilan global 2024 : **5 résolues, 5 ouvertes** (3 moyennes, 2 basses — plus aucune priorité haute). |
