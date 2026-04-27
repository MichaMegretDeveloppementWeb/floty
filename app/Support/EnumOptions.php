<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\User\Vehicle\EnumOptionData;
use BackedEnum;

/**
 * Helper transverse pour convertir une liste de cases d'enum
 * en options `<SelectInput>` exposées au front.
 *
 * Chaque enum métier expose une méthode `label()` qui retourne le
 * libellé FR affichable — la valeur brute reste envoyée au backend.
 */
final class EnumOptions
{
    /**
     * @param  array<int, BackedEnum>  $cases
     * @return list<EnumOptionData>
     */
    public static function fromCases(array $cases): array
    {
        return array_map(
            static fn (BackedEnum $case): EnumOptionData => new EnumOptionData(
                value: (string) $case->value,
                label: method_exists($case, 'label')
                    ? $case->label()
                    : (string) $case->value,
            ),
            $cases,
        );
    }
}
