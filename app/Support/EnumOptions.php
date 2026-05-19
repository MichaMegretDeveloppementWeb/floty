<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\User\Vehicle\EnumOptionData;
use BackedEnum;

/**
 * Converts a list of backed-enum cases into `<SelectInput>` options for
 * the frontend. Each enum is expected to expose a `label()` method
 * returning the user-facing French label; the raw value remains the
 * one sent back to the backend.
 */
final class EnumOptions
{
    /**
     * Build the option list from the provided enum cases.
     *
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
