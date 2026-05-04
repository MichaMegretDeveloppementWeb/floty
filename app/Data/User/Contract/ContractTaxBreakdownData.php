<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail fiscal complet d'un contrat - affiché dans la section
 * « Taxes générées » de la page Show contrat.
 *
 * Structure pluriannuelle : un contrat chevauchant 2 années porte 2
 * entrées dans `years`. Le `totalDue` est la somme des `totalDue` de
 * chaque année (déjà arrondies half-up à 2 décimales par année).
 */
#[TypeScript]
final class ContractTaxBreakdownData extends Data
{
    /**
     * @param  list<ContractTaxYearBreakdownData>  $years
     */
    public function __construct(
        #[DataCollectionOf(ContractTaxYearBreakdownData::class)]
        public array $years,
        public float $totalDue,
    ) {}
}
