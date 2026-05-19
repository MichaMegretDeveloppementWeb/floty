<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\FiscalCharacteristicsExtensionStrategy;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for deleting a VFC, carrying the gap-filling strategy chosen by the user.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class DeleteFiscalCharacteristicsData extends Data
{
    public function __construct(
        #[Required]
        public FiscalCharacteristicsExtensionStrategy $extensionStrategy,
    ) {}
}
