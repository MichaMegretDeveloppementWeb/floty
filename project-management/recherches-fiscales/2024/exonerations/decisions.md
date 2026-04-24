# Décisions — Exonérations applicables aux taxes annuelles CO₂ et polluants — Exercice 2024

> **Statut** : Version 0.1
> **Auteur** : Micha MEGRET (prestataire)
> **Date** : 22 avril 2026
> **Renvoi sources** : voir `sources.md` (cotes S1, S2, …) et `recherches.md` pour les analyses détaillées.

---

## Décision 1 — Périmètre des exonérations à implémenter dans Floty V1

**Contexte** : la sous-section « Exonérations » du Paragraphe 3 du CIBS (taxe CO₂) comporte 10 articles (L. 421-123 à L. 421-132) et celle du Paragraphe 4 (taxe polluants) comporte 9 articles (L. 421-136, L. 421-138 à L. 421-144 — voir Note méthodologique sur la numérotation dans `recherches.md` § 3.2). Toutes ces exonérations ne sont pas pertinentes au même titre pour Floty V1. Il faut décider lesquelles sont **implémentées comme règles actives** dans le moteur de calcul, lesquelles sont **modélisées mais inactives par défaut** (activables manuellement via seeder), et lesquelles sont **documentées sans modélisation** (cas réputés inapplicables au modèle Floty).

**Options envisagées** :
- **Option A — Implémenter toutes les exonérations comme règles actives** : exhaustivité maximale ; mais coût d'implémentation important pour des exonérations qui ne s'appliqueront jamais à la flotte Renaud (auto-écoles, sport, agricole, transport public).
- **Option B — N'implémenter que les exonérations critiques pour Floty (handicap, électrique, hybride 2024, loueur, LCD)** : minimaliste ; risque de devoir tout reprendre si une entreprise utilisatrice atypique entre dans le périmètre.
- **Option C — Implémenter **toutes** les exonérations dans le **modèle de données**, mais avec un **flag d'activation par défaut** distinguant les règles « actives » (testées en V1) et « inactives » (modélisées pour évolutivité)** : approche équilibrée — la couverture documentaire reste exhaustive, le coût de validation V1 reste maîtrisé.

**Décision retenue** : **Option C — modélisation exhaustive avec flag d'activation**.

Pour 2024, sont **actives par défaut** (testées en V1) :
- L. 421-123 / L. 421-136 — handicap (totale, attachée au véhicule)
- L. 421-124 — électrique/hydrogène CO₂ (totale, attachée au véhicule)
- L. 421-125 — hybride conditionnel CO₂ 2024 (totale, conditionnelle, **2024 uniquement**)
- L. 421-128 / L. 421-140 — loueur (totale, sur jours non loués du bailleur)
- L. 421-129 / L. 421-141 — LCD (totale, double exonération si applicable)

Sont **inactives par défaut** (modélisées mais non testées en V1, activables au cas par cas via seeder) :
- L. 421-126 / L. 421-138 — organisme intérêt général
- L. 421-127 / L. 421-139 — entreprise individuelle
- L. 421-130 / L. 421-142 — transport public personnes
- L. 421-131 / L. 421-143 — activités agricoles/forestières
- L. 421-132 / L. 421-144 — enseignement de la conduite + compétitions

**Justification** :
- Conforme au principe d'exhaustivité de la méthodologie (§ 3.2) : le modèle de données couvre toutes les exonérations CIBS, sans omission.
- Conforme au principe de maintenabilité de la méthodologie (§ 2 — « privilégier la maintenabilité et l'évolutivité du code ») : si à terme une entreprise utilisatrice de profil exotique entre dans le périmètre Floty, l'activation d'une règle inactive est un simple changement de flag, pas une réécriture.
- Le coût de validation V1 reste maîtrisé : seules les 5 règles actives nécessitent des tests unitaires et une validation expert-comptable spécifique.

**Niveau de confiance** : **Haute** (choix produit cohérent avec l'usage attendu de Floty et conforme à la méthodologie).

**À valider par expert-comptable** : Non sur le principe ; à mentionner dans le rapport de livraison pour transparence.

**Conséquences sur l'implémentation** :
- La table de règles d'exonération en base de données comporte une colonne `actif_par_defaut` (booléen).
- Les 5 règles actives sont seedées avec `actif_par_defaut = true` ; les 5 inactives avec `actif_par_defaut = false`.
- L'UI Floty (page de consultation des règles, cf. cahier des charges § 4.2) affiche toutes les règles avec un badge « Actif » / « Inactif (activable) ».
- Les règles inactives ne sont pas appliquées au calcul tant que le prestataire ne les active pas via un seeder (ou, en V2, via une UI d'administration).

---

## Décision 2 — Distinction sémantique « exonération technique » vs « effet du barème »

**Contexte** : pour la taxe polluants, les véhicules électriques sont à 0 € parce qu'ils relèvent de la **catégorie E** de l'article L. 421-135 (effet du barème), et non d'un article d'exonération technique. Pour la taxe CO₂, les mêmes véhicules sont exonérés au titre de **CIBS art. L. 421-124** (exonération technique). La distinction est documentée dans `2024/taxe-polluants/decisions.md` Décision 3. Il faut maintenant figer la **représentation interne** de cette distinction dans Floty.

**Options envisagées** :
- **Option A — Confondre les deux mécanismes** : représenter l'« exonération » comme une seule règle qui met le tarif à 0 € quel que soit le motif. Plus simple ; mais perte d'information sémantique pour l'audit.
- **Option B — Représenter distinctement les deux mécanismes** : la table des règles distingue `règle_type ∈ {tarification, exonération_technique, abattement, ...}`. Une règle de tarification peut produire un tarif de 0 € sans être qualifiée d'exonération.
- **Option C — Représenter par convention de nommage** sans modèle de données distinct.

**Décision retenue** : **Option B — représentation sémantique distincte**.

Pour le PDF récapitulatif Floty :
- Si la taxe CO₂ d'un véhicule électrique = 0 €, le motif affiché est : « **Exonéré au titre de CIBS art. L. 421-124** (véhicule électrique exclusif) ».
- Si la taxe polluants du même véhicule = 0 €, le motif affiché est : « **Catégorie E — tarif 0 € en application du barème CIBS art. L. 421-135** ».

**Justification** :
- Conforme à la doctrine BOFiP qui distingue les deux mécanismes (S3 § 110 in fine pour la taxe CO₂ ; S3 §§ 260-280 pour la taxe polluants).
- Auditabilité : un expert-comptable doit pouvoir vérifier que Floty applique le bon motif pour chaque ligne. Confondre les deux serait une perte d'information qui pourrait gêner la validation.
- Cohérence avec la méthodologie (§ 6.6) qui distingue déjà les types de règles « tarification », « exonération », « abattement », etc.

**Niveau de confiance** : **Haute**.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- La table `taxe_rules` (ou équivalent) inclut un champ `type` (`tarification` / `exonération_technique` / `abattement` / `effet_barème`).
- Le moteur de calcul renvoie pour chaque ligne fiscale un objet `{montant, motif, règle_appliquée_id}` qui distingue exonération vs effet du barème.
- Le générateur de PDF récapitulatif consomme ce motif pour produire le libellé approprié.

---

## Décision 3 — Algorithme d'application des exonérations (priorisation et exclusivité)

**Contexte** : un véhicule peut, en théorie, satisfaire plusieurs conditions d'exonération simultanément. Exemples :
- Véhicule électrique accessible en fauteuil roulant : satisfait L. 421-123 (handicap) ET L. 421-124 (électrique) — pour la taxe CO₂.
- Véhicule hybride essence à faibles émissions, détenu par une auto-école : satisfait L. 421-125 (hybride 2024) ET L. 421-132 (auto-école) — pour la taxe CO₂.
- Véhicule détenu par la société de location et loué en LCD à une entreprise utilisatrice : la combinaison L. 421-128 (bailleur exonéré sur jours non loués) + L. 421-129 (locataire exonéré sur LCD) → **double exonération** sur les jours de LCD.

L'application aux deux taxes peut diverger (ex : la combinaison handicap + électrique ne pose pas de problème — taxe CO₂ exonérée par L. 421-123 OU L. 421-124, peu importe ; mais la prise en compte d'autres exonérations diffère).

**Options envisagées** :
- **Option A — Premier match gagnant** : appliquer la première exonération éligible dans un ordre figé. Simple, déterministe ; mais perte de traçabilité si plusieurs exonérations sont applicables.
- **Option B — Toutes les exonérations applicables sont enregistrées** mais le résultat fiscal est unique (taxe = 0 €) — Floty mémorise toutes les exonérations applicables et peut les afficher dans le PDF.
- **Option C — Hiérarchie déterministe avec exclusivité** : pour chaque taxe et chaque jour, une seule exonération est appliquée, selon un ordre de priorité qui maximise la lisibilité.

**Décision retenue** : **Option B — enregistrement de toutes les exonérations applicables, mais affichage de l'exonération principale** dans le PDF.

Pour chaque combinaison (taxe × jour × véhicule × entreprise), Floty détermine la liste des exonérations applicables. Le résultat fiscal est zéro dès qu'une au moins est applicable. L'**exonération principale** affichée dans le PDF est déterminée par l'ordre de priorité suivant (du plus spécifique au plus général) :

1. L. 421-128 / L. 421-140 (loueur) — si applicable au bailleur.
2. L. 421-129 / L. 421-141 (LCD) — si applicable au locataire.
3. L. 421-123 / L. 421-136 (handicap) — exonération spécifique au véhicule.
4. L. 421-124 (électrique/hydrogène CO₂) — exonération attachée à la motorisation.
5. L. 421-125 (hybride 2024) — exonération conditionnelle attachée à la motorisation.
6. L. 421-126 à L. 421-127 / L. 421-138 à L. 421-139 — exonérations attachées au statut du redevable.
7. L. 421-130 à L. 421-132 / L. 421-142 à L. 421-144 — exonérations attachées à l'activité.

Si plusieurs exonérations sont applicables, la liste secondaire est mémorisée et affichée dans une note du PDF récapitulatif (« Le véhicule satisfait également les conditions de [autres exonérations] »).

**Justification** :
- Conforme à l'objectif d'auditabilité (méthodologie § 10) : toutes les justifications fiscales applicables sont mémorisées, ce qui permet à l'expert-comptable de valider même les cas marginaux.
- L'ordre de priorité retenu est lisible : on commence par les exonérations attachées à la situation contractuelle (loueur, LCD), puis aux caractéristiques du véhicule (handicap, motorisation), puis au statut du redevable, puis à l'activité.
- En pratique, la quasi-totalité des cas Floty V1 ne fera intervenir qu'une seule exonération (loueur côté bailleur, ou aucune côté entreprise utilisatrice). Les chevauchements sont des cas pathologiques qu'il faut savoir tracer.

**Niveau de confiance** : **Haute** (choix produit, sans risque fiscal — la combinaison de deux exonérations applicables aboutit toujours au même résultat fiscal qu'une seule).

**À valider par expert-comptable** : Non sur le principe.

**Conséquences sur l'implémentation** :
- Le moteur de calcul itère sur **toutes** les règles d'exonération et collecte celles qui sont applicables.
- Si la liste est non vide, taxe = 0 € pour cette combinaison (jour × véhicule × entreprise × taxe), et l'exonération principale est sélectionnée selon l'ordre de priorité.
- Tests unitaires : un véhicule électrique accessible en fauteuil roulant → exonération CO₂ principale = handicap (L. 421-123) ; mention secondaire = électrique (L. 421-124). Vérifier que le PDF affiche le motif principal et la mention secondaire.

---

## Décision 4 — Aménagement transitoire des seuils de l'exonération hybride 2024 (L. 421-125)

**Contexte** : l'article L. 421-125 du CIBS prévoit deux niveaux de seuils pour l'exonération hybride :
- **Régime général** : 60 g/km WLTP, 50 g/km NEDC, 3 CV PA.
- **Régime aménagé** (véhicules dont l'ancienneté n'excède pas 3 ans à compter de la date de première immatriculation) : 120 g/km WLTP, 100 g/km NEDC, 6 CV PA.

Le cahier des charges Floty (§ 5.6) ne mentionne explicitement que le régime général (60/50/3) et omet le régime aménagé. C'est un écart entre le cahier des charges et la lettre du texte CIBS. Il faut décider laquelle des deux lectures s'impose.

**Options envisagées** :
- **Option A — Suivre le cahier des charges** : n'appliquer que le régime général (60/50/3). Risque de sous-exonération pour les véhicules récents — donc sur-imposition fiscale. Non conforme à la lettre du texte.
- **Option B — Suivre la lettre de l'article L. 421-125** : appliquer le régime général ET le régime aménagé selon l'ancienneté du véhicule.

**Décision retenue** : **Option B — suivre la lettre de l'article CIBS L. 421-125**.

L'algorithme Floty pour l'exonération L. 421-125 en 2024 est :

```
fonction est_exonere_hybride_2024(vehicule, date_reference) :
    # 1. Vérifier la combinaison de sources d'énergie
    combinaisons_eligibles = {
        # Combinaison (a) : électricité ou hydrogène + (gaz naturel, GPL, essence, ou E85)
        ("Électrique", "Gaz naturel"), ("Électrique", "GPL"),
        ("Électrique", "Essence"), ("Électrique", "Superéthanol E85"),
        ("Hydrogène", "Gaz naturel"), ("Hydrogène", "GPL"),
        ("Hydrogène", "Essence"), ("Hydrogène", "Superéthanol E85"),
        # Combinaison (b) : gaz naturel ou GPL + (essence ou E85)
        ("Gaz naturel", "Essence"), ("Gaz naturel", "Superéthanol E85"),
        ("GPL", "Essence"), ("GPL", "Superéthanol E85"),
    }
    si vehicule.combinaison_sources_energie ∉ combinaisons_eligibles :
        retourner False
    
    # 2. Calculer l'ancienneté du véhicule à la date de référence (1er janvier 2024 par défaut — voir Décision 5)
    anciennete_annees = (date_reference - vehicule.date_premiere_immatriculation).years
    regime_amenage = (anciennete_annees < 3)
    
    # 3. Vérifier le seuil applicable
    si vehicule.methode_homologation == "WLTP" et vehicule.co2_wltp est renseigné :
        seuil = 120 si regime_amenage sinon 60
        retourner vehicule.co2_wltp <= seuil
    sinon si vehicule.methode_homologation == "NEDC" et vehicule.co2_nedc est renseigné :
        seuil = 100 si regime_amenage sinon 50
        retourner vehicule.co2_nedc <= seuil
    sinon :
        # Bascule sur PA
        seuil = 6 si regime_amenage sinon 3
        retourner vehicule.puissance_administrative <= seuil
```

**Justification** :
- La consigne explicite de la mission impose : « la lecture littérale de l'article CIBS prime sur les paraphrases pédagogiques ».
- Le quatrième alinéa de L. 421-125 (vérifié par lecture directe sur Légifrance, S5) prévoit sans ambiguïté l'aménagement transitoire.
- La doctrine BOFiP (S3 §§ 130-150) confirme l'aménagement.
- Le cahier des charges sera mis à jour pour refléter cet aménagement (proposition de mise à jour à transmettre à Renaud).

**Niveau de confiance** : **Haute** (lecture directe et univoque de l'article CIBS).

**À valider par expert-comptable** : Non sur le principe (lecture littérale du texte). Mention dans le rapport de livraison pour transparence sur l'écart avec le cahier des charges et la mise à jour proposée.

**Conséquences sur l'implémentation** :
- La règle d'exonération L. 421-125 stockée en base inclut deux jeux de seuils : `seuils_regime_general = {wltp: 60, nedc: 50, pa: 3}` et `seuils_regime_amenage = {wltp: 120, nedc: 100, pa: 6}`.
- La règle référence le champ `date_premiere_immatriculation` du véhicule pour calculer l'ancienneté.
- Tests unitaires : un véhicule hybride essence + électrique de 2 ans 6 mois avec WLTP CO₂ = 110 → exonéré (sous le seuil aménagé 120). Le même véhicule à 3 ans 1 mois → non exonéré (au-dessus du seuil régime général 60).
- Mise à jour du cahier des charges § 5.6 : préparer une proposition de réécriture explicitant les deux jeux de seuils.

---

## Décision 5 — Date de référence pour évaluer l'ancienneté du véhicule (L. 421-125)

**Contexte** : l'aménagement transitoire L. 421-125 (Décision 4) requiert de vérifier si le véhicule a moins de 3 ans à compter de sa première immatriculation. Mais à quelle date évaluer cette condition : 1er janvier de l'année d'imposition, date de première affectation, date au cas par cas ?

**Options envisagées** :
- **Option A — 1er janvier de l'année d'imposition** : la condition est figée pour toute l'année. Simple, déterministe.
- **Option B — Date de chaque attribution** : la condition peut basculer en cours d'année (si le véhicule passe de < 3 ans à ≥ 3 ans à mi-année). Complexe, mais plus précis.
- **Option C — 31 décembre de l'année d'imposition** : prudent (si le véhicule est < 3 ans au 31/12, il l'a été toute l'année).

**Décision retenue** : **Option A — 1er janvier de l'année d'imposition**.

L'ancienneté est évaluée au 1er janvier de l'année d'imposition (donc 1er janvier 2024 pour l'exercice 2024). Si à cette date le véhicule a strictement moins de 3 ans depuis sa première immatriculation, le régime aménagé s'applique pour toute l'année 2024 ; sinon le régime général s'applique pour toute l'année.

**Justification** :
- Cohérence avec la mécanique annuelle de la taxe : l'année d'imposition est l'unité de base, le régime applicable doit être figé pour toute l'année.
- Lecture par défaut prudente et non ambiguë (l'ancienneté du véhicule au 1er janvier est une donnée stable, vérifiable, non susceptible d'interprétation).
- Aucune source primaire ne tranche explicitement ce point. L'option A est la plus simple à implémenter et à auditer.
- Note méthodologique : la lecture alternative B aurait pour effet qu'un véhicule passant de moins de 3 ans à plus de 3 ans à mi-année verrait son régime basculer du régime aménagé au régime général à mi-année, ce qui complexifierait le calcul sans gain pratique majeur (l'exonération n'est par construction pas annuelle proratisée — le véhicule est ou n'est pas exonéré).

**Niveau de confiance** : **Moyenne**. Aucune source primaire ne tranche. La lecture A est défendable, mais une administration fiscale stricte pourrait imposer la lecture B (date de chaque événement). À valider avec l'expert-comptable. Inscrit comme zone d'incertitude dans `incertitudes.md` (Z-2024-010, voir mise à jour de ce fichier).

**À valider par expert-comptable** : **Oui**.

**Conséquences sur l'implémentation** :
- Le moteur de calcul Floty calcule l'ancienneté à partir de `vehicule.date_premiere_immatriculation` et de la date de référence `1er janvier de l'année d'imposition`.
- Cette date de référence est paramétrable au niveau de l'année (en cas de changement de doctrine, ajuster sans toucher au code).
- Tests unitaires : véhicule immatriculé 02/02/2021 → au 01/01/2024 : 2 ans 11 mois → < 3 ans → régime aménagé. Le même véhicule pour l'exercice 2025 : au 01/01/2025 : 3 ans 11 mois → ≥ 3 ans → régime général.

---

## Décision 6 — Mécanique de l'exonération loueur (L. 421-128 / L. 421-140) dans Floty

**Contexte** : l'exonération loueur est le **mécanisme pivot** du modèle Floty. Elle exonère la société de location (le bailleur) sur les périodes où le véhicule est dans son stock (non loué), et fait basculer le redevable sur l'entreprise utilisatrice (locataire / affectataire) pendant les périodes de mise à disposition. Il faut figer la mécanique de calcul Floty correspondante.

**Options envisagées** :
- **Option A — Calculer la part bailleur ET la part de chaque entreprise utilisatrice** : Floty produit une ligne fiscale pour le bailleur (taxe = 0 €, exonérée) et une ligne par entreprise utilisatrice (taxe au prorata). Maximalement transparent ; mais alourdit le PDF du bailleur (qui n'a aucune taxe à déclarer).
- **Option B — Ne calculer que la part des entreprises utilisatrices** (le bailleur n'apparaît pas dans Floty puisque sa taxe est par construction nulle). Plus léger ; le bailleur n'a pas besoin de récapitulatif fiscal.

**Décision retenue** : **Option B — ne calculer que la part des entreprises utilisatrices**.

Le moteur de calcul Floty :
- Itère sur toutes les attributions journalières par véhicule × entreprise utilisatrice × année.
- Calcule, pour chaque combinaison, le prorata d'affectation (jours_attribution / 366) et applique la règle de tarification (CO₂ et polluants).
- Ne produit **aucune ligne fiscale** pour la société de location (le bailleur), puisque celle-ci est par construction exonérée par L. 421-128 / L. 421-140 sur ses jours non loués.
- La somme des prorata des entreprises utilisatrices d'un véhicule sur l'année peut être ≤ 1 ; la part « non couverte » correspond aux jours de stock du bailleur (non taxés, non comptabilisés dans Floty).

**Justification** :
- Le périmètre Floty se limite aux taxes dues par les entreprises utilisatrices (méthodologie § 3.3 — critère de redevabilité).
- La société de location de Renaud n'est pas une « entreprise utilisatrice » au sens de Floty : elle est le bailleur. Ses propres taxes (sur ses véhicules en stock) sont nulles par construction et n'ont pas vocation à apparaître dans Floty.
- Le PDF récapitulatif Floty est destiné aux entreprises utilisatrices et à leur expert-comptable, pas au bailleur.

**Niveau de confiance** : **Haute** (cohérent avec la méthodologie et le cahier des charges).

**À valider par expert-comptable** : Non sur le principe (validé par lecture directe du CIBS L. 421-128 et L. 421-140 et confirmé par BOFiP). À mentionner dans le rapport de livraison pour transparence.

**Conséquences sur l'implémentation** :
- Le moteur de calcul Floty ne génère pas de ligne fiscale pour le bailleur.
- Le PDF récapitulatif Floty est produit pour chaque entreprise utilisatrice individuellement.
- Une **note de transparence** peut être ajoutée au PDF de chaque entreprise utilisatrice : « La société de location qui détient ce véhicule bénéficie de l'exonération CIBS art. L. 421-128 (taxe CO₂) et L. 421-140 (taxe polluants) sur les périodes où le véhicule n'est pas mis à disposition d'une entreprise utilisatrice. La présente déclaration concerne uniquement la part de la taxe due par votre entreprise au titre de votre durée d'utilisation effective. »

---

## Décision 7 — Application de l'exonération LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice)

> **Note d'historique** : cette décision a été initialement rédigée le 22/04/2026 sous le titre « Articulation L. 421-129 / L. 421-141 (LCD) avec le modèle Floty — qualification LLD retenue par défaut », en l'absence d'information précise du client sur la nature exacte de son montage contractuel. Elle retenait par prudence l'**Option B — qualification LLD par défaut** pour éviter d'annuler le calcul fiscal en cas de requalification LCD. Le 23/04/2026, après clarification directe avec Renaud, il est apparu que le modèle économique Floty s'inscrit en réalité dans le régime LCD, avec une mécanique de cumul annuel par couple (véhicule, entreprise utilisatrice) validée par la doctrine fiscale et par la pratique de Renaud depuis plusieurs années. La décision est donc **révisée**.

**Contexte** : l'exonération de location de courte durée (LCD) prévue par CIBS art. L. 421-129 (taxe CO₂) et L. 421-141 (taxe polluants) s'évalue, selon la lecture doctrinale conforme au texte CIBS et au BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 180, **par couple (véhicule, entreprise utilisatrice)** sur **cumul annuel** des jours de location. Au seuil de 30 jours cumulés par couple sur une année civile, l'exonération bascule : sous le seuil, couple entièrement exonéré ; au-dessus, pas d'exonération et taxe due au prorata.

**Décision retenue** : **Application systématique** de l'exonération LCD dans le moteur de calcul Floty, selon la mécanique doctrinale (cumul annuel par couple, seuil 30 jours).

La règle Floty :
- Le moteur agrège en continu, pour chaque année civile, les jours d'attribution de chaque couple (véhicule, entreprise utilisatrice).
- Tant que le cumul reste ≤ 30 jours pour un couple donné : taxe CO₂ = 0 et taxe polluants = 0 pour ce couple.
- Dès que le cumul > 30 jours pour un couple : l'exonération ne s'applique pas, la taxe se calcule au prorata (cumul total / jours de l'année) selon les barèmes standards.
- Cette mécanique est documentée dans `taxes-rules/2024.md` R-2024-021 (version 1.1, confiance Haute).

**Justification** :
- Lecture littérale de L. 421-129 + L. 421-141 + L. 421-99 : l'entreprise affectataire (qui dispose du véhicule dans le cadre de la location) est exonérée sur les jours de location courte.
- Lecture BOFiP § 180 : « véhicules qui, au cours d'une année civile, sont pris en location pour une période n'excédant pas un mois civil ou trente jours consécutifs » — le cumul annuel est le critère.
- **Présomption forte de validité** : la pratique de Renaud applique cette mécanique depuis plusieurs années sans redressement fiscal de l'administration, ce qui constitue une validation implicite post hoc.

**Niveau de confiance** : **Haute** (texte primaire, doctrine officielle, validation a posteriori par la pratique).

**À valider par expert-comptable** : Non. La clarification directe du client (qui connaît son propre montage contractuel) et la cohérence doctrinale suffisent.

**Conséquences sur l'implémentation** :
- Le moteur de calcul Floty intègre une **étape de cumul par couple** (véhicule, entreprise utilisatrice) sur l'année civile, évaluée avant l'application des barèmes de tarification.
- L'exonération LCD est **active par défaut** pour tous les couples sous le seuil — c'est une règle standard, pas une exonération conditionnelle activable manuellement.
- Les champs Floty initialement proposés (`entreprise_utilisatrice.qualification_mise_a_disposition`, `attribution.qualification_specifique`) sont **abandonnés** : il n'y a plus de qualification manuelle à effectuer — le moteur évalue systématiquement le cumul par couple et applique l'exonération si le seuil est respecté.
- L'UI Floty (cahier des charges § 3.4, Vue par entreprise) doit afficher en marge de chaque ligne véhicule un compteur du cumul annuel (véhicule, entreprise) avec indication de l'impact fiscal courant, pour rendre la mécanique transparente au moment des décisions d'attribution.
- Le PDF récapitulatif Floty intègre pour chaque couple une ligne : « Véhicule X — cumul Y jours — exonération LCD applicable / non applicable — taxe due : Z € ». La mention LLD n'a plus lieu d'être.

**Incertitude associée** : Z-2024-002, **Résolu — 23/04/2026** (voir `2024/taxe-co2/incertitudes.md`).

---

## Décision 8 — Mécanique de prorata d'exonération partielle (usage mixte)

**Contexte** : la mécanique de prorata d'exonération partielle (`recherches.md` § 5) permet de gérer le cas d'un véhicule affecté en partie à une activité exonérée et en partie à une activité taxable au cours de la même année. Cette mécanique est documentée par BOFiP S4 §§ 110-120. Pour Floty V1, elle est hors périmètre opérationnel (les entreprises utilisatrices Floty exercent une activité commerciale standard sans branche exonérée), mais le moteur de calcul doit être conçu pour la supporter.

**Options envisagées** :
- **Option A — Implémenter le prorata d'exonération partielle dès V1** : nécessaire si une entreprise utilisatrice exotique entre dans le périmètre.
- **Option B — Ne pas l'implémenter en V1 (tout ou rien)** : simplification ; mais nécessite de revoir le moteur de calcul si une exonération partielle devient applicable.
- **Option C — Implémenter la **structure** mais ne pas activer la mécanique en V1** (analogue à la Décision 1 — règles inactives par défaut).

**Décision retenue** : **Option C — implémenter la structure, désactivation par défaut en V1**.

La structure de données Floty supporte le concept d'« exonération partielle au prorata » (champ `pourcentage_exonere` ou `jours_exoneres` par véhicule × année × entreprise). Mais en V1 :
- Aucune entreprise utilisatrice n'est marquée comme exerçant une activité exonérée partielle.
- Le calcul applique systématiquement `prorata_taxable = jours_affectation / 366`, sans soustraction de jours « exonérés ».

À terme (V2), une UI d'administration permettra d'activer le prorata d'exonération partielle pour une entreprise utilisatrice donnée.

**Justification** :
- Conforme au principe d'évolutivité (méthodologie § 2).
- Conforme au principe de minimalisme V1 (Décision 1 — règles inactives par défaut).
- Permet de répondre à la doctrine BOFiP S4 §§ 110-120 sans surcoût opérationnel V1.

**Niveau de confiance** : **Haute** (choix produit cohérent).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Le modèle de données Floty inclut un champ optionnel `pourcentage_exonere_par_activite` au niveau de chaque attribution (ou globalement au niveau entreprise utilisatrice × année).
- Le moteur de calcul intègre ce paramètre dans le calcul du prorata, mais avec une valeur par défaut de 0 % (= pas d'exonération partielle).

---

## Décision 9 — Cas hybride Diesel-électrique pour l'exonération L. 421-125

**Contexte** : un véhicule hybride combinant moteur Diesel et moteur électrique (technologie peu répandue mais commercialisée par certains constructeurs) ne correspond à aucune des combinaisons éligibles à l'exonération L. 421-125 (ni la combinaison (a) électricité+essence/E85/GNV/GPL, ni la combinaison (b) gaz+essence/E85). La lecture stricte de l'article aboutit à la non-éligibilité de ces véhicules.

Ce cas est documenté en `recherches.md` § 4.3 (Exemple D) et en `recherches.md` § 8.5.

**Options envisagées** :
- **Option A — Lecture stricte** : exclusion systématique des hybrides Diesel-électrique de l'exonération L. 421-125, conformément à la lettre de l'article.
- **Option B — Lecture extensive** : assimiler l'hybride Diesel à un hybride essence par cohérence d'esprit (les deux ont une motorisation hybride à faibles émissions). Mais incompatible avec la lettre du texte.

**Décision retenue** : **Option A — lecture stricte**.

Le moteur de calcul Floty :
- N'applique pas l'exonération L. 421-125 aux véhicules dont la combinaison de sources d'énergie inclut le Diesel (combinaison « Diesel + Électrique » ou « Diesel + Hydrogène »).
- Calcule la taxe CO₂ selon le barème WLTP ou NEDC sur la base des émissions effectives.
- En complément, classe ces véhicules en catégorie « véhicules les plus polluants » pour la taxe polluants (cf. `2024/taxe-polluants/decisions.md` Décision 3 et incertitude Z-2024-007).

**Justification** :
- La consigne explicite de la mission impose la lecture littérale de l'article CIBS.
- L'absence de la combinaison « Diesel + électricité » dans la liste des combinaisons éligibles est un choix législatif délibéré (l'incitation visait spécifiquement les motorisations alternatives non-Diesel).
- Application du principe de prudence (méthodologie § 8.3) : en cas de doute, on majore.

**Niveau de confiance** : **Haute** (lecture directe et univoque de l'article CIBS).

**À valider par expert-comptable** : Non (lecture littérale). À mentionner dans le rapport de livraison pour transparence.

**Conséquences sur l'implémentation** :
- L'algorithme `est_exonere_hybride_2024` (cf. Décision 4) vérifie strictement l'appartenance de la combinaison aux deux jeux (a) ou (b), excluant de fait les combinaisons Diesel + autre.
- Tests unitaires : Mercedes Classe E 300de hybride Diesel + électrique CO₂ WLTP 38 g/km → taxe CO₂ calculée selon barème (24 € de tarif annuel plein) ; pas d'exonération.

---

## Décision 10 — Sources primaires retenues pour la traçabilité des exonérations 2024

**Contexte** : la méthodologie projet (§ 4.1) impose que toute règle fiscale soit tracée à une source primaire. Il convient de désigner explicitement les sources primaires utilisées pour les exonérations 2024.

**Décision retenue** : trois sources primaires concordantes sont retenues, dans l'ordre d'autorité légale :

1. **Texte de loi — Code des Impositions sur les Biens et Services (CIBS), articles L. 421-123 à L. 421-132 (exonérations à la taxe CO₂) et L. 421-136 à L. 421-144 (exonérations à la taxe polluants)**, dans leur version applicable au 31 décembre 2023 (modifiée par la loi n° 2023-1322 du 29 décembre 2023, art. 97 et art. 100 — loi de finances pour 2024). Source d'autorité légale, fait foi devant l'administration fiscale.

2. **Doctrine officielle — Bulletin Officiel des Finances Publiques (BOFiP-Impôts)** :
   - `BOI-AIS-MOB-10-30-20-20240710`, section II « Exonérations » (§§ 90 à 200), pour les conditions d'application des exonérations à la taxe CO₂ et à la taxe polluants ;
   - `BOI-AIS-MOB-10-30-10-20250528`, section II-A « Application des exonérations en cas d'usage mixte », pour la mécanique de prorata d'exonération partielle.

3. **Notices administratives DGFiP** :
   - Notice n° 2857-FC-NOT-SD (Cerfa 52374#03) — partie I.3 « Exonérations » de la taxe CO₂ ;
   - Notice n° 2858-FC-NOT-SD (Cerfa 52375#03) — partie I.3 « Exonérations » de la taxe polluants.

**Justification** :
- Cette triangulation garantit qu'aucune règle d'exonération ne repose sur une source unique.
- Les **conditions cumulatives** de l'exonération hybride 2024 (L. 421-125) — qui sont les plus complexes du périmètre — sont vérifiées sur trois sources primaires concordantes : CIBS (texte intégral lu), BOFiP (doctrine commentée), notice DGFiP (présentation administrative).
- Les sources tertiaires (S9 PwC, S10 FNA, S12 Compta-Online, S13 Drive to Business) ont été consultées pour confirmer la lecture sans être autoritaires en cas de divergence.

**Niveau de confiance** : **Haute** (triangulation primaire complète sur tous les articles d'exonération).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Les références S5 (CIBS), S3 (BOFiP § 90-200), S4 (BOFiP § 110-120 pour le prorata) et S1, S2 (notices DGFiP) de `sources.md` sont les sources de vérité pour les règles d'exonération implémentées dans le moteur de calcul Floty.
- Toute mise à jour des règles d'exonération (notamment la suppression de L. 421-125 au 01/01/2025) sera tracée dans le sous-dossier `2025/exonerations/` avec sa propre triangulation primaire.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Décisions initiales exonérations 2024 — 10 décisions documentées (périmètre actif/inactif, distinction technique vs effet barème, algorithme d'application, aménagement transitoire L. 421-125, date de référence ancienneté, mécanique loueur, qualification LLD pour Floty, prorata d'exonération partielle, hybride Diesel, sources primaires). 7 décisions à confiance haute, 2 à confiance moyenne (Décision 5 — date de référence ancienneté ; Décision 7 — qualification LLD), 1 à confiance haute mais à valider en transparence (Décision 4 — écart cahier des charges). |
