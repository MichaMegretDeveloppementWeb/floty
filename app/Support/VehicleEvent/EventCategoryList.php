<?php

declare(strict_types=1);

namespace App\Support\VehicleEvent;

/**
 * Composes the final nature list of a vehicle event (UI « Nature », kept under
 * the `categories` naming in code). Fixed defaults first (the auto-generated
 * natures of control executions / lifecycle markers), then the user's
 * additions; each value trimmed and deduplicated case-insensitively (the first
 * occurrence wins, so a default is never dropped by a duplicate). Unlimited.
 */
final class EventCategoryList
{
    /**
     * @param  list<string>  $defaults  Fixed natures (auto-generated)
     * @param  list<string>  $custom  User-supplied additions
     * @return list<string>
     */
    public static function compose(array $defaults, array $custom): array
    {
        $result = [];
        $seen = [];

        foreach ([...$defaults, ...$custom] as $raw) {
            $value = trim($raw);

            if ($value === '') {
                continue;
            }

            $key = mb_strtolower($value);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $value;
        }

        return $result;
    }
}
