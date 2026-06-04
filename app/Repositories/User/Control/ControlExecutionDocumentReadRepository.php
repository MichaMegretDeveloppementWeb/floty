<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlExecutionDocumentReadRepositoryInterface;
use App\Models\ControlExecutionDocument;

final class ControlExecutionDocumentReadRepository implements ControlExecutionDocumentReadRepositoryInterface
{
    public function countForExecution(int $controlExecutionId): int
    {
        return ControlExecutionDocument::query()
            ->where('control_execution_id', $controlExecutionId)
            ->count();
    }
}
