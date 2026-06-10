<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureReadRepositoryInterface;
use App\Models\VehicleEventNature;

final class VehicleEventNatureReadRepository implements VehicleEventNatureReadRepositoryInterface
{
    public function reductiveLabels(): array
    {
        return $this->labelsWhereReductive(true);
    }

    public function nonReductiveLabels(): array
    {
        return $this->labelsWhereReductive(false);
    }

    /**
     * @return list<string>
     */
    private function labelsWhereReductive(bool $reductive): array
    {
        return VehicleEventNature::query()
            ->where('is_fiscally_reductive', $reductive)
            ->orderBy('label')
            ->pluck('label')
            ->all();
    }
}
