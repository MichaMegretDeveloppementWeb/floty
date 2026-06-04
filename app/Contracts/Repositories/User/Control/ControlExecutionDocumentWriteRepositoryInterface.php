<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Models\ControlExecutionDocument;

/**
 * Writes of control execution documents (Chantier B / B2).
 */
interface ControlExecutionDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ControlExecutionDocument;

    public function delete(ControlExecutionDocument $document): void;
}
