<?php

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Bundle d'options enum pour le formulaire `Vehicles/Create.vue` —
 * chaque clé alimente un `<SelectInput>`.
 */
#[TypeScript]
final class VehicleFormOptionsData extends Data
{
    /**
     * @param  list<EnumOptionData>  $receptionCategories
     * @param  list<EnumOptionData>  $vehicleUserTypes
     * @param  list<EnumOptionData>  $bodyTypes
     * @param  list<EnumOptionData>  $energySources
     * @param  list<EnumOptionData>  $euroStandards
     * @param  list<EnumOptionData>  $homologationMethods
     * @param  list<EnumOptionData>  $pollutantCategories
     */
    public function __construct(
        public array $receptionCategories,
        public array $vehicleUserTypes,
        public array $bodyTypes,
        public array $energySources,
        public array $euroStandards,
        public array $homologationMethods,
        public array $pollutantCategories,
    ) {}
}
