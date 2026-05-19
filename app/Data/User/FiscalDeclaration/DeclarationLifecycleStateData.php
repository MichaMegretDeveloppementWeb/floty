<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use App\Services\Fiscal\Declaration\DeclarationLifecycleResolver;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full lifecycle state for a declaration of a `(company, year)` couple.
 *
 * Composed by {@see DeclarationLifecycleResolver}
 * from the `superseded_by_id` chain, status and `is_obsolete` flag.
 * Consumed by the adaptive `DeclarationStateCard.vue` which renders a
 * different card per `state` (S1 untouched ... S7 regeneration ongoing).
 *
 * Replaces the legacy `fiscalActiveDeclaration` (which filtered out
 * obsolete-orphan declarations and hid them from the UI).
 */
#[TypeScript]
final class DeclarationLifecycleStateData extends Data
{
    /**
     * @param  list<InvalidationReasonData>  $obsoleteReasons  Empty when state is not obsolete.
     * @param  list<DeclarationListItemData>  $historyChain  Earlier versions, newest to oldest, excluding `currentDeclaration`.
     */
    public function __construct(
        public DeclarationLifecycleState $state,
        public ?DeclarationListItemData $currentDeclaration,
        public ?DeclarationListItemData $predecessorDeclaration,
        public int $pendingClustersCount,
        public bool $canGenerate,
        #[DataCollectionOf(InvalidationReasonData::class)]
        public array $obsoleteReasons,
        #[DataCollectionOf(DeclarationListItemData::class)]
        public array $historyChain,
    ) {}
}
