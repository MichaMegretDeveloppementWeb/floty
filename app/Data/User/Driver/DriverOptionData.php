<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option minimale pour le sélecteur driver dans le formulaire Contract
 * (Phase 06 V1.2 - consommé par `DriverSelector.vue`).
 *
 * Filtré par company + période exacte (cf. `DriverQueryService::optionsForContract`).
 */
#[TypeScript]
final class DriverOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $initials,
    ) {}
}
