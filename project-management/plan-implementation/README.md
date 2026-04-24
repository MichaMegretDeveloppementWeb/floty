# Plan d'implémentation Floty V1

> **Version** : 1.0
> **Date de création** : 24 avril 2026
> **Objet** : parcours séquencé des tâches pour implémenter Floty V1 (MVP), organisé en 14 phases, avec tâches atomiques et fiches projet de référence.
> **Livrable cible** : application Floty V1 prête pour validation client (cf. ADR-0007 — périmètre MVP).

---

## Vue d'ensemble

Ce dossier est la **feuille de route exécutable** de l'implémentation. Il s'articule en deux sous-dossiers :

- **[`tasks/`](tasks/)** — séquence des actions à réaliser, organisée par phase. Chaque tâche = un fichier markdown avec objectif, méthode (pistes à suivre, pas le code complet), critères de validation.
- **[`docs/`](docs/)** — fiches projet de référence citées par les tâches. Ce **ne sont pas des tutoriels génériques** mais des explications ciblées pour Floty (ex: comment structurer la migration `vehicles`, quelle logique précise dans `LcdCumulCalculationService`, quelle UX pour le modal de suppression).

**Les tâches indiquent *ce qu'on fait* et pointent vers les fiches `docs/` pour le *comment précis*.**

---

## Relation avec les autres dossiers `project-management/`

Ce dossier **opérationnalise** les décisions prises ailleurs. Il ne duplique pas les règles :

| Pour quoi ? | Où regarder |
|---|---|
| Périmètre V1 (quelles fonctionnalités ?) | [`../decisions/0007-perimetre-v1-mvp.md`](../decisions/0007-perimetre-v1-mvp.md) |
| Stack technique (quelles versions ?) | [`../decisions/0008-stack-technique-v1.md`](../decisions/0008-stack-technique-v1.md) + [`../stack-technique/versions-outils.md`](../stack-technique/versions-outils.md) |
| Règles d'architecture, conventions, patterns | [`../implementation-rules/`](../implementation-rules/) (12 documents) |
| Schéma BDD détaillé | [`../modele-de-donnees/`](../modele-de-donnees/) |
| Règles fiscales 2024 à implémenter | [`../taxes-rules/2024.md`](../taxes-rules/2024.md) (24 règles) |
| Cahier des charges fonctionnel | [`../cahier_des_charges.md`](../cahier_des_charges.md) |
| Design system (visuel à traduire) | [`../design-system/`](../design-system/) |

Les tâches ci-dessous référencent ces documents systématiquement.

---

## Structure d'une tâche

Chaque fichier `tasks/phase-XX-YYY/NN-description.md` suit ce format :

```markdown
# Task XX.NN — Titre court

> **Phase** : XX
> **Statut** : à faire / en cours / terminée
> **Dépendances** : tasks précédentes requises
> **Estimation** : Xh
> **Références règles** : implementation-rules/xxx.md § Y
> **Fiche projet** : docs/xxx.md (si applicable)

## Objectif
Phrase claire sur le but.

## Périmètre
Ce qui est fait / Ce qui n'est pas fait.

## Méthode
Étapes à suivre (sans code complet, pistes claires).

## Critères de validation
Checklist vérifiable.

## Pièges identifiés
Points d'attention.

## Références
Liens ADR, règles, fiches.
```

---

## Les 14 phases

| # | Phase | Objet | Dépendances |
|---|---|---|---|
| **00** | [`phase-00-init`](tasks/phase-00-init/) | Installation Laravel 13 + Vue Starter Kit, configuration tooling (Pint, Pail, Boost, Wayfinder, Vitest, Spatie Data, TS Transformer), dépôt Git, pipeline CI/CD | — |
| **01** | [`phase-01-fondations-backend`](tasks/phase-01-fondations-backend/) | Structure dossiers `app/` (Actions, Services, Repositories, Data, Contracts, Enums, Exceptions), `BaseAppException`, `RepositoryServiceProvider`, conventions Eloquent, Laravel Pint config | 00 |
| **02** | [`phase-02-design-system-ui-kit`](tasks/phase-02-design-system-ui-kit/) | Traduction du `design-system/` en tokens Tailwind 4 (`@theme`), Layouts `WebLayout`/`UserLayout`, UI Kit custom (Button, Input, Modal, Drawer, Toast, Badge, Card, Table, DataTable), composables `useToast` | 01 |
| **03** | [`phase-03-auth`](tasks/phase-03-auth/) | Migration `users`, `LoginController`/`LoginAction`, `StoreLoginRequest`, Page Login Vue, middleware `auth`, seeder `DemoUserSeeder` | 01, 02 |
| **04** | [`phase-04-vehicle`](tasks/phase-04-vehicle/) | Migrations `vehicles` + `vehicle_fiscal_characteristics` (avec triggers MySQL anti-chevauchement), Models, Enums Vehicle (`VehicleUserType`, `EnergySource`, `HomologationMethod`, etc.), Data DTO (4 variantes), Actions / Services / Repositories, FormRequests, Controller, Policy, Pages Index/Show/Create/Edit avec Partials | 01, 02, 03 |
| **05** | [`phase-05-company`](tasks/phase-05-company/) | Migration `companies`, Model, Data, Actions (Create/Update/Deactivate), Service, Controller, Pages CRUD | 04 |
| **06** | [`phase-06-driver`](tasks/phase-06-driver/) | Migration `drivers`, Model, Data, Actions (incluant `ReplaceDriverAction`), Services, Controller, Pages | 05 |
| **07** | [`phase-07-assignment`](tasks/phase-07-assignment/) | Migration `assignments` avec colonne générée pour `UNIQUE(vehicle_id, date) WHERE deleted_at IS NULL`, Model, Data, Actions (incluant `BulkSaveWeeklyAssignmentsAction`), `LcdCumulReadRepository`, `LcdCumulCalculationService`, Controller | 04, 05, 06 |
| **08** | [`phase-08-unavailability`](tasks/phase-08-unavailability/) | Migration `unavailabilities` avec CHECK `has_fiscal_impact = (type = 'pound')`, Model, Data, Actions, Services, Controller, Pages | 04, 07 |
| **09** | [`phase-09-planning`](tasks/phase-09-planning/) | Vue `Planning/Heatmap` (100 × 52 cellules, `shallowRef`, `v-memo`), `WeeklyEntry` (saisie tableur multi-cellules), `ByCompany` avec compteur LCD temps réel, `ByVehicle` (timeline 52 semaines colorées), Wizard attribution rapide (3 étapes) | 07, 08 |
| **10** | [`phase-10-fiscal-engine`](tasks/phase-10-fiscal-engine/) | Migration `fiscal_rules`, interface `FiscalRule` + 5 sous-types (`ClassificationRule`, `TarificationRule`, `ExonerationRule`, `AbatementRule`, `TransversalRule`), `FiscalRulePipeline` (8 étapes), `FiscalRuleEngine`, `FiscalRuleRegistry`, seeder des 24 règles 2024 (`Rules2024Seeder`), tests unitaires par règle | 04, 07 |
| **11** | [`phase-11-declarations`](tasks/phase-11-declarations/) | Migrations `declarations` + `declaration_pdfs`, Actions (`CalculateDeclarationAction`, `ChangeDeclarationStatusAction`, `DetectDeclarationInvalidationAction`), `DeclarationInvalidationDetector`, Controllers, Pages `Declarations/Index` et `Show` (avec historique PDF + badge invalidation), page `FiscalRules` lecture-seule | 10 |
| **12** | [`phase-12-pdf`](tasks/phase-12-pdf/) | `DeclarationPdfRenderer` (wrapper DomPDF), template HTML/CSS compatible DomPDF (sans flex/grid), `DeclarationSnapshotService`, `DeclarationPdfStorage` (filesystem local), hash SHA-256 snapshot + PDF, `GenerateDeclarationPdfAction` avec transaction Laravel | 11 |
| **13** | [`phase-13-livraison`](tasks/phase-13-livraison/) | Dashboard KPI, barre de recherche globale, pages publiques (`Home`, `MentionsLegales`), pages d'erreur Inertia (404, 500, 503), audit Lighthouse + correctifs, checklist livraison client, déploiement Hostinger Business + pipeline CI/CD effectif | 11, 12 |

---

## Ordre de développement recommandé

Les phases sont **séquentielles** par défaut (chaque phase dépend de la précédente). Cependant **certaines peuvent être parallélisées** :

- La phase **02 (design system + UI Kit)** peut démarrer **en parallèle** de la phase **01 (fondations backend)** si deux personnes travaillent.
- La phase **04 (Vehicle)** est le **modèle** pour les phases **05-08** (qui reprennent les mêmes patterns). Une fois 04 solide, 05/06/07/08 s'enchaînent rapidement en copiant les patterns.
- La phase **10 (moteur fiscal)** ne dépend pas de 05/06/07/08 mais de **04 (Vehicle)** et **07 (Assignment)** (pour les données). Peut être commencée en parallèle de 08-09.

Pour Floty V1 mono-développeur : **ordre strict 00 → 13**.

---

## Estimation globale (à affiner en implémentation)

| Bloc | Volume estimé |
|---|---|
| Phases 00-01 (init + fondations) | 2-3 jours |
| Phase 02 (design system → UI Kit) | 4-6 jours |
| Phase 03 (auth) | 1-2 jours |
| Phase 04 (Vehicle — modèle) | 5-7 jours |
| Phases 05-08 (autres domaines, moins fournis) | 2-3 jours chacun |
| Phase 09 (Planning vues) — riche en UX | 5-7 jours |
| Phase 10 (moteur fiscal) | 7-10 jours (coeur de l'app) |
| Phase 11 (déclarations) | 3-4 jours |
| Phase 12 (PDF) | 2-3 jours |
| Phase 13 (livraison) | 3-5 jours |
| **Total V1** | **~45-60 jours de dev** |

---

## Convention de progression

À mesure que les tâches sont terminées, **mettre à jour le statut dans chaque fichier** (`Statut : terminée` + date + lien commit). Ça donne une trace complète utilisable pour les prochaines versions et pour transmettre au client.

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — arborescence, README index, liste des 14 phases avec descriptions, ordre de développement, estimation. |
