# Recherches — Abattements applicables aux taxes annuelles CO₂ et polluants — Exercice 2024

> **Statut** : Version 0.1 — recherche initiale
> **Auteur** : Micha MEGRET (prestataire)
> **Date de rédaction** : 23 avril 2026
> **Périmètre matériel** : abattements applicables aux taxes annuelles d'affectation des véhicules de tourisme (Prélèvements 7 et 8 de la cartographie : taxe CO₂ et taxe polluants), exercice 2024.
> **Hors périmètre de cette recherche** : exonérations (déjà instruites dans `2024/exonerations/`), barèmes de tarification (instruits dans `2024/taxe-co2/` et `2024/taxe-polluants/`), cas particuliers de qualification véhicule (renvoyés à `2024/cas-particuliers/`), coefficient pondérateur frais kilométriques (mentionné pour mémoire — hors périmètre Floty V1), années 2025 et 2026.

---

## 1. Sources consultées

Cf. fichier `sources.md` pour la bibliographie complète. Synthèse :

- **S1** — Légifrance — CIBS, Section 3 « Taxes sur l'affectation des véhicules à des fins économiques » (articles L. 421-93 à L. 421-167), version applicable au 31 décembre 2023 (modifiée par la loi n° 2023-1322 du 29 décembre 2023, art. 97). En particulier : article **L. 421-111** (minoration 15 000 €) et article **L. 421-125** (exonération hybride conditionnelle, **dans sa version 2022-2024** — qui ne contient pas d'abattement E85). Comparé à la version révisée applicable **à compter du 1er janvier 2025** (issue de la loi de finances pour 2025), qui transforme la nature même de la mesure (exonération → abattement E85).
- **S2** — BOFiP `BOI-AIS-MOB-10-30-20-20240710` — section consacrée aux taxes d'affectation des véhicules de tourisme, version applicable à l'exercice 2024 — particulièrement la sous-section sur l'exonération hybride § L. 421-125 (§§ 130 à 150) et la sous-section sur la minoration de 15 000 € (§§ 30 à 50).
- **S3** — BOFiP `BOI-AIS-MOB-10-30-10-20250528` — dispositions communes aux taxes d'affectation, transversales aux deux taxes — particulièrement la mécanique de prorata d'usage.
- **S4** — BOFiP `BOI-AIS-MOB-10-30-20` — version postérieure à 2024 (publication consultée pour vérifier la date d'apparition explicite de l'abattement E85 dans la doctrine). La version publiée à compter du 28 mai 2025 contient au § 240 la mention textuelle suivante : « **À compter du 1er janvier 2025**, les véhicules recourant exclusivement ou partiellement au superéthanol E85 bénéficient d'un abattement sur leurs émissions de CO₂ ou leur puissance administrative, sauf lorsque ces émissions ou cette puissance dépassent respectivement 250 g/km ou douze chevaux administratifs (CIBS, art. L. 421-125). Cet abattement, à hauteur de 40 % des émissions de CO₂ ou de deux chevaux administratifs, s'applique dans les mêmes conditions que pour le malus CO₂ (II-C-2 § 160 du BOI-AIS-MOB-10-20-40). »
- **S5** — Notice officielle DGFiP n° 2857-FC-NOT-SD (Cerfa n° 52374#03, édition décembre 2024) — déclaration de la taxe CO₂ pour 2024. Vérification que **la notice 2024 ne mentionne aucun abattement E85** (elle traite l'E85 uniquement dans le cadre de l'exonération hybride § L. 421-125, conjointement avec une autre source d'énergie).
- **S6** — Notice officielle DGFiP n° 2858-FC-NOT-SD (Cerfa n° 52375#03, édition décembre 2024) — déclaration de la taxe polluants pour 2024. Vérification qu'**aucun abattement** n'est prévu pour la taxe polluants en 2024 (le tarif est forfaitaire par catégorie, sans modulation pour E85 ni pour aucun autre paramètre).
- **S7** — Loi de finances pour 2024 — Loi n° 2023-1322 du 29 décembre 2023 — art. 97 (rédaction des articles CIBS L. 421-119 à L. 421-144 applicable à compter du 01/01/2024) et art. 100 (suppression programmée de l'exonération hybride § L. 421-125 au 01/01/2025).
- **S8** — Loi de finances pour 2025 — Loi n° 2025-127 du 14 février 2025 — pour vérification de la disposition créant l'abattement E85 à compter du 1er janvier 2025 (refonte de l'article L. 421-125 du CIBS).
- **S9** — `cartographie-taxes.md` § 7 (Prélèvement 7) — mention initialement présente : « 2024 : abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) », contradictoire avec la lecture des sources primaires et qui a déclenché la seconde passe de recherche (cf. § 5.7 et § 8.1). *Cette mention a été corrigée dans la cartographie en v0.2 (23/04/2026) suite à la conclusion de la présente mission ; voir historique de `cartographie-taxes.md`.*
- **S10** — `cahier_des_charges.md` § 5.6 — mention « Abattements pour véhicules E85 (superéthanol) : à compter du 1er janvier 2025, abattement de 40 % sur les émissions de CO₂ OU de 2 CV sur la puissance administrative, sauf si les émissions dépassent 250 g/km ou si la puissance dépasse 12 CV ». Cette formulation est cohérente avec la lecture des sources primaires (date d'entrée en vigueur 01/01/2025).
- **S11** — PwC Avocats — alerte « Aménagement de la fiscalité applicable aux véhicules » (LF 2024) — référencée pour cohérence (ne mentionne aucun abattement E85 pour 2024).
- **S12** — FNA — fiche « La taxe annuelle sur les véhicules de tourisme 2024 (ex-TVS) » — référencée pour cohérence (ne mentionne aucun abattement E85 pour 2024).
- **S13** — Drive to Business — fiche « Bonus et malus écologique » — référencée pour la documentation parallèle de l'abattement E85 à compter du 01/01/2025 et son absence en 2024.
- **S14** — Compta-Online — fiche « Taxes sur les véhicules » — référencée pour cohérence avec l'absence d'abattement isolé en 2024.
- **S15** — Legifiscal — fiche « Taxes annuelles sur les véhicules de tourisme » — référencée pour cohérence avec l'absence d'abattement isolé en 2024.

---

## 2. Cadrage méthodologique : qu'est-ce qu'un « abattement » ?

### 2.1 Définition opératoire dans le périmètre Floty

Conformément à la méthodologie projet (§ 6.6 — caractéristiques véhicule consommées et produites par une règle, et Annexe C.2 — modèle de fiche d'abattement), un **abattement** est une règle qui **modifie une caractéristique d'entrée du véhicule** consommée par une règle de tarification ultérieure.

Exemple type : un abattement de 40 % sur les émissions de CO₂ ne supprime pas la taxe ; il **réduit la valeur** de la caractéristique « taux d'émission CO₂ » qui est ensuite passée à la règle de tarification CO₂ (laquelle continue de calculer un tarif à partir de la valeur réduite). Le résultat fiscal n'est généralement pas zéro : la taxe est due, simplement calculée sur une assiette diminuée.

### 2.2 Distinction sémantique avec les notions voisines

| Notion | Effet sur le calcul | Exemple type 2024 dans le périmètre Floty |
|---|---|---|
| **Tarification** | Calcule un tarif à partir des caractéristiques du véhicule | Barème WLTP de la taxe CO₂ (CIBS art. L. 421-120) — applicable à tous les véhicules WLTP non exonérés |
| **Abattement** | Modifie une caractéristique d'entrée d'une règle de tarification ; le tarif est ensuite recalculé sur la valeur modifiée | **Aucun abattement isolé en 2024** dans le périmètre Floty (voir § 3 et § 4 ci-dessous) |
| **Exonération** | Annule purement l'application des règles de tarification ; le tarif est forcé à 0 € indépendamment des caractéristiques techniques | Exonération électrique/hydrogène (CIBS art. L. 421-124) ; exonération hybride conditionnelle 2024 (CIBS art. L. 421-125) ; exonération loueur (CIBS art. L. 421-128 / L. 421-140) — toutes documentées dans `2024/exonerations/` |
| **Effet du barème** (catégorie tarifée à 0 €) | Le barème lui-même attribue un tarif de 0 € à une catégorie sans qu'il s'agisse d'une exonération technique | Catégorie E (électrique/hydrogène) de la taxe polluants — tarif 0 € au titre du barème CIBS art. L. 421-135, sans procédure d'exonération à demander |
| **Minoration** | Réduit forfaitairement le tarif calculé d'un montant fixe (en € ou en CV) | Minoration 15 000 € sur les frais kilométriques (CIBS art. L. 421-111) — voir § 4 — **hors périmètre Floty V1** |
| **Coefficient pondérateur** | Modifie le tarif final en lui appliquant un pourcentage selon un critère externe (kilométrage remboursé) | Coefficient pondérateur frais kilométriques (CIBS art. L. 421-110) — **hors périmètre Floty V1** |

### 2.3 Importance de la distinction abattement vs exonération en 2024

Cette distinction est centrale pour 2024 parce que **l'article L. 421-125 du CIBS change de nature au 1er janvier 2025** :

- **Version 2022 → 2024** : L. 421-125 décrit une **exonération conditionnelle** (la taxe est annulée si les conditions sont remplies, sinon le plein tarif est dû). Texte intégral cité dans `2024/exonerations/recherches.md` § 4.3.
- **Version à compter du 01/01/2025** : L. 421-125 décrit un **abattement** (la taxe est calculée sur une assiette réduite de 40 % CO₂ ou 2 CV PA, avec plafonds de 250 g/km et 12 CV).

Dans les deux cas, le numéro d'article et la matière (E85 et hybrides) restent les mêmes. Mais la **mécanique fiscale change radicalement** :

- En 2024, un véhicule éligible à l'exonération paie **0 €** ; un véhicule non éligible paie **le plein tarif**.
- À partir de 2025, un véhicule éligible à l'abattement paie **un tarif réduit** ; un véhicule non éligible paie **le plein tarif**.

Cette bascule explique l'apparente confusion entre cahier des charges, cartographie et notices — qu'il faut lever ici.

---

## 3. Recherche d'abattements applicables en 2024 — taxe CO₂

### 3.1 Démarche d'inventaire exhaustif

Pour chercher d'éventuels abattements applicables à la taxe annuelle CO₂ en 2024, nous procédons en trois temps :

1. **Lecture exhaustive de la sous-section « Tarifs » du Paragraphe 3** (CIBS art. L. 421-119 à L. 421-122) — pour identifier toute mécanique d'abattement intégrée au tarif.
2. **Lecture exhaustive de la sous-section « Exonérations » du Paragraphe 3** (CIBS art. L. 421-123 à L. 421-132) — pour vérifier qu'aucune disposition « exonération » ne dissimule en réalité un mécanisme d'abattement.
3. **Lecture des dispositions communes** (CIBS art. L. 421-105 à L. 421-118) — pour identifier toute mécanique transversale (minoration, coefficient pondérateur, abattement spécifique).
4. **Lecture de la doctrine BOFiP** (BOI-AIS-MOB-10-30-10 et BOI-AIS-MOB-10-30-20 dans leurs versions 2024) et des **notices DGFiP** (2857-FC-NOT-SD édition décembre 2024 et 2858-FC-NOT-SD édition décembre 2024) — pour confirmer qu'aucune disposition citée par la doctrine ne soit oubliée.

### 3.2 Inventaire des dispositions « tarifs » (CIBS art. L. 421-119 à L. 421-122) — 2024

| Article | Objet | Nature | Abattement ? |
|---|---|---|---|
| L. 421-119 | Article chapeau des tarifs CO₂ | Tarification | Non |
| L. 421-120 | Barème WLTP | Tarification progressive par tranches | Non |
| L. 421-121 | Barème NEDC | Tarification progressive par tranches | Non |
| L. 421-122 | Barème puissance administrative | Tarification progressive par tranches | Non |

**Conclusion partielle** : aucun abattement n'est intégré au tarif lui-même. Les barèmes WLTP, NEDC et PA appliquent leur formule directement aux caractéristiques d'entrée du véhicule, sans modulation préalable.

### 3.3 Inventaire des dispositions « exonérations » (CIBS art. L. 421-123 à L. 421-132) — 2024

| Article | Objet | Nature | Abattement déguisé ? |
|---|---|---|---|
| L. 421-123 | Handicap | Exonération totale | Non |
| L. 421-124 | Électrique/hydrogène | Exonération totale | Non |
| **L. 421-125** | **Hybride conditionnel** (combinaison de sources d'énergie incluant l'E85, sous seuils CO₂/PA) | **Exonération totale conditionnelle** (version 2022-2024) | **Non — c'est une exonération, pas un abattement** (voir § 5 ci-dessous) |
| L. 421-126 | Organismes intérêt général | Exonération totale | Non |
| L. 421-127 | Entreprises individuelles | Exonération totale | Non |
| L. 421-128 | Loueur | Exonération totale | Non |
| L. 421-129 | Location courte durée | Exonération totale | Non |
| L. 421-130 | Transport public personnes | Exonération totale | Non |
| L. 421-131 | Activités agricoles/forestières | Exonération totale | Non |
| L. 421-132 | Enseignement conduite + compétitions | Exonération totale | Non |

**Conclusion partielle** : aucune disposition d'exonération ne dissimule un abattement. L'article L. 421-125 — qui cite explicitement l'E85 dans ses combinaisons éligibles — fonctionne en 2024 comme une exonération conditionnelle (taxe = 0 € si conditions remplies), pas comme un abattement (taxe sur assiette réduite).

### 3.4 Inventaire des dispositions communes (CIBS art. L. 421-105 à L. 421-118) — 2024

| Article | Objet | Nature | Abattement ? |
|---|---|---|---|
| L. 421-105 à L. 421-109 | Définitions, fait générateur, prorata | Mécanique générale | Non |
| L. 421-110 | Coefficient pondérateur frais kilométriques | Coefficient pondérateur | Non — coefficient pondérateur, pas abattement |
| **L. 421-111** | **Minoration de 15 000 € sur les véhicules salariés/dirigeants donnant lieu à prise en charge de frais kilométriques** | **Minoration forfaitaire** | **Mention au § 4 — minoration au sens large, non « abattement » au sens technique de la méthodologie. Hors périmètre Floty V1.** |

**Conclusion partielle** : la seule disposition de modulation du tarif (hors barème) applicable aux véhicules de tourisme en 2024 est la **minoration de 15 000 €** prévue par l'article L. 421-111. Elle ne modifie pas une caractéristique du véhicule (au sens de la méthodologie § 6.6) ; elle déduit forfaitairement un montant. Elle est traitée pour mémoire au § 4.

### 3.5 Inventaire dans le BOFiP `BOI-AIS-MOB-10-30-20-20240710`

Lecture intégrale de la version applicable à l'exercice 2024 (mise à jour du 10/07/2024). Recherche systématique du terme « abattement » dans le commentaire des articles L. 421-93 à L. 421-132.

**Résultat** : la seule occurrence du terme « abattement » dans la version 2024 du BOFiP concerne **l'abattement de 15 000 € sur les véhicules salariés/dirigeants donnant lieu à prise en charge de frais kilométriques** (BOI-AIS-MOB-10-30-20 §§ 30-50 — qui commentent CIBS art. L. 421-111). **Aucune mention d'un abattement E85 ni d'aucun autre abattement** pour 2024.

### 3.6 Inventaire dans la notice DGFiP n° 2857-FC-NOT-SD édition décembre 2024

Lecture intégrale de la notice (8 pages). Le seul abattement explicitement identifié est l'**abattement de 15 000 €** mentionné en partie II.3 et en partie IV ligne O — qui s'applique au montant cumulé dû au titre des véhicules salariés/dirigeants (cf. `2024/taxe-co2/recherches.md` § 3.9).

**Aucune mention d'un abattement E85** dans la notice 2024 (ni en partie I.3 « Exonérations », ni en partie II « Mécanique de calcul », ni en partie IV « Tableaux de calcul »).

### 3.7 Synthèse — Aucun abattement isolé applicable à la taxe CO₂ en 2024

Au terme de cet inventaire, **aucun abattement isolé** (au sens « modification d'une caractéristique d'entrée d'une règle de tarification ») **n'est applicable à la taxe annuelle CO₂ en 2024**. Le seul mécanisme de modulation du tarif (hors barème) est la **minoration de 15 000 €** prévue par CIBS art. L. 421-111, qui est :
- une minoration forfaitaire (pas un abattement au sens technique),
- réservée aux véhicules salariés/dirigeants donnant lieu à prise en charge de frais kilométriques,
- **hors périmètre Floty V1** par construction.

L'E85 entre **uniquement** dans le périmètre des **exonérations** en 2024, par le biais de l'article L. 421-125 (hybride conditionnel), à condition d'être combiné à une autre source d'énergie (électrique, hydrogène, gaz naturel ou GPL) — voir § 5.

---

## 4. Cas de la minoration de 15 000 € (CIBS art. L. 421-111) — pour mémoire

### 4.1 Texte de l'article CIBS L. 421-111

L'article L. 421-111 du CIBS, dans sa version applicable au 31/12/2023, énonce :

> « Le montant cumulé des taxes mentionnées au 1° de l'article L. 421-94 dues au titre des véhicules dont la prise en charge est mentionnée au 2° de l'article L. 421-95 fait l'objet d'un abattement de 15 000 €. »
> — S1, CIBS art. L. 421-111, version applicable au 31/12/2023, consulté le 23/04/2026

### 4.2 Doctrine BOFiP

Le BOFiP `BOI-AIS-MOB-10-30-20-20240710` §§ 30 à 50 confirme cette mécanique : l'« abattement » de 15 000 € s'applique au **montant cumulé** des deux taxes annuelles (CO₂ + polluants) **dues au titre des véhicules salariés/dirigeants donnant lieu à prise en charge de frais kilométriques**. Il s'agit d'une **déduction forfaitaire en euros** opérée sur le montant à payer, et non d'une modification d'une caractéristique d'entrée du véhicule (taux CO₂, puissance administrative, etc.).

Plus précisément :
- Cette « minoration » s'applique **après** le calcul des deux taxes, sur leur somme.
- Elle ne s'applique **qu'aux véhicules « salariés/dirigeants »** (au sens du 2° de CIBS art. L. 421-95 : véhicules possédés ou loués par une personne physique dont l'entreprise prend en charge totalement ou partiellement les frais d'utilisation).
- Elle est limitée par véhicule de l'année (15 000 € par redevable, et non par véhicule).

### 4.3 Pourquoi le terme « abattement » figure dans la lettre du CIBS

Le législateur emploie le mot « abattement » à l'article L. 421-111, mais la **nature** de la mesure relève d'une **minoration forfaitaire** (déduction de 15 000 € sur un montant total). Au regard de la classification opératoire de la méthodologie projet (§ 2.2 du présent document), cette mesure ne correspond pas à un **abattement au sens fiscal-technique** (« modification d'une caractéristique d'entrée d'une règle de tarification ») — elle correspond à une **minoration** (« déduction forfaitaire d'un montant »).

Cette ambiguïté terminologique du CIBS est documentée ici sans ambiguïté : peu importe le nom employé par le législateur, la mécanique est celle d'une minoration sur le total à payer.

### 4.4 Hors périmètre Floty V1

Cette minoration **ne concerne pas le modèle Floty V1**. Justification :

- Floty V1 traite des véhicules **détenus par la société de location de Renaud** et **mis à disposition** d'entreprises utilisatrices dans le cadre d'un contrat de location (cf. cahier des charges § 1.1 et `2024/taxe-co2/recherches.md` § 5.7). Ce ne sont **pas** des véhicules « salariés/dirigeants donnant lieu à prise en charge de frais kilométriques » au sens du 2° de CIBS art. L. 421-95.
- La mécanique « coefficient pondérateur frais kilométriques » (CIBS art. L. 421-110) — qui est l'autre face de la même médaille — est elle aussi explicitement hors périmètre Floty V1 (cf. `2024/taxe-co2/recherches.md` § 3.9).

Pour mémoire, dans Floty V1 :
- aucun véhicule n'est classé en catégorie « salarié/dirigeant avec frais kilométriques » ;
- la minoration 15 000 € est **modélisée** dans la base (par exhaustivité du modèle de données, conformément à la méthodologie § 3.2 et au principe Décision 1 de `2024/exonerations/decisions.md`) mais **inactive par défaut** ;
- son activation nécessitera une seconde validation expert-comptable et une évolution UX (saisie du kilométrage remboursé par véhicule), à programmer en V2 si une entreprise utilisatrice atypique entre dans le périmètre.

---

## 5. Cas E85 en 2024 — clôture de l'incertitude Z-2024-003

### 5.1 Énoncé du problème

L'incertitude `Z-2024-003 — Abattement E85 en 2024` (`2024/taxe-co2/incertitudes.md`) constatait une **divergence apparente** entre trois lectures :

1. **Cahier des charges Floty § 5.6** : « Abattements pour véhicules E85 (superéthanol) : à compter du **1er janvier 2025**, abattement de 40 % sur les émissions de CO₂ OU de 2 CV sur la puissance administrative, sauf si les émissions dépassent 250 g/km ou si la puissance dépasse 12 CV ».
2. **Cartographie phase 0 — `cartographie-taxes.md` § 7 (Prélèvement 7)** : « 2024 : seuil d'exonération CO₂ à 15 g/km ; barème WLTP progressif par tranches. Exonération totale : véhicules exclusivement électriques, hydrogène, ou hybrides électrique/hydrogène. **Abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km)** ».
3. **Notice DGFiP S5 (n° 2857-FC-NOT-SD édition décembre 2024)** : aucune mention d'abattement E85 isolé pour 2024 (l'E85 est traité uniquement comme une source d'énergie éligible au mécanisme d'exonération hybride § L. 421-125, en combinaison avec une autre source d'énergie).

L'incertitude `Z-2024-003` était notée « Ouvert » dans `2024/taxe-co2/incertitudes.md` avec hypothèse de travail provisoire « pas d'abattement E85 isolé en 2024 », à clore par la présente mission.

### 5.2 Chronologie de l'article CIBS L. 421-125

L'article L. 421-125 du CIBS connaît **deux versions distinctes** dans la période 2022-2026 :

**Version (a) applicable à l'exercice 2024** (issue de l'article 97 de la loi n° 2023-1322 du 29 décembre 2023, dans sa rédaction au 31/12/2023) — **EXONÉRATION** :

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules dont la source d'énergie est :
> 1° L'électricité ou l'hydrogène et l'une des sources d'énergie suivantes : le gaz naturel, le gaz de pétrole liquéfié, l'essence ou le superéthanol E85 ;
> 2° Le gaz naturel ou le gaz de pétrole liquéfié et l'une des sources d'énergie suivantes : l'essence ou le superéthanol E85.
>
> Le bénéfice de l'exonération est subordonné à la condition que les émissions de dioxyde de carbone du véhicule, déterminées en application de la méthode mentionnée à l'article L. 421-6, n'excèdent pas 60 grammes par kilomètre.
>
> Pour les véhicules dont les émissions de dioxyde de carbone n'ont pas été déterminées en application de la méthode mentionnée à l'article L. 421-6, l'exonération s'applique aux véhicules ayant fait l'objet d'une réception européenne dont les émissions de dioxyde de carbone n'excèdent pas 50 grammes par kilomètre, ou ne relevant pas de cette catégorie et dont la puissance administrative n'excède pas 3 chevaux administratifs.
>
> Pour les véhicules dont l'ancienneté, à compter de la date de leur première immatriculation, n'excède pas trois ans, les seuils prévus aux deux alinéas précédents sont portés respectivement à 120, 100 et 6. »
> — S1, CIBS art. L. 421-125 (version 2024), consulté le 23/04/2026 — repris textuellement de `2024/exonerations/recherches.md` § 4.3

**Version (b) applicable à compter du 1er janvier 2025** (issue de la loi de finances pour 2025 — loi n° 2025-127 du 14 février 2025) — **ABATTEMENT** :

Cette version révise l'article L. 421-125 du CIBS pour transformer le dispositif d'exonération conditionnelle (qui annulait la taxe pour certaines combinaisons hybrides incluant l'E85) en un dispositif d'**abattement** (qui réduit l'assiette de calcul pour les véhicules carburant à l'E85, exclusivement ou partiellement). Le BOFiP postérieur à 2024 (S4) confirme cette nouvelle nature au § 240 (texte cité au § 1 de la présente recherche).

**Conséquence** : l'**article L. 421-125 change de nature** entre 2024 et 2025. L'exonération hybride disparaît au 31/12/2024 (cf. `2024/exonerations/recherches.md` § 4.3) ; l'abattement E85 apparaît au 01/01/2025.

### 5.3 Vérification croisée — la notice DGFiP 2024 ne mentionne aucun abattement E85

Lecture intégrale de la notice n° 2857-FC-NOT-SD (édition décembre 2024) consacrée à la déclaration de la taxe CO₂ pour 2024 :

- **Partie I.3 — Liste des exonérations** : énumération des articles CIBS L. 421-123 à L. 421-132. L'E85 figure uniquement dans le contenu de l'article L. 421-125 (hybride conditionnel), comme l'une des sources d'énergie pouvant entrer dans les combinaisons éligibles. **Aucune mention d'un abattement E85 isolé**.
- **Partie II — Mécanique de calcul** : prorata journalier ou trimestriel ; coefficient pondérateur frais kilométriques ; minoration 15 000 €. **Aucun abattement E85**.
- **Partie IV — Tableaux de calcul** : barèmes WLTP, NEDC, PA, et lignes de calcul du tarif et du montant à payer. **Aucune ligne dédiée à un abattement E85**.

La notice S5 confirme donc, en sources primaires, que **l'abattement E85 n'existe pas en 2024**.

### 5.4 Vérification croisée — la doctrine BOFiP applicable à 2024 ne mentionne aucun abattement E85

Lecture intégrale du BOFiP `BOI-AIS-MOB-10-30-20-20240710` (S2) — version applicable à l'exercice 2024 :

- **Section II « Exonérations »** (§§ 90 à 200) : couvre exhaustivement les articles CIBS L. 421-123 à L. 421-132. **Aucun abattement E85** n'y est mentionné. L'E85 figure uniquement dans la sous-section consacrée à l'article L. 421-125 (§§ 130 à 150), au titre des combinaisons de sources d'énergie éligibles à l'exonération hybride conditionnelle.
- **Section III « Tarifs »** (§§ 210 à 230) : barèmes WLTP, NEDC, PA. **Aucun abattement E85**.
- **Section IV « Taxe polluants »** (§§ 260 à 290) : tarifs forfaitaires par catégorie. **Aucun abattement E85**.

Le BOFiP `BOI-AIS-MOB-10-30-10-20250528` (S3) — dispositions communes — ne mentionne pas non plus d'abattement E85.

### 5.5 Vérification croisée — la doctrine BOFiP postérieure à 2024 confirme l'apparition de l'abattement E85 « à compter du 1er janvier 2025 »

Le BOFiP `BOI-AIS-MOB-10-30-20` (S4) — dans sa version publiée à compter du 28 mai 2025 (donc postérieure à l'exercice 2024) — précise au § 240 :

> « **À compter du 1er janvier 2025**, les véhicules recourant exclusivement ou partiellement au superéthanol E85 bénéficient d'un abattement sur leurs émissions de CO₂ ou leur puissance administrative, sauf lorsque ces émissions ou cette puissance dépassent respectivement 250 g/km ou douze chevaux administratifs (CIBS, art. L. 421-125). Cet abattement, à hauteur de 40 % des émissions de CO₂ ou de deux chevaux administratifs, s'applique dans les mêmes conditions que pour le malus CO₂ (II-C-2 § 160 du BOI-AIS-MOB-10-20-40). »
> — S4, BOI-AIS-MOB-10-30-20 (version postérieure à 2024), § 240, consulté le 23/04/2026

Cette formulation **date sans ambiguïté l'apparition de l'abattement E85 au 1er janvier 2025**. Elle est en concordance avec :
- la version (b) de l'article L. 421-125 issue de la loi de finances pour 2025 (S8) ;
- la mention du cahier des charges Floty § 5.6 (S10) qui dit « à compter du 1er janvier 2025 ».

### 5.6 Vérification croisée — sources tertiaires

- **PwC Avocats** (S11) — alerte LF 2024 : ne mentionne aucun abattement E85 pour 2024. La doctrine PwC applicable à LF 2024 traite l'E85 uniquement dans le cadre de l'exonération hybride § L. 421-125.
- **FNA** (S12) — fiche taxe annuelle véhicules de tourisme 2024 : ne mentionne aucun abattement E85 pour 2024.
- **Drive to Business** (S13) — fiche bonus/malus écologique : référence l'apparition de l'abattement E85 pour les véhicules d'entreprise « à compter de 2025 », conformément à la chronologie législative.
- **Compta-Online** (S14) et **Legifiscal** (S15) : ne mentionnent aucun abattement E85 pour 2024.

**Triangulation primaire + secondaire + tertiaire convergente** : aucune source ne confirme l'existence d'un abattement E85 isolé en 2024.

### 5.7 Origine de l'erreur dans la cartographie phase 0

La cartographie `cartographie-taxes.md` § 7 contenait initialement la mention erronée « 2024 : abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) ». Cette mention était en réalité une **anticipation de la disposition applicable à compter du 01/01/2025** qui avait été par erreur attribuée à l'exercice 2024. C'est cette erreur qui a généré l'incertitude `Z-2024-003`.

L'erreur s'explique par la confusion de deux dispositifs distincts portant le même numéro d'article (L. 421-125), qui couvrent dans les deux cas l'E85, mais avec deux mécaniques fondamentalement différentes (exonération en 2024 ; abattement à compter de 2025). Cette confusion est fréquente dans les sources tertiaires moins rigoureuses ; elle avait échappé au filtre de la cartographie initiale. Elle est tranchée et close par la présente recherche, et la cartographie a été corrigée en v0.2 (23/04/2026 — voir historique de `cartographie-taxes.md`).

### 5.8 Conclusion ferme — Z-2024-003

**Pour l'exercice fiscal 2024, l'abattement E85 isolé n'est PAS applicable.**

Pour les véhicules carburant à l'E85, la situation fiscale en 2024 est la suivante :
- Si la source d'énergie du véhicule est **uniquement** l'E85 (mono-carburant E85) : ni exonération ni abattement → **plein tarif** selon le barème WLTP, NEDC ou PA applicable.
- Si la source d'énergie du véhicule **combine** l'E85 avec une autre source éligible (électrique, hydrogène, gaz naturel ou GPL — combinaisons (a) ou (b) de L. 421-125) : éligible au mécanisme **d'exonération hybride conditionnelle** § L. 421-125 (déjà documenté dans `2024/exonerations/recherches.md` § 4.3 et `2024/exonerations/decisions.md` Décision 4), sous réserve de respecter les seuils CO₂/PA (60/50/3 ou 120/100/6 selon ancienneté).

**Statut Z-2024-003 : Résolu** (à passer en « Résolu » dans `2024/taxe-co2/incertitudes.md` et l'index global ; voir `decisions.md` Décision 2 et `incertitudes.md` du présent sous-dossier).

---

## 6. Recherche d'abattements applicables en 2024 — taxe polluants

### 6.1 Démarche d'inventaire

Symétriquement à la recherche pour la taxe CO₂ (§ 3 ci-dessus), nous procédons en :

1. **Lecture de la sous-section « Tarifs »** de la taxe polluants (CIBS art. L. 421-133 à L. 421-135) — barème forfaitaire par catégorie (E, 1, véhicules les plus polluants).
2. **Lecture de la sous-section « Exonérations »** (CIBS art. L. 421-136 à L. 421-144) — déjà couverte dans `2024/exonerations/`.
3. **Lecture de la doctrine BOFiP** (BOI-AIS-MOB-10-30-20 §§ 260 à 290 dans sa version 2024).
4. **Lecture de la notice DGFiP n° 2858-FC-NOT-SD édition décembre 2024**.

### 6.2 Inventaire — taxe polluants 2024

| Disposition | Nature | Abattement ? |
|---|---|---|
| CIBS art. L. 421-134 (catégories E, 1, véhicules les plus polluants) | Définition des catégories d'émissions | Non |
| CIBS art. L. 421-135 (tarifs : 0 €, 100 €, 500 €) | Barème forfaitaire par catégorie | Non — c'est un barème de tarification, sans modulation |
| CIBS art. L. 421-110 (coefficient pondérateur frais kilométriques) | Coefficient pondérateur (commun aux deux taxes) | Non — coefficient pondérateur, pas abattement |
| CIBS art. L. 421-111 (minoration 15 000 €) | Minoration forfaitaire (commune aux deux taxes) | Voir § 4 ci-dessus — hors périmètre Floty V1 |
| CIBS art. L. 421-136 à L. 421-144 (exonérations) | Exonérations | Non — déjà couvertes dans `2024/exonerations/` |

### 6.3 Inventaire dans la notice DGFiP n° 2858-FC-NOT-SD édition décembre 2024

Lecture intégrale (S6). Le seul mécanisme de modulation du tarif (hors barème) est l'**abattement de 15 000 €** mentionné en partie II.3 (commun aux deux taxes annuelles), comme déjà documenté en § 4. **Aucun autre abattement spécifique à la taxe polluants** n'est mentionné.

La taxe polluants 2024 fonctionne strictement par tarif forfaitaire selon catégorie : il n'y a **aucune mécanique de modulation** propre à la taxe polluants. Les véhicules à l'E85 ne bénéficient d'**aucun abattement** sur la taxe polluants (ni en 2024, ni à compter de 2025 — l'abattement E85 introduit à compter du 01/01/2025 par la révision de L. 421-125 concerne **uniquement la taxe CO₂**).

### 6.4 Conclusion pour la taxe polluants

**Aucun abattement isolé n'est applicable à la taxe annuelle polluants en 2024.** La seule disposition de modulation hors barème est la minoration 15 000 € de CIBS art. L. 421-111, commune aux deux taxes annuelles, hors périmètre Floty V1 (voir § 4).

---

## 7. Caractéristiques véhicule consommées et modifiées par chaque mécanisme identifié

Conformément à l'Annexe C.2 de la méthodologie projet, nous documentons pour chaque mécanisme identifié les **caractéristiques véhicule consommées** (lues en entrée) et **modifiées** (produites en sortie pour les règles de tarification ultérieures).

### 7.1 Aucun abattement isolé en 2024

Pour 2024 :
- **Aucun abattement isolé** ne consomme ni ne modifie de caractéristique véhicule dans le périmètre Floty.
- L'E85, pour 2024, est traité **uniquement** comme une source d'énergie éligible à l'exonération hybride conditionnelle § L. 421-125 (cf. `2024/exonerations/decisions.md` Décision 4) — la mécanique consommée est celle de l'exonération, pas d'un abattement.

### 7.2 Minoration 15 000 € (L. 421-111) — pour mémoire

Si, à terme, la minoration de 15 000 € devait être activée dans Floty (V2 ou V3), sa fiche-règle au format Annexe C.2 serait :

- **Caractéristiques véhicule consommées par la règle** :
  - Statut « véhicule salarié/dirigeant donnant lieu à prise en charge de frais kilométriques » (booléen) — au sens du 2° de CIBS art. L. 421-95
- **Caractéristiques véhicule produites ou modifiées** : aucune (la minoration s'applique sur le **montant cumulé à payer**, pas sur une caractéristique véhicule)
- **Mécanique** : déduction forfaitaire de 15 000 € sur la somme **(taxe CO₂ + taxe polluants)** due au titre de l'ensemble des véhicules salariés/dirigeants du redevable. Plafonnée par redevable et non par véhicule.

Cette structure n'est pas implémentée comme règle active en V1 (comme la mécanique du coefficient pondérateur frais kilométriques, elle est documentée pour exhaustivité mais désactivée).

### 7.3 Abattement E85 (L. 421-125 version postérieure au 31/12/2024) — pour mémoire

Cet abattement, qui apparaît à compter du 1er janvier 2025, sera instruit dans `2025/abattements/`. Sa fiche-règle prévisionnelle au format Annexe C.2 (à confirmer en 2025) inclura :

- **Caractéristiques véhicule consommées** :
  - Type de carburant (doit inclure E85, exclusivement ou partiellement)
  - Taux d'émission de CO₂ WLTP (g/km) — pour vérifier le plafond ≤ 250
  - Puissance administrative (CV) — pour vérifier le plafond ≤ 12
- **Caractéristiques véhicule produites ou modifiées** :
  - Modifie : taux d'émission de CO₂ utilisé en entrée des règles de tarification CO₂ → multiplié par 0,60 (équivalent à un abattement de 40 %)
  - Et/ou : puissance administrative utilisée en entrée → diminuée de 2 CV (équivalent à un abattement de 2 CV PA)

**Hors périmètre de la présente mission** : à instruire dans `2025/abattements/`.

---

## 8. Divergences ou ambiguïtés rencontrées

### 8.1 Divergence #1 — Cartographie phase 0 vs sources primaires (« abattement E85 en 2024 »)

**Constat** : la cartographie `cartographie-taxes.md` § 7 mentionne, sous Prélèvement 7 « Particularités 2024 », un « abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) ». Cette affirmation est en contradiction avec :

- la lettre de l'article L. 421-125 du CIBS, version applicable au 31/12/2023 (S1), qui décrit une **exonération** et non un abattement ;
- la doctrine BOFiP `BOI-AIS-MOB-10-30-20-20240710` (S2), qui ne mentionne aucun abattement E85 ;
- la notice DGFiP n° 2857-FC-NOT-SD édition décembre 2024 (S5), qui ne mentionne aucun abattement E85 ;
- la formulation du cahier des charges Floty § 5.6 (S10), qui date l'abattement E85 « à compter du 1er janvier 2025 » ;
- la doctrine BOFiP postérieure à 2024 (S4), qui confirme « à compter du 1er janvier 2025 ».

**Interprétation** : la cartographie phase 0 a anticipé par erreur sur l'exercice 2024 une disposition qui ne s'applique en réalité qu'à compter du 01/01/2025. L'erreur résulte de la confusion entre deux versions distinctes du même article L. 421-125 (version 2022-2024 = exonération conditionnelle ; version à compter du 01/01/2025 = abattement E85).

**Décision** : la cartographie a été corrigée en v0.2 (23/04/2026) en cohérence avec la conclusion de la présente recherche — la mention erronée « abattement E85 en 2024 » a été retirée du Prélèvement 7 et remplacée par une formulation alignée sur les sources primaires (voir `decisions.md` Décision 3 et historique de `cartographie-taxes.md`). Pour 2024, aucun abattement isolé n'est applicable.

**Pas d'incertitude résiduelle** : la lecture des sources primaires est univoque. La cartographie sera mise à jour pour acter l'erreur.

### 8.2 Ambiguïté #2 — Terminologie « abattement » dans CIBS art. L. 421-111

**Constat** : le législateur emploie le mot « abattement » dans le texte de l'article L. 421-111, mais la mécanique décrite (déduction forfaitaire de 15 000 € sur le montant cumulé des taxes dues) relève d'une **minoration** au sens de la classification opératoire de la méthodologie projet (modification d'un montant à payer, pas d'une caractéristique d'entrée d'une règle de tarification).

**Décision** : la mécanique est documentée au § 4 ci-dessus comme « minoration au sens large » et signalée pour exhaustivité. La distinction sémantique entre « abattement (au sens technique de la méthodologie) » et « minoration (au sens du CIBS) » est documentée dans `decisions.md` Décision 1 et conservée pour la cohérence du modèle de données Floty.

**Pas d'ambiguïté résiduelle** : la mécanique est non équivoque ; seul le nom employé par le législateur prête à confusion.

### 8.3 Pas d'autre divergence identifiée

L'inventaire systématique (§ 3 et § 6) n'a pas révélé d'autre divergence entre sources primaires, secondaires et tertiaires sur le sujet des abattements applicables en 2024.

---

## 9. Questions ouvertes

### Q1 — Nouvelle vérification à effectuer en cas de doctrine inédite

Aucune question ouverte significative ne subsiste à l'issue de cette recherche. La conclusion principale (« aucun abattement isolé applicable en 2024 ») est tranchée par la triangulation des sources primaires (CIBS, BOFiP, notice DGFiP) et confirmée par la concordance avec le cahier des charges Floty § 5.6.

Si une **mise à jour ultérieure du BOFiP** venait à introduire rétroactivement une doctrine sur un abattement applicable en 2024 (cas peu probable mais théoriquement possible), il faudrait reprendre cette recherche. Aucune indication dans ce sens à la date du 23/04/2026.

### Q2 — Coordination avec la phase d'instruction `2025/abattements/`

Quand la phase d'instruction de `2025/abattements/` sera engagée, la **bascule de nature** de l'article L. 421-125 (exonération → abattement) sera le point central à documenter. La présente recherche prépare ce travail en documentant ici précisément la nature « exonération » applicable en 2024 ; la recherche 2025 documentera symétriquement la nature « abattement » applicable à compter du 01/01/2025. Une attention particulière devra être portée à la **cohérence du modèle de données Floty** sur la frontière 31/12/2024 ↔ 01/01/2025 (changement de type de règle pour le même véhicule).

---

## 10. Limites de la recherche

### 10.1 Périmètre temporel de la mission

Cette recherche se limite **strictement à 2024**. L'abattement E85 introduit à compter du 1er janvier 2025 (par révision de l'article L. 421-125 du CIBS), ainsi que tout autre abattement éventuel applicable à compter de 2025, feront l'objet de leurs propres recherches dans `2025/abattements/` et `2026/abattements/`.

### 10.2 Hors périmètre matériel

Sont exclus de cette recherche (renvoyés à d'autres sous-dossiers) :
- exonérations (déjà instruites dans `2024/exonerations/`)
- barèmes de tarification CO₂ et polluants (déjà instruits dans `2024/taxe-co2/` et `2024/taxe-polluants/`)
- cas particuliers de qualification véhicule → `2024/cas-particuliers/`
- coefficient pondérateur frais kilométriques et minoration 15 000 € (mentionnés au § 4 pour mémoire — hors périmètre Floty V1)

### 10.3 Sources primaires consultées

Toutes les sources primaires identifiées dans le périmètre 2024 ont pu être consultées : CIBS art. L. 421-105 à L. 421-167 dans leur version applicable au 31/12/2023 ; BOFiP `BOI-AIS-MOB-10-30-10` et `BOI-AIS-MOB-10-30-20` dans leurs versions applicables à l'exercice 2024 ; notices DGFiP n° 2857-FC-NOT-SD et n° 2858-FC-NOT-SD édition décembre 2024 ; lois de finances 2024 (n° 2023-1322 du 29/12/2023) et 2025 (n° 2025-127 du 14/02/2025, pour vérification de la révision de L. 421-125). La triangulation primaire est complète.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 23/04/2026 | Micha MEGRET | Recherche initiale abattements 2024 — clôture de l'incertitude Z-2024-003 (« Abattement E85 en 2024 ») par démonstration triangulée que l'abattement E85 isolé n'est pas applicable en 2024 (le dispositif n'apparaît qu'à compter du 01/01/2025 par révision de l'article L. 421-125 du CIBS, la version applicable en 2024 décrivant une exonération et non un abattement). Inventaire exhaustif des dispositions du CIBS Section 3 et de la doctrine BOFiP confirmant l'absence d'autre abattement isolé applicable aux taxes annuelles CO₂ et polluants en 2024. Documentation pour mémoire de la minoration 15 000 € (CIBS art. L. 421-111) — hors périmètre Floty V1. Correction proposée pour la cartographie phase 0 § 7 (mention erronée « abattement E85 en 2024 » à supprimer). |
