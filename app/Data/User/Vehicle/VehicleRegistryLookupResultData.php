<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Résultat d'un lookup véhicule par plaque d'immatriculation.
 *
 * Couvre les 15 champs récupérables depuis un provider de type SIV +
 * bases techniques constructeur (cf. brief client section « Ce qui sera
 * automatique »). Tous les champs métier sont nullables · aucun
 * provider ne garantit toutes les valeurs ; les manquants sont gérés
 * côté front (champ libre, badge « à compléter »).
 *
 * **Hors périmètre intentionnel** :
 *   - `pollutantCategory` · dérivée live par {@see App\Enums\Vehicle\PollutantCategory::derive()}
 *     côté serveur (cf. R-2024-013). Jamais saisie, jamais récupérée.
 *   - `vehicleUserType` · déduit mécaniquement de `receptionCategory`
 *     (M1 = VP, N1 = VU · check constraint en base).
 *   - `firstEconomicUseDate` · valeur Floty interne, pré-initialisée
 *     côté UI à partir de `acquisitionDate` (saisie utilisateur).
 *   - Flags fiscaux M1/N1, `acceptsE85`, `handicapAccess` · jugement
 *     métier, restent à cocher manuellement.
 *
 * Sérialisation camelCase par défaut (convention Floty pour les DTOs
 * de sortie · le composable Vue traduit explicitement vers les clés
 * snake_case du formulaire `useVehicleCreateForm`).
 */
#[TypeScript]
final class VehicleRegistryLookupResultData extends Data
{
    public function __construct(
        // Plaque normalisée (uppercase, sans tirets ni espaces) ·
        // permet au front de vérifier la cohérence avec l'input.
        public string $licensePlate,

        // Identité véhicule
        public ?string $brand,
        public ?string $model,
        public ?string $vin,
        public ?string $color,

        // Dates d'immatriculation (ISO 8601 · YYYY-MM-DD)
        public ?string $firstFrenchRegistrationDate,
        public ?string $firstOriginRegistrationDate,

        // Caractéristiques fiscales
        public ?ReceptionCategory $receptionCategory,
        public ?BodyType $bodyType,
        public ?int $seatsCount,
        public ?EnergySource $energySource,
        public ?UnderlyingCombustionEngineType $underlyingCombustionEngineType,
        public ?EuroStandard $euroStandard,
        public ?HomologationMethod $homologationMethod,
        public ?int $co2Wltp,
        public ?int $co2Nedc,
        public ?int $taxableHorsepower,
        public ?int $kerbMass,

        // Traçabilité (jamais nulles · garanties par chaque strategy)
        public RegistryLookupProvider $sourceProvider,
        public string $fetchedAt,
    ) {}
}
