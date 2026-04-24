# ADR-0008 — Stack technique V1

> **Statut** : Acceptée
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Après les ADR-0001 à ADR-0007 qui ont cadré la fiscalité comme donnée, l'architecture du moteur de règles, l'immuabilité des PDF, l'invalidation par marquage, le calcul jour-par-jour et le périmètre V1 MVP, il restait à fixer la **stack technique** qui porterait l'implémentation Floty V1.

Le choix de la stack conditionne :

- **La capacité à livrer dans les délais** (courbe d'apprentissage, maturité de l'écosystème).
- **Les contraintes d'exploitation** (hébergement Hostinger Business mutualisé).
- **La qualité perçue par les reviewers seniors** qui examineront le code (cohérence avec les standards 2026 de l'écosystème Laravel/Vue).
- **La facilité d'évolution** vers V1.2 (facturation loyers), V2 (analytics, rôles) et V3 (IA).

L'étape 5 du workflow projet a comporté trois sous-étapes :

1. **Étape 5.1** — Audit des versions stables à jour (avril 2026) de chaque outil envisagé, vérification des contraintes Hostinger Business (`project-management/stack-technique/versions-outils.md`).
2. **Étape 5.2** — Arbitrage avec le client des 8 questions structurantes (verrouillé le 24/04/2026).
3. **Étape 5.3 et 5.4** — Refonte des règles d'implémentation existantes (`architecture-solid`, `structure-fichiers`, `assets-vite`, `gestion-erreurs`, `conventions-nommage`) et rédaction de 7 nouvelles règles Inertia/Vue/TS de niveau senior+ (`typescript-dto`, `vue-composants`, `inertia-navigation`, `composables-services-utils`, `pinia-stores`, `performance-ui`, `tests-frontend`). L'ensemble est dans `project-management/implementation-rules/`.

Cet ADR formalise les décisions stack résultantes.

---

## Décision

### Stack V1 Floty — synthèse verrouillée

| # | Composant | Version retenue | Justification principale |
|---|---|---|---|
| 1 | **PHP** | 8.5 | Dernière stable PHP, disponible sur Hostinger Business et en local via Herd |
| 2 | **Laravel** | 13.6.x | Dernière majeure stable (sortie 17 mars 2026), zéro breaking change depuis Laravel 12 |
| 3 | **MySQL** | 8.0 (Hostinger) | Imposé par le plan mutualisé Hostinger Business |
| 4 | **Composer** | 2 (`composer2` sur Hostinger) | Pré-installé côté serveur, pas d'installation manuelle |
| 5 | **Inertia.js** | v3.0.x | Dernière majeure (26 mars 2026), bundle léger, SSR dev natif, plugin Vite officiel |
| 6 | **Vue** | 3.5.33 | Stable ; Vue 3.6 encore en bêta |
| 7 | **Pinia** | 3.0.4 | Outil officiel Vue 3, stable, TypeScript first |
| 8 | **TypeScript** | 6.0 | Dernière majeure avant TS 7 Go-based (mi/fin 2026) |
| 9 | **Vite** | 8.0.x | Rolldown intégré (Rust), 9 patches déjà sortis, plugins Floty tous compatibles |
| 10 | **Tailwind CSS** | 4.2.0 | Config CSS-first via `@theme`, dernière stable |
| 11 | **Spatie Laravel Data** | 4.22.0 | DTO frontière PHP↔TS, supporte Laravel 13 |
| 12 | **Spatie TS Transformer** | 3.1.1 | Génération auto des types TS depuis les DTO PHP annotés |
| 13 | **DomPDF wrapper** | 3.1.2 (`barryvdh/laravel-dompdf`) | Contrainte Hostinger (pas de Node en SSH → Browsershot exclu V1) |
| 14 | **Tests backend** | PHPUnit (Laravel intégré) | Conforme à l'habitude du prestataire |
| 15 | **Tests frontend** | Vitest 4.1.4 + Vue Test Utils 2.4.6 | Stack officielle Vue 3 + Vite |
| 16 | **Node.js (build local uniquement)** | 24 LTS | Build local + push des assets compilés ; pas de Node en production |
| 17 | **Auth scaffold** | Aucun starter kit | Installation custom via `laravel new` + auth sur mesure (maîtrise totale) |
| 18 | **UI Kit** | Custom Floty depuis `design-system/` | Pas de shadcn-vue ni autre bibliothèque tierce — UI Kit construit sur le design system client |
| 19 | **Browsershot** | Exclu V1 | Confirmé : `node -v` SSH Hostinger → `command not found` |
| 20 | **MCP `vite-plugin-vue-mcp`** | Différé | Pas critique V1, à évaluer en phase d'implémentation |
| 21 | **Laravel Wayfinder** | v0 (via Boost) | Génération automatique de **fonctions TypeScript typées** depuis les controllers Laravel. Remplace Ziggy. Type-safety end-to-end PHP↔TS sur le routing. |
| 22 | **Laravel Pint** | v1 | Formatter PHP officiel Laravel. Configuration partagée (`pint.json`). Lancement à chaque commit (pre-commit hook ou CI). |
| 23 | **Laravel Pail** | v1 | Tail des logs en temps réel en dev (`php artisan pail`). |
| 24 | **Laravel Boost + Laravel MCP** | v2 / v0 | MCP server assistant Claude Code sur les opérations Laravel courantes (recherche docs, schéma BDD, routes, tinker). Installé en dev uniquement. |
| 25 | **Laravel Sail** | **Non utilisé** | Pas de Docker dev en V1. Stack locale via Herd. |

Le document de recherche complet (dates de release, matrice de compatibilité, sources web) est dans `project-management/stack-technique/versions-outils.md`.

### Architecture applicative

Conformément aux règles documentées dans `project-management/implementation-rules/architecture-solid.md` (v2.1) :

- **4-layer backend** : Controller → Action → Service → Repository.
- **5ᵉ couche de remontée** : Resource (DTO Spatie Data) entre Controller et Vue, avec génération automatique des types TypeScript.
- **Segmentation par Espace** dès V1 : `Web/` (public) et `User/` (connecté), extensible `User/{Role}/` en V2.
- **Segmentation par Domaine** : Vehicle, Company, Driver, Assignment, Unavailability, Declaration, Planning, Fiscal, Auth.
- **Pragmatisme** assumé : une couche n'existe que si elle encapsule une complexité réelle.

### Frontend

- **Inertia v3** comme pont Laravel ↔ Vue. Un point d'entrée JavaScript unique (`resources/js/app.ts`), Vite assure le code splitting automatique par page Inertia via `import.meta.glob`.
- **Vue 3** en **Composition API stricte** avec `<script setup lang="ts">`, TypeScript strict activé (`"strict": true`, `"noUncheckedIndexedAccess": true`, etc.).
- **Laravel Wayfinder** génère automatiquement des **fonctions TypeScript typées** pour chaque controller/route. Le code front consomme les routes via `VehicleController.show({ vehicle: id })` au lieu de `route('user.vehicles.show', { vehicle: id })`. Type-safety **end-to-end** : renommer une route côté Laravel produit une erreur TS au build.
- **Pattern « une page = un dossier + Partials »** pour tous les composants significatifs.
- **Distinction explicite** : `Components/Ui/` (UI Kit custom), `Components/Domain/` (composants métier réutilisables), `Components/Layouts/` (squelettes page).
- **Tailwind CSS 4** en modèle CSS-first avec `@theme`, intégrant les tokens du design system Floty. **Modèle hybride pragmatique** pour le CSS : Tailwind utility par défaut, fichiers `.css` dédiés segmentés quand le besoin réel le justifie (animations complexes, palettes spécifiques, `@media print`, surcharges de libs tierces), `<style scoped>` pour les cas vraiment locaux.

### Build et déploiement

Contrainte Hostinger Business : **pas de Node.js en SSH**. Conséquences :

- Le build Vite se fait **localement** sur le poste développeur (Node 24 LTS via Herd ou autre).
- Les assets compilés (`public/build/`) sont **versionnés et poussés** via Git.
- La commande de déploiement côté serveur utilise `composer2 install --no-dev --optimize-autoloader`, sans build npm.
- Un workflow CI/CD (GitHub Actions) automatise build + push des assets vers Hostinger (à documenter lors de l'implémentation).

### Stockage PDF

Les PDF récapitulatifs fiscaux sont stockés en **filesystem local** (disque Laravel `local`), avec le chemin relatif en base. Voir `project-management/modele-de-donnees/02-schema-fiscal.md`.

### Tests

- **Backend** : PHPUnit intégré à Laravel, couverture sur les Services, Actions, Repositories et moteur de règles fiscales.
- **Frontend** : Vitest pour les utils, composables, stores, composants UI Kit. Fichiers `.spec.ts` adjacents au code testé. Fixtures typées via DTO Spatie Data.

### Hors périmètre V1

Les éléments suivants restent exclus en V1 :

- **Browsershot** (PDF par Chrome headless) — bloqué par l'absence de Node sur Hostinger. Chemin d'évolution documenté si passage VPS.
- **Redis** — bloqué par Hostinger Business mutualisé. Driver cache `database` à la place.
- **SSR Inertia** — désactivé V1 (pas de bénéfice SEO, pas de Node en prod).
- **Tests E2E (Playwright)** — reportés à V2.

---

## Justification

### Pourquoi Laravel 13 + PHP 8.5

Laravel 13 (sortie 17 mars 2026) apporte zéro breaking change depuis Laravel 12, une AI SDK stable, passkey auth et vector search. PHP 8.5 est la dernière stable et supportée par Hostinger Business. Prendre la dernière version garantit un support long et un alignement avec les standards 2026 reviewers seniors. L'écosystème Spatie (Data, TS Transformer) supporte Laravel 13 depuis sa sortie.

### Pourquoi Inertia + Vue plutôt que Livewire + Alpine

La discussion détaillée est documentée dans le dialogue prestataire/client (23-24/04/2026). Synthèse : **3 des 10 vues V1** (saisie hebdomadaire tableur, heatmap globale filtrable, compteur LCD temps réel) sont **structurellement denses en interactions client**, ce qui met en tension le modèle server-driven de Livewire. Inertia+Vue est structurellement plus adapté à ces vues tout en restant aussi simple que Livewire sur les 7 vues CRUD standard. Sur Hostinger mutualisé, Inertia est **plus doux pour le serveur** (décharge vers le navigateur après le premier chargement).

### Pourquoi Inertia v3 et pas v2.3

Bien qu'Inertia v3 soit sorti récemment (26 mars 2026), ses apports (bundle léger, plugin Vite officiel, SSR dev natif, `Inertia::defer`, optimistic updates) valent l'investissement. Au moment du go-live V1, v3 aura plusieurs mois de recul. Un reviewer senior en 2026 serait en droit de questionner un choix Inertia v2.3 sur un projet démarré maintenant.

### Pourquoi Vite 8 avec Rolldown

Vite 8 a intégré Rolldown (bundler Rust) le 12 mars 2026 avec 9 patches déjà publiés. L'équipe Vite est la même qui a développé Rollup, la fiabilité est établie. Les plugins utilisés par Floty (plugin Vue, plugin Tailwind 4, plugin Inertia, plugin Laravel) sont tous officiels et compatibles. Plan B trivial : downgrade `vite@7` si on rencontre un blocage spécifique.

### Pourquoi pas de starter kit Laravel Breeze / Vue Starter Kit

Le client préfère une installation custom pour garder la maîtrise totale du système d'auth. L'installer `laravel new` (Herd 5.25.1) supporte Laravel 13 de base, permettant de démarrer sur une installation propre sans dépendance au starter kit officiel. Cohérent avec l'habitude du prestataire ; permet un contrôle fin de ce qui entre dans le projet (pas de shadcn-vue, pas d'options inutiles V1).

### Pourquoi pas de shadcn-vue et UI Kit custom à la place

Le client dispose déjà d'un design system HTML/CSS (`project-management/design-system/`) à traduire en composants Vue. Plutôt que d'intégrer une bibliothèque tierce qu'il faudrait customiser pour respecter le design system, on construit un UI Kit sur mesure (`resources/js/Components/Ui/`) adapté 100 % aux tokens Floty. Coût initial significatif mais maîtrise totale et cohérence visuelle garantie.

### Pourquoi DomPDF et pas Browsershot

Confirmé par test SSH (`node -v` → `command not found`) : Node n'est pas disponible sur Hostinger Business mutualisé. Browsershot (qui requiert Chrome headless via Puppeteer Node) est donc exclu V1. DomPDF reste la référence PHP native pour Laravel et produit un PDF acceptable si le template est pensé pour ses contraintes CSS2.1 dès l'origine.

### Pourquoi PHPUnit et pas Pest

Pest apporte une syntaxe plus élégante, mais PHPUnit reste le standard Laravel intégré et conforme à l'habitude du prestataire. Le gain Pest ne justifie pas la courbe d'apprentissage pour le reviewer et les potentielles frictions. Possibilité de migrer en V2 si l'équipe s'élargit et veut adopter Pest.

### Pourquoi ce niveau de documentation (12 docs d'implémentation totalisant ~9 800 lignes)

Le projet sera soumis à critique de développeurs seniors experts (Laravel, Vue, TS, JS). Une documentation complète et cohérente est le levier principal pour garantir une implémentation de qualité senior+ sur la durée, indépendamment de l'instance qui code. Le coût d'écriture est amorti par :

- **Onboarding** : un nouvel intervenant a une base solide pour prendre le relais.
- **Consistance** : les règles documentées servent de source de vérité pour toutes les décisions d'implémentation.
- **Traçabilité** : les anti-patterns sont explicités, ce qui accélère la revue de code.
- **Évolutivité** : V1.2, V2, V3 s'appuieront sur les mêmes règles.

---

## Alternatives écartées

### Alternative 1 — Livewire 4 + Alpine.js

Conservé comme option jusqu'au dialogue du 23/04/2026. Écarté après analyse détaillée des 10 vues V1 : 3 vues stratégiques (saisie tableur, heatmap filtrable, compteur LCD temps réel) sont structurellement inadaptées au modèle server-driven de Livewire.

### Alternative 2 — Inertia v2.3 (conservateur)

Sortie le 15 janvier 2026, supporté 12 mois security après sortie v3. Écarté pour éviter de démarrer un projet neuf sur une version qui sera en maintenance dans 6 mois.

### Alternative 3 — Vite 7 (conservateur)

Sortie en juin 2025, largement rodé. Écarté car Vite 8 est stable, les plugins Floty sont compatibles, et l'écart de rapidité (10-30× builds) est un bonus DX. Plan B trivial (downgrade) si problème.

### Alternative 4 — PHP 8.4

PHP 8.5 est la dernière stable supportée par Hostinger. Prendre 8.4 au lieu de 8.5 n'apportait rien ; 8.5 a été choisi pour rester à jour.

### Alternative 5 — Pest

Syntaxe plus moderne mais courbe d'apprentissage, et PHPUnit reste le standard Laravel. Décision pragmatique du prestataire.

### Alternative 6 — Laravel Vue Starter Kit officiel

Accélérait le démarrage mais imposait une dépendance à shadcn-vue et un scaffold auth que le prestataire préfère custom. Écarté.

### Alternative 7 — PostgreSQL via DBaaS externe

PostgreSQL aurait permis les exclusion constraints natives (chevauchement de périodes), JSONB, index partiels. Mais le surcoût (60-120 €/mois pour une DBaaS) n'est pas justifié par le volume V1. Le plan Hostinger Business n'offre pas PostgreSQL nativement. Décision : rester sur MySQL 8, documenter le chemin d'évolution vers PostgreSQL si passage VPS ultérieur.

### Alternative 8 — Browsershot (PDF Chromium)

Qualité PDF supérieure (CSS moderne, Tailwind support complet). Bloqué par l'absence de Node sur Hostinger Business. Chemin d'évolution vers VPS documenté.

### Alternative 9 — Redis pour le cache

Plus rapide que le driver `database`, supporte nativement les tags Laravel. Bloqué par Hostinger Business. Le driver `database` avec table `cache` + émulation de tags suffit pour le volume V1 (~100 véhicules × 30 entreprises).

### Alternative 10 — SSR Inertia

Améliore le premier chargement sur les pages lourdes (heatmap). Désactivé V1 car :

1. Bloqué par l'absence de Node en prod.
2. Floty est B2B derrière login → pas de bénéfice SEO.
3. Complexité supplémentaire non justifiée pour V1.

---

## Conséquences

### Conséquences positives

- **Stack moderne et cohérente** : tous les composants sont alignés sur les dernières versions stables 2026, ce qui garantit support long et qualité perçue par les reviewers.
- **Type-safety end-to-end** : Spatie Data + TS Transformer garantissent l'absence de divergence PHP↔TS, détectée à la compilation.
- **Architecture évolutive** : la segmentation par Espace dès V1 permet l'ajout de rôles V2 sans refonte. Le modèle de cache par tags permet la montée vers Redis VPS sans toucher au code applicatif.
- **Qualité senior+** : les 12 docs d'implémentation posent un cadre rigoureux qui sert de socle à toutes les décisions d'écriture du code.
- **Autonomie prestataire** : pas de dépendance à des starter kits ou libs lourdes maintenues par des tiers. Le prestataire maîtrise 100 % du code produit.

### Conséquences techniques — contraintes assumées

- **MySQL au lieu de PostgreSQL** : certaines contraintes (exclusion constraints, JSONB, index partiels) doivent être implémentées en applicatif ou via triggers MySQL. Moins élégant que PostgreSQL mais gérable. Cf. adaptations `01-schema-metier.md` et `02-schema-fiscal.md`.
- **DomPDF au lieu de Browsershot** : le template PDF doit être pensé pour les limites de DomPDF (CSS2.1, pas de flexbox ni grid). Conception initiale légèrement plus contrainte.
- **Build assets local** : le workflow de déploiement nécessite un CI/CD (GitHub Actions) pour automatiser le build et le push. Prévu lors de l'implémentation.
- **Pas de Node en prod** : exclut Browsershot, SSR Inertia, et les dépendances Node en runtime serveur. Contrainte stable V1, levée possible en V3 via VPS.

### Conséquences produit

- **Design system à traduire en UI Kit Vue** : travail substantiel au démarrage pour produire des composants Vue typés qui respectent les tokens Tailwind 4 `@theme`. Investissement qui paie sur toute la durée du projet.
- **Qualité visuelle maîtrisée** : l'UI Kit custom garantit une cohérence visuelle parfaite, sans dépendance à des défauts de bibliothèques tierces.

### Conséquences organisationnelles

- **Rédaction d'une documentation dense avant code** : investissement amont qui accélère l'écriture du code (les règles sont arrêtées) et facilite l'onboarding/reprise.
- **Pipeline CI/CD à mettre en place** : prérequis pour le déploiement Hostinger. À faire tôt dans l'implémentation.

### Conséquences économiques

- **Pas de surcoût mensuel récurrent** sur l'infrastructure V1 (Hostinger Business + MySQL inclus, pas de DBaaS externe, pas de Redis, pas de VPS).
- **Coût d'évolution vers VPS** : documenté comme chemin de montée en charge pour V2/V3. Plan B activable si besoin (Browsershot, Redis, PostgreSQL, SSR Inertia).

---

## Chemin d'évolution vers un VPS (V2 ou V3)

Si V2 ou V3 nécessitent plus de performance ou de sophistication, le passage à un VPS (Hostinger Cloud, OVH, Scaleway, etc.) permettrait :

- **PostgreSQL natif** : remplacement du MySQL par PostgreSQL. Migrations à adapter (types, index partiels, exclusion constraints — peuvent remplacer les triggers actuels).
- **Browsershot** : remplacement de DomPDF par Browsershot pour un PDF de bien meilleure qualité visuelle.
- **Redis** : driver cache passe de `database` à `redis` (simple changement dans `config/cache.php`).
- **SSR Inertia** : activation du serveur SSR Node.js pour améliorer le premier chargement.
- **Node en SSH** : build sur le serveur devient possible, workflow CI/CD allégé.

**Aucun de ces passages ne nécessite de refonte du code applicatif** : ce sont tous des changements d'infrastructure + config. Le modèle de données et le code restent inchangés.

---

## Liens

- **Document de recherche** : `project-management/stack-technique/versions-outils.md` (versions détaillées, matrice de compatibilité, sources web).
- **Règles d'implémentation** (12 documents) : `project-management/implementation-rules/`
  - `architecture-solid.md` — 4-layer + Resource, segmentation par espace.
  - `structure-fichiers.md` — arborescence et patterns.
  - `conventions-nommage.md` — nommage PHP, TS, Vue, BDD.
  - `assets-vite.md` — bundling et modèle CSS hybride.
  - `gestion-erreurs.md` — exceptions typées et flux Inertia.
  - `typescript-dto.md` — Spatie Data + génération auto types TS.
  - `vue-composants.md` — Composition API stricte.
  - `inertia-navigation.md` — patterns de navigation.
  - `composables-services-utils.md` — distinction des 3 concepts.
  - `pinia-stores.md` — Pinia comme outil de réserve.
  - `performance-ui.md` — zones critiques Floty, anti-pattern skeleton/lazy.
  - `tests-frontend.md` — Vitest + VTU + fixtures typées.
- **ADR précédents** : ADR-0001 à ADR-0007.
- **Modèle de données** : `project-management/modele-de-donnees/` (à adapter pour MySQL — cf. étape 5.5 A2 et A3).

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.2 | 24/04/2026 | Micha MEGRET | Intégration de **Laravel Wayfinder** (remplace Ziggy pour les routes typées), **Laravel Pint** (formatter PHP), **Laravel Pail** (tail logs), **Laravel Boost + MCP Laravel** (assistance Claude Code en dev). Exclusion explicite de **Laravel Sail** (stack locale Herd). Ajout de la mention « type-safety end-to-end PHP↔TS sur le routing » dans la section Frontend. 5 entrées ajoutées à la synthèse stack (21 → 25 composants listés). |
| 1.1 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — suppression du doublon « Pourquoi un UI Kit custom et pas shadcn-vue » (la justification existait déjà sous le titre « Pourquoi pas de shadcn-vue et UI Kit custom à la place »). |
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — formalisation de la stack technique V1 après audit des versions (étape 5.1), verrouillage des décisions client (étape 5.2), refonte/rédaction des règles d'implémentation (étapes 5.3 et 5.4). 20 composants techniques arrêtés, 10 alternatives écartées avec justification, chemin d'évolution vers VPS documenté. |
