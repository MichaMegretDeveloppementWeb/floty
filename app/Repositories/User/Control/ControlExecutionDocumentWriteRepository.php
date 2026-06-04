<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlExecutionDocumentWriteRepositoryInterface;
use App\Models\ControlExecutionDocument;

final class ControlExecutionDocumentWriteRepository implements ControlExecutionDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ControlExecutionDocument
    {
        return ControlExecutionDocument::query()->create($attributes);
    }

    public function delete(ControlExecutionDocument $document): void
    {
        $document->delete();
    }
}
