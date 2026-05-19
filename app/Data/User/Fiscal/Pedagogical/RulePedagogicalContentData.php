<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal\Pedagogical;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Fiscal\ValueObjects\FlatBracketsTable;
use App\Fiscal\ValueObjects\ProgressiveBracketsTable;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Frontend projection of a fiscal rule's pedagogical content (ADR-0022).
 * Mirror of the domain VO {@see RulePedagogicalContent}.
 */
#[TypeScript]
final class RulePedagogicalContentData extends Data
{
    public function __construct(
        public RuleTab $tab,
        public RuleSection $section,
        public string $title,
        public string $pitch,
        public ?string $body = null,
        public ?string $appliesWhen = null,
        public ?string $effect = null,
        public ?ProgressiveBracketsTableData $progressiveBrackets = null,
        public ?FlatBracketsTableData $flatBrackets = null,
        public ?string $example = null,
    ) {}

    /**
     * Domain VO -> presentation DTO mapping. Explicit because Spatie Data
     * cannot auto-convert when nested structures use different classes.
     */
    public static function fromVo(RulePedagogicalContent $vo): self
    {
        return new self(
            tab: $vo->tab,
            section: $vo->section,
            title: $vo->title,
            pitch: $vo->pitch,
            body: $vo->body,
            appliesWhen: $vo->appliesWhen,
            effect: $vo->effect,
            progressiveBrackets: $vo->progressiveBrackets !== null
                ? self::progressiveFromVo($vo->progressiveBrackets)
                : null,
            flatBrackets: $vo->flatBrackets !== null
                ? self::flatFromVo($vo->flatBrackets)
                : null,
            example: $vo->example,
        );
    }

    private static function progressiveFromVo(ProgressiveBracketsTable $vo): ProgressiveBracketsTableData
    {
        return new ProgressiveBracketsTableData(
            unit: $vo->unit,
            header: $vo->header,
            rows: array_map(
                static fn ($row): ProgressiveBracketRowData => new ProgressiveBracketRowData(
                    label: $row->label,
                    rate: $row->rate,
                ),
                $vo->rows,
            ),
        );
    }

    private static function flatFromVo(FlatBracketsTable $vo): FlatBracketsTableData
    {
        return new FlatBracketsTableData(
            header: $vo->header,
            rows: array_map(
                static fn ($row): FlatBracketRowData => new FlatBracketRowData(
                    category: $row->category,
                    amount: $row->amount,
                    note: $row->note,
                ),
                $vo->rows,
            ),
        );
    }
}
