<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal\Pedagogical;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ProgressiveBracketsTableData extends Data
{
    /**
     * @param  array{0: string, 1: string}  $header
     * @param  list<ProgressiveBracketRowData>  $rows
     */
    public function __construct(
        public string $unit,
        public array $header,
        #[DataCollectionOf(ProgressiveBracketRowData::class)]
        public array $rows,
    ) {}
}
