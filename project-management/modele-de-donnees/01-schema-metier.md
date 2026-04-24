# Schéma de données — Entités métier

> **Version** : 1.1
> **Date** : 24 avril 2026
> **Scope** : utilisateurs, véhicules (avec historisation fiscale), entreprises utilisatrices, conducteurs, attributions, indisponibilités.
> **SGBD V1** : MySQL 8 (Hostinger Business). Cf. ADR-0008.

---

## 0. Adaptation MySQL 8 — implémentation V1

Le schéma ci-dessous a été initialement rédigé dans une logique **agnostique au SGBD** avec une préférence PostgreSQL (où certaines contraintes natives — exclusion GIST, JSONB, index partiels — facilitent la modélisation). Suite au verrouillage de la stack technique (ADR-0008), Floty V1 utilise **MySQL 8** sur Hostinger Business. Cette section documente les **adaptations spécifiques** à appliquer lors de l'implémentation des migrations.

### 0.1 Mapping des types

| Type initial (agnostique / PostgreSQL) | Implémentation MySQL 8 | Note |
|---|---|---|
| `BIGINT` | `BIGINT UNSIGNED AUTO_INCREMENT` | Convention Laravel |
| `VARCHAR(n)` | `VARCHAR(n)` | Identique |
| `TEXT` | `TEXT` | Identique |
| `DATE` | `DATE` | Identique |
| `TIMESTAMPTZ` (avec timezone) | `TIMESTAMP` (UTC stocké via Eloquent) | MySQL 8 supporte `TIMESTAMP WITH TIME ZONE` partiellement ; convention Floty : tout en UTC, conversion locale uniquement à l'affichage |
| `INTEGER` | `INT` | Identique |
| `SMALLINT` | `SMALLINT` | Identique |
| `BOOLEAN` | `TINYINT(1)` | Mapping standard MySQL pour `bool` Laravel |
| `JSONB` | `JSON` | MySQL 8 stocke en binaire interne, performances équivalentes pour Floty V1 |
| `CHAR(n)` | `CHAR(n)` | Identique |
| `DECIMAL(p, s)` | `DECIMAL(p, s)` | Identique |
| `ENUM (PostgreSQL CREATE TYPE)` | **non utilisé** : `VARCHAR` + `CHECK` constraint, validé applicativement via Laravel Enum | MySQL `ENUM` natif est moins flexible que les enums PHP backed |

### 0.2 Adaptation des index partiels

PostgreSQL supporte les index partiels (`CREATE INDEX ... WHERE deleted_at IS NULL`). **MySQL 8 ne les supporte pas**. Trois stratégies utilisées en Floty V1 :

| Cas | Stratégie MySQL |
|---|---|
| `UNIQUE (immatriculation) WHERE deleted_at IS NULL` | **Colonne générée** : ajouter `immatriculation_active VARCHAR(20) GENERATED ALWAYS AS (IF(deleted_at IS NULL, immatriculation, NULL)) STORED` puis `UNIQUE (immatriculation_active)` |
| `INDEX (vehicle_id) WHERE effective_to IS NULL` | **Colonne générée** : `is_current TINYINT(1) GENERATED ALWAYS AS (IF(effective_to IS NULL, 1, NULL)) STORED` puis `INDEX (vehicle_id, is_current)` |
| `UNIQUE (vehicle_id, date) WHERE deleted_at IS NULL` (attributions) | **Colonne générée composée** ou trigger BEFORE INSERT/UPDATE qui rejette les doublons actifs |

> Le pattern « colonne générée + index sur la colonne » est le plus robuste sur MySQL 8 et reste lisible. Préféré aux triggers quand applicable.

### 0.3 Adaptation des exclusion constraints

Le schéma propose une `EXCLUDE USING gist` PostgreSQL pour empêcher le chevauchement de périodes dans `vehicle_fiscal_characteristics`. **MySQL ne supporte pas les exclusion constraints**. Trois lignes de défense en Floty V1 :

1. **Validation applicative** dans le `VehicleFiscalCharacteristicsService` (cf. `architecture-solid.md` § 5) — c'est la première et principale ligne de défense, qui calcule `effective_to` et lève `VehicleFiscalCharacteristicsValidationException` si chevauchement détecté.
2. **Trigger MySQL `BEFORE INSERT` / `BEFORE UPDATE`** sur `vehicle_fiscal_characteristics` qui rejette les insertions/mises à jour créant un chevauchement (filet de sécurité au niveau BDD, contre-attaque pour les modifications hors flux applicatif).
3. **Verrou pessimiste** (`SELECT ... FOR UPDATE`) dans le service quand on lit la version courante avant d'en créer une nouvelle (évite les races concurrentes).

### 0.4 Triggers MySQL à créer

Liste exhaustive des triggers nécessaires en Floty V1 :

| Trigger | Table | Quand | Rôle |
|---|---|---|---|
| `vfc_no_overlap_insert` | `vehicle_fiscal_characteristics` | `BEFORE INSERT` | Rejeter si chevauchement avec une période existante |
| `vfc_no_overlap_update` | `vehicle_fiscal_characteristics` | `BEFORE UPDATE` | Idem |
| `attribution_no_indispo_insert` | `assignments` | `BEFORE INSERT` | Rejeter si jour couvert par une indisponibilité fourrière du véhicule |
| `indispo_fiscal_impact_consistency` | `unavailabilities` | `BEFORE INSERT/UPDATE` | Garantir `has_fiscal_impact = (type = 'pound')` |

Exemple de trigger pour `vehicle_fiscal_characteristics` :

```sql
DELIMITER //

CREATE TRIGGER vfc_no_overlap_insert
BEFORE INSERT ON vehicle_fiscal_characteristics
FOR EACH ROW
BEGIN
    DECLARE overlap_count INT;

    SELECT COUNT(*) INTO overlap_count
    FROM vehicle_fiscal_characteristics
    WHERE vehicle_id = NEW.vehicle_id
      AND id != COALESCE(NEW.id, 0)
      AND (
          (NEW.effective_to IS NULL AND effective_to IS NULL)
          OR (NEW.effective_to IS NULL AND effective_to >= NEW.effective_from)
          OR (effective_to IS NULL AND NEW.effective_from <= effective_to)
          OR (NEW.effective_from <= effective_to AND NEW.effective_to >= effective_from)
      );

    IF overlap_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Chevauchement de périodes pour ce véhicule';
    END IF;
END //

DELIMITER ;
```

> **Note** : Laravel ne génère pas les triggers via le schema builder. Ils sont créés via `DB::unprepared(...)` dans une migration dédiée (ex: `add_overlap_triggers_to_vehicle_fiscal_characteristics_table.php`).

### 0.5 Check constraints

MySQL 8 supporte les `CHECK` constraints depuis 8.0.16. On les utilise pour les invariants intra-ligne (cohérence entre colonnes d'une même ligne) :

```sql
ALTER TABLE vehicle_fiscal_characteristics
ADD CONSTRAINT chk_effective_dates
CHECK (effective_to IS NULL OR effective_from <= effective_to);

ALTER TABLE indisponibilites
ADD CONSTRAINT chk_indispo_dates
CHECK (end_date IS NULL OR start_date <= end_date);

ALTER TABLE indisponibilites
ADD CONSTRAINT chk_fiscal_impact_consistency
CHECK (has_fiscal_impact = (type = 'pound'));
```

### 0.6 Foreign keys et `ON DELETE`

MySQL 8 supporte les FK avec `ON DELETE RESTRICT`, `CASCADE`, `SET NULL`, `NO ACTION`. La convention Floty (cf. `03-strategie-suppression.md`) reste **`ON DELETE RESTRICT`** partout sauf cas explicitement justifiés. Aucun changement par rapport au schéma agnostique.

### 0.7 Encoding et collation

```sql
DEFAULT CHARSET=utf8mb4
DEFAULT COLLATE=utf8mb4_unicode_ci
```

Tous les `CREATE TABLE` Floty utilisent **utf8mb4** (support emoji/caractères étendus) et **utf8mb4_unicode_ci** (tri / comparaison normalisés Unicode, insensible à la casse). Conformément aux defaults Laravel modernes.

### 0.8 Limites MySQL acceptées en V1

Les contraintes suivantes sont **acceptées** et documentées comme dette technique mineure de l'implémentation MySQL :

| Limite | Impact | Mitigation V1 |
|---|---|---|
| Pas d'`EXCLUDE` constraint native | Anti-chevauchement de périodes via trigger + validation applicative | Couverture par 3 lignes de défense (cf. § 0.3) |
| Pas d'index partiel natif | `WHERE deleted_at IS NULL` impossible | Colonnes générées + index classique |
| `JSON` moins typé que `JSONB` PostgreSQL | Pas d'index sur clés JSON | Pour le `snapshot_json` qui n'est jamais requêté par clé (juste sérialisé / hashé) : pas un problème |
| Pas de `WITH RECURSIVE` aussi flexible | Si besoin de requêtes récursives V2+ (ex: arborescence de remplacements de conducteurs) | Limité à la lecture, géré applicativement |

### 0.9 Chemin d'évolution PostgreSQL (V2/V3 si VPS)

Si Floty migre vers un VPS avec PostgreSQL :

1. **Migrations** : remplacer `JSON` → `JSONB`, `TIMESTAMP` → `TIMESTAMPTZ`, ajouter `EXCLUDE USING gist` sur `vehicle_fiscal_characteristics` et `assignments`.
2. **Index partiels** : remplacer les colonnes générées + index par `CREATE INDEX ... WHERE ...`.
3. **Triggers** : supprimer les triggers anti-chevauchement (remplacés par les exclusion constraints natives).
4. **Code applicatif** : aucun changement nécessaire (les services et repositories n'utilisent que des features SQL standards).

L'effort est estimé **entre 2 et 4 jours** pour la migration complète + tests, exécutable dans un sprint dédié sans risque sur le code métier.

---

## 1. `users` — Comptes applicatifs

Conformément à ADR-0007, pas de rôles en V1, pas de libre-service. Seeders uniquement.

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `email` | VARCHAR(255) | NON | — | Unique, utilisé pour la connexion |
| `password` | VARCHAR(255) | NON | — | Hash bcrypt (natif Laravel) |
| `first_name` | VARCHAR(100) | NON | — | Prénom |
| `last_name` | VARCHAR(100) | NON | — | Nom |
| `email_verified_at` | TIMESTAMPTZ | OUI | NULL | Pour la compatibilité Laravel (non utilisé en V1) |
| `remember_token` | VARCHAR(100) | OUI | NULL | Pour la compatibilité Laravel |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |
| `deleted_at` | TIMESTAMPTZ | OUI | NULL | Soft delete |

**Index** :
- `UNIQUE (email)`
- `INDEX (deleted_at)` — pour filtrer rapidement les comptes actifs

**Invariants** :
- Au moins un compte actif doit exister à tout moment (sinon application inaccessible). Vérification applicative, pas de contrainte SQL.

---

## 2. `vehicles` — Registre des véhicules

Porte les attributs **non fiscaux** du véhicule (identité, statut, cycle de vie). Les caractéristiques fiscales sont dans `vehicle_fiscal_characteristics` (cf. table 3).

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `license_plate` | VARCHAR(20) | NON | — | Plaque ; format libre (inclut plaques anciennes, étrangères temporaires…) |
| `brand` | VARCHAR(80) | NON | — | |
| `model` | VARCHAR(120) | NON | — | |
| `vin` | VARCHAR(20) | OUI | NULL | Numéro de châssis, si disponible |
| `color` | VARCHAR(30) | OUI | NULL | |
| `photo_path` | VARCHAR(500) | OUI | NULL | Chemin relatif dans le disque Laravel (cf. filesystem) |
| `first_french_registration_date` | DATE | NON | — | Date de première immatriculation **en France** |
| `first_origin_registration_date` | DATE | NON | — | Date de première immatriculation à l'origine (étrangère pour import, sinon = France) |
| `first_economic_use_date` | DATE | NON | — | Date de première affectation à une activité économique (cf. CIBS L. 421-119-1, 2°) |
| `acquisition_date` | DATE | NON | — | Entrée dans la flotte (date d'achat) |
| `exit_date` | DATE | OUI | NULL | Sortie de flotte (vente, destruction, transfert…) — NULL tant qu'actif |
| `exit_reason` | VARCHAR(30) | OUI | NULL | Énum applicative : vente, destruction, transfert, autre |
| `current_status` | VARCHAR(30) | NON | `'actif'` | Énum applicative : actif, maintenance, vendu, detruit, autre |
| `mileage_current` | INTEGER | OUI | NULL | Km courant (indicatif, saisie libre) |
| `notes` | TEXT | OUI | NULL | Notes libres |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |
| `deleted_at` | TIMESTAMPTZ | OUI | NULL | Soft delete |

**Index** :
- `UNIQUE (immatriculation) WHERE deleted_at IS NULL` — une plaque = un véhicule actif ; permet la re-saisie après suppression physique
- `UNIQUE (vin) WHERE vin IS NOT NULL AND deleted_at IS NULL`
- `INDEX (date_exit)` — pour filtrer les véhicules sortis

**Invariants** :
- `date_first_registration_france ≥ date_first_registration_origin` (CHECK).
- Si `date_exit IS NOT NULL` alors `exit_reason IS NOT NULL` (CHECK).
- Si `date_exit IS NOT NULL` alors `current_status ∈ {'vendu', 'detruit', 'autre'}` (vérif applicative).

**Remarque** : l'auto-complétion SIV (API carte grise) reste un bonus **V3**, cf. ADR-0007. En V1, tous les champs sont saisis manuellement.

---

## 3. `vehicle_fiscal_characteristics` — Historisation des caractéristiques fiscales

Table centrale pour la correction du calcul fiscal. Chaque modification effective d'une caractéristique fiscalement déterminante crée une **nouvelle ligne** avec `effective_from` ≥ jour de la modification. La ligne précédente se voit définir son `effective_to`.

**Principe** :
- Pour un véhicule donné, les périodes d'effet **ne se chevauchent jamais** (exclusion constraint).
- Les périodes forment une chaîne continue : la nouvelle `effective_from` = ancienne `effective_to + 1 jour` (ou `effective_from = effective_to` si besoin de capturer un changement au jour J avec rétroactivité, à clarifier en implémentation).
- `effective_to IS NULL` signifie « version courante, non bornée » — équivalent conceptuel à `is_current = true` (choix acté : pas de colonne `is_current`, redondance évitée).

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `vehicle_id` | BIGINT | NON | — | FK → `vehicles.id` |
| `effective_from` | DATE | NON | — | Date d'effet (inclusive) |
| `effective_to` | DATE | OUI | NULL | Date de fin d'effet (inclusive) ; NULL = courant |
| **Catégorie européenne et type utilisateur** | | | | |
| `reception_category` | VARCHAR(10) | NON | — | Énum : M1, N1 |
| `vehicle_user_type` | VARCHAR(10) | NON | — | Énum : VP, VU |
| `body_type` | VARCHAR(20) | NON | — | Rubrique J.2 : CI, BB, CTTE, BE, HB, etc. |
| `seats_count` | SMALLINT | NON | — | Rubrique S.1 |
| **Source d'énergie et motorisation** | | | | |
| `energy_source` | VARCHAR(30) | NON | — | Énum (`EnergySource`) : `gasoline`, `diesel`, `electric`, `hydrogen`, `plugin_hybrid`, `non_plugin_hybrid`, `lpg`, `cng`, `e85`, `electric_hydrogen` |
| `underlying_combustion_engine_type` | VARCHAR(20) | OUI | NULL | Énum : `gasoline`, `diesel`, `not_applicable` — requis si source_energie est hybride |
| `euro_standard` | VARCHAR(20) | OUI | NULL | Énum : euro_1…euro_6d_isc_fcm |
| `pollutant_category` | VARCHAR(30) | NON | — | Énum (`PollutantCategory`) : `e`, `category_1`, `most_polluting` |
| **Mesure des émissions** | | | | |
| `homologation_method` | VARCHAR(20) | NON | — | Énum : WLTP, NEDC, PA (puissance administrative) |
| `co2_wltp` | INTEGER | OUI | NULL | g/km — requis si methode = WLTP |
| `co2_nedc` | INTEGER | OUI | NULL | g/km — requis si methode = NEDC |
| `taxable_horsepower` | SMALLINT | OUI | NULL | CV fiscaux — requis si methode = PA |
| `kerb_mass` | INTEGER | OUI | NULL | kg, case G carte grise |
| **Champs conditionnels fiscaux** | | | | |
| `handicap_access` | BOOLEAN | NON | `false` | Accessible fauteuil roulant ou aménagé handicap (L. 421-123 / L. 421-136) |
| `n1_passenger_transport` | BOOLEAN | NON | `false` | Pour N1 : affectation au transport de personnes |
| `n1_removable_second_row_seat` | BOOLEAN | NON | `false` | Pour N1 : banquette amovible avec 2ᵉ rang |
| `m1_special_use` | BOOLEAN | NON | `false` | Pour M1 : transport public, taxi, VTC, ambulance, auto-école, compétition |
| `n1_ski_lift_use` | BOOLEAN | NON | `false` | Pick-ups N1 ≥ 5 places utilisés en remontées mécaniques |
| **Audit** | | | | |
| `change_reason` | VARCHAR(20) | NON | — | Énum (`FiscalCharacteristicsChangeReason`) : `initial_creation`, `effective_change` (nouvelle version), `input_correction` (ne crée pas de nouvelle version — géré via update direct) |
| `change_note` | TEXT | OUI | NULL | Libre, ex. "Conversion E85 le 12/03/2024" |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |

**Contraintes et index** :
- `PRIMARY KEY (id)`
- `FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT` — impossible de supprimer physiquement un véhicule tant qu'il porte des caractéristiques
- `CHECK (effective_to IS NULL OR effective_from <= effective_to)`
- **Exclusion constraint PostgreSQL** :
  ```sql
  EXCLUDE USING gist (
    vehicle_id WITH =,
    daterange(effective_from, COALESCE(effective_to, DATE '9999-12-31'), '[]') WITH &&
  )
  ```
  Garantit l'absence de chevauchement entre versions pour un même véhicule.
- `INDEX (vehicle_id, effective_from DESC)` — pour récupérer rapidement la version effective à une date donnée
- `INDEX (vehicle_id) WHERE effective_to IS NULL` — pour récupérer la version courante sans scan

**Invariants métier (à valider dans la couche applicative, cross-colonnes)** :
- Si `methode_homologation = 'WLTP'` alors `co2_wltp IS NOT NULL`.
- Si `methode_homologation = 'NEDC'` alors `co2_nedc IS NOT NULL`.
- Si `methode_homologation = 'PA'` alors `puissance_admin IS NOT NULL`.
- Si `source_energie IN ('plugin_hybrid', 'non_plugin_hybrid', 'electric_hydrogen')` alors `type_moteur_thermique_sous_jacent IS NOT NULL`.
- Si `categorie_reception = 'M1'` alors `type_utilisateur = 'VP'` ; si `N1` alors `VU`.

**Distinction correction vs modification effective** :
- **Correction de saisie** : édition directe de la ligne active (UPDATE) — ex. l'utilisateur a saisi 138 g CO₂ au lieu de 118. Pas de nouvelle version historique.
- **Modification effective** : nouvelle ligne avec `effective_from` date du changement, fermeture de la précédente avec `effective_to` = date - 1. Ex. conversion E85, ajout d'un 2ᵉ rang de places.

Ce choix est exposé à l'utilisateur par un **toggle dans le formulaire** : « Corriger une erreur de saisie » (par défaut) ou « Le véhicule a réellement changé, historiser la modification ».

---

## 4. `companies`

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `legal_name` | VARCHAR(255) | NON | — | |
| `siren` | CHAR(9) | OUI | NULL | 9 chiffres |
| `siret` | CHAR(14) | OUI | NULL | 14 chiffres (siège) |
| `address_line_1` | VARCHAR(255) | OUI | NULL | |
| `address_line_2` | VARCHAR(255) | OUI | NULL | |
| `postal_code` | VARCHAR(10) | OUI | NULL | |
| `city` | VARCHAR(100) | OUI | NULL | |
| `country` | VARCHAR(2) | NON | `'FR'` | ISO 3166-1 alpha-2 |
| `contact_name` | VARCHAR(150) | OUI | NULL | |
| `contact_email` | VARCHAR(255) | OUI | NULL | |
| `contact_phone` | VARCHAR(30) | OUI | NULL | |
| `short_code` | VARCHAR(5) | NON | — | 2-3 lettres (ex. AC pour ACME), saisie rapide tableur |
| `color` | CHAR(7) | NON | — | Hex code RGB (`#RRGGBB`) pour la timeline véhicule |
| `is_active` | BOOLEAN | NON | `true` | Flag de désactivation fonctionnelle (distinct du soft delete) |
| `deactivated_at` | TIMESTAMPTZ | OUI | NULL | Date de désactivation |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |
| `deleted_at` | TIMESTAMPTZ | OUI | NULL | Soft delete (distinct de `is_active`) |

**Index** :
- `UNIQUE (code_court) WHERE deleted_at IS NULL` — le code court sert à la saisie rapide, doit être unique parmi les entreprises actives
- `UNIQUE (siren) WHERE siren IS NOT NULL AND deleted_at IS NULL`
- `INDEX (is_active, deleted_at)`

**Note sur `is_active` vs `deleted_at`** :
- `is_active = false` : entreprise désactivée métier (plus d'attributions futures possibles) mais référencée par l'historique → **reste visible en lecture** dans les vues historiques.
- `deleted_at IS NOT NULL` : soft delete (supprimée fonctionnellement de l'application) → invisible dans les listes standard.

---

## 5. `drivers`

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `company_id` | BIGINT | NON | — | FK → `entreprises_utilisatrices.id` ; un conducteur est lié à une seule entreprise |
| `first_name` | VARCHAR(100) | NON | — | |
| `nom` | VARCHAR(100) | NON | — | |
| `is_active` | BOOLEAN | NON | `true` | Actif / inactif |
| `deactivated_at` | TIMESTAMPTZ | OUI | NULL | |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |
| `deleted_at` | TIMESTAMPTZ | OUI | NULL | Soft delete |

**Index** :
- `INDEX (entreprise_id, is_active)`
- `INDEX (deleted_at)`

**Contraintes** :
- `FOREIGN KEY (entreprise_id) REFERENCES entreprises_utilisatrices(id) ON DELETE RESTRICT`

**Fonctionnalité « Remplacer par… »** (CDC § 2.3) : pas de structure dédiée en base. Opération applicative qui exécute un `UPDATE attributions SET conducteur_id = nouveau WHERE conducteur_id = ancien AND date >= date_pivot`. Les attributions passées restent rattachées à l'ancien conducteur.

---

## 6. `assignments` — Entité pivot centrale

Conformément à ADR-0005 : **une ligne par (véhicule, date)**. Granularité jour, année civile.

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `vehicle_id` | BIGINT | NON | — | FK → `vehicles.id` |
| `company_id` | BIGINT | NON | — | FK → `entreprises_utilisatrices.id` |
| `driver_id` | BIGINT | OUI | NULL | FK → `conducteurs.id` ; optionnel à la création, peut être saisi plus tard |
| `date` | DATE | NON | — | Jour d'attribution |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |
| `deleted_at` | TIMESTAMPTZ | OUI | NULL | Soft delete |

**Contraintes et index** :
- `UNIQUE (vehicle_id, date) WHERE deleted_at IS NULL` — **contrainte critique** : un véhicule = une seule entreprise par jour (CDC § 2.4).
- `FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT`
- `FOREIGN KEY (entreprise_id) REFERENCES entreprises_utilisatrices(id) ON DELETE RESTRICT`
- `FOREIGN KEY (conducteur_id) REFERENCES conducteurs(id) ON DELETE RESTRICT`
- `INDEX (entreprise_id, date)` — vue par entreprise
- `INDEX (vehicle_id, date)` — vue par véhicule
- `INDEX (date)` — heatmap annuelle
- `INDEX (vehicle_id, entreprise_id, EXTRACT(YEAR FROM date))` — cumul LCD par couple (support du compteur temps réel)

**Invariants applicatifs** :
- `driver_id` (si renseigné) doit appartenir à `company_id` (CHECK cross-table non trivial, vérifié en applicatif).
- `date` doit tomber dans une plage où le véhicule est actif : `date >= vehicles.date_acquisition AND (vehicles.date_exit IS NULL OR date <= vehicles.date_exit)`.
- Une attribution ne peut pas recouvrir un jour d'indisponibilité du véhicule (sauf si l'indisponibilité est postérieurement modifiée).

**Audit trail V2** : hors périmètre V1 (cf. ADR-0007). En V1, les timestamps `created_at`/`updated_at` et `deleted_at` donnent un audit partiel. Les snapshots PDF (ADR-0003) donnent une forme de « figement » par déclaration.

---

## 7. `unavailabilities`

Période (plage continue de jours) durant laquelle un véhicule n'est pas attribuable. Seul le type **fourrière** a un impact fiscal (CDC § 2.5).

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `vehicle_id` | BIGINT | NON | — | FK → `vehicles.id` |
| `type` | VARCHAR(30) | NON | — | Énum (`UnavailabilityType`) : `maintenance`, `technical_inspection`, `accident`, `pound`, `other` |
| `has_fiscal_impact` | BOOLEAN | NON | — | Dénormalisation calculée : `true` ssi `type = 'pound'`. Pour requêtage rapide. |
| `start_date` | DATE | NON | — | Début (inclusif) |
| `end_date` | DATE | OUI | NULL | Fin (inclusive) ; NULL tant que non terminée |
| `description` | TEXT | OUI | NULL | Libre |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |
| `deleted_at` | TIMESTAMPTZ | OUI | NULL | Soft delete |

**Contraintes et index** :
- `CHECK (end_date IS NULL OR start_date <= end_date)`
- `CHECK (has_fiscal_impact = (type = 'pound'))` — cohérence dénormalisation
- `FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT`
- `INDEX (vehicle_id, start_date)` — affichage planning véhicule
- `INDEX (vehicle_id, has_fiscal_impact, start_date)` — pour le prorata fiscal
- `INDEX (type, start_date)` — rapports par type

**Choix de modèle « période » vs « une ligne par jour »** :

Contrairement aux attributions (jour-par-jour), les indisponibilités sont modélisées par **plages continues** (`start_date` / `end_date`). Justifications :
- Une indisponibilité correspond sémantiquement à un événement (passage en maintenance), pas à 10 événements journaliers distincts.
- Plus compact en base (1 ligne pour 2 semaines de maintenance au lieu de 14).
- La logique de prorata fiscal itère de toute façon sur les jours de l'année pour croiser avec les attributions ; la projection « plage → jours » est triviale côté code.

**Conflit attribution × indisponibilité** : vérifié à la saisie en applicatif, pas par contrainte SQL (la modélisation mixte jour/plage ne se prête pas à une exclusion constraint simple). Message d'erreur : « Ce véhicule est indisponible le JJ/MM (type : fourrière) — supprimer ou modifier l'indisponibilité avant d'attribuer ».

---

## 8. Relations — schéma synthétique

```
users (comptes applicatifs, indépendants)

vehicles ──1─────N── vehicle_fiscal_characteristics (historisation)
   │
   │ 1
   │
   └──N── attributions ──N──1── entreprises_utilisatrices ──1──N── conducteurs
   │                          (conducteur_id → N──1 conducteurs, optionnel)
   │
   └──N── indisponibilites
```

---

## 9. Hors périmètre V1 (rappels)

- **Barèmes fiscaux en base** (CDC § 2.6) : en V1, les barèmes vivent dans le code (ADR-0002 : règles non éditables). Aucune table `fiscal_brackets` n'est créée.
- **Audit trail complet des modifications d'attribution** : V2 (ADR-0007).
- **Table `rentals` pour les grilles tarifaires loyers** : V1.2 (roadmap). Non créée en V1.
- **Table `roles` / `permissions`** : V2. Non créée en V1.
- **Table `audit_log` générique** : V2. Non créée en V1.

Le modèle V1 est **compatible** avec ces extensions : aucune d'elles ne nécessite de refactor des tables ci-dessus.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.3 | 24/04/2026 | Micha MEGRET | Passe E1 strict total (étape 5.7) — refonte complète des noms de tables, colonnes BDD et propriétés Data DTO en anglais : tables `entreprises_utilisatrices`/`conducteurs`/`attributions`/`indisponibilites` → `companies`/`drivers`/`assignments`/`unavailabilities`. Colonnes véhicules : `immatriculation`→`license_plate`, `marque`→`brand`, `modele`→`model`, `couleur`→`color`, dates `date_xxx`→`xxx_date`. Colonnes vehicle_fiscal_characteristics : `categorie_*`→`*_category`, `type_utilisateur`→`vehicle_user_type`, `carrosserie`→`body_type`, `nb_places_assises`→`seats_count`, `source_energie`→`energy_source`, `methode_homologation`→`homologation_method`, `puissance_admin`→`taxable_horsepower`, `masse_ordre_marche`→`kerb_mass`, `n1_*` flags traduits. Colonnes companies : `raison_sociale`→`legal_name`, `code_court`→`short_code`, adresse traduite. FK `entreprise_id`→`company_id`, `conducteur_id`→`driver_id`. Codes administratifs FR universels conservés (M1, N1, VP, VU, CI, BB, CTTE, BE, HB, WLTP, NEDC, PA, SIREN, SIRET, VIN). |
| 1.2 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — application de la convention E1 (anglais strict pour les enums Floty) sur les valeurs stockées : `'fourriere'` → `'pound'`, valeurs `EnergySource` traduites (`'gasoline'`, `'plugin_hybrid'`, `'electric_hydrogen'`, etc.), `UnavailabilityType` traduites (`'technical_inspection'`, `'accident'`, `'pound'`, `'other'`), `FiscalCharacteristicsChangeReason` traduites (`'initial_creation'`, `'effective_change'`, `'input_correction'`), `PollutantCategory` traduites (`'e'`, `'category_1'`, `'most_polluting'`). Codes administratifs FR conservés en exception documentée (M1, N1, VP, VU, CI, BB, CTTE, BE, HB, WLTP, NEDC, PA). Noms de classes enum référencés en anglais (`EnergySource`, `UnavailabilityType`, etc.). |
| 1.1 | 24/04/2026 | Micha MEGRET | Ajout de la section « 0. Adaptation MySQL 8 — implémentation V1 » suite à ADR-0008. Documente le mapping des types (TIMESTAMPTZ → TIMESTAMP UTC, JSONB → JSON, BOOLEAN → TINYINT(1)), la stratégie pour les index partiels (colonnes générées MySQL 8), l'absence d'exclusion constraint (3 lignes de défense : validation service + trigger BEFORE INSERT/UPDATE + verrou pessimiste), liste des 4 triggers à créer avec exemple SQL, check constraints supportées, encoding utf8mb4, limites assumées, chemin d'évolution PostgreSQL documenté. Le schéma original (sections 1-9) reste lisible comme spec agnostique. |
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — 7 tables métier V1 avec types, contraintes, index, invariants. |
