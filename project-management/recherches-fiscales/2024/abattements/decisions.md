# Décisions — Abattements applicables aux taxes annuelles CO₂ et polluants — Exercice 2024

> **Statut** : Version 0.1
> **Auteur** : Micha MEGRET (prestataire)
> **Date** : 23 avril 2026
> **Renvoi sources** : voir `sources.md` (cotes S1, S2, …) et `recherches.md` pour les analyses détaillées.

---

## Décision 1 — Périmètre des abattements à implémenter dans Floty V1 pour 2024

**Contexte** : la recherche exhaustive (`recherches.md` § 3 à § 6) conclut qu'**aucun abattement isolé** (au sens « modification d'une caractéristique d'entrée d'une règle de tarification ») **n'est applicable aux taxes annuelles CO₂ et polluants en 2024**. La seule disposition du CIBS qui emploie le terme « abattement » dans son texte est l'article L. 421-111, qui décrit en réalité une **minoration forfaitaire de 15 000 €** sur le montant cumulé des deux taxes dues au titre des véhicules salariés/dirigeants donnant lieu à prise en charge de frais kilométriques. Cette mécanique est par construction hors périmètre Floty V1 (la flotte Renaud n'inclut pas ce type de véhicules).

Il faut formaliser ce constat sans ambiguïté pour Floty.

**Options envisagées** :
- **Option A — Modéliser la minoration 15 000 € comme règle inactive en V1** : implémenter la structure de données (par exhaustivité du modèle, conformément à la méthodologie § 3.2 et au pattern de la Décision 1 de `2024/exonerations/decisions.md`), avec un flag `actif_par_défaut = false`. Activable au cas par cas en V2 si une entreprise utilisatrice atypique entre dans le périmètre.
- **Option B — Ne pas la modéliser** : Floty V1 n'inclut tout simplement pas la minoration 15 000 €. Plus minimaliste ; mais brise la symétrie avec la Décision 1 de `2024/exonerations/decisions.md` qui modélise toutes les exonérations (y compris celles inactives par défaut).
- **Option C — Implémenter la minoration 15 000 € comme règle active V1** : impose de saisir un kilométrage remboursé par véhicule, et de classer chaque véhicule en « salarié/dirigeant avec frais kilométriques » ou « autres ». Coût d'implémentation important pour zéro gain V1.

**Décision retenue** : **Option A — modélisation passive avec flag d'activation `false`**.

Pour 2024, **aucun abattement** au sens technique (modification de caractéristique d'entrée) n'est implémenté comme règle active dans Floty V1. La **minoration 15 000 €** (CIBS art. L. 421-111) est **modélisée dans la structure de données** (par exhaustivité), avec :
- `règle_type = minoration`
- `actif_par_défaut = false`
- annotation explicite « Hors périmètre Floty V1 — concerne les véhicules salariés/dirigeants donnant lieu à prise en charge de frais kilométriques (CIBS art. L. 421-95, 2°). Aucun véhicule de la flotte Floty V1 n'est concerné. »

L'**abattement E85** prévu par la version révisée de l'article L. 421-125 (**à compter du 1er janvier 2025**) n'est PAS applicable en 2024 — il sera instruit dans `2025/abattements/`.

**Justification** :
- Conforme au principe d'exhaustivité de la méthodologie (§ 3.2) : le modèle de données couvre toutes les dispositions CIBS, y compris celles inactives en V1.
- Conforme au principe d'évolutivité (méthodologie § 2 — « privilégier la maintenabilité et l'évolutivité du code ») : l'activation ultérieure de la minoration 15 000 € (si une entreprise utilisatrice atypique le requiert) est un simple changement de flag, pas une réécriture.
- Cohérent avec le traitement symétrique des exonérations (Décision 1 de `2024/exonerations/decisions.md`).

**Niveau de confiance** : **Haute** (choix produit cohérent avec la méthodologie et conforme aux conclusions triangulées de la recherche).

**À valider par expert-comptable** : Non sur le principe ; à mentionner dans le rapport de livraison pour transparence.

**Conséquences sur l'implémentation** :
- La table de règles d'abattement / minoration en base de données comporte une seule entrée pour 2024 :
  - `nom = "Minoration 15 000 € sur véhicules salariés/dirigeants (CIBS art. L. 421-111)"`
  - `règle_type = minoration`
  - `actif_par_défaut = false`
  - `champ_consommé = "véhicule.statut_salarié_dirigeant_avec_frais_km"` (booléen — toujours faux dans Floty V1)
  - `champ_modifié = aucun` (la minoration s'applique sur le montant cumulé à payer, pas sur une caractéristique véhicule)
- L'UI Floty (page de consultation des règles, cf. cahier des charges § 4.2) affiche cette règle avec un badge « Inactif (activable) » et la mention « Hors périmètre Floty V1 ».
- Le PDF récapitulatif (cahier des charges § 5.7) **ne fait pas mention** de cette minoration pour 2024 (puisqu'elle n'est jamais déclenchée).
- Une page « Abattements applicables » de l'UI Floty pour l'exercice 2024 affiche le message clair : « **Aucun abattement applicable en 2024** » avec un renvoi vers la rubrique « Exonérations » pour le mécanisme de l'article L. 421-125 (qui couvre l'E85 en combinaison avec une autre source d'énergie, sous condition).

---

## Décision 2 — Clôture de l'incertitude Z-2024-003 (« Abattement E85 en 2024 »)

**Contexte** : l'incertitude `Z-2024-003 — Abattement E85 en 2024`, ouverte dans `2024/taxe-co2/incertitudes.md` lors de l'instruction de la taxe CO₂, devait être close par la présente mission. Elle constatait une divergence apparente entre :
- le cahier des charges Floty § 5.6 (« abattement E85 à compter du 1er janvier 2025 »),
- la cartographie phase 0 (« 2024 : abattement E85 de 40 % sur taux CO₂ »),
- la notice DGFiP S5 pour 2024 (aucune mention d'abattement E85).

**Options envisagées** :
- **Option A — Confirmer l'absence d'abattement E85 isolé en 2024** : conforme à la lecture des sources primaires.
- **Option B — Implémenter l'abattement E85 en 2024 conformément à la cartographie phase 0** : non conforme à la lecture des sources primaires.

**Décision retenue** : **Option A — l'abattement E85 isolé n'est PAS applicable en 2024.**

L'incertitude Z-2024-003 est clôturée comme suit :

- Pour 2024, l'E85 est traité **uniquement** dans le cadre de l'**exonération hybride conditionnelle** § L. 421-125 (en combinaison avec une autre source d'énergie : électrique, hydrogène, gaz naturel ou GPL — cf. `2024/exonerations/decisions.md` Décision 4).
- À compter du 1er janvier 2025, l'article L. 421-125 du CIBS est **révisé** par la loi de finances pour 2025 (loi n° 2025-127 du 14 février 2025) : il décrit alors un **abattement** E85 (40 % sur les émissions de CO₂ ou 2 CV sur la puissance administrative, plafonds 250 g/km et 12 CV). Ce nouveau mécanisme sera instruit dans `2025/abattements/`.
- Pour un véhicule mono-carburant E85 affecté à des fins économiques en 2024 : ni exonération ni abattement → **plein tarif** selon le barème WLTP, NEDC ou PA applicable (voir Décision 4 ci-dessous pour l'exemple chiffré illustrant la non-application).

**Justification** :
- Triangulation primaire complète (CIBS art. L. 421-125 version 2024 ; BOFiP `BOI-AIS-MOB-10-30-20-20240710` ; notice DGFiP n° 2857-FC-NOT-SD édition décembre 2024) — voir `recherches.md` § 5.3 à § 5.5.
- Confirmation par la doctrine BOFiP postérieure à 2024 (`BOI-AIS-MOB-10-30-20` à compter du 28/05/2025, § 240) qui date sans ambiguïté l'apparition de l'abattement E85 « à compter du 1er janvier 2025 » — voir `recherches.md` § 5.5.
- Confirmation par la formulation explicite du cahier des charges Floty § 5.6 (« à compter du 1er janvier 2025 »).
- Confirmation par les sources tertiaires consultées (PwC, FNA, Drive to Business, Compta-Online, Legifiscal — voir `recherches.md` § 5.6).

**Niveau de confiance** : **Haute** (triangulation primaire complète et univoque).

**À valider par expert-comptable** : Non (la lecture est univoque). À mentionner dans le rapport de livraison pour transparence sur la clôture de l'incertitude.

**Conséquences sur l'implémentation** :
- Aucune règle « abattement E85 » n'est implémentée pour 2024 dans le moteur de calcul Floty.
- L'incertitude Z-2024-003 est passée au statut « **Résolu** » dans `2024/taxe-co2/incertitudes.md` (sa localisation d'origine), avec date de clôture (23/04/2026) et résumé de l'issue.
- L'index global `recherches-fiscales/incertitudes.md` est mis à jour en conséquence (statut « Résolu » pour Z-2024-003 et compteur de synthèse 2024).
- Un renvoi de clôture est mentionné dans le présent sous-dossier dans `incertitudes.md` (entrée brève pointant vers `2024/taxe-co2/incertitudes.md` pour le détail consolidé).

---

## Décision 3 — Correction de la cartographie phase 0 (mention erronée « abattement E85 en 2024 »)

**Contexte** : la cartographie `cartographie-taxes.md` § 7 (Prélèvement 7), dans sa rubrique « Particularités 2024 », mentionne par erreur un « abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) ». Cette mention est en contradiction avec les sources primaires (CIBS, BOFiP, notice DGFiP) et avec la formulation du cahier des charges Floty § 5.6. Elle est l'origine documentaire de l'incertitude Z-2024-003.

**Options envisagées** :
- **Option A — Corriger la cartographie pour acter l'erreur** : suppression de la mention « abattement E85 » sous Prélèvement 7 « 2024 » ; mention conservée mais déplacée sous « 2025 ».
- **Option B — Conserver la cartographie en l'état et documenter l'erreur uniquement dans `2024/abattements/`** : laisse une trace contradictoire dans la documentation.

**Décision retenue** : **Option A — correction de la cartographie**.

Une mise à jour de `cartographie-taxes.md` § 7 (Prélèvement 7, sous-rubrique « 2024 ») est proposée pour :
- supprimer la mention erronée « abattement E85 de 40 % sur taux CO₂ (plafond 250 g/km) » qui est en réalité applicable à compter du 01/01/2025 ;
- ajouter sous « 2025 » une mention explicite de l'apparition de l'abattement E85 par révision de l'article L. 421-125 du CIBS, conformément à la doctrine BOFiP `BOI-AIS-MOB-10-30-20` § 240 (version postérieure à 2024).

**Note** : la modification effective de `cartographie-taxes.md` est une opération transverse (qui touche la phase 0). Elle sera proposée à Renaud avec un commit dédié, mentionnant explicitement la résolution de l'incertitude Z-2024-003 comme motif.

**Justification** :
- Conforme au principe de cohérence inter-documents (méthodologie § 7 — critères de complétude).
- Évite qu'une lecture future de la cartographie ne réintroduise l'incertitude Z-2024-003.
- Conforme au principe de transparence sur les corrections (méthodologie § 10.3 — versioning intra-document).

**Niveau de confiance** : **Haute** (correction documentée par triangulation primaire).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Aucune conséquence sur le code (la cartographie ne pilote pas directement les seeders ; elle pilote l'arborescence des sous-dossiers à instruire, qui reste inchangée).
- Conséquence documentaire uniquement : mise à jour de `cartographie-taxes.md` § 7 et ajout d'une entrée dans son historique de version.

---

## Décision 4 — Exemple chiffré illustrant la non-application de l'abattement E85 en 2024

**Contexte** : la méthodologie projet (§ 5.3) impose qu'au moins un exemple chiffré illustre le résultat d'une règle. Pour la conclusion principale « aucun abattement isolé en 2024 », il est utile de produire un exemple qui montre explicitement le tarif appliqué à un véhicule E85 en 2024, en regard du tarif réduit qui s'appliquerait à compter du 01/01/2025.

**Décision retenue** : produire deux exemples chiffrés contrastés (2024 vs 2025 simulé), pour rendre la non-application 2024 claire et auditable.

### 4.1 Exemple — Véhicule mono-carburant E85 en 2024

Hypothèses :
- Véhicule : Ford Kuga FlexFuel mono-carburant E85, M1, première immatriculation en France 15/04/2023, taux d'émission CO₂ WLTP = 130 g/km, puissance administrative = 9 CV.
- Année d'imposition : 2024.
- Année bissextile : 366 jours.
- Affectation à une entreprise utilisatrice : 200 jours sur l'année.

**Vérification d'éligibilité aux dispositifs 2024** :
- Exonération électrique/hydrogène (L. 421-124) : carburant n'est pas exclusivement électrique/hydrogène → **NON éligible**.
- Exonération hybride conditionnelle (L. 421-125 version 2024) : la combinaison « E85 seul » n'est pas dans les combinaisons (a) ou (b) (qui exigent deux sources d'énergie en combinaison, dont l'E85 peut être l'une) → **NON éligible**.
- Abattement E85 : **n'existe pas en 2024** (cf. Décisions 1 et 2 ci-dessus) → **NON applicable**.
- Conclusion : **plein tarif** selon le barème WLTP 2024.

**Calcul du tarif annuel plein** (barème WLTP 2024, cf. `2024/taxe-co2/decisions.md` Décision 4) :

| Tranche | s_inf (excl) | s_sup (incl) | Tarif (€/g) | Fraction (g/km) | Contribution (€) |
|---|---|---|---|---|---|
| 1 | 0 | 14 | 0 | 14 | 0 |
| 2 | 14 | 55 | 1 | 41 | 41 |
| 3 | 55 | 63 | 2 | 8 | 16 |
| 4 | 63 | 95 | 3 | 32 | 96 |
| 5 | 95 | 115 | 4 | 20 | 80 |
| 6 | 115 | 135 | 10 | 15 | 150 |
| **Total** | | | | | **383 €** |

(Le calcul reproduit exactement la mécanique illustrée dans `2024/taxe-co2/decisions.md` Décision 4 pour 130 g/km : 383 € de tarif annuel plein.)

**Calcul du montant taxe CO₂ après prorata** :

Tarif annuel plein × Prorata = 383 × 200 / 366 = 209,2896… ≈ **209,29 €** (en valeur exacte conservée jusqu'à l'arrondi final, conformément à `2024/taxe-co2/decisions.md` Décision 2).

**Calcul du montant taxe polluants** (essence Euro 6 = catégorie 1 — voir `2024/taxe-polluants/decisions.md` ; l'E85 est une essence à allumage commandé) :

Tarif forfaitaire 100 € × Prorata = 100 × 200 / 366 = 54,6448… ≈ **54,64 €**.

**Total dû par l'entreprise utilisatrice au titre de ce véhicule en 2024** : 209,29 + 54,64 = **263,93 €** (avant arrondi final au total par redevable).

### 4.2 Exemple comparatif simulé — Mêmes hypothèses, exercice 2025 (avec abattement E85 applicable)

À titre d'illustration de la bascule au 01/01/2025 (qui sera précisément instruite dans `2025/abattements/`) :

- Véhicule identique : Ford Kuga FlexFuel mono-carburant E85, M1, taux d'émission CO₂ WLTP = 130 g/km.
- Vérification de l'éligibilité à l'abattement E85 (version 2025 de L. 421-125) : carburant inclut E85 ✓, taux CO₂ ≤ 250 g/km ✓ → **abattement applicable**.
- Taux CO₂ effectif après abattement de 40 % : 130 × 0,60 = **78 g/km**.
- Le barème WLTP 2025 (qui sera instruit dans `2025/taxe-co2/`) serait alors appliqué sur 78 g/km au lieu de 130 g/km.

**Différence** : en 2024, la taxe CO₂ est calculée sur 130 g/km (plein tarif) ; à compter de 2025, elle serait calculée sur 78 g/km (assiette réduite). C'est précisément la mécanique « abattement » telle que définie par la méthodologie projet (modification d'une caractéristique d'entrée d'une règle de tarification — Annexe C.2).

### 4.3 Conclusion de l'exemple

Le contraste chiffré rend la non-application en 2024 explicite : un véhicule E85 affecté en 2024 paie le **plein tarif WLTP** (calculé sur la valeur de CO₂ non réduite), alors que le même véhicule à compter de 2025 paiera un tarif **réduit** (calculé sur 60 % de la valeur CO₂). Cette différence est entièrement imputable à la révision de l'article L. 421-125 du CIBS au 01/01/2025.

**Niveau de confiance** : **Haute** (calculs reproduits à partir des barèmes officiels, cohérents avec les exemples du BOFiP § 230 utilisés dans `2024/taxe-co2/`).

**À valider par expert-comptable** : Non (calcul illustratif, sans risque fiscal — il ne fait que reproduire la mécanique des barèmes 2024 déjà validés).

**Conséquences sur l'implémentation** :
- L'exemple chiffré peut être utilisé comme **test unitaire** pour confirmer que Floty applique bien le plein tarif aux véhicules E85 en 2024, sans tentative d'abattement.
- Pour 2025, un exemple symétrique sera produit dans `2025/abattements/` confirmant l'application de l'abattement E85.

---

## Décision 5 — Sources primaires retenues pour la traçabilité de la conclusion 2024

**Contexte** : la méthodologie projet (§ 4.1) impose que toute conclusion fiscale soit tracée à des sources primaires identifiées. Pour la conclusion principale « aucun abattement isolé applicable en 2024 », la traçabilité repose sur la **non-existence** d'une disposition (preuve par lecture exhaustive). Il faut formaliser quelles sources primaires ont été lues exhaustivement pour étayer cette conclusion par défaut.

**Décision retenue** : trois sources primaires concordantes ont été lues intégralement et constituent la base de la conclusion :

1. **Texte de loi — Code des Impositions sur les Biens et Services (CIBS), Section 3 « Taxes sur l'affectation des véhicules à des fins économiques »** (articles L. 421-93 à L. 421-167), dans leur version applicable au 31 décembre 2023 (modifiée par la loi n° 2023-1322 du 29 décembre 2023, art. 97 — loi de finances pour 2024). Articles particulièrement consultés et utilisés dans cette mission : L. 421-105 à L. 421-118 (dispositions communes, dont L. 421-110 coefficient pondérateur et L. 421-111 minoration 15 000 €) ; L. 421-119 à L. 421-122 (tarifs CO₂) ; L. 421-123 à L. 421-132 (exonérations CO₂) ; L. 421-133 à L. 421-135 (tarifs polluants) ; L. 421-136 à L. 421-144 (exonérations polluants).

2. **Doctrine officielle — Bulletin Officiel des Finances Publiques (BOFiP-Impôts)** :
   - `BOI-AIS-MOB-10-30-20-20240710` (taxes d'affectation des véhicules de tourisme — version applicable à l'exercice 2024) — sections « Exonérations » (§§ 90 à 200), « Tarifs CO₂ » (§§ 210 à 230), « Taxe polluants » (§§ 260 à 290) et « Minoration 15 000 € » (§§ 30 à 50).
   - `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes aux taxes d'affectation).
   - `BOI-AIS-MOB-10-30-20` (version postérieure à 2024) consultée pour vérifier la date d'apparition explicite de l'abattement E85 dans la doctrine — § 240 confirme « à compter du 1er janvier 2025 ».

3. **Notices administratives DGFiP** :
   - Notice n° 2857-FC-NOT-SD (Cerfa 52374#03, édition décembre 2024) — pour la taxe CO₂.
   - Notice n° 2858-FC-NOT-SD (Cerfa 52375#03, édition décembre 2024) — pour la taxe polluants.

**Justification** :
- Cette triangulation garantit que la conclusion par défaut (« aucun abattement isolé en 2024 ») repose sur l'absence vérifiée de toute disposition applicable, et non sur une seule lecture lacunaire.
- La preuve par défaut est ici renforcée par une preuve positive : l'apparition future de l'abattement E85 « à compter du 1er janvier 2025 » est documentée par la doctrine postérieure à 2024 (BOFiP § 240), ce qui corrobore par contraste la non-existence en 2024.
- Les sources tertiaires (PwC, FNA, Drive to Business, Compta-Online, Legifiscal) ont été consultées pour vérifier qu'aucune source professionnelle ne contredit la lecture des sources primaires.

**Niveau de confiance** : **Haute** (triangulation primaire complète + corroboration par la doctrine postérieure à 2024 qui date l'apparition de l'abattement au 01/01/2025).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Les références S1 (CIBS), S2 et S3 (BOFiP versions 2024), S4 (BOFiP postérieur 2024 § 240), S5 et S6 (notices DGFiP) de `sources.md` sont les sources de vérité pour la conclusion d'absence d'abattement isolé en 2024.
- Si une mise à jour ultérieure du BOFiP venait à introduire rétroactivement une doctrine sur un abattement applicable en 2024 (cas peu probable mais théoriquement possible), la présente conclusion serait à réviser. À ce jour (23/04/2026), aucune indication dans ce sens.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 23/04/2026 | Micha MEGRET | Décisions initiales abattements 2024 — 5 décisions documentées : (1) périmètre Floty V1 — aucun abattement actif, minoration 15 000 € modélisée mais inactive ; (2) clôture de l'incertitude Z-2024-003 (« Abattement E85 en 2024 ») — l'abattement E85 isolé n'est pas applicable en 2024 ; (3) correction proposée pour la cartographie phase 0 § 7 (mention erronée à supprimer) ; (4) exemple chiffré illustrant la non-application 2024 (véhicule E85 → plein tarif WLTP) avec exemple comparatif simulé 2025 ; (5) sources primaires retenues. Niveau de confiance Haute pour les 5 décisions (triangulation primaire complète, lecture univoque des sources). |
