# Moteur fiscal Floty · règles & contraintes

## Cache fiscal (chantier 2026-05-17)

Le résultat de `FleetFiscalAggregator::vehicleFullYearTaxBreakdown(vehicle, year)`
est mis en cache persistant (driver `database`, TTL 1 h) car ce calcul
est appelé partout (Planning, Vehicle Show, Companies, Dashboard,
Declarations) et coûte ~5-10 ms par véhicule (pipeline fiscal complet).

### Sources d'inputs surveillées (et donc d'invalidation)

| Source | Surveillé par |
|---|---|
| Table `vehicle_fiscal_characteristics` (toutes colonnes) | Observer Eloquent `saved` + `deleted` |
| Bulk deletes `query()->delete()` sur la même table | Invalidation manuelle dans `VehicleFiscalCharacteristicsWriteRepository` |
| `vehicles.first_origin_registration_date` (R-2024-017) | Observer Eloquent `Vehicle` `saved` (conditionnel `wasChanged`) |
| Cycle de vie Vehicle (`deleted`, `restored`, `forceDeleted`) | Observer Eloquent `Vehicle` |
| Fichiers règles fiscales `app/Fiscal/Year{YYYY}/*` | `php artisan cache:clear` dans `deploy.sh` ligne ~130 |

### ⛔ Règle d'or · pas de raw SQL sur ces tables

Les méthodes ci-dessous DOIVENT être l'**unique** chemin d'écriture sur
`vehicle_fiscal_characteristics` et sur `vehicles.first_origin_registration_date` ·

- `VehicleFiscalCharacteristics::create([...])` / `::factory()->create([...])`
- `$vfc->update([...])` / `$vfc->delete()`
- `VehicleFiscalCharacteristicsWriteRepository::*` (méthodes publiques)
- `Vehicle::create([...])` / `$vehicle->update([...])`
- `VehicleWriteRepository::*`

**Interdit** ·
- `DB::table('vehicle_fiscal_characteristics')->update/delete/insert(...)`
- `DB::statement('UPDATE vehicle_fiscal_characteristics SET ...')`
- Toute manipulation `tinker` qui contournerait les Observers (`Model::withoutEvents()`)

### Si raw SQL devient inévitable (cas exceptionnel)

Encadrer l'opération par une invalidation manuelle ·

```php
use App\Services\Fiscal\FiscalCacheInvalidator;

$invalidator = app(FiscalCacheInvalidator::class);

// AVANT le raw SQL · invalide les véhicules impactés
foreach ($impactedVehicleIds as $id) {
    $invalidator->invalidateForVehicle($id);
}

DB::statement('UPDATE vehicle_fiscal_characteristics SET ... WHERE ...');
```

Et ajouter un commentaire `// CACHE INVALIDATION · raw SQL, invalidation
manuelle ligne X` pour faciliter la relecture future.

### Filet de sécurité

TTL 1 h sur le cache (`FiscalCacheInvalidator::CACHE_TTL_SECONDS`).
Une invalidation oubliée ne reste donc jamais plus d'1 h. Pour
remonter le TTL (24 h+ par exemple) une fois confiance acquise et
monitoring en place, modifier la constante.

### Doc complémentaire

- `project-management/recherches-fiscales/methodologie.md` · section
  cache fiscal côté gouvernance fiscale.
- Tests `tests/Feature/Fiscal/FiscalCacheInvalidationTest.php` · 9
  tests d'invalidation exhaustifs (preuve par tests des 4 piliers de
  la garantie).

## Architecture du moteur

Cf. `project-management/recherches-fiscales/methodologie.md` pour la
méthodo fiscale complète (sources BOFiP, traçabilité légale,
nomenclature des règles).
