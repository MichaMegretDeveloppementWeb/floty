# Recherches — Taxe annuelle sur les émissions de CO₂ des véhicules de tourisme — Exercice 2024

> **Statut** : Version 0.1 — recherche initiale
> **Auteur** : Micha MEGRET (prestataire)
> **Date de rédaction** : 22 avril 2026
> **Périmètre matériel** : taxe annuelle sur les émissions de CO₂ (Prélèvement 7 de la cartographie), exercice 2024 (taxe due au titre de l'utilisation 2024, déclarée en janvier 2025)
> **Hors périmètre de cette recherche** (à traiter ultérieurement) : exonérations détaillées, abattements (E85), cas particuliers N1/M1, taxe polluants, années 2025-2026.

---

## 1. Sources consultées

Cf. fichier `sources.md` pour la bibliographie complète. Synthèse :

- **S1** — Notice officielle DGFiP n° 2857-FC-NOT-SD (Cerfa n° 52374#03, décembre 2024) — déclaration de la taxe annuelle sur les émissions de CO₂ des véhicules de tourisme. **Source primaire majeure** : reproduit textuellement les barèmes WLTP, NEDC et PA pour 2024 fixés par les articles L. 421-120, L. 421-121 et L. 421-122 du CIBS.
- **S2** — BOFiP `BOI-AIS-MOB-10-30-20-20240710` (taxes sur l'affectation des véhicules de tourisme — version 2024).
- **S3** — BOFiP `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes aux taxes d'affectation).
- **S4** — Légifrance — CIBS Section 3, art. L. 421-93 à L. 421-167, dans leur version applicable au 31/12/2023 (modifiée par LF 2024). Articles L. 421-119 à L. 421-122 (texte intégral des barèmes CO₂) consultés directement.
- **S5** — Loi de finances pour 2024 n° 2023-1322 du 29 décembre 2023 (en particulier art. 100, II, modifiant CGI 39, 1-4°).
- **S6** — service-public.gouv.fr (entreprendre) — fiche F22203 « Taxes sur l'affectation des véhicules de tourisme à des fins économiques ».
- **S7** — economie.gouv.fr — page « Entreprises : ce qu'il faut savoir sur les taxes sur l'affectation des véhicules à des fins économiques ».
- **S8** — PwC Avocats — alerte « Aménagement de la fiscalité applicable aux véhicules » (LF 2024).
- **S9** — FNA — fiche « La taxe annuelle sur les véhicules de tourisme 2024 (ex-TVS) ».
- **S10** — ANAFAGC — fiche « Taxes sur les véhicules de tourisme : y penser en janvier ! ».
- **S11** — service-public.gouv.fr — fiche F35947 « Taxe sur les émissions de CO₂ des véhicules de tourisme (malus CO₂) » (utilisée pour vérifier l'absence de confusion avec la taxe ponctuelle à l'immatriculation, hors périmètre Floty).

---

## 2. Synthèse de la législation applicable

### 2.1 Texte de référence

La taxe annuelle sur les émissions de dioxyde de carbone des véhicules de tourisme affectés à des fins économiques est régie par le **Code des Impositions sur les Biens et Services (CIBS), Livre IV (Mobilités), Section 3 « Taxes sur l'affectation des véhicules à des fins économiques »**, articles **L. 421-93 à L. 421-167**. Plus spécifiquement :

| Article CIBS | Objet |
|---|---|
| L. 421-2 | Définition du « véhicule de tourisme » (renvoi à la définition européenne M1, et à certains N1) |
| L. 421-6 | Définition de la méthode de mesure WLTP |
| L. 421-94, a du 1° | Existence et nature de la taxe annuelle CO₂ |
| L. 421-95 | Définition de l'« entreprise » et de l'affectation à des fins économiques |
| L. 421-99 | Fait générateur et redevable |
| L. 421-105 | Détermination du fait générateur (affectation au territoire de taxation) |
| L. 421-107 | Proportion annuelle d'affectation (calcul du prorata) |
| L. 421-110 | Coefficient pondérateur en cas de remboursement de frais kilométriques |
| L. 421-111 | Abattement de 15 000 € sur les frais kilométriques |
| L. 421-119 | Article chapeau des tarifs CO₂ |
| **L. 421-120** | **Barème WLTP** (taxe CO₂) |
| **L. 421-121** | **Barème NEDC** (taxe CO₂) |
| **L. 421-122** | **Barème puissance administrative** (taxe CO₂) |
| L. 421-123 à L. 421-132 | Exonérations (handicap, électrique/hydrogène, hybrides, E85, organismes sans but lucratif, entreprise individuelle, location, LCD, activités agricoles/forestières, etc.) — **hors périmètre de cette recherche, traités dans `exonerations/` et `abattements/`** |
| L. 421-141 | Renvoi exonérations LCD |
| L. 421-157 et L. 421-159 | Redevable, exigibilité |
| L. 421-163 | Pas de déclaration si montant nul |
| L. 421-164 | État récapitulatif annuel |
| L. 131-1 | Règle générale d'arrondi (à l'euro le plus proche) |
| L. 141-2 | Exigibilité au fait générateur |
| L. 171-2 | Paiement à dépôt déclaration |

**Texte législatif d'origine** : la refonte de l'ex-TVS en deux taxes annuelles distinctes (CO₂ et polluants atmosphériques) résulte de l'article 55 de la loi n° 2020-1721 du 29 décembre 2020 (LF 2021), avec entrée en vigueur au **1er janvier 2022**. La loi de finances pour 2024 (loi n° 2023-1322 du 29 décembre 2023) **durcit les barèmes WLTP, NEDC et puissance administrative à compter du 1er janvier 2024** et planifie un renforcement annuel jusqu'en 2027 — c'est cette version 2024 que nous instruisons ici.

### 2.2 Champ d'application

**Véhicules taxables** (CIBS art. L. 421-2 — S1 §I.2.a) :

- véhicules de la **catégorie M1** (voitures particulières), **autres que les véhicules à usage spécial**, mais **y compris ceux qui sont accessibles en fauteuil roulant** ;
- parmi les véhicules de la **catégorie N1** :
  - ceux dont la **carrosserie est « Camion pick-up »** et qui **comportent au moins cinq places assises** et ne sont pas exclusivement affectés à l'exploitation des remontées mécaniques et des domaines skiables ;
  - ceux dont la **carrosserie est « Camionnette »** qui comportent (ou sont susceptibles de comporter après une manipulation aisée) **au moins deux rangs de places assises** et sont **affectés au transport de personnes**.

**Territoire de taxation** : France métropolitaine, Guadeloupe, Martinique, Guyane, La Réunion, Mayotte (S1 §I.2.b). Les autres collectivités d'outre-mer sont hors champ. Pour Floty, le périmètre opérationnel se limite à la France métropolitaine (cf. cahier des charges — silence sur les DOM).

**Affectation à des fins économiques** (CIBS art. L. 421-95 — S1 §I.2.b) — un véhicule autorisé à circuler est affecté à des fins économiques s'il remplit **l'une** des conditions suivantes :

1. il est **détenu** (possédé ou pris en location de longue durée) par une entreprise et immatriculé en France ;
2. il **circule sur la voie publique** et une entreprise prend à sa charge **totalement ou partiellement** les frais engagés par une personne physique pour en disposer ou l'utiliser (cas typique : remboursement de frais kilométriques au salarié) ;
3. dans les autres cas, il **circule sur la voie publique pour les besoins de la réalisation de l'activité économique** d'une entreprise.

**Notion d'entreprise** : « personne assujettie à la TVA » au sens des articles 256 A et 256 B du CGI (S1 §I.1). La forme juridique (société, association, groupement) n'a plus d'importance depuis le passage au CIBS — c'est l'exercice d'une activité économique soumise à TVA qui déclenche la qualification d'entreprise.

**Sont réputés ne pas être affectés** (et donc non taxables, S1 §I.2.b) :
- véhicules de démonstration ou « W garage » qui ne réalisent pas d'opération de transport autre que strictement nécessaire ;
- véhicules immobilisés ou mis en fourrière à la demande des pouvoirs publics.

### 2.3 Date d'entrée en vigueur des barèmes 2024

> « **À compter du 1er janvier 2024 (taxe à acquitter en 2025)**, le tarif annuel est déterminé au moyen d'un barème associant un tarif marginal à chaque fraction des émissions de dioxyde de carbone (barème WLTP ou barème NEDC) ou à chaque fraction de puissance administrative (barème en fonction de la puissance administrative). Le tarif est alors égal à la somme des produits de chaque fraction par le tarif marginal associé. »
> — S1, partie II.1, p. 3, consulté le 22/04/2026

**Bascule majeure 2024** : le barème WLTP (et symétriquement NEDC et PA) **passe d'un tarif unique appliqué à l'ensemble des émissions** à un **barème progressif par tranches à tarif marginal**. C'est une évolution structurelle : avant 2024, on multipliait le total CO₂ par un seul taux ; depuis 2024, on calcule tranche par tranche et on somme les contributions, comme l'impôt sur le revenu. Cette mécanique est confirmée par S2 §230 et S8.

### 2.4 Date de fin d'application

Le barème 2024 s'applique à l'**exercice fiscal 2024 uniquement** (utilisation du véhicule du 01/01/2024 au 31/12/2024). À compter du 01/01/2025, un nouveau barème durci entre en vigueur (programmé par LF 2024 dans une trajectoire pluriannuelle jusqu'en 2027 ; cf. S2 §230 — « renforcement prévu chaque année jusqu'en 2027 »). Le détail 2025 sera instruit dans `2025/taxe-co2/recherches.md`.

---

## 3. Extraits pertinents

### 3.1 Définition du redevable et notion d'affectation

> « Les taxes annuelles sur les véhicules de tourisme, désormais régies par le code des impositions sur les biens et services (CIBS), sont dues par les entreprises qui détiennent des véhicules affectés à des fins économiques ou en disposent dans le cadre d'une location ou d'une mise à disposition, ou encore prennent en charge les frais d'acquisition ou d'utilisation de tels véhicules.
>
> Pour l'application de ces taxes, la notion d'entreprise suppose l'exercice d'une activité économique au sens de l'article 256 A du code général des impôts (CGI) et l'assujettissement de cette activité à la taxe sur la valeur ajoutée (TVA) en application de l'article 256 B du CGI. Ce n'est donc plus la forme juridique sous laquelle une entreprise exerce son activité (société ou assimilée) qui détermine si elle redevable. »
> — S1, p. 1, partie I.1, consulté le 22/04/2026

> « L'affectataire du véhicule [est] l'entreprise qui dispose effectivement du véhicule et non le détenteur [pour les locations] »
> — S2, §130, consulté le 22/04/2026

### 3.2 Fait générateur

> « Est considéré comme affecté à des fins économiques un véhicule qui est autorisé à circuler sur le territoire susmentionné et remplit l'une des conditions suivantes :
> - il est détenu (c'est-à-dire possédé ou pris en location de longue durée) par une entreprise et immatriculé en France ;
> - il circule sur la voie publique et une entreprise prend à sa charge totalement ou partiellement les frais engagés par une personne physique pour en disposer ou l'utiliser ;
> - dans les situations autres que les deux premières, il circule sur la voie publique pour les besoins de la réalisation de l'activité économique d'une entreprise. »
> — S1, p. 2, partie I.2.b, consulté le 22/04/2026

### 3.3 Prorata d'affectation — formule officielle

> « La proportion annuelle d'affectation du véhicule à des fins économiques est en principe le quotient entre :
> - au numérateur : la durée annuelle pendant laquelle l'entreprise redevable a été affectataire du véhicule à des fins économiques, exprimée en nombre de jours ;
> - au dénominateur, le nombre de jours de l'année civile. »
> — S1, p. 4, partie II.2.a, consulté le 22/04/2026

> « Nombre de jours au cours desquels le véhicule taxable est affecté à des fins économiques [au numérateur] ; nombre total de jours de l'année civile [au dénominateur]. »
> — S3, §160, consulté le 22/04/2026

### 3.4 Option forfaitaire trimestrielle (à mémoriser pour Floty)

> « La proportion annuelle d'affectation du véhicule à des fins économiques peut, sur option du redevable, être calculée forfaitairement sur une base trimestrielle et non plus journalière. Dans ce cas, la proportion annuelle d'affectation est le produit du pourcentage de 25 % par le nombre de périodes de trois mois d'affectation du véhicule. »
> — S1, p. 4, partie II.2.b, consulté le 22/04/2026

> Une période de trois mois d'affectation s'entend :
> — d'un trimestre civil au premier jour duquel l'entreprise détient le véhicule ;
> — de toute période au premier jour de laquelle l'entreprise affecte un véhicule à des fins économiques sans le détenir, et qui s'achève, soit à la fin du trimestre civil lorsque cette période débute au premier jour d'un trimestre civil soit, à défaut, à l'issue de quatre-vingt-dix jours consécutifs.
> — S1, p. 4, partie II.2.b

**Note importante** : cette option forfaitaire trimestrielle est **supprimée à compter du 1er janvier 2025** (S2 §300-360). Pour 2024, elle reste en vigueur. Le cahier des charges Floty ne la mentionne pas — Floty calcule **systématiquement par jours**, ce qui est l'option par défaut et la plus précise. C'est conforme et à fortiori non bloquant : le calcul journalier est le principe ; le calcul trimestriel est une option du redevable. Floty propose donc le seul calcul journalier — voir Décision 5 dans `decisions.md`.

### 3.5 Règle d'arrondi

> « Le montant total de la taxe à payer est arrondi à l'euro le plus proche. Les montants inférieurs à 0,50 euro sont ramenés à l'euro inférieur et ceux supérieurs ou égaux à 0,50 euro sont comptés pour 1. »
> — S1, p. 6, encadré « ARRONDIS FISCAUX », consulté le 22/04/2026

> « Arrondi à l'euro le plus proche, la fraction d'euro égale à 0,5 devant être comptée pour 1. »
> — S3, §150 (renvoi à CIBS L. 131-1)

> « Arrondi appliqué au niveau du montant final par véhicule/redevable, pas par ligne ou étape intermédiaire. »
> — S3, paraphrase de §150 (synthèse)

**Conclusion sur l'arrondi** : la règle est l'arrondi au montant **total** payé par le redevable ; les calculs intermédiaires (par véhicule, par tranche, après prorata) restent en valeur exacte (centimes ou plus de précision). Floty doit conserver une précision décimale en interne et n'arrondir qu'au tout dernier moment (cf. cahier des charges §7.3 « Les arrondis se font uniquement en fin de calcul (pas d'arrondis intermédiaires) » — c'est conforme).

### 3.6 Exemple officiel BOFiP — barème WLTP 2024 pour 100 g/km

> Exemple 1 (extrait BOFiP §230) :
>
> « Soit un véhicule de tourisme dont les émissions de CO₂ ont été déterminées en application de la méthode WLTP et s'élèvent à 100 g/km. Le tarif annuel applicable est :
>
> - 2024 : 14 × 0 + (55−14) × 1 + (63−55) × 2 + (95−63) × 3 + (100−95) × 4 = **173 €**
> - 2025 : 193 €
> - 2026 : 213 €
> - 2027 : 232 € »
> — S2, §230, exemple 1, consulté le 22/04/2026

**Vérification manuelle 2024** (calcul que je refais à la main pour valider ma compréhension) :
- Tranche « jusqu'à 14 g/km » (bornes [0 ; 14]) : 14 g × 0 €/g = 0 €
- Tranche « 15 à 55 » (bornes [15 ; 55], soit 41 g) : 41 × 1 = 41 €
- Tranche « 56 à 63 » (bornes [56 ; 63], soit 8 g) : 8 × 2 = 16 €
- Tranche « 64 à 95 » (bornes [64 ; 95], soit 32 g) : 32 × 3 = 96 €
- Tranche « 96 à 115 » : on ne va que jusqu'à 100, donc fraction de 5 g (de 96 à 100) : 5 × 4 = 20 €
- **Somme = 0 + 41 + 16 + 96 + 20 = 173 €** ✓ (concorde avec le BOFiP)

L'exemple BOFiP utilise une notation à intervalles « ouverts à droite » dans son écriture (« (55−14) × 1 ») mais aboutit au même résultat. Les bornes de la notice 2857-FC-NOT-SD (« De 15 à 55 », « De 56 à 63 », etc.) sont des intervalles **fermés en entiers** sans chevauchement. La logique de calcul est : pour chaque tranche [borne_inf, borne_sup], la fraction prise en compte est `min(co2, borne_sup) − (borne_inf − 1)` lorsque `co2 ≥ borne_inf`, et 0 sinon — soit au final l'écart entre `min(co2, borne_sup)` et la borne supérieure de la tranche précédente. (Voir Décision 4 dans `decisions.md` pour la formalisation précise utilisée par Floty.)

### 3.7 Exemple officiel BOFiP — prorata avec année bissextile (2024)

> Exemple 2 (extrait BOFiP §230, suite) :
>
> « Soit le même véhicule WLTP émettant 100 g/km, acquis le 1er mars 2024.
>
> - 2024 : il est affecté du 01/03/2024 au 31/12/2024, soit **306 jours**, sur une année comptant **366 jours** (année bissextile). Tarif annuel plein 2024 = 173 €. Tarif dû = 173 × 306 / 366 = **144,64 €**
> - 2025 : il est affecté du 01/01/2025 au 06/12/2025, soit 340 jours, sur 365. 193 × 340/365 = 179,78 €. »
> — S2, §230, exemple 2, consulté le 22/04/2026

**Vérification 2024** : 173 × 306 / 366 = 52 938 / 366 = 144,6393... ≈ **144,64 €** (avant arrondi final à l'euro). Si Floty arrondit au montant final dû par le redevable (et non par véhicule), ce 144,64 € reste tel quel jusqu'à l'agrégation de tous les véhicules de l'entreprise, puis est arrondi.

**Confirmation année bissextile** : l'exemple BOFiP utilise explicitement **366 jours pour 2024**. Cela tranche définitivement le traitement de l'année bissextile — voir Décision 3 dans `decisions.md`.

### 3.8 Illustration de calcul — barème puissance administrative 2024

L'article L. 421-122 du CIBS énonce que le barème PA associe « un tarif marginal à chaque fraction de la puissance administrative », formulation strictement identique à celle des barèmes WLTP (L. 421-120) et NEDC (L. 421-121). Le BOFiP § 230 confirme expressément que **les trois barèmes sont des barèmes progressifs par tranches**. La mécanique de calcul est donc identique à celle illustrée pour le WLTP au § 3.7 ci-dessus, en remplaçant « g/km » par « CV ».

**Illustration pour un véhicule de 10 CV (2024)** :

```
fraction tranche 1 (≤ 3)        : 3 CV  × 1 500 €/CV = 4 500 €
fraction tranche 2 (4-6)        : 3 CV  × 2 250 €/CV = 6 750 €
fraction tranche 3 (7-10)       : 4 CV  × 3 750 €/CV = 15 000 €
                                                       --------
                                Tarif annuel plein = 26 250 €
```

**Illustrations complémentaires** :

| Puissance | Détail du calcul | Tarif annuel plein |
|---|---|---|
| 1 CV | 1 × 1 500 | 1 500 € |
| 3 CV | 3 × 1 500 | 4 500 € |
| 6 CV | 3 × 1 500 + 3 × 2 250 | 11 250 € |
| 7 CV | 3 × 1 500 + 3 × 2 250 + 1 × 3 750 | 15 000 € |
| 11 CV | 3 × 1 500 + 3 × 2 250 + 4 × 3 750 + 1 × 4 750 | 31 000 € |
| 16 CV | 3 × 1 500 + 3 × 2 250 + 4 × 3 750 + 5 × 4 750 + 1 × 6 000 | 56 000 € |

Ces montants paraissent élevés en absolu mais sont cohérents avec l'objectif dissuasif du barème PA, qui ne s'applique que dans deux cas résiduels précisés par L. 421-119-1, 3° et BOFiP § 220 : véhicules sans réception européenne, ou véhicules déjà immatriculés et affectés à une activité économique par l'entreprise affectataire avant le 1er janvier 2006. Voir Décision 6 dans `decisions.md`.

### 3.9 Coefficient pondérateur — remboursement de frais kilométriques

Le tarif calculé en application des barèmes pour les véhicules **possédés ou pris en location par les salariés ou les dirigeants** bénéficiant d'un remboursement de frais kilométriques par l'entreprise est modulé selon ce coefficient pondérateur (S1 partie IV, ligne N) :

| Nombre de kilomètres remboursés par la société | % de la taxe à verser |
|---|---|
| De 0 à 15 000 km | 0 % |
| De 15 001 à 25 000 km | 25 % |
| De 25 001 à 35 000 km | 50 % |
| De 35 001 à 45 000 km | 75 % |
| Supérieur à 45 000 km | 100 % |
> — S1, partie IV, ligne N, consulté le 22/04/2026

Et un **abattement de 15 000 €** s'applique en plus, mais **uniquement** au montant cumulé dû au titre de ces véhicules « salariés/dirigeants », pas aux véhicules de la société (S1 partie II.3 et CIBS art. L. 421-111).

**Hors périmètre Floty (à confirmer)** : ce dispositif concerne le cas où l'entreprise rembourse à un salarié les frais kilométriques de son véhicule personnel. Dans Floty, on suit des véhicules **détenus par la société de location** et **affectés** par mise à disposition à une entreprise utilisatrice — pas des véhicules personnels de salariés. Le coefficient pondérateur et l'abattement de 15 000 € **ne s'appliquent donc pas** aux véhicules Floty. À documenter explicitement dans `cas-particuliers/`.

### 3.10 Modalités de déclaration et de paiement

**Régime réel normal de TVA ou non-redevables TVA** (S1 partie III) :
- Déclaration sur **annexe n° 3310 A** à la déclaration de TVA
- À déposer au cours du mois de **janvier** suivant la période d'imposition
- Date limite : 25 janvier pour les non-redevables TVA

**Régime simplifié de TVA (RSI)** :
- Déclaration sur **formulaire n° 3517**
- Déposé au titre de l'exercice au cours duquel la taxe est devenue exigible

**Fiche d'aide au calcul** : formulaire n° 2857-FC-SD (référencé par la notice S1) — non joint à la déclaration mais peut être demandé par l'administration. C'est précisément le document que Floty génère (cf. cahier des charges §5.7 « Récapitulatif fiscal PDF »).

**Pas de déclaration si montant nul** (CIBS art. L. 421-163, S3 §380) : si après application des exonérations le total dû est nul, aucune déclaration n'est requise.

**État récapitulatif annuel** (CIBS art. L. 421-164, S3 §400-410) : à tenir à jour, non joint à la déclaration, mais communiqué sur demande de l'administration. Doit mentionner pour chaque véhicule : immatriculation, caractéristiques techniques, conditions d'affectation, périodes, exonérations applicables. **C'est exactement le contenu du PDF récapitulatif Floty** — Floty doit s'assurer que toutes ces informations apparaissent dans son export.

---

## 4. Valeurs numériques relevées — Barèmes 2024

### 4.1 Barème WLTP 2024 (CIBS art. L. 421-120)

Source : S1 partie IV, ligne G.1 (notice officielle DGFiP 2857-FC-NOT-SD).

| # tranche | Fraction des émissions de CO₂ (g/km) | Tarif marginal (€/g de la fraction) |
|---|---|---|
| 1 | Jusqu'à 14 | 0 |
| 2 | De 15 à 55 | 1 |
| 3 | De 56 à 63 | 2 |
| 4 | De 64 à 95 | 3 |
| 5 | De 96 à 115 | 4 |
| 6 | De 116 à 135 | 10 |
| 7 | De 136 à 155 | 50 |
| 8 | De 156 à 175 | 60 |
| 9 | À partir de 176 | 65 |

**Champ d'application du barème WLTP 2024** : véhicules immatriculés en recourant à la méthode de détermination des émissions de CO₂ dite WLTP au sens de l'article L. 421-6 du CIBS. En pratique : **véhicules pour lesquels la première immatriculation en France est délivrée à compter du 1er mars 2020**, à l'exception des véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées (S1 partie II.1).

**Mécanique de calcul** : barème progressif à tarif marginal, par fractions de g/km. Pour chaque tranche, on calcule la fraction des émissions du véhicule comprise dans la tranche, et on multiplie par le tarif marginal. Le tarif annuel plein est la somme de ces produits.

**Croisement** : valeurs identiques entre S1 (notice DGFiP), S2 (BOFiP exemple §230 — confirme tranches 1, 2, 3, 4 et 5 implicitement par le calcul du véhicule 100 g/km) et S8 (PwC) (confirmation indépendante). **Confiance : haute.**

### 4.2 Barème NEDC 2024 (CIBS art. L. 421-121)

Source : S1 partie IV, ligne G.2 (notice officielle DGFiP 2857-FC-NOT-SD).

| # tranche | Fraction des émissions de CO₂ (g/km) | Tarif marginal (€/g de la fraction) |
|---|---|---|
| 1 | Jusqu'à 12 | 0 |
| 2 | De 13 à 45 | 1 |
| 3 | De 46 à 52 | 2 |
| 4 | De 53 à 79 | 3 |
| 5 | De 80 à 95 | 4 |
| 6 | De 96 à 112 | 10 |
| 7 | De 113 à 128 | 50 |
| 8 | De 129 à 145 | 60 |
| 9 | À partir de 146 | 65 |

**Champ d'application du barème NEDC 2024** : véhicules ayant fait l'objet d'une réception européenne, immatriculés pour la première fois **à compter du 1er juin 2004** et **n'étaient pas affectés à des fins économiques sur le territoire de taxation par l'entreprise affectataire avant le 1er janvier 2006** (S1 partie II.1, second alinéa).

**Mécanique de calcul** : identique au WLTP (barème progressif à tarif marginal).

**Croisement** : la notice S1 est ici la source primaire la plus précise et exhaustive ; le BOFiP (S2) ne reproduit pas explicitement les tranches NEDC mais confirme l'existence et la structure du barème (renvoi à L. 421-121 CIBS). **Confiance : haute** (source primaire DGFiP).

### 4.3 Barème puissance administrative 2024 (CIBS art. L. 421-122)

Sources : S4 (texte intégral de l'article L. 421-122 CIBS au 31/12/2023, modifié par LF 2024 art. 97) ; S1 (notice officielle DGFiP 2857-FC-NOT-SD, partie IV, ligne G.3) ; S2 (BOFiP § 230) pour la mécanique.

| # tranche | Fraction de la puissance administrative (CV) | Tarif marginal (€) |
|---|---|---|
| 1 | Jusqu'à 3 | 1 500 |
| 2 | De 4 à 6 | 2 250 |
| 3 | De 7 à 10 | 3 750 |
| 4 | De 11 à 15 | 4 750 |
| 5 | À partir de 16 | 6 000 |

**Champ d'application** : tous les véhicules ne relevant ni du barème WLTP ni du barème NEDC. Soit :
- véhicules sans réception européenne (anciens, véhicules importés non homologués sur cycles WLTP/NEDC)
- véhicules immatriculés pour la première fois **avant le 1er juin 2004**
- véhicules immatriculés à compter du 1er juin 2004 mais qui étaient **déjà affectés à des fins économiques par l'entreprise affectataire avant le 1er janvier 2006**
- véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées (selon ni WLTP ni NEDC)

**Mécanique de calcul** : barème progressif à tarif marginal **identique au WLTP et au NEDC**. CIBS art. L. 421-122 énonce : « Le barème en puissance administrative associant un tarif marginal à chaque fraction de la puissance administrative ». La doctrine BOFiP confirme expressément que les trois barèmes (WLTP, NEDC, PA) sont des « barèmes progressifs par tranches » (BOI-AIS-MOB-10-30-20 § 230). Le calcul transpose donc strictement la mécanique WLTP en remplaçant « g/km » par « CV » :

```
tarif_annuel_pa = Σ_tranches  (CV inclus dans la tranche × tarif_marginal_de_la_tranche)
```

Soit, formellement :
```
pour chaque tranche [borne_inf, borne_sup] avec tarif_marginal :
    fraction = max(0, min(puissance_admin, borne_sup) - borne_basse_precedente)
    tarif_annuel_pa += fraction × tarif_marginal
```

**Vérification numérique — toutes les puissances de 1 à 16 CV** :

| Puissance | Décomposition | Tarif annuel plein |
|---|---|---|
| 1 CV | 1 × 1 500 | 1 500 € |
| 3 CV | 3 × 1 500 | 4 500 € |
| 4 CV | 3 × 1 500 + 1 × 2 250 | 6 750 € |
| 5 CV | 3 × 1 500 + 2 × 2 250 | 9 000 € |
| 6 CV | 3 × 1 500 + 3 × 2 250 | 11 250 € |
| 7 CV | 3 × 1 500 + 3 × 2 250 + 1 × 3 750 | 15 000 € |
| 10 CV | 3 × 1 500 + 3 × 2 250 + 4 × 3 750 | 26 250 € |
| 11 CV | + 1 × 4 750 | 31 000 € |
| 15 CV | 3 × 1 500 + 3 × 2 250 + 4 × 3 750 + 5 × 4 750 | 50 000 € |
| 16 CV | + 1 × 6 000 | 56 000 € |

Ces résultats sont cohérents avec l'objectif dissuasif du barème PA : il ne s'applique que dans deux cas résiduels (véhicules sans réception européenne, ou véhicules déjà affectés à une activité économique par l'entreprise affectataire avant le 1er janvier 2006 — CIBS art. L. 421-119-1, 3° et BOFiP § 220) et cible des véhicules anciens fortement motorisés.

**Confiance : haute** — convergence du texte de l'article L. 421-122 CIBS, de la doctrine BOFiP § 230, et du cahier des charges Floty (§ 5.2, note finale) qui énonçait déjà cette mécanique en toutes lettres. Voir Décision 6 dans `decisions.md`.

---

## 5. Cas particuliers identifiés dans les sources

> Ces cas particuliers sont identifiés ici à titre informatif (méthodologie §3.1 — exhaustivité). Leur instruction détaillée ne fait **pas** partie de cette mission. Renvois vers les sous-dossiers cibles indiqués.

### 5.1 Véhicules accessibles en fauteuil roulant
- Statut : **inclus dans le champ de la taxe** (S1 §I.2.a — explicitement « y compris ceux qui sont accessibles en fauteuil roulant »), mais **exonérés** au titre de CIBS art. L. 421-123 (S1 §I.3).
- Renvoi : à instruire dans `2024/exonerations/`.

### 5.2 Véhicules électriques, hydrogène, électriques+hydrogène
- Statut : **dans le champ** mais **exonérés** au titre de CIBS art. L. 421-124 (S1 §I.3).
- Renvoi : à instruire dans `2024/exonerations/`.

### 5.3 Véhicules hybrides — exonération conditionnelle 2024
- L'exonération hybride pour 2024 (CIBS art. L. 421-125) couvre les véhicules dont la source d'énergie combine soit (a) électricité ou hydrogène + (gaz naturel, GPL, essence, ou superéthanol E85), soit (b) gaz naturel ou GPL + (essence ou superéthanol E85), ET dont les émissions de CO₂ n'excèdent pas certains seuils (60 g/km WLTP, 50 g/km NEDC, 3 CV PA), avec aménagements temporaires sur 3 ans pour le double de ces seuils (S1 §I.3, dernier paragraphe).
- **Note importante** : cette exonération hybride est **supprimée à partir du 1er janvier 2025** (S2). Elle existe **uniquement pour 2024**. À traiter en priorité haute dans `2024/exonerations/`.
- Renvoi : à instruire dans `2024/exonerations/`.

### 5.4 Abattement E85
- Le cahier des charges (§5.6) mentionne « abattement E85 de 40 % à compter du 1er janvier 2025 ». La cartographie phase 0 mentionne pour 2024 « abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) » — il y a divergence sur l'année d'application.
- **Vérification dans S1** : la notice 2024 ne mentionne pas d'abattement E85 (les véhicules E85 entrent uniquement dans le mécanisme d'exonération hybride § L. 421-125 — voir 5.3 ci-dessus, qui couvre l'E85 en combinaison avec l'électrique/hydrogène/GNV/GPL).
- **Conclusion provisoire** : pour 2024, **pas d'abattement E85 isolé** — seule l'exonération hybride § L. 421-125 (qui peut couvrir des véhicules combinant E85 et autre énergie) s'applique. L'abattement E85 de 40 % introduit à partir du 1er janvier 2025 est une mesure nouvelle (loi de finances 2025).
- Renvoi : à instruire dans `2024/abattements/`. Question ouverte (cf. §7).

### 5.5 Personnes physiques en nom propre (entrepreneurs individuels)
- Statut : **exonérés** au titre de CIBS art. L. 421-127 (S1 §I.3).
- Renvoi : `2024/exonerations/`. Faiblement pertinent pour Floty (les entreprises utilisatrices sont des sociétés).

### 5.6 Véhicules en location de courte durée (LCD) — moins d'1 mois civil ou 30 jours consécutifs
- Statut : **exonérés** au titre de CIBS art. L. 421-129 (S1 §I.3). Le redevable bascule sur le **locataire** dans ce cas (S2 §140).
- **Important pour Floty** : la mise à disposition des véhicules de la société de location aux entreprises utilisatrices se fait dans le cadre de **location longue durée** ou de **mise à disposition** (cf. cahier des charges §1.1 — montage de groupe inter-sociétés). Ce n'est PAS de la LCD au sens fiscal. Donc l'exonération LCD ne s'applique pas et la taxe est bien due par les entreprises utilisatrices, comme prévu.
- Renvoi : `2024/exonerations/` et `2024/cas-particuliers/`.

### 5.7 Véhicules détenus par les loueurs (société de location elle-même)
- Statut : **exonération CIBS art. L. 421-128** — « les véhicules exclusivement affectés soit à la location, soit à la mise à disposition temporaire de clients en remplacement de leur véhicule immobilisé » (S1 §I.3).
- **Implication pour Floty** : la société de location de Renaud, qui détient les véhicules et les met à disposition des entreprises utilisatrices, **est exonérée en tant que loueur**. Confirmé par le cahier des charges §5.1 : « la société de location qui détient les véhicules est exonérée en tant que loueur (article L. 421-128 du CIBS) ». **Ce sont les entreprises utilisatrices qui sont redevables**, au prorata de leur durée d'utilisation. C'est précisément le modèle Floty.
- Renvoi : `2024/exonerations/`.

### 5.8 Activités exonérées (agricoles/forestières, transport public personnes, conduite, compétitions)
- Statut : **exonérés** au titre de CIBS art. L. 421-130 à L. 421-132 (S1 §I.3).
- Renvoi : `2024/exonerations/`. Marginal pour Floty.

### 5.9 Frontière M1/N1 (pick-ups, camionnettes 5+ places)
- Le périmètre véhicules taxables CIBS art. L. 421-2 inclut explicitement certains N1 « pick-up ≥ 5 places assises » et « camionnette ≥ 2 rangs de places ». Le contour précis (champ J.1 carte grise, conversion technique, cas frontière) est complexe.
- Renvoi : `2024/cas-particuliers/`. Déjà identifié comme zone d'incertitude par la cartographie phase 0 (§6.6).

### 5.10 Véhicules acquis en cours d'année / cédés en cours d'année
- Cas standard et bien documenté (S3 §190 : exemple acquisition 31 janvier, cession 30 novembre = 304 jours / 365 = 83,3 %). Aucune subtilité — le calcul du prorata se fait par décompte exact des jours d'affectation.
- **C'est exactement le mode de calcul Floty** (par décompte des attributions journalières).

### 5.11 Mise en fourrière / immobilisation pouvoirs publics
- Réduction du prorata à proportion (S3 §190 : « 15 jours en fourrière = 350 / 365 = 95,9 % »).
- Pour Floty : l'application gère déjà la notion d'« indisponibilité » (cahier des charges §2.5). Une indisponibilité de type « fourrière / immobilisation administrative » devrait **réduire le numérateur du prorata** (jours non comptés comme affectation à l'entreprise). À confirmer — voir Question ouverte §7.

### 5.12 Indemnités kilométriques aux salariés (coefficient pondérateur + abattement 15 000 €)
- Mécanique distincte (S1 partie II.3, S3 §260-280, CIBS L. 421-110 et L. 421-111).
- **Pas applicable à Floty V1** — Floty couvre des véhicules détenus et affectés par la société de location, pas des remboursements kilométriques de véhicules personnels de salariés. À mentionner dans `cas-particuliers/` pour exhaustivité.

---

## 6. Divergences ou ambiguïtés rencontrées

### 6.1 Ambiguïté #1 — Date d'entrée en vigueur des nouveaux barèmes 2024

**Constat** : la notice S1 dit « **À compter du 1er janvier 2024 (taxe à acquitter en 2025)** ». Cette formulation pourrait laisser entendre que le barème 2024 est appliqué à des affectations 2024 mais déclaré et acquitté en 2025 — ce qui est correct.

**Précision** : l'année 2024 est la **période de taxation** (durée d'affectation prise en compte = jours du 01/01/2024 au 31/12/2024). La déclaration et le paiement interviennent en janvier 2025. C'est conforme au principe « taxe annuelle à terme échu ».

**Pas d'ambiguïté résiduelle** : c'est cohérent. Mention pour traçabilité.

### 6.2 Ambiguïté #2 — Tranche WLTP « De 96 à 115 » et exemple 100 g/km

**Constat** : dans l'exemple BOFiP (S2) pour 100 g/km, la dernière contribution est calculée comme `(100−95) × 4 = 20 €`, ce qui correspond à 5 g traités au tarif marginal de 4 €/g (tranche « De 96 à 115 »).

**Vérification de cohérence** : la notice S1 écrit la tranche comme « De 96 à 115 ». L'exemple BOFiP l'écrit comme « (100−95) × 4 », ce qui suppose une borne inférieure à 95 (exclu) et inclusion de 96. C'est strictement équivalent : la fraction de CO₂ comprise dans [96 ; 100] = 5 g, qui se calcule indifféremment comme `(100 − 95)` ou `(100 − 96 + 1)`.

**Pas d'ambiguïté** : les deux notations sont mathématiquement équivalentes. Le calcul est correct.

### 6.3 Ambiguïté #3 — Cas où la donnée CO₂ est manquante mais véhicule WLTP-éligible

**Constat** : la notice S1 partie II.1 précise « véhicules pour lesquels la première immatriculation en France est délivrée à compter du 1er mars 2020, **à l'exception des véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées** ». Ces véhicules basculent alors sur le barème puissance administrative.

**Cas Floty** : à anticiper si une saisie de véhicule omet le CO₂ tout en ayant une date d'immat. ≥ 01/03/2020. Le moteur de calcul doit basculer automatiquement sur le barème PA (sous réserve que la puissance administrative soit renseignée).

**Renvoi** : à traiter dans `2024/cas-particuliers/`.

### 6.4 Ambiguïté #4 — Définition exacte de « location longue durée »

**Constat** : la notice S1 oppose location longue durée (LLD, dans laquelle l'entreprise « détient » le véhicule au sens fiscal) et location courte durée (LCD, exonérée car redevable = locataire). La frontière LCD/LLD est définie par CIBS art. L. 421-129 : « période d'au plus un mois civil ou trente jours consécutifs ».

**Cas Floty** : la mise à disposition par la société de location aux entreprises utilisatrices se fait pour des durées variables, parfois inférieures à 30 jours. **Question** : si une entreprise utilisatrice utilise un véhicule pendant 3 jours dans le mois, est-ce de la LCD (et donc exonéré) ou de la LLD (et donc taxé) ?

**Lecture probable** : la durée de référence pour LCD/LLD est la **durée du contrat de location**, pas la durée d'usage effectif. Si le contrat-cadre Renaud / entreprise utilisatrice porte sur une mise à disposition pluriannuelle (avec usage discontinu), c'est de la LLD. Mais si chaque attribution journalière dans Floty est un contrat distinct ≤ 30 jours, ça pourrait basculer en LCD.

**Renvoi** : question majeure à instruire dans `2024/cas-particuliers/` et à valider avec l'expert-comptable. **À considérer comme zone d'incertitude active** (cf. §7).

---

## 7. Questions ouvertes

### Q1 — Abattement E85 en 2024
La cartographie mentionne « abattement E85 de 40 % en 2024 », le cahier des charges dit « à compter du 1er janvier 2025 », la notice S1 ne mentionne aucun abattement E85 pour 2024 (seulement l'exonération hybride § L. 421-125 qui peut englober des E85 en combinaison). **À trancher dans `2024/abattements/`.** Hypothèse de travail provisoire (à valider) : pas d'abattement E85 isolé en 2024.

### Q2 — Qualification LLD vs LCD pour la mise à disposition Floty
Voir §6.4. Si l'attribution journalière est requalifiée en LCD, l'exonération CIBS L. 421-129 s'appliquerait et la taxe ne serait pas due par l'entreprise utilisatrice. **À instruire dans `2024/cas-particuliers/`** et impérativement à faire valider par l'expert-comptable. **Impact potentiel : annulation totale du calcul Floty pour ces entreprises.**

### Q3 — Indisponibilité « fourrière » comme réduction du prorata
Le BOFiP S3 §190 précise que la mise en fourrière à demande des pouvoirs publics **réduit** le prorata (jours non comptés comme affectation). **Question pour Floty** : faut-il ajouter un type d'indisponibilité « fourrière publique » qui réduit le prorata fiscal, distinct des indisponibilités opérationnelles (maintenance, sinistre privé) qui n'ont pas d'effet fiscal et restent une utilisation par l'entreprise (pas d'extinction de l'affectation) ? **À instruire dans `2024/cas-particuliers/`**.

### Q4 — Cas frontière M1/N1 (pick-up 5 places, camionnette 2 rangs)
Voir §5.9. Critères techniques précis (carrosserie carte grise, places assises, transport personnes vs marchandises). À instruire dans `2024/cas-particuliers/`.

### Q5 — Cas véhicule WLTP-éligible mais sans donnée CO₂ disponible
Voir §6.3. Bascule sur barème PA, à formaliser dans Floty. À instruire dans `2024/cas-particuliers/`.

### Q6 — Véhicule d'occasion importé (immat. étrangère antérieure à 1ère immat. France)
La date pivot pour WLTP/NEDC est la **date de première immatriculation en France**, pas la date de première immatriculation tout court. Pour un véhicule importé ayant une 1ère immatriculation étrangère en 2018 (avant 01/03/2020) puis une 1ère immatriculation France en 2022, est-ce WLTP ou NEDC ? **Lecture par défaut** : la notice et le BOFiP raisonnent sur la **méthode d'homologation effective** du véhicule (WLTP ou NEDC), donc si le véhicule a été homologué WLTP à l'origine il garde son barème WLTP indépendamment de la date France. À confirmer. **À instruire dans `2024/cas-particuliers/`.**

### Q7 — Véhicule mis hors-service en cours d'année (vente, destruction)
Pas d'ambiguïté légale (jours d'affectation décomptés exactement, prorata réduit), mais point UX Floty : faut-il proposer une fin d'attribution automatique à la date de sortie de flotte ? Hors périmètre fiscal pur, mais à mémoriser pour la conception applicative.

---

## 8. Limites de la recherche

### 8.1 Périmètre temporel de la mission

Cette recherche se limite **strictement à 2024**. Les barèmes 2025, 2026, 2027 (mentionnés en passant dans S2 §230) ne sont pas instruits ici — ils feront l'objet de leurs propres recherches dans `2025/taxe-co2/` et `2026/taxe-co2/`.

### 8.2 Hors périmètre matériel

Sont volontairement exclus de cette recherche (renvoyés à d'autres sous-dossiers) :
- exonérations détaillées (handicap, électrique, hybride, organismes sans but lucratif, etc.) → `2024/exonerations/`
- abattements (E85 si applicable 2024) → `2024/abattements/`
- cas particuliers de qualification véhicule (M1/N1, frontière LCD/LLD, importation) → `2024/cas-particuliers/`
- taxe polluants atmosphériques → `2024/taxe-polluants/`
- coefficient pondérateur frais kilométriques (théoriquement applicable mais hors usage Floty)

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Recherche initiale taxe CO₂ 2024 — barèmes WLTP/NEDC/PA exhaustifs, croisement BOFiP, 7 questions ouvertes pour missions ultérieures. |
| 0.2 | 23/04/2026 | Micha MEGRET | Vérification croisée des articles CIBS L. 421-119 à L. 421-122 directement sur Légifrance. La mécanique du barème PA est confirmée identique à WLTP/NEDC (tarif marginal × fraction), conformément au texte de l'article L. 421-122 et à la doctrine BOFiP § 230. La lecture du cahier des charges § 5.2 (note finale) est correcte. § 4.3 réécrit (tableaux et exemples chiffrés mis à jour). § 6.1 et § 6.2 (divergences alléguées avec le cahier des charges) supprimées et § 6.3-§ 6.6 renumérotées en § 6.1-§ 6.4. § 8.1 (limites d'accès aux sources) supprimée car caduque, § 8.2-§ 8.3 renumérotées. |
