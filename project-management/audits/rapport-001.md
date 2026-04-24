# Rapport d'audit 001 — `project-management/` Floty

> **Date** : 24 avril 2026
> **Auteur** : Claude (audit délégué à 4 agents parallèles + synthèse)
> **Objet** : audit de cohérence et de qualité du corpus documentaire `project-management/` avant démarrage de l'implémentation
> **Périmètre audité** : ~40 fichiers / ~900 Ko sur 10 dossiers (cahier_des_charges, roadmap, changelog, decisions/, design-system/, implementation-rules/, modele-de-donnees/, plan-implementation/, recherches-fiscales/, stack-technique/, taxes-rules/)
> **Statut** : lecture seule — aucune modification effectuée
> **Usage** : document de référence pour les corrections à apporter avant l'étape 6 (implémentation)

---

## Sommaire

1. [Verdict d'ensemble](#1-verdict-densemble)
2. [Ce qui est solide](#2-ce-qui-est-solide)
3. [Corrections P0 — bloquantes avant de coder](#3-corrections-p0--bloquantes-avant-de-coder)
4. [Corrections P1 — avant phase 03 (auth)](#4-corrections-p1--avant-phase-03-auth)
5. [Corrections P2 — avant V1 final](#5-corrections-p2--avant-v1-final)
6. [Audit détaillé — ADRs](#6-audit-détaillé--adrs)
7. [Audit détaillé — Règles fiscales](#7-audit-détaillé--règles-fiscales)
8. [Audit détaillé — Implementation rules](#8-audit-détaillé--implementation-rules)
9. [Audit détaillé — Plan d'implémentation](#9-audit-détaillé--plan-dimplémentation)
10. [ADRs manquants à rédiger](#10-adrs-manquants-à-rédiger)
11. [Risques de sur-ingénierie](#11-risques-de-sur-ingénierie)
12. [Dossiers vides et incohérences structurelles](#12-dossiers-vides-et-incohérences-structurelles)

---

## 1. Verdict d'ensemble

**Corpus documentaire exceptionnellement mature pour un projet qui n'a pas encore une ligne de code applicatif.** ~900 Ko sur ~40 fichiers, architecture en 10 dossiers, traçabilité à trois niveaux (git + historique intra-doc + changelog transverse). La qualité rédactionnelle, la discipline méthodologique (triangulation fiscale, ADRs structurés, versioning des documents) et la cohérence globale sont **au-dessus du standard d'un cabinet de conseil moyen**.

Trois problèmes systémiques doivent cependant être réglés **avant d'écrire du code** :

1. **Cohérences partielles entre passes successives** — plusieurs documents ont été mis à jour par vagues (v1.0 → v2.1) sans repasse globale : des exemples de code contredisent des règles énoncées quelques lignes plus haut (Wayfinder vs `route()`, enums français vs anglais).
2. **Asymétrie de maturation du plan** — phase 00 détaillée à la tâche près (11 fiches), phases 01-13 = READMEs de titres seulement. ~230 fiches de tâches restent à rédiger.
3. **Angles opérationnels absents** — ni ADR ni documentation sur RGPD, sécurité applicative, backup, CI/CD, observabilité, locale/timezone, unicité MySQL des attributions, caching du moteur fiscal.

---

## 2. Ce qui est solide

| Dossier / fichier | État |
|---|---|
| `cahier_des_charges.md` v1.5 | Complet, versionné avec historique précis des 5 révisions. Règles fiscales (§5) exhaustives. Périmètre UX (§3) détaillé pour 10 vues. |
| `recherches-fiscales/` (2024) | Méthodologie rigoureuse (triangulation CIBS + BOFiP + notice DGFiP), 24 règles 2024 consolidées, 10 incertitudes tracées dont 5 résolues. Pattern `decisions.md / recherches.md / sources.md / incertitudes.md` propre. |
| `taxes-rules/2024.md` | Consolidation impeccable (R-2024-001 à R-2024-024), chaque règle avec description, pseudo-code, exemples chiffrés, base légale, tests de bornes. |
| `decisions/` (8 ADRs) | Structure standard respectée, décisions dures motivées (immutabilité PDF, moteur générique, calcul journalier). ADR-0006 particulièrement mature (7 décisions internes). |
| `modele-de-donnees/` | Section « Adaptation MySQL 8 » bien pensée (triggers anti-chevauchement, colonnes générées pour remplacer index partiels, 3 lignes de défense applicative). |
| `stack-technique/versions-outils.md` | Vérification Hostinger factuelle (shell SSH), matrice de compatibilité croisée explicite. |
| `design-system/` | Bundle Claude Design avec tokens, typographie, conventions monétaires FR, règles de ton. Opérationnel. |

---

## 3. Corrections P0 — bloquantes avant de coder

> Effort estimé total : 2-4 heures de corrections mécaniques + 2-3 décisions à trancher avec le client.

### P0.1 — Erreur arithmétique dans `taxes-rules/2024.md` R-2024-010 [CRITIQUE]

**Fichier** : `project-management/taxes-rules/2024.md`, règle R-2024-010, tests unitaires.

**Problème** : test unitaire « 200 g/km → 3 058 € ». **Le calcul correct donne 4 258 €** (cohérent avec R-2024-022 qui utilise le même 200 g/km et indique bien 4 258 €).

Vérification : tranches (0;14](14;55](55;63](63;95](95;115](115;135](135;155](155;175](175;+∞) avec tarifs 0,1,2,3,4,10,50,60,65.
Pour 200 : 0 + 41×1 + 8×2 + 32×3 + 20×4 + 20×10 + 20×50 + 20×60 + 25×65 = 0+41+16+96+80+200+1000+1200+1625 = **4 258 €**.

**Risque** : si ce test est repris tel quel en PHPUnit, il validera un bug au lieu de le détecter.

**Correction** : remplacer `3 058 €` par `4 258 €` dans la ligne de test de R-2024-010.

---

### P0.2 — Exemple E polluants contradictoire avec R-2024-021 LCD [CRITIQUE]

**Fichier** : `project-management/recherches-fiscales/2024/taxe-polluants/decisions.md`, Exemple E.

**Problème** : BMW Série 5 Diesel affectée 30 jours. Selon R-2024-021 (LCD cumul par couple, seuil 30 jours), 30 jours ≤ 30 → couple **exonéré** → 0 €. Mais l'exemple calcule 40,98 €. Une note signale la révision de Z-2024-002 mais conserve le résultat 40,98 €.

**Risque** : un développeur qui reprend cet exemple comme test unitaire implémentera la mauvaise logique LCD.

**Correction** : soit corriger le résultat (0 €), soit changer la durée (>30 j), soit marquer explicitement l'exemple comme « pédagogique pre-Z-2024-002, ne pas utiliser en test ».

---

### P0.3 — Migration Wayfinder non propagée aux exemples de code

**Problème** : la bascule Ziggy → Wayfinder a été décidée le 24/04 (v1.1 de `inertia-navigation.md`) mais les exemples de code n'ont pas été migrés dans les autres docs.

**Fichiers affectés** :
- `implementation-rules/vue-composants.md` (lignes 290, 309) : `router.visit(route('user.vehicles.show', ...))`
- `implementation-rules/gestion-erreurs.md` (ligne 388) : `form.post(route('user.vehicles.store'), ...)`
- `implementation-rules/composables-services-utils.md` (lignes 70, 356) : `router.get(route('user.dashboard'), ...)`, `route('user.vehicles.index')`
- `implementation-rules/architecture-solid.md` (lignes 261-262) : `redirect()->route('user.vehicles.show', ...)` côté JS
- `implementation-rules/tests-frontend.md` (lignes 80-84) : `global.route = vi.fn(...)` (mock Ziggy)
- `implementation-rules/structure-fichiers.md` (ligne 135) : `route('user.vehicles.show', ...)`
- `implementation-rules/typescript-dto.md` (ligne 783) : exemple `route('user.vehicles.show', ...)`
- `implementation-rules/inertia-navigation.md` elle-même : ~15 exemples `route()` après la section Wayfinder (lignes 170-180, 214, 304, 308, 396, 406, 415…)

**Correction** : passe de substitution mécanique `route('user.xxx.yyy', ...)` → `XxxController.yyy({...})` dans tous ces fichiers.

---

### P0.4 — Enums anglais strict partiellement appliqué

**Problème** : la décision E1 (anglais strict pour enums) n'est pas complètement propagée.

**Fichiers affectés** :
- `implementation-rules/conventions-nommage.md` ligne 181 : exemple TS `type DeclarationStatus = 'brouillon' | 'verifiee' | 'generee' | 'envoyee'` alors que le tableau récap utilise `Draft / Verified / Generated / Sent`.
- `implementation-rules/tests-frontend.md` ligne 615 : fixture `energySource: 'essence'` au lieu de `'gasoline'`.
- `implementation-rules/tests-frontend.md` ligne 637 : fixture `currentStatus: 'actif'` au lieu de `'active'`.

**Correction** : aligner sur la convention E1 dans les 3 emplacements.

---

### P0.5 — Couche « Service frontend » rémanente

**Problème** : `composables-services-utils.md` v1.1 a supprimé la couche Service frontend. Rémanence dans :
- `implementation-rules/tests-frontend.md` ligne 157 : « Service frontend ... Oui » dans la matrice.

**Correction** : supprimer la ligne de la matrice.

---

### P0.6 — Dépendance inversée Events ↔ Invalidation dans le plan

**Problème** :
- Phase 07.13 : listener `AssignmentChanged` déclenche la détection d'invalidation.
- Phase 11.09 : service `DeclarationInvalidationDetector` (qui fait la détection).

Le listener de la phase 07 appelle un service qui n'existera qu'en phase 11. Même problème avec `UnavailabilityChanged` (phase 08.09).

**Correction** : soit créer un stub `DeclarationInvalidationDetector` en phase 07 (interface vide), soit déplacer les event listeners (07.13 et 08.09) vers la phase 11.

---

### P0.7 — Cache tags utilisés avant configuration du driver

**Problème** :
- Phase 07.07 et 07.12 : utilisation de cache tags Laravel (`BulkAssignmentService`, Observer).
- Phase 13.09 : configuration du driver `database` + émulation des tags.

**Correction** : remonter la tâche 13.09 en phase 01 (fondations backend).

---

### P0.8 — Layouts : emplacement incohérent

**Problème** :
- `implementation-rules/structure-fichiers.md` ligne 58 : `resources/js/Layouts/` (racine)
- `implementation-rules/structure-fichiers.md` lignes 232, 281 : `Components/Layouts/`
- `implementation-rules/architecture-solid.md` ligne 1581+ : `Components/Layouts/`
- `implementation-rules/vue-composants.md`, `conventions-nommage.md` : `Components/Layouts/`

**Correction** : supprimer la référence de `structure-fichiers.md` ligne 58 au profit de `Components/Layouts/`.

---

### P0.9 — Stockage PDF non tranché dans ADR-0003

**Problème** : ADR-0003 (accepté) laisse le stockage PDF « à trancher en phase de modèle de données ». ADR-0008 tranche (filesystem local + chemin en base) mais ADR-0003 n'a pas été mis à jour.

**Correction** : ajouter une mention « stockage tranché par ADR-0008 : filesystem local + chemin en base » dans ADR-0003.

---

### P0.10 — Questions ouvertes à trancher avec le client

| Sujet | Enjeu |
|---|---|
| Noms de colonnes BDD et propriétés DTO en français ou anglais strict ? | Question ouverte documentée dans le changelog du 24/04. Cohérence E1 stricte demanderait `licensePlate`, `brand`, `model`, `color`, `numberOfSeats`, `acquisitionDate`… Chantier conséquent mais cohérent. |
| Barèmes fiscaux en code PHP (constantes de Rule) ou en BDD (tables dédiées) ? | Ni ADR-0001 ni plan-implementation phase 10 ne le tranche. Si code : cohérent ADR-0002 (règles non éditables). Si BDD : il manque des tables `fiscal_scale_co2_2024`, `fiscal_scale_pollutants_2024` dans le schéma. |
| Format exact du snapshot JSON | Le fichier `docs/declaration-snapshot-format.md` est référencé en phase 11/12 mais n'existe pas. À créer. |

---

## 4. Corrections P1 — avant phase 03 (auth)

### P1.1 — ADRs à rédiger avant la phase 03

| ADR | Pourquoi critique |
|---|---|
| **Versioning des règles fiscales** | Convention implicite partagée par ADR-0003, 0004, 0006 sans jamais être formalisée (SemVer ? hash de contenu ? entier ?). Impacte la structure des snapshots immuables. |
| **RGPD et conservation** | ADR-0003 prévoit 10 ans de snapshots avec données personnelles (SIREN, noms conducteurs). Absent : DPO, droit à l'effacement, sous-traitance Hostinger, registre de traitement. |
| **Sécurité applicative minimum** | HTTPS, CSP, rate limiting, session hardening, CSRF Inertia, audit dépendances. Produit B2B fiscal = niveau d'exigence élevé. |

### P1.2 — Incohérences fiscales à résoudre

- **R-2024-004 camionnette 4 places en 2 rangs** : la disjonction actuelle `≥ 5 places OU banquette amovible` exclut un cas plausible (ex. Dacia Dokker 4 places en 2 rangs fixes). Le texte CIBS L. 421-2 vise « ≥ 2 rangs de places ». **Potentielle sous-inclusion** → à clarifier avec le client + expert-comptable.
- **Décision 1 taxe-co2 vs Décision 2 cas-particuliers** : critère WLTP formulé comme « 1ère immat. France ≥ 2020-03-01 » dans taxe-co2 vs « méthode d'homologation effective » dans cas-particuliers. La règle R-2024-005 s'aligne sur cas-particuliers (bonne lecture), mais Décision 1 taxe-co2 n'a pas été révisée. À harmoniser.
- **Interaction LCD × fourrière** : R-2024-021 (LCD cumul ≤ 30 j) et R-2024-008 (fourrière réduit le numérateur) ne tranchent pas si les jours fourrière comptent dans le cumul LCD. À trancher explicitement.
- **Changement d'entreprise utilisatrice en cours de journée** : granularité jour impose un arbitrage (A ou B le jour J ?). Non tranché dans R-2024-002 ni R-2024-021.
- **Attribution chevauchant deux années civiles** : évoqué dans Décision 9 taxe-co2 (« scindage fictif ») mais non formalisé en règle.

### P1.3 — Tâches à ajouter au plan d'implémentation

| Tâche manquante | Phase |
|---|---|
| Localisation FR (`config/app.php locale = fr`) + timezone Europe/Paris | Phase 01 |
| Gouvernance du sélecteur d'année (YearSelector transverse : URL ? session ? Pinia ?) | Phase 02 (doc + tâche dédiée) |
| Dashboard minimal + recherche globale basique | Remonter de la phase 13 vers phase 04-05 |
| `VehicleFactory`, `CompanyFactory`, `DriverFactory`, `AssignmentFactory`, `UnavailabilityFactory` | Phases 04-08 |
| `DemoVehiclesSeeder`, `DemoCompaniesSeeder`, `DemoAssignmentsSeeder` | Phases 04-08 (sinon heatmap vide pendant 9 phases) |
| `isValidSiren` util | Phase 02.13 ou phase 05 |
| `DropdownMenu` / `Combobox` / `Autocomplete` / `Tooltip` / `Skeleton` | Phase 02 (UI Kit) |
| Commande Artisan `floty:user:reset-password` ou équivalent | Phase 03 |

### P1.4 — Inertia v3 sous-exploité

`inertia-navigation.md` reste essentiellement v2+Wayfinder. À compléter :
- `useHttp` hook (remplaçant axios)
- Events renommés : `router.on('httpException')` et `router.on('networkError')` (vérifier `gestion-erreurs.md` ligne 549 qui utilise encore `exception`)
- `setLayoutProps` / `useLayoutProps`
- Optimistic updates avec rollback automatique
- Instant visits
- Prefetching avancé

---

## 5. Corrections P2 — avant V1 final

### P2.1 — ADRs secondaires à rédiger

| ADR | Pourquoi utile |
|---|---|
| Unicité MySQL des attributions | ADR-0005 pose « 1 véhicule, 1 jour, 1 entreprise ». MySQL n'a pas d'exclusion constraint. Trigger ? Advisory lock ? Transaction sérialisable ? |
| Caching du moteur fiscal | Dashboard avec « estimation taxes année en cours » nécessite calcul lourd ; compteur LCD temps réel. Aucune stratégie de cache/invalidation documentée. |
| Tests fiscaux (golden tests) | ADR-0006 l'effleure ; vu la criticité fiscale, mérite un ADR dédié avec jeux BOFiP officiels comme fixtures de non-régression. |
| Backup / PRA | Conservation 10 ans sur mutualisé Hostinger sans stratégie de sauvegarde documentée = risque majeur. |
| CI/CD déploiement | Renvoyé « à l'implémentation » dans ADR-0008. Secrets, webhook, rollback, déploiement de seeders fiscaux. |
| Auth / gestion comptes | Reset mot de passe, session, 2FA futur, création de compte — éclaté dans ADR-0007 sans rigueur. |
| Observabilité production | Pail = dev. Silence sur logs/monitoring/alerting en production. |

### P2.2 — Validation et pattern manquants

- **Validation front (Zod/Valibot) : silence absolu** dans `typescript-dto.md` et `gestion-erreurs.md`. Un reviewer senior interprétera comme oubli. Trancher explicitement : « pas de Zod/Valibot en V1, validation backend Laravel seule via FormRequest ».
- **Error boundaries Vue** : pas de pattern Floty défini. Ajouter un `ErrorBoundary.vue` pour wrapper les zones critiques (heatmap, saisie tableur).
- **Audit log des actions utilisateur** : aucune tâche dans le plan. Critique pour déclarations fiscales (qui a passé en `verified` ? qui a regénéré le PDF ?).

### P2.3 — Processus projet

- **Rédiger au moins les fiches critiques des phases 04-12** avant de les attaquer. Au minimum la première tâche de chaque phase et les tâches à risque (04.01-04.03 migrations, 07.01 colonne générée, 10.07 pipeline 8 étapes, 12.03 template DomPDF).
- **Planifier 3 démos intermédiaires client** : fin phase 04 (CRUD véhicule), fin phase 09 (planning), fin phase 11 (déclarations). Sinon le client ne voit rien avant la phase 13.
- **Réviser l'estimation globale 45-60 jours** : phase 04 (34 tâches), phase 09 (26 tâches UX complexes), phase 10 (25 tâches fiscales) probablement sous-estimées de 30-50%.

---

## 6. Audit détaillé — ADRs

### ADR-0002 — Règles fiscales non éditables en V1

- **Points forts** : structure standard, 3 alternatives écartées, honnêteté sur le modèle économique du prestataire, réouverture V2/V3 prévue.
- **Points faibles** :
  - Procédure opérationnelle de déploiement d'un seeder non définie (SLA pour un correctif urgent ? fenêtre de déploiement ?).
  - « Versionnage interne des règles » mentionné ailleurs (ADR-0003, 0006) mais pas introduit ici.
  - Risque de « bus factor » non abordé (dépendance 100% technique à un prestataire unique).

### ADR-0003 — PDF et snapshots immuables

- **Points forts** : justification fiscale et juridique solide, 3 alternatives, couplage PDF + snapshot JSON motivé.
- **Points faibles** :
  - **Durée de conservation non fixée** (texte évoque « 10 ans » sans trancher).
  - **Stockage PDF « à trancher »** dans un ADR « Accepté » — tranché en réalité par ADR-0008 mais non référencé (cf. P0.9).
  - **Format du snapshot JSON non versionné** ici.
  - **Risque RGPD non abordé** (conservation 10 ans vs droit à l'effacement).
  - **Perte de PDF** (corruption, backup, checksum, recouvrement) : silence.

### ADR-0004 — Invalidation par marquage

- **Points forts** : cohérence forte avec ADR-0003, orthogonalité statut cycle de vie / statut validité, préconisation technique (hash signature) pragmatique.
- **Points faibles** :
  - **Liste exhaustive des actions invalidantes** non donnée rigoureusement.
  - Quid d'une modification qui s'annule (A puis défait A) ? Hash rebascule automatiquement ? Non traité.
  - Performance du calcul de hash sur 366 jours × 100 véhicules à chaque consultation : acceptable V1 mais une mention aurait été utile.

### ADR-0005 — Calcul jour-par-jour

- **Points forts** : triangulation CIBS + BOFiP + cahier des charges, valeur chiffrée BOFiP (`173 × 306/366 = 144,64 €`), 4 alternatives.
- **Points faibles** :
  - **Changement d'année civile** au sein d'une attribution : non précisé.
  - **Fuseau horaire** : aucune décision explicite (UTC ? Europe/Paris ?).
  - **Changements de caractéristiques véhicule intra-journaliers** : pas de règle de résolution.
  - **Modèle de données** : deux alternatives proposées, aucune tranchée.

### ADR-0006 — Architecture du moteur de règles

- **Points forts** : profondeur exceptionnelle (7 décisions internes, 5 alternatives), tableau mappant chaque sous-type aux règles 2024, pipeline court-circuit, validation de cohérence au démarrage.
- **Points faibles** :
  - **Versioning interne des règles** : format non fixé (SemVer ? entier ? composite ?).
  - **Ordre des exonérations** : « dans un ordre défini » sans critère explicite.
  - **Performance du mode simulation** (compteur LCD) : pas de décision sur caching/debouncing.
  - **Stratégie de tests** (golden tests fiscaux) : effleurée, mériterait un ADR dédié.
  - **Flag `isActive`** : jamais expliqué (qui peut désactiver ? via seeder ?).
  - **Incohérence interne** : R-2024-007/008/009 classées `TransversalRule` alors que l'indisponibilité fourrière influe directement sur le prorata (pas « transversal » stricto sensu).

### ADR-0007 — Périmètre V1 MVP

- **Points forts** : découpage tri-dimensionnel explicité, alignement avec mémoire projet sur V1.2, justification « coder toutes les exonérations y compris inactives » robuste, 3 alternatives explorées.
- **Points faibles** :
  - **Aucune estimation chiffrée** (charge, jalons, coûts).
  - **Critères d'acceptation V1 non définis** — qu'est-ce qu'une V1 « livrée » ?
  - **Reprise d'historique** : la charge de saisie manuelle de 2 ans × 30 entreprises × ~200 jours n'est pas traitée.
  - **RGPD / mentions légales** : engagement fort (trame CNIL-conforme) sans ADR dédié.
  - **« Reset manuel du mot de passe »** : flou opérationnel.
  - **Citation erronée** : « ADR-0002 pour l'esprit minimaliste V1 » à propos du reset mot de passe — ADR-0002 parle des règles fiscales, pas de l'auth.

### ADR-0008 — Stack technique V1

- **Points forts** : exhaustivité rare (25 composants versionnés), 10 alternatives écartées, vérification empirique Hostinger (`node -v` → command not found), chemin d'évolution VPS documenté.
- **Points faibles** :
  - **Déploiement CI/CD** renvoyé « à l'implémentation » au lieu d'un ADR dédié.
  - **MySQL et unicité attributions** traité d'une ligne alors que c'est un risque majeur.
  - **Backups** non mentionnés (critique sur mutualisé Hostinger).
  - **Sécurité applicative** (HTTPS, rate limiting, 2FA, CSP) : silence.
  - **Observabilité production** (logs rotation, Sentry/Bugsnag) : silence.
  - **Licences des dépendances** : pas d'audit mentionné.

### Tensions croisées entre ADRs

1. **ADR-0002 vs ADR-0006** — Flag `is_active` mutable par seeder uniquement ? À trancher explicitement.
2. **ADR-0003 vs ADR-0008** — Stockage PDF : cf. P0.9.
3. **ADR-0003/0004 vs ADR-0006** — Versioning des règles : convention implicite jamais définie.
4. **ADR-0004 vs ADR-0006** — Actions invalidantes devraient être dérivées automatiquement de `vehicleCharacteristicsConsumed` du pipeline. Lien structurel non tracé.
5. **ADR-0005 vs ADR-0006** — Cumul LCD par couple est un état persistant annuel. Relation avec mode simulation et invalidation non tracée.
6. **ADR-0007 vs ADR-0008** — Dashboard « estimation taxes année en cours » implique calcul lourd. Silence sur caching.
7. **Conventions de datation** non uniformes entre ADR-0002-0005 et 0006-0008.

---

## 7. Audit détaillé — Règles fiscales

### Qualité globale : excellente

- **Architecture documentaire mature** (pattern à 2 niveaux pour les incertitudes, historique de révision conservé, versioning intra-document).
- **Triangulation effective** : tous les chiffres (tarifs, bornes WLTP/NEDC/PA, tarifs polluants, seuils exo hybride) sont triangulés ; exemple BOFiP § 230 (100 g/km → 173 €, puis × 306/366 = 144,64 €) reproduit à l'identique.
- **Distinction sémantique** exonération technique vs effet du barème (R-2024-016 / R-2024-014 catégorie E) finement traitée.
- **24 règles R-2024-001 à R-2024-024** toutes présentes dans `taxes-rules/2024.md`, correctement paramétrées, testables.

### Incohérences trouvées

| # | Localisation | Incohérence | Gravité |
|---|---|---|---|
| 1 | R-2024-010 test unitaire | 200 g/km annoncé à 3 058 € — vrai calcul 4 258 € | **Haute** (cf. P0.1) |
| 2 | Exemple E polluants (decisions.md) | 30 j cumulés ≤ 30 ⇒ exonéré LCD, mais l'exemple calcule 40,98 € | **Haute** (cf. P0.2) |
| 3 | R-2024-004 camionnette 4 places en 2 rangs | Disjonction `≥5 places OU banquette amovible` exclut ce cas plausible | Moyenne |
| 4 | Décision 1 taxe-co2 vs Décision 2 cas-particuliers | Critère « 1ère immat. France ≥ 2020-03-01 » vs « méthode d'homologation effective » | Moyenne |
| 5 | R-2024-021 LCD × R-2024-008 fourrière | Cumul pour seuil LCD inclut-il la fourrière ? Non tranché | Moyenne |
| 6 | R-2024-017 calcul ancienneté | `.years` entier vs comparaison de dates — risque arrondi | Basse |
| 7 | R-2024-017 plages BOFiP | `§ 130-150` vs `§ 120-140` — imprécision à vérifier | Basse |

### Gaps / cas non couverts

1. **Changement d'entreprise utilisatrice en cours de journée** : non tranché.
2. **Véhicule partagé entre deux entreprises simultanément** le même jour : à préciser.
3. **Transformation en cours d'année modifiant le `type_fiscal`** : interaction R-2024-007 (historisation) × R-2024-004 (type_fiscal) non explicitée.
4. **Norme Euro évoluant en cours d'année** (rétrofit, conversion) : pas illustré.
5. **Attribution chevauchant deux années civiles** : Décision 9 taxe-co2 l'évoque (« scindage fictif ») mais R-2024-002 ne le formalise pas.
6. **Interaction minoration 15 000 € × prorata** : même inactive V1, ordre d'application non documenté.
7. **Hybrides Euro pré-5** : classement « véhicules les plus polluants » par défaut à confirmer.
8. **DROM hors Guadeloupe, Martinique, Guyane, Réunion, Mayotte** : articulation avec CIBS L. 421-3 à harmoniser.
9. **R-2024-018 (OIG) CGI art. 261, 7°** : cité mais non résumé.
10. **Règle d'affichage PDF** (décimales, arrondi) dispersée dans R-2024-003 + Décision 7 taxe-co2 — pas de règle dédiée.

### Risques fiscaux résiduels

- **Haute priorité** : aucune. Z-2024-002 (LCD/LLD) a été résolu le 23/04/2026.
- **Moyenne priorité (3 ouvertes)** :
  - Z-2024-001 : indisponibilités longues hors fourrière — lecture majorante.
  - Z-2024-007 : hybrides Diesel-électrique — population restreinte, lecture majorante.
  - Z-2024-010 : date de référence ancienneté L. 421-125 — 1er janvier retenu par défaut, à valider EC.
- **Basse priorité (2 ouvertes)** : Z-2024-008 (BOFiP § 290) et Z-2024-009 (garde-fou Crit'Air).

### Verdict exploitabilité développeur

**Oui, le corpus est exploitable directement** pour écrire les seeders Floty et le moteur de calcul, **sous réserve des corrections P0 et P1 ci-dessus**.

---

## 8. Audit détaillé — Implementation rules

### Qualité globale : très élevée mais dérivée

~9 800 lignes sur 12 documents. Aligné sur la stack (Laravel 13 / Inertia v3 / Vue 3.5 / Tailwind 4 / Spatie Data 4 / Pinia 3 / Vitest 4) et sur les ADRs. Principes senior+ respectés (Composition API, TS strict, DTO auto-générés, pas de `any`, props immutables, tests adjacents).

### Trois dérives systémiques

1. **Wayfinder ↔ Ziggy** — cf. P0.3.
2. **Enums E1 anglais** — cf. P0.4.
3. **TypeScript 6** annoncé partout : version encore spéculative au go-live, à accompagner d'un plan B « TS 5.9 si TS 6 pas stable ».

### Par document (synthèse)

| Doc | Verdict | Point critique |
|---|---|---|
| `architecture-solid.md` (91 Ko) | Excellent, trop long | Interfaces Repository miroir systématiques = sur-ingénierie sur petit projet. `redirect()->route('user.xxx')` côté JS (Wayfinder). |
| `assets-vite.md` | Solide | Plugin Wayfinder absent de la config Vite montrée. |
| `composables-services-utils.md` | Très bon | Suppression de la couche Service frontend cohérente. 2 exemples `route(...)` restants. |
| `conventions-nommage.md` | Très bon | Ligne 181 exemple TS en français. Pas de convention commit messages. |
| `gestion-erreurs.md` | **Meilleur du corpus** | Events v3 à vérifier (`exception` vs `httpException`). Pas de pattern pour exceptions moteur fiscal. `form.post(route('...'))` (Wayfinder). |
| `inertia-navigation.md` (v1.1) | Dérive grave | Section Wayfinder posée en tête, 15 exemples `route(...)` plus bas. `useHttp`, `setLayoutProps`, optimistic updates absents. |
| `performance-ui.md` | **Meilleur guide anti-gold-plating** | Cibles bundle (vendor <100 KB) non étayées (projet pas encore codé). Pas de section cache HTTP. |
| `pinia-stores.md` | Très bon | `pinia-plugin-persistedstate` utilisé mais pas dans ADR-0008 stack. Pattern reset à déconnexion à vérifier v3. |
| `structure-fichiers.md` (v2.0) | Bon | Ligne 58 `resources/js/Layouts/` vs `Components/Layouts/` partout ailleurs (cf. P0.8). |
| `tests-frontend.md` | Bon | Mock `global.route = vi.fn()` (Ziggy). Matrice « Service frontend » rémanente. Fixtures en français. |
| `typescript-dto.md` | Excellent | Silence sur Zod/Valibot à expliciter. Typo « générgération » ligne 25. |
| `vue-composants.md` | Très bon | 2 exemples `router.visit(route(...))`. `useTemplateRef` sans note de nouveauté Vue 3.5. Pas de pattern error boundary. |

### Incohérences croisées

- **Wayfinder** (cf. P0.3).
- **Enums E1** (cf. P0.4).
- **Couche « Service frontend »** (cf. P0.5).
- **Emplacement Layouts** (cf. P0.8).
- **TypeScript 6** (assumé, à documenter comme pari).
- **Flash keys** : `toast-success` vs `flash.success` — artifact du middleware à commenter.

### Gaps majeurs

1. Inertia v3 sous-exploité (cf. P1.4).
2. Validation front (Zod/Valibot) silencieuse.
3. Error boundaries Vue non définies.
4. CSRF + auth scaffold custom non documenté.
5. I18n / mappings enum → label FR non structurés.
6. Aucun ADR frontend dédié — ADR-0008 est une stack, pas une architecture frontend.
7. Pas de route prefix explicite pour la page « Règles de calcul ».

---

## 9. Audit détaillé — Plan d'implémentation

### Qualité globale : solide mais asymétrique

- **Phase 00** : 11 tâches détaillées + 3 docs = mature, exemplaire.
- **Phases 01-13** : READMEs listant des titres. ~230 fiches de tâches manquantes (estimation : 2-3h × 230 = 50-70 jours de rédaction).

### Par phase (synthèse)

| Phase | État | Points critiques |
|---|---|---|
| **00 init** | Mature (11 tâches) | Pré-commit hooks n'incluent pas `typescript:transform`. Tâche 00.13 Hostinger sous-estimée. Pas de tâche « git flow / branches ». Pas de configuration Laravel Boost explicite. |
| **01 fondations-backend** | README 8 tâches | Pas de tâche stubs Artisan customs. `inertia.d.ts` dupliqué avec 00.05. Pas de tâche locale/timezone FR. |
| **02 design-system** | README 15 tâches | YearSelector sans tâche dédiée ni gouvernance. Dashboard repoussé phase 13. `DropdownMenu`, `Tooltip`, `Skeleton` manquants. |
| **03 auth** | README 13 tâches | `LoginAttemptService` vs `RateLimiter` natif à clarifier. Pas de forgot/reset password ni alternative. |
| **04 vehicle** | README 34 tâches (dense) | Pas de tâche Factory ni DemoSeeder. Fiches `migration-vehicle-fiscal-characteristics.md` référencées sans existence. |
| **05 company** | README 12 tâches | `isValidSiren` util utilisé sans création dédiée. |
| **06 driver** | README 11 tâches | Page Create driver passée sous silence. `DriverSelector` composant manquant. |
| **07 assignment** | README 15 tâches | Dépendance inversée Events ↔ Invalidation (cf. P0.6). Pas de factory/seeder. |
| **08 unavailability** | README 12 tâches | Dépendance Events identique. |
| **09 planning** | README 26 tâches | Heatmap sans test de charge >500 véhicules. Conflit saisie concurrente non traité. Pas de filtre par company. |
| **10 fiscal-engine** | README 25 tâches | Tâche 10.16 (8 règles d'exonération) à éclater. Barèmes fiscaux en code ou BDD ? (cf. P0.10). Pas de framework test scénarios fiscaux. Années futures (2025, 2026) non opérationnalisées. |
| **11 declarations** | README 22 tâches | Pas de factories. Retour vers `draft` vaguement évoqué. Pas d'audit log des statuts. Pas de comparaison N vs N-1. |
| **12 pdf** | README 13 tâches | Pas de visual regression test. Pas de tâche gestion erreurs DomPDF mi-parcours. Pas de garbage collection PDF. |
| **13 livraison** | README 21 tâches | Dashboard et recherche globale arrivent trop tard (cf. P1.3). Corbeille soft-deleted jamais testée avant. Cache driver en 13.09 contradictoire avec phases 07 (cf. P0.7). Duplicate 13.15 ↔ 00.13. |

### Dépendances manquantes / incohérences

1. Events ↔ Invalidation (cf. P0.6).
2. Cache driver (cf. P0.7).
3. Configuration locale FR + timezone (manque).
4. YearSelector transverse sans gouvernance.
5. `isValidSiren` util non créé.
6. Factories et seeders de démo absents.
7. ~40 fiches `docs/` référencées sans existence.

### Gaps majeurs vs périmètre V1

1. **Seeds fiscales chiffrées** (code PHP vs BDD non tranché).
2. **Format exact du snapshot JSON** (fiche référencée mais inexistante).
3. **Recherche globale** arrive trop tard.
4. **Dashboard** arrive trop tard.
5. **YearSelector** sans gouvernance.
6. **Gestion corbeille** centralisée phase 13 seulement.
7. **Module facturation V1.2** : aucune trace dans le plan V1 (pas de `rental_amount` anticipé, pas de note dans `Assignment`). Risque d'architecture à refondre en V1.2.
8. **Internationalisation / locale FR** absente.
9. **Fuseau horaire Europe/Paris** absent.
10. **Invariants fiscaux** (cohérence dates acquisition/service, SIREN/SIRET) incomplets.
11. **Audit log actions utilisateur** inexistant.
12. **Notifications** : aucune (OK V1 mais à acter).
13. **Email verification** : colonne présente en migration mais aucun flux.

### Alignement avec les ADRs

| ADR | Traité ? | Remarque |
|---|---|---|
| 0001 Fiscalité comme donnée | **Partiel** | Phase 10 mais barèmes chiffrés ambigus |
| 0002 Règles non éditables | Oui | Phase 10.D page consultation |
| 0003 PDF snapshots immuables | Oui | Phases 11 + 12 + hash SHA-256 |
| 0004 Invalidation par marquage | Oui | Phases 07.13 + 08.09 + 11.09 + 11.12 |
| 0005 Calcul jour par jour | Oui | Phase 07 + phase 04 |
| 0006 Moteur de règles | Oui | Phase 10 entièrement |
| 0007 Périmètre V1 MVP | Oui | Plan globalement aligné |
| 0008 Stack technique V1 | Oui | Phase 00 entièrement |

### Risques d'ordonnancement

1. **Phase 04 goulot** : 34 tâches, 5-7 jours annoncés, probablement 10-12 réels. Dérives cumulées sur 05-08.
2. **Phase 09 Planning sous-estimée** : 26 tâches UX complexes, 5-7 jours annoncés, probablement 10-15.
3. **Phase 10 Moteur fiscal** : 25 tâches, risque d'interprétation juridique. 5 scénarios d'intégration insuffisants.
4. **Absence de démo intermédiaire** client — risque projet majeur.
5. **Pas de plan de migration données** depuis tableur existant.
6. **Aucun buffer / contingency** dans l'estimation 45-60 jours.

---

## 10. ADRs manquants à rédiger

### Prioritaires (P1)

| # | ADR | Justification |
|---|---|---|
| 1 | Versioning des règles fiscales | Convention implicite partagée par ADR-0003, 0004, 0006 jamais formalisée |
| 2 | RGPD / conservation 10 ans | ADR-0003 fige données personnelles, silence sur DPO, droit à l'effacement, sous-traitance Hostinger |
| 3 | Sécurité applicative minimum | HTTPS, CSP, rate limiting, session hardening, CSRF Inertia |
| 4 | Auth / gestion comptes | Reset mot de passe, session, 2FA futur, création de compte — éclaté dans ADR-0007 |

### Secondaires (P2)

| # | ADR | Justification |
|---|---|---|
| 5 | Unicité MySQL des attributions | Trigger ? Advisory lock ? Transaction sérialisable ? Traité d'une ligne dans ADR-0008 |
| 6 | Caching du moteur fiscal | Dashboard + compteur LCD temps réel = calculs lourds sans stratégie |
| 7 | Tests fiscaux (golden tests) | Criticité fiscale élevée, ADR-0006 l'effleure |
| 8 | Backup / PRA | Hostinger mutualisé = vulnérable, conservation 10 ans |
| 9 | CI/CD déploiement | Renvoyé « à l'implémentation » dans ADR-0008 |
| 10 | Observabilité production | Silence sur logs/monitoring/alerting en prod |
| 11 | Internationalisation / multi-tenants prospective | Poser « non V1, architecture ne doit pas l'empêcher » évite choix bloquants |
| 12 | Gestion changements d'année | Saisie rétrospective, attributions à cheval, clôture d'exercice, déploiement catalogue en cours d'année |

---

## 11. Risques de sur-ingénierie

1. **~9 800 lignes d'`implementation-rules/` avant une ligne de code** — coût de maintenance documentaire à chaque évolution de stack.
2. **4 variantes DTO systématiques par entité** (Data/ListItemData/FormData/StoreData) — excessif pour `Driver` ou `Unavailability`. Adoucir : 2 variantes minimum, 3-4 si l'entité a un listing dense ou un formulaire spécifique.
3. **Interfaces Repository miroir obligatoires** — double le coût pour CRUD simples sans gain testabilité réel. Proposer : interface uniquement pour Repositories complexes ou appelés depuis 2+ Services.
4. **8 canaux de log thématiques** — rend le debug quotidien plus fastidieux. Proposer 3 canaux V1 (`daily`, `fiscal` 90j, `declarations` 365j).
5. **Segmentation `Web/User/` dès V1** — ajoute profondeur de namespace pour un rôle unique. À réévaluer si V2 rôles non prioritaire.
6. **Ordre `<script setup>` canonique à 11 points** — impossible à auditer rigoureusement. Mieux : 5-6 points.
7. **Checklists de 10-15 items par doc** — cumul ~130 items. Un dev ne cochera pas 130 cases. Proposer méta-checklist de 8-10 points transverses.

---

## 12. Dossiers vides et incohérences structurelles

| Dossier | État | Interprétation |
|---|---|---|
| `specifications-fonctionnelles/` | **Vide** | Le cahier des charges joue-t-il ce rôle ? À clarifier ou supprimer. |
| `recherches-fiscales/2025/` | Arborescence seule, zéro contenu | Cohérent avec stratégie MVP 2024, mais V1 réellement utile au client seulement quand 2025 sera instruit (déclaration 2024 déjà passée en avril 2026). |
| `recherches-fiscales/2026/` | Idem | Idem. |
| `taxes-rules/2025.md` | Inexistant | Idem. |
| `taxes-rules/2026.md` | Inexistant | Idem. |

**Remarque temporelle critique** : nous sommes le 24/04/2026. La déclaration fiscale 2024 (les taxes de 2024 dues par les entreprises utilisatrices) se déclare en janvier 2025 — **déjà passée depuis plus d'un an**. Si Floty vise à produire les déclarations 2024 rétroactivement comme pièce justificative d'un contrôle, c'est cohérent. Si Floty vise à produire les déclarations en cours, il lui faut 2025 (déclarable en janvier 2026, aussi passée) voire 2026 (déclarable en janvier 2027). **À clarifier avec le client** : quel exercice fiscal Floty doit-il couvrir à la livraison V1 ?

---

## Annexe — Fichiers audités

### Documents anchor lus directement par l'auditeur principal

- `project-management/cahier_des_charges.md`
- `project-management/roadmap.md`
- `project-management/changelog.md` (extrait 2026-04-24)
- `project-management/decisions/0001-fiscalite-comme-donnee.md`
- `project-management/modele-de-donnees/README.md`
- `project-management/modele-de-donnees/01-schema-metier.md` (extrait)
- `project-management/modele-de-donnees/02-schema-fiscal.md` (extrait)
- `project-management/stack-technique/versions-outils.md` (extrait)
- `project-management/design-system/README.md`
- `project-management/design-system/project/README.md` (extrait)

### Documents audités par agents parallèles

**Agent 1 — ADRs** :
- `project-management/decisions/0002-regles-non-editables-v1.md`
- `project-management/decisions/0003-pdf-snapshots-immuables.md`
- `project-management/decisions/0004-invalidation-par-marquage.md`
- `project-management/decisions/0005-calcul-jour-par-jour.md`
- `project-management/decisions/0006-moteur-de-regles-architecture.md`
- `project-management/decisions/0007-perimetre-v1-mvp.md`
- `project-management/decisions/0008-stack-technique-v1.md`

**Agent 2 — Règles fiscales** :
- `project-management/recherches-fiscales/methodologie.md`
- `project-management/recherches-fiscales/cartographie-taxes.md`
- `project-management/recherches-fiscales/incertitudes.md`
- `project-management/taxes-rules/2024.md`
- `project-management/recherches-fiscales/2024/taxe-co2/decisions.md`
- `project-management/recherches-fiscales/2024/taxe-co2/incertitudes.md`
- `project-management/recherches-fiscales/2024/taxe-polluants/decisions.md`
- `project-management/recherches-fiscales/2024/taxe-polluants/incertitudes.md`
- `project-management/recherches-fiscales/2024/abattements/decisions.md`
- `project-management/recherches-fiscales/2024/exonerations/decisions.md`
- `project-management/recherches-fiscales/2024/cas-particuliers/decisions.md`

**Agent 3 — Implementation rules** :
- `project-management/implementation-rules/architecture-solid.md` (91 Ko)
- `project-management/implementation-rules/assets-vite.md`
- `project-management/implementation-rules/composables-services-utils.md`
- `project-management/implementation-rules/conventions-nommage.md`
- `project-management/implementation-rules/gestion-erreurs.md`
- `project-management/implementation-rules/inertia-navigation.md`
- `project-management/implementation-rules/performance-ui.md`
- `project-management/implementation-rules/pinia-stores.md`
- `project-management/implementation-rules/structure-fichiers.md`
- `project-management/implementation-rules/tests-frontend.md`
- `project-management/implementation-rules/typescript-dto.md`
- `project-management/implementation-rules/vue-composants.md`

**Agent 4 — Plan d'implémentation** :
- `project-management/plan-implementation/README.md`
- `project-management/plan-implementation/docs/spatie-data-configuration.md`
- `project-management/plan-implementation/docs/starter-kit-cleanup.md`
- `project-management/plan-implementation/docs/vitest-configuration.md`
- `project-management/plan-implementation/tasks/phase-00-init/*.md` (12 fichiers)
- 13 READMEs dans `tasks/phase-01-*` à `tasks/phase-13-livraison/`

---

## Méthode d'exploitation de ce rapport

1. **Parcourir P0 de haut en bas** et traiter chaque point — la plupart sont des corrections mécaniques de 5-30 min.
2. **Arbitrer les 3 questions ouvertes de P0.10** avec le client avant de toucher au code.
3. **Rédiger les 4 ADRs prioritaires** (P1) en parallèle des tâches phase 01-02.
4. **Trancher les incertitudes fiscales P1.2** avec l'expert-comptable du client — idéalement dans une passe unique de revue.
5. **Ajouter les tâches P1.3 au plan** avant de démarrer les phases concernées.
6. **Les ADRs P2 et corrections P2** peuvent être traités au fur et à mesure des phases correspondantes.

**Ordre recommandé** : P0.1 → P0.2 → P0.10 → P0.3-P0.9 → ADRs P1 → P1.2 fiscal → P1.3 tâches → P1.4 Inertia v3 → phases d'implémentation.
