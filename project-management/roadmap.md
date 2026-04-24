# Roadmap Floty

> **Version** : 1.0
> **Date** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)
> **Objet** : vue d'ensemble navigable du produit, version par version. Document synthétique ; pour le détail, se référer à l'ADR-0007 et au cahier des charges § 9.

---

## Principe directeur

Floty se construit par **versions successives cohérentes**, chacune apportant un bloc de valeur testable et validable en conditions réelles avant de passer à la suivante. Le principe n'est pas de livrer beaucoup rapidement, mais de livrer **bien** et **par étapes mesurées**.

Chaque version :
- a un objectif clair et un périmètre fermé
- est livrée complète sur son périmètre
- permet au client de l'utiliser en production avant la suivante

---

## Vue d'ensemble

| Version | Objectif principal | Statut | Livraison |
|---|---|---|---|
| **V1** (MVP) | Calcul fiscal opérationnel + planning + PDF récapitulatif | En conception / implémentation | À déterminer |
| **V1.2** | Module facturation loyers (commerciale, distincte du fiscal) | Planifiée | Post-V1 |
| **V2** | Analytics, alertes, audit, récurrence, rôles, notifications | Envisagée | À définir après retours V1 |
| **V3** | IA (alertes, suggestions, prédictif, NLP), mobile, intégrations | Envisagée | Long terme |

---

## V1 (MVP) — Cœur fiscal et planning

### Objectif

Fournir à Renaud un outil opérationnel pour :
1. Gérer la flotte et les entreprises utilisatrices.
2. Saisir les attributions de véhicules au jour le jour.
3. Calculer les taxes fiscales (CO₂ + polluants) dues par chaque entreprise utilisatrice.
4. Produire les PDF récapitulatifs fiscaux annuels avec snapshot immuable.

### Périmètre V1

**Authentification & utilisateurs** : email + mot de passe, création par seeder côté prestataire, pas de rôles.

**Entités métier (CRUD complet)** :
- Véhicules, avec **historisation des caractéristiques fiscales**
- Entreprises utilisatrices
- Conducteurs

**Saisie et planning** :
- Saisie hebdomadaire tableur
- Wizard d'attribution rapide (3 étapes)
- Indisponibilités (dont distinction fiscale fourrière vs autres)
- Heatmap globale annuelle
- Vue par entreprise (avec compteur LCD par couple en temps réel)
- Vue par véhicule
- Panneau latéral de détail semaine

**Fiscal** :
- Moteur de règles complet (ADR-0006) avec les **24 règles 2024** du catalogue
- Codage de toutes les exonérations, y compris celles marquées « inactives par défaut »
- Page « Règles de calcul » en lecture seule
- Page « Déclarations » avec statuts + historique PDF + badges d'invalidation
- **PDF récapitulatif fiscal** (seul document exporté en V1)

**Application** :
- Sidebar + top bar permanentes, sélecteur d'année
- Dashboard simple (KPI calculés, pas d'IA)
- Barre de recherche globale basique (texte)
- Page d'accueil publique minimale + page mentions légales + page de connexion
- Design responsive (desktop prioritaire)
- Hébergement Hostinger Paris

### Hors V1

Tout ce qui n'est pas listé ci-dessus. Voir sections V1.2, V2, V3 et « Exclusions durables » ci-dessous.

### Référence

- **ADR-0007** (`project-management/decisions/0007-perimetre-v1-mvp.md`) — cadrage détaillé, justifications, alternatives écartées
- Cahier des charges § 3 (vues et interface) v1.5

---

## V1.2 — Module facturation loyers

### Objectif

Étendre Floty au volet **commercial** : permettre à la société de location (Renaud) de facturer les loyers aux entreprises utilisatrices sur la base des attributions enregistrées dans Floty. Cette facturation est **distincte du volet fiscal** : il ne s'agit pas d'un document transmis à l'administration mais d'une facture commerciale standard.

### Périmètre V1.2

- **Grilles tarifaires de location** configurables par la société de location (journalier, mensuel…).
- **Calcul automatique des loyers** dus par chaque entreprise utilisatrice à partir des attributions déjà enregistrées en V1.
- **PDF facture commerciale** aux entreprises utilisatrices — nouveau type de document, distinct du PDF récapitulatif fiscal (V1).
- **Envoi des factures** aux entreprises utilisatrices.

### Positionnement

- Extension construite **par-dessus le modèle V1** (mêmes données d'attribution, flux parallèle).
- **Ne modifie pas** le volet fiscal : le calcul des taxes continue de fonctionner comme en V1.
- Acté avec le client dès les premiers échanges (cf. mémoire projet, roadmap initiale).

### Référence

- Mémoire projet `roadmap_v12_facturation.md` (auto-memory)
- Cahier des charges § 9.1 v1.5

---

## V2 — Évolutions fonctionnelles

### Objectif

Enrichir Floty de fonctionnalités de **confort et de reporting** sur la base des retours d'usage V1 (et V1.2). La priorisation précise se fait **après livraison et appropriation** par le client.

### Pistes V2 (à prioriser après V1)

- Analytics multi-années (comparaisons, graphiques, détection de variations)
- Alertes sur le dashboard (véhicules sous-utilisés, CT à prévoir, conducteurs inactifs, exonérations applicables) — logique de seuils sans IA
- Audit trail complet des modifications d'attribution
- Récurrence des attributions (copier un pattern de semaine)
- Gestion de rôles et permissions
- Formulaire officiel pré-rempli (3310-A-SD / 3517)
- Notifications par email
- Mode multi-société (société de location comme entité gérée)

### Positionnement

- Aucune de ces fonctionnalités n'est bloquante pour l'usage V1.
- Leur développement est conditionné aux retours d'usage — il est possible qu'une fonctionnalité listée ci-dessus ne soit jamais prioritisée si elle s'avère non-essentielle à l'usage réel.

### Référence

- Cahier des charges § 9.2 v1.5

---

## V3 — Sophistications et IA

### Objectif

Positionner Floty sur un niveau **avancé** grâce à l'intelligence artificielle et aux intégrations externes, une fois le socle parfaitement stabilisé.

### Pistes V3

- Détection d'anomalies par IA (patterns d'utilisation, seuils adaptatifs)
- Suggestions d'attribution IA dans le wizard (optimisation LCD)
- Analyse fiscale prédictive (projections, recommandations de renouvellement)
- Recherche en langage naturel (⌘K avec NLP)
- Auto-complétion depuis API SIV / carte grise
- Application mobile dédiée
- Intégration comptable (export vers logiciels comptables)
- Notifications push

### Positionnement

- Ces fonctionnalités supposent un socle V1 + V2 rodé en conditions réelles.
- Elles apportent une dimension avancée mais ne sont pas nécessaires à la mission principale de Floty.

### Référence

- Cahier des charges § 9.3 v1.5

---

## Exclusions durables (non planifiées)

Certaines fonctionnalités sont **exclues de manière durable** du périmètre Floty, indépendamment de la version. Elles ne sont pas « reportées » mais « non retenues ».

### Import CSV / Excel

**Exclu durablement**. Raison : les données historiques disponibles côté client (Renaud) sont inexploitables (erreurs de saisie manuelle antérieure, cœur même de la motivation à commander Floty). La reprise d'historique se fait par saisie contrôlée via les interfaces dédiées (saisie hebdomadaire tableur, wizard).

Réouverture possible uniquement si à l'avenir Floty était déployé chez d'autres clients disposant de données propres. Aucune planification à ce jour.

### Gestion de la TAI

**Exclu durablement**. La Taxe Annuelle Incitative au verdissement (introduite par LF 2025) est applicable aux propriétaires de flotte ≥ 100 véhicules. Or Floty calcule exclusivement les taxes dues par les **entreprises utilisatrices**, prises individuellement (qui n'atteignent jamais ce seuil). La société de location propriétaire de la flotte peut être concernée par la TAI mais n'est pas dans le périmètre de calcul Floty. Cf. cartographie phase 0 (`recherches-fiscales/cartographie-taxes.md`).

### PDF fiche véhicule et PDF planning

**Non prévus**. Seuls deux PDF sont dans la roadmap : le récapitulatif fiscal (V1) et la facture commerciale (V1.2). Les autres exports PDF envisagés au démarrage du projet n'ont pas démontré une valeur client justifiant leur développement.

### Gestion des coûts véhicule

**Non prévue**. La facturation V1.2 couvre exclusivement les loyers que la société de location facture aux entreprises utilisatrices. La gestion des coûts opérationnels (maintenance, carburant, assurance) n'est pas dans le périmètre Floty.

### Référence

- Cahier des charges § 9.4 v1.5
- ADR-0007 section « Exclusions durables »

---

## Gouvernance de la roadmap

- Cette roadmap est **indicative** pour V2 et V3 — ces versions seront priorisées et détaillées après livraison des versions précédentes.
- V1 et V1.2 ont un périmètre **arrêté** (cf. ADR-0007).
- Toute évolution majeure du périmètre V1 ou V1.2 passe par un amendement d'ADR-0007 documenté.
- Toute réouverture d'une exclusion durable (notamment l'import CSV, ou l'arrivée d'un nouveau client) requiert un ADR dédié.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Création initiale — vue d'ensemble V1 / V1.2 / V2 / V3 / exclusions durables, cadrage aligné avec ADR-0007 et cahier des charges § 9 v1.5. |
