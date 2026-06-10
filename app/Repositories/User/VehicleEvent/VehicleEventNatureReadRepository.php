<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureReadRepositoryInterface;
use App\Models\VehicleEventNature;
use App\Support\VehicleEvent\EventNatureCatalog;

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

    public function customSuggestions(): array
    {
        $baseKeys = array_map('mb_strtolower', EventNatureCatalog::NON_REDUCTIVE);

        return VehicleEventNature::query()
            ->where('is_fiscally_reductive', false)
            ->orderBy('label')
            ->get(['id', 'label'])
            ->reject(static fn (VehicleEventNature $n): bool => in_array(mb_strtolower($n->label), $baseKeys, true))
            ->map(static fn (VehicleEventNature $n): array => ['id' => $n->id, 'label' => $n->label])
            ->values()
            ->all();
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
