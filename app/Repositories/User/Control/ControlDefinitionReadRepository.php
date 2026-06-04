<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Data\User\Control\ControlDefinitionData;
use App\Models\ControlDefinition;

final class ControlDefinitionReadRepository implements ControlDefinitionReadRepositoryInterface
{
    /**
     * @return array<int, ControlDefinitionData>
     */
    public function listForCatalog(): array
    {
        return ControlDefinition::query()
            ->with('recipientDeltas')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(static fn (ControlDefinition $definition): ControlDefinitionData => ControlDefinitionData::fromModel($definition))
            ->all();
    }
}
