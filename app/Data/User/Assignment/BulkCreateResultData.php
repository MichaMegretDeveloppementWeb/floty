<?php

declare(strict_types=1);

namespace App\Data\User\Assignment;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Réponse de `POST /app/planning/assignments` (création en masse) —
 * indique le nombre de lignes effectivement insérées (les doublons
 * silencieux étant tolérés via `INSERT IGNORE`).
 */
#[TypeScript]
final class BulkCreateResultData extends Data
{
    public function __construct(
        public int $requested,
        public int $inserted,
        public int $skipped,
    ) {}
}
