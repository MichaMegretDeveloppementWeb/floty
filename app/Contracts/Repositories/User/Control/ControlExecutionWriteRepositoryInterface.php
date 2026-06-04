<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Models\ControlExecution;

/**
 * Writes of control executions (Chantier B / B2).
 */
interface ControlExecutionWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ControlExecution;

    public function softDelete(ControlExecution $execution): void;
}
