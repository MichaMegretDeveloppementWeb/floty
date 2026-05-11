<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * État complet du cycle de vie d'une déclaration fiscale pour un
 * couple `(company, year)` (Phase 11 D5.8, audit pré-livraison
 * Proposition IV).
 *
 * Composé par
 * {@see App\Services\Fiscal\Declaration\DeclarationLifecycleResolver}
 * à partir de la chaîne `superseded_by_id` + statut + flag
 * `is_obsolete` de la déclaration courante.
 *
 * Consommé par le composant Vue adaptatif
 * {@see resources/js/Components/Domain/Declaration/DeclarationStateCard.vue}
 * qui rend une carte différente selon le `state` (S1 vierge ... S7
 * régénération en cours), permettant à l'utilisateur de comprendre
 * immédiatement où il en est dans le workflow déclaratif.
 *
 * **Source unique de vérité** : remplace le legacy
 * `fiscalActiveDeclaration` (qui filtrait `is_obsolete = false` et
 * masquait les déclarations obsolètes orphelines).
 */
#[TypeScript]
final class DeclarationLifecycleStateData extends Data
{
    /**
     * @param  list<InvalidationReasonData>  $obsoleteReasons  Vide si state n'est pas obsolète
     * @param  list<DeclarationListItemData>  $historyChain  Versions antérieures de la chaîne, plus récent → ancien (exclut `currentDeclaration`)
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
