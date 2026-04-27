<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Vehicle\PollutantCategory;
use App\Exceptions\Fiscal\FiscalCalculationException;

/**
 * Tarif annuel forfaitaire par catégorie polluants (R-2024-014).
 * Chaque {@see PollutantCategory} doit avoir un tarif explicite —
 * impossible de construire un tarif partiel.
 */
final readonly class PollutantTariff
{
    /**
     * @param  array<value-of<PollutantCategory>, float>  $tariffs
     */
    public function __construct(public array $tariffs)
    {
        foreach (PollutantCategory::cases() as $case) {
            if (! array_key_exists($case->value, $tariffs)) {
                throw FiscalCalculationException::pollutantTariffMissingCategory($case->value);
            }
            if ($tariffs[$case->value] < 0.0) {
                throw FiscalCalculationException::pollutantTariffNegative(
                    $case->value,
                    $tariffs[$case->value],
                );
            }
        }
    }

    public function tariffFor(PollutantCategory $category): float
    {
        return $this->tariffs[$category->value];
    }
}
