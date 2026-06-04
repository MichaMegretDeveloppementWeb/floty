<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

/**
 * Reads of control execution documents (Chantier B / B2).
 */
interface ControlExecutionDocumentReadRepositoryInterface
{
    public function countForExecution(int $controlExecutionId): int;
}
