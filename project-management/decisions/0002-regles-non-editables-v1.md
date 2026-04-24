# ADR-0002 — Règles fiscales non éditables depuis l'application en V1

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Décidée initialement** : 20 avril 2026 (brainstorming de cadrage)
> **Formalisée** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

L'ADR-0001 a acté que les règles fiscales sont traitées comme des données de première classe, stockées en base et interprétées par un moteur générique. Une question secondaire mais structurante s'est posée : **qui peut créer, modifier, désactiver ou supprimer une règle fiscale ?**

Deux approches principales étaient possibles :

1. **Édition libre via l'application** : les utilisateurs Floty (à commencer par Renaud) peuvent créer/modifier des règles via une interface dédiée. Flexibilité maximale pour le client, mais risque élevé de casser les calculs.
2. **Édition contrôlée par l'équipe technique** : les règles sont déployées exclusivement par l'équipe prestataire via des seeders de base de données. L'application ne propose que la **consultation** des règles.

Le choix avait des conséquences majeures sur la complexité de l'application V1, la responsabilité contractuelle, la sécurité des calculs, et le modèle économique du prestataire.

## Décision

**En V1, les règles fiscales sont exclusivement éditables par l'équipe technique (prestataire) via déploiement de seeders.** L'application propose uniquement la **consultation** des règles, pas leur édition.

Concrètement :

- La table `fiscal_rules` de l'application n'a pas d'écran d'édition.
- Les seuls opérateurs habilités à modifier le catalogue sont les développeurs du projet, via pull request et déploiement contrôlé.
- Les seeders sont versionnés dans le repository git du projet, testés, et exécutés lors d'un déploiement serveur.
- Un retour du client (correction d'interprétation, ajustement après expertise comptable, ajout d'année fiscale) déclenche une prestation technique facturée séparément : nous produisons un nouveau seeder, nous le déployons, nous facturons l'intervention.

Cette décision est **spécifique à la V1**. Elle pourra être rediscutée ultérieurement (V2, V3…) si le besoin d'autonomie du client évolue.

## Justification

### Sûreté des calculs

Les déclarations fiscales produites par Floty engagent directement la responsabilité fiscale des entreprises utilisatrices. Une règle mal saisie (borne de tranche erronée, condition d'exonération mal formulée) peut produire des milliers d'euros d'erreur multipliés par des dizaines de véhicules. Limiter l'édition à l'équipe technique — avec processus de revue, tests unitaires, et déploiement contrôlé — est le seul moyen réaliste d'assurer la justesse en V1.

### Simplicité d'implémentation

Construire une interface d'édition de règles est un projet à part entière : formulaires dynamiques adaptés aux différents types (classification, tarification, exonération, abattement, transversale), validation en temps réel, historique d'édition, rollback, tests de non-régression automatiques, permissions fines… En V1, le scope est déjà chargé. Reporter cette brique évite de surcharger le planning et de prendre un risque technique inutile.

### Expertise technique requise

Modéliser correctement une règle fiscale suppose une compréhension du code CIBS, de la doctrine BOFiP, et de l'articulation avec les autres règles du catalogue. C'est une expertise que l'équipe technique a développée à travers la phase de recherche fiscale documentée dans `project-management/recherches-fiscales/`. Demander à Renaud (ou à un expert-comptable ponctuel) de saisir une règle directement en application pose un risque d'erreur sémantique difficile à détecter.

### Modèle économique du prestataire

La mise à jour annuelle des règles (nouvelle année fiscale, nouveau barème, correction réglementaire) est une **prestation récurrente** qui justifie un contrat de maintenance. Traiter cette mise à jour comme une intervention technique facturable est cohérent avec le modèle d'une prestation freelance continue.

### Transparence préservée

Le client n'a pas **besoin** d'éditer les règles pour avoir confiance. Il a besoin de les **consulter**, et cette consultation est pleinement assurée (cf. cahier des charges § 3.13 et ADR-0001). La page « Fiscalité → Règles de calcul » rend chaque règle lisible, avec référence aux sources (CIBS, BOFiP). La transparence est maintenue sans nécessiter l'édition.

## Alternatives écartées

### Alternative 1 — Édition libre via UI complète

Une interface complète permettant à un utilisateur habilité d'ajouter, modifier, désactiver des règles.

**Rejetée** pour les raisons suivantes :

- Complexité de développement disproportionnée pour une V1 (probablement 20-30 % du coût de l'application pour cette seule fonctionnalité).
- Risque de casser des calculs en production par fausse manipulation.
- Nécessité d'un système de permissions et d'audit trail avant de l'ouvrir à tout utilisateur.
- Non-demandé par Renaud, qui préfère s'appuyer sur la compétence technique du prestataire pour les évolutions.

### Alternative 2 — Édition partielle via UI (paramètres uniquement)

Approche intermédiaire : les métadonnées des règles (nom, description, période) et leurs **paramètres numériques** (tranches de barèmes, tarifs, seuils) éditables via UI, mais pas la logique algorithmique.

**Rejetée** pour les raisons suivantes :

- Sûreté compromise : un tarif saisi avec un zéro de trop produit des résultats catastrophiques sans alerte automatique.
- Complexité encore substantielle (formulaires dynamiques, validation des ranges, cohérence inter-paramètres).
- Bénéfice marginal : la modification annuelle d'un barème est, en pratique, une opération qu'il est raisonnable de traiter comme une prestation technique ponctuelle.

**Note prospective** : cette alternative pourra être reconsidérée en V2 ou V3 si un besoin d'autonomie précis émerge côté client.

### Alternative 3 — Édition par export/import CSV

Approche minimaliste : l'utilisateur exporte les règles en CSV, les modifie dans un tableur, les réimporte.

**Rejetée** pour les mêmes raisons de sûreté (sans aucune validation de cohérence, un tableur permet toutes les erreurs), et parce qu'elle ne résout pas le problème de l'expertise nécessaire pour modifier correctement une règle fiscale.

## Conséquences

### Conséquences positives

- **V1 simplifié** : pas d'UI d'édition à concevoir, développer, tester. Focus sur le cœur métier (calcul de taxes, planning, PDF).
- **Qualité contrôlée** : chaque règle passe par le processus de recherche documentée (`project-management/recherches-fiscales/`) avant d'être déployée.
- **Traçabilité git** : chaque modification de règle est un commit identifié, avec auteur, date, justification, et le diff exact.
- **Tests unitaires possibles** : les règles sont du code TypeScript/PHP/... testable avec les outils standards (cf. taxes-rules/2024.md qui fournit déjà les jeux de tests).
- **Modèle économique clair** : les évolutions de règles sont une prestation technique facturable.

### Conséquences techniques

- La table `fiscal_rules` n'a pas d'écran d'édition dans l'application Floty.
- Chaque règle existe en deux endroits : son code (classe dans le repo) et son métadonnée (ligne en base, déployée par seeder).
- Les seeders sont le seul mécanisme de création/modification de règles en base.
- Un processus de revue de code (pull request) s'applique à toute modification de règle avant déploiement.

### Conséquences produit

- La page « Fiscalité → Règles de calcul » est **en lecture seule** en V1. Pas de boutons « Modifier », « Ajouter une règle ». Juste une consultation riche avec filtres par année, par taxe, par type.
- Les corrections ponctuelles (retour expert-comptable) sont traitées en dehors de l'application : le client nous les remonte, nous produisons un seeder correcteur, nous déployons.

### Conséquences organisationnelles

- Chaque début d'année fiscale, l'équipe technique produit la recherche fiscale pour la nouvelle année et déploie les seeders correspondants (facturé comme prestation continue).
- Les retours expert-comptable arrivent sous forme de liste ; chaque point donne lieu à une étude et un correctif seeder.

### Conséquences économiques

- Le client accepte implicitement un contrat de maintenance technique continue avec le prestataire.
- Les économies réalisées par l'exonération LCD (cœur de valeur Floty — cf. R-2024-021 dans `taxes-rules/2024.md`) justifient largement ce coût de maintenance.

### Évolution future

- En V2 ou V3, une fonctionnalité d'édition partielle ou complète pourra être envisagée si le client exprime un besoin d'autonomie accrue.
- L'architecture du moteur de règles (ADR-0006) est prévue pour s'adapter : si un jour l'édition UI est ouverte, il suffira d'ajouter l'écran sans toucher au moteur ou aux règles existantes.

## Liens

- ADR-0001 — La fiscalité est une donnée, pas du code
- ADR-0003 — PDF et snapshots immuables des déclarations
- ADR-0006 — Architecture du moteur de règles
- `project-management/recherches-fiscales/methodologie.md` — processus de production des règles
- `project-management/cahier_des_charges.md` § 3.13 — page de consultation des règles

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — formalisation d'une décision prise dès le brainstorming de cadrage (20/04/2026). |
