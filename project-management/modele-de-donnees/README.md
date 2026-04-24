# Modèle de données Floty — V1

> **Version** : 1.1
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)
> **Objet** : schéma relationnel complet du périmètre V1 (MVP), décisions structurantes, stratégies transverses.
> **SGBD V1** : MySQL 8 (Hostinger Business). Cf. ADR-0008. Le schéma original est rédigé en mode agnostique (avec une préférence terminologique PostgreSQL) ; les sections « 0. Adaptation MySQL 8 » dans `01-schema-metier.md` et `02-schema-fiscal.md` documentent le mapping.

---

## Positionnement

Ce dossier constitue le **livrable de l'étape 4 du workflow projet** (modèle de données), établi après :

- ADR-0001 à ADR-0007 (décisions d'architecture)
- Catalogue de règles 2024 (`taxes-rules/2024.md`) — 24 règles R-2024-001 à R-2024-024
- Cahier des charges v1.5 (notamment § 2 entités, § 3 vues, § 5 règles fiscales)

Il sert de **base pour l'étape 5** (stack technique) et l'étape 6 (implémentation MVP 2024).

---

## Structure du dossier

| Fichier | Contenu |
|---|---|
| [`README.md`](README.md) | Vue d'ensemble, principes fondateurs (ce document) |
| [`01-schema-metier.md`](01-schema-metier.md) | Tables métier : utilisateurs, véhicules + historisation, entreprises utilisatrices, conducteurs, attributions, indisponibilités |
| [`02-schema-fiscal.md`](02-schema-fiscal.md) | Tables fiscales : règles fiscales (métadonnées), déclarations, PDF générés, snapshots |
| [`03-strategie-suppression.md`](03-strategie-suppression.md) | Stratégie de suppression (soft delete par défaut + modal avec option suppression physique) |
| [`04-strategie-cache.md`](04-strategie-cache.md) | Cache Laravel pour le compteur LCD temps réel et les calculs fiscaux |

---

## Principes fondateurs du modèle

### 1. Traçabilité temporelle complète

Toutes les entités métier qui peuvent évoluer dans le temps sont **historisées** — on ne perd jamais une version antérieure. Cela concerne principalement :

- Les **caractéristiques fiscales du véhicule** (source d'énergie, CO₂ WLTP/NEDC, puissance administrative, norme Euro, etc.) via la table `vehicle_fiscal_characteristics` avec `effective_from` / `effective_to`.
- Les **attributions** et **indisponibilités**, datées au jour près (ADR-0005 : calcul jour par jour).
- Les **règles fiscales** via le champ `applicability_period_start/end` et `version_internal`.
- Les **déclarations** (état, PDF générés, snapshots, hash, invalidation — ADR-0003 et ADR-0004).

### 2. Calcul journalier, cumul annuel

Une attribution = une ligne par (véhicule, entreprise, date). Cette granularité jour-par-jour est imposée par ADR-0005 et permet :

- Un calcul fiscal exact (prorata journalier, exonération LCD par couple avec cumul annuel).
- Une re-calculabilité totale : à tout moment, les taxes peuvent être recalculées depuis les données brutes.
- Une saisie tableur naturelle (grille véhicules × jours).

### 3. Cohérence par contraintes en base

Les invariants métier sont portés autant que possible par des contraintes SQL (clés uniques, clés étrangères, check constraints, triggers MySQL pour l'anti-chevauchement) plutôt que par de la logique applicative seule. Exemples :

- Un véhicule ne peut être attribué qu'à **une seule entreprise par jour** (unique sur `(vehicle_id, date)`, via colonne générée MySQL pour le filtre soft delete — cf. `01-schema-metier.md` § 0.2).
- Les périodes de caractéristiques fiscales d'un véhicule **ne se chevauchent jamais** (3 lignes de défense en MySQL : validation applicative dans le service + trigger BEFORE INSERT/UPDATE + verrou pessimiste — cf. `01-schema-metier.md` § 0.3).
- Une indisponibilité a ses bornes dans l'ordre (`start_date ≤ end_date`, CHECK constraint MySQL 8).

### 4. Suppression logique par défaut, physique sur demande explicite

Toutes les entités principales portent `deleted_at` (soft delete Laravel). La suppression physique est possible depuis l'application mais **gouvernée par un modal à deux niveaux** (cf. `03-strategie-suppression.md`) : l'option « suppression définitive » est désactivée par défaut, à activer explicitement par clic, avec avertissement. Motivation : protéger l'historique fiscal référencé par des déclarations et PDF immuables, tout en laissant la main à l'utilisateur pour les vraies erreurs (saisie test, doublon créé par mégarde).

### 5. Séparation données courantes / données immuables

Le modèle distingue clairement :

- **Données vivantes** (véhicules, attributions, indisponibilités…) — modifiables, historisées.
- **Données immuables** (PDF générés + snapshots JSON, cf. ADR-0003) — jamais modifiées une fois persistées. Une modification ultérieure des données courantes **n'affecte pas** le PDF généré : elle peut seulement déclencher une invalidation par hash (ADR-0004).

### 6. Modèle ouvert sur les évolutions V1.2 / V2

Le schéma V1 anticipe les ajouts futurs sans les implémenter :

- **V1.2 facturation loyers** (cf. mémoire `roadmap_v12_facturation.md`) : le modèle `assignments` servira aussi de base au calcul des loyers commerciaux. Pas de champ `loyer` en V1, mais la granularité (jour × couple) est celle qu'il faudra.
- **V2 audit trail** : `deleted_at` et les timestamps `created_at/updated_at` fournissent déjà un audit partiel. L'extension vers un audit trail complet (auteur, ancien/nouvel état) pourra utiliser des tables dédiées `*_audit` sans refonte du schéma principal.
- **V2 rôles** : la table `users` ne porte pas de rôle en V1 mais gardera sa structure quand une table `roles` et une jointure seront ajoutées en V2.

---

## Conventions de nommage

- **Tables** : snake_case, pluriel (`vehicles`, `assignments`).
- **Colonnes** : snake_case, singulier (`co2_wltp`, `effective_from`).
- **Clés primaires** : `id` (bigint auto-incrémenté, convention Laravel).
- **Clés étrangères** : `{table_singulier}_id` (`vehicle_id`, `company_id`).
- **Timestamps Laravel** : `created_at`, `updated_at`, `deleted_at` (nullable, soft delete).
- **Booléens** : préfixe `is_` (`is_active`) ou nommage explicite (`handicap_transport`).
- **Énumérations** : `VARCHAR` + CHECK constraint MySQL 8, validé applicativement via Laravel Backed Enum (cf. ADR-0008).

## Conventions de types — implémentation MySQL 8

| Besoin | Type MySQL 8 (Floty V1) | Note |
|---|---|---|
| Identifiant technique | `BIGINT UNSIGNED AUTO_INCREMENT` | Convention Laravel |
| Date pure (jour d'attribution) | `DATE` | |
| Instant technique (horodatage) | `TIMESTAMP` (UTC stocké) | Pas de `TIMESTAMPTZ` natif sur MySQL — convention Floty : tout en UTC, conversion locale uniquement à l'affichage |
| Montant fiscal | `INTEGER` (euros entiers, arrondi fiscal R-2024-003) ou `DECIMAL(10,2)` pour les valeurs intermédiaires | |
| CO₂ (g/km) | `INTEGER` | |
| Puissance administrative (CV) | `SMALLINT` | |
| Texte court (code, identifiant métier) | `VARCHAR(32)` ou moins | |
| Description libre | `TEXT` | |
| Données structurées flexibles (snapshot, legal_basis) | `JSON` | Pas de `JSONB` (PostgreSQL-only) ; MySQL 8 stocke `JSON` en binaire interne avec parsing optimisé |
| Hash (SHA-256) | `CHAR(64)` | |
| Booléen | `TINYINT(1)` | Convention Laravel pour `bool` |
| Énumération | `VARCHAR(40)` + CHECK | Validée applicativement via Laravel Backed Enum |

> Pour le détail complet du mapping PostgreSQL → MySQL et la justification de chaque choix, voir `01-schema-metier.md` § 0 « Adaptation MySQL 8 ».

---

## Liens

- **ADR-0001** — Fiscalité comme donnée
- **ADR-0003** — PDF et snapshots immuables
- **ADR-0004** — Invalidation par marquage
- **ADR-0005** — Calcul fiscal jour-par-jour
- **ADR-0006** — Architecture du moteur de règles (7 décisions)
- **ADR-0007** — Périmètre V1 MVP
- **ADR-0008** — Stack technique V1 (MySQL 8 acté)
- **Cahier des charges** v1.5 § 2 (entités), § 3 (vues), § 5 (fiscal)
- **Catalogue 2024** — `taxes-rules/2024.md` (24 règles)

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.1 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — A3 corrigé : alignement sur ADR-0008 (stack MySQL 8). Refonte du tableau « Conventions de types » pour MySQL (TIMESTAMP UTC au lieu de TIMESTAMPTZ, JSON au lieu de JSONB, TINYINT(1) booléens, VARCHAR+CHECK pour enums), ajout du SGBD V1 en tête, ajout de ADR-0008 dans les liens, mise à jour du § 3 « Cohérence par contraintes en base » pour mentionner les 3 lignes de défense MySQL et les triggers, référence vers § 0 des fichiers schema. |
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — principes fondateurs, conventions, index du dossier. |
