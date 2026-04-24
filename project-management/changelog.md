# Changelog projet Floty

> **Objet** : journal de bord chronologique de toutes les actions significatives menées sur le projet Floty (décisions, rédactions documentaires, phases de recherche, corrections, livraisons).
> **Audience** : usage **interne** — prestataire, futur intervenant, future instance de travail reprenant le projet.
> **Format** : entrées datées, ordre antéchronologique (le plus récent en haut). Horaires précis pour les actions à venir, horaires indicatifs pour la rétrospective.
> **Positionnement** :
>
> - Complète le git log (granularité fichier) et les historiques intra-documents (granularité document) en fournissant une **vue transverse** narrative.
> - N'est **pas un document client** : niveau de détail interne, réflexions libres, pas de destination hors équipe.

---

## 2026-04-24

### Après-midi (24/04 J+1) — Étape 5.8 : intégration Wayfinder + outils Laravel Boost

- Préparation client : il a anticipé en copiant les règles Laravel Boost dans `CLAUDE.md` avant installation Laravel. Cela a révélé plusieurs packages à intégrer : **Wayfinder** (routes TS typées, remplace Ziggy), **Pint** (formatter), **Pail** (tail logs dev), **Boost + MCP** (assist Claude Code), **Sail** (exclu). PHP confirmé 8.5.
- Décision : adopter Wayfinder (gain TypeScript end-to-end significatif), intégrer Pint/Pail/Boost comme outils dev. Sail explicitement exclu (stack locale Herd).
- Mises à jour :
  - **ADR-0008 v1.2** : 5 entrées ajoutées à la synthèse stack (21 → 25 composants), mention « type-safety end-to-end PHP↔TS » dans la section Frontend.
  - **versions-outils.md v1.3** : 6 entrées ajoutées à la synthèse finale (Wayfinder, Pint, Pail, Boost, MCP, Sail marqué non utilisé).
  - **inertia-navigation.md v1.1** : ajout d'une section complète « Laravel Wayfinder » avec tableau comparatif Wayfinder vs Ziggy, patterns `<Link>` / `router.visit` / `useForm` avec Wayfinder, anti-patterns, setup Vite.
  - **conventions-nommage.md** : ajout d'une sous-section sur l'usage Wayfinder côté front dans la section Routes.

### Midi (24/04 J+1) — Étape 5.7 : E1 strict total (anglais pour tous les noms internes)

- Décision client : appliquer E1 **strict total** — tous les noms internes (tables, colonnes, propriétés Data, classes, méthodes, variables) en anglais. Seuls les acronymes/sigles/codes administratifs universels (WLTP, NEDC, PA, SIREN, SIRET, VIN, M1, N1, VP, VU, CI, BB, CTTE, BE, HB, E85, Co2) sont conservés tels quels comme valeurs.
- Table de mapping établie et propagée via `sed` sur les 19 fichiers concernés (12 implementation-rules + 5 modele-de-donnees + 1 stack-technique + 1 ADR).
- Mappings clés appliqués :
  - **Tables** : `entreprises_utilisatrices`→`companies`, `conducteurs`→`drivers`, `attributions`→`assignments`, `indisponibilites`→`unavailabilities`.
  - **Colonnes véhicules** : `immatriculation`→`license_plate`, `marque`→`brand`, `modele`→`model`, `couleur`→`color`, `date_acquisition`→`acquisition_date`, etc.
  - **Colonnes fiscal** : `source_energie`→`energy_source`, `methode_homologation`→`homologation_method`, `categorie_polluants`→`pollutant_category`, `puissance_admin`→`taxable_horsepower`, `masse_ordre_marche`→`kerb_mass`, etc.
  - **Colonnes companies** : `raison_sociale`→`legal_name`, `code_court`→`short_code`, adresse traduite.
  - **Classes PHP** : `EntrepriseUtilisatrice`→`Company`, `Conducteur`→`Driver`, `Attribution`→`Assignment`, `Indisponibilite`→`Unavailability`. Tous les Action/Service/Repository/Data/Controller/Exception/FormRequest associés renommés.
  - **Namespaces** : `App\Models\Conducteur`→`App\Models\Driver`, `App\Repositories\User\Attribution`→`App\Repositories\User\Assignment`, etc.
  - **Routes** : `/entreprises`→`/companies`, `user.entreprises.*`→`user.companies.*`, etc.
  - **Propriétés Data DTO camelCase** : `typeUtilisateur`→`vehicleUserType`, `sourceEnergie`→`energySource`, `nbPlacesAssises`→`seatsCount`, `dateAcquisition`→`acquisitionDate`, `raisonSociale`→`legalName`, etc.
- Plusieurs passes successives (Phase 2a-2j) avec `sed` puis correctifs Edit ciblés pour les résidus que sed n'a pas matché (namespaces avec backslash `App\X\Y`).
- Vérification finale par grep : aucun résidu technique français restant. Texte explicatif français conservé intact (« entreprise utilisatrice » dans les phrases reste « entreprise utilisatrice »).
- État documentation après E1 strict total : 27 docs cohérents, alignés sur la convention « tout interne en anglais ».

### Matinée (24/04 J+1) — Étape 5.6 : audit et corrections documentation

- Audit complet de la documentation produite (27 documents : 8 ADR, 12 implementation-rules, 5 modele-de-donnees, 1 stack-technique, changelog) via un agent dédié. Rapport produit avec 4 anomalies critiques, 7 mineures, 4 cosmétiques. Note globale qualité initiale : 7/10.
- Validation client des correctifs via dialogue. Décisions actées : E1 = anglais strict pour les enums (clés et valeurs stockées), B2 = suppression de la couche Services frontend orpheline, correction de toutes les anomalies y compris cosmétiques.
- Correctifs appliqués :
  - **A1** — `architecture-solid.md` v2.2 : alignement sections 3-7 sur la convention `{Espace}/{Domaine}/`. Tous les exemples PHP (namespaces, `Inertia::render`, routes) corrigés vers `User/Vehicle`, `User/Attribution`, `Shared/Pdf`, etc. Suppression note ligne 228-230 contradictoire. Refonte enums (`SourceEnergie` → `EnergySource`, `TypeUtilisateur` → `VehicleUserType`, `MethodeHomologation` → `HomologationMethod`, etc.). Suppression « (à créer) » obsolètes.
  - **A2** — `composables-services-utils.md` v1.1 : suppression de la section 2 « Service frontend » (couche orpheline non justifiée en Floty V1). Renumérotation de 9 sections à 8. Anti-pattern explicite « pas de dossier `resources/js/Services/` ». Renommage doc « Composables et utils ».
  - **A3** — `modele-de-donnees/README.md` v1.1 : alignement sur ADR-0008 (MySQL 8). Refonte tableau « Conventions de types » (TIMESTAMP UTC, JSON, TINYINT(1), VARCHAR+CHECK pour enums). Ajout SGBD V1 en tête, ajout ADR-0008 dans les liens.
  - **A4** — `versions-outils.md` v1.2 : note de tête en § 4 signalant que les recommandations historiques sont conservées pour traçabilité, et que les § 5/6 prévalent en cas de divergence.
  - **E1** (enums anglais strict) propagé dans `conventions-nommage.md` v2.1 (refonte exemples + tableau récap), `architecture-solid.md` v2.2, `gestion-erreurs.md`, `typescript-dto.md`, `01-schema-metier.md` v1.2, `02-schema-fiscal.md` v1.2. Mapping documenté : `DeclarationStatus` Draft/Verified/Generated/Sent, `EnergySource` Gasoline/PluginHybrid/..., `UnavailabilityType` Maintenance/TechnicalInspection/Accident/Pound/Other, `FiscalCharacteristicsChangeReason` InitialCreation/EffectiveChange/InputCorrection, `RuleType` Classification/Pricing/Exemption/Abatement/Transversal, `TaxType` Co2/Pollutants. Codes administratifs FR universels (M1, N1, VP, VU, CI, BB, CTTE, BE, HB, WLTP, NEDC, PA) conservés en exception documentée.
  - **Mineures** : `structure-fichiers.md` ligne 66 « Pest » → « PHPUnit » ; routes incohérentes corrigées dans `conventions-nommage.md` ligne 353 (`vehicles.index` → `user.vehicles.index`, `web.auth.login` → `web.auth.login.show`) ; mention « PHP 8.4+, en pratique PHP 8.5 sur Floty » dans `architecture-solid.md` ; rappels inline « MySQL 8 : JSONB → JSON, TIMESTAMPTZ → TIMESTAMP » dans `02-schema-fiscal.md`.
  - **Cosmétiques** : suppression du doublon « Pourquoi un UI Kit custom et pas shadcn-vue » dans ADR-0008 (justification déjà présente sous le titre « Pourquoi pas de shadcn-vue et UI Kit custom à la place »). Uniformisation `Tailwind v4`/`Tailwind 4` dans `versions-outils.md`.
- **Question ouverte non corrigée** : noms de **colonnes BDD** et **propriétés Data DTO** restés en français (`immatriculation`, `marque`, `modele`, `couleur`, `nbPlacesAssises`, `dateAcquisition`, etc.). Cohérence stricte avec E1 demanderait de les traduire (`licensePlate` ou `registrationNumber`, `brand`, `model`, `color`, `numberOfSeats`, `acquisitionDate`, etc.) — chantier conséquent à arbitrer avec le client avant l'étape 6.
- État documentation après corrections : 27 docs cohérents, prêts pour l'étape 6 (implémentation MVP 2024) sous réserve de l'arbitrage sur la question ouverte ci-dessus.

### Soirée et nuit — Étape 5 du workflow (Stack technique) — clôture complète

- ~02h00 (24/04) — Lancement de l'étape 5.1 : audit web des versions stables avril 2026 de tous les outils envisagés (PHP, Laravel, Inertia, Vue, Vite, Tailwind, Pinia, TypeScript, Spatie Data, Spatie TS Transformer, DomPDF, Pest, Vitest, Vue Test Utils, Node) + vérification contraintes Hostinger Business. Découverte de 3 corrections majeures vs ma compréhension initiale : PHP 8.4 (et même 8.5) bien dispo Hostinger, Composer pré-installé, Node.js absent confirmé en SSH. Livrable : `project-management/stack-technique/versions-outils.md`.
- ~03h30 — Étape 5.2 : verrouillage des 8 décisions stack avec le client. PHP 8.5, Laravel 13.6, Inertia v3, Vue 3.5, TypeScript 6, Vite 8, Tailwind 4, PHPUnit, **pas de starter kit (install custom)**, **pas de shadcn-vue (UI Kit custom Floty)**, DomPDF V1 (Browsershot exclu), driver cache `database`.
- ~04h00 — Étape 5.3 : refonte des 5 documents existants `implementation-rules/` qui dataient d'un projet Livewire+Alpine+Blade. Refonte profonde pour stack Inertia+Vue+TS+Spatie Data : `architecture-solid.md` (v2.1, 1731 lignes — pilote validé bloc par bloc avec corrections sur transactions↔pragmatisme, action vs service face aux repos, exemple service avec vraie logique métier, confirmation pratique senior Spatie Data, arborescence avec segmentation par espace dès V1, partials par page), `structure-fichiers.md`, `assets-vite.md` (révisé en v2.1 après dialogue sur le modèle CSS hybride), `gestion-erreurs.md`, `conventions-nommage.md`. Total bloc : 4180 lignes.
- ~05h00 — Étape 5.4 : rédaction de 7 nouvelles règles d'implémentation Inertia/Vue/TS niveau senior+. Ordre logique : (1) `typescript-dto.md` fondation Spatie Data + génération auto types TS, (2) `vue-composants.md` Composition API stricte, (3) `inertia-navigation.md` 4 mécanismes + 8 pièges, (4) `composables-services-utils.md` distinction des 3 concepts, (5) `pinia-stores.md` outil de réserve avec hiérarchie 8 mécanismes d'état, (6) `performance-ui.md` zones critiques Floty + anti-pattern N°1 skeleton/lazy détaillé, (7) `tests-frontend.md` Vitest + VTU + fixtures typées. Total bloc : 5434 lignes. **Cumul `implementation-rules/` : 12 documents, 9807 lignes** — base documentaire dense avant code.
- ~06h00 — Étape 5.5 : production des livrables principaux de clôture stack. Rédaction **ADR-0008 — Stack technique V1** qui formalise les 20 décisions stack avec justifications, 10 alternatives écartées, conséquences positives/techniques/produit/organisationnelles/économiques, chemin d'évolution VPS pour PostgreSQL+Redis+Browsershot+SSR documenté. Adaptation des 2 fichiers `modele-de-donnees/01-schema-metier.md` (v1.1) et `02-schema-fiscal.md` (v1.1) avec une section « 0. Adaptation MySQL 8 » qui mappe les types PostgreSQL → MySQL (TIMESTAMPTZ → TIMESTAMP UTC, JSONB → JSON, BOOLEAN → TINYINT(1), index partiels → colonnes générées MySQL 8, exclusion constraints → triggers BEFORE INSERT/UPDATE + 3 lignes de défense applicatives). 4 triggers SQL listés avec exemple complet. Le schéma original reste lisible comme spec agnostique.
- ~07h00 — Étape 5 verrouillée. Bilan : la stack est arrêtée, les règles d'implémentation sont posées, le schéma est adapté MySQL. **Prêt à passer à l'étape 6 du workflow (implémentation MVP 2024)**, sur des fondations rigoureuses.

---

## 2026-04-23

### Nuit — Livraison schéma détaillé V1 (étape 4 du workflow, suite)

- ~22h30 — Réponses du client aux 6 questions structurantes posées en ouverture d'étape 4 :
  - **Nuance suppression** : le client refuse l'interdiction stricte de suppression physique. Demande une UX modal à 2 niveaux : suppression logique par défaut, option « suppression définitive » désactivée par défaut, à cocher explicitement.
  - **Q1 attribution par jour** : validé.
  - **Q2 historisation caractéristiques fiscales** : validé. Question complémentaire : `is_current` ou `effective_to NULL` suffit ? → Réponse : `effective_to` nullable seul, sans `is_current` (redondance évitée, index partiel `WHERE effective_to IS NULL` couvre les performances).
  - **Q3 invalidation** : option 2 (détection au moment de l'action), avec mention des alternatives en doc.
  - **Q4 stockage PDF** : **filesystem Laravel avec chemin en base** (override de la recommandation initiale « blob DB »). **Révèle Laravel comme backend.**
  - **Q5 cumul LCD** : à la volée + cache Laravel dès V1.
  - **Q6 snapshot JSON** : 100% confiance dans la recommandation prestataire.
- ~23h00 — Production du livrable étape 4 : 5 fichiers dans `project-management/modele-de-donnees/` :
  - `README.md` — index + 6 principes fondateurs + conventions Laravel
  - `01-schema-metier.md` — 7 tables métier (users, vehicles, vehicle_fiscal_characteristics, entreprises_utilisatrices, conducteurs, attributions, indisponibilites) avec types, contraintes, index, invariants
  - `02-schema-fiscal.md` — 3 tables fiscales (fiscal_rules, declarations, declaration_pdfs) avec structure snapshot JSON, hash SHA-256, stratégie filesystem
  - `03-strategie-suppression.md` — modal à 2 niveaux, RESTRICT (pas de cascade), cas particuliers par entité
  - `04-strategie-cache.md` — 3 couches de cache, tags d'invalidation, garantie PDF recalculé hors cache
- Schéma cohérent avec ADR-0001 à ADR-0007 et avec CDC § 2 v1.5. Étape 4 verrouillée. Prochaine étape : 5 (stack technique) — Laravel déjà acté côté backend.

### Soirée — Ouverture étape 4 du workflow (Modèle de données)

- ~20h30 — Après validation du workflow (étapes 1, 2, 3 terminées), lancement de l'étape 4 : conception du modèle de données. Point de départ : les 7 décisions d'architecture du moteur de règles (ADR-0006), le périmètre V1 MVP (ADR-0007), les règles du catalogue 2024 (`taxes-rules/2024.md`), et les exigences CDC v1.5 (notamment § 2.1 champs véhicule, § 2.5 indisponibilités, § 3.4 compteur LCD temps réel). Objectif de la session : proposer une architecture de modèle de données, identifier les décisions structurantes à trancher, converger vers un schéma qui servira ensuite de base à l'étape 5 (stack technique).
- ~21h00 — Proposition structurée envoyée au client : 6 principes fondateurs (dont « pas de suppression physique » à challenger), cartographie de 10 entités (users, vehicles, vehicle_fiscal_characteristics, entreprises, conducteurs, attributions, indisponibilites, fiscal_rules, declarations, declaration_pdfs), et 6 questions structurantes à trancher (granularité attribution, historisation, invalidation, stockage PDF, cumul LCD, snapshot JSON).

### Soirée — Création du changelog projet

- ~20h15 — Le client propose la création d'un `changelog.md` à la racine de `project-management/` comme journal de bord chronologique transverse, complémentaire au git log (granularité fichier), aux historiques intra-documents (granularité document), et aux ADR (décisions d'architecture). Validation + création avec population rétrospective des 3 jours passés. Conventions d'usage définies pour les entrées futures.

### Soirée — Cadrage périmètre MVP (étape 3 du workflow)

- ~18h30 — Conversation structurée sur le périmètre V1. Proposition initiale en trois catégories (MUST-HAVE / SHOULD-HAVE / REPORTED) + 6 questions à trancher avec le client (Q1 saisie hebdo+wizard, Q2 heatmap V1, Q3 import CSV, Q4 exonérations inactives, Q5 page publique, Q6 PDF exports).
- ~19h15 — Retour client : validation des 6 questions. Précisions importantes :
  - Q3 : **Pas d'import CSV jamais prévu** (et pas seulement V1) — Renaud confirme que les données historiques sont inexploitables (« ils ont fait n'importe quoi, c'est d'ailleurs pour cela que je commande cette application »). Décision durable, pas un report.
  - Q6 : En V1.2, on ajoutera un **PDF facture commerciale** aux entreprises utilisatrices (distinct du PDF récapitulatif fiscal) — info nouvelle à intégrer dans la documentation V1.2.
  - Rappel du principe de continuité : documenter les décisions futures aux bons endroits pour garantir la continuité (changement d'instance, reprise de contexte).
- ~19h30 — Exécution en 5 étapes : (1) rédaction ADR-0007 — Périmètre V1 MVP, (2) refonte CDC § 9 en 4 sous-sections (9.1 V1.2 / 9.2 V2 / 9.3 V3 / 9.4 Exclusions durables), (3) refonte CDC § 4.3 (Export PDF) et § 4.4 (Import CSV reformulé en exclusion durable), (4) création de `project-management/roadmap.md` (vue d'ensemble navigable V1 → V3), (5) enrichissement de la mémoire `roadmap_v12_facturation.md` avec le PDF facture commerciale.
- ~20h00 — CDC promu en **v1.5**. Étape 3 du workflow verrouillée.

### Après-midi — Rédaction des 6 ADR (étape 2 du workflow, partie 2/2)

- ~15h00 — Lancement de la rédaction des 6 ADR fondateurs après validation conceptuelle des décisions :
  - **ADR-0001** — La fiscalité est une donnée, pas du code
  - **ADR-0002** — Règles non éditables depuis l'application en V1
  - **ADR-0003** — PDF et snapshots immuables des déclarations
  - **ADR-0004** — Invalidation de déclarations par marquage (non-blocante)
  - **ADR-0005** — Calcul fiscal jour-par-jour
  - **ADR-0006** — Architecture du moteur de règles
- ~16h30 — 6 ADR rédigés (970 lignes au total) avec format uniforme : Contexte, Décision, Justification, Alternatives écartées, Conséquences, Liens, Historique. Cohérence inter-ADR (les ADR se référencent mutuellement).

### Après-midi — Design du moteur de règles (étape 2 du workflow, partie 1/2)

- ~13h30 — Lancement de la conversation sur le design du moteur de règles, en partant des 24 règles concrètes de `taxes-rules/2024.md`.
- ~14h00 — Proposition structurée en 7 décisions : (1) Anatomie de Règle — interface de base + 5 sous-types, (2) Pipeline d'orchestration en 8 étapes, (3) Logique en code + métadonnées en base, (4) 3 modes d'exécution (calcul / simulation / PDF), (5) Snapshots et audit, (6) Page de consultation lecture-seule, (7) 3 temporalités (période règles, caractéristiques historisées, année fiscale).
- ~14h15 — Retour client : validation des 7 décisions, avec deux précisions importantes :
  - Retrait des champs `confidence` et `associatedUncertainty` de l'interface `FiscalRule` — ces concepts restent dans la documentation (pont interne ↔ interprétation réelle), pas dans l'application qui doit présenter la règle de manière autoritaire.
  - Ajout des champs `vehicleCharacteristicsConsumed` et `vehicleCharacteristicsProduced` pour cohérence avec ce que fait déjà `taxes-rules/{année}.md` et pour bénéfices opérationnels (ordonnancement pipeline, validation cohérence, analyse d'impact).

### Midi — Clarification Z-2024-002 et refonte LCD

- ~12h00 — Renaud apporte une clarification sur la nature exacte de son montage contractuel : **la société de location loue vraiment ses véhicules aux entreprises utilisatrices**, comme si elles étaient externes au groupe. La pratique depuis plusieurs années : multiples petites locations pour un même véhicule, mais pas cumulées sur différents véhicules (cf. citation exacte conservée dans ADR-0007).
- ~12h30 — Réexamen doctrinal complet (Légifrance L. 421-129, L. 421-141, L. 421-99 + BOFiP BOI-AIS-MOB-10-30-20 § 130 à § 190) : la lecture correcte n'est pas « LLD par défaut » mais **« exonération LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice) »** — conforme au texte, au BOFiP § 180, et à la pratique de Renaud (sans redressement fiscal).
- ~13h00 — Mise à jour en cascade sur 14 fichiers : Z-2024-002 passée en **Résolu**, R-2024-021 entièrement réécrite, CDC § 3.4 enrichi (compteur LCD par couple), CDC § 2.1 v1.4 (nouveaux champs véhicule), ADR-0004 (invalidation), etc. Bilan 2024 final : 5 résolues / 5 ouvertes, plus aucune priorité haute.

### Matin — Polishing final et audit de cohérence

- ~08h00 — Lancement de l'audit de cohérence sur l'ensemble du dossier 2024 (agent dédié).
- ~09h30 — Rapport : 0 incohérence bloquante, 4 incohérences mineures, 4 cosmétiques. Corrections ciblées appliquées (INC-001 à INC-004 + INC-COS-01 à INC-COS-06).
- ~10h30 — Le client pose une question pertinente sur l'omission d'un renvoi Z-2024-002 dans `2024/taxe-polluants/incertitudes.md` — l'exonération LCD touchant les deux taxes, le renvoi y est ajouté (correction d'une lacune méthodologique).

### Matin — Phase 1 production du catalogue 2024 (suite)

- ~06h30 — Production de **`taxes-rules/2024.md`** : synthèse exécutive consolidée, 24 règles R-2024-001 à R-2024-024 selon le format méthodologie § 6.6, avec vue d'ensemble, renvois decisions, niveaux de confiance. Agent dispatché.
- ~07h30 — Livraison du fichier (1 210 lignes, 95 Ko). Vérification spot R-2024-012 Barème PA (mécanique marginale × fraction confirmée partout).

---

## 2026-04-22

### Après-midi et soirée — Phase 1 instruction cas particuliers 2024

- ~18h00 — Lancement de la mission `2024/cas-particuliers/` — dernier sous-dossier à instruire pour clôturer 2024. Scope : 8 cas particuliers (A à H) couvrant frontière M1/N1, véhicule importé d'occasion, bascule PA, hybrides Diesel, qualification LLD/LCD, indisponibilités longues, sortie de flotte, conversion en cours d'année.
- ~19h30 — Livrables produits : 4 fichiers (recherches, decisions, sources, incertitudes). Bilan : 3 incertitudes résolues (Z-2024-004, Z-2024-005, Z-2024-006), 3 consolidées en règles Floty (Z-2024-001, Z-2024-002, Z-2024-007) mais maintenues ouvertes pour validation EC. 1 exigence produit identifiée (historisation des caractéristiques fiscales).
- ~20h30 — Mise à jour CDC § 2.1 et § 2.5 (v1.1 → v1.2 → v1.3) pour intégrer les propositions des cas particuliers : nouveaux champs véhicule, précision sur l'impact fiscal des indisponibilités.

### Après-midi — Phase 1 instruction abattements 2024

- ~16h00 — Lancement de la mission `2024/abattements/`. Scope léger : à priori aucun abattement isolé en 2024 (l'abattement E85 arrive au 01/01/2025 par révision de L. 421-125).
- ~17h00 — Livrable : 4 fichiers. Conclusion : aucun abattement isolé applicable en 2024, minoration 15 000 € (CIBS art. L. 421-111) citée pour mémoire (hors périmètre Floty V1). Z-2024-003 (« Abattement E85 en 2024 ») passée à Résolu. Correction en cascade : mention erronée dans `cartographie-taxes.md` § 7 (« 2024 : abattement E85 ») supprimée → cartographie promue en v0.2.

### Après-midi — Restructuration incertitudes.md

- ~14h30 — Le client propose de déplacer le détail des incertitudes de l'index global vers chaque sous-dossier (principe de cohérence avec `recherches.md`, `decisions.md`, `sources.md`). Refonte appliquée : création de `incertitudes.md` dans chaque sous-dossier (détail), index global transformé en tableaux synthétiques pointant vers les sous-dossiers. Méthodologie mise à jour (§ 6.1 arborescence + § 6.5 format à deux niveaux) → méthodologie v0.3.

### Midi — Mises à jour CDC pour propositions recherche

- ~12h00 — Mise à jour CDC § 5.6 pour préciser l'exonération hybride 2024 L. 421-125 (suite à instruction exonérations) : explicitation des deux jeux de combinaisons d'énergie + aménagement transitoire des seuils pour véhicules ≤ 3 ans. CDC promu en v1.1.

### Matin et midi — Phase 1 instruction exonérations 2024

- ~10h00 — Lancement de la mission `2024/exonerations/`. Scope dense : 10 exonérations à instruire individuellement (CIBS art. L. 421-123 à L. 421-132 pour CO₂, L. 421-136 à L. 421-144 pour polluants).
- ~12h00 — Livrable : 4 fichiers (recherches 826 lignes, decisions 386 lignes, sources 217 lignes, incertitudes). 10 décisions documentées. Z-2024-002 (LLD/LCD) enrichie avec les éléments de l'instruction exonérations ; identification de Z-2024-010 (date de référence ancienneté hybride).

### Matin — Phase 1 instruction taxe polluants 2024

- ~08h30 — Lancement de la mission `2024/taxe-polluants/`. Scope : catégorisation (E, 1, plus polluants) + tarifs forfaitaires.
- ~09h30 — Livrable : 4 fichiers. 6 décisions documentées. Identification de Z-2024-007 (hybrides Diesel-électrique), Z-2024-008 (exemple BOFiP § 290 écart documentaire), Z-2024-009 (garde-fou Crit'Air UX).
- ~10h00 — Vérification directe Légifrance de L. 421-134 (catégorisation) et L. 421-135 (tarifs 0/100/500 €) : travail de l'agent confirmé sans correction.

### Matin — Correction critique du barème PA

- ~07h30 — Vérification des articles Légifrance L. 421-119 à L. 421-122 (WLTP, NEDC, PA). Découverte d'une **erreur structurelle** dans la Décision 6 produite par l'agent taxe-co2 : lecture forfaitaire additive (10 CV = 7 500 €) alors que la doctrine impose tarif marginal × fraction (10 CV = 26 250 €).
- ~08h00 — Corrections en cascade dans les 4 fichiers de `2024/taxe-co2/` : Décision 6 réécrite, § 4.3 recherches.md revu, exemples chiffrés recalculés, tests unitaires actualisés, historiques v0.2 ajoutés. Lecture correcte confirmée par BOFiP § 230 (« les trois barèmes sont des barèmes progressifs par tranches »).

### Matin — Phase 1 instruction taxe CO₂ 2024

- ~06h00 — Lancement de la première mission d'instruction fiscale : `2024/taxe-co2/`. Scope : 3 barèmes (WLTP, NEDC, PA), règles de détermination, redevabilité, prorata.
- ~07h30 — Livrable : 4 fichiers (recherches 45 Ko, decisions 28 Ko, sources 13 Ko, incertitudes 10 Ko à la racine). 10 décisions documentées. 6 incertitudes identifiées (Z-2024-001 à Z-2024-006).

### Matin — Phase 0 cartographie exhaustive des taxes

- ~04h00 — Lancement de la mission de cartographie : recensement exhaustif des taxes françaises applicables aux véhicules d'entreprise (2024-2026), filtrage par critère de redevabilité (entreprise utilisatrice uniquement).
- ~05h30 — Livrable : `cartographie-taxes.md` (482 lignes, 50 Ko). 16 prélèvements recensés, 2 retenus (taxe CO₂ + taxe polluants), 14 écartés avec justification. 6 zones d'incertitude documentées. Confirmation du périmètre Floty pressenti dans le cahier des charges.

---

## 2026-04-21

- Pas d'activité enregistrée (journée de préparation / réflexion hors session active).

---

## 2026-04-20

### Journée — Brainstorming de cadrage, méthodologie, arborescence

- Démarrage effectif du projet après transmission du cahier des charges par le client.
- Brainstorming en plusieurs passes (prompt.txt, prompt-2.txt, prompt-3.txt) : ~30 questions produit tranchées, principes fondateurs actés (fiscalité = donnée, règles non éditables V1, PDF immuables, invalidation par marquage, calcul jour-par-jour), mode de travail établi (réponses client via fichiers prompt.txt à la racine).
- Rédaction et validation de la **méthodologie de recherche fiscale** (`recherches-fiscales/methodologie.md`) — v0.1 initiale puis v0.2 après passe d'annotations client (refonte §3 périmètre, ajout phase 0 cartographie, format incertitudes.md, etc.).
- Création de l'arborescence `project-management/` : `decisions/`, `taxes-rules/`, `modele-de-donnees/`, `specifications-fonctionnelles/`, `plan-implementation/`, `recherches-fiscales/` avec sous-structure 2024/2025/2026.
- Cahier des charges en v1.0 à la livraison initiale par le client.

---

## Conventions du changelog

### Quand ajouter une entrée

- **Décision significative** actée (architecture, produit, périmètre, méthodologie)
- **Livrable produit** (document, sous-dossier, fichier structurant)
- **Correction importante** (bug de cohérence fiscale, refonte suite à clarification client, etc.)
- **Jalon de workflow franchi** (passage d'étape à étape)
- **Pivot ou réorientation** (changement de stratégie, révision majeure d'un choix)

### Quand **ne pas** ajouter d'entrée

- Édition mineure d'un fichier (typo, reformulation locale, cosmétique) — le git log suffit
- Consultation sans modification — lecture n'est pas une action traçable
- Travail en cours non conclu — attendre la conclusion pour une entrée récapitulative

### Format d'une entrée

```
## YYYY-MM-DD

### [Moment — Titre court du périmètre]

- **HH:MM** — Description concise de l'action, avec renvoi vers les fichiers / ADR / sections concernés si utile.
```

Le format reste **narratif** : on décrit ce qui s'est passé, pas seulement ce qui a été créé. Les détails vont dans les fichiers concernés ; le changelog donne la vue d'ensemble.

### Ordre

**Antéchronologique** : le plus récent en haut. Pour retrouver rapidement l'état courant du projet et les dernières actions.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Création initiale du changelog projet. Population rétrospective des entrées du 20/04/2026 au 23/04/2026 couvrant : brainstorming initial, méthodologie de recherche, cartographie phase 0, phase 1 par sous-dossier (taxe CO₂, polluants, exonérations, abattements, cas particuliers), production du catalogue `taxes-rules/2024.md`, polishing, clarification LCD, étape 2 (design moteur de règles + 6 ADR), étape 3 (cadrage MVP + ADR-0007 + roadmap). Conventions définies pour les entrées futures. |
