# Recherches — Taxe annuelle sur les émissions de polluants atmosphériques des véhicules de tourisme — Exercice 2024

> **Statut** : Version 0.1 — recherche initiale
> **Auteur** : Micha MEGRET (prestataire)
> **Date de rédaction** : 22 avril 2026
> **Périmètre matériel** : taxe annuelle sur les émissions de polluants atmosphériques (Prélèvement 8 de la cartographie), exercice 2024 (taxe due au titre de l'utilisation 2024, déclarée en janvier 2025)
> **Hors périmètre de cette recherche** (à traiter ultérieurement) : exonérations détaillées (CIBS L. 421-135 à L. 421-144), abattements éventuels, cas particuliers de qualification véhicule, taxe CO₂ déjà instruite (cf. `2024/taxe-co2/`), années 2025-2026.

---

## 1. Sources consultées

Cf. fichier `sources.md` pour la bibliographie complète. Synthèse :

- **S1** — Notice officielle DGFiP n° 2858-FC-NOT-SD (Cerfa n° 52375#03, décembre 2024) — déclaration de la taxe annuelle sur les émissions de polluants atmosphériques des véhicules de tourisme. **Source primaire majeure** : reproduit textuellement le tableau des trois tarifs forfaitaires 2024 et les définitions des trois catégories d'émissions (E, 1, véhicules les plus polluants). Documente le champ d'application, les modalités de déclaration et le coefficient pondérateur frais kilométriques.
- **S2** — BOFiP `BOI-AIS-MOB-10-30-20-20240710` — section IV « Montant de la taxe annuelle sur les émissions de polluants atmosphériques » (§§ 260 à 290). **Source primaire de doctrine officielle** : confirme les trois tarifs 2024, donne la correspondance avec les vignettes Crit'Air, et fournit l'exemple chiffré officiel § 290.
- **S3** — BOFiP `BOI-AIS-MOB-10-30-10-20250528` — dispositions communes aux taxes d'affectation des véhicules à des fins économiques (transversal CO₂ + polluants).
- **S4** — Légifrance — CIBS articles L. 421-133 à L. 421-144 (Paragraphe 4 « Tarifs de la taxe annuelle sur les émissions de polluants atmosphériques des véhicules de tourisme »), version applicable au 1er janvier 2024 (issue de la loi n° 2023-1322 du 29 décembre 2023, art. 97). Texte intégral consulté.
- **S5** — Loi de finances pour 2024 — Loi n° 2023-1322 du 29 décembre 2023, art. 97 (création du Paragraphe 4 dans sa rédaction 2024) et art. 100, II (non-déductibilité du résultat imposable).
- **S6** — service-public.gouv.fr (entreprendre) — fiche F22203 « Taxes sur l'affectation des véhicules de tourisme à des fins économiques » (couvre les deux taxes annuelles).
- **S7** — economie.gouv.fr — page « Entreprises : ce qu'il faut savoir sur les taxes sur l'affectation des véhicules à des fins économiques ».
- **S8** — PwC Avocats — alerte « Aménagement de la fiscalité applicable aux véhicules » (LF 2024).
- **S9** — FNA — fiche « La taxe annuelle sur les véhicules de tourisme 2024 (ex-TVS) » (croisement tertiaire pour les tarifs et la définition de la catégorie 1).
- **S10** — Compta-Online — fiche « Calcul de la taxe sur les émissions de polluants atmosphériques » (croisement tertiaire avec mention explicite de la correspondance Crit'Air).
- **S11** — Guichet Carte Grise — fiche « TVS : ce qu'il faut savoir sur les nouvelles taxes en 2024 » (croisement tertiaire confirmant la formule « moteur thermique à allumage commandé » et l'exclusion des diesels de la catégorie 1).

---

## 2. Synthèse de la législation applicable

### 2.1 Texte de référence

La taxe annuelle sur les émissions de polluants atmosphériques des véhicules de tourisme affectés à des fins économiques est régie par le **Code des Impositions sur les Biens et Services (CIBS), Livre IV (Mobilités), Section 3 « Taxes sur l'affectation des véhicules à des fins économiques »**. Elle est l'une des deux composantes issues de la refonte de l'ex-TVS en 2022 — l'autre étant la taxe CO₂ instruite dans `2024/taxe-co2/`. Les deux taxes partagent le même périmètre véhiculaire, le même redevable, le même fait générateur, et le même mode de prorata. Elles ne diffèrent que par le **paramètre tarifaire** : la taxe CO₂ fonctionne avec un barème progressif (WLTP, NEDC ou PA), la taxe polluants fonctionne avec un **tarif forfaitaire unique par catégorie d'émissions**.

| Article CIBS | Objet |
|---|---|
| L. 421-2 | Définition du véhicule de tourisme (M1 et certains N1) — **commun** taxe CO₂ et taxe polluants |
| L. 421-94, b du 1° | Existence et nature de la taxe annuelle polluants |
| L. 421-95 | Définition de l'« entreprise » et de l'affectation à des fins économiques — **commun** |
| L. 421-99 | Fait générateur et redevable — **commun** |
| L. 421-105 | Détermination du fait générateur (affectation au territoire de taxation) — **commun** |
| L. 421-107 | Proportion annuelle d'affectation (calcul du prorata) — **commun** |
| L. 421-110 | Coefficient pondérateur en cas de remboursement de frais kilométriques — **commun** |
| L. 421-111 | Abattement de 15 000 € sur les frais kilométriques — **commun** |
| **L. 421-133** | Article chapeau du Paragraphe 4 (renvoi à L. 421-94, b du 1°) |
| **L. 421-134** | **Définition des trois catégories d'émissions de polluants** (E, 1, véhicules les plus polluants) |
| **L. 421-135** | **Tableau des tarifs forfaitaires** par catégorie |
| L. 421-136 | Exonération véhicules accessibles en fauteuil roulant — **hors périmètre** (à instruire dans `2024/exonerations/`) |
| L. 421-138 | Exonération organismes intérêt général (CGI 261, 7°) — **hors périmètre** |
| L. 421-139 | Exonération entrepreneurs individuels — **hors périmètre** |
| L. 421-140 | Exonération loueurs et mise à disposition temporaire — **hors périmètre** mais **fondamentale pour Floty** : c'est l'exonération de la société de location elle-même |
| L. 421-141 | Exonération location courte durée (≤ 1 mois civil ou 30 jours consécutifs) — **hors périmètre** |
| L. 421-142 | Exonération transport public personnes — **hors périmètre** |
| L. 421-143 | Exonération activités agricoles ou forestières — **hors périmètre** |
| L. 421-144 | Exonération enseignement de la conduite + compétitions sportives — **hors périmètre** |
| L. 421-157 et suivants | Exigibilité, redevable, déclaration, paiement — **commun** taxe CO₂ et taxe polluants |
| L. 421-163 | Pas de déclaration si montant nul — **commun** |
| L. 421-164 | État récapitulatif annuel — **commun** |
| L. 131-1 | Règle générale d'arrondi (à l'euro le plus proche) — **commun** |
| L. 141-2 | Exigibilité au fait générateur — **commun** |
| L. 171-2 | Paiement à dépôt déclaration — **commun** |

**Texte législatif d'origine** : la création du Paragraphe 4 (« Tarifs de la taxe annuelle sur les émissions de polluants atmosphériques ») dans sa rédaction applicable à compter du 1er janvier 2024 résulte de l'**article 97 de la loi n° 2023-1322 du 29 décembre 2023** (loi de finances pour 2024). Cette loi a transformé l'ancienne « taxe sur l'ancienneté des véhicules de tourisme » (en vigueur de 2022 à 2023, tarif lié à la date de 1ère mise en circulation) en taxe sur les **émissions de polluants atmosphériques** au sens strict, classée par **catégorie de motorisation** (et non plus par âge).

> « Les taxes annuelles sur les véhicules de tourisme, désormais régies par le code des impositions sur les biens et services (CIBS), […] sur les émissions de polluants atmosphériques (taxe sur l'ancienneté des véhicules jusqu'en 2023). »
> — S1, encadré rouge p. 1, consulté le 22/04/2026

### 2.2 Champ d'application

**Véhicules taxables** (CIBS art. L. 421-2) : strictement identique à la taxe CO₂ — voir `2024/taxe-co2/recherches.md` § 2.2. Synthèse :

- véhicules **catégorie M1**, autres que véhicules à usage spécial, y compris ceux accessibles en fauteuil roulant ;
- parmi les véhicules **catégorie N1** : ceux dont la carrosserie est « Camion pick-up » ≥ 5 places assises (sauf domaines skiables), et ceux dont la carrosserie est « Camionnette » avec ≥ 2 rangs de places, affectés au transport de personnes.

**Territoire de taxation** : France métropolitaine, Guadeloupe, Martinique, Guyane, La Réunion, Mayotte. (S1 § I.2.b)

**Affectation à des fins économiques** (CIBS art. L. 421-95) : strictement identique à la taxe CO₂ — voir `2024/taxe-co2/recherches.md` § 2.2 et § 3.2.

**Notion d'entreprise** : « personne assujettie à la TVA » au sens des articles 256 A et 256 B du CGI. Identique à la taxe CO₂.

**Sont réputés ne pas être affectés** (et donc non taxables) :
- véhicules de démonstration ou « W garage » qui ne réalisent pas d'opération de transport autre que strictement nécessaire ;
- véhicules immobilisés ou mis en fourrière à la demande des pouvoirs publics.

> Le redevable, le fait générateur, la définition de l'affectation à des fins économiques, le territoire de taxation et la notion d'entreprise sont **strictement identiques** à ceux de la taxe annuelle CO₂. Voir Décision 1 dans `decisions.md` qui formalise ce report intégral.

### 2.3 Date d'entrée en vigueur des tarifs 2024

Les tarifs forfaitaires fixés à l'article L. 421-135 du CIBS, dans la rédaction issue de la loi de finances pour 2024 (art. 97), s'appliquent à compter du **1er janvier 2024** pour la période d'imposition courant du 01/01/2024 au 31/12/2024. La taxe est déclarée et acquittée en **janvier 2025**.

### 2.4 Date de fin d'application

Les tarifs 2024 (E = 0 € ; catégorie 1 = 100 € ; véhicules les plus polluants = 500 €) restent **inchangés en 2025**. Une **revalorisation** intervient au 1er janvier 2026 par la loi de finances pour 2026 (loi n° 2026-103 du 19 février 2026, art. 58) : nouveaux tarifs E = 0 €, catégorie 1 = 130 €, véhicules les plus polluants = 650 €. Cette évolution sera instruite dans `2026/taxe-polluants/recherches.md`. Pour l'exercice 2024, seuls les tarifs 0 / 100 / 500 € sont retenus.

---

## 3. Extraits pertinents

### 3.1 Définition des trois catégories d'émissions de polluants — texte du CIBS

> « Le tarif annuel est déterminé en fonction de l'appartenance du véhicule à l'une des trois catégories d'émissions de polluants suivantes :
> 1° La catégorie E, qui regroupe les véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène ou une combinaison des deux ;
> 2° La catégorie 1, qui regroupe les véhicules qui sont alimentés par un moteur thermique à allumage commandé et qui respectent les valeurs limites d'émissions « Euro 5 » ou « Euro 6 » mentionnées respectivement au tableau 1 et au tableau 2 de l'annexe I du règlement (CE) n° 715/2007 du Parlement européen et du Conseil du 20 juin 2007 relatif à la réception des véhicules à moteur au regard des émissions des véhicules particuliers et utilitaires légers (Euro 5 et Euro 6), dans sa rédaction en vigueur ;
> 3° La catégorie des véhicules les plus polluants, qui regroupe les véhicules ne relevant ni du 1°, ni du 2° du présent article. »
> — S4, CIBS art. L. 421-134, version applicable au 01/01/2024 (loi n° 2023-1322 du 29/12/2023, art. 97), consulté le 22/04/2026

### 3.2 Définition des trois catégories — texte de la notice DGFiP S1

> « La catégorie E regroupe les véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène ou une combinaison des deux.
>
> La catégorie 1 regroupe les véhicules alimentés par un moteur thermique à allumage commandé et qui respectent les valeurs limites d'émissions « Euro 5 » ou « Euro 6 » (normes européennes).
>
> La catégorie des véhicules les plus polluants regroupe les véhicules qui ne relèvent pas, ni de la catégorie E, ni de la catégorie 1. »
> — S1, partie IV, ligne F, p. 6, consulté le 22/04/2026

### 3.3 Définition des trois catégories — paraphrase BOFiP

> « le tarif de la taxe annuelle polluants dépend de l'appartenance du véhicule à l'une des trois catégories suivantes :
> - la catégorie E, qui regroupe les véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène ou une combinaison des deux ;
> - la catégorie 1, qui regroupe les véhicules essence, hybrides et gaz compatibles avec les normes d'émissions européennes dites « Euro 5 » et « Euro 6 » ;
> - la catégorie « véhicules les plus polluants », qui regroupe l'ensemble des autres véhicules. »
> — S2, § 260, consulté le 22/04/2026

**Note** : la formulation BOFiP « essence, hybrides et gaz » est l'**équivalent fonctionnel** de la formulation « moteur thermique à allumage commandé » utilisée dans la lettre de l'article L. 421-134 et reprise par la notice DGFiP. L'allumage commandé — par opposition à l'allumage par compression — désigne précisément les moteurs essence et au gaz (GPL, GNV, superéthanol E85). Les moteurs Diesel utilisent l'allumage par compression et sont donc **exclus de la catégorie 1**, même Euro 6 — ils basculent automatiquement en « véhicules les plus polluants ». Voir Décision 3 dans `decisions.md`. Cette équivalence est confirmée par les sources tertiaires S9 (FNA) et S11 (Guichet Carte Grise).

### 3.4 Tableau des tarifs forfaitaires 2024 — notice DGFiP

> « Tarif annuel selon barème : le tarif de la taxe sur les émissions de polluants atmosphériques est déterminé en fonction de l'appartenance du véhicule à l'une des trois catégories d'émissions de polluants définies ci-après, est le suivant : (CIBS art. L. 421-134 et L. 421-135) :
>
> | Catégorie d'émissions de polluants | Tarif annuel (€) |
> |---|---|
> | Catégorie E | 0 |
> | Catégorie 1 | 100 |
> | Véhicules les plus polluants | 500 |
> »
> — S1, partie IV, ligne F, p. 6, consulté le 22/04/2026

### 3.5 Tableau des tarifs forfaitaires 2024 — BOFiP

> Tableau § 280 (synthèse, avec mention de la correspondance Crit'Air) :
>
> | Catégorie | Tarif |
> |---|---|
> | E (électrique et hydrogène) | 0 € |
> | 1 (gaz, hybride et essence Euro 5 et 6) | 100 € |
> | Véhicules les plus polluants (catégories Crit'Air 2 à 5 et non classés) | 500 € |
>
> — S2, § 280, consulté le 22/04/2026

### 3.6 Lien avec la nomenclature Crit'Air

Le BOFiP (S2 § 270) établit explicitement la correspondance entre les trois catégories d'émissions polluants du CIBS et les vignettes Crit'Air définies par l'**arrêté du 21 juin 2016 établissant la nomenclature des véhicules classés en fonction de leur niveau d'émission de polluants atmosphériques en application de l'article R. 318-2 du code de la route**. Cette correspondance est :

| Catégorie CIBS (taxe polluants) | Vignette Crit'Air | Tarif 2024 |
|---|---|---|
| **E** (électrique, hydrogène, ou combinaison des deux) | **Crit'Air E** (verte) | **0 €** |
| **1** (essence, hybride essence, gaz — allumage commandé Euro 5/6) | **Crit'Air 1** (violette) | **100 €** |
| **Véhicules les plus polluants** | **Crit'Air 2, 3, 4, 5** + véhicules **non classés** (sans vignette) | **500 €** |

**Implication pratique pour Floty** : la catégorie d'émissions de polluants est, en règle générale, **directement lisible** sur le certificat qualité de l'air (Crit'Air) du véhicule. Cette équivalence sécurise la qualification : un véhicule avec vignette Crit'Air 1 est en catégorie 1 fiscale ; un véhicule avec vignette Crit'Air verte (E) est en catégorie E fiscale ; toute autre vignette ou absence de vignette = « véhicules les plus polluants ». Voir Décision 3 dans `decisions.md` et Décision 4 dans `decisions.md` (chemins alternatifs de qualification).

**Confirmation** : sources tertiaires S9 (FNA), S10 (Compta-Online) et S11 (Guichet Carte Grise) reproduisent indépendamment cette correspondance Crit'Air ↔ catégorie fiscale.

### 3.7 Cas particulier des véhicules Diesel — confirmation explicite

> « Le document spécifie que la catégorie 1 comprend exclusivement les véhicules à moteur thermique à allumage commandé respectant les normes Euro 5 ou Euro 6. Cette formulation exclut implicitement les diesels, qui utilisent l'allumage par compression, pas l'allumage commandé. »
> — S11, paraphrase de l'analyse Guichet Carte Grise, consulté le 22/04/2026

**Conséquence directe** : un véhicule Diesel, **même Euro 6d-ISC-FCM (le plus récent)**, est obligatoirement classé en catégorie « véhicules les plus polluants » au sens du CIBS art. L. 421-134, et donc taxé à 500 € pour 2024 (avant prorata). C'est une **différence de traitement importante** entre essence Euro 6 (catégorie 1, 100 €) et Diesel Euro 6 (véhicules les plus polluants, 500 €) qui doit être saisie sans erreur par Floty. Cette distinction est cohérente avec la **vignette Crit'Air** qui distingue précisément Crit'Air 1 (essence/gaz Euro 5+) de Crit'Air 2 (Diesel Euro 5+).

### 3.8 Mécanique de calcul — formule officielle

Le calcul de la taxe annuelle polluants pour un véhicule donné repose sur la même mécanique de prorata que la taxe CO₂. La notice DGFiP S1 partie IV et le BOFiP S2 § 290 confirment :

```
Montant taxe polluants = Tarif forfaitaire (selon catégorie) × Proportion annuelle d'affectation [× Coefficient pondérateur si frais kilométriques]
```

avec :

- **Tarif forfaitaire** : 0 € (catégorie E), 100 € (catégorie 1), 500 € (véhicules les plus polluants), pour 2024.
- **Proportion annuelle d'affectation** = numérateur (jours d'affectation à l'entreprise) / dénominateur (jours de l'année civile = 366 pour 2024 année bissextile). Identique à la taxe CO₂. Voir `2024/taxe-co2/decisions.md` Décisions 3, 4, 5, 8, 9 pour la formalisation détaillée.
- **Coefficient pondérateur** (uniquement pour véhicules salariés/dirigeants avec frais kilométriques pris en charge) : 0 % à 100 % selon kilométrage remboursé (table identique à la taxe CO₂). Hors périmètre Floty V1 (la flotte Renaud n'est pas constituée de véhicules personnels de salariés) — cf. `2024/taxe-co2/recherches.md` § 3.9.
- **Abattement 15 000 €** sur le montant cumulé dû au titre des véhicules salariés/dirigeants : également hors périmètre Floty V1.

### 3.9 Exemple chiffré officiel BOFiP — § 290

> Hypothèses : véhicule de catégorie 1 (tarif annuel plein 100 €), salarié bénéficiant d'un remboursement de frais kilométriques par l'entreprise, 30 000 km remboursés sur l'année (→ coefficient pondérateur 50 %), 75 % d'utilisation pour activité non exonérée et 25 % pour une activité agricole exonérée.
>
> Calcul : 75 % × 50 % × 100 € = **32,5 €**
>
> — S2, § 290, consulté le 22/04/2026

**Vérification manuelle** : `0,75 × 0,50 × 100 = 37,5 €`. L'exemple BOFiP énonce un résultat de **32,5 €**, soit un écart apparent de 5 € avec le calcul littéral.

> ⚠️ Le BOFiP énonce 32,5 € mais le calcul littéral 0,75 × 0,50 × 100 donne 37,5 €. **Examen attentif** : il est possible que l'exemple BOFiP intègre un autre paramètre non explicité dans la paraphrase (par exemple : 65 % d'affectation au lieu de 75 %, ou un coefficient pondérateur différent), ou bien le BOFiP applique l'arrondi fiscal final (37,5 → 38 € — ce qui ne donne pas 32,5 non plus). Cet écart est **documenté en zone d'incertitude** (cf. § 6.1) et fera l'objet d'une seconde passe de vérification directe sur le BOFiP après prise de recul. Pour les besoins du moteur de calcul Floty, **on retient la mécanique** `Tarif × Prorata × [Coefficient pondérateur]` qui est non ambiguë et conforme à la formule de la notice DGFiP S1 partie IV (lignes F, J, K, M, N).

**Note méthodologique** : un seul exemple BOFiP est fourni pour la taxe polluants, et il porte sur un cas hors périmètre Floty (véhicule salarié avec frais kilométriques). Pour les véhicules détenus par la société et affectés à une entreprise utilisatrice (cœur du modèle Floty), la mécanique est plus simple : `Tarif forfaitaire × Prorata jours`. Voir les exemples chiffrés produits dans `decisions.md` Décision 5.

### 3.10 Modalités de déclaration et de paiement

Identiques à la taxe CO₂ (déclaration commune sur les mêmes formulaires, paiement conjoint) :

- **Régime réel normal de TVA ou non-redevables TVA** : déclaration sur **annexe n° 3310 A** à la déclaration de TVA, à déposer en janvier suivant la période d'imposition (au plus tard le 25 janvier pour les non-redevables TVA).
- **Régime simplifié de TVA (RSI)** : déclaration sur **formulaire n° 3517**, déposé au titre de l'exercice où la taxe est devenue exigible.
- **Fiche d'aide au calcul** : formulaire n° **2858-FC-SD** (distinct du formulaire 2857-FC-SD qui concerne la taxe CO₂). Non joint à la déclaration mais peut être demandé par l'administration. Floty produit cette information dans son PDF récapitulatif (cahier des charges § 5.7).
- **Pas de déclaration si montant nul** (CIBS art. L. 421-163, S3 § 380) : si après application des exonérations et de la catégorie E (0 €), le total dû au titre de la taxe polluants est nul, aucune déclaration n'est requise pour cette taxe (mais la taxe CO₂ peut rester due et exiger une déclaration séparée).
- **État récapitulatif annuel** (CIBS art. L. 421-164, S3 § 400-410) : à tenir à jour, mêmes obligations que la taxe CO₂.

### 3.11 Coefficient pondérateur frais kilométriques + abattement 15 000 €

S1 partie II.3 et S1 partie IV ligne M reproduisent exactement le même barème pondérateur que la taxe CO₂ :

| Nombre de kilomètres remboursés par la société | % de la taxe à verser |
|---|---|
| De 0 à 15 000 km | 0 % |
| De 15 001 à 25 000 km | 25 % |
| De 25 001 à 35 000 km | 50 % |
| De 35 001 à 45 000 km | 75 % |
| Supérieur à 45 000 km | 100 % |

L'abattement de 15 000 € s'applique **conjointement** à la taxe CO₂ ET à la taxe polluants pour les véhicules détenus ou loués par les salariés/dirigeants donnant lieu à prise en charge :

> « [l'abattement] s'applique au montant total dû au titre de la taxe sur les émissions de CO2 et de la taxe sur les émissions de polluants atmosphériques due sur ces véhicules donnant lieu à prise en charge. »
> — S1, partie II.3, p. 4, consulté le 22/04/2026

**Hors périmètre Floty** : ces dispositions ne concernent pas les véhicules détenus par la société de location et affectés par mise à disposition à une entreprise utilisatrice. Documenté ici pour exhaustivité.

### 3.12 Option forfaitaire trimestrielle

Identique à la taxe CO₂ : disponible en 2024, **supprimée à compter du 1er janvier 2025**. La notice DGFiP S1 précise par ailleurs :

> « L'option est exercée par le redevable conjointement pour la taxe annuelle sur les émissions de dioxyde de carbone et pour la taxe annuelle sur les émissions de polluants atmosphériques, au plus tard au moment où il constate ces taxes. Elle s'applique à l'ensemble des véhicules de tourisme affectés par le redevable à des fins économiques. »
> — S1, partie II.2.b, p. 4, consulté le 22/04/2026

**Conséquence** : l'option ne peut pas être exercée séparément pour la taxe polluants ; elle est **conjointe** aux deux taxes. Floty n'implémente pas cette option (cf. `2024/taxe-co2/decisions.md` Décision 5 — calcul journalier exclusif).

### 3.13 Règle d'arrondi

Identique à la taxe CO₂ — voir `2024/taxe-co2/decisions.md` Décision 2. La notice S1 reprend mot pour mot la même formulation :

> « Le montant total de la taxe à payer est arrondi à l'euro le plus proche. Les montants inférieurs à 0,50 euro sont ramenés à l'euro inférieur et ceux supérieurs ou égaux à 0,50 euro sont comptés pour 1. »
> — S1, p. 5, encadré « ARRONDIS FISCAUX », consulté le 22/04/2026

L'arrondi s'applique au **montant total à payer** par le redevable (et non par véhicule, ni par taxe). En pratique, dans Floty : la taxe CO₂ et la taxe polluants sont calculées séparément avec précision décimale par véhicule, puis sommées au niveau du redevable, et l'**arrondi final unique** s'applique sur le total. Voir Décision 2 dans `decisions.md`.

---

## 4. Valeurs numériques relevées — Tarifs 2024

### 4.1 Tarifs forfaitaires (CIBS art. L. 421-135, version 2024)

| Catégorie d'émissions de polluants | Tarif annuel 2024 (€) | Vignette Crit'Air équivalente |
|---|---|---|
| **E** (électrique exclusif, hydrogène exclusif, ou combinaison des deux) | **0 €** | Crit'Air E (verte) |
| **1** (moteur thermique à allumage commandé Euro 5 ou Euro 6 — soit essence, hybride essence, GPL, GNV, superéthanol E85) | **100 €** | Crit'Air 1 (violette) |
| **Véhicules les plus polluants** (tous les autres : Diesels même Euro 6, essence pré-Euro 5, véhicules sans vignette Crit'Air) | **500 €** | Crit'Air 2, 3, 4, 5 ou non classé |

**Croisement** : valeurs **identiques** entre :
- S1 (notice DGFiP, partie IV ligne F)
- S2 (BOFiP § 280)
- S4 (CIBS art. L. 421-135, version 2024)
- S8 (PwC), S9 (FNA), S10 (Compta-Online), S11 (Guichet Carte Grise) — confirmations tertiaires indépendantes
- Cahier des charges Floty § 5.3 — concorde

**Confiance : haute** (triangulation primaire complète + 4 sources tertiaires concordantes + concordance avec le cahier des charges).

### 4.2 Mécanique de calcul

```
Pour chaque véhicule v de l'entreprise e sur l'année 2024 :
  tarif_v = catégorie_v.tarif_2024     # 0 / 100 / 500 €
  prorata_v = jours_affectation_v_à_e / 366    # 2024 = année bissextile
  taxe_polluants_v = tarif_v × prorata_v
  
Pour l'entreprise e :
  total_polluants_e = somme des taxe_polluants_v
  total_co2_e = (somme des taxes CO₂ — voir 2024/taxe-co2/)
  total_dû_e = ARRONDI_EURO(total_co2_e + total_polluants_e)
```

(L'arrondi final s'applique au total à payer toutes taxes confondues, conformément à la notice S1.)

### 4.3 Coefficient pondérateur (rappel — hors périmètre Floty V1)

Identique au tableau de la taxe CO₂ (cf. `2024/taxe-co2/recherches.md` § 3.9). Mêmes 5 tranches (0 → 0 % ; 15 001-25 000 → 25 % ; 25 001-35 000 → 50 % ; 35 001-45 000 → 75 % ; > 45 000 → 100 %).

---

## 5. Cas particuliers identifiés dans les sources

> Ces cas particuliers sont identifiés ici à titre informatif (méthodologie § 3.1 — exhaustivité). Leur instruction détaillée ne fait **pas** partie de cette mission. Renvois vers les sous-dossiers cibles indiqués.

### 5.1 Véhicules accessibles en fauteuil roulant
- Statut : **inclus dans le champ de la taxe polluants** (S1 § I.2.a) mais **exonérés** au titre de CIBS art. L. 421-136 (S1 § I.3).
- Renvoi : `2024/exonerations/`.

### 5.2 Véhicules électriques, hydrogène, électrique+hydrogène (catégorie E)
- Statut : **dans le champ** mais avec un **tarif forfaitaire de 0 €** par classement direct en catégorie E (CIBS art. L. 421-134, 1°).
- **Important** : la « non-imposition » de la catégorie E **ne résulte pas d'une exonération** au sens technique du terme. C'est un **tarif fixé à 0 € par le barème lui-même** (article L. 421-135). La distinction est mineure en pratique mais importante en théorie : il n'y a pas de procédure d'exonération à demander, le calcul aboutit naturellement à 0 €. À noter que pour la **taxe CO₂**, les véhicules électriques bénéficient en parallèle d'une **vraie exonération** au titre de CIBS art. L. 421-124 (à instruire dans `2024/exonerations/`). Les deux mécanismes (tarif 0 € polluants + exonération CO₂) aboutissent au même résultat (taxe totale = 0 €) pour un véhicule 100 % électrique.

### 5.3 Véhicules hybrides essence
- Statut : **catégorie 1** (à 100 €) — l'allumage commandé du moteur essence intégré dans la chaîne hybride suffit. L'hybride est explicitement cité par le BOFiP § 260 et § 280.
- Cas particulier : un véhicule hybride combinant **diesel + électrique** (très rare en pratique) tomberait en « véhicules les plus polluants » faute d'allumage commandé. À documenter dans `2024/cas-particuliers/`.

### 5.4 Véhicules Diesel Euro 6 — point critique
- Statut : **systématiquement « véhicules les plus polluants »** à 500 €, même Euro 6 (incluant Euro 6d-ISC-FCM le plus récent), parce que le moteur Diesel utilise l'allumage par compression et non l'allumage commandé.
- Cohérence Crit'Air : un Diesel Euro 5 ou 6 a la vignette **Crit'Air 2** — qui appartient à la catégorie « véhicules les plus polluants » du CIBS.
- Renvoi : ce point est central et figure dans la Décision 3 de `decisions.md`. Floty doit clairement signaler le tarif pour les véhicules Diesel et ne pas tenter de les classer en catégorie 1.

### 5.5 Véhicules essence pré-Euro 5
- Statut : « véhicules les plus polluants » à 500 €. Les véhicules essence Euro 1, 2, 3, 4 n'entrent pas dans la catégorie 1 (qui exige Euro 5 ou 6). Cohérence Crit'Air : Crit'Air 3 (essence Euro 4), Crit'Air 4 (essence Euro 3), Crit'Air 5 (essence Euro 2), non classé (essence Euro 1).

### 5.6 Véhicules sans vignette Crit'Air (non classés)
- Statut : « véhicules les plus polluants » à 500 €. Concerne les véhicules antérieurs à 1997 (essence) ou 2001 (Diesel) qui n'ont pas accès à la vignette Crit'Air.
- Très rares dans une flotte d'entreprise contemporaine.

### 5.7 Loueur (société de location elle-même) — exonération CIBS L. 421-140
- Statut : **exonéré** au titre de CIBS art. L. 421-140 (les véhicules « exclusivement affectés à la location ou à la mise à disposition temporaire de clients en remplacement de leur véhicule immobilisé »).
- **Implication pour Floty** : la société de location de Renaud, qui détient les véhicules et les met à disposition des entreprises utilisatrices, **est exonérée en tant que loueur**. C'est l'**équivalent strict** de l'exonération CIBS L. 421-128 pour la taxe CO₂. Les **entreprises utilisatrices** restent redevables au prorata. C'est précisément le modèle Floty — voir `2024/taxe-co2/recherches.md` § 5.7.
- Renvoi : `2024/exonerations/`.

### 5.8 Personnes physiques en nom propre (entrepreneurs individuels)
- Statut : **exonérés** au titre de CIBS art. L. 421-139.
- Renvoi : `2024/exonerations/`. Faiblement pertinent pour Floty.

### 5.9 Véhicules en location courte durée (≤ 1 mois civil ou 30 jours consécutifs)
- Statut : **exonérés** au titre de CIBS art. L. 421-141 (équivalent strict de L. 421-129 pour la taxe CO₂).
- **Important pour Floty** : la mécanique de l'exonération LCD est **identique pour les deux taxes** (texte CIBS rigoureusement parallèle entre L. 421-129 et L. 421-141). Floty applique cette exonération de manière unifiée selon la mécanique de **cumul annuel par couple (véhicule, entreprise utilisatrice)** documentée dans `taxes-rules/2024.md` R-2024-021 (lecture définitive après clarification client le 23/04/2026 — voir Z-2024-002 résolu dans `2024/taxe-co2/incertitudes.md`).

### 5.10 Activités exonérées (transport public personnes, agricoles/forestières, conduite, compétitions)
- Statut : **exonérés** au titre de CIBS art. L. 421-142 à L. 421-144.
- Renvoi : `2024/exonerations/`. Marginal pour Floty.

### 5.11 Frontière M1/N1 (pick-ups, camionnettes 5+ places)
- Identique à la taxe CO₂ (le périmètre véhiculaire est commun aux deux taxes). Renvoi : `2024/cas-particuliers/`. Cf. Z-2024-005 dans `incertitudes.md`.

### 5.12 Véhicules acquis ou cédés en cours d'année
- Cas standard et bien documenté pour les deux taxes (S3 § 190). Aucune subtilité supplémentaire pour la taxe polluants — le calcul du prorata se fait par décompte exact des jours d'affectation, exactement comme pour la taxe CO₂.

### 5.13 Mise en fourrière / immobilisation pouvoirs publics
- Identique à la taxe CO₂ (réduction du numérateur). Voir `2024/taxe-co2/decisions.md` Décision 8 et `incertitudes.md` Z-2024-001.

### 5.14 Indemnités kilométriques aux salariés
- Coefficient pondérateur + abattement 15 000 € — identique à la taxe CO₂. **Pas applicable à Floty V1** (cf. § 3.11 et `2024/taxe-co2/recherches.md` § 5.12).

---

## 6. Divergences ou ambiguïtés rencontrées

### 6.1 Ambiguïté #1 — Exemple chiffré BOFiP § 290 — écart constaté lors du recalcul

**Constat** : l'exemple chiffré officiel BOFiP § 290, tel que reproduit en § 3.9, énonce un résultat de **32,5 €** pour les hypothèses : véhicule de catégorie 1 (tarif 100 €), 75 % d'utilisation activité non exonérée, coefficient pondérateur 50 % (kilométrage 30 000 km). Or le calcul littéral `0,75 × 0,50 × 100 = 37,5 €` donne 37,5 € et non 32,5 €.

**Hypothèses possibles** :
1. Le pourcentage d'affectation à l'activité non exonérée est de 65 % et non 75 % : `0,65 × 0,50 × 100 = 32,5 €` ✓. C'est cohérent.
2. La paraphrase de la source secondaire a mal restitué une valeur du BOFiP.
3. Le BOFiP arrondit en interne ou utilise un autre paramètre non précisé.

**Action** : ne pas s'appuyer sur cet exemple pour valider la mécanique du calcul. La mécanique générale `Tarif × Prorata × [Coefficient pondérateur]` est par ailleurs **directement énoncée** par la notice DGFiP S1 partie IV ligne N (« porter ici le résultat du produit des colonnes F, J et K ou F, J, K et M »). Cette formulation suffit à la formalisation Floty. L'écart sur l'exemple est documenté en zone d'incertitude (cf. § 7).

**Pas d'impact pour Floty V1** : le coefficient pondérateur et le mécanisme « activité partielle non exonérée × activité exonérée » sont hors périmètre Floty V1 (la flotte Renaud n'a pas de véhicules salariés en remboursement kilométrique, et les exonérations partielles d'activité — agricole, transport public — ne concernent pas la flotte type d'entreprise Renaud).

### 6.2 Ambiguïté #2 — Formulation BOFiP § 260 vs CIBS art. L. 421-134

**Constat** : la lettre du CIBS art. L. 421-134 et la notice DGFiP S1 utilisent la formule **« moteur thermique à allumage commandé »**. Le BOFiP § 260 utilise la formule **« essence, hybrides et gaz »**. Ces formules ne sont pas littéralement identiques.

**Analyse** : « moteur thermique à allumage commandé » est la définition technique précise (par opposition au « moteur thermique à allumage par compression » = Diesel). Les motorisations utilisant l'allumage commandé sont :
- essence (incluant superéthanol E85)
- gaz (GPL, GNV)
- hybrides essence (le moteur thermique de la chaîne hybride est à allumage commandé)

Donc **« essence, hybrides et gaz »** (formule BOFiP) **= énumération complète** des motorisations à allumage commandé. La formule technique CIBS et l'énumération BOFiP sont **équivalentes** — l'une est définie en compréhension (par le critère technique d'allumage), l'autre en extension (en listant les motorisations concernées).

**Pas d'ambiguïté résiduelle** : un véhicule essence Euro 6 → catégorie 1 ; un véhicule GPL Euro 5 → catégorie 1 ; un véhicule hybride essence Euro 6 → catégorie 1 ; un véhicule Diesel Euro 6 → catégorie « véhicules les plus polluants ». Voir Décision 3 dans `decisions.md`.

### 6.3 Ambiguïté #3 — Hybrides Diesel (rares mais existants)

**Constat** : un véhicule hybride combinant moteur Diesel + électrique (technologie peu répandue mais commercialisée par certains constructeurs premium en 2018-2022) n'a pas de moteur thermique à allumage commandé. Strictement lue, la lettre de l'article L. 421-134, 2° l'**exclut** de la catégorie 1.

**Conséquence** : un hybride Diesel-électrique Euro 6 se classe en « véhicules les plus polluants » → 500 €. Cette lecture est cohérente avec la vignette Crit'Air (un hybride Diesel a généralement Crit'Air 2 et non 1).

**Aucune source ne traite explicitement ce cas** ; mais la lettre de l'article et la cohérence Crit'Air convergent. Voir Décision 3 dans `decisions.md` (qui formalise une logique de classement par énumération exhaustive des motorisations).

### 6.4 Ambiguïté #4 — Véhicules très anciens sans donnée Euro fiable

**Constat** : pour un véhicule essence très ancien (avant 1997) ou un véhicule importé sans certificat de conformité, la norme Euro peut être inconnue ou non documentable.

**Lecture par défaut** : à défaut de pouvoir établir Euro 5 ou Euro 6, le véhicule ne peut pas être classé en catégorie 1 → bascule en « véhicules les plus polluants » à 500 €. C'est cohérent avec la **règle générale du 3°** de l'article L. 421-134 (« regroupe les véhicules ne relevant ni du 1°, ni du 2° »).

**Pas d'ambiguïté** : la catégorie « véhicules les plus polluants » est par construction la **catégorie résiduelle**. Tout doute sur la qualification Euro 5/6 ou sur la motorisation aboutit à un classement dans cette catégorie. C'est par ailleurs conforme au **principe de prudence** (méthodologie § 8.3) : en cas de doute, on majore.

### 6.5 Ambiguïté #5 — Précision sur les normes Euro 5d, 6b, 6c, 6d-Temp, 6d-ISC-FCM

**Constat** : la norme Euro a connu plusieurs sous-déclinaisons (Euro 5a, 5b, 6b, 6c, 6d-Temp, 6d, 6d-ISC, 6d-ISC-FCM). L'article L. 421-134 se réfère aux « valeurs limites d'émissions Euro 5 ou Euro 6 mentionnées respectivement au tableau 1 et au tableau 2 de l'annexe I du règlement (CE) n° 715/2007 ». Toutes les sous-déclinaisons d'Euro 5 et d'Euro 6 satisfont par construction les valeurs limites de leur norme parente.

**Conclusion** : tout véhicule homologué Euro 5 (toute sous-déclinaison) ou Euro 6 (toute sous-déclinaison) qui est par ailleurs à allumage commandé est en **catégorie 1**. Il n'y a pas de subtilité supplémentaire.

---

## 7. Questions ouvertes

### Q1 — Vérification de l'exemple BOFiP § 290 (écart 32,5 € vs 37,5 €)
Voir § 6.1. À ré-examiner par lecture directe approfondie du BOFiP en deuxième passe (consultation différée de 24 h pour recul, conformément à la méthodologie § 9.3). Sans impact pour Floty V1 (mécanique hors périmètre), mais à clarifier pour la complétude documentaire.

### Q2 — Confirmation explicite hybrides Diesel
Voir § 6.3. Aucune source ne traite explicitement les véhicules hybrides Diesel-électrique. La lecture proposée (catégorie « véhicules les plus polluants ») s'appuie sur la lettre de l'article et la cohérence Crit'Air. À documenter dans `2024/cas-particuliers/` et à valider par l'expert-comptable.

### Q3 — Interaction taxe polluants + exonération de la société de location
La société de location de Renaud bénéficie d'une exonération au titre de L. 421-140 (équivalent L. 421-128 pour la taxe CO₂). À instruire conjointement dans `2024/exonerations/` pour les deux taxes.

### Q4 — Cohérence catégorie polluants ↔ vignette Crit'Air saisie dans Floty
Le cahier des charges Floty (§ 5.3) parle de « catégorie d'émissions de polluants atmosphériques : E, 1, ou véhicules les plus polluants (correspond aux catégories Crit'Air) ». Pour la saisie, doit-on demander la **catégorie fiscale** (E / 1 / véhicules les plus polluants) directement, ou la **vignette Crit'Air** (E / 1 / 2 / 3 / 4 / 5 / non classé) avec mapping automatique vers la catégorie fiscale ? Question produit. Recommandation : saisir la vignette Crit'Air (information dont l'utilisateur dispose immédiatement par lecture du certificat qualité de l'air), Floty calcule automatiquement la catégorie fiscale. Ce point est précisé dans Décision 4 dans `decisions.md`.

---

## 8. Limites de la recherche

### 8.1 Périmètre temporel de la mission

Cette recherche se limite **strictement à 2024**. Les tarifs 2025 (identiques à 2024) et 2026 (revalorisés à E=0/1=130/polluants=650) ne sont pas instruits ici — ils feront l'objet de leurs propres recherches dans `2025/taxe-polluants/` et `2026/taxe-polluants/`.

### 8.2 Hors périmètre matériel

Sont volontairement exclus de cette recherche (renvoyés à d'autres sous-dossiers) :
- exonérations détaillées (CIBS L. 421-136, L. 421-138 à L. 421-144) → `2024/exonerations/`
- abattements éventuels → `2024/abattements/` (a priori aucun abattement spécifique à la taxe polluants en 2024 — à confirmer)
- cas particuliers de qualification véhicule (M1/N1, hybrides Diesel, importation) → `2024/cas-particuliers/`
- taxe CO₂ → `2024/taxe-co2/` (déjà instruite)
- coefficient pondérateur frais kilométriques (théoriquement applicable mais hors usage Floty)

### 8.3 Source primaire non consultée

L'exemple BOFiP § 290 (cf. § 3.9 et § 6.1) n'a pas pu être vérifié textuellement de manière reproductible : la paraphrase obtenue contient un écart numérique apparent (32,5 € vs 37,5 € attendu par calcul littéral). Cette vérification est listée en question ouverte (Q1) pour seconde passe.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Recherche initiale taxe polluants 2024 — trois tarifs forfaitaires (E=0, 1=100, polluants=500) tracés à 4 sources, mécanique de classement par allumage commandé Euro 5/6 documentée, équivalence Crit'Air formalisée, 14 cas particuliers identifiés, 5 ambiguïtés documentées, 4 questions ouvertes pour missions ultérieures. |
