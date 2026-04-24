# Zones grises et points à valider — Cas particuliers et règles transitoires applicables aux taxes annuelles (CO₂ et polluants) — Exercice 2024

> Ce document détaille les incertitudes, zones grises et décisions à confiance basse ou moyenne identifiées au cours de l'instruction des cas particuliers pour 2024. Il alimente le fichier transverse `recherches-fiscales/incertitudes.md` qui en présente la synthèse.
>
> **Auteur** : Micha MEGRET (prestataire)
> **Convention de numérotation** : `Z-AAAA-NNN` où `AAAA` = année fiscale concernée, `NNN` = numéro séquentiel par année.
>
> **Note importante** : ce sous-dossier `cas-particuliers/` est le dernier instruit pour l'exercice 2024. Sa fonction principale est de **clore** (ou consolider sans clôturer) plusieurs incertitudes ouvertes par les sous-dossiers précédents (`2024/taxe-co2/`, `2024/taxe-polluants/`, `2024/exonerations/`). En conséquence, ce fichier contient principalement des **renvois de clôture** (pour les incertitudes que cette mission a définitivement tranchées) et des **renvois de consolidation** (pour celles qui restent ouvertes mais dont la règle d'implémentation Floty est désormais documentée).

---

## Z-2024-001 — Indisponibilités longues hors fourrière (renvoi de consolidation)

L'instruction des cas particuliers 2024 a consolidé cette incertitude qui avait été initialement ouverte lors de l'instruction de la taxe CO₂. Le détail consolidé est tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-001), avec mention explicite des éléments apportés par l'instruction des cas particuliers (formalisation de la règle Floty en algorithme, garde-fou UI, lien avec le champ `indisponibilite.type` du cahier des charges § 2.5).

Pour rappel synthétique :

- **Notre choix actuel** : seul le type d'indisponibilité « Fourrière / immobilisation administrative » réduit le numérateur du prorata fiscal. Tous les autres types (Maintenance, CT, Sinistre, Autre) ne réduisent pas le numérateur. Conforme au principe de prudence (BOFiP S5 § 190 cite uniquement la fourrière comme cause de réduction).
- **Action attendue** : Validation expert-comptable. Question précise : « Pour Floty, faut-il assimiler à la fourrière les indisponibilités longues pour sinistre privé majeur (immobilisation > 30 jours) du point de vue du prorata fiscal ? »
- **Statut maintenu** : **Ouvert** — désormais documenté en règle d'implémentation Floty dans `2024/cas-particuliers/recherches.md` § 8 et `2024/cas-particuliers/decisions.md` Décision 5.

---

## Z-2024-002 — Qualification du modèle Floty au regard de l'exonération LCD (renvoi de clôture)

Cette incertitude était traitée dans le présent sous-dossier comme « consolidée — priorité haute », sur la base de l'hypothèse de travail « LLD par défaut ». Le 23/04/2026, après clarification directe avec Renaud sur la nature exacte du montage contractuel, l'incertitude est passée au statut **Résolu**. La lecture définitive est désormais **« LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice) »**, conforme à L. 421-129 + L. 421-141 + BOFiP § 180 et à la pratique de Renaud (sans redressement fiscal). Le détail consolidé est tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-002).

**Conséquence pour l'implémentation Floty** : la mécanique de cumul par couple est intégrée comme règle fiscale standard (R-2024-021 dans `taxes-rules/2024.md`). Une exigence UI accompagne cette règle (compteur cumulé jours / impact fiscal estimé par cellule de la heatmap entreprise) pour rendre l'effet de l'exonération visible au moment des décisions d'attribution. Cette exigence sera intégrée à la phase de spécification du périmètre MVP.

La Décision 5 du présent sous-dossier (« Z-2024-001 et Z-2024-002 — maintien des incertitudes ouvertes + consolidation des règles d'implémentation ») reste valable pour Z-2024-001 mais est partiellement **dépassée** pour Z-2024-002, désormais résolue. Les champs Floty proposés (`entreprise_utilisatrice.qualification_mise_a_disposition`, `attribution.qualification_specifique`) ne sont plus pertinents et seront retirés de la spécification — la qualification LCD est désormais appliquée systématiquement avec mécanisme de cumul automatique.

---

## Z-2024-004 — Véhicule importé d'occasion (renvoi de clôture)

L'instruction des cas particuliers 2024 a clos cette incertitude qui avait été initialement ouverte lors de l'instruction de la taxe CO₂. Le détail consolidé est tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-004), où le statut a été passé à « **Résolu** » le 23/04/2026 avec résumé de l'issue.

**Résumé synthétique de la résolution** :

- La lecture par défaut documentée dans Z-2024-004 (suivre la **méthode d'homologation effective**, donc NEDC dans le cas pivot d'un véhicule homologué NEDC à l'étranger en 2018 et immatriculé en France en 2022) est **confirmée** par triangulation primaire complète :
  - **CIBS art. L. 421-119-1** (S2 du présent sous-dossier `sources.md`) — formulation littérale du 1° (« véhicules immatriculés en recourant à la méthode WLTP ») et du 2° (« réception européenne, immatriculés pour la première fois à compter du 1er juin 2004, non affectés à des fins économiques par l'entreprise affectataire avant le 1er janvier 2006 »). Le pivot conceptuel est la **méthode d'homologation**, pas la date France.
  - **BOFiP** `BOI-AIS-MOB-10-30-20` § 210-220 (S4) — confirme la lecture par méthode d'homologation effective.
  - **Notice DGFiP** n° 2857-FC-NOT-SD partie II.1 (S7) — la date du 01/03/2020 est un critère pratique de bascule propre à la France (date à laquelle tous les véhicules neufs immatriculés en France l'étaient en méthode WLTP), non un critère normatif de fond.
- **Implémentation Floty** : algorithme `determiner_bareme` étendu pour utiliser la `date_premiere_immatriculation_origine` (étrangère pour un véhicule importé) dans la condition du 2° de L. 421-119-1, et non la `date_premiere_immatriculation_france`. Voir `2024/cas-particuliers/decisions.md` Décision 2.
- Garde-fou UI pour les véhicules importés : alerte affichant la justification du barème retenu et la date d'origine.
- Test unitaire impératif : véhicule BMW Série 3 NEDC homologué en Allemagne en 2018, immatriculé en France en 2022 → barème NEDC (cf. `recherches.md` § 4.6 cas B.2).

Confiance de la résolution : **Haute** (triangulation primaire complète et univoque).

---

## Z-2024-005 — Frontière fiscale M1 / N1 (renvoi de clôture)

L'instruction des cas particuliers 2024 a clos cette incertitude qui avait été initialement ouverte lors de l'instruction de la taxe CO₂ (et déjà identifiée par la cartographie phase 0 § 6.6). Le détail consolidé est tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-005), où le statut a été passé à « **Résolu** » le 23/04/2026 avec résumé de l'issue.

**Résumé synthétique de la résolution** :

- La règle d'implémentation Floty est entièrement documentée à partir des sources primaires :
  - **CIBS art. L. 421-2** (S1 du présent sous-dossier `sources.md`) — texte intégral consulté : 1° pour les M1, 2°-a pour les pick-ups N1 ≥ 5 places, 2°-b pour les camionnettes N1 ≥ 2 rangs affectées au transport de personnes.
  - **BOFiP** `BOI-AIS-MOB-10-30-20` § 60 (S4) — précise les critères opérationnels (rubriques J.1, J.2, S.1 du certificat d'immatriculation, condition « affecté au transport de personnes » pour les camionnettes).
  - **BOFiP** `BOI-AIS-MOB-10-10` (S6) — confirme la définition transversale du véhicule de tourisme.
  - **Notice DGFiP** n° 2858-FC-NOT-SD partie I.2.a (S8) — reproduction administrative du champ d'application.
- **Implémentation Floty** : algorithme `qualifier_type_fiscal` calculé automatiquement à partir des champs `categorie_reception_europeenne` (J.1), `carrosserie` (J.2), `nombre_places_assises` (S.1), `affectation_transport_personnes` (déclaratif), `usage_remontees_mecaniques_skiables` (déclaratif), `usage_special` (M1) et `banquette_amovible_avec_2_rangs` (camionnettes). Voir `2024/cas-particuliers/decisions.md` Décision 1.
- 6 cas types documentés couvrant les principales situations frontières (`recherches.md` § 3.4) :
  - Cas A.1 : voiture M1 classique → taxable.
  - Cas A.2 : pick-up N1 ≥ 5 places → taxable.
  - Cas A.3 : camionnette N1 ≥ 2 rangs affectée transport personnes → taxable.
  - Cas A.4 : camionnette N1 1 rang ou non affectée transport personnes → non taxable.
  - Cas A.5 : pick-up N1 < 5 places → non taxable.
  - Cas A.6 : camionnette N1 avec banquette amovible → taxable si transport de personnes, non taxable sinon.
- Garde-fou UI pour les cas frontières : alertes affichées invitant l'utilisateur à confirmer la qualification (`recherches.md` § 3.5).
- **Mise à jour proposée du cahier des charges § 2.1** : harmoniser le critère « camionnettes ≥ 3 rangs de places » avec la lettre du CIBS (« au moins deux rangs de places ») et ajouter le critère « affecté au transport de personnes » pour les camionnettes N1.

Confiance de la résolution : **Haute** (triangulation primaire complète, lecture directe et univoque des sources).

---

## Z-2024-006 — Bascule automatique sur barème PA en cas de donnée CO₂ manquante (renvoi de clôture)

L'instruction des cas particuliers 2024 a clos cette incertitude qui avait été initialement ouverte lors de l'instruction de la taxe CO₂. Le détail consolidé est tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-006), où le statut a été passé à « **Résolu** » le 23/04/2026 avec résumé de l'issue.

**Résumé synthétique de la résolution** :

- La règle juridique est explicite et univoque :
  - **CIBS art. L. 421-119-1, 3°** (S2) — bascule sur PA pour « les autres véhicules, ainsi que [...] ceux pour lesquels les émissions de dioxyde de carbone n'ont pas pu être déterminées ».
  - **BOFiP** `BOI-AIS-MOB-10-30-20` § 220 (S4) — confirme.
  - **Notice DGFiP** n° 2857-FC-NOT-SD partie II.1 (S7) — formulation explicite : « le barème [WLTP] s'applique [...] **à l'exception des véhicules pour lesquels les émissions de CO₂ n'ont pas pu être déterminées** ».
- **Implémentation Floty** : algorithme `determiner_bareme_avec_bascule_pa` qui (i) applique la bascule automatique sur PA si donnée CO₂ manquante mais PA disponible, avec alerte UI claire ; (ii) bloque le calcul (message d'erreur) si ni CO₂ ni PA ne sont disponibles. Voir `2024/cas-particuliers/decisions.md` Décision 3.
- L'alerte UI répond à un enjeu d'auditabilité (l'utilisateur et l'expert-comptable doivent comprendre pourquoi le tarif est élevé sur ce véhicule). L'écart numérique entre tarif PA et tarif WLTP est généralement massif (× 30 à × 100), ce qui crée une incitation forte à corriger la saisie.
- Test unitaire : véhicule WLTP-éligible (1ère immat. France 15/06/2022) sans CO₂ saisi avec PA = 6 CV → bascule sur PA → tarif annuel plein 11 250 € (`recherches.md` § 5.4).

C'est essentiellement un point UX/produit (la règle juridique est claire). Confiance de la résolution : **Haute** (règle juridique explicite + bonne pratique UX documentée).

---

## Z-2024-007 — Hybrides Diesel-électrique (renvoi de consolidation)

L'instruction des cas particuliers 2024 a consolidé cette incertitude qui avait été initialement ouverte lors de l'instruction de la taxe polluants. Le détail consolidé est tenu dans `2024/taxe-polluants/incertitudes.md` (entrée Z-2024-007), avec mention explicite des éléments apportés par l'instruction des cas particuliers (formalisation de la règle Floty en algorithme étendu, ajout du champ `type_moteur_thermique_sous_jacent`, garde-fou UI).

Pour rappel synthétique :

- **Notre choix actuel** : par lecture stricte de l'article L. 421-134, 2° du CIBS et par cohérence avec la vignette Crit'Air, classement des hybrides Diesel-électrique en « véhicules les plus polluants » → 500 € en 2024. Application du principe de prudence (méthodologie § 8.3 — la lecture la plus majorante en cas de doute).
- **Vérification d'absence d'assouplissement réglementaire** : la présente mission a recherché toute disposition (article CIBS, BOFiP, notice DGFiP, arrêté ministériel) qui aurait pu prévoir un traitement particulier pour les hybrides Diesel-électrique. **Aucune n'a été identifiée**. Cette absence est cohérente avec l'esprit de la disposition (les hybrides Diesel ont une part de leur fonctionnement assurée par un moteur Diesel qui émet par construction davantage d'oxydes d'azote et de particules fines).
- **Implémentation Floty** : algorithme `categorie_polluants_etendu` (extension de `2024/taxe-polluants/decisions.md` Décision 3) avec ajout du champ `type_moteur_thermique_sous_jacent` (énumération {Essence, Diesel, sans objet}), conditionnel pour les motorisations « Hybride non rechargeable » et « Hybride rechargeable ». Voir `2024/cas-particuliers/decisions.md` Décision 4.
- Garde-fou UI : sélecteur obligatoire pour ce champ lors de la saisie d'un véhicule hybride, avec explication contextuelle.
- Test unitaire : Mercedes Classe E 300de hybride rechargeable Diesel + électrique → catégorie « véhicules les plus polluants » → 500 € (`recherches.md` § 6.6).
- **Action attendue** : Validation expert-comptable souhaitable. Question précise : « Pour un véhicule hybride combinant moteur Diesel et moteur électrique homologué Euro 6, quelle catégorie de la taxe annuelle polluants atmosphériques s'applique : catégorie 1 ou catégorie « véhicules les plus polluants » ? ».
- **Statut maintenu** : **Ouvert** — désormais documenté en règle d'implémentation Floty dans `2024/cas-particuliers/recherches.md` § 6 et `2024/cas-particuliers/decisions.md` Décision 4. Maintien probable jusqu'à la validation expert-comptable car le cas n'est pas explicitement traité par les sources primaires.

---

## (Aucune nouvelle incertitude juridique ouverte par l'instruction des cas particuliers 2024)

L'instruction des cas particuliers pour l'exercice 2024 n'a pas donné lieu à l'ouverture d'une nouvelle incertitude juridique. Justification :

- Les **3 cas particuliers résolus** par cette mission (A — frontière M1/N1 ; B — véhicule importé d'occasion ; C — bascule PA en cas de donnée CO₂ manquante) sont tranchés par **triangulation primaire complète** (CIBS + BOFiP + notice DGFiP) avec lecture directe et univoque des sources.
- Les **3 cas particuliers consolidés** (D — hybrides Diesel-électrique ; E — exonération LCD avec cumul par couple ; F — indisponibilités longues hors fourrière) reposent sur des incertitudes déjà ouvertes (Z-2024-007, Z-2024-002, Z-2024-001 respectivement) ; aucune nouvelle question juridique n'a émergé. *Note : Z-2024-002 (cas E) a été résolue le 23/04/2026 par clarification client ultérieure ; Z-2024-001 et Z-2024-007 restent ouvertes.*
- Les **2 cas particuliers UX/produit** (G — sortie de flotte ; H — changement de caractéristiques fiscales en cours d'année) ne soulèvent **aucune incertitude juridique** :
  - Cas G : la règle juridique est explicite (BOFiP S5 § 190 — décompte exact des jours d'affectation effective).
  - Cas H : la lecture par défaut (caractéristiques effectives à chaque jour) est cohérente avec les sources primaires (notamment l'état récapitulatif annuel CIBS art. L. 421-164 mentionnant « les caractéristiques techniques, les conditions d'affectation, les périodes »). L'exigence produit identifiée (historisation des caractéristiques fiscales) relève de la conception applicative, pas du droit fiscal.

### Note méthodologique — exigence produit identifiée mais non formalisée comme incertitude

Le **cas H** (véhicule changeant de caractéristiques fiscales en cours d'année) a permis d'identifier une **exigence produit majeure** : la nécessité d'historiser les caractéristiques fiscales d'un véhicule (pour gérer correctement les conversions E85, modifications d'aménagement, retraits d'homologation, etc.). Cette exigence dépasse le périmètre strict de la recherche fiscale et relève de la conception applicative. Elle est documentée en `2024/cas-particuliers/decisions.md` Décision 6, avec proposition de mise à jour du cahier des charges § 2.1. Elle ne constitue **pas une incertitude juridique** au sens de la méthodologie projet (§ 8) et n'est donc pas tracée comme une entrée Z-2024-NNN.

---

## Synthèse — Bilan des incertitudes 2024 après cette mission

| Référence | Sujet | Priorité | Statut entrée mission | Statut sortie mission |
|---|---|---|---|---|
| Z-2024-001 | Indisponibilités longues hors fourrière | Moyenne | Ouvert | **Ouvert** (consolidé) |
| Z-2024-002 | Qualification du modèle Floty au regard de l'exonération LCD | Haute (initiale) | Ouvert | **Résolu — 23/04/2026** (par clarification client ultérieure) |
| Z-2024-003 | Abattement E85 en 2024 | Moyenne | Résolu (par `2024/abattements/`) | **Résolu** (inchangé) |
| Z-2024-004 | Véhicule importé d'occasion | Moyenne | Ouvert | **Résolu — 23/04/2026** |
| Z-2024-005 | Frontière fiscale M1 / N1 | Moyenne | Ouvert | **Résolu — 23/04/2026** |
| Z-2024-006 | Bascule automatique sur barème PA | Basse | Ouvert | **Résolu — 23/04/2026** |
| Z-2024-007 | Hybrides Diesel-électrique | Moyenne | Ouvert | **Ouvert** (consolidé, validation EC souhaitable) |
| Z-2024-008 | Vérification exemple BOFiP § 290 | Basse | Ouvert | **Ouvert** (sans changement — point documentaire) |
| Z-2024-009 | Garde-fou Crit'Air vs motorisation+Euro | Basse | Ouvert | **Ouvert** (sans changement — point UX) |
| Z-2024-010 | Date de référence ancienneté hybride 2024 | Moyenne | Ouvert | **Ouvert** (sans changement — validation EC) |

**Bilan global 2024 (mis à jour 23/04/2026 après clôture de Z-2024-002)** :

- Avant cette mission : **1 résolue** (Z-2024-003), **9 ouvertes** (1 haute, 5 moyennes, 3 basses).
- Après cette mission et clarification Z-2024-002 : **5 résolues** (Z-2024-002, Z-2024-003, Z-2024-004, Z-2024-005, Z-2024-006), **5 ouvertes** (3 moyennes, 2 basses — **plus aucune priorité haute**).

Sur les 5 incertitudes restant ouvertes en sortie d'exercice 2024 :

- **2 sont désormais documentées en règle d'implémentation Floty** (Z-2024-001 indisponibilités longues, Z-2024-007 hybrides Diesel) — elles attendent une validation expert-comptable mais ne bloquent pas le développement de Floty (la lecture par défaut est codée).
- **1 est un point UX/produit** (Z-2024-009 — garde-fou Crit'Air) — à clore en phase de développement.
- **1 est un point documentaire** sans impact opérationnel (Z-2024-008 — vérification exemple BOFiP § 290).
- **1 attend une validation expert-comptable** sur un point juridique non instruit par cette mission (Z-2024-010 — date de référence ancienneté hybride).

Le dossier 2024 est **prêt pour livraison à l'expert-comptable du client** pour les 5 incertitudes restantes, sans aucune priorité haute. La résolution de Z-2024-002 (qualification LCD avec cumul par couple) lève le risque structurant le plus important du dossier.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Création initiale du fichier `incertitudes.md` du sous-dossier `2024/cas-particuliers/`. Contenu : 3 renvois de clôture (Z-2024-004, Z-2024-005, Z-2024-006 — résolues par la présente mission) ; 3 renvois de consolidation (Z-2024-001, Z-2024-002, Z-2024-007 — maintenues ouvertes mais documentées en règle d'implémentation Floty). Aucune nouvelle incertitude juridique ouverte ; identification d'une exigence produit (historisation des caractéristiques fiscales — cas H) non formalisée comme incertitude. Bilan 2024 : 4 résolues (vs 1 entrée mission), 6 ouvertes (vs 9 entrée mission), dont 3 documentées en règle Floty et 1 priorité haute (Z-2024-002). |
| 0.2 | 23/04/2026 | Micha MEGRET | **Z-2024-002 passée au statut « Résolu »** suite à clarification directe avec Renaud (lecture définitive : LCD avec cumul annuel par couple véhicule × entreprise utilisatrice). Le renvoi de consolidation Z-2024-002 du présent fichier est mis à jour en renvoi de clôture, avec mention que la Décision 5 de `decisions.md` est partiellement dépassée (les champs `qualification_mise_a_disposition` et `qualification_specifique` proposés par cette décision deviennent sans objet). Bilan global 2024 actualisé : 5 résolues, 5 ouvertes (plus aucune priorité haute). |
