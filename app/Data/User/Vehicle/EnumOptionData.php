<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Paire valeur/libellé pour les `<SelectInput>` adossés à un enum
 * (catégorie réception, source d'énergie, méthode d'homologation, etc.).
 *
 * `value` = valeur sérialisée de l'enum (`M1`, `gasoline`, …)
 * `label` = libellé FR provenant de `Enum::label()` côté backend.
 */
#[TypeScript]
final class EnumOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
