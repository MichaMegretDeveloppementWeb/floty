<?php

declare(strict_types=1);

namespace App\Actions\Control;

use App\Contracts\Repositories\User\Control\ControlDefinitionWriteRepositoryInterface;
use App\Models\ControlDefinition;

/**
 * Soft-deletes a global control definition. Its recipient deltas stay intact
 * but inert (FK RESTRICT, no physical cascade), matching the application's
 * deletion strategy.
 */
final readonly class DeleteControlDefinitionAction
{
    public function __construct(
        private ControlDefinitionWriteRepositoryInterface $repository,
    ) {}

    public function execute(ControlDefinition $definition): void
    {
        $this->repository->softDelete($definition);
    }
}
