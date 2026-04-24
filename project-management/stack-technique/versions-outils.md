# Audit des versions outils — avril 2026

> **Version** : 1.0
> **Date** : 23 avril 2026
> **Objet** : synthèse des versions stables actuelles de chaque outil envisagé pour la stack Floty, vérification des contraintes Hostinger Business, identification des points d'attention pour l'arbitrage final.
> **Statut** : à valider avec le client avant rédaction ADR-0008.

---

## 1. Contraintes Hostinger Business — vérifiées en avril 2026

Points confirmés ou **corrigés** par rapport à ma compréhension initiale :

| Contrainte | Info client initiale | Vérification web | Conclusion |
|---|---|---|---|
| Version PHP max | 8.4 | Plage 8.2 → 8.5 sélectionnable dans hPanel, 8.3 par défaut | **✓ PHP 8.4 disponible**, voire 8.5. Le client avait raison. |
| Composer | « Non dispo, installer via `php -r` » | **Pré-installé** (Composer 2, commande `composer2` pour PHP ≥ 8.0) | **Correction** : pas besoin d'installer. Utiliser `composer2` en SSH. |
| Node.js + npm | « Pas de Node.js » | Disponible via « Managed Node.js web apps » (5 apps max sur Business), avec npm et SSH | **Correction possible** : Node.js semble disponible en 2026 sur Business. **À re-vérifier** via ton hPanel pour ton plan spécifique. |
| SSH | Disponible | Disponible sur Business et Cloud | **✓** |
| MySQL | Oui, exclusif | Oui, MySQL 8.x | **✓** |

**Impact** :

- ❌ Plus besoin de la pipeline `php composer.phar install` — on utilise `composer2 install --no-dev --optimize-autoloader`.
- ⚠️ Si Node.js + npm sont effectivement dispo en shell SSH, **cela change partiellement la donne** :
  - On peut potentiellement builder les assets Vite directement sur le serveur (pas obligatoire, le build local + push reste plus propre).
  - **Browsershot (PDF via Chrome headless) reste à re-considérer** si Chromium peut être installé — à creuser si tu veux un PDF de meilleure qualité que DomPDF.
  - Pour l'instant, je reste sur DomPDF V1 (décision plus prudente), Browsershot comme chemin d'évolution documenté.
- ✓ La plupart des choix faits restent valides avec ou sans Node.js sur Hostinger.

**Sources** :
- [Hostinger — How to Change PHP Version](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/)
- [Hostinger — Using Composer](https://www.hostinger.com/support/5792078-how-to-use-composer)
- [Hostinger — Web Apps Hosting](https://www.hostinger.com/web-apps-hosting)

---

## 2. Versions stables — avril 2026

### 2.1 Runtimes

| Outil | Version stable | Date | Support/LTS | Notes |
|---|---|---|---|---|
| **PHP** | 8.4 (Hostinger) / 8.5.4 (dernier) | PHP 8.5 : 2025-11 / PHP 8.4 : 2024-11 | PHP 8.4 support actif jusqu'au 31/12/2026, sécurité jusqu'au 31/12/2028 | On prend **PHP 8.4** sur Hostinger Business ; 8.5 envisageable si stable côté Hostinger |
| **Node.js** | 24.15.0 LTS | avril 2026 | LTS actif jusqu'au 30/04/2028 | Pour le build local des assets Vite |
| **MySQL** | 8.0.x (Hostinger) | Hostinger gère la version | Maintenu | Pas de choix de version sur mutualisé |

### 2.2 Backend Laravel

| Outil | Version stable | Date | Notes |
|---|---|---|---|
| **Laravel** | **13.6.0** | release 13.0 : 17/03/2026 | Requiert PHP 8.3+. Zero breaking changes depuis Laravel 12. Native PHP attributes. Stable AI SDK, passkey auth, vector search. |
| **Laravel Breeze** / **Starter Kit Vue** | Starter Kit officiel consolidé (Laravel 12+) | v1.0.2 du repo `laravel/vue-starter-kit` (févr. 2025) | Depuis Laravel 12, consolidation : **un starter kit par stack** (Vue, React, Livewire). Le Vue Starter Kit inclut Composition API + TypeScript + Tailwind + shadcn-vue. |
| **Spatie Laravel Data** | **4.22.0** | 16/04/2026 | Supporte Laravel 13. Frontière PHP↔TS. |
| **Spatie Laravel TypeScript Transformer** | **3.1.1** (typescript-transformer) | 18/03/2026 | Requiert PHP 8.2+. Génère les types TS depuis `#[TypeScript]` sur les DTO. |
| **barryvdh/laravel-dompdf** | **3.1.2** | 21/02/2026 | Wrapper DomPDF 3.x. Supporte Laravel 9 → 13, PHP 8.1+. |
| **Pest** | **4.5.0** | 13/04/2026 | Requiert PHP 8.3+, Laravel 11+. Browser testing, parallel, flaky retries. |
| **PHPUnit** | inclus via Pest ou standalone | — | Pest est construit sur PHPUnit, on peut écrire les deux styles. |

### 2.3 Front Inertia + Vue

| Outil | Version stable | Date | Notes |
|---|---|---|---|
| **Inertia.js** | **v3.0.x** | 26/03/2026 | **Très récent**. Breaking changes depuis v2 : Axios retiré (XHR intégré), SSR out-of-the-box en dev, `Inertia::lazy()` → `Inertia::optional()`, `router.cancel()` → `cancelAll()`, events `invalid/exception` → `httpException/networkError`, `inertia` attribute → `data-inertia`. **Plugin `@inertiajs/vite` nouveau**. |
| **Inertia.js** | alt : v2.3.10 | 15/01/2026 | Support bugs 6 mois / sécurité 12 mois après sortie v3 |
| **Vue 3** | **3.5.33** (stable) | 2025-2026 | 3.6 en bêta (gains perfs réactivité, pas encore stable). On part sur 3.5. |
| **Pinia** | **3.0.4** | stable | Supporte **uniquement Vue 3**. Officialisé comme store Vue. Pas de nouveautés v3 sauf modernisation. |
| **Vue Router** | non requis avec Inertia | — | Inertia gère le routing — Vue Router n'est pas utilisé. |
| **TypeScript** | **6.0** | 17/03/2026 | Dernière release JS-based avant TS 7 (Go) prévu mi/fin 2026. **Stable et recommandé**. |
| **Vite** | **8.0.9** | 12/03/2026 | **Rolldown intégré** (bundler Rust, builds 10-30x + rapides). **Nouveau**. Alt : Vite 7 (sorti juin 2025, stable et éprouvé). |

### 2.4 CSS et composants

| Outil | Version stable | Date | Notes |
|---|---|---|---|
| **Tailwind CSS** | **4.2.0** | 18/02/2026 | Config CSS-first (`@theme`), logical properties, 4 palettes supplémentaires (mauve, olive, mist, taupe), webpack plugin natif. Builds 5x plus rapides (microsecondes en incrémental). |
| **shadcn-vue** (optionnel) | — | — | Inclus dans le Laravel Vue Starter Kit officiel. Bibliothèque de composants accessibles copy-paste. À valider avec le design system Floty. |

### 2.5 Tests front

| Outil | Version stable | Date | Notes |
|---|---|---|---|
| **Vitest** | **4.1.4** | avril 2026 | Native Vite, browser mode, recommandé officiellement Vue |
| **Vue Test Utils** | **2.4.6** | avril 2024 | Version stable depuis longtemps, mature, focus type definitions |
| **@testing-library/vue** (alt ou complément) | — | — | Approche comportementale plutôt que technique. Souvent combinée à Vitest + VTU. |

---

## 3. Matrice de compatibilité croisée

Vérification que les versions choisies fonctionnent ensemble :

| Paire | Compat ? | Commentaire |
|---|---|---|
| Laravel 13 + PHP 8.4 | ✅ | Laravel 13 exige PHP 8.3+, 8.4 OK. |
| Laravel 13 + Spatie Data 4.22 | ✅ | Spatie a suivi Laravel 13 dès sortie. |
| Laravel 13 + Spatie TS Transformer 3.1 | ✅ | Requiert PHP 8.2+, OK avec 8.4. |
| Laravel 13 + DomPDF 3.1 | ✅ | Supporte Laravel 9 → 13. |
| Laravel 13 + Pest 4.5 | ✅ | Pest 4.5 pour Laravel 11+. |
| Inertia v3 + Vue 3.5 | ✅ | Adapter Vue officiel pour Inertia v3. |
| Inertia v3 + Vite 8 | ✅ | Nouveau plugin `@inertiajs/vite` compatible. |
| Vite 8 + Vue 3.5 | ✅ | Vite 8 a intégré Rolldown, compat Vue |
| Vitest 4.1 + Vue 3.5 + Vue Test Utils 2.4 | ✅ | Stack recommandée. |
| Tailwind 4.2 + Vite 8 | ✅ | Tailwind 4 pensé CSS-first et Vite natif. |
| TypeScript 6 + Vue 3.5 | ✅ | Vue 3 type declarations OK avec TS 6. |
| TypeScript 6 + Vitest 4 | ✅ | |
| Starter Kit Vue officiel + Laravel 13 | ⚠️ | Le starter date de févr. 2025 pour Laravel 12. À vérifier après `laravel new`, possiblement à adapter. |
| Inertia v3 + Laravel Breeze | ⚠️ | Breeze original = Laravel 11. Starter Kit officiel est la continuation. À vérifier que le Starter Kit Vue supporte bien Inertia v3 (il peut être encore sur v2.3 si sorti avant mars 2026). |

---

## 4. Points d'attention

> **⚠ Section conservée pour traçabilité historique.** Les recommandations intermédiaires ci-dessous documentent le **dialogue d'arbitrage** qui a mené aux décisions finales. **Pour la décision finale verrouillée**, voir § 5 « Synthèse — stack V1 Floty » et § 6 « Décisions verrouillées (24/04/2026) ». En cas de divergence entre cette section et les § 5/6, **les § 5/6 prévalent**.

### 4.1 Inertia v3 — très récent (sortie 26/03/2026)

Inertia v3 est sorti **il y a moins d'un mois**. Plusieurs implications :

- **Avantages** : fonctionnalités modernes (HTTP client intégré, SSR dev automatique, optimistic updates, `useHttp`), bundle plus léger, plugin Vite officiel.
- **Risques** :
  - Écosystème pas encore complètement aligné : le Laravel Vue Starter Kit officiel date de février 2025 et pourrait pointer Inertia v2 par défaut.
  - Moins de retours terrain, possiblement quelques bugs à révéler.
  - Breaking changes par rapport à v2 (ressources externes, tutoriels, StackOverflow encore partiellement sur v2).
- **Alternative conservatrice** : Inertia v2.3.10 (support bugs 6 mois, sécurité 12 mois).

**Décision à prendre** : pousser sur Inertia v3 ou jouer v2.3 pour sécurité ?

**Recommandation prestataire** : **Inertia v3**. Le surcoût de maintenance (adapter le starter, suivre la doc v3) est modéré face au bénéfice long terme (bundle léger, DX améliorée, nouveauté activement maintenue). Si un reviewer senior voit Inertia v2 sur un projet démarré en avril 2026, il se demandera pourquoi. La livraison V1 n'est pas pour demain, v3 aura 3-6 mois de recul au moment du go-live.

### 4.2 Vite 8 + Rolldown — très récent (12/03/2026)

Vite 8 a intégré **Rolldown** (bundler Rust) comme bundler unifié. Gains annoncés : builds 10-30x plus rapides.

- **Risques** : Rolldown est encore jeune, quelques plugins écosystème non complètement adaptés.
- **Alternative** : Vite 7 (juin 2025, stable et éprouvé).

**Recommandation prestataire** : **Vite 8** si le Laravel Vue Starter Kit l'intègre proprement. Sinon Vite 7 sans hésiter — l'écart n'est pas critique.

### 4.3 Tailwind CSS v4 — architecture CSS-first

Tailwind 4 abandonne la config JS classique au profit de `@theme` en CSS. Ça **change toutes les règles** du design system Floty (`project-management/design-system/`) si elles étaient écrites pour Tailwind 3.

**Décision à prendre** : vérifier que les tokens du design system Floty sont compatibles avec `@theme` Tailwind 4, ou les adapter.

**Recommandation prestataire** : **Tailwind 4** pour un projet neuf. L'adaptation du design system est triviale.

### 4.4 PHP 8.4 vs 8.5 sur Hostinger

Hostinger Business supporte la plage 8.2 à 8.5. On peut partir sur 8.5 qui est officiellement le dernier stable.

**Recommandation prestataire** : **PHP 8.4** pour stabilité + compatibilité écosystème (8.5 encore jeune fin 2025). Si tu préfères 8.5 pour être à jour, aucune contre-indication technique.

### 4.5 Laravel Vue Starter Kit — à adapter

Le starter kit officiel `laravel/vue-starter-kit` (v1.0.2) date de **février 2025**, antérieur à Laravel 13 et Inertia v3. Il faudra **vraisemblablement l'adapter** :

- `composer update` vers Laravel 13
- Passage Inertia v2 → v3 (breaking changes à appliquer)
- Vérifier Tailwind (v3 → v4 probablement)
- Vérifier Vite (v5/6 → v8)

**Pas bloquant** mais à prévoir comme travail d'initialisation du projet.

---

## 5. Synthèse — stack V1 Floty (verrouillée 24/04/2026)

| # | Composant | Version retenue | Justification |
|---|---|---|---|
| 1 | PHP | **8.5** | Local Herd OK, Hostinger Business OK, dernière stable |
| 2 | Laravel | **13.6.x** | Dernière stable, zéro breaking change depuis 12 |
| 3 | MySQL | 8.0 (Hostinger) | Imposé par le plan |
| 4 | Composer | **2** (`composer2` Hostinger) | Pré-installé Hostinger Business |
| 5 | Starter Kit | **AUCUN** | Installation 100 % custom via `laravel new` (installer Herd 5.25.1, supporte Laravel 13). Maîtrise totale du système d'auth, conforme aux habitudes du prestataire. |
| 6 | Inertia.js | **v3.0.x** | Dernière, plugin Vite officiel, bundle léger, SSR dev natif |
| 7 | Vue | **3.5.33** | Stable, 3.6 encore en bêta |
| 8 | Pinia | **3.0.4** | Officiel Vue 3 |
| 9 | TypeScript | **6.0** | Strict mode, dernier avant TS 7 (Go-based) |
| 10 | Vite | **8.0.x** | Rolldown intégré (Rust), 9 patches déjà sortis, plugins mainstream tous compatibles |
| 11 | Tailwind CSS | **4.2.0** | Config CSS-first (`@theme`), dernière stable |
| 12 | UI components | **UI Kit custom Floty** | Construit depuis le `design-system/` Floty (à traduire en composants Vue + tokens Tailwind 4). Pas de shadcn-vue. Travail substantiel mais cohérent avec exigence senior+ et maîtrise totale. |
| 13 | Spatie Laravel Data | **4.22.0** | DTO frontière PHP↔TS |
| 14 | Spatie TS Transformer | **3.1.1** | Génération auto des types TS depuis annotations PHP |
| 15 | DomPDF wrapper | **3.1.2** (`barryvdh/laravel-dompdf`) | DomPDF 3.x ; Browsershot exclu V1 (Node absent en SSH Hostinger) |
| 16 | Tests backend | **PHPUnit** (intégré Laravel) | Conforme aux habitudes du prestataire, pas Pest |
| 17 | Tests front | **Vitest 4.1.4** + **Vue Test Utils 2.4.6** | Stack officielle Vue 3 + Vite |
| 18 | Node.js (build local) | **24 LTS** | Build local uniquement, push assets compilés via Git/SSH (Node **non disponible** en SSH Hostinger Business — confirmé 24/04/2026 par test `node -v`) |
| 19 | Browsershot | **❌ exclu V1** | Bloqué par absence de Node sur Hostinger ; chemin d'évolution VPS documenté |
| 20 | MCP `vite-plugin-vue-mcp` | **différé** | Pas critique V1, à considérer en phase d'implémentation si DX bénéfice avéré |
| 21 | **Laravel Wayfinder** | **v0** (installé via Boost) | Génération auto des fonctions TS typées depuis les controllers Laravel. Remplace Ziggy. Type-safety end-to-end PHP↔TS. Plugin Vite `@laravel/vite-plugin-wayfinder`. Génère dans `resources/js/{wayfinder,actions,routes}/`. |
| 22 | **Laravel Pint** | **v1** | Formatter PHP officiel Laravel (wrapper sur PHP-CS-Fixer). Config `pint.json` à la racine, lancement `vendor/bin/pint`. À intégrer en pre-commit hook + CI. |
| 23 | **Laravel Pail** | **v1** | Tail des logs applicatifs en temps réel : `php artisan pail`. Dev-only. |
| 24 | **Laravel Boost** | **v2** | MCP server qui expose à Claude Code des outils spécifiques Laravel (recherche docs versionnée, `database-query`, `database-schema`, `browser-logs`, etc.). Installé en dev uniquement (`composer require --dev laravel/boost`). |
| 25 | **Laravel MCP** | **v0** | Dépendance de Laravel Boost. Expose Laravel comme serveur MCP. Dev only. |
| 26 | **Laravel Sail** | **❌ non utilisé V1** | Docker dev non retenu. Stack locale via Herd (PHP 8.5 + MySQL). |

---

## 6. Décisions verrouillées (24/04/2026)

| # | Question | Décision | Source |
|---|---|---|---|
| 1 | Inertia v3 vs v2.3 | **v3** | « autant utiliser la dernière version si pas de problème de compatibilité » |
| 2 | Vite 8 vs Vite 7 | **v8** | Recommandation prestataire : compat OK, 9 patches déjà sortis, plugins mainstream tous alignés |
| 3 | PHP 8.4 vs 8.5 | **8.5** | Herd local OK, Hostinger Business OK |
| 4 | Tailwind 4 | **Validé** | Habitude prestataire ; design system Floty à traduire en UI Kit custom basé Tailwind 4 |
| 5 | shadcn-vue | **Refusé** | UI Kit custom Floty à construire depuis le design system, sur mesure |
| 6 | Pest vs PHPUnit | **PHPUnit** | Conforme habitude prestataire |
| 7 | Node.js Hostinger | **Confirmé absent** | Test SSH `node -v` → `command not found`. DomPDF acté pour V1, Browsershot exclu. |
| 8 | MCP `vite-plugin-vue-mcp` | **Différé** | Pas critique V1 |

**Conséquences à intégrer dans la suite du travail** :

- L'ADR-0008 actera ces choix avec leur justification complète.
- Le travail sur le design system devient critique : il existe sous forme HTML/CSS dans `project-management/design-system/`, à **traduire en composants Vue 3 réutilisables** + **tokens Tailwind 4**. Cela représente un volume de travail substantiel à intégrer au plan d'implémentation.
- Les règles d'implémentation (étape 5.3 et 5.4) doivent décrire **comment construire un UI Kit custom** (structure, conventions, accessibilité, théming) — c'est désormais un sujet de premier plan.
- L'auth est 100 % custom : pas de Breeze, pas de Jetstream. Le backend authentification (login email/mdp, pas de rôles V1, pas de reset libre-service — cf. ADR-0007) doit être codé proprement par-dessus Laravel auth natif.

---

## Sources

- [Laravel 13 — Release Notes](https://laravel.com/docs/13.x/releases)
- [Laravel Versions](https://laravelversions.com/en)
- [Vue 3 releases](https://vuejs.org/about/releases)
- [Inertia.js v3 — Documentation](https://inertiajs.com/)
- [Inertia.js v3 — Upgrade Guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide)
- [Tailwind CSS 4.2 — Release](https://laravel-news.com/tailwindcss-4-2-0)
- [Vite 8](https://vite.dev/blog/announcing-vite8)
- [Pinia](https://pinia.vuejs.org/)
- [TypeScript 6.0](https://devblogs.microsoft.com/typescript/announcing-typescript-6-0/)
- [Pest PHP](https://pestphp.com/)
- [Vitest](https://vitest.dev/)
- [Vue Test Utils](https://test-utils.vuejs.org/)
- [Spatie Laravel Data](https://github.com/spatie/laravel-data)
- [Spatie TypeScript Transformer](https://github.com/spatie/typescript-transformer)
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf)
- [Laravel Vue Starter Kit](https://github.com/laravel/vue-starter-kit)
- [Hostinger PHP versions](https://www.hostinger.com/support/php/php-versions/)
- [Hostinger Composer](https://www.hostinger.com/support/5792078-how-to-use-composer)
- [Hostinger Node.js Web Apps](https://www.hostinger.com/web-apps-hosting)
- [PHP Supported Versions](https://www.php.net/supported-versions.php)
- [Node.js Releases](https://nodejs.org/en/about/previous-releases)

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — audit des versions stables avril 2026, vérification contraintes Hostinger Business, matrice de compatibilité, recommandations, 8 questions à trancher. |
| 1.3 | 24/04/2026 | Micha MEGRET | Ajout de 6 composants à la synthèse (étape 5.8) : **Laravel Wayfinder** (routes TS typées, remplace Ziggy), **Laravel Pint** (formatter PHP), **Laravel Pail** (tail logs dev), **Laravel Boost** (MCP Claude Code Laravel), **Laravel MCP** (dépendance Boost), **Laravel Sail** marqué explicitement comme non utilisé. Tous issus du bundle Laravel Boost installé par le starter Laravel 13. |
| 1.2 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — A4 corrigé : ajout d'une note de tête en § 4 « Points d'attention » signalant que cette section est conservée pour traçabilité historique des arbitrages, et que les décisions finales sont en § 5 et § 6 (qui prévalent en cas de divergence). |
| 1.1 | 24/04/2026 | Micha MEGRET | Verrouillage des décisions client : PHP 8.5, Vite 8, Inertia v3, Tailwind 4, PHPUnit, **pas de starter kit (install custom)**, **pas de shadcn-vue (UI Kit custom Floty)**. Confirmation Node.js absent en SSH Hostinger Business → DomPDF V1 acté, Browsershot exclu. |
