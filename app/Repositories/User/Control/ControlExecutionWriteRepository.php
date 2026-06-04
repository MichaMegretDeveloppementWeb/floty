<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlExecutionWriteRepositoryInterface;
use App\Models\ControlExecution;

final class ControlExecutionWriteRepository implements ControlExecutionWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ControlExecution
    {
        return ControlExecution::query()->create($attributes);
    }

    public function softDelete(ControlExecution $execution): void
    {
        $execution->delete();
    }
}
