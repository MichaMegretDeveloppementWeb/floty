<?php

declare(strict_types=1);

namespace App\Support\VehicleEvent;

/**
 * Composes the final category list of a vehicle event (Chantier A, multi
 * categories). Fixed defaults first (type default, or the control defaults),
 * then the user's custom additions; each value trimmed, deduplicated
 * case-insensitively (the first occurrence wins, so a default is never dropped
 * by a custom duplicate), and capped at {@see self::MAX}.
 */
final class EventCategoryList
{
    public const int MAX = 5;

    /**
     * @param  list<string>  $defaults  Fixed categories (kept, never editable)
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

            if (count($result) >= self::MAX) {
                break;
            }
        }

        return $result;
    }
}
