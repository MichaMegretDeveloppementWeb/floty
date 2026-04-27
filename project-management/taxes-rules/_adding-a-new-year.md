# Comment ajouter une nouvelle année fiscale dans Floty

> **Statut** : Procédure de référence (méta-doc — pas un catalogue
> d'année). Validée par le test
> `tests/Feature/Fiscal/FiscalRegistryExtensibilityTest.php` qui prouve
> que la mécanique du registry tient la route.

## Vue d'ensemble

L'architecture moteur de règles fiscales (cf. ADR-0006 + chantier
1.8/1.9) est conçue pour qu'**ajouter une année future** soit une
opération **purement additive** — aucun fichier `Year2024/` ne doit
être touché pour ouvrir 2025, 2026, etc.

Cette page décrit la **checklist concrète** à suivre.

## Pré-requis légal

1. Vérifier que les règles fiscales de l'année cible sont **arrêtées**
   (loi de finances promulguée, BOFiP publié). Sans ça, on coderait
   sur du sable.
2. Ouvrir un nouveau fichier `project-management/taxes-rules/{YYYY}.md`
   en s'inspirant strictement du modèle `2024.md`. Y consolider chaque
   règle (R-{YYYY}-{nnn}) avec son type, sa base légale, sa logique de
   calcul, son tableau de paramètres et ses exemples chiffrés.

## Création du code

### 1. Classes Rule

Créer le dossier `app/Fiscal/Year{YYYY}/` avec les sous-dossiers
correspondant aux sous-types réellement présents :

```
app/Fiscal/Year{YYYY}/
├── Classification/   # qualifications (M1/N1, méthode CO₂, polluants)
├── Pricing/          # barèmes (WLTP, NEDC, PA, polluants)
├── Exemption/        # exonérations (handicap, électrique, LCD, …)
├── Abatement/        # abattements (E85 à partir de 2025)
└── Transversal/      # prorata, arrondi, indispos…
```

Chaque classe :
- Nommage : `R{YYYY}_{nnn}_{ShortDescription}.php`
- `final readonly`
- Implémente l'un des 5 sous-types : `ClassificationRule`,
  `PricingRule`, `ExemptionRule`, `AbatementRule`, `TransversalRule`
- `ruleCode()` retourne `R-{YYYY}-{nnn}`
- `taxesConcerned()` retourne la liste des `TaxType` impactés

### 2. Enregistrement dans le registry

Dans `app/Providers/FiscalServiceProvider.php` :

```php
public function register(): void
{
    $this->app->singleton(FiscalRuleRegistry::class, function ($app): FiscalRuleRegistry {
        $registry = new FiscalRuleRegistry($app);

        $this->registerYear2024($registry);
        $this->registerYear{YYYY}($registry);  // ← ajouter

        return $registry;
    });
}

private function registerYear{YYYY}(FiscalRuleRegistry $registry): void
{
    $registry->register({YYYY}, [
        // Classification
        R{YYYY}_004_FiscalTypeQualification::class,
        // Pricing
        R{YYYY}_010_WltpProgressive::class,
        // … etc.
    ]);
}
```

### 3. Configuration

Ajouter l'année dans `config/floty.php` :

```php
'fiscal' => [
    'available_years' => [2024, {YYYY}],
],
```

La première année du tableau reste la valeur de **fallback** quand
aucune année n'est posée en session (cf.
`App\Fiscal\Resolver\FiscalYearResolver`).

### 4. Seeder des métadonnées

Étendre le `FiscalRulesSeeder` ou créer un nouveau
`FiscalRules{YYYY}Seeder`. Chaque règle reçoit `fiscal_year = {YYYY}`,
`applicability_start = '{YYYY}-01-01'`, `applicability_end = '{YYYY}-12-31'`,
et son `code_reference` est calculé automatiquement par le helper
`enrich()` (cf. `database/seeders/FiscalRulesSeeder.php`). Override
explicite `is_active = false` pour les règles inactives par défaut.

## Tests

### 1. Goldens fiscaux

Créer `tests/Unit/Fiscal/Year{YYYY}/...` avec un test golden par
règle ou par cas BOFiP de référence. Modèle : voir
`tests/Unit/Fiscal/FiscalCalculatorTest.php` (goldens 2024).

### 2. Filet de sécurité barèmes

Si l'année introduit de nouveaux barèmes progressifs, étendre
`tests/Unit/Fiscal/BracketsCatalog2024Test.php` ou créer un
`tests/Unit/Fiscal/Year{YYYY}/BracketsTest.php` qui valide les valeurs
DGFiP attendues.

## Déploiement

```bash
# 1. Tests verts
vendor/bin/pint --test --format agent
npm run lint:check
npm run types:check
php artisan test --compact

# 2. Migration BDD (si nouveaux champs Vehicle/Company introduits par
#    les règles, ex. : champ d'activité spécifique)
php artisan migrate

# 3. Seeder des métadonnées des règles
php artisan db:seed --class=FiscalRules{YYYY}Seeder

# 4. Audit registry
php artisan tinker --execute '
    $registry = app(\App\Fiscal\Registry\FiscalRuleRegistry::class);
    var_dump($registry->registeredYears());
    echo count($registry->rulesForYear({YYYY}))." classes pour {YYYY}\n";
'
```

## UX YearSelector

Aucun changement requis : la TopBar utilise déjà `useFiscalYear()` qui
lit `availableYears` depuis les shared props Inertia. Dès que
`config/floty.php` contient 2 années, le sélecteur devient interactif
(en attente de 1.10.bis ou phase ultérieure pour exposer un endpoint
POST permettant à l'utilisateur de basculer son année active).

## Règles structurelles (hors pipeline)

Certaines règles du catalogue 2024 vivent **hors** du registry car
elles ne sont pas calculatoires (R-001, R-007, R-009, R-020, R-023,
R-024 — cf. ADR-0006 § 2 et `FiscalServiceProvider`). Pour une
nouvelle année, vérifier si ces règles ont des équivalents :
- documenter dans `taxes-rules/{YYYY}.md`
- ne PAS les enregistrer dans le registry — leur emplacement est
  contextuel (controller, repository, composable Vue, etc.)

## Validation par le test d'extensibilité

Le test `tests/Feature/Fiscal/FiscalRegistryExtensibilityTest.php`
prouve que la mécanique fonctionne pour une année arbitraire (2099
fake). Si ce test est rouge, ne pas tenter d'ajouter une nouvelle
année — corriger d'abord la régression.

## Historique

| Date | Modification |
|---|---|
| 2026-04-27 | Création du document (phase 1.10) |
