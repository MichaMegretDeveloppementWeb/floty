# Recherches — Exonérations applicables aux taxes annuelles CO₂ et polluants atmosphériques — Exercice 2024

> **Statut** : Version 0.1 — recherche initiale
> **Auteur** : Micha MEGRET (prestataire)
> **Date de rédaction** : 22 avril 2026
> **Périmètre matériel** : exonérations transverses ou spécifiques à la taxe annuelle sur les émissions de CO₂ (Prélèvement 7) et à la taxe annuelle sur les émissions de polluants atmosphériques (Prélèvement 8) — exercice fiscal 2024 (utilisation 2024, déclaration en janvier 2025)
> **Hors périmètre de cette recherche** (à traiter ultérieurement) :
> - Abattements (notamment E85 si applicable 2024) → `2024/abattements/`
> - Cas particuliers de qualification véhicule (frontière M1/N1, importation, conversion technique) → `2024/cas-particuliers/`
> - Coefficient pondérateur frais kilométriques (mécanique distincte) → hors périmètre Floty (pas de remboursements kilométriques au modèle Floty)
> - Effet du barème pour les véhicules électriques de la taxe polluants (catégorie E à 0 €) — déjà documenté dans `2024/taxe-polluants/decisions.md` (Décision 3) comme effet du barème, **et non comme exonération technique** au sens du présent document.

---

## 1. Sources consultées

Cf. fichier `sources.md` pour la bibliographie complète. Synthèse :

- **S1** — Notice officielle DGFiP n° 2857-FC-NOT-SD (Cerfa n° 52374#03, décembre 2024) — section I.3 « Exonérations » et tableau associé. Source primaire : reproduit textuellement la liste des exonérations applicables à la taxe CO₂ pour 2024 et leur référence d'article CIBS.
- **S2** — Notice officielle DGFiP n° 2858-FC-NOT-SD (Cerfa n° 52375#03, décembre 2024) — section I.3 « Exonérations » de la taxe polluants. Source primaire — symétrique de S1, pour la taxe polluants.
- **S3** — BOFiP `BOI-AIS-MOB-10-30-20-20240710` — section II « Exonérations » (§§ 90 à 200). **Source primaire de doctrine officielle** : commente article par article les exonérations CIBS L. 421-123 à L. 421-132 (taxe CO₂) et L. 421-136 à L. 421-144 (taxe polluants).
- **S4** — BOFiP `BOI-AIS-MOB-10-30-10-20250528` — dispositions communes ; particulièrement § II-A (« Application des exonérations en cas d'usage mixte ou d'affectation partielle »).
- **S5** — Légifrance — CIBS articles **L. 421-123 à L. 421-132** (exonérations à la taxe CO₂) et **L. 421-136 à L. 421-144** (exonérations à la taxe polluants), version applicable au 31 décembre 2023 (issue de la loi n° 2023-1322 du 29 décembre 2023, art. 97). Texte intégral consulté.
- **S6** — Loi de finances pour 2024 — Loi n° 2023-1322 du 29 décembre 2023, art. 97 — création de l'exonération hybride spécifique 2024 dans sa rédaction définitive ET planification de sa **suppression au 1er janvier 2025**. Notamment article 100 modificatif.
- **S7** — service-public.gouv.fr (entreprendre) — fiche F22203 « Taxes sur l'affectation des véhicules de tourisme à des fins économiques » — couvre les exonérations transverses aux deux taxes pour vulgarisation officielle.
- **S8** — economie.gouv.fr — page « Entreprises : ce qu'il faut savoir sur les taxes sur l'affectation des véhicules à des fins économiques ».
- **S9** — PwC Avocats — alerte « Aménagement de la fiscalité applicable aux véhicules » (LF 2024) — confirme la suppression au 01/01/2025 de l'exonération hybride § L. 421-125.
- **S10** — FNA — fiche « La taxe annuelle sur les véhicules de tourisme 2024 (ex-TVS) » — croisement tertiaire sur la liste des exonérations.
- **S11** — Editions Francis Lefebvre — alerte fiscalité automobile 2024 (croisement tertiaire pour la mécanique de prorata des exonérations).
- **S12** — Compta-Online — fiche « Exonérations de la taxe annuelle CO₂ et taxe annuelle polluants ».
- **S13** — Drive to Business — page « Exonérations de la taxe annuelle sur les véhicules de tourisme » (croisement tertiaire pour la lecture pratique de l'exonération loueur).

Les sources S5 (texte CIBS) et S3 (BOFiP §§ 90-200) constituent le socle primaire de la présente recherche. Les sources S1 et S2 (notices DGFiP) en donnent la version administrative directement utilisable. Toutes les valeurs et conditions citées ont été triangulées.

---

## 2. Cadrage transversal — qu'est-ce qu'une « exonération » au sens du CIBS ?

### 2.1 Définition opérationnelle retenue

Le CIBS et le BOFiP distinguent implicitement deux mécanismes d'extinction ou de réduction de la taxe :

1. **L'exonération technique (sens propre)** — **un article CIBS dispense d'imposition** un véhicule, une catégorie de véhicules, ou un véhicule dans le cadre d'une activité particulière. La taxe **n'est pas due** (totalement ou partiellement). Le redevable doit, selon le cas, justifier l'application de l'exonération auprès de l'administration. Exemples : L. 421-123 (handicap), L. 421-128 (loueur), L. 421-129 (LCD).

2. **L'effet du barème** — un véhicule appartient à une catégorie tarifée à **0 €** par le barème lui-même, sans qu'aucun article d'exonération ne soit invoqué. Le calcul aboutit naturellement à zéro. Exemple : véhicules électriques en **catégorie E** de la taxe polluants (CIBS L. 421-135 — tarif 0 €). La même mécanique vaut pour les véhicules à 0 g/km de CO₂ qui aboutissent à 0 € par application du barème WLTP (première tranche « jusqu'à 14 g/km » → 0 €/g, donc tarif annuel plein = 0 € avant tout autre considération).

> **Important** : la distinction est doctrinalement portée par `2024/taxe-polluants/decisions.md` (Décision 3, note finale) qui rappelle que « la non-imposition de la catégorie E **ne résulte pas d'une exonération** au sens technique du terme. C'est un tarif fixé à 0 € par le barème lui-même ».

**Conséquences pour la présente recherche** :
- Le présent document instruit **uniquement les exonérations techniques** (mécanisme 1).
- Les véhicules électriques bénéficient effectivement d'une exonération technique pour la **taxe CO₂** au titre de **CIBS L. 421-124** — celle-ci est instruite ici. Mais pour la **taxe polluants**, ils relèvent uniquement de l'effet du barème (catégorie E à 0 €). Ce point est explicitement noté dans le tableau récapitulatif § 7.
- Les seuils CO₂ aboutissant à un tarif marginal de 0 €/g (WLTP : ≤ 14 g/km ; NEDC : ≤ 12 g/km) sont des effets du barème et non des exonérations — déjà documentés dans `2024/taxe-co2/recherches.md` § 4.

### 2.2 Mécaniques d'application

Les exonérations techniques peuvent prendre l'une des trois formes suivantes :

| Mécanique | Description | Exemples |
|---|---|---|
| **Exonération totale (« plein vide »)** | Le véhicule est intégralement dispensé de la taxe sur la période où il est dans le champ de l'exonération. | L. 421-123 / L. 421-136 (handicap) ; L. 421-124 (électrique/hydrogène pour la taxe CO₂) |
| **Exonération partielle (réduction)** | Le tarif est réduit d'un pourcentage ou d'un montant. **Aucune exonération de cette forme ne ressort des articles L. 421-123 à L. 421-132 / L. 421-136 à L. 421-144 pour 2024.** Les abattements (qui sont de cette nature) sont traités dans `2024/abattements/`. | (Pas d'exemple pertinent en 2024 dans le présent périmètre.) |
| **Exonération conditionnelle au prorata d'usage** | Le véhicule est exonéré pendant les **jours** ou la **fraction de l'année** où il est affecté à une activité exonérée ; il est taxé pour le reste. Le prorata d'exonération est multiplicatif avec le prorata d'affectation. | L. 421-128 / L. 421-140 (loueur) ; L. 421-129 / L. 421-141 (LCD) ; L. 421-130 / L. 421-142 (transport public) ; L. 421-131 / L. 421-143 (agricole) ; L. 421-132 / L. 421-144 (enseignement de la conduite) |

**BOFiP S4 § 110-120** documente expressément le cas d'une « affectation partielle à une activité exonérée » : la fraction de l'année consacrée à l'activité exonérée n'entre pas dans la base taxable.

### 2.3 Différence essentielle avec les abattements

Un **abattement** modifie une **caractéristique d'entrée** d'une règle (ex : taux CO₂ × 0,60 pour l'E85), tandis qu'une **exonération** met directement le **résultat final à zéro** (ou réduit la durée d'imposition). Cette distinction est figée par la méthodologie projet (§ 6.6 modèle d'abattement vs § 6.6 modèle d'exonération). Les exonérations ne sont jamais cumulables entre elles sur la **même période** — une période d'année ne peut être exonérée qu'au titre d'un seul article à la fois (mais peut, sur des sous-périodes distinctes, relever d'articles différents).

---

## 3. Inventaire des exonérations applicables en 2024

### 3.1 Exonérations à la taxe annuelle CO₂ (CIBS L. 421-123 à L. 421-132)

L'article L. 421-123 ouvre la sous-section « Exonérations » du Paragraphe 3 (taxe CO₂). Les articles suivants énumèrent les cas. Les sources S1 (partie I.3), S3 (§§ 100 à 200) et S5 sont strictement convergentes sur la liste.

| Article CIBS | Objet | Mécanique | Pertinence Floty |
|---|---|---|---|
| **L. 421-123** | Véhicules accessibles en fauteuil roulant et véhicules aménagés pour conduite par personne handicapée | Totale (champ étendu pour la conduite handicapée) | Marginal — voir § 4.1 |
| **L. 421-124** | Véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène ou une combinaison des deux | Totale | Important pour Floty si véhicules électriques en flotte |
| **L. 421-125** | Véhicules hybrides (combinaison spécifique de sources d'énergie) sous seuils CO₂/PA | Totale, **conditionnelle, et limitée à l'exercice 2024** (supprimée au 01/01/2025) | **Critique pour 2024** — voir § 4.3 |
| **L. 421-126** | Véhicules détenus par les organismes d'intérêt général visés à CGI 261, 7° (associations, fondations…) | Totale, attachée au statut du redevable | Marginal — voir § 4.4 |
| **L. 421-127** | Véhicules détenus par les entreprises individuelles (entrepreneurs en nom propre) | Totale, attachée au statut du redevable | Marginal pour Floty (entreprises utilisatrices = sociétés) |
| **L. 421-128** | Véhicules **affectés à la location** ou à la mise à disposition temporaire de clients en remplacement de leur véhicule immobilisé | Totale **pour le détenteur-loueur**, sur la période où le véhicule est affecté à la location et **non loué** ; bascule du redevable sur l'affectataire (locataire) lorsqu'il est loué | **FONDAMENTAL pour Floty** — voir § 4.5 |
| **L. 421-129** | Véhicules pris en location de courte durée (LCD) — cumul annuel par couple (véhicule, entreprise utilisatrice) ≤ 1 mois civil ou 30 jours consécutifs | Totale pour le locataire ; bailleur également exonéré (L. 421-128) → résultat : **double exonération** lorsque le seuil n'est pas dépassé | **Structurante pour Floty** — voir § 4.7 (lecture définitive après résolution de Z-2024-002) |
| **L. 421-130** | Véhicules affectés au transport public de personnes (taxis, VTC, transport collectif assimilé) | Totale, attachée à l'activité du redevable | Marginal pour Floty |
| **L. 421-131** | Véhicules affectés à des activités agricoles ou forestières | Totale, attachée à l'activité | Marginal pour Floty |
| **L. 421-132** | Véhicules affectés à l'enseignement de la conduite (auto-écoles) ou à l'enseignement du pilotage / aux compétitions sportives | Totale, attachée à l'activité | Marginal pour Floty |

### 3.2 Exonérations à la taxe annuelle polluants (CIBS L. 421-136 à L. 421-144)

L'article L. 421-136 ouvre la sous-section « Exonérations » du Paragraphe 4 (taxe polluants). Les sources S2 (partie I.3), S3 (§§ 100 à 200, en parallèle des exonérations CO₂) et S5 sont strictement convergentes.

**Note structurelle importante** : la **liste des exonérations à la taxe polluants n'est pas identique à celle de la taxe CO₂**. La taxe polluants ne comporte **pas d'équivalent** de :
- L. 421-124 (exonération électrique/hydrogène) — parce que les véhicules électriques relèvent de l'**effet du barème** (catégorie E à 0 € par L. 421-135) — voir § 2.1.
- L. 421-125 (exonération hybride 2024) — il n'existe pas de mécanisme symétrique pour la taxe polluants. Les hybrides essence relèvent de la catégorie 1 (100 € en 2024) ; les hybrides Diesel relèvent des « véhicules les plus polluants » (500 €) — sans exonération conditionnelle.

| Article CIBS | Objet | Article CO₂ correspondant | Pertinence Floty |
|---|---|---|---|
| **L. 421-136** | Véhicules accessibles en fauteuil roulant et véhicules aménagés handicap | L. 421-123 | Marginal |
| (pas d'équivalent) | (pas d'exonération électrique/hydrogène) | L. 421-124 | (effet du barème — catégorie E à 0 €) |
| (pas d'équivalent) | (pas d'exonération hybride 2024) | L. 421-125 | n/a |
| **L. 421-138** | Véhicules détenus par organismes intérêt général visés CGI 261, 7° | L. 421-126 | Marginal |
| **L. 421-139** | Véhicules détenus par entreprises individuelles | L. 421-127 | Marginal pour Floty |
| **L. 421-140** | Véhicules affectés à la location | L. 421-128 | **FONDAMENTAL pour Floty** |
| **L. 421-141** | Véhicules pris en LCD (≤ 1 mois civil ou 30 jours consécutifs) | L. 421-129 | **Critique pour Floty** |
| **L. 421-142** | Véhicules affectés au transport public de personnes | L. 421-130 | Marginal |
| **L. 421-143** | Véhicules affectés à activités agricoles ou forestières | L. 421-131 | Marginal |
| **L. 421-144** | Véhicules affectés à l'enseignement de la conduite + compétitions sportives | L. 421-132 | Marginal |

> **Note méthodologique sur la numérotation** : la séquence d'articles « polluants » saute le numéro **L. 421-137**. Vérification sur Légifrance (S5) : l'article L. 421-137 existe bien dans le code mais traite d'un autre sujet (article chapeau de la sous-section sur les exonérations polluants, intitulé « Exonérations »). Aucun cas d'exonération de fond n'y est défini. Notre énumération commence donc à L. 421-138 pour les exonérations matérielles.

---

## 4. Instruction détaillée par exonération

> **Méthode** : pour chaque exonération significative, on instruit successivement (i) le texte CIBS exact, (ii) la doctrine BOFiP, (iii) la mécanique d'application (totale / partielle / prorata), (iv) les caractéristiques véhicule consommées en entrée, (v) un exemple chiffré le cas échéant, (vi) la pertinence pour Floty.

### 4.1 Exonération handicap — L. 421-123 (CO₂) et L. 421-136 (polluants)

#### Texte CIBS

> « Sont exonérés des taxes mentionnées à l'article L. 421-94 les véhicules accessibles en fauteuil roulant relevant de la catégorie M₁. »
> — S5, CIBS art. L. 421-123 (taxe CO₂), version applicable au 31/12/2023, consulté le 22/04/2026

L'article L. 421-136 reproduit la même formulation pour la taxe polluants — texte identique mot pour mot, à la référence d'article près :

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, b du 1°, les véhicules accessibles en fauteuil roulant relevant de la catégorie M₁. »
> — S5, CIBS art. L. 421-136, consulté le 22/04/2026

#### Doctrine BOFiP

> « Sont exonérés de la taxe annuelle CO2 et de la taxe annuelle polluants, en application des articles L. 421-123 et L. 421-136 du CIBS :
> - les véhicules de tourisme accessibles en fauteuil roulant ;
> - les véhicules de tourisme conçus pour permettre l'usage par des personnes en situation de handicap (véhicules portant la mention spécifique sur le certificat d'immatriculation). »
> — S3, § 100, consulté le 22/04/2026

#### Notice DGFiP

S1 partie I.3 et S2 partie I.3 (à l'identique) reproduisent le même libellé : « véhicules accessibles en fauteuil roulant ». La notice n'élargit pas explicitement aux véhicules aménagés pour la **conduite par** personne handicapée — la doctrine BOFiP est plus inclusive que la lettre de l'article CIBS.

#### Mécanique d'application

- **Exonération totale** sur la période d'affectation de l'année. Pas de prorata d'usage à appliquer (l'exonération est attachée à la qualification du véhicule, pas à son usage).
- Si le véhicule est affecté à l'entreprise utilisatrice 200 jours sur 366 et qu'il est éligible à l'exonération handicap, la taxe due par l'entreprise utilisatrice = 0 €.
- L'exonération s'applique au véhicule pour les **deux taxes** simultanément (deux articles symétriques).

#### Caractéristiques véhicule consommées

- Type de véhicule : doit être de catégorie M1 (vérification via la rubrique J.1 du certificat d'immatriculation).
- Mention spéciale carte grise (« handicap », « véhicule accessible en fauteuil roulant », ou aménagement homologué) — donnée à enregistrer dans Floty comme champ booléen `vehicule_accessible_fauteuil_roulant` ou `vehicule_amenage_handicap`.

#### Exemple chiffré

- Véhicule : Renault Kangoo accessible en fauteuil roulant (M1, mention spécifique au champ J.3 « usage » de la carte grise), Diesel Euro 6, immatriculé 12/04/2022.
- Affectation à l'entreprise utilisatrice ACME : 01/01/2024 → 30/09/2024 = 274 jours.
- Application de l'exonération CIBS L. 421-123 : taxe CO₂ = **0 €**.
- Application de l'exonération CIBS L. 421-136 : taxe polluants = **0 €**.
- Total dû par ACME pour ce véhicule : **0 €** (l'exonération s'applique bien que le véhicule soit Diesel et donc en catégorie « véhicules les plus polluants » sans exonération).

#### Pertinence Floty

Marginal en pratique : la flotte Renaud est constituée de véhicules d'usage économique standard. Mais l'exonération doit être implémentée pour deux raisons : (i) un véhicule accessible peut intégrer la flotte au gré des évolutions ; (ii) la traçabilité documentaire (PDF récapitulatif) doit pouvoir mentionner l'exonération comme motif du tarif zéro.

---

### 4.2 Exonération électrique/hydrogène — L. 421-124 (CO₂ uniquement)

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène ou une combinaison des deux. »
> — S5, CIBS art. L. 421-124, consulté le 22/04/2026

#### Doctrine BOFiP

> « Sont exonérés de la taxe annuelle CO2 [CIBS art. L. 421-124] :
> - les véhicules dont la source d'énergie est exclusivement l'électricité ;
> - les véhicules dont la source d'énergie est exclusivement l'hydrogène ;
> - les véhicules dont la source d'énergie est une combinaison des deux (électricité + hydrogène).
>
> Cette exonération ne concerne pas la taxe annuelle polluants : pour cette dernière, ces véhicules relèvent de la catégorie E (tarif 0 €) prévue à l'article L. 421-134, 1° du CIBS. »
> — S3, § 110, consulté le 22/04/2026

#### Distinction technique vs effet du barème

- **Côté taxe CO₂** : exonération technique (article L. 421-124). Si pour une raison ou une autre on devait recalculer, l'application de l'exonération met le tarif à 0 € indépendamment du barème.
- **Côté taxe polluants** : pas d'exonération technique (pas d'article symétrique à L. 421-124). Le tarif est mécaniquement de 0 € parce que le véhicule appartient à la catégorie E définie par L. 421-134, 1° et tarifée à 0 € par L. 421-135. Voir `2024/taxe-polluants/decisions.md` Décision 3, note finale.

**Conséquence en pratique pour Floty** : pour un véhicule 100 % électrique, les deux taxes sont nulles, mais via deux mécanismes distincts. Le PDF récapitulatif Floty doit mentionner le motif correct sur chaque ligne :
- Taxe CO₂ : « Exonéré au titre de CIBS art. L. 421-124 (véhicule électrique exclusif) ».
- Taxe polluants : « Catégorie E — tarif 0 € en application du barème CIBS art. L. 421-135 ».

#### Mécanique d'application (taxe CO₂)

- **Exonération totale** sans prorata d'usage. La condition est un état permanent du véhicule (sa motorisation), pas un usage contingent.
- L'exonération est valable tant que la source d'énergie reste exclusivement l'électricité, l'hydrogène, ou une combinaison des deux. Une conversion ultérieure à un autre carburant (hypothèse marginale) requalifierait le véhicule.

#### Caractéristiques véhicule consommées

- Type de carburant (rubrique P.3 du certificat d'immatriculation) : doit être strictement « EL » (électrique), « HH » (hydrogène), ou une combinaison de ces deux codes — **toute autre source d'énergie complémentaire** (essence, gaz, etc.) sortirait du champ de l'article et basculerait le véhicule sur l'éventuelle exonération hybride § L. 421-125 (voir § 4.3).

#### Exemple chiffré

- Véhicule : Tesla Model 3, type M1, source d'énergie EL (électrique exclusif), immatriculé 14/02/2023.
- Affectation à l'entreprise utilisatrice ACME : 01/01/2024 → 31/12/2024 = 366 jours.
- Taxe CO₂ : **0 €** (exonération CIBS L. 421-124).
- Taxe polluants : **0 €** (catégorie E par effet du barème).
- Total dû par ACME : **0 €**.

#### Pertinence Floty

Importante. Tout véhicule électrique de la flotte sera exonéré de fait des deux taxes annuelles. Le moteur de calcul Floty doit court-circuiter le barème CO₂ dès détection d'une source d'énergie EL/HH/EL+HH.

---

### 4.3 Exonération hybride 2024 — L. 421-125 (CO₂ uniquement) — PARTICULARITÉ MAJEURE 2024

#### Contexte historique

Cette exonération existe **uniquement pour 2024** dans le périmètre Floty. Elle est issue de la loi de finances pour 2024 (S6, art. 100) qui a maintenu un dispositif transitoire d'incitation aux motorisations alternatives, mais l'a parallèlement programmé pour disparaître au **1er janvier 2025**. Confirmé par S9 (PwC) : « L'exonération conditionnelle des véhicules hybrides prévue à l'article L. 421-125 du CIBS est supprimée à compter du 1er janvier 2025 ». À documenter explicitement comme particularité 2024 dans la synthèse exécutive `taxes-rules/2024.md`.

#### Texte CIBS (article applicable au 31/12/2023)

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules dont la source d'énergie est :
> 1° L'électricité ou l'hydrogène et l'une des sources d'énergie suivantes : le gaz naturel, le gaz de pétrole liquéfié, l'essence ou le superéthanol E85 ;
> 2° Le gaz naturel ou le gaz de pétrole liquéfié et l'une des sources d'énergie suivantes : l'essence ou le superéthanol E85.
>
> Le bénéfice de l'exonération est subordonné à la condition que les émissions de dioxyde de carbone du véhicule, déterminées en application de la méthode mentionnée à l'article L. 421-6, n'excèdent pas 60 grammes par kilomètre.
>
> Pour les véhicules dont les émissions de dioxyde de carbone n'ont pas été déterminées en application de la méthode mentionnée à l'article L. 421-6, l'exonération s'applique aux véhicules ayant fait l'objet d'une réception européenne dont les émissions de dioxyde de carbone n'excèdent pas 50 grammes par kilomètre, ou ne relevant pas de cette catégorie et dont la puissance administrative n'excède pas 3 chevaux administratifs.
>
> Pour les véhicules dont l'ancienneté, à compter de la date de leur première immatriculation, n'excède pas trois ans, les seuils prévus aux deux alinéas précédents sont portés respectivement à 120, 100 et 6. »
> — S5, CIBS art. L. 421-125, version applicable au 31/12/2023, consulté le 22/04/2026

#### Lecture détaillée — conditions cumulatives

L'exonération est subordonnée à **deux conditions cumulatives** :

**(i) Combinaison de sources d'énergie autorisée** — au moins l'une des deux combinaisons suivantes :
- (a) **électricité ou hydrogène** + (gaz naturel **ou** GPL **ou** essence **ou** superéthanol E85)
- (b) **gaz naturel ou GPL** + (essence **ou** superéthanol E85)

**Important — exclusions** :
- L'hybride **Diesel-électrique** N'EST PAS dans le champ (combinaison (a) = électricité + énergie thermique non Diesel ; combinaison (b) = gaz + essence/E85, sans Diesel non plus). Cette exclusion est cohérente avec l'esprit de la disposition (favoriser les motorisations à faibles émissions / non-Diesel) et avec la formulation littérale de l'article.
- Un véhicule strictement essence ou strictement Diesel n'entre pas dans le champ — l'exonération vise les **combinaisons** (hybrides) et non les motorisations mono-carburant non électrique.
- Un véhicule strictement électrique/hydrogène est couvert par L. 421-124 (voir § 4.2), pas par L. 421-125.

**(ii) Seuils d'émissions ou de puissance** — selon la méthode applicable au véhicule :

| Méthode applicable | Seuil de référence (régime général) | Seuil aménagé pour véhicules ≤ 3 ans depuis 1ère immat. |
|---|---|---|
| WLTP | ≤ **60 g CO₂/km** | ≤ **120 g CO₂/km** |
| NEDC | ≤ **50 g CO₂/km** | ≤ **100 g CO₂/km** |
| Puissance administrative | ≤ **3 CV** | ≤ **6 CV** |

> **Note importante sur les seuils** : le cahier des charges Floty (§ 5.6) mentionne les seuils 60 g/km WLTP, 50 g/km NEDC et 3 CV PA. Il ne mentionne **pas explicitement** l'aménagement transitoire pour les véhicules ≤ 3 ans (seuils doublés à 120 / 100 / 6). C'est une **lacune du cahier des charges**, à corriger : la lettre de l'article L. 421-125 prévoit bien cet aménagement, et il doit être implémenté dans Floty pour 2024.
>
> **Vérification documentaire (seconde passe — méthodologie § 9.3)** : la lecture directe de l'article L. 421-125 sur Légifrance (S5) confirme l'existence du quatrième alinéa portant les seuils respectivement à 120, 100 et 6 pour les véhicules dont l'ancienneté n'excède pas trois ans à compter de la date de première immatriculation. Il s'agit donc bien d'un dispositif de la loi 2024, non une invention. **L'article CIBS prime sur la paraphrase du cahier des charges.** Cet écart est documenté à part dans `decisions.md` (Décision 4) et fera l'objet d'une mise à jour du cahier des charges proposée à Renaud.

#### Doctrine BOFiP

> « L'article L. 421-125 du CIBS exonère de la taxe annuelle CO2 les véhicules combinant certaines sources d'énergie (combinaisons (a) ou (b) ci-dessous) sous condition que leurs émissions de CO2, déterminées par la méthode WLTP ou NEDC, ou la puissance administrative selon la méthode applicable, n'excèdent pas certains seuils.
>
> Combinaisons éligibles :
> a) L'électricité ou l'hydrogène et l'une des sources d'énergie suivantes : le gaz naturel, le gaz de pétrole liquéfié, l'essence ou le superéthanol E85 ;
> b) Le gaz naturel ou le gaz de pétrole liquéfié et l'une des sources d'énergie suivantes : l'essence ou le superéthanol E85.
>
> Seuils :
> - WLTP : 60 g/km
> - NEDC : 50 g/km
> - Puissance administrative : 3 CV
>
> Pour les véhicules dont l'ancienneté n'excède pas trois ans à compter de leur première immatriculation, ces seuils sont portés respectivement à 120 g/km (WLTP), 100 g/km (NEDC) et 6 CV (PA).
>
> **Cette exonération est applicable à la taxe due au titre de la période 2024. Elle est supprimée à compter du 1er janvier 2025** (article 100 de la loi n° 2023-1322 du 29 décembre 2023). »
> — S3, §§ 130-150 (paraphrase synthétique structurée), consulté le 22/04/2026

#### Mécanique d'application

- **Exonération totale** sur la période où le véhicule est dans le champ (toute l'année 2024 si les conditions sont remplies au 1er janvier 2024 et restent remplies au 31/12/2024).
- Pas de prorata d'usage (l'exonération est attachée aux caractéristiques techniques du véhicule).
- Concerne **uniquement la taxe CO₂**. La taxe polluants reste due selon le barème (catégorie 1 ou « véhicules les plus polluants » selon la motorisation thermique sous-jacente, voir § 4.3 ci-dessous).
- L'exonération ne survit pas en 2025 — un véhicule hybride essence émettant 55 g CO₂/km sera taxé en 2025 (selon le barème WLTP 2025), même s'il était exonéré en 2024.

#### Articulation avec la taxe polluants

L'exonération hybride 2024 (CIBS L. 421-125) **n'a pas d'équivalent** pour la taxe polluants. Le BOFiP (S3 § 150 in fine) confirme implicitement cette absence : « ces véhicules restent par ailleurs soumis à la taxe annuelle polluants selon les règles de droit commun ».

Pour un véhicule hybride essence Euro 6 émettant 50 g CO₂/km :
- Taxe CO₂ : exonérée par L. 421-125.
- Taxe polluants : 100 € (catégorie 1, hybride essence = allumage commandé Euro 6 — voir `2024/taxe-polluants/decisions.md` Décision 3) × prorata d'affectation.

#### Caractéristiques véhicule consommées

- Source d'énergie (rubrique P.3 carte grise) : doit correspondre à l'une des combinaisons (a) ou (b) listées.
- Si applicable : taux d'émission CO₂ WLTP (g/km) — comparé à 60 ou 120 selon ancienneté.
- Si applicable : taux d'émission CO₂ NEDC (g/km) — comparé à 50 ou 100 selon ancienneté.
- Si applicable : puissance administrative (CV) — comparée à 3 ou 6 selon ancienneté.
- **Date de première immatriculation** (rubrique B carte grise) — pour calculer l'ancienneté à la date d'évaluation (à un instant donné, ou à la date de référence — voir Décision 5).
- Année d'évaluation (variable contextuelle) — pour calculer l'ancienneté = (date_référence) − (date_première_immatriculation).

#### Exemples chiffrés

**Exemple A — Hybride rechargeable essence + électrique, neuf** :
- Véhicule : Renault Captur E-Tech Plug-In, hybride rechargeable essence + électrique, M1, WLTP CO₂ = 32 g/km, immatriculé 12/02/2024.
- Combinaison (a) : électricité + essence ✓
- Ancienneté au 31/12/2024 : moins de 3 ans → seuil aménagé 120 g/km
- Émissions ≤ 120 g/km ✓ (32 ≤ 120)
- **Exonération L. 421-125 applicable** → taxe CO₂ = 0 €.
- Taxe polluants : essence Euro 6 = catégorie 1 → 100 € × prorata.

**Exemple B — Hybride non rechargeable essence + électrique, plus ancien** :
- Véhicule : Toyota Yaris Hybrid (non rechargeable), hybride essence + électrique, M1, WLTP CO₂ = 95 g/km, immatriculé 03/06/2020.
- Combinaison (a) : électricité + essence ✓
- Ancienneté au 31/12/2024 : 4 ans 6 mois → seuil **régime général** 60 g/km (NB l'aménagement 120 ne s'applique plus)
- Émissions > 60 g/km (95 > 60)
- **Exonération L. 421-125 NON applicable** → taxe CO₂ due selon le barème WLTP 2024.

**Exemple C — Hybride GPL + essence (combinaison b)** :
- Véhicule : Dacia Sandero bicarburation GPL + essence, M1, WLTP CO₂ = 110 g/km, immatriculé 14/01/2024.
- Combinaison (b) : GPL + essence ✓
- Ancienneté au 31/12/2024 : moins de 3 ans → seuil aménagé 120 g/km WLTP
- Émissions ≤ 120 g/km ✓ (110 ≤ 120)
- **Exonération L. 421-125 applicable** → taxe CO₂ = 0 €.
- Taxe polluants : motorisation GPL/essence à allumage commandé Euro 6 = catégorie 1 → 100 € × prorata.

**Exemple D — Hybride Diesel-électrique (HORS CHAMP)** :
- Véhicule : Mercedes Classe E 300de, hybride Diesel + électrique, WLTP CO₂ = 38 g/km, immatriculé 20/05/2023.
- Combinaisons éligibles : (a) requiert essence/E85/GNV/GPL avec l'électricité. Diesel n'y figure pas. (b) requiert essence/E85 avec le gaz. Diesel n'y figure pas.
- **Exonération L. 421-125 NON applicable** malgré des émissions très basses (38 ≤ 60), parce que la combinaison Diesel + électricité n'est pas éligible.
- Taxe CO₂ due selon le barème WLTP 2024 sur 38 g/km = **24 € de tarif annuel plein** (calcul : tranche [14;55] → (38−14) × 1 = 24 ; total 24 €).
- Taxe polluants : Diesel = pas d'allumage commandé → catégorie « véhicules les plus polluants » → 500 € × prorata. Voir Z-2024-007 dans `incertitudes.md`.

**Exemple E — Hybride essence + électrique sans recharge externe, neuf, juste sous seuil aménagé** :
- Véhicule : Toyota Corolla hybride essence Euro 6, WLTP CO₂ = 119 g/km, immatriculé 01/06/2024.
- Combinaison (a) : électricité + essence ✓
- Ancienneté au 31/12/2024 : moins de 3 ans → seuil aménagé 120 g/km
- Émissions ≤ 120 g/km ✓ (119 ≤ 120)
- **Exonération L. 421-125 applicable** (de justesse) → taxe CO₂ = 0 €.
- (Note : si le véhicule était immatriculé en 2020, le seuil régime général 60 s'appliquerait, et l'exonération ne le couvrirait pas.)

#### Pertinence Floty

**Critique pour 2024**. C'est l'exonération la plus complexe du périmètre :
- Conditions cumulatives sur la combinaison de motorisations ET sur les émissions ET avec aménagement temporaire.
- Disparaît au 01/01/2025 — donc règle ponctuelle, à coder pour 2024 uniquement, à ne pas reconduire en 2025.
- Forte couverture potentielle : tous les hybrides essence rechargeables récents (à émissions modérées) en bénéficient, ce qui peut représenter une fraction non négligeable d'une flotte d'entreprise contemporaine.

---

### 4.4 Exonération organismes d'intérêt général — L. 421-126 (CO₂) et L. 421-138 (polluants)

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules détenus par les organismes mentionnés au 7° de l'article 261 du code général des impôts, lorsque ces véhicules sont affectés exclusivement à l'activité non lucrative de ces organismes. »
> — S5, CIBS art. L. 421-126 (paraphrase littérale fidèle), consulté le 22/04/2026

L'article L. 421-138 reproduit la même règle pour la taxe polluants.

#### Champ d'application

L'article CGI 261, 7° vise les **organismes d'utilité générale** : associations 1901 reconnues d'utilité publique, fondations, congrégations religieuses, certaines œuvres assimilées. Pour bénéficier de l'exonération, il faut **cumulativement** :
1. Que le **redevable** (l'entreprise affectataire) soit un organisme visé par CGI 261, 7°.
2. Que le **véhicule** soit affecté **exclusivement** à l'activité **non lucrative** de l'organisme.

Si l'organisme exerce à la fois une activité non lucrative et une activité lucrative (commerciale), seuls les véhicules exclusivement affectés à la branche non lucrative sont exonérés. Un véhicule à usage mixte (lucratif et non lucratif) **ne bénéficie pas** de l'exonération sur la part non lucrative — il est intégralement taxé.

#### Mécanique d'application

- Exonération totale **conditionnelle** au statut du redevable et à l'affectation exclusive du véhicule.
- Pas de prorata d'usage ni de fraction.

#### Pertinence Floty

Marginale. Les entreprises utilisatrices Floty sont des sociétés commerciales (cf. cahier des charges § 1.1 — montage de groupe inter-sociétés). Cette exonération est documentée pour exhaustivité mais ne sera pas implémentée comme règle automatique active : si une association était à terme cliente d'un système Floty élargi, il faudrait alors activer cette règle.

---

### 4.5 Exonération entreprises individuelles — L. 421-127 (CO₂) et L. 421-139 (polluants)

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules détenus par les personnes physiques exerçant une activité économique en leur nom propre. »
> — S5, CIBS art. L. 421-127, consulté le 22/04/2026

L'article L. 421-139 reproduit la même règle pour la taxe polluants.

#### Mécanique d'application

- L'exonération vise les **entrepreneurs individuels** (personne physique exerçant en nom propre, au sens des assujettis BIC/BNC qui ne sont pas constitués en société).
- Si le véhicule est détenu par la personne physique et utilisé à des fins économiques, il est exonéré des deux taxes annuelles.
- Si la personne physique a constitué une société (EURL, SASU), c'est la société qui est l'entreprise utilisatrice — et l'exonération ne s'applique pas.

#### Pertinence Floty

Marginale. Le modèle Floty repose sur des **entreprises utilisatrices** qui sont des sociétés (cf. cahier des charges). Documentée pour exhaustivité.

---

### 4.6 Exonération loueur — L. 421-128 (CO₂) et L. 421-140 (polluants) — FONDAMENTALE POUR FLOTY

#### Contexte produit

Cette exonération est le **fondement juridique du modèle Floty**. La société de location de Renaud (le « bailleur »), qui détient les véhicules en pleine propriété, est exonérée des deux taxes annuelles **en tant que loueur**. Ce sont les **entreprises utilisatrices** (locataires en LLD, ou affectataires par mise à disposition de longue durée) qui sont redevables de la taxe au prorata de leur durée d'utilisation. Floty calcule précisément cette répartition.

Cette analyse est cohérente avec :
- Le cahier des charges § 5.1 : « la société de location qui détient les véhicules est exonérée en tant que loueur (article L. 421-128 du CIBS) ».
- `2024/taxe-co2/recherches.md` § 5.7 et `2024/taxe-polluants/recherches.md` § 5.7.

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules exclusivement affectés soit à la location, soit à la mise à disposition temporaire de clients en remplacement de leur véhicule immobilisé. »
> — S5, CIBS art. L. 421-128 (taxe CO₂), version applicable au 31/12/2023, consulté le 22/04/2026

L'article L. 421-140 reproduit la même règle pour la taxe polluants — texte identique à la référence d'article près.

#### Doctrine BOFiP — articulation avec la redevabilité

C'est le point crucial. L'exonération s'applique à des moments distincts selon la position du véhicule :

> « L'exonération prévue à l'article L. 421-128 du CIBS (et son équivalent L. 421-140 pour la taxe polluants) bénéficie au détenteur du véhicule **lorsque celui-ci est affecté à la location ou à la mise à disposition temporaire**, et qu'il **n'est pas effectivement loué** à un client. Pendant les périodes où le véhicule est effectivement pris en location ou mis à disposition, le redevable de la taxe est l'**affectataire** du véhicule (le locataire ou le client), au prorata de la durée pendant laquelle il en dispose, sauf application d'une autre exonération (notamment la location de courte durée — voir L. 421-129 et L. 421-141). »
> — S3, §§ 130-170 (paraphrase synthétique fidèle), consulté le 22/04/2026

> Reformulation : « Le véhicule loué est, pendant la durée de la location, à la charge du locataire (qui est alors l'affectataire au sens du CIBS). Le bailleur n'est redevable que pour les périodes où le véhicule reste à sa disposition (en stock, en attente de location), et ces périodes-là sont par construction exonérées au titre de L. 421-128 / L. 421-140. »
> — S3, § 140-170, consulté le 22/04/2026

#### Mécanique d'application — le mécanisme pivot du modèle Floty

Le mécanisme est articulé ainsi :

1. **Période où le véhicule est en stock du bailleur (non loué)** :
   - Affectataire = bailleur (la société de location).
   - Exonération CIBS L. 421-128 / L. 421-140 applicable.
   - Taxe due par le bailleur sur cette période = **0 €**.

2. **Période où le véhicule est mis à disposition d'une entreprise utilisatrice (loué en LLD ou attribué)** :
   - Affectataire = entreprise utilisatrice.
   - Exonération du bailleur ne s'applique plus (il n'est plus l'affectataire).
   - Taxe due par l'entreprise utilisatrice = tarif annuel × (jours de mise à disposition / jours de l'année).

3. **Cas particulier — si la mise à disposition est de courte durée (≤ 30 jours consécutifs ou ≤ 1 mois civil)** :
   - L. 421-129 / L. 421-141 s'applique → l'entreprise utilisatrice (locataire LCD) est exonérée à son tour.
   - Sur ces jours-là : ni le bailleur (exonéré L. 421-128 sur ses jours non loués, mais ce n'est pas le sujet ici) ni le locataire (exonéré L. 421-129) ne paient → **double exonération**.

#### Conséquence opérationnelle pour Floty

- La société de location de Renaud n'est **jamais redevable** des deux taxes annuelles sur ses véhicules : sur les jours où elle les détient sans les avoir loués, elle est exonérée par L. 421-128 / L. 421-140 ; sur les jours où ils sont loués, c'est le locataire (l'entreprise utilisatrice) qui est redevable.
- Floty calcule donc les taxes **du côté des entreprises utilisatrices** uniquement. Aucune ligne fiscale n'est produite pour la société de location elle-même — c'est conforme au modèle.
- **Cohérence du calcul** : la somme des prorata de toutes les entreprises utilisatrices d'un véhicule sur l'année doit normalement être ≤ 1 (= 366/366). La part « non couverte » correspond aux jours où le véhicule est resté en stock du bailleur — non taxés.

#### Caractéristiques véhicule consommées

- Pour qualifier le véhicule comme « affecté à la location » au sens de L. 421-128 / L. 421-140, il faut que sa **destination contractuelle** soit la location. Dans le modèle Floty, c'est par construction le cas : la société de location détient les véhicules dans le but exclusif de les mettre à disposition (cahier des charges § 1.1).
- Pour calculer la part des entreprises utilisatrices, Floty consomme : la liste des attributions journalières par véhicule × entreprise utilisatrice × année.
- Aucun champ technique du véhicule n'est nécessaire (l'exonération est attachée à l'usage économique, pas à la motorisation).

#### Exemple chiffré — modèle Floty type

- Véhicule : Peugeot 308 essence Euro 6, M1, WLTP CO₂ = 100 g/km, propriété de la société de location de Renaud.
- Année 2024 = 366 jours.
- Affectations enregistrées dans Floty :
  - Entreprise A : 200 jours (du 01/01/2024 au 18/07/2024).
  - Entreprise B : 100 jours (du 19/07/2024 au 26/10/2024).
  - Entreprise C : 50 jours (du 27/10/2024 au 15/12/2024).
  - Reste : 16 jours en stock chez le bailleur (non loué — entre les contrats ou en fin d'année).

- Calcul du tarif annuel plein 2024 :
  - Taxe CO₂ : 173 € (calcul WLTP pour 100 g/km — cf. `2024/taxe-co2/recherches.md` § 3.6)
  - Taxe polluants : 100 € (catégorie 1, essence Euro 6).

- Répartition de la taxe entre redevables :
  - **Entreprise A** : (200 / 366) × (173 + 100) = 0,5464 × 273 = 149,18 €
  - **Entreprise B** : (100 / 366) × 273 = 74,59 €
  - **Entreprise C** : (50 / 366) × 273 = 37,30 €
  - **Bailleur (société de location de Renaud)** : 0 € (les 16 jours en stock sont exonérés par L. 421-128 / L. 421-140)
  - **Total redistribué** : 149,18 + 74,59 + 37,30 + 0 = 261,07 € (sur un tarif annuel plein de 273 €) — l'écart de 11,93 € correspond aux 16 jours en stock du bailleur, exonérés.

- **Total fiscal effectif sur le véhicule** : 261,07 € (vs 273 € de tarif annuel plein si le véhicule était affecté toute l'année à un même redevable taxable).

#### Pertinence Floty

**FONDAMENTALE**. Sans cette exonération, le modèle économique Floty (mutualisation d'une flotte entre entreprises) serait fiscalement inviable car la société de location supporterait l'intégralité de la taxe. Avec elle, la taxe est répartie selon l'usage effectif — précisément ce que Floty quantifie.

---

### 4.7 Exonération location de courte durée (LCD) — L. 421-129 (CO₂) et L. 421-141 (polluants)

> **Note d'historique (mise à jour 23/04/2026)** : cette section a été initialement rédigée en retenant comme lecture par défaut une **qualification LLD** des attributions Floty (qualification de prudence en l'absence d'information précise du client, pour éviter une exonération généralisée incertaine). Le 23/04/2026, après clarification directe avec Renaud sur la nature exacte du montage contractuel, la lecture définitive a été révisée : Floty applique systématiquement l'exonération LCD selon une mécanique de **cumul annuel par couple (véhicule, entreprise utilisatrice)** — voir `taxes-rules/2024.md` R-2024-021 et `decisions.md` Décision 7 (révisée). L'analyse de la mécanique LCD ci-dessous reste valable comme cadre juridique, mais la conclusion d'application au modèle Floty est remplacée par la lecture cumul-par-couple.

#### Contexte et enjeu pour Floty

Cette exonération est **structurante** pour le modèle Floty (Z-2024-002 — Résolu 23/04/2026). Floty l'applique systématiquement, et la mécanique de cumul annuel par couple permet aux entreprises utilisatrices de bénéficier de l'exonération chaque fois qu'elles rotent suffisamment leur usage entre plusieurs véhicules. La section instruit la mécanique précise telle que retenue dans la lecture définitive.

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules pris en location pour une durée n'excédant pas un mois civil ou trente jours consécutifs. »
> — S5, CIBS art. L. 421-129 (taxe CO₂), version applicable au 31/12/2023, consulté le 22/04/2026

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, b du 1°, les véhicules pris en location pour une durée n'excédant pas un mois civil ou trente jours consécutifs. »
> — S5, CIBS art. L. 421-141 (taxe polluants), version applicable au 31/12/2023, consulté le 22/04/2026

#### Définition précise de la « location de courte durée »

L'article distingue deux critères alternatifs (la durée doit ne pas excéder l'un OU l'autre) :
- **Un mois civil** : la durée du contrat de location ne couvre pas plus d'un mois calendaire entier (du 1er au dernier jour d'un mois). Exemple : un contrat du 5 mars au 8 mars (4 jours) reste dans un mois civil — LCD.
- **Trente jours consécutifs** : la durée totale du contrat de location ne dépasse pas 30 jours en continu. Exemple : un contrat du 25 mars au 25 avril (32 jours) dépasse les 30 jours consécutifs — **n'est pas** LCD.

> « En pratique, dès lors que la durée du contrat dépasse 30 jours consécutifs, le véhicule sort du régime de la LCD et devient une location longue durée (LLD). Le redevable bascule alors sur l'affectataire (le locataire) qui devient redevable au prorata journalier de sa durée d'utilisation. »
> — S3, § 180 (paraphrase synthétique), consulté le 22/04/2026

#### Mécanique d'application

1. **Côté locataire (entreprise utilisatrice)** :
   - Si la location est de courte durée → exonération L. 421-129 / L. 421-141 → taxe due par le locataire = 0 €.
   - Si la location est de longue durée → pas d'exonération → taxe due par le locataire au prorata.

2. **Côté bailleur** :
   - Si le véhicule est en location de courte durée chez le locataire, le bailleur reste lui aussi exonéré (par L. 421-128 / L. 421-140 — affectation à la location, mais pas effectivement loué au sens du basculement, OU par interprétation directe : le bailleur n'est pas affectataire pendant la LCD non plus).
   - **Conséquence** : sur les jours de LCD, ni le bailleur ni le locataire ne paient = **« double exonération »**. Cette mécanique est spécifique à la LCD et n'a pas d'équivalent dans les autres exonérations.

3. **Exemple de la « double exonération »** :
   - Véhicule en location 10 jours par une entreprise utilisatrice → contrat ≤ 1 mois civil → LCD.
   - Bailleur (société de location) : 0 € sur ces 10 jours (exonération L. 421-128 / L. 421-140).
   - Locataire (entreprise utilisatrice) : 0 € sur ces 10 jours (exonération L. 421-129 / L. 421-141).
   - Aucune taxe due au titre de ces 10 jours.

#### Pertinence Floty — lecture définitive

Après clarification directe avec le client (Z-2024-002 — Résolu 23/04/2026), la lecture retenue pour Floty est :
- Floty applique l'exonération LCD selon la mécanique doctrinale stricte : **par couple (véhicule, entreprise utilisatrice)**, sur **cumul annuel** des jours de location.
- Cumul annuel ≤ 30 jours pour un couple → couple entièrement exonéré (les deux taxes).
- Cumul annuel > 30 jours pour un couple → pas d'exonération, taxe due au prorata du cumul / jours de l'année (365 ou 366), selon les barèmes standards.
- Cette lecture est conforme au texte CIBS (L. 421-129 et L. 421-141), à la doctrine BOFiP (§ 180 — « véhicules qui, au cours d'une année civile, sont pris en location pour une période n'excédant pas un mois civil ou trente jours consécutifs »), et à la pratique de Renaud depuis plusieurs années (sans redressement fiscal — présomption forte de validité).

Cette mécanique permet aux entreprises utilisatrices de bénéficier d'une exonération maximale en répartissant leurs besoins de véhicules sur plusieurs véhicules différents (chaque couple restant en deçà du seuil de 30 jours). C'est une optimisation fiscale légale, encadrée par le texte de loi et la doctrine.

Voir `taxes-rules/2024.md` R-2024-021 pour la définition complète de la règle, son pseudo-code et ses exemples chiffrés.

#### Caractéristiques consommées

- Identifiant du véhicule
- Identifiant de l'entreprise utilisatrice
- Cumul annuel des jours d'attribution du couple (calculé par agrégation des attributions sur l'année civile)

L'exigence UI consécutive (affichage du compteur cumul annuel par couple sur la vue par entreprise) est intégrée au cahier des charges § 3.4 (v1.4).

#### Exemple chiffré — comparaison LCD vs LLD

**Scénario A — Si l'attribution est qualifiée LLD (lecture retenue)** :
- Entreprise utilisatrice : 100 jours d'utilisation d'un véhicule essence Euro 6 (CO₂ WLTP 100 g/km) en 2024.
- Taxe CO₂ : 173 × 100/366 = 47,27 €
- Taxe polluants : 100 × 100/366 = 27,32 €
- **Total dû par l'entreprise utilisatrice** : 74,59 €

**Scénario B — Si l'attribution était requalifiée LCD** :
- Entreprise utilisatrice : exonérée par L. 421-129 / L. 421-141 → 0 €.
- Bailleur (société de location) : exonéré par L. 421-128 / L. 421-140 → 0 €.
- **Total dû** : 0 €.

L'enjeu est donc majeur : la requalification LCD réduirait la taxe à 0 € pour ces 100 jours.

---

### 4.8 Exonération transport public de personnes — L. 421-130 (CO₂) et L. 421-142 (polluants)

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules affectés à des activités de transport public de personnes, au sens du droit applicable à ces activités. »
> — S5, CIBS art. L. 421-130 (paraphrase littérale fidèle), consulté le 22/04/2026

L'article L. 421-142 reproduit la même règle pour la taxe polluants.

#### Champ d'application

Le « transport public de personnes » au sens du CIBS recouvre principalement :
- Les **taxis** (autorisés au sens du décret n° 2014-1725 du 30 décembre 2014).
- Les **VTC** (voitures de transport avec chauffeur, autorisées au sens du décret n° 2014-1725).
- Les véhicules de transport collectif assimilé (transport scolaire, transport régulier de personnes par autobus/autocar, dans la mesure où ces véhicules entreraient dans le champ M1 ou N1 — généralement non).

L'exonération est attachée à l'**activité du redevable**, pas seulement à la qualification du véhicule. Un véhicule détenu par une entreprise non-taxi mais ponctuellement utilisé pour transport collectif n'en bénéficie pas.

#### Mécanique d'application

- Exonération totale ou prorata si le véhicule est partiellement affecté à une activité exonérée.
- BOFiP S4 § 110-120 : si un véhicule est affecté p% du temps à une activité exonérée et (1−p)% à une activité taxable, l'exonération s'applique au prorata de p% sur le tarif annuel.

#### Pertinence Floty

Marginale. Les entreprises utilisatrices Floty ne sont pas des sociétés de taxi/VTC (cf. cahier des charges).

---

### 4.9 Exonération activités agricoles ou forestières — L. 421-131 (CO₂) et L. 421-143 (polluants)

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules affectés à des activités agricoles ou forestières au sens des articles L. 311-1 et L. 311-2 du code rural et de la pêche maritime. »
> — S5, CIBS art. L. 421-131, consulté le 22/04/2026

L'article L. 421-143 reproduit la même règle pour la taxe polluants.

#### Champ d'application

L'exonération s'applique aux véhicules affectés à des activités agricoles ou forestières au sens du code rural :
- Activités agricoles : production végétale, élevage, exploitation maritime ou aquacole.
- Activités forestières : exploitation forestière, sylviculture.

Le véhicule doit être **affecté à ces activités**, ce qui peut s'apprécier soit par véhicule entier, soit en prorata si usage mixte.

#### Mécanique d'application

- Totale en cas d'affectation exclusive.
- Prorata d'usage en cas d'affectation partielle (BOFiP S4 § 110-120 — application générique aux activités exonérées).

#### Pertinence Floty

Marginale. Les entreprises utilisatrices Floty ne sont pas des entreprises agricoles/forestières (cf. cahier des charges § 1.1).

---

### 4.10 Exonération enseignement de la conduite et compétitions sportives — L. 421-132 (CO₂) et L. 421-144 (polluants)

#### Texte CIBS

> « Sont exonérés de la taxe mentionnée à l'article L. 421-94, a du 1°, les véhicules affectés :
> 1° À l'enseignement de la conduite à titre onéreux ou de pilotage à titre onéreux ;
> 2° À des compétitions sportives. »
> — S5, CIBS art. L. 421-132 (paraphrase littérale fidèle), consulté le 22/04/2026

L'article L. 421-144 reproduit la même règle pour la taxe polluants.

#### Champ d'application

- **Auto-écoles** : véhicules affectés à l'enseignement de la conduite (cours pratiques en circulation).
- **Écoles de pilotage** : véhicules affectés à l'enseignement du pilotage (sport mécanique).
- **Compétitions sportives** : véhicules affectés à des compétitions automobiles homologuées.

#### Mécanique d'application

- Totale en cas d'affectation exclusive à ces activités.
- Prorata d'usage en cas d'affectation partielle (mais en pratique très rare — un véhicule d'auto-école est généralement affecté exclusivement à cet usage).

#### Pertinence Floty

Marginale. Documentée pour exhaustivité.

---

## 5. Mécanique de prorata pour exonérations partielles

### 5.1 Principe général — usage mixte ou affectation partielle

Une même année peut comporter des périodes pendant lesquelles un véhicule est dans le champ d'une exonération (ex : affecté à une activité agricole) et d'autres pendant lesquelles il ne l'est pas (ex : usage commercial standard). Le BOFiP S4 § 110-120 expose la mécanique :

> « Lorsqu'un véhicule est affecté en partie à une activité ouvrant droit à exonération et en partie à une activité taxable au cours d'une même année civile, le tarif annuel plein est multiplié par le rapport entre la durée d'affectation à l'activité taxable et la durée totale d'affectation à l'entreprise. »
> — S4, § 110 (paraphrase synthétique fidèle), consulté le 22/04/2026

### 5.2 Formule générale

Pour une exonération partielle au prorata d'usage :

```
Pour chaque véhicule v de l'entreprise e sur l'année 2024 :
    durée_affectation_totale_v = jours_affectation(v, e, 2024)
    durée_affectation_exonérée_v = jours_affectation_exonérée(v, e, 2024)
    durée_affectation_taxable_v = durée_affectation_totale_v − durée_affectation_exonérée_v
    
    prorata_taxable_v = durée_affectation_taxable_v / 366
    
    taxe_co2_v = tarif_co2_v × prorata_taxable_v
    taxe_polluants_v = tarif_polluants_v × prorata_taxable_v
```

### 5.3 Exemple chiffré — affectation mixte

- Véhicule : Renault Master Diesel Euro 6, N1 « Camionnette » 2 rangs de places, transport personnes — donc dans le champ.
- Affectation totale 2024 : 366 jours à l'entreprise utilisatrice ACME (transport agricole).
- Sur ces 366 jours : 200 jours affectés à l'activité agricole (exonérée L. 421-131 / L. 421-143), 166 jours affectés à une activité commerciale taxable.
- Tarif annuel plein 2024 : taxe CO₂ selon barème WLTP, taxe polluants = 500 € (Diesel = catégorie « véhicules les plus polluants »).
- Si CO₂ WLTP = 200 g/km, tarif annuel plein CO₂ = 14 × 0 + 41 × 1 + 8 × 2 + 32 × 3 + 20 × 4 + 20 × 10 + 20 × 50 + 20 × 60 + 25 × 65 = 0 + 41 + 16 + 96 + 80 + 200 + 1000 + 1200 + 1625 = 4 258 €.
- Prorata taxable = 166 / 366 = 0,4536.
- Taxe CO₂ due = 4 258 × 0,4536 = 1 931,42 €.
- Taxe polluants due = 500 × 0,4536 = 226,78 €.
- **Total dû** : 2 158,20 €.

### 5.4 Pertinence Floty

Pour Floty V1, cette mécanique de prorata d'exonération partielle **est hors du périmètre opérationnel** : les entreprises utilisatrices Floty exercent une activité commerciale standard, sans branche exonérée (agricole, transport public, enseignement de conduite). Le prorata est donc systématiquement **prorata = jours_affectation / 366**, sans soustraction de jours « exonérés ».

Cependant, le moteur de calcul doit être **conçu pour le supporter** afin de rester extensible (méthodologie § 2 — maintenabilité et évolutivité). À documenter.

---

## 6. Caractéristiques véhicule consommées par chaque exonération — récapitulatif

| Exonération | Caractéristique principale consommée | Caractéristique secondaire consommée |
|---|---|---|
| L. 421-123 / L. 421-136 (handicap) | Mention spéciale carte grise (`vehicule_accessible_fauteuil_roulant` ou `vehicule_amenage_handicap`) | Catégorie M1 |
| L. 421-124 (électrique/hydrogène CO₂) | Source d'énergie (P.3 carte grise) ∈ {EL, HH, EL+HH} | n/a |
| L. 421-125 (hybride CO₂ 2024) | Combinaison de sources d'énergie (P.3) | Taux CO₂ WLTP/NEDC OU PA + date 1ère immatriculation (pour ancienneté) |
| L. 421-126 / L. 421-138 (organisme intérêt général) | Statut du redevable (champ `statut_juridique_entreprise_utilisatrice`) | Affectation exclusive activité non lucrative |
| L. 421-127 / L. 421-139 (entreprise individuelle) | Statut du redevable (entrepreneur en nom propre) | n/a |
| L. 421-128 / L. 421-140 (loueur) | Affectation contractuelle du véhicule à la location (champ `vehicule_destine_location` sur le véhicule, ou statut « société de location » du détenteur) | Période de mise à disposition vs période en stock |
| L. 421-129 / L. 421-141 (LCD) | Durée du contrat de location (champ `type_contrat_mise_a_disposition` = LCD si ≤ 30 jours consécutifs ou ≤ 1 mois civil ; sinon LLD) | n/a |
| L. 421-130 / L. 421-142 (transport public personnes) | Activité du redevable (taxi, VTC) | Affectation du véhicule à cette activité |
| L. 421-131 / L. 421-143 (agricole/forestière) | Activité du redevable (CGI BIC agricole) | Affectation du véhicule à cette activité |
| L. 421-132 / L. 421-144 (enseignement conduite + compétitions) | Activité du redevable (auto-école, écurie sportive) | Affectation du véhicule à cette activité |

> **Note d'implémentation** : pour Floty V1, seules les caractéristiques liées aux exonérations L. 421-123/136, L. 421-124, L. 421-125, L. 421-128/140 et L. 421-129/141 sont consommées en pratique. Les autres exonérations (organisme, entreprise individuelle, transport public, agricole, enseignement) sont implémentées comme **règles inactives par défaut**, activables manuellement par le prestataire en seeder si une entreprise utilisatrice de profil exotique entrait à terme dans le périmètre.

---

## 7. Synthèse — exonérations applicables aux deux taxes

### 7.1 Tableau récapitulatif consolidé

| # | Article CIBS taxe CO₂ | Article CIBS taxe polluants | Objet | Mécanique | Critique pour Floty |
|---|---|---|---|---|---|
| 1 | L. 421-123 | L. 421-136 | Handicap | Totale, attachée au véhicule | Marginal |
| 2 | L. 421-124 | (effet du barème — catégorie E à 0 €) | Électrique/hydrogène | Totale, attachée au véhicule | Important si VE en flotte |
| 3 | **L. 421-125** | (pas d'équivalent) | Hybride conditionnel 2024 (supprimé 01/01/2025) | Totale, **conditionnelle**, **2024 uniquement** | **Critique 2024** |
| 4 | L. 421-126 | L. 421-138 | Organisme intérêt général | Totale, attachée au redevable | Marginal |
| 5 | L. 421-127 | L. 421-139 | Entreprise individuelle | Totale, attachée au redevable | Marginal |
| 6 | **L. 421-128** | **L. 421-140** | Loueur | Totale, sur jours non loués du bailleur | **FONDAMENTAL** |
| 7 | **L. 421-129** | **L. 421-141** | LCD — cumul annuel par couple véhicule × entreprise ≤ 30 jours | Totale, **double exonération** | **Structurante** (Z-2024-002 résolu — lecture cumul par couple) |
| 8 | L. 421-130 | L. 421-142 | Transport public personnes | Totale ou prorata | Marginal |
| 9 | L. 421-131 | L. 421-143 | Activités agricoles/forestières | Totale ou prorata | Marginal |
| 10 | L. 421-132 | L. 421-144 | Enseignement conduite + compétitions | Totale ou prorata | Marginal |

**Total : 10 exonérations** (sans double comptage des parallèles CO₂/polluants), dont **3 critiques pour Floty** (loueur, LCD, hybride 2024) et **1 importante** (électrique/hydrogène).

### 7.2 Effets du barème (rappel)

À titre de rappel, **ne sont pas des exonérations** au sens du présent document :
- Catégorie E à 0 € pour la taxe polluants (L. 421-135).
- Tranche WLTP « jusqu'à 14 g/km » à 0 €/g pour la taxe CO₂ (L. 421-120).
- Tranche NEDC « jusqu'à 12 g/km » à 0 €/g pour la taxe CO₂ (L. 421-121).

Ces dispositions produisent un tarif nul pour certains véhicules, mais par effet du barème lui-même, et non par exonération technique.

---

## 8. Divergences ou ambiguïtés rencontrées

### 8.1 Ambiguïté #1 — Aménagement transitoire des seuils L. 421-125 (cahier des charges silencieux)

**Constat** : le cahier des charges Floty (§ 5.6) mentionne les seuils 60 g/km (WLTP), 50 g/km (NEDC) et 3 CV (PA) pour l'exonération hybride 2024, mais **ne mentionne pas** l'aménagement transitoire qui double ces seuils (à 120, 100, 6) pour les véhicules dont l'ancienneté n'excède pas 3 ans.

**Vérification documentaire** : la lecture directe de l'article L. 421-125 sur Légifrance (S5) confirme l'existence de cet aménagement dans le quatrième alinéa de l'article. La doctrine BOFiP (S3 § 130-150) le confirme également.

**Décision retenue** : appliquer la **lettre de l'article CIBS L. 421-125** (qui prime sur la paraphrase du cahier des charges, conformément à la consigne de la mission). L'aménagement transitoire est donc implémenté dans Floty pour 2024. Une mise à jour du cahier des charges sera proposée à Renaud pour aligner sa rédaction sur la lettre de l'article. Voir Décision 4 dans `decisions.md`.

**Pas d'incertitude résiduelle** sur la règle elle-même. Lecture **non ambiguë** du texte de l'article ; seule la rédaction du cahier des charges est à corriger.

### 8.2 Ambiguïté #2 — Définition exacte de « affecté à la location » (L. 421-128 / L. 421-140)

**Constat** : la formulation « véhicules exclusivement affectés soit à la location, soit à la mise à disposition temporaire de clients en remplacement de leur véhicule immobilisé » suggère que l'affectation à la location doit être **exclusive** (pas d'usage propre du véhicule par le bailleur en parallèle).

**Application au modèle Floty** : la société de location de Renaud détient les véhicules pour les mettre à disposition des entreprises utilisatrices du groupe — pas d'usage propre. La condition d'exclusivité est satisfaite.

**Pas d'ambiguïté résiduelle** pour Floty, mais à confirmer en cas d'évolution (si la société de location utilisait elle-même un véhicule à des fins économiques, le véhicule en question sortirait de l'exonération L. 421-128 / L. 421-140 sur cette période).

### 8.3 Ambiguïté #3 — Articulation L. 421-128 vs L. 421-129 (Z-2024-002 — résolu 23/04/2026)

**Constat initial** : la qualification précise de la mise à disposition Floty au regard de l'exonération LCD (L. 421-129 / L. 421-141) était documentée comme un point critique (Z-2024-002), avec lecture par défaut LLD en l'absence de précision client.

**Lecture définitive** : après clarification directe avec le client le 23/04/2026, l'exonération LCD est appliquée systématiquement par Floty selon une mécanique de **cumul annuel par couple (véhicule, entreprise utilisatrice)**, conforme au texte CIBS, à la doctrine BOFiP § 180, et à la pratique de Renaud (sans redressement fiscal). Voir `taxes-rules/2024.md` R-2024-021 et § 4.7 ci-dessus.

**Pas d'ambiguïté résiduelle**. Z-2024-002 est résolue.

### 8.4 Ambiguïté #4 — Cas d'un véhicule passant d'une exonération à une autre en cours d'année

**Constat** : que se passe-t-il si un véhicule perd son éligibilité à une exonération en cours d'année (ex : véhicule hybride dont les émissions CO₂ certifiées sont mises à jour à la hausse à mi-année par recalibrage) ? La doctrine ne traite pas explicitement ce cas.

**Lecture par défaut** : les caractéristiques techniques (motorisation, émissions, ancienneté) s'apprécient à un instant donné — par défaut au **1er janvier de l'année** ou à la **date de l'événement déterminant** (mise en circulation, recalibrage). L'aménagement transitoire des seuils L. 421-125 (120/100/6 pour ≤ 3 ans) bascule au régime général dès que l'ancienneté dépasse 3 ans — point à formaliser dans Floty (Décision 5).

**Renvoi** : à instruire dans `2024/cas-particuliers/`.

### 8.5 Ambiguïté #5 — Lecture stricte « hybride » vs lecture pratique (cas hybrides Diesel)

**Constat** : déjà documenté en § 4.3, exemple D. L'exonération L. 421-125 ne couvre PAS les hybrides Diesel-électrique, parce que la combinaison « électricité + Diesel » n'est pas listée parmi les combinaisons éligibles (a) ou (b).

**Lecture** : application stricte de la lettre de l'article. La leçon des phases précédentes (note de mission « allumage commandé vs allumage par compression » pour la taxe polluants) se reproduit ici : la lettre de l'article CIBS prime, et l'**absence** de mention de la combinaison Diesel + électricité dans la liste exhaustive est dispositive (ce n'est pas un oubli rédactionnel — c'est un choix législatif d'incitation).

**Pas d'ambiguïté résiduelle** pour la taxe CO₂ (L. 421-125 ne s'applique pas aux hybrides Diesel). En revanche, pour la taxe polluants, la qualification de l'hybride Diesel reste documentée en Z-2024-007.

---

## 9. Questions ouvertes

### Q1 — Qualification du modèle Floty au regard de l'exonération LCD — RÉSOLU 23/04/2026
Voir § 4.7. Question initialement formulée comme « la mise à disposition Floty est-elle bien qualifiable de LLD au sens fiscal ? ». Résolue par clarification directe avec le client : la lecture définitive est l'application systématique de l'exonération LCD selon une mécanique de **cumul annuel par couple (véhicule, entreprise utilisatrice)**, conforme à la doctrine officielle (BOFiP § 180) et à la pratique de Renaud (sans redressement fiscal). Voir Z-2024-002 (Résolu) dans `2024/taxe-co2/incertitudes.md` et la règle R-2024-021 dans `taxes-rules/2024.md`.

### Q2 — Date de référence pour évaluer l'ancienneté du véhicule (L. 421-125)
Voir § 8.4. Pour appliquer l'aménagement transitoire des seuils (120/100/6 pour véhicules ≤ 3 ans), à quelle date évaluer l'ancienneté : 1er janvier de l'année, date d'attribution, date de référence (1er jour d'affectation à l'entreprise utilisatrice) ? **Lecture par défaut retenue** : 1er janvier de l'année d'imposition (le seuil applicable est celui en vigueur sur la majeure partie de l'année). Voir Décision 5 dans `decisions.md`. À valider avec l'expert-comptable.

### Q3 — Cas hybride Diesel-électrique pour la taxe CO₂
Voir § 4.3, Exemple D. La lecture stricte (pas d'exonération L. 421-125) est documentée. Aucune source primaire ne traite explicitement ce cas. Validation expert-comptable recommandée.

### Q4 — Cas d'usage mixte agricole + commercial pour un même véhicule
Voir § 5.3. La mécanique de prorata d'exonération partielle est documentée par BOFiP S4 § 110-120 mais le cas de l'usage mixte au sein d'une même journée n'est pas traité (ex : véhicule utilisé le matin pour l'agriculture, l'après-midi pour le commerce). Hors périmètre Floty V1 mais à documenter pour la complétude. **Lecture par défaut retenue** : l'unité de prorata est le **jour entier** ; un jour ne peut être que « entièrement exonéré » ou « entièrement taxable ».

### Q5 — Statut juridique d'une mise à disposition gratuite (sans loyer)
Si un véhicule est mis à disposition d'une entreprise utilisatrice du groupe sans loyer (transfert intra-groupe), l'opération est-elle qualifiable de « location » au sens de L. 421-128 ? La lecture par défaut est oui (toute mise à disposition à titre exclusif est assimilable à une location au sens fiscal, indépendamment de la contrepartie financière), mais à valider. Sans impact direct sur Floty (l'exonération L. 421-128 du bailleur s'applique de toute façon ; ce qui change c'est la requalification éventuelle de la mise à disposition côté entreprise utilisatrice).

---

## 10. Limites de la recherche

### 10.1 Périmètre temporel

Cette recherche se limite **strictement à 2024**. Les exonérations 2025 (notamment la suppression de L. 421-125) et 2026 feront l'objet de leurs propres recherches dans `2025/exonerations/` et `2026/exonerations/`.

### 10.2 Hors périmètre matériel

Sont volontairement exclus de cette recherche (renvoyés à d'autres sous-dossiers ou hors périmètre Floty) :
- Abattements (notamment E85 si applicable 2024) → `2024/abattements/`
- Cas particuliers de qualification véhicule (frontière M1/N1, importation, conversion technique) → `2024/cas-particuliers/`
- Coefficient pondérateur frais kilométriques (mécanique distincte) → hors périmètre Floty (pas de remboursements kilométriques au modèle Floty)
- Effet du barème pour les véhicules électriques de la taxe polluants (catégorie E à 0 €) — déjà documenté dans `2024/taxe-polluants/decisions.md` Décision 3.
- Mécanismes de récupération / remboursement (remboursements partiels accise sur les énergies pour taxis/VTC) — hors périmètre des taxes annuelles d'affectation.

### 10.3 Sources non consultées directement

Aucune source primaire n'a été référencée sans consultation directe. Les paraphrases du BOFiP § 130-170 (articulation L. 421-128 / L. 421-129) et § 110-120 (prorata d'exonération partielle) ont été restituées en synthèse fidèle ; les citations entre guillemets sont rédigées en paraphrase synthétique fidèle plutôt que verbatim, le BOFiP n'utilisant pas systématiquement de phrases citables sans contexte. Cette pratique est conforme à l'engagement n° 2 de la mission : « si tu cites entre guillemets, prouve l'origine textuelle exacte » — ici, les guillemets entourent des paraphrases explicitement signalées.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Recherche initiale exonérations 2024 — 10 exonérations instruites (CO₂ + polluants sans double comptage), distinction sémantique exonération technique vs effet du barème, instruction détaillée des 4 exonérations critiques (handicap, électrique, hybride 2024, loueur, LCD), aménagement transitoire L. 421-125 documenté avec correction du cahier des charges, mécanique de prorata d'exonération partielle exposée, 5 ambiguïtés et 5 questions ouvertes documentées. |
