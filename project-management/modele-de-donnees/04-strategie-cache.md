# Stratégie de cache

> **Version** : 1.0
> **Date** : 23 avril 2026
> **Objet** : stratégie de cache Laravel pour le compteur LCD temps réel (CDC § 3.4) et les calculs fiscaux coûteux. Calcul à la volée, cache dès V1.

---

## Contexte

Deux besoins exposent Floty à un risque de performance si le calcul est systématiquement relancé sans mise en cache :

1. **Compteur LCD par couple (véhicule, entreprise) en temps réel** — CDC § 3.4 v1.5 : dans la vue par entreprise, chaque cellule affiche un LCD avec le cumul annuel de jours pour le couple + impact fiscal estimé. Pour une entreprise utilisant 10 véhicules sur 52 semaines, ce sont potentiellement 520 calculs par page chargée.
2. **Dashboard KPI** — CDC § 3.2 : taux d'occupation, estimations fiscales annuelles. Agrégations à l'échelle flotte × année.

**Décision actée** : calcul **à la volée** depuis la source (attributions + caractéristiques historisées), avec **cache Laravel dès V1**. Pas de table de totaux dénormalisés pré-calculés (option 1 écartée).

Justifications :
- Les totaux dénormalisés nécessiteraient leur propre logique d'invalidation à chaque mutation (attribution, indisponibilité, règle). Complexe à garantir correct, source de bugs subtils.
- Le cache Laravel offre le même bénéfice opérationnel (lecture O(1) sur hit) avec une invalidation déclarative par clé.
- L'unique source de vérité reste les tables principales, ce qui respecte ADR-0001 (fiscalité comme donnée recalculable).

---

## 1. Couches de cache

Deux couches distinctes selon la granularité :

### 1.1 Cache « cumul LCD par couple × année »

**Clé** :
```
fiscal:lcd_cumul:vehicle_{vehicle_id}:entreprise_{entreprise_id}:year_{year}
```

**Valeur** : entier (nombre de jours cumulés sur l'année fiscale) + flag booléen (seuil atteint ou non).

**TTL** : infini, invalidation par tag.

### 1.2 Cache « calcul fiscal complet par déclaration »

**Clé** :
```
fiscal:declaration_preview:entreprise_{entreprise_id}:year_{year}
```

**Valeur** : objet structuré avec détail par couple, totaux CO₂ et polluants, motifs d'exonération. Format aligné avec `snapshot_json` (cf. `02-schema-fiscal.md`).

**TTL** : 1 heure (filet de sécurité), invalidation par tag également.

**Note** : ce cache n'est utilisé que pour la **page Déclarations** en mode consultation avant génération PDF. La génération PDF elle-même **ne lit jamais le cache** — elle recalcule systématiquement depuis la source pour produire un snapshot fidèle (ADR-0003).

### 1.3 Cache « heatmap globale annuelle »

**Clé** :
```
planning:heatmap_global:year_{year}
```

**Valeur** : matrice (véhicules × semaines) avec densité d'occupation.

**TTL** : infini, invalidation par tag sur mutation d'attribution.

---

## 2. Tags d'invalidation

Laravel supporte les **cache tags** sur les drivers compatibles (array, redis, memcached — pas file). En V1, le driver par défaut sera `file` sur Hostinger (limitation), ce qui nécessitera :

- Soit de migrer vers **Redis** dès V1 (cf. étape 5 stack technique, option à arbitrer).
- Soit d'utiliser une table SQL `cache` avec un système de tags manuel (clés → tags associés, suppression par tag via DELETE sur jointure).

**Recommandation prestataire** : Redis en V1 si Hostinger le propose (plan mutualisé ou VPS). Sinon, driver `database` de Laravel avec émulation de tags, moins performant mais portable.

### Tags utilisés

| Tag | Invalide quoi |
|---|---|
| `vehicle:{vehicle_id}` | Toutes les entrées concernant ce véhicule |
| `entreprise:{entreprise_id}` | Toutes les entrées concernant cette entreprise |
| `couple:{vehicle_id}:{entreprise_id}` | Spécifiquement le cumul LCD de ce couple |
| `year:{year}` | Toutes les entrées de cette année fiscale |
| `fiscal_rules` | Toutes les entrées de calcul fiscal (invalidation globale sur changement de règle) |

Une entrée de cache peut porter plusieurs tags. Exemple, le cumul LCD du couple (véhicule 42, entreprise 12) pour 2024 est taggué avec :
```
['vehicle:42', 'entreprise:12', 'couple:42:12', 'year:2024']
```

---

## 3. Points de réactivité à la mutation

### 3.1 Mutation d'attribution (create / update / delete soft ou hard)

**Observer** Laravel sur le modèle `Assignment` :
- Dans `created`, `updated`, `deleted`, `restored` : émettre un événement `AssignmentChanged($assignment)`.
- Le listener invalide :
  ```php
  Cache::tags([
      "vehicle:{$assignment->vehicle_id}",
      "entreprise:{$assignment->entreprise_id}",
      "couple:{$assignment->vehicle_id}:{$assignment->entreprise_id}",
      "year:" . Carbon::parse($assignment->date)->year,
  ])->flush();
  ```
- Si l'attribution est déjà liée à une déclaration générée : déclenche aussi la vérification d'invalidation (ADR-0004) via un job en arrière-plan.

### 3.2 Mutation d'indisponibilité

Similaire, uniquement si `has_fiscal_impact = true` (fourrière) — les autres types n'affectent pas le calcul fiscal donc pas de cache à invalider.

### 3.3 Mutation de caractéristiques fiscales véhicule (`vehicle_fiscal_characteristics`)

Invalidation agressive : tous les calculs concernant ce véhicule sur toutes les années recouvertes par la période affectée.
```php
Cache::tags(["vehicle:{$vehicleId}"])->flush();
```

### 3.4 Seeder de règles (modification de `fiscal_rules`)

Invalidation globale :
```php
Cache::tags(['fiscal_rules'])->flush();
```

En pratique, un seeder mettant à jour une règle exécute cet appel en post-hook.

### 3.5 Création / désactivation d'une entreprise ou d'un véhicule

Invalidation partielle ciblée sur l'entité concernée, pour être propre. En réalité, l'impact est limité (pas de cache sur les listes d'entités).

---

## 4. Stratégie de « warmup »

Après une invalidation massive (mise à jour de règle, saisie hebdomadaire de 100 véhicules × 7 jours), la première visite de la page LCD recalculerait tout. Pour éviter un effet de latence perçue :

- **V1** : pas de warmup automatique. Acceptable car les invalidations massives sont rares (seeder de règle = 1×/an ; saisie hebdomadaire = rafale limitée).
- **V2+** : job en arrière-plan qui pré-calcule les cumuls LCD de l'année en cours pour toutes les combinaisons (véhicule × entreprise) ayant au moins une attribution dans l'année. Lancé la nuit et après chaque saisie hebdomadaire significative.

---

## 5. Cohérence cache ↔ PDF

**Garantie** : le PDF ne lit **jamais** le cache. Il recalcule tout depuis la source et produit son snapshot. Cela signifie que :

- Un cache stale n'affectera **jamais** la justesse fiscale d'un PDF généré.
- Un bug d'invalidation cache n'aura d'impact que sur les affichages temps réel (compteur, dashboard), pas sur les livrables fiscaux officiels.

Cette ségrégation est **volontaire** et conforme à l'esprit d'ADR-0003 (PDF immuables fondés sur un snapshot fidèle).

---

## 6. Observabilité

En V1 :

- Logging minimal : hit / miss cache sur les clés principales.
- Commande artisan `php artisan cache:clear` utilisable en cas de doute.
- Pas de dashboard cache dédié (V2).

---

## 7. Alternatives écartées

### Alternative 1 — Table de totaux dénormalisés (`lcd_cumuls`)

Table `(vehicle_id, entreprise_id, year, cumul_jours)` mise à jour par trigger SQL ou par observer applicatif.

**Écartée** : complexité d'invalidation équivalente à celle du cache, mais sans les bénéfices (impossibilité de partager un store entre plusieurs calculs, rigidité de la structure).

### Alternative 2 — Calcul à la volée sans cache

S'appuyer sur les index SQL (notamment `INDEX (vehicle_id, entreprise_id, EXTRACT(YEAR FROM date))`) et des requêtes agrégées directes.

**Écartée** : tenable pour le cumul simple mais pas pour les calculs fiscaux complets (pipeline de règles, prorata, exonérations). Le dashboard et la page Déclarations deviendraient lents sur grosse flotte.

### Alternative 3 — Matérialisation SQL (vue matérialisée PostgreSQL)

Définir des `MATERIALIZED VIEW` avec rafraîchissement manuel ou triggers.

**Écartée** : couplage fort au SGBD, moins de flexibilité que le cache applicatif, UX de rafraîchissement moins fine-tunable.

---

## 8. Récapitulatif en une phrase

**Le cache Laravel accélère l'UX temps réel, mais la justesse fiscale reste garantie par le recalcul systématique depuis la source au moment de la génération PDF.**

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — 3 couches de cache (cumul LCD, calcul fiscal complet, heatmap), tags d'invalidation, points de réactivité sur mutations, garantie PDF recalculé hors cache. |
