<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal identity of a vehicle targeted by a commercial discount.
 * Carries enough to render a plate + brand/model in the Show list and
 * link back to the vehicle detail page.
 */
#[TypeScript]
final class RentalDiscountVehicleTagData extends Data
{
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $brand,
        public string $model,
    ) {}
}
