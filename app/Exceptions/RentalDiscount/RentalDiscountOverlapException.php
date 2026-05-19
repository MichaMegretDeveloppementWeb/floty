<?php

declare(strict_types=1);

namespace App\Exceptions\RentalDiscount;

use App\Exceptions\BaseAppException;
use App\Models\RentalDiscount;

/**
 * Rental discount create/update would overlap one or more existing discounts
 * (period overlap AND non-empty vehicle intersection). The user message cites
 * the conflicting discounts so the operator can locate and arbitrate them.
 *
 * @phpstan-type ConflictItem array{id: int, label: string|null, startDate: string, endDate: string}
 */
final class RentalDiscountOverlapException extends BaseAppException
{
    /**
     * @param  list<ConflictItem>  $conflicts
     */
    public function __construct(
        public readonly array $conflicts,
    ) {
        $count = count($conflicts);

        $userList = implode(', ', array_map(
            static fn (array $c): string => sprintf(
                '« %s » (%s au %s)',
                $c['label'] ?? sprintf('Réduction #%d', $c['id']),
                self::formatDateFr($c['startDate']),
                self::formatDateFr($c['endDate']),
            ),
            array_slice($conflicts, 0, 3),
        ));

        $suffix = $count > 3 ? sprintf(' et %d autre(s)', $count - 3) : '';

        parent::__construct(
            technicalMessage: sprintf(
                'RentalDiscount overlap detected with %d existing discount(s): %s',
                $count,
                implode(', ', array_map(static fn (array $c): string => (string) $c['id'], $conflicts)),
            ),
            userMessage: $count === 1
                ? "Cette réduction chevauche {$userList}{$suffix}. Ajustez la période ou la liste des véhicules pour éviter le conflit."
                : "Cette réduction chevauche {$count} réductions existantes : {$userList}{$suffix}. Ajustez la période ou la liste des véhicules pour éviter le conflit.",
        );
    }

    /**
     * @param  list<RentalDiscount>  $discounts
     */
    public static function forDiscounts(array $discounts): self
    {
        return new self(array_map(
            static fn (RentalDiscount $d): array => [
                'id' => $d->id,
                'label' => $d->label,
                'startDate' => $d->start_date->toDateString(),
                'endDate' => $d->end_date->toDateString(),
            ],
            $discounts,
        ));
    }

    /**
     * Light ISO Y-m-d to d/m/Y conversion without a Carbon dependency.
     */
    private static function formatDateFr(string $iso): string
    {
        [$y, $m, $d] = explode('-', $iso);

        return sprintf('%s/%s/%s', $d, $m, $y);
    }
}
