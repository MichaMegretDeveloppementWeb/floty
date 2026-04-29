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
 * Payload de suppression d'une VFC depuis la modale Historique.
 *
 * Le seul champ porté est la stratégie de comblement du trou laissé
 * par la suppression — l'utilisateur a explicitement choisi entre
 * étendre la version précédente ou la suivante (cf.
 * {@see FiscalCharacteristicsExtensionStrategy}).
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
