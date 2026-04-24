# Cartographie des taxes applicables aux véhicules d'entreprise en France (2024-2026)

> **Statut** : Version 0.1 — livrable de la phase 0 (cartographie préalable)
> **Auteur** : Micha MEGRET (prestataire)
> **Date** : 22 avril 2026
> **Périmètre** : France métropolitaine, véhicules de tourisme (catégories M1 et N1 visées par la définition fiscale du véhicule de tourisme), années 2024, 2025 et 2026
> **Critère d'inclusion** : taxes dont l'**entreprise utilisatrice** du véhicule à des fins économiques est le redevable légal (cf. methodologie.md §3.3)

---

## 1. Objet du document

Ce document constitue le livrable de la **phase 0** définie par la méthodologie (`methodologie.md` §9.1). Il recense, de manière raisonnée et auditable, l'ensemble des prélèvements obligatoires (taxes, contributions, redevances, accises) que l'administration française est susceptible d'imposer à raison de l'utilisation d'un véhicule à des fins économiques sur le territoire métropolitain, pour les années 2024, 2025 et 2026.

Pour chaque prélèvement identifié, il précise :
- la nature et la périodicité,
- le fait générateur,
- la base légale (article CIBS, CGI ou loi de finances),
- l'applicabilité aux exercices 2024, 2025 et 2026,
- le **redevable légal** et la **décision d'inclusion ou d'exclusion** dans le périmètre Floty.

Ce document devient ensuite la table des matières opérationnelle : il fixe la liste des sous-dossiers à traiter pour chaque année dans `recherches-fiscales/{année}/` et la liste des règles à produire dans `taxes-rules/{année}.md`.

**Rappel** : ce document ne détaille PAS les barèmes ni les formules de calcul. L'objectif est une carte des prélèvements, pas un manuel de calcul. Les barèmes seront instruits en phases ultérieures, taxe par taxe.

---

## 2. Méthode de recherche

### 2.1 Stratégie

1. Balayage systématique du **Code des Impositions sur les Biens et Services (CIBS), livre IV ("Mobilités"), chapitre I ("Déplacements routiers")**, qui contient la quasi-totalité des taxes spécifiques aux véhicules depuis la refonte opérée par l'ordonnance n° 2021-1843 du 22 décembre 2021.
2. Lecture des commentaires du **BOFiP-Impôts** sous la série AIS (Autres Impôts Spéciaux) — Mobilités, notamment :
   - `BOI-AIS-MOB-10-20-40` : taxes sur l'immatriculation des véhicules de tourisme
   - `BOI-AIS-MOB-10-30-10` : dispositions communes aux taxes d'affectation des véhicules à des fins économiques
   - `BOI-AIS-MOB-10-30-20` : taxes d'affectation des véhicules de tourisme (CO₂ et polluants)
   - `BOI-AIS-MOB-10-30-30` : taxe d'affectation des véhicules lourds de transport de marchandises
3. Lecture des fiches pratiques de `service-public.gouv.fr` (section Entreprendre) et `economie.gouv.fr`.
4. Vérification des **lois de finances 2024, 2025, 2026** pour identifier les taxes nouvelles, supprimées, ou dont les paramètres ont basculé (LF 2024 : durcissement du malus ; LF 2025 : création de la TAI ; LF 2026 : article 58 — revalorisation polluants atmosphériques, abaissement seuils).
5. Croisement avec sources tertiaires (PwC Avocats, FNA, Francis Lefebvre, Legifiscal, Drive to Business, Arval Mobility Observatory, Compta-Online) pour valider l'interprétation du redevable.

### 2.2 Application du filtre de redevabilité

Pour chaque prélèvement identifié, application stricte du critère `methodologie.md §3.3` :

- **À inclure dans Floty** : le redevable légal est l'entreprise **utilisatrice** du véhicule (définition au sens du CIBS art. L. 421-94 et suivants : entreprise qui détient en pleine propriété, en location longue durée, en location courte durée, ou qui prend en charge les frais d'utilisation).
- **Hors périmètre** : redevable = propriétaire-bailleur de la flotte (société de location), constructeur/importateur, ou conducteur en nom propre.

### 2.3 Limites assumées

- La recherche n'inclut pas (hors périmètre V1) les taxes applicables aux véhicules lourds de transport de marchandises (≥ 12 tonnes, catégories N2/N3/O), aux véhicules de transport en commun (M2/M3), aux deux-roues motorisés, ni aux véhicules agricoles/forestiers. Ces catégories sortent du périmètre véhicule Floty (M1 et N1 uniquement — voir `cahier_des_charges.md`).
- Les dispositifs européens non transposés en droit français ne sont pas traités.
- Les DOM-TOM sont hors périmètre.

---

## 3. Prélèvements identifiés

Chaque prélèvement est documenté selon la grille standard. L'ordre retenu est logique : d'abord les **prélèvements à l'occasion de l'acquisition/immatriculation** (ponctuels), puis les **prélèvements annuels sur l'affectation** (récurrents), puis les **prélèvements connexes** (carburant, amendes, régimes fiscaux particuliers).

---

### Prélèvement 1 — Taxe sur les émissions de dioxyde de carbone des véhicules de tourisme (« malus CO₂ »)

- **Nature** : Taxe ponctuelle à l'immatriculation
- **Redevable légal** : La personne au nom de laquelle le certificat d'immatriculation est établi (acquéreur). Pour un véhicule acquis par une entreprise utilisatrice, c'est **elle** qui acquitte la taxe au moment de l'établissement de la carte grise. Pour un véhicule loué en LLD, le bailleur (propriétaire) immatricule à son nom et acquitte la taxe — dans ce cas la taxe n'est **pas** directement à la charge de l'entreprise utilisatrice (refacturation économique possible, mais redevabilité juridique = bailleur).
- **Périodicité** : Ponctuelle, au fait générateur
- **Fait générateur** : Première délivrance d'un certificat d'immatriculation en France (véhicule neuf, ou véhicule d'occasion importé non précédemment immatriculé en France), pour les véhicules de tourisme
- **Base légale** :
  - CIBS art. L. 421-58 à L. 421-70-1 (tarifs) ; L. 421-30 et suivants (règles générales d'immatriculation)
  - Assise sur le fait générateur défini à L. 421-30, 4°-a
  - BOFiP `BOI-AIS-MOB-10-20-40`
- **Applicabilité et particularités par année** :
  - **2024** : Seuil de déclenchement à 118 g CO₂/km ; plafond 60 000 €. Plafonnement à 50 % du prix d'acquisition en vigueur.
  - **2025** : Seuil abaissé à 113 g CO₂/km à compter du 01/03/2025 ; plafond rehaussé (70 000 €). Suppression du plafonnement à 50 % du prix d'acquisition (abrogation de l'art. L. 421-61 CIBS) annoncée.
  - **2026** : Seuil abaissé à 108 g CO₂/km à compter du 01/01/2026 ; plafond 80 000 € pour les véhicules émettant > 192 g/km. Malus rétroactif sur occasion initialement prévu au 01/01/2026, **reporté à 2027** par LF 2026.
- **Évaluation de la redevabilité** : **Zone mixte**.
  - Cas 1 — Véhicule **acquis** en pleine propriété par l'entreprise utilisatrice → redevable = entreprise utilisatrice → **à inclure dans Floty** (en toute rigueur, même si c'est une taxe ponctuelle non récurrente).
  - Cas 2 — Véhicule en **LLD** immatriculé au nom du bailleur (cas dominant dans le modèle Renaud, flotte partagée mise à disposition par une société de location) → redevable = bailleur → **HORS PÉRIMÈTRE — redevable = propriétaire-bailleur**.
  - **Décision provisoire** : dans le contexte Floty (flotte détenue par la société de location de Renaud, mise à disposition d'entreprises utilisatrices), le malus CO₂ est **HORS PÉRIMÈTRE V1**. À re-évaluer si une entreprise utilisatrice venait à acquérir en pleine propriété un véhicule via Floty — cas à priori exclu du cahier des charges.
- **Sources** :
  - Légifrance — CIBS Section 2 Taxes sur l'immatriculation, art. L. 421-58 à L. 421-70-1 : https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/ (consulté 22/04/2026)
  - BOFiP `BOI-AIS-MOB-10-20-40-20250528` : https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250528 (consulté 22/04/2026)
  - Largus — Malus écologique 2026 : https://www.largus.fr/actualite-automobile/malus-ecologique-2026-tout-savoir-sur-le-nouveau-bareme-applicable-des-le-1er-janvier-30044217.html (consulté 22/04/2026)

---

### Prélèvement 2 — Taxe sur la masse en ordre de marche des véhicules de tourisme (« malus au poids »)

- **Nature** : Taxe ponctuelle à l'immatriculation
- **Redevable légal** : Idem malus CO₂ : personne au nom de laquelle le certificat d'immatriculation est établi.
- **Périodicité** : Ponctuelle, au fait générateur
- **Fait générateur** : Première délivrance d'un certificat d'immatriculation en France, pour les véhicules de tourisme dont la masse en ordre de marche dépasse le seuil
- **Base légale** :
  - CIBS art. L. 421-71 à L. 421-81-1
  - CIBS art. L. 421-30, 4°-b (fait générateur)
  - BOFiP `BOI-AIS-MOB-10-20-40`
- **Applicabilité et particularités par année** :
  - **2024** : Seuil 1 600 kg ; barème 10 €/kg. Abattement familial (70 kg par enfant à charge à partir du 3ᵉ enfant).
  - **2025** : Seuil 1 600 kg ; barème inchangé pour le tarif marginal de base. Extension progressive du champ aux hybrides rechargeables (précédemment exonérés partiellement).
  - **2026** : Seuil **abaissé à 1 500 kg** à compter du 01/01/2026 ; barème progressif à tranches : 10 €/kg de 1 500 à 1 699 kg, 15 € de 1 700 à 1 799 kg, 20 € de 1 800 à 1 899 kg, 25 € de 1 900 à 1 999 kg, 30 € au-delà de 2 000 kg. Exonération totale confirmée pour véhicules à motorisation exclusivement électrique ou hydrogène. Abattement 8 places assises porté à 600 kg.
- **Évaluation de la redevabilité** : **Idem malus CO₂** — redevable = titulaire du certificat d'immatriculation. Dans le contexte Floty (bailleur immatricule) → **HORS PÉRIMÈTRE V1 — redevable = propriétaire-bailleur**.
- **Sources** :
  - Service-public — Taxe masse en ordre de marche : https://www.service-public.gouv.fr/particuliers/vosdroits/F35950 (consulté 22/04/2026)
  - Largus — Malus au poids 2026-2028 : https://www.largus.fr/actualite-automobile/malus-au-poids-2026-2028-baremes-dates-et-exonerations-30044052.html (consulté 22/04/2026)

---

### Prélèvement 3 — Taxe fixe sur les certificats d'immatriculation (« taxe Y4 »)

- **Nature** : Taxe ponctuelle à l'immatriculation (taxe de gestion)
- **Redevable légal** : Titulaire du certificat d'immatriculation
- **Périodicité** : Ponctuelle, à chaque délivrance d'un certificat d'immatriculation (première immatriculation, changement de titulaire, duplicata, modification d'état civil)
- **Fait générateur** : Délivrance d'un certificat d'immatriculation
- **Base légale** : CIBS art. L. 421-49 (taxe fixe) ; anciennement CGI art. 1628-0 bis
- **Applicabilité et particularités par année** : 11 € fixe en 2024, 2025 et 2026. Applicable à tous les véhicules, y compris exonérés de la taxe régionale.
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — redevable = titulaire du certificat d'immatriculation (bailleur dans le contexte Floty)**. De plus, elle n'est pas liée à un usage récurrent : elle est ponctuelle et intégrée au prix de la carte grise, lui-même supporté par le propriétaire.
- **Sources** :
  - ANTS — Taxes sur les cartes grises : https://immatriculation.ants.gouv.fr/tout-savoir/taxes-sur-les-cartes-grises (consulté 22/04/2026)
  - Légifrance — CIBS Paragraphe 2 Taxe régionale et taxes apparentées, art. L. 421-41 à L. 421-54-1 : https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/ (consulté 22/04/2026)

---

### Prélèvement 4 — Taxe régionale sur les certificats d'immatriculation (« taxe Y1 »)

- **Nature** : Taxe ponctuelle à l'immatriculation, perçue par les régions
- **Redevable légal** : Titulaire du certificat d'immatriculation (personne physique ou morale)
- **Périodicité** : Ponctuelle, à chaque première immatriculation ou changement de titulaire
- **Fait générateur** : Délivrance du certificat d'immatriculation
- **Base légale** :
  - CIBS art. L. 421-41 à L. 421-54-1 (tarifs régionaux, tarifs particuliers par catégories)
  - Tarif régional du cheval fiscal fixé par délibération du conseil régional
- **Applicabilité et particularités par année** :
  - **2024, 2025, 2026** : Applicable. Tarif du CV fiscal variable par région (30 € à 68,95 € en 2026 ; moyenne nationale 53,39 €). Calcul = CV fiscal × nombre de CV. Demi-tarif pour véhicules utilitaires N1 (sous conditions), tracteurs, catégories M2/M3/N2/N3.
  - **Évolution majeure 01/05/2025** : Les véhicules électriques/hydrogène ne sont **plus exonérés d'office** de la taxe régionale — chaque région décide de maintenir ou non l'exonération.
  - **LF 2026** : Majoration forfaitaire de 14 € pour tout propriétaire résidant en Île-de-France à compter de mars 2026.
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — redevable = titulaire du certificat d'immatriculation (bailleur dans le contexte Floty)**.
- **Sources** :
  - Légifrance — CIBS Paragraphe 2 : https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/ (consulté 22/04/2026)
  - Legalstart — Taxe régionale carte grise 2026 : https://www.legalstart.fr/fiches-pratiques/vehicule-professionnel/taxe-regionale-carte-grise/ (consulté 22/04/2026)
  - Service-public — Prix carte grise 2026 : https://www.service-public.gouv.fr/particuliers/actualites/A18021 (consulté 22/04/2026)

---

### Prélèvement 5 — Taxe pour le développement des actions de formation professionnelle dans les transports (« taxe Y2 »)

- **Nature** : Taxe ponctuelle à l'immatriculation, additionnelle à la taxe régionale
- **Redevable légal** : Titulaire du certificat d'immatriculation, pour les véhicules de transport routier (VUL, camions)
- **Périodicité** : Ponctuelle, à l'immatriculation
- **Fait générateur** : Première immatriculation en France d'un véhicule affecté au transport routier
- **Base légale** : CIBS art. L. 421-55 à L. 421-57 (paragraphe 3, Taxe sur les véhicules de transport)
- **Applicabilité et particularités par année** : Applicable 2024, 2025, 2026. Concerne les VUL (N1) et camions. Exonération pour véhicules non polluants bénéficiant déjà d'une exonération de taxe régionale, et pour véhicules de collection (> 30 ans).
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — redevable = titulaire du certificat d'immatriculation (bailleur dans le contexte Floty)**. De plus, concerne spécifiquement les véhicules de transport routier, ce qui peut être pertinent pour une partie de la flotte (pick-ups N1) mais reste ponctuel et attaché à l'immatriculation.
- **Sources** :
  - Légifrance — CIBS Paragraphe 3 Taxe sur les véhicules de transport : https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599043/ (consulté 22/04/2026)
  - Flotauto — Taxe formation professionnelle transports routiers : https://www.flotauto.com/taxe-association-developpement-formation-professionnelle-transports-routiers-20140718.html (consulté 22/04/2026)

---

### Prélèvement 6 — Taxe d'acheminement du certificat d'immatriculation (« taxe Y5 »)

- **Nature** : Redevance de service, accessoire à la délivrance du titre
- **Redevable légal** : Titulaire du certificat d'immatriculation
- **Périodicité** : Ponctuelle
- **Fait générateur** : Envoi postal du certificat d'immatriculation
- **Base légale** : Arrêté ministériel (pas une taxe au sens du CIBS). Montant 2,76 €.
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — nature non fiscale (redevance postale) + redevable = titulaire (bailleur)**. Cité pour exhaustivité, non retenu.
- **Sources** : eplaque — Taxe Y5 : https://www.eplaque.fr/carte-grise/y5-carte-grise (consulté 22/04/2026)

---

### Prélèvement 7 — Taxe annuelle sur les émissions de dioxyde de carbone des véhicules de tourisme affectés à des fins économiques (« ex-TVS composante CO₂ »)

- **Nature** : Taxe annuelle
- **Redevable légal** : **Entreprise utilisatrice** du véhicule (définition CIBS art. L. 421-94 et L. 421-99) :
  - Entreprise qui **détient** le véhicule en pleine propriété (art. L. 421-25 par renvoi)
  - Entreprise qui en **dispose** via location longue durée (> 1 mois) ou mise à disposition
  - Entreprise qui **prend en charge** les frais d'acquisition ou d'utilisation (remboursements kilométriques à des salariés au-delà du seuil)
  - Pour la location courte durée (< 1 mois) : pas d'assujettissement (exonération)
- **Périodicité** : **Annuelle**, avec prorata temporis sur la durée effective d'affectation à l'entreprise (article L. 421-99 et L. 421-104)
- **Fait générateur** : Affectation du véhicule à l'activité économique de l'entreprise utilisatrice sur le territoire français, au cours de l'année civile
- **Base légale** :
  - CIBS art. L. 421-93 à L. 421-132 (section 3, taxes d'affectation, incluant les tarifs CO₂)
  - Tarif : L. 421-120 à L. 421-132 (barèmes WLTP, NEDC et puissance fiscale selon méthode de mesure et date de 1ère immatriculation)
  - BOFiP `BOI-AIS-MOB-10-30-10` (dispositions communes) et `BOI-AIS-MOB-10-30-20` (véhicules de tourisme)
- **Applicabilité et particularités par année** :
  - **2024** : Seuil d'exonération CO₂ à 15 g/km ; barème WLTP progressif par tranches. Exonération totale : véhicules exclusivement électriques, hydrogène, ou combinaison des deux (CIBS art. L. 421-124). Exonération hybride conditionnelle (CIBS art. L. 421-125 dans sa version 2022-2024) couvrant les véhicules combinant E85 avec une autre source d'énergie sous seuils CO₂/PA. **Aucun abattement isolé** applicable en 2024 — l'abattement E85 (40 % CO₂ ou 2 CV PA, plafonds 250 g/km et 12 CV) résulte de la révision de l'article L. 421-125 par LF 2025 et n'entre en vigueur qu'à compter du 1er janvier 2025.
  - **2025** : Seuil abaissé à **10 g/km** au 01/01/2025. Seuls les véhicules électriques/hydrogène/électrique+hydrogène restent totalement exonérés (les hybrides rechargeables PHEV perdent l'exonération totale). Barème WLTP renforcé.
  - **2026** : Seuil abaissé à **5 g/km** au 01/01/2026. Barème WLTP à nouveau durci (art. 58 LF 2026).
- **Évaluation de la redevabilité** : **À INCLURE DANS FLOTY** — c'est précisément la première taxe coeur du cahier des charges. Redevable = entreprise utilisatrice ✓. Périodicité annuelle avec prorata par jours d'utilisation ✓. Cœur du modèle métier Floty (répartition des coûts entre entreprises co-utilisatrices).
- **Sources** :
  - Légifrance — CIBS Section 3, Taxes sur l'affectation des véhicules à des fins économiques, art. L. 421-93 à L. 421-167 : https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599155/ (consulté 22/04/2026)
  - BOFiP `BOI-AIS-MOB-10-30-20-20250528` : https://bofip.impots.gouv.fr/bofip/13954-PGP.html/identifiant=BOI-AIS-MOB-10-30-20-20250528 (consulté 22/04/2026)
  - BOFiP `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes) : https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528 (consulté 22/04/2026)
  - economie.gouv.fr — Taxes affectation véhicules fins économiques : https://www.economie.gouv.fr/entreprises/gerer-sa-fiscalite-et-ses-impots/limpot-sur-les-benefices-ir-et/entreprises-ce-quil-faut-savoir-sur-les-taxes-sur-laffectation-des (consulté 22/04/2026)
  - PwC Avocats — LF 2024 fiscalité véhicules : https://www.pwcavocats.com/fr/ealertes/ealertes-france/2024/loi-de-finances-pour-2024-mesures-pour-les-entreprises/amenagement-de-la-fiscalite-applicable-aux-vehicules.html (consulté 22/04/2026)

---

### Prélèvement 8 — Taxe annuelle sur les émissions de polluants atmosphériques des véhicules de tourisme affectés à des fins économiques (« ex-TVS composante air »)

- **Nature** : Taxe annuelle
- **Redevable légal** : **Entreprise utilisatrice** — définition strictement identique au Prélèvement 7 (mêmes règles d'assujettissement CIBS art. L. 421-94 / L. 421-99).
- **Périodicité** : **Annuelle**, avec prorata temporis identique au Prélèvement 7
- **Fait générateur** : Affectation du véhicule à l'activité économique sur le territoire français
- **Base légale** :
  - CIBS art. L. 421-133 à L. 421-144 (paragraphe 4, tarifs de la taxe annuelle sur les polluants atmosphériques)
  - BOFiP `BOI-AIS-MOB-10-30-20`
- **Applicabilité et particularités par année** :
  - **2024** : Tarifs fixes par catégorie "polluants" du véhicule (Euro 5/6 Crit'Air 1 → 100 € ; catégorie "E" véhicules les plus polluants → 500 € ; véhicules électriques/hydrogène → 0 €). Catégorie selon norme Euro et/ou date de 1ère immatriculation.
  - **2025** : Tarifs inchangés par rapport à 2024.
  - **2026** : **Revalorisation par article 58 LF 2026** — tarifs en hausse (non modifiés depuis 2024). Ex. Euro 5/6 Crit'Air 1 → 130 € ; véhicules les plus polluants → 650 €. Nouvelle hausse prévue en 2027.
- **Évaluation de la redevabilité** : **À INCLURE DANS FLOTY** — deuxième taxe cœur. Redevable = entreprise utilisatrice ✓. Périodicité annuelle avec prorata ✓.
- **Sources** :
  - Légifrance — CIBS Paragraphe 4, art. L. 421-133 à L. 421-144 : https://www.legifrance.gouv.fr/codes/id/LEGISCTA000048844560 (consulté 22/04/2026)
  - BOFiP `BOI-AIS-MOB-10-30-20-20250528` (cité supra, Prélèvement 7)
  - FNA — Taxe annuelle véhicules de tourisme 2026 : https://fna.fr/documents/la-taxe-annuelle-sur-les-vehicules-de-tourisme-2026-ex-tvs/ (consulté 22/04/2026)

---

### Prélèvement 9 — Taxe annuelle incitative relative à l'acquisition de véhicules légers à faibles émissions (« TAI verdissement des flottes »)

- **Nature** : Taxe annuelle incitative (sanction pour non-atteinte d'un quota de verdissement)
- **Redevable légal** : **Entreprise disposant d'une flotte ≥ 100 véhicules** sur l'année civile (ratio durées d'affectation / jours de l'année > 100). La définition "entreprise" renvoie aux assujettis TVA (CGI art. 256 A et 256 B).
- **Périodicité** : Annuelle
- **Fait générateur** : Constitution, sur l'année civile, d'une flotte d'au moins 100 véhicules affectés à des fins économiques dont la part de véhicules à faibles émissions (VFE) est inférieure au taux cible
- **Base légale** :
  - CIBS art. L. 421-132-1 à L. 421-132-6 (paragraphe 3 bis, créé par LF 2025)
  - Loi de finances pour 2025 (loi n° 2025-127 du 14/02/2025)
- **Applicabilité et particularités par année** :
  - **2024** : **Non applicable** (taxe créée par LF 2025).
  - **2025** : Applicable, mais sur période fractionnée 01/03/2025 → 31/12/2025 (première année d'application). Quota cible 15 % de VFE. Tarif 2 000 €/véhicule manquant. Déclaration en janvier 2026.
  - **2026** : Applicable en année pleine (365 jours). Quota cible **18 %**. Tarif **4 000 €/véhicule manquant**. Progression programmée : 25 % en 2027, 30 % en 2028, 35 % en 2029, 48 % en 2030.
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — redevable = propriétaire-bailleur de la flotte (société de location de Renaud, si elle atteint le seuil de 100 véhicules au sens du CIBS)**.
  - Les entreprises **utilisatrices** prises individuellement, dans le modèle Floty, disposent de portions de flotte bien inférieures à 100 véhicules-équivalents-an et ne sont donc pas redevables.
  - Le seuil peut être atteint au niveau de la société de location propriétaire — mais celle-ci n'est pas dans le périmètre de calcul Floty.
  - Confirmation du positionnement déjà acté par `methodologie.md` §3.4 et annexe A.
  - **À documenter pour vigilance future** si l'architecture du groupe évolue (ex : consolidation d'entreprises utilisatrices qui atteindraient ensemble le seuil).
- **Sources** :
  - Légifrance — CIBS Paragraphe 3 bis, art. L. 421-132-1 à L. 421-132-6 : https://www.legifrance.gouv.fr/codes/id/LEGISCTA000051214904 (consulté 22/04/2026)
  - Arval Mobility Observatory — TAI en 15 questions : https://www.mobility-observatory.arval.fr/taxe-annuelle-incitative-verdissement-des-flottes-automobiles-15-questions (consulté 22/04/2026)
  - Legifiscal — TAI acquisition VFE : https://www.legifiscal.fr/impots-entreprises/taxes-diverses/autres-impots-professionnels/taxe-annuelle-incitative-relative-acquisition-vehicules-legers-faibles-emissions.html (consulté 22/04/2026)
  - Drive to Business — Taxe verdissement flottes 2026 : https://www.drivetobusiness.fr/taxe-annuelle-incitative-relative-a-lacquisition-de-vehicules-legers-a-faibles-emissions/ (consulté 22/04/2026)

---

### Prélèvement 10 — Taxe annuelle sur les véhicules lourds de transport de marchandises (« ex-taxe à l'essieu »)

- **Nature** : Taxe annuelle
- **Redevable légal** : Entreprise utilisatrice du véhicule lourd
- **Périodicité** : Annuelle
- **Fait générateur** : Utilisation sur le territoire français d'un véhicule de transport de marchandises dont la masse techniquement admissible en charge est **≥ 12 tonnes** (et remorques O4 ≥ 16 tonnes)
- **Base légale** :
  - CIBS art. L. 421-156 et suivants (paragraphe dédié, issu de la refonte 2022)
  - BOFiP `BOI-AIS-MOB-10-30-30`
- **Applicabilité** : 2024, 2025, 2026 — applicable.
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — véhicules hors champ Floty**. Le périmètre Floty est limité aux catégories M1 (voitures particulières) et N1 (utilitaires légers ≤ 3,5 tonnes, incluant pick-ups). Les véhicules ≥ 12 tonnes sont N2, N3 ou O — non couverts par le cahier des charges.
- **Sources** :
  - BOFiP `BOI-AIS-MOB-10-30-30-20250528` : https://bofip.impots.gouv.fr/bofip/13962-PGP.html/identifiant=BOI-AIS-MOB-10-30-30-20250528 (consulté 22/04/2026)
  - impots.gouv.fr — Taxe véhicules lourds marchandises : https://www.impots.gouv.fr/taxe-annuelle-sur-les-vehicules-lourds-de-transport-de-marchandises (consulté 22/04/2026)

---

### Prélèvement 11 — Accise sur les énergies, fraction perçue sur les produits énergétiques (ex-TICPE)

- **Nature** : Accise (taxe indirecte sur la consommation)
- **Redevable légal** : **Producteur, importateur ou distributeur** du carburant. La taxe est économiquement supportée par le consommateur final mais **juridiquement acquittée par l'opérateur**.
- **Périodicité** : À chaque mise à la consommation des carburants
- **Fait générateur** : Mise à la consommation des produits énergétiques en France
- **Base légale** : CIBS livre III (Énergies), ex-TICPE (art. 265 ancien CGI, recodifié CIBS)
- **Applicabilité et particularités par année** : Applicable 2024, 2025, 2026. Remboursement partiel possible pour :
  - Transporteurs routiers de marchandises (≥ 7,5 t)
  - Transporteurs publics de voyageurs
  - Taxis
  - À compter du 01/01/2025, déclaration via annexe 3310-TIC à la TVA. Suppression du tarif forfaitaire au 01/01/2026 (calcul sur tarifs régionaux réels).
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — redevable légal = opérateur pétrolier, pas l'entreprise utilisatrice**. L'entreprise supporte la taxe dans le prix du carburant mais n'est pas redevable au sens fiscal. Le mécanisme de remboursement partiel pour les transporteurs est un droit à restitution, non un prélèvement dont l'entreprise est redevable.
- **Sources** :
  - impots.gouv.fr — Accise produits pétroliers ex-TICPE : https://www.impots.gouv.fr/accise-sur-les-produits-petroliers-ex-ticpe-remboursements-des-taxis-et-transporteurs (consulté 22/04/2026)
  - Service-public Entreprendre — Remboursement accise gazole : https://entreprendre.service-public.gouv.fr/vosdroits/F31222 (consulté 22/04/2026)

---

### Prélèvement 12 — TVA sur acquisition et utilisation des véhicules

- **Nature** : Impôt indirect général, pas une taxe spécifique véhicule
- **Redevable légal** : Entreprise assujettie à la TVA
- **Périodicité** : Périodique (mensuelle, trimestrielle, annuelle selon régime)
- **Règle spécifique véhicules** : TVA sur acquisition et entretien de véhicules de tourisme (M1) **non déductible** (sauf exceptions : taxis, VTC, auto-écoles, revendeurs). TVA sur utilitaires N1 (mention BB sur carte grise) **intégralement déductible**. TVA sur carburant partiellement déductible (80 % gazole/essence VP, 100 % GPL/électricité).
- **Base légale** : CGI art. 271 et suivants ; art. 206 IV annexe II du CGI
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — impôt général non spécifique aux véhicules, déjà géré par la comptabilité générale des entreprises**. Floty n'a pas vocation à traiter la TVA. Mentionné ici pour exhaustivité.

---

### Prélèvement 13 — Limitation de la déduction de l'amortissement des véhicules de tourisme (plafond fiscal)

- **Nature** : Ce n'est **pas un prélèvement**, c'est un mécanisme de **réintégration fiscale** au résultat imposable
- **Redevable de facto** : Entreprise propriétaire du véhicule (ou preneur en crédit-bail) soumise à l'IS ou à l'IR BIC/BNC
- **Règle** : L'amortissement déductible du véhicule de tourisme est plafonné :
  - 30 000 € si CO₂ ≤ 20 g/km (électrique)
  - 20 300 € si 20 < CO₂ ≤ 50 g/km (hybride rechargeable)
  - 18 300 € si 50 < CO₂ ≤ 160 g/km (thermique homologué WLTP ≤ seuil)
  - 9 900 € si CO₂ > seuil
- **Base légale** : CGI art. 39, 4
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — mécanisme de réintégration au résultat imposable, traité par la comptabilité/fiscalité des bénéfices**. Ce n'est ni une taxe ponctuelle ni une taxe récurrente sur l'affectation. Cité pour exhaustivité. De plus, redevable de facto = propriétaire (bailleur).

---

### Prélèvement 14 — Amendes ZFE (zones à faibles émissions)

- **Nature** : **Sanction pénale** (contravention), pas un prélèvement fiscal
- **Redevable** : Titulaire du certificat d'immatriculation (ou conducteur verbalisé)
- **Montants** : 68 € (VUL), 135 € (poids lourd), minoration/majoration selon délai
- **Évaluation de la redevabilité** : **HORS PÉRIMÈTRE — nature non fiscale (amende pénale)**. Cité pour exhaustivité ; pas de redevance fiscale associée à la circulation en ZFE à ce jour (2026), seulement des interdictions assorties de contraventions.
- **Sources** : Service-public et documentation métropoles (Lyon, Grenoble, Saint-Étienne) consultées 22/04/2026.

---

### Prélèvement 15 — Péages autoroutiers et péages urbains

- **Nature** : Redevance d'usage (contrepartie d'un service), non fiscale
- **Évaluation** : **HORS PÉRIMÈTRE — nature non fiscale**. Pas de péage urbain instauré en France métropolitaine à ce jour. Les péages autoroutiers relèvent du droit commercial (concessions).

---

### Prélèvement 16 — Taxe additionnelle sur les véhicules d'occasion (supprimée)

- **Statut** : **Supprimée depuis le 01/01/2021** (pour les véhicules d'occasion déjà immatriculés en France). Le « malus CO₂ sur occasion » est juridiquement la même taxe que le Prélèvement 1 ; il s'applique désormais uniquement aux occasions importées ou, à partir de 2027 (report de 2026), à certaines occasions.
- **Évaluation** : Sans objet pour 2024-2026. Cité pour clôture historique.

---

## 4. Synthèse — À inclure dans Floty

| # | Nom | Périodicité | Années | Cœur métier Floty ? |
|---|---|---|---|---|
| 7 | Taxe annuelle sur les émissions de CO₂ (ex-TVS CO₂) | Annuelle avec prorata | 2024, 2025, 2026 | **OUI, cœur** |
| 8 | Taxe annuelle sur les émissions de polluants atmosphériques (ex-TVS air) | Annuelle avec prorata | 2024, 2025, 2026 | **OUI, cœur** |

**Décision** : **deux taxes retenues**, correspondant exactement au périmètre déjà pressenti dans le cahier des charges et la méthodologie. Elles partagent le même redevable (entreprise utilisatrice), la même périodicité (annuelle avec prorata par jours d'affectation), le même ensemble de règles d'assiette (définition du véhicule taxable, exonérations, abattements), et ne diffèrent que par le paramètre tarifaire. Elles sont le seul couple de prélèvements qui correspond au modèle Floty (répartition de l'impôt entre entreprises co-utilisatrices d'un véhicule).

**Conséquence pour l'arborescence `recherches-fiscales/{année}/`** : les sous-dossiers à créer pour 2024, 2025 et 2026 sont :
- `taxe-co2/`
- `taxe-polluants/`
- `exonerations/` (transversal aux deux taxes)
- `abattements/` (transversal)
- `cas-particuliers/` (transversal)

Soit exactement l'arborescence déjà prévue par `methodologie.md` §6.1. La cartographie **confirme** cette arborescence sans évolution.

---

## 5. Synthèse — À exclure du périmètre Floty

| # | Nom | Motif d'exclusion |
|---|---|---|
| 1 | Malus CO₂ à l'immatriculation | Redevable = titulaire du certificat d'immatriculation (bailleur dans le modèle Floty). Taxe ponctuelle intégrée au coût d'acquisition, non récurrente, non répartie entre utilisateurs. |
| 2 | Malus au poids à l'immatriculation | Idem Prélèvement 1. |
| 3 | Taxe fixe Y4 | Idem (+ taxe de gestion, pas une taxe fiscale au sens classique). |
| 4 | Taxe régionale Y1 | Idem (+ intégrée au coût d'acquisition). |
| 5 | Taxe Y2 (formation professionnelle transport routier) | Idem. |
| 6 | Taxe Y5 (acheminement) | Nature non fiscale (redevance postale) + redevable = bailleur. |
| 9 | TAI verdissement des flottes | Redevable = propriétaire de flotte ≥ 100 véhicules (au sens du CIBS). L'entreprise utilisatrice individuelle, dans le modèle Floty, n'atteint pas ce seuil. Déjà acté `methodologie.md` §3.4. À vigilance si consolidation future. |
| 10 | Taxe véhicules lourds marchandises (ex-taxe à l'essieu) | Véhicules hors périmètre Floty (M1 et N1 ≤ 3,5 t uniquement). Applicable aux ≥ 12 t. |
| 11 | Accise sur les énergies (ex-TICPE) | Redevable légal = opérateur pétrolier, pas l'entreprise utilisatrice. |
| 12 | TVA sur acquisition/utilisation | Impôt général, hors périmètre spécifique véhicule. Géré par la comptabilité générale. |
| 13 | Plafond fiscal d'amortissement | Mécanisme de réintégration fiscale, pas un prélèvement. Géré par la fiscalité des bénéfices. |
| 14 | Amendes ZFE | Nature non fiscale (contraventions). |
| 15 | Péages | Nature non fiscale (redevance d'usage). |
| 16 | Taxe additionnelle véhicules d'occasion (supprimée) | Abrogée depuis 2021 ; sans objet sur 2024-2026. |

---

## 6. Zones d'incertitude

### 6.1 Redevabilité du malus CO₂ et du malus au poids — arbitrage à confirmer

- **Localisation** : Prélèvements 1 et 2.
- **Nature de l'incertitude** : Dans le modèle Floty pressenti (flotte détenue par la société de location de Renaud, mise à disposition d'entreprises utilisatrices), le redevable juridique du malus CO₂/poids est **le bailleur**, qui immatricule le véhicule à son nom. L'entreprise utilisatrice ne supporte pas directement la taxe.
  - **Cependant** : si le bailleur refacture le malus à l'entreprise utilisatrice via le loyer (pratique courante en LLD), on pourrait arguer que Floty **doit** intégrer ces malus pour produire un récapitulatif fiscal complet du coût véhicule supporté par l'entreprise. C'est un choix produit, pas un choix juridique.
- **Notre choix actuel** : **exclusion** — le cahier des charges et la méthodologie cantonnent Floty aux taxes dont l'entreprise utilisatrice est **juridiquement redevable**, pas celles qu'elle supporte économiquement via refacturation.
- **Conséquence si erroné** : Récapitulatif fiscal Floty sous-complet (le total vu par l'entreprise est en réalité supérieur si on inclut la refacturation des malus). Mais pas d'erreur de calcul sur les taxes effectivement déclarées.
- **Action attendue** : Confirmation explicite par Renaud et son expert-comptable que l'exclusion est conforme à l'usage attendu de Floty (récapitulatif fiscal strict, pas TCO véhicule complet).

### 6.2 TAI et agrégation entre entreprises utilisatrices

- **Localisation** : Prélèvement 9.
- **Nature de l'incertitude** : La définition CIBS de la flotte redevable de la TAI repose sur les "véhicules affectés à des fins économiques" de l'entreprise, calculée en jours-équivalents sur l'année. Dans le modèle Floty, chaque entreprise utilisatrice dispose d'une fraction seulement de la flotte. La TAI se calcule **entreprise par entreprise** (redevable = personne morale au sens TVA), pas au niveau du pool — donc a priori chaque entreprise utilisatrice est en dessous du seuil de 100.
  - **Point à vérifier** : l'article L. 421-132-1 et suivants ne prévoit pas d'intégration fiscale automatique entre entreprises partageant des véhicules.
- **Notre choix actuel** : **exclusion** (cohérent avec `methodologie.md` §3.4 et annexe A).
- **Conséquence si erroné** : Risque de sous-déclaration pour une entreprise qui, via Floty, consoliderait in fine ≥ 100 véhicules-équivalents-an.
- **Action attendue** : Vigilance côté application — Floty pourrait à terme calculer le ratio-flotte de chaque entreprise utilisatrice et signaler un dépassement de 100, même si la taxe elle-même reste hors calcul V1. À discuter avec l'expert-comptable.

### 6.3 Refacturation des malus à l'entreprise utilisatrice par le bailleur

- **Localisation** : Prélèvements 1, 2, 3, 4, 5.
- **Nature de l'incertitude** : En LLD, les bailleurs répercutent généralement le coût total des taxes d'immatriculation dans les loyers. Est-ce qu'une entreprise utilisatrice doit pouvoir **tracer** ce coût dans Floty (transparence), ou Floty se limite-t-il aux taxes juridiquement redevables ?
- **Notre choix actuel** : Floty V1 = récapitulatif fiscal juridique strict, donc n'intègre pas ces refacturations.
- **Action attendue** : Confirmation client.

### 6.4 Bascule 01/03/2025 (nouveaux barèmes malus) et 01/01/2026 (abaissement seuils CO₂/poids, revalorisation polluants)

- **Localisation** : Prélèvements 1, 2, 7, 8.
- **Nature de l'incertitude** : Les lois de finances 2025 et 2026 ont introduit des bascules de barème en cours d'année ou en début d'année, dont les dates précises doivent être validées à la seconde source au moment de l'instruction des barèmes :
  - Malus CO₂ 2025 : nouveau barème au **01/03/2025**, pas 01/01.
  - Abaissement seuils CO₂ annuel à 10 g au 01/01/2025 et 5 g au 01/01/2026.
  - Malus au poids : seuil 1 500 kg au 01/01/2026.
  - Taxe polluants : revalorisation 01/01/2026 (LF 2026, art. 58).
- **Notre choix actuel** : Instruction année par année, en se référant systématiquement au texte de la LF applicable.
- **Action attendue** : Lors de la phase 1 (détail des barèmes), documenter la date précise de bascule pour chaque paramètre.

### 6.5 Statut exact de la taxe régionale pour les véhicules électriques depuis mai 2025

- **Localisation** : Prélèvement 4.
- **Nature de l'incertitude** : L'exonération VE de la taxe régionale est devenue facultative par région depuis le 01/05/2025. La cartographie des régions maintenant ou supprimant l'exonération n'est pas stabilisée et évolue chaque exercice budgétaire régional.
- **Conséquence** : Sans objet pour Floty (hors périmètre) mais cité pour complétude.

### 6.6 Véhicules N1 (pick-ups, utilitaires ≥ 5 places) : frontière fiscale avec M1

- **Localisation** : Prélèvements 7 et 8.
- **Nature de l'incertitude** : Un véhicule N1 mention "BE" (pick-up) avec ≥ 5 places ou mention "BB" (camionnette M1/N1 dérivée) peut basculer dans le champ des taxes annuelles CO₂ et polluants selon son homologation. Cette qualification repose sur le paragraphe J.1 de la carte grise et sur le nombre de places assises.
- **Notre choix actuel** : À instruire en phase 1 via la section "Champ d'application" de chaque règle `R-AAAA-XXX` dans `taxes-rules/{année}.md`. Point à documenter précisément pour éviter l'oubli d'un véhicule N1 éligible.

---

## 7. Sources consultées (bibliographie)

### Sources primaires

1. **Légifrance — CIBS Section 2, Taxes sur l'immatriculation des véhicules (art. L. 421-29 à L. 421-92)** — https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/ — consulté 22/04/2026
2. **Légifrance — CIBS Paragraphe 2, Taxe régionale (art. L. 421-41 à L. 421-54-1)** — https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/ — consulté 22/04/2026
3. **Légifrance — CIBS Paragraphe 3, Taxe sur les véhicules de transport (art. L. 421-55 à L. 421-57)** — https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599043/ — consulté 22/04/2026
4. **Légifrance — CIBS Section 3, Taxes sur l'affectation des véhicules à des fins économiques (art. L. 421-93 à L. 421-167)** — https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599155/ — consulté 22/04/2026
5. **Légifrance — CIBS Paragraphe 3 bis, TAI acquisition VFE (art. L. 421-132-1 à L. 421-132-6)** — https://www.legifrance.gouv.fr/codes/id/LEGISCTA000051214904 — consulté 22/04/2026
6. **Légifrance — CIBS Paragraphe 4, Tarifs taxe polluants atmosphériques (art. L. 421-133 à L. 421-144)** — https://www.legifrance.gouv.fr/codes/id/LEGISCTA000048844560 — consulté 22/04/2026
7. **BOFiP-Impôts — BOI-AIS-MOB-10-20-40-20250528 (taxes sur l'immatriculation des véhicules de tourisme)** — https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250528 — consulté 22/04/2026
8. **BOFiP-Impôts — BOI-AIS-MOB-10-30-10-20250528 (dispositions communes taxes d'affectation)** — https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528 — consulté 22/04/2026
9. **BOFiP-Impôts — BOI-AIS-MOB-10-30-20-20250528 (taxes sur l'affectation des véhicules de tourisme)** — https://bofip.impots.gouv.fr/bofip/13954-PGP.html/identifiant=BOI-AIS-MOB-10-30-20-20250528 — consulté 22/04/2026
10. **BOFiP-Impôts — BOI-AIS-MOB-10-30-20-20240710 (version 2024 des taxes d'affectation tourisme)** — https://bofip.impots.gouv.fr/bofip/13954-PGP.html/identifiant=BOI-AIS-MOB-10-30-20-20240710 — consulté 22/04/2026
11. **BOFiP-Impôts — BOI-AIS-MOB-10-30-30-20250528 (taxe véhicules lourds marchandises)** — https://bofip.impots.gouv.fr/bofip/13962-PGP.html/identifiant=BOI-AIS-MOB-10-30-30-20250528 — consulté 22/04/2026
12. **impots.gouv.fr — Taxe annuelle sur les véhicules lourds de transport de marchandises** — https://www.impots.gouv.fr/taxe-annuelle-sur-les-vehicules-lourds-de-transport-de-marchandises — consulté 22/04/2026
13. **impots.gouv.fr — Accise sur les produits pétroliers (ex-TICPE), remboursements taxis et transporteurs** — https://www.impots.gouv.fr/accise-sur-les-produits-petroliers-ex-ticpe-remboursements-des-taxis-et-transporteurs — consulté 22/04/2026

### Sources secondaires

14. **economie.gouv.fr — Entreprises : taxes sur l'affectation des véhicules à des fins économiques (ex-TVS)** — https://www.economie.gouv.fr/entreprises/gerer-sa-fiscalite-et-ses-impots/limpot-sur-les-benefices-ir-et/entreprises-ce-quil-faut-savoir-sur-les-taxes-sur-laffectation-des — consulté 22/04/2026
15. **economie.gouv.fr — Malus automobile, quelles taxes payer** — https://www.economie.gouv.fr/particuliers/voyager-et-se-deplacer/malus-automobile-quelles-taxes-devez-vous-payer-sur-votre-vehicule — consulté 22/04/2026
16. **Service-public Entreprendre — Taxes sur l'affectation des véhicules de tourisme (ex-TVS)** — https://entreprendre.service-public.gouv.fr/vosdroits/F22203 — consulté 22/04/2026
17. **Service-public Entreprendre — Remboursement partiel accise gazole** — https://entreprendre.service-public.gouv.fr/vosdroits/F31222 — consulté 22/04/2026
18. **Service-public — Taxe sur la masse en ordre de marche (malus masse)** — https://www.service-public.gouv.fr/particuliers/vosdroits/F35950 — consulté 22/04/2026
19. **Service-public — Coût de la carte grise 2026** — https://www.service-public.gouv.fr/particuliers/actualites/A18021 — consulté 22/04/2026
20. **Service-public Entreprendre — Déclaration taxe annuelle véhicules lourds marchandises** — https://entreprendre.service-public.gouv.fr/actualites/A17077 — consulté 22/04/2026
21. **ecologie.gouv.fr — Fiscalité environnementale véhicules** — https://www.ecologie.gouv.fr/politiques-publiques/fiscalite-environnementale-relative-aux-vehicules — consulté 22/04/2026
22. **ANTS — Taxes sur les cartes grises** — https://immatriculation.ants.gouv.fr/tout-savoir/taxes-sur-les-cartes-grises — consulté 22/04/2026

### Sources tertiaires (croisement d'interprétation)

23. **PwC Avocats — LF 2024 : fiscalité applicable aux véhicules** — https://www.pwcavocats.com/fr/ealertes/ealertes-france/2024/loi-de-finances-pour-2024-mesures-pour-les-entreprises/amenagement-de-la-fiscalite-applicable-aux-vehicules.html — consulté 22/04/2026
24. **FNA — Taxe annuelle véhicules de tourisme 2026** — https://fna.fr/documents/la-taxe-annuelle-sur-les-vehicules-de-tourisme-2026-ex-tvs/ — consulté 22/04/2026
25. **Arval Mobility Observatory — TAI en 15 questions** — https://www.mobility-observatory.arval.fr/taxe-annuelle-incitative-verdissement-des-flottes-automobiles-15-questions — consulté 22/04/2026
26. **Legifiscal — TAI acquisition VFE** — https://www.legifiscal.fr/impots-entreprises/taxes-diverses/autres-impots-professionnels/taxe-annuelle-incitative-relative-acquisition-vehicules-legers-faibles-emissions.html — consulté 22/04/2026
27. **Drive to Business — Taxe verdissement flottes 2026** — https://www.drivetobusiness.fr/taxe-annuelle-incitative-relative-a-lacquisition-de-vehicules-legers-a-faibles-emissions/ — consulté 22/04/2026
28. **Drive to Business — Malus écologique 2025-2026 pour professionnels** — https://www.drivetobusiness.fr/bonus-et-malus-ecologique/ — consulté 22/04/2026
29. **Drive to Business — Taxe d'immatriculation pour les entreprises** — https://www.drivetobusiness.fr/la-taxe-dimmatriculation/ — consulté 22/04/2026
30. **Largus — Malus écologique 2026** — https://www.largus.fr/actualite-automobile/malus-ecologique-2026-tout-savoir-sur-le-nouveau-bareme-applicable-des-le-1er-janvier-30044217.html — consulté 22/04/2026
31. **Largus — Malus au poids 2026-2028** — https://www.largus.fr/actualite-automobile/malus-au-poids-2026-2028-baremes-dates-et-exonerations-30044052.html — consulté 22/04/2026
32. **Legalstart — Taxe régionale carte grise 2026** — https://www.legalstart.fr/fiches-pratiques/vehicule-professionnel/taxe-regionale-carte-grise/ — consulté 22/04/2026
33. **Cartecarburant — TVS 2026 (fiche pédagogique)** — https://www.cartecarburant.com/blog-cartes-carburant/reglementation/tvs-2026-comprendre-les-nouvelles-taxes-sur-les-vehicules-de-societe/ — consulté 22/04/2026
34. **Compta-Online — Taxes véhicules 2026** — https://www.compta-online.com/taxes-sur-les-vehicules-ao6174 — consulté 22/04/2026
35. **Flotauto — Taxe association développement formation professionnelle transports routiers** — https://www.flotauto.com/taxe-association-developpement-formation-professionnelle-transports-routiers-20140718.html — consulté 22/04/2026
36. **Fidu / ESK / Cogep — Impôts et taxes automobile et transport, changements 2026** — https://fidu.fr/impots-et-taxes-pour-les-professionnels-de-lautomobile-et-du-transport-ce-qui-va-changer-en-2026/ — consulté 22/04/2026
37. **Clinique du Droit de Rouen — Durcissement régime fiscal véhicules société 2025** — https://www.cliniquedudroitrouen.fr/2026/01/16/entre-fiscalite-des-vehicules-de-societe-et-transition-ecologique-durcissement-du-regime-en-2025/ — consulté 22/04/2026
38. **FIDUCIAL — Deux taxes mobilités remplacent la TVS** — https://www.fiducial.fr/Articles-et-dossiers/Depuis-le-1er-janvier-2022-deux-taxes-mobilites-remplacent-la-taxe-sur-les-vehicules-de-societes — consulté 22/04/2026
39. **eplaque — Taxes Y4, Y5** — https://www.eplaque.fr/carte-grise/y4-carte-grise + https://www.eplaque.fr/carte-grise/y5-carte-grise — consulté 22/04/2026

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Brouillon initial de la cartographie phase 0 — recensement de 16 prélèvements, application du filtre de redevabilité, confirmation du périmètre Floty réduit aux Prélèvements 7 et 8 (taxes annuelles CO₂ et polluants), documentation de 6 zones d'incertitude, bibliographie 39 entrées. |
| 0.2 | 23/04/2026 | Micha MEGRET | Correction du Prélèvement 7 entrée 2024 : suppression de la mention erronée « abattement E85 de 40 % » (qui n'entre en vigueur qu'au 01/01/2025 par révision LF 2025 de l'article L. 421-125). Précision que pour 2024, l'article L. 421-125 dans sa version 2022-2024 prévoit une exonération conditionnelle (et non un abattement) pour les véhicules combinant E85 avec une autre source d'énergie sous seuils. Correction documentée dans `2024/abattements/decisions.md` Décision 3 et clôture de Z-2024-003. |
