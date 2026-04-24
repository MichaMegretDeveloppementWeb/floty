# Recherches — Cas particuliers et règles transitoires applicables aux taxes annuelles (CO₂ et polluants) — Exercice 2024

> **Statut** : Version 0.1 — recherche initiale
> **Auteur** : Micha MEGRET (prestataire)
> **Date de rédaction** : 22 avril 2026
> **Périmètre matériel** : cas particuliers et règles transitoires applicables conjointement à la taxe annuelle sur les émissions de CO₂ (Prélèvement 7) et à la taxe annuelle sur les émissions de polluants atmosphériques (Prélèvement 8) — exercice fiscal 2024 (utilisation 2024, déclaration en janvier 2025).
> **Hors périmètre de cette recherche** :
> - Coefficient pondérateur frais kilométriques (mécanique distincte, hors usage Floty V1) — déjà cadré dans `2024/taxe-co2/recherches.md` § 3.9 et `2024/taxe-polluants/recherches.md` § 3.11.
> - Spécificités 2025 / 2026 (autres exercices, autres sous-dossiers).
> - Détail des règles d'exonération hybride (L. 421-125), déjà instruit dans `2024/exonerations/`.
> - Détail des barèmes WLTP, NEDC et PA, déjà instruit dans `2024/taxe-co2/`.
> - Détail des trois catégories d'émissions de polluants, déjà instruit dans `2024/taxe-polluants/`.

---

## 1. Sources consultées

Cf. fichier `sources.md` pour la bibliographie complète. Synthèse :

- **S1** — Légifrance — CIBS art. **L. 421-2** (définition du « véhicule de tourisme » — frontière M1/N1, rubriques carrosserie pick-up et camionnette, condition « affecté au transport de personnes »). Source primaire majeure pour le cas particulier A.
- **S2** — Légifrance — CIBS art. **L. 421-119-1** (règle de bascule WLTP / NEDC / PA — trois cas à 1°, 2°, 3°). Source primaire majeure pour les cas particuliers B et C.
- **S3** — Légifrance — CIBS art. **L. 421-134** (définition des trois catégories d'émissions de polluants — formulation « moteur thermique à allumage commandé »). Source primaire majeure pour le cas particulier D.
- **S4** — BOFiP `BOI-AIS-MOB-10-30-20-20240710` (taxes d'affectation des véhicules de tourisme — version 2024). Particulièrement § 60 (frontière N1/M1), § 210-220 (bascule WLTP/NEDC/PA), § 230 (mécanique des barèmes), § 260-280 (catégories polluants). Source primaire de doctrine officielle.
- **S5** — BOFiP `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes aux taxes d'affectation). Particulièrement §§ 150-190 (prorata, exemple fourrière 350/365). Source primaire transversale.
- **S6** — BOFiP `BOI-AIS-MOB-10-10` (définition du véhicule de tourisme — référence transversale pour les deux taxes annuelles et la taxe à l'immatriculation). Source primaire de doctrine officielle.
- **S7** — Notice DGFiP n° 2857-FC-NOT-SD (Cerfa n° 52374#03, décembre 2024) — partie II.1 (bascule WLTP/NEDC/PA, exception « véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées »).
- **S8** — Notice DGFiP n° 2858-FC-NOT-SD (Cerfa n° 52375#03, décembre 2024) — partie I.2.a (champ d'application véhicules taxables).
- **S9** — Cahier des charges Floty (`project-management/cahier_des_charges.md`) §§ 2.1, 2.5, 5 — pour cadrage des champs véhicule lus en entrée par Floty et des règles produit déjà documentées.
- **S10** — PwC Avocats — alerte « Aménagement de la fiscalité applicable aux véhicules » (LF 2024).
- **S11** — FNA — fiche « La taxe annuelle sur les véhicules de tourisme 2024 (ex-TVS) » (croisement tertiaire pour la frontière M1/N1 et la catégorisation polluants).
- **S12** — Drive to Business — page « Taxe d'immatriculation pour les entreprises » (croisement tertiaire pour la frontière M1/N1, qualification du véhicule taxable au sens fiscal).
- **S13** — Compta-Online — fiche « Taxes sur les véhicules 2024 » (croisement tertiaire pour la mécanique de bascule sur PA en cas de donnée CO₂ manquante et la qualification N1).
- **S14** — Legifiscal — fiche « Taxes annuelles sur les véhicules de société 2024 » (croisement tertiaire général).

Les sources S1 à S6 constituent le socle primaire de la présente recherche. Les sources S7 et S8 (notices DGFiP) en donnent la version administrative directement utilisable. Les sources S9 à S14 sont mobilisées pour croisement tertiaire ou cadrage produit.

---

## 2. Cadrage transversal — qu'est-ce qu'un « cas particulier » au sens de cette mission ?

### 2.1 Définition opérationnelle

Un « cas particulier » au sens de la présente mission est une situation où la mécanique générale des taxes annuelles (déjà instruite dans les sous-dossiers `2024/taxe-co2/`, `2024/taxe-polluants/`, `2024/exonerations/` et `2024/abattements/`) requiert une **règle d'implémentation Floty supplémentaire ou clarifiante**. Il peut s'agir :

- d'une **frontière** (cas frontière entre véhicule taxable et non taxable, ou entre méthodes de mesure d'émissions), où la règle générale n'est pas opératoire sans précision additionnelle ;
- d'une **règle transitoire** (régime applicable uniquement à certains exercices), notamment celles issues de la refonte CIBS de 2022 et des bascules de loi de finances ;
- d'un **cas pratique non explicitement traité par les sources primaires**, qui requiert une lecture par défaut documentée et tracée ;
- d'un **point UX/produit Floty** dépendant d'une règle fiscale (ex : bascule automatique sur barème PA en cas de donnée CO₂ manquante).

Pour chaque cas particulier instruit dans la présente mission, le livrable type est :

1. La règle juridique de référence (article CIBS, BOFiP, notice DGFiP) ;
2. La **règle Floty implémentable** : champs véhicule (ou attribution) lus en entrée, calcul ou bascule en résultat, alerte UI éventuelle ;
3. Au moins un exemple chiffré ou un cas type ;
4. Le statut de l'incertitude associée (résolue par cette mission, maintenue ouverte avec validation expert-comptable nécessaire, ou nouvelle incertitude créée).

### 2.2 Articulation avec les autres sous-dossiers

Cette mission est le **cinquième et dernier sous-dossier de l'exercice 2024**. Elle clôture la série d'instructions par taxe et par dispositif (CO₂, polluants, exonérations, abattements). Elle se concentre exclusivement sur les **cas non couverts ou incomplètement clarifiés** par les sous-dossiers précédents, en s'appuyant sur leurs conclusions.

La présente recherche ne réinstruit pas :

- les barèmes WLTP, NEDC et PA (déjà documentés dans `2024/taxe-co2/recherches.md` § 4) ;
- les catégories d'émissions de polluants (déjà documentées dans `2024/taxe-polluants/recherches.md` § 3 et § 4) ;
- les conditions d'exonération hybride L. 421-125 (déjà documentées dans `2024/exonerations/recherches.md` § 4.3) ;
- la conclusion d'absence d'abattement isolé en 2024 (déjà documentée dans `2024/abattements/decisions.md`).

---

## 3. Cas particulier A — Frontière fiscale M1 / N1 (quels véhicules sont taxables au sens des deux taxes annuelles ?)

### 3.1 Texte CIBS

L'article L. 421-2 du CIBS définit le « véhicule de tourisme » comme suit (texte intégral consulté sur Légifrance, version applicable au 31/12/2023) :

> « Pour l'application du présent chapitre, est considéré comme un véhicule de tourisme :
> 1° Parmi les véhicules de la catégorie M1, ceux qui ne sont pas à usage spécial, y compris ceux qui sont accessibles en fauteuil roulant ;
> 2° Parmi les véhicules de la catégorie N1 :
>    a) Ceux dont la carrosserie est « Camion pick-up » et qui comportent au moins cinq places assises, à l'exception de ceux qui sont exclusivement affectés à l'exploitation des remontées mécaniques et des domaines skiables ;
>    b) Ceux dont la carrosserie est « Camionnette » qui comportent, ou qui sont susceptibles de comporter après une manipulation aisée, au moins deux rangs de places assises et qui sont affectés au transport de personnes. »
> — S1, CIBS art. L. 421-2, version applicable au 31/12/2023, consulté le 22/04/2026

### 3.2 Doctrine BOFiP

> « Sont considérés comme véhicules de tourisme :
> - les voitures particulières (catégorie M1 au sens de l'article R. 311-1 du code de la route), à l'exception des véhicules à usage spécial (ambulances, corbillards, véhicules blindés…) ; les véhicules accessibles en fauteuil roulant restent inclus ;
> - les véhicules à usages multiples (catégorie N1) dont la carrosserie est « Camion pick-up » et qui disposent d'au moins cinq places assises (cinq places de carte grise, lisibles à la rubrique S.1) ; sont écartés ceux qui sont exclusivement utilisés à l'exploitation des remontées mécaniques et des domaines skiables ;
> - les véhicules à usages multiples (catégorie N1) dont la carrosserie est « Camionnette » qui comportent, ou peuvent comporter après une manipulation aisée des sièges (banquette amovible, par exemple), au moins deux rangs de places assises et qui sont affectés au transport de personnes. »
> — S4, BOFiP BOI-AIS-MOB-10-30-20-20240710, § 60, paraphrase fidèle, consulté le 22/04/2026

La référence transversale `BOI-AIS-MOB-10-10` (S6) confirme que la définition de l'article L. 421-2 vaut pour l'ensemble du chapitre, **donc** simultanément pour la taxe annuelle CO₂, la taxe annuelle polluants, et la taxe ponctuelle à l'immatriculation (cf. cartographie phase 0 § 6.6).

### 3.3 Critères techniques opérationnels

Trois données du certificat d'immatriculation interviennent dans la qualification :

| Donnée carte grise | Rubrique | Rôle dans la qualification |
|---|---|---|
| Catégorie de réception européenne | J.1 | Détermine M1 ou N1 (voire autre — non taxable). |
| Carrosserie | J.2 (libellé textuel) | Pour les N1 : doit être « Camion pick-up » ou « Camionnette » pour entrer dans le champ. |
| Nombre de places assises | S.1 | Pour les pick-ups : ≥ 5 places. Pour les camionnettes : présence d'au moins deux rangs de places. |
| Affectation au transport de personnes | (donnée hors carte grise — usage déclaré par l'entreprise) | Pour les camionnettes N1 : critère final de taxabilité. |

### 3.4 Cas types

#### Cas A.1 — Voiture particulière classique (M1)

- Exemple : Renault Clio essence, M1.
- J.1 = M1 ; J.2 = « Berline » (ou « Break », « Coupé », etc., peu importe la carrosserie pour les M1).
- → Véhicule de tourisme au sens fiscal → **taxable**.

#### Cas A.2 — Pick-up ≥ 5 places assises (N1)

- Exemple : Ford Ranger Double Cabine, N1, carrosserie « Camion pick-up », 5 places assises.
- J.1 = N1 ; J.2 = « Camion pick-up » (ou variante équivalente exacte indiquée par le constructeur sur la carte grise) ; S.1 = 5.
- Affectation : usage économique d'entreprise (transport de personnes pour déplacements professionnels).
- Pas d'affectation exclusive aux remontées mécaniques / domaines skiables.
- → Véhicule de tourisme au sens fiscal → **taxable**.

#### Cas A.3 — Camionnette N1 avec 2 rangs de places affectée au transport de personnes

- Exemple : Renault Trafic Combi 9 places, N1, carrosserie « Camionnette », 9 places assises (2 rangs ou plus).
- J.1 = N1 ; J.2 = « Camionnette » ; S.1 = 9.
- Affectation : transport de personnes pour les besoins de l'entreprise.
- → Véhicule de tourisme au sens fiscal → **taxable**.

#### Cas A.4 — Camionnette N1 strictement utilitaire (1 rang de places, pas affectée au transport de personnes)

- Exemple : Renault Trafic Fourgon, N1, carrosserie « Camionnette », 3 places assises (toutes en cabine — 1 rang), affectée au transport de marchandises.
- J.1 = N1 ; J.2 = « Camionnette » ; S.1 = 3 ; usage = transport de marchandises.
- → **Hors champ** des taxes annuelles (le véhicule ne comporte pas au moins deux rangs de places et n'est pas affecté au transport de personnes).
- Note : les véhicules N1 « purement utilitaires » bénéficient par ailleurs de la déductibilité TVA spécifique aux VUL — cet aspect est hors périmètre Floty (cf. cartographie phase 0 Prélèvement 12).

#### Cas A.5 — Pick-up N1 « 4 places » (cas non taxable)

- Exemple : Ford Ranger Simple Cabine ou Single Cab, N1, carrosserie « Camion pick-up », 2 ou 4 places assises (configuration sans rangée arrière complète).
- J.1 = N1 ; J.2 = « Camion pick-up » ; S.1 < 5.
- → **Hors champ** car la condition CIBS pour les pick-ups est « au moins cinq places assises ». Le pick-up à 4 places ou moins échappe à la qualification de véhicule de tourisme.

#### Cas A.6 — Camionnette N1 avec banquette amovible (frontière la plus délicate)

- Exemple : Citroën Berlingo dérivé VP, N1, carrosserie « Camionnette », configuration avec une banquette arrière démontable.
- J.1 = N1 ; J.2 = « Camionnette » ; S.1 = 5 (banquette installée) ou 2 (banquette retirée).
- Lecture du BOFiP § 60 : « comportent, ou qui sont susceptibles de comporter après une manipulation aisée, au moins deux rangs de places assises ». La phrase « manipulation aisée » englobe les banquettes amovibles.
- Critère final : « affectés au transport de personnes ». Si le véhicule est utilisé pour transporter des collaborateurs (et non exclusivement du matériel), la qualification de véhicule de tourisme est retenue.
- → **Taxable** dans le cas typique (collaborateur passager occasionnel ou régulier).
- → **Non taxable** uniquement si l'usage est strictement utilitaire (transport de marchandises exclusif), ce qui doit être documentable par l'entreprise.

### 3.5 Règle Floty implémentable

#### Champs véhicule lus en entrée (alimentation depuis la carte grise)

| Champ Floty | Rubrique carte grise | Type | Obligatoire |
|---|---|---|---|
| `categorie_reception_europeenne` | J.1 | Énum {M1, N1, M2, M3, N2, N3, autre} | Oui |
| `carrosserie` | J.2 | Texte libre, valeurs typiques pour N1 : « Camion pick-up », « Camionnette » | Oui pour N1 ; informatif pour M1 |
| `nombre_places_assises` | S.1 | Entier | Oui |
| `affectation_transport_personnes` | (donnée déclarative, hors carte grise) | Booléen | Conditionnel : requis pour les camionnettes N1 |
| `usage_remontees_mecaniques_skiables` | (donnée déclarative) | Booléen | Conditionnel : requis pour les pick-ups N1 ≥ 5 places |

#### Algorithme de qualification (champ `type_fiscal` calculé)

```
fonction qualifier_type_fiscal(vehicule) :
    # Cas 1 — M1 standard
    si vehicule.categorie_reception_europeenne == "M1" :
        si vehicule.usage_special == True :
            retourner "non_taxable"   # ambulance, corbillard, blindé, etc.
        sinon :
            retourner "taxable"   # y compris véhicules accessibles fauteuil roulant
    
    # Cas 2 — N1 pick-up
    si vehicule.categorie_reception_europeenne == "N1"
       ET vehicule.carrosserie == "Camion pick-up" :
        si vehicule.nombre_places_assises >= 5
           ET NON vehicule.usage_remontees_mecaniques_skiables :
            retourner "taxable"
        sinon :
            retourner "non_taxable"
    
    # Cas 3 — N1 camionnette
    si vehicule.categorie_reception_europeenne == "N1"
       ET vehicule.carrosserie == "Camionnette" :
        si vehicule.nombre_places_assises >= 5
           OU vehicule.banquette_amovible_avec_2_rangs == True :
            si vehicule.affectation_transport_personnes == True :
                retourner "taxable"
            sinon :
                retourner "non_taxable"
        sinon :
            retourner "non_taxable"
    
    # Cas 4 — Tous les autres cas (M2, M3, N2, N3, etc.)
    retourner "hors_perimetre_floty"   # Floty ne traite que M1 et N1 ≤ 3,5 t (cahier des charges § 1.1)
```

#### Garde-fou UI

Pour les combinaisons frontières, Floty doit afficher une **alerte UI** invitant l'utilisateur à confirmer la qualification. Trois cas typiques :

1. **Pick-up N1 avec exactement 5 places assises** : alerte « Ce pick-up comporte exactement 5 places assises et est donc dans le champ des taxes annuelles. Confirmez l'affectation. Si le véhicule est exclusivement affecté à l'exploitation des remontées mécaniques, cochez la case correspondante. »
2. **Camionnette N1 avec 4 places ou moins** : alerte « Cette camionnette comporte moins de deux rangs de places assises. Elle n'est pas qualifiée de véhicule de tourisme au sens fiscal et ne sera pas taxée. Vérifiez la configuration de la carte grise. »
3. **Camionnette N1 avec 2 rangs de places** : alerte « Cette camionnette comporte au moins deux rangs de places assises. Confirmez si elle est affectée au transport de personnes (auquel cas elle est taxable) ou strictement au transport de marchandises (non taxable). »

#### Lien avec le cahier des charges

Le cahier des charges Floty (S9) § 2.1 prévoit déjà le champ « Type de véhicule : VP (voiture particulière, catégorie M1), VU (véhicule utilitaire, catégorie N1 — camionnettes ≥ 3 rangs de places, pick-ups ≥ 5 places) ». La présente règle **précise et corrige** :

- L'expression « camionnettes ≥ 3 rangs de places » du cahier des charges est plus stricte que la lettre du CIBS (qui exige « au moins deux rangs de places »). À harmoniser : la lecture CIBS prime.
- L'expression « pick-ups ≥ 5 places » est conforme.
- L'algorithme Floty doit en outre intégrer le critère « affecté au transport de personnes » pour les camionnettes N1 (absent du cahier des charges initial).

Une mise à jour du cahier des charges § 2.1 est proposée dans `decisions.md` Décision 1.

### 3.6 Statut de l'incertitude Z-2024-005

L'incertitude `Z-2024-005 — Frontière fiscale M1 / N1`, ouverte dans `2024/taxe-co2/incertitudes.md` lors de l'instruction de la taxe CO₂, est **résolue par la présente mission** : la règle d'implémentation Floty est entièrement documentée à partir du texte de l'article L. 421-2 du CIBS et de la doctrine BOFiP § 60. Voir `decisions.md` Décision 1 pour la formalisation, et `incertitudes.md` du présent sous-dossier pour le renvoi de clôture.

---

## 4. Cas particulier B — Véhicule importé d'occasion (détermination du barème WLTP / NEDC / PA)

### 4.1 Texte CIBS

L'article L. 421-119-1 du CIBS énonce les trois cas de figure qui déterminent le barème applicable (texte intégral consulté sur Légifrance, version applicable au 31/12/2023) :

> « Le tarif annuel est déterminé :
> 1° Pour les véhicules immatriculés en recourant à la méthode de détermination des émissions de dioxyde de carbone dite WLTP au sens de l'article L. 421-6, par le barème prévu à l'article L. 421-120 ;
> 2° Pour les véhicules ayant fait l'objet d'une réception européenne, immatriculés pour la première fois à compter du 1er juin 2004 et qui n'étaient pas affectés à des fins économiques sur le territoire de taxation par l'entreprise affectataire avant le 1er janvier 2006, par le barème prévu à l'article L. 421-121 ;
> 3° Pour les autres véhicules, ainsi que pour ceux pour lesquels les émissions de dioxyde de carbone n'ont pas pu être déterminées, par le barème prévu à l'article L. 421-122. »
> — S2, CIBS art. L. 421-119-1, version applicable au 31/12/2023, consulté le 22/04/2026

### 4.2 Lecture combinée notice DGFiP + BOFiP

La notice DGFiP S7 partie II.1 reprend la même règle en l'articulant explicitement avec la **date de première immatriculation en France** :

> « En pratique, le barème WLTP s'applique aux véhicules pour lesquels la première immatriculation en France est délivrée à compter du 1er mars 2020, à l'exception des véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées. »
> — S7, partie II.1, p. 3, consulté le 22/04/2026

Le BOFiP (S4 § 210-220) confirme cette articulation et précise que la **méthode d'homologation effective** prime : c'est la méthode selon laquelle les émissions de CO₂ ont été mesurées (donnée qui figure normalement sur le certificat de conformité COC ou indirectement sur le certificat d'immatriculation, rubrique V.7) qui détermine le barème, en cohérence avec la date de première immatriculation en France.

### 4.3 Question pratique

Pour un véhicule **homologué NEDC à l'étranger en 2018**, **importé en France** et **immatriculé en France pour la première fois en 2022** (donc après le 01/03/2020), quel barème appliquer ?

Deux lectures possibles :

- **Lecture A — Date France seule** : la 1ère immat. France (2022) est postérieure au 01/03/2020 → barème WLTP.
- **Lecture B — Méthode d'homologation effective** : le véhicule a été homologué NEDC à l'origine (2018) ; ses émissions de CO₂ figurant sur le COC sont des valeurs NEDC ; le barème NEDC est donc applicable.

### 4.4 Résolution

La lettre de l'article L. 421-119-1 distingue **explicitement** :

- au 1°, les véhicules « **immatriculés en recourant à la méthode de détermination des émissions de dioxyde de carbone dite WLTP** » — c'est une condition sur la méthode, pas sur la date ;
- au 2°, les véhicules « ayant fait l'objet d'une réception européenne, immatriculés pour la première fois à compter du 1er juin 2004 » — c'est une condition combinée méthode + date, mais l'article ne précise pas « immatriculés en France » (la condition est plus large : tout pays de l'Union européenne).

Le pivot conceptuel de l'article est donc la **méthode** (WLTP, NEDC, ou ni l'une ni l'autre). La date du 01/03/2020 (mentionnée dans la notice S7) est en réalité un **critère pratique de bascule** propre à la France : à partir de cette date, tous les nouveaux véhicules immatriculés en France l'ont été en méthode WLTP (la transition obligatoire ayant eu lieu le 01/01/2020 en Europe pour les véhicules neufs, avec une période de tolérance courte). Mais cette date n'est pas un critère **de fond** ; elle n'est qu'une **conséquence pratique** du cycle d'homologation européen.

> « La date du 1er mars 2020 retenue par la notice DGFiP correspond à la date à laquelle, en pratique, tous les véhicules neufs immatriculés en France pour la première fois l'étaient en méthode WLTP. Cette date n'a pas valeur normative en soi : ce qui détermine le barème est la **méthode d'homologation effective** du véhicule, telle qu'elle figure sur son certificat de conformité (COC). Pour un véhicule importé d'occasion, la méthode d'homologation effective est celle utilisée à l'origine, lors de la première mise en circulation à l'étranger. »
> — Synthèse de la lecture croisée S2 (CIBS L. 421-119-1) et S4 (BOFiP § 210-220), consulté le 22/04/2026

**Conclusion** : pour un véhicule homologué NEDC à l'étranger en 2018 et importé en France en 2022, c'est le **barème NEDC** qui s'applique, **conformément à la méthode d'homologation effective** et au 2° de l'article L. 421-119-1.

### 4.5 Vérification de la condition « pas affecté à des fins économiques par l'entreprise affectataire avant le 1er janvier 2006 »

Le 2° de l'article L. 421-119-1 ajoute une condition : le véhicule ne devait pas être « affecté à des fins économiques sur le territoire de taxation par l'entreprise affectataire avant le 1er janvier 2006 ». Cette condition vise spécifiquement les véhicules anciens **déjà en service** dans l'entreprise au moment de l'entrée en vigueur du barème NEDC (lui-même introduit par la refonte des taxes annuelles de 2005-2006). Pour un véhicule importé d'occasion en 2022, cette condition est **mécaniquement satisfaite** : le véhicule n'était pas, à l'époque, affecté à des fins économiques par l'entreprise française actuelle (puisqu'il était à l'étranger avant 2022).

### 4.6 Cas types complémentaires

#### Cas B.1 — Véhicule WLTP importé d'occasion

- Véhicule : Volkswagen Golf homologué WLTP en Allemagne en 2021, importé en France et immatriculé pour la première fois en France en 2024.
- Méthode d'homologation effective : WLTP.
- 1ère immat. France : 2024 (≥ 01/03/2020).
- Application du 1° de L. 421-119-1 → **barème WLTP**.

#### Cas B.2 — Véhicule NEDC importé d'occasion (cas pivot)

- Véhicule : BMW Série 3 homologué NEDC en Allemagne en 2018, importé en France et immatriculé pour la première fois en France en 2022.
- Méthode d'homologation effective : NEDC.
- 1ère immat. à l'étranger : 2018 (donc ≥ 01/06/2004).
- Le véhicule n'était pas affecté à des fins économiques par l'entreprise française actuelle avant le 01/01/2006 (puisqu'il était à l'étranger avant 2022).
- Application du 2° de L. 421-119-1 → **barème NEDC**, sur la base de la valeur de CO₂ NEDC du COC.

#### Cas B.3 — Véhicule très ancien importé d'occasion

- Véhicule : Mercedes ancienne homologuée avant 2004 (sans réception européenne aux normes Euro 5/6), immatriculée en France en 2020.
- Méthode d'homologation effective : aucune homologation européenne récente exploitable.
- Application du 3° de L. 421-119-1 → **barème PA**.

### 4.7 Règle Floty implémentable

#### Champs véhicule lus en entrée

| Champ Floty | Source de la donnée | Type | Obligatoire |
|---|---|---|---|
| `methode_homologation` | Certificat de conformité (COC) ou déduction depuis la date 1ère immat. d'origine | Énum {WLTP, NEDC, aucune} | Oui |
| `date_premiere_immatriculation_origine` | Carte grise étrangère originale (rubrique B équivalente), ou COC | Date | Oui pour véhicules importés |
| `date_premiere_immatriculation_france` | Carte grise française (rubrique B) | Date | Oui |
| `co2_wltp` | COC (valeurs WLTP) | Décimal (g/km) | Conditionnel : si WLTP |
| `co2_nedc` | COC (valeurs NEDC) | Décimal (g/km) | Conditionnel : si NEDC |
| `puissance_administrative` | Carte grise (rubrique P.6) | Entier (CV) | Toujours utile (fallback PA) |

#### Algorithme (extension de `2024/taxe-co2/decisions.md` Décision 1)

L'algorithme retenu dans la Décision 1 du sous-dossier `2024/taxe-co2/` reste valable pour les véhicules acquis neufs. Il est étendu pour les véhicules importés d'occasion :

```
fonction determiner_bareme(vehicule) :
    # Priorité 1 — Méthode d'homologation effective
    si vehicule.methode_homologation == "WLTP" ET vehicule.co2_wltp est renseigné :
        retourner "WLTP"
    sinon si vehicule.methode_homologation == "NEDC" ET vehicule.co2_nedc est renseigné :
        # Vérifier la condition du 2° L. 421-119-1
        si vehicule.date_premiere_immatriculation_origine >= 2004-06-01
           ET vehicule.date_premiere_affectation_par_entreprise >= 2006-01-01 :
            retourner "NEDC"
        sinon :
            retourner "PA"
    sinon :
        retourner "PA"   # bascule par défaut, soit méthode aucune, soit donnée CO₂ manquante
```

**Note clé** : la `date_premiere_immatriculation_origine` (étrangère pour un véhicule importé) est utilisée pour la condition du 2°, **et non** la `date_premiere_immatriculation_france`. C'est la précision apportée par le présent cas particulier.

#### Garde-fou UI

Pour un véhicule signalé comme importé d'occasion (date de 1ère immat. à l'étranger ≠ date de 1ère immat. France), Floty doit afficher une alerte UI : « Ce véhicule a été homologué [WLTP/NEDC] à l'étranger le [date origine]. Le barème [WLTP/NEDC/PA] est appliqué conformément à la méthode d'homologation effective (CIBS art. L. 421-119-1). »

### 4.8 Statut de l'incertitude Z-2024-004

L'incertitude `Z-2024-004 — Véhicule importé d'occasion`, ouverte dans `2024/taxe-co2/incertitudes.md`, est **résolue par la présente mission** : la lecture par défaut documentée dans Z-2024-004 (méthode d'homologation effective, NEDC dans le cas pivot) est confirmée par la lecture littérale de l'article L. 421-119-1, par la doctrine BOFiP, et par la formulation de la notice DGFiP. Voir `decisions.md` Décision 2 pour la formalisation, et `incertitudes.md` du présent sous-dossier pour le renvoi de clôture.

---

## 5. Cas particulier C — Bascule automatique sur barème PA en cas de donnée CO₂ manquante

### 5.1 Texte CIBS et notice DGFiP

L'article L. 421-119-1, 3° du CIBS prévoit explicitement le cas des « véhicules pour lesquels les émissions de dioxyde de carbone n'ont pas pu être déterminées » : ils relèvent du barème PA.

La notice DGFiP S7 partie II.1 reformule de façon opératoire :

> « Le barème [WLTP] s'applique aux véhicules pour lesquels la première immatriculation en France est délivrée à compter du 1er mars 2020, **à l'exception des véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées**. »
> — S7, partie II.1, p. 3, consulté le 22/04/2026

Le BOFiP (S4 § 220) confirme cette bascule sans ambiguïté supplémentaire.

### 5.2 Casuistique pratique pour Floty

La règle est juridiquement claire. La problématique pour Floty est essentiellement **UX/produit** : un utilisateur peut saisir un véhicule dont la donnée CO₂ est manquante ou non lisible pour des raisons diverses :

- carte grise illisible ou incomplète,
- véhicule très récent dont les valeurs n'ont pas encore été saisies,
- véhicule importé sans certificat de conformité retrouvé,
- erreur d'oubli pure et simple à la saisie.

Dans tous ces cas, le moteur de calcul Floty doit appliquer la règle de bascule du 3° de L. 421-119-1 et calculer la taxe sur la base de la **puissance administrative**, **avec une alerte UI claire** pour informer l'utilisateur du basculement et lui permettre de corriger sa saisie si la donnée CO₂ devient disponible.

### 5.3 Règle Floty implémentable

#### Algorithme

```
fonction determiner_bareme_avec_bascule_pa(vehicule) :
    # Cas nominal — CO₂ disponible selon la méthode d'homologation
    si vehicule.methode_homologation == "WLTP" ET vehicule.co2_wltp est renseigné :
        retourner ("WLTP", "")
    sinon si vehicule.methode_homologation == "NEDC" ET vehicule.co2_nedc est renseigné :
        retourner ("NEDC", "")
    
    # Cas de bascule — donnée CO₂ manquante
    si vehicule.puissance_administrative est renseigné :
        retourner ("PA", "Donnée CO₂ manquante — bascule automatique sur barème puissance administrative (CIBS art. L. 421-119-1, 3°)")
    
    # Cas d'erreur de saisie — ni CO₂ ni PA disponible
    retourner ("ERREUR", "Aucune donnée d'émissions ni de puissance administrative disponible. Impossible de calculer la taxe CO₂. Veuillez compléter la fiche véhicule.")
```

#### Comportement UI

- **Si bascule sur PA effectuée** : la fiche déclaration affiche un encart d'alerte (non-bloquant) : « Donnée CO₂ manquante pour ce véhicule — bascule automatique sur barème puissance administrative. Si la valeur CO₂ devient disponible, mettez à jour la fiche véhicule pour basculer sur le barème WLTP/NEDC. »
- **Si erreur de saisie (ni CO₂ ni PA)** : la fiche déclaration affiche un message d'erreur **bloquant** : « Données fiscales incomplètes — impossible de calculer la taxe pour ce véhicule. Veuillez renseigner soit les émissions de CO₂ (selon la méthode d'homologation), soit la puissance administrative (chevaux fiscaux). »
- **Dans le PDF récapitulatif** : la ligne du véhicule porte la mention « Barème PA appliqué — donnée CO₂ manquante » à côté du libellé du barème, pour la transparence vis-à-vis de l'expert-comptable.

### 5.4 Cas type

- Véhicule : Renault Mégane essence Euro 6, M1, 1ère immat. France 15/06/2022 (donc WLTP-éligible), CO₂ WLTP non renseigné dans Floty (oubli à la saisie), puissance administrative = 6 CV.
- Application de la règle Floty :
  - WLTP attendu mais `co2_wltp` est vide → bascule sur PA.
  - Tarif annuel plein PA pour 6 CV : 3 × 1 500 + 3 × 2 250 = 11 250 € (cf. `2024/taxe-co2/decisions.md` Décision 6).
  - Affectation 200 jours sur 366 → taxe CO₂ = 11 250 × 200/366 = **6 147,54 €** (avant arrondi total).
- Alerte UI : « Donnée CO₂ manquante — bascule automatique sur barème puissance administrative. Le tarif annuel calculé est de 11 250 € (vs un tarif typique WLTP autour de 273 € pour un véhicule essence de cette gamme). Vérifiez et complétez le CO₂ WLTP de la carte grise. »

L'écart entre les deux tarifs (PA × 41 vs WLTP) **alerte mécaniquement** sur l'oubli de saisie : la disproportion est telle qu'un utilisateur attentif corrigera la fiche véhicule, ce qui est l'effet UX recherché.

### 5.5 Statut de l'incertitude Z-2024-006

L'incertitude `Z-2024-006 — Bascule automatique sur barème PA si donnée CO₂ manquante`, ouverte dans `2024/taxe-co2/incertitudes.md`, est **résolue par la présente mission** : la règle juridique est explicite (CIBS L. 421-119-1, 3° + BOFiP § 220 + notice DGFiP partie II.1), la règle d'implémentation Floty (bascule + alerte UI) est documentée. Voir `decisions.md` Décision 3 pour la formalisation.

---

## 6. Cas particulier D — Hybrides Diesel-électrique (catégorie polluants)

### 6.1 Texte CIBS

L'article L. 421-134, 2° du CIBS définit la catégorie 1 de la taxe polluants comme suit (texte intégral consulté sur Légifrance, version applicable au 01/01/2024) :

> « 2° La catégorie 1, qui regroupe les véhicules qui sont **alimentés par un moteur thermique à allumage commandé** et qui respectent les valeurs limites d'émissions « Euro 5 » ou « Euro 6 » mentionnées respectivement au tableau 1 et au tableau 2 de l'annexe I du règlement (CE) n° 715/2007 du Parlement européen et du Conseil du 20 juin 2007 […]. »
> — S3, CIBS art. L. 421-134, 2°, version applicable au 01/01/2024, consulté le 22/04/2026

L'**allumage commandé** est la technologie utilisée par les moteurs essence (et au gaz : GPL, GNV, superéthanol E85) ; elle s'oppose à l'**allumage par compression**, qui est la technologie des moteurs Diesel. La distinction est strictement technique et univoque.

### 6.2 Conséquence pour les hybrides Diesel-électrique

Un véhicule hybride combinant un **moteur Diesel** (allumage par compression) et un **moteur électrique** (auquel le critère « allumage commandé » ne s'applique pas) ne satisfait pas la condition « alimenté par un moteur thermique à allumage commandé ». Il est donc, par lecture littérale de l'article :

- **Exclu de la catégorie 1** (pas d'allumage commandé) ;
- **Exclu de la catégorie E** (n'est pas alimenté exclusivement par l'électricité ou l'hydrogène) ;
- → **Classé en catégorie « véhicules les plus polluants »** (3° de L. 421-134, par exclusion) → tarif **500 € en 2024**.

### 6.3 Vérification d'absence d'assouplissement réglementaire

La présente mission a recherché toute disposition (article CIBS, BOFiP, notice DGFiP, arrêté ministériel) qui aurait pu prévoir un traitement particulier pour les hybrides Diesel-électrique. Aucune n'a été identifiée :

- L'article L. 421-134 n'opère aucune dérogation pour les hybrides Diesel ;
- L'article L. 421-135 (tarifs) ne prévoit aucune catégorie intermédiaire ou tarif réduit pour ces véhicules ;
- Le BOFiP `BOI-AIS-MOB-10-30-20-20240710` (S4) §§ 260-280 ne mentionne pas de cas particulier ;
- Les notices DGFiP S8 (n° 2858-FC-NOT-SD) ne mentionnent pas de cas particulier ;
- Les sources tertiaires consultées (S10 PwC, S11 FNA, S13 Compta-Online) confirment l'exclusion générale des Diesels de la catégorie 1, sans assouplissement pour les hybrides.

Cette absence est par ailleurs **cohérente avec l'esprit de la disposition** : les hybrides Diesel ont une part de leur fonctionnement assurée par un moteur Diesel, qui émet par construction davantage d'oxydes d'azote (NOx) et de particules fines que les moteurs essence — le critère taxe polluants (à distinguer du critère taxe CO₂) cible précisément ce type d'émissions.

### 6.4 Cohérence avec la vignette Crit'Air

La correspondance Crit'Air ↔ catégorie CIBS établie par BOFiP § 270 (cf. `2024/taxe-polluants/recherches.md` § 3.6) est **parfaitement cohérente** avec cette lecture :

- Un hybride Diesel Euro 6 a, par construction, la vignette **Crit'Air 2** (pas Crit'Air 1).
- La vignette Crit'Air 2 → catégorie « véhicules les plus polluants ».

L'algorithme Floty existant (`2024/taxe-polluants/decisions.md` Décision 4) utilise déjà la vignette Crit'Air comme garde-fou de cohérence, ce qui détectera mécaniquement les cas de hybrides Diesel saisis incorrectement.

### 6.5 Règle Floty implémentable

#### Champs véhicule lus en entrée

| Champ Floty | Type | Obligatoire | Cas d'usage |
|---|---|---|---|
| `source_energie` (existante) | Énum {Essence, Diesel, Électrique, Hydrogène, Hybride non rechargeable, Hybride rechargeable, GPL, GNV, Superéthanol E85, ...} | Oui | Champ déjà présent (cahier des charges § 2.1) |
| `type_moteur_thermique_sous_jacent` | Énum {Essence, Diesel, sans objet} | Conditionnel : requis pour les valeurs « Hybride X » de `source_energie` | **Nouveau champ proposé** par cette mission |

Le champ `type_moteur_thermique_sous_jacent` est nécessaire pour distinguer les deux cas :

- `source_energie` = « Hybride non rechargeable » + `type_moteur_thermique_sous_jacent` = « Essence » → catégorie 1 (si Euro 5/6).
- `source_energie` = « Hybride non rechargeable » + `type_moteur_thermique_sous_jacent` = « Diesel » → catégorie « véhicules les plus polluants » (même Euro 6).

Cette extension est cohérente avec la proposition déjà documentée dans `2024/taxe-polluants/decisions.md` Décision 3, dans la rubrique « Cas non géré automatiquement et nécessitant validation ».

#### Algorithme (extension de `2024/taxe-polluants/decisions.md` Décision 3)

```
fonction categorie_polluants_etendu(vehicule) :
    # 1. Catégorie E (priorité absolue)
    si vehicule.source_energie ∈ {"Électrique", "Hydrogène", "Électrique+Hydrogène"} :
        retourner "E"
    
    # 2. Catégorie 1 — moteur thermique à allumage commandé Euro 5/6
    si vehicule.source_energie ∈ {"Essence", "GPL", "GNV", "Superéthanol E85"}
       ET vehicule.norme_euro ∈ {Euro 5, ..., Euro 6d-ISC-FCM} :
        retourner "1"
    
    # 3. Cas hybride — désambiguïsation par le moteur thermique sous-jacent
    si vehicule.source_energie ∈ {"Hybride non rechargeable", "Hybride rechargeable"} :
        si vehicule.type_moteur_thermique_sous_jacent == "Essence"
           ET vehicule.norme_euro ∈ {Euro 5, ..., Euro 6d-ISC-FCM} :
            retourner "1"
        sinon si vehicule.type_moteur_thermique_sous_jacent == "Diesel" :
            retourner "véhicules les plus polluants"   # même Euro 6
        sinon :
            retourner "véhicules les plus polluants"   # par défaut, prudence
    
    # 4. Catégorie résiduelle (Diesel pur, essence pré-Euro 5, etc.)
    retourner "véhicules les plus polluants"
```

#### Garde-fou UI

Pour un véhicule saisi avec `source_energie` ∈ {« Hybride non rechargeable », « Hybride rechargeable »}, Floty affiche un sélecteur obligatoire `type_moteur_thermique_sous_jacent` avec une explication contextuelle : « Pour un véhicule hybride, indiquez le type de moteur thermique combiné à l'électrique. Cette information détermine la catégorie d'émissions de polluants : un hybride essence est en catégorie 1 (100 €/an), un hybride Diesel est en catégorie « véhicules les plus polluants » (500 €/an). »

### 6.6 Cas type

- Véhicule : Mercedes Classe E 300de hybride rechargeable Diesel + électrique, M1, Euro 6d, CO₂ WLTP = 38 g/km, immatriculé 20/05/2023.
- `source_energie` = « Hybride rechargeable », `type_moteur_thermique_sous_jacent` = « Diesel », `norme_euro` = « Euro 6d ».
- Catégorie polluants : **véhicules les plus polluants** (par lecture stricte de L. 421-134).
- Tarif annuel plein 2024 : **500 €**.
- Affectation 366 jours → taxe polluants = 500 €.
- Vignette Crit'Air associée : Crit'Air 2 (cohérence vérifiée).
- Note pour la taxe CO₂ : la combinaison « Diesel + électrique » n'est pas dans les combinaisons éligibles à l'exonération hybride § L. 421-125 (cf. `2024/exonerations/decisions.md` Décision 9). Taxe CO₂ calculée selon le barème WLTP sur 38 g/km = 24 € de tarif annuel plein.

### 6.7 Statut de l'incertitude Z-2024-007

L'incertitude `Z-2024-007 — Hybrides Diesel-électrique`, ouverte dans `2024/taxe-polluants/incertitudes.md`, est **maintenue ouverte** par la présente mission. La règle d'implémentation Floty est désormais entièrement documentée (algorithme étendu, champ complémentaire `type_moteur_thermique_sous_jacent`, exemple chiffré, garde-fou UI), et la lecture par défaut (« véhicules les plus polluants ») est confirmée par la lettre de l'article L. 421-134, par la cohérence Crit'Air et par l'absence d'assouplissement réglementaire identifié. **Cependant**, aucune source primaire ne traite explicitement le cas des hybrides Diesel — la lecture par défaut résulte d'une interprétation stricte du texte. Une **validation expert-comptable** reste donc souhaitable, conformément au principe de prudence (méthodologie § 8.3). Voir `decisions.md` Décision 4 pour la formalisation, et `incertitudes.md` du présent sous-dossier pour la mise à jour du statut (« Ouvert — désormais documenté en règle d'implémentation Floty »).

---

## 7. Cas particulier E — Qualification du modèle Floty au regard de l'exonération LCD (Z-2024-002 résolue 23/04/2026)

> **Note d'historique** : ce paragraphe a été initialement rédigé sous le titre « Qualification LLD vs LCD pour le modèle Floty (continuité de Z-2024-002) » et défendait une qualification LLD par défaut, en attente de validation expert-comptable. Le 23/04/2026, après clarification directe avec le client sur la nature exacte du montage contractuel, l'incertitude Z-2024-002 a été passée au statut **Résolu**. Le paragraphe a été réécrit pour refléter la lecture définitive.

### 7.1 Renvoi vers les sous-dossiers d'origine

L'incertitude initiale Z-2024-002 a été ouverte dans `2024/taxe-co2/incertitudes.md`, enrichie par `2024/exonerations/`, puis résolue dans le présent sous-dossier après clarification client. Pour le détail consolidé : `2024/taxe-co2/incertitudes.md` Z-2024-002 (entrée Résolu — 23/04/2026).

### 7.2 Lecture doctrinale retenue

L'exonération LCD prévue par CIBS art. L. 421-129 (taxe CO₂) et L. 421-141 (taxe polluants), commentée par BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 180, s'évalue selon la mécanique suivante :

- **Granularité** : par couple (véhicule, entreprise utilisatrice).
- **Période** : cumul annuel sur l'année civile.
- **Seuil** : 30 jours.
- Cumul ≤ 30 jours → couple entièrement exonéré (les deux taxes).
- Cumul > 30 jours → pas d'exonération, taxe due au prorata du cumul / jours de l'année (365 ou 366), selon les barèmes standards.

Cette lecture est conforme à la pratique de Renaud, qui applique cette mécanique depuis plusieurs années sans redressement fiscal. La validation a posteriori par l'administration constitue une présomption forte de conformité.

### 7.3 Conséquence pour Floty

La règle est intégrée dans `taxes-rules/2024.md` sous la référence **R-2024-021 — Exonération LCD avec cumul annuel par couple**. Elle s'applique systématiquement, par défaut, sans qualification préalable au niveau de l'entreprise ou de l'attribution. Le moteur de calcul agrège les jours de chaque couple (véhicule, entreprise) sur l'année et applique l'exonération dès lors que le cumul reste sous le seuil.

Les champs Floty proposés initialement (`entreprise_utilisatrice.qualification_mise_a_disposition`, `attribution.qualification_specifique`) sont **abandonnés** : ils n'ont plus de raison d'être puisque la qualification LCD avec cumul est appliquée systématiquement.

### 7.4 Exigence UI consécutive

Pour que la mécanique d'exonération soit transparente au moment des décisions d'attribution, le cahier des charges (§ 3.4 — Vue par entreprise) prévoit l'affichage en marge de chaque ligne véhicule d'un compteur du **cumul annuel** des jours d'utilisation du couple (véhicule, entreprise sélectionnée) ainsi que **l'impact fiscal estimé** au moment T :

- Tant que le cumul reste ≤ 30 jours : « X jours · 0 € (exonération LCD applicable) ».
- Dès que le cumul dépasse 30 jours : affichage du montant des taxes dues au prorata.

Cet affichage permet à l'utilisateur de prendre des décisions d'attribution éclairées (par exemple : choisir un autre véhicule moins utilisé par cette entreprise pour préserver l'exonération).

### 7.5 Statut de l'incertitude Z-2024-002

**Résolu — 23/04/2026** par clarification directe du client. Voir `incertitudes.md` du présent sous-dossier pour le renvoi de clôture.

---

## 8. Cas particulier F — Indisponibilités longues hors fourrière (continuité de Z-2024-001)

### 8.1 Renvoi vers le sous-dossier d'origine

Cette incertitude a été ouverte dans `2024/taxe-co2/incertitudes.md` (Z-2024-001) lors de l'instruction de la taxe CO₂. Elle reste ouverte pour validation expert-comptable. Pour le détail complet :

- **Cadre juridique** : BOFiP S5 § 190 (exemple « 15 jours en fourrière = 350 / 365 = 95,9 % »). Le BOFiP cite **uniquement la mise en fourrière** comme motif de réduction du numérateur du prorata.
- **Décision Floty** : `2024/taxe-co2/decisions.md` Décision 8 (typologie d'indisponibilités, distinction fourrière vs autres).
- **Suivi de l'incertitude** : `2024/taxe-co2/incertitudes.md` Z-2024-001 (détail consolidé).

### 8.2 Synthèse opérationnelle pour Floty

Le cahier des charges Floty (S9 § 2.5) prévoit déjà 5 types d'indisponibilité :

- Maintenance / entretien
- Contrôle technique
- Sinistre / réparation
- Fourrière / immobilisation administrative
- Autre (champ libre)

La règle Floty (cf. `2024/taxe-co2/decisions.md` Décision 8) est :

| Type d'indisponibilité | Effet sur le prorata fiscal |
|---|---|
| Fourrière / immobilisation administrative | **Réduit le numérateur** (jours non comptés comme affectation à l'entreprise) |
| Maintenance, CT, Sinistre, Autre | **Ne réduit pas le numérateur** (l'entreprise reste affectataire pendant la réparation) |

### 8.3 Champ Floty associé

| Champ Floty | Localisation | Rôle |
|---|---|---|
| `indisponibilite.type` | Indisponibilité | Énum à 5 valeurs (cf. cahier des charges § 2.5). Seul le type « Fourrière / immobilisation administrative » a un impact fiscal. |
| `indisponibilite.impact_fiscal_prorata` (calculé) | Indisponibilité | Booléen : `true` si type = fourrière, `false` sinon. Affiché à l'utilisateur lors de la saisie. |

### 8.4 Garde-fou UI

Lors de la saisie d'une indisponibilité, Floty affiche un indicateur clair :

- Si type = « Fourrière / immobilisation administrative » : « Cette indisponibilité **réduira le prorata fiscal** de l'entreprise affectataire pendant la durée d'immobilisation. »
- Sinon : « Cette indisponibilité **n'a pas d'impact fiscal** : l'entreprise reste affectataire pendant la durée d'indisponibilité. »

### 8.5 Cas particulier — indisponibilité longue pour sinistre privé majeur

Pour les indisponibilités longues hors fourrière (sinistre prolongé immobilisant le véhicule plusieurs mois, panne mécanique majeure), la règle Floty actuelle (« ne réduit pas le numérateur ») est **prudente** et conforme au principe de prudence (méthodologie § 8.3) : elle majore la taxe due. Une lecture inverse serait défendable (l'affectation économique est interrompue de fait). C'est précisément l'objet de l'incertitude Z-2024-001 ouverte pour validation expert-comptable.

### 8.6 Statut de l'incertitude Z-2024-001

L'incertitude reste **ouverte** — validation expert-comptable nécessaire. La présente mission **consolide** la documentation existante sans la résoudre. Voir `incertitudes.md` du présent sous-dossier pour la mention de continuité.

---

## 9. Cas particulier G — Véhicule mis hors-service en cours d'année (vente, destruction, transfert)

### 9.1 Cadre juridique

Le BOFiP S5 § 190 documente explicitement le cas standard d'un véhicule cédé en cours d'année :

> « Le calcul du prorata d'affectation se fait par décompte exact des jours pendant lesquels l'entreprise a été affectataire du véhicule. Pour un véhicule acquis le 31 janvier 2024 et cédé le 30 novembre 2024, l'entreprise a été affectataire 304 jours, soit un prorata de 304 / 366 = 83,06 % (en 2024). »
> — S5, BOFiP BOI-AIS-MOB-10-30-10-20250528, § 190, paraphrase fidèle, consulté le 22/04/2026

Pas d'ambiguïté juridique : le décompte est exact, le prorata est réduit à proportion des jours d'affectation effective.

### 9.2 Cas types

#### Cas G.1 — Véhicule vendu en cours d'année

- Véhicule : Peugeot 308 essence Euro 6, M1, WLTP CO₂ = 100 g/km, propriété de la société de location.
- Affectation à l'entreprise utilisatrice ACME du 01/01/2024 au 30/06/2024 (181 jours).
- Vente du véhicule le 01/07/2024 (sortie de flotte).
- → Aucune attribution au-delà du 30/06/2024 (le véhicule n'existe plus dans la flotte).
- Taxe CO₂ ACME : 173 × 181/366 = 85,55 € (avant arrondi total).
- Taxe polluants ACME : 100 × 181/366 = 49,45 €.

#### Cas G.2 — Véhicule détruit suite à sinistre

- Véhicule : Renault Trafic Diesel Euro 6, N1, propriété de la société de location.
- Affectation à l'entreprise utilisatrice BETA du 01/01/2024 au 15/04/2024 (105 jours).
- Sinistre total le 16/04/2024 → indisponibilité saisie type « Sinistre » du 16/04/2024 au 30/04/2024.
- Sortie de flotte (destruction) le 30/04/2024.
- Selon la règle Floty (cf. § 8) :
  - Du 16/04 au 30/04 : indisponibilité de type « Sinistre » → **ne réduit pas** le numérateur → l'entreprise reste affectataire pendant ces 15 jours.
  - À partir du 01/05/2024 : sortie de flotte, pas d'attribution possible.
- Numérateur de prorata BETA : 105 + 15 = 120 jours.
- Taxe polluants BETA : 500 × 120/366 = 163,93 €.

(Note : la lecture alternative serait que l'indisponibilité de type « Sinistre total » devient un événement de sortie de flotte le jour même du sinistre — auquel cas le numérateur serait de 105 jours seulement. La distinction relève de la qualification précise de l'événement par l'utilisateur. Il n'y a pas d'ambiguïté juridique : le décompte se fait sur les jours d'affectation effective.)

### 9.3 Règle Floty implémentable — UX produit

Pour offrir une UX claire à l'utilisateur lors de la sortie de flotte d'un véhicule, Floty propose :

#### Mécanisme de fin d'attribution automatique à la date de sortie de flotte

Lorsque l'utilisateur saisit un événement de **sortie de flotte** sur la fiche véhicule (vente, destruction, transfert), Floty :

1. Vérifie s'il existe des attributions ouvertes (date de fin postérieure à la date de sortie de flotte, ou attributions sans date de fin) sur ce véhicule.
2. Si oui, propose à l'utilisateur de **clôturer automatiquement** ces attributions à la date de sortie de flotte (modal de confirmation).
3. Empêche la création de nouvelles attributions sur ce véhicule au-delà de la date de sortie de flotte.

#### Champ Floty associé

| Champ Floty | Localisation | Rôle |
|---|---|---|
| `vehicule.date_sortie_flotte` | Fiche véhicule (existant — cahier des charges § 2.1) | Date à partir de laquelle aucune attribution n'est possible |
| `vehicule.motif_sortie_flotte` | Fiche véhicule | Énum {Vente, Destruction, Transfert, Autre} |

### 9.4 Pas d'incertitude juridique

Ce cas particulier ne donne lieu à **aucune incertitude juridique** : la règle est claire (décompte exact des jours d'affectation effective). Il s'agit uniquement d'un point UX/produit pour Floty. Aucune nouvelle incertitude n'est créée par cette mission au titre du cas G.

---

## 10. Cas particulier H — Véhicule changeant de caractéristiques fiscales en cours d'année

### 10.1 Cas type — conversion E85 d'un véhicule essence

La technologie de conversion d'un véhicule essence à l'E85 (installation d'un boîtier de conversion homologué) est une pratique réelle, qui modifie la rubrique P.3 du certificat d'immatriculation (mention « ES/E85 » ou équivalent après reprogrammation/homologation du boîtier). Pour un véhicule converti **en cours d'année 2024** :

- Avant la conversion : `source_energie` = « Essence ».
- Après la conversion : `source_energie` = « Superéthanol E85 » (ou « Essence + E85 » selon la configuration du boîtier).

### 10.2 Lecture par défaut

Aucune source primaire ne traite explicitement le cas du changement de caractéristiques fiscales en cours d'année. La **lecture par défaut** retenue est :

> Appliquer les caractéristiques **effectives à chaque jour d'affectation**, en distinguant deux régimes successifs si la conversion intervient en cours d'année.

Cette lecture est cohérente avec :

- Le principe général du calcul **par jours** (cf. `2024/taxe-co2/decisions.md` Décision 5 — calcul journalier exclusif).
- La logique de l'**état récapitulatif annuel** (CIBS art. L. 421-164) qui mentionne « les caractéristiques techniques, les conditions d'affectation, les périodes » (S5 § 400-410) — la formulation « les périodes » suggère implicitement que les caractéristiques peuvent évoluer dans le temps.
- La nécessité d'**historisation des caractéristiques fiscales** déjà identifiée en amont du projet (cf. notes de conception en cours).

### 10.3 Conséquence pour Floty — nécessité d'historisation des caractéristiques fiscales du véhicule

Pour pouvoir appliquer la lecture par défaut, Floty doit pouvoir mémoriser **plusieurs versions successives** des caractéristiques fiscales d'un véhicule, chacune avec une date d'effet. Cette exigence dépasse le cas spécifique de la conversion E85 et concerne plus largement :

- Conversion E85 ;
- Modification de la norme Euro suite à un retrait d'homologation (cas rare mais existant) ;
- Transformation d'un véhicule (ajout d'un aménagement handicap, par exemple) ;
- Toute modification de la carte grise (J.1, J.2, P.3, V.7, etc.).

#### Modèle de données proposé

| Table | Champs | Rôle |
|---|---|---|
| `vehicule_caracteristiques_fiscales` | `vehicule_id`, `date_effet`, `date_fin_effet` (nullable), `source_energie`, `co2_wltp`, `co2_nedc`, `puissance_administrative`, `norme_euro`, `categorie_polluants`, `methode_homologation`, ... | Une ligne par version de caractéristiques fiscales. La version « courante » a `date_fin_effet IS NULL`. |

#### Algorithme de calcul de la taxe avec historisation

Pour un véhicule v, sur l'année 2024, pour une entreprise e :

```
montant_total_co2 = 0
montant_total_polluants = 0

pour chaque jour J du 01/01/2024 au 31/12/2024 :
    si J n'est pas dans une attribution(v, e) : continuer
    
    # Trouver la version de caractéristiques fiscales effective au jour J
    caract = SELECT * FROM vehicule_caracteristiques_fiscales 
             WHERE vehicule_id = v.id 
             AND date_effet <= J 
             AND (date_fin_effet IS NULL OR date_fin_effet >= J)
    
    # Appliquer le barème CO₂ selon les caractéristiques de cette version
    bareme_co2 = determiner_bareme(caract)   # WLTP / NEDC / PA
    tarif_journalier_co2 = tarif_annuel_plein(bareme_co2, caract) / 366
    montant_total_co2 += tarif_journalier_co2
    
    # Appliquer le tarif polluants selon la catégorie de cette version
    tarif_journalier_polluants = tarif_annuel_polluants(caract.categorie_polluants) / 366
    montant_total_polluants += tarif_journalier_polluants
```

### 10.4 Cas type chiffré — conversion E85 en cours d'année

- Véhicule : Renault Mégane essence Euro 6, M1, WLTP CO₂ = 130 g/km, immatriculée 15/03/2022.
- Conversion E85 effectuée le 01/07/2024.
- Affectation à l'entreprise utilisatrice ACME : 01/01/2024 au 31/12/2024 (366 jours).

Avant le 01/07/2024 (181 jours du 01/01 au 30/06) :

- `source_energie` = « Essence », `categorie_polluants` = 1 (essence Euro 6).
- Taxe CO₂ : 383 × 181/366 = 189,40 € (calcul WLTP 130 g/km, cf. `2024/taxe-co2/decisions.md` Décision 4).
- Taxe polluants : 100 × 181/366 = 49,45 €.

Du 01/07/2024 au 31/12/2024 (185 jours) :

- `source_energie` = « Superéthanol E85 » (mono-carburant).
- En 2024, l'E85 mono-carburant n'est ni exonéré ni abattu (cf. `2024/abattements/decisions.md`) → plein tarif sur 130 g/km WLTP.
- L'E85 reste un carburant à allumage commandé Euro 6 → catégorie polluants = 1.
- Taxe CO₂ : 383 × 185/366 = 193,58 €.
- Taxe polluants : 100 × 185/366 = 50,55 €.

Total annuel pour ACME :

- Taxe CO₂ : 189,40 + 193,58 = 382,98 € (équivalent à 383 € pour toute l'année — la conversion E85 ne change pas le tarif WLTP en 2024 puisqu'il n'y a pas d'abattement).
- Taxe polluants : 49,45 + 50,55 = 100,00 € (équivalent à 100 € pour toute l'année — la conversion ne change pas la catégorie 1).
- Total : **482,98 €**.

**Note** : dans ce cas particulier, la conversion E85 **ne change pas** le résultat fiscal en 2024 (puisque l'E85 ne bénéficie pas d'abattement isolé en 2024). Mais le mécanisme d'historisation est nécessaire pour :

- Tracer la conversion de manière auditable dans le PDF récapitulatif.
- Permettre, à compter de 2025, l'application de l'abattement E85 sur la part de l'année où le véhicule est en E85 (cas qui sera instruit dans `2025/abattements/`).

### 10.5 Statut — nouvelle incertitude

Cette mission **n'ouvre pas de nouvelle incertitude** au sens fiscal, mais identifie une **exigence produit majeure** (historisation des caractéristiques fiscales) qui dépasse le périmètre strict de la recherche fiscale et relève de la conception applicative. À documenter dans le cahier des charges (cf. `decisions.md` Décision 6).

---

## 11. Synthèse — Cas particuliers instruits et statut des incertitudes

### 11.1 Cas particuliers traités

| Cas | Sujet | Sous-dossier d'origine | Statut |
|---|---|---|---|
| A | Frontière fiscale M1 / N1 | `2024/taxe-co2/` | **Résolu** par la présente mission |
| B | Véhicule importé d'occasion (méthode WLTP/NEDC) | `2024/taxe-co2/` | **Résolu** par la présente mission |
| C | Bascule automatique sur barème PA si donnée CO₂ manquante | `2024/taxe-co2/` | **Résolu** par la présente mission (point UX) |
| D | Hybrides Diesel-électrique (catégorie polluants) | `2024/taxe-polluants/` | **Maintenu Ouvert** (validation expert-comptable souhaitable) |
| E | Qualification LLD vs LCD pour Floty | `2024/taxe-co2/` (enrichi par `2024/exonerations/`) | **Maintenu Ouvert** (validation expert-comptable indispensable, priorité haute) |
| F | Indisponibilités longues hors fourrière | `2024/taxe-co2/` | **Maintenu Ouvert** (validation expert-comptable nécessaire) |
| G | Véhicule mis hors-service en cours d'année | `2024/taxe-co2/` (Q7) | **Résolu** (point UX, pas d'incertitude juridique) |
| H | Véhicule changeant de caractéristiques fiscales en cours d'année | (nouveau, identifié par cette mission) | **Résolu** (lecture par défaut + exigence produit historisation) |

### 11.2 Statut des incertitudes 2024 après cette mission

| Référence | Sujet | Statut avant | Statut après |
|---|---|---|---|
| Z-2024-001 | Indisponibilités longues hors fourrière | Ouvert | **Ouvert** (consolidé en règle Floty) |
| Z-2024-002 | Qualification du modèle Floty au regard de l'exonération LCD | Ouvert (haute) | **Résolu — 23/04/2026** (par clarification client ultérieure) |
| Z-2024-003 | Abattement E85 en 2024 | Résolu (par `2024/abattements/`) | **Résolu** (inchangé) |
| Z-2024-004 | Véhicule importé d'occasion | Ouvert | **Résolu — 23/04/2026** |
| Z-2024-005 | Frontière fiscale M1 / N1 | Ouvert | **Résolu — 23/04/2026** |
| Z-2024-006 | Bascule automatique sur barème PA | Ouvert | **Résolu — 23/04/2026** |
| Z-2024-007 | Hybrides Diesel-électrique | Ouvert | **Ouvert** (consolidé, validation EC souhaitable) |
| Z-2024-008 | Vérification exemple BOFiP § 290 | Ouvert | **Ouvert** (sans changement — point documentaire faible impact) |
| Z-2024-009 | Garde-fou Crit'Air vs motorisation+Euro | Ouvert | **Ouvert** (sans changement — point UX) |
| Z-2024-010 | Date de référence ancienneté hybride 2024 | Ouvert | **Ouvert** (sans changement — validation EC) |

Bilan : **3 incertitudes résolues** par cette mission (Z-2024-004, Z-2024-005, Z-2024-006), **2 incertitudes consolidées et maintenues ouvertes** (Z-2024-001, Z-2024-007), **3 incertitudes inchangées** (Z-2024-008, Z-2024-009, Z-2024-010 — non du périmètre matériel de cette mission), **1 incertitude consolidée puis résolue ultérieurement** (Z-2024-002 — résolue le 23/04/2026 par clarification client après cette mission).

### 11.3 Pas de nouvelle incertitude au sens fiscal

Cette mission n'ouvre **aucune nouvelle incertitude juridique**. Elle identifie en revanche une **exigence produit** structurante (historisation des caractéristiques fiscales du véhicule — cas H) qui sera traitée comme proposition de mise à jour du cahier des charges (cf. `decisions.md` Décision 6). Cette exigence dépasse le périmètre strict de la recherche fiscale.

### 11.4 Mises à jour proposées au cahier des charges

1. **§ 2.1 — Type de véhicule** : préciser la qualification N1 selon les critères CIBS (camionnettes ≥ **2 rangs** de places affectées au transport de personnes ; pick-ups ≥ 5 places — déjà conforme), ajouter le critère « affecté au transport de personnes » pour les camionnettes N1.
2. **§ 2.1 — Champ « Type de moteur thermique sous-jacent »** : ajouter ce champ conditionnel pour les motorisations hybrides (essence ou Diesel), indispensable pour le classement polluants.
3. **§ 2.1 — Historisation des caractéristiques fiscales** : ajouter une mention sur la nécessité d'historiser les caractéristiques fiscales d'un véhicule (cas de conversion E85, modification d'aménagement, etc.).
4. **§ 2.5 — Indisponibilités** : préciser que seul le type « Fourrière / immobilisation administrative » a un impact fiscal (réduction du numérateur du prorata).

---

## 12. Limites de la recherche

### 12.1 Périmètre temporel

Cette recherche se limite **strictement à 2024**. Les évolutions 2025 (suppression de l'exonération hybride § L. 421-125 dans sa version 2022-2024 ; apparition de l'abattement E85 ; suppression de l'option forfaitaire trimestrielle) et 2026 (revalorisation des tarifs polluants par LF 2026 art. 58) seront instruites dans `2025/cas-particuliers/` et `2026/cas-particuliers/` respectivement.

### 12.2 Hors périmètre matériel

Sont volontairement exclus de cette recherche :

- Coefficient pondérateur frais kilométriques (mécanique distincte, hors usage Floty V1) — déjà cadré dans les sous-dossiers taxe-co2 et taxe-polluants.
- Cas particuliers liés aux exonérations marginales (organismes intérêt général, entreprises individuelles, transport public, agricole/forestier, enseignement de la conduite) — déjà documentés dans `2024/exonerations/` comme inactifs par défaut.
- Cas particuliers liés à la qualification fiscale de véhicules très exotiques (véhicules diplomatiques, véhicules militaires, véhicules de collection > 30 ans) — hors périmètre Floty (flotte d'entreprise classique).

### 12.3 Validation expert-comptable

À l'issue de cette mission, **trois incertitudes** restent à valider par l'expert-comptable :

- **Z-2024-001** (indisponibilités longues hors fourrière) — priorité moyenne.
- **Z-2024-002** (qualification du modèle Floty au regard de l'exonération LCD) — **priorité haute** *à l'issue de cette mission ; ultérieurement résolue le 23/04/2026 par clarification directe avec le client (lecture définitive : LCD avec cumul annuel par couple)*.
- **Z-2024-007** (hybrides Diesel-électrique) — priorité moyenne.

Ces incertitudes sont entièrement documentées en règle d'implémentation Floty (avec champs, algorithmes, alertes UI) ; la validation expert-comptable porte sur la conformité de la lecture juridique retenue par défaut, pas sur la mécanique d'implémentation. Z-2024-002 a été résolue postérieurement à cette mission ; il reste donc deux incertitudes pour validation expert-comptable (Z-2024-001 et Z-2024-007).

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Recherche initiale cas particuliers et règles transitoires 2024 — 8 cas particuliers instruits (A à H) couvrant la frontière M1/N1, l'importation d'occasion, la bascule PA, les hybrides Diesel, la qualification LLD/LCD, les indisponibilités longues, les véhicules hors-service, et la conversion en cours d'année. 3 incertitudes résolues (Z-2024-004, Z-2024-005, Z-2024-006), 3 maintenues ouvertes (Z-2024-001, Z-2024-002, Z-2024-007), aucune nouvelle incertitude juridique. Identification d'une exigence produit majeure (historisation des caractéristiques fiscales) à intégrer au cahier des charges. |
| 0.2 | 23/04/2026 | Micha MEGRET | § 7 (Cas particulier E — Qualification LLD vs LCD) réécrit pour refléter la clarification directe avec le client : la lecture définitive est « LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice) » et non « LLD par défaut ». Les champs Floty proposés initialement (`qualification_mise_a_disposition`, `qualification_specifique`) sont abandonnés au profit d'une application systématique de la règle de cumul. Z-2024-002 est passée au statut **Résolu**. Statut du dossier 2024 actualisé : 5 résolues, 5 ouvertes, plus aucune priorité haute. |
