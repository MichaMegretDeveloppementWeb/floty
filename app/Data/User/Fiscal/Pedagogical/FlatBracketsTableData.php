<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal\Pedagogical;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class FlatBracketsTableData extends Data
{
    /**
     * @param  array{0: string, 1: string}  $header
     * @param  list<FlatBracketRowData>  $rows
     */
    public function __construct(
        public array $header,
        #[DataCollectionOf(FlatBracketRowData::class)]
        public array $rows,
    ) {}
}
