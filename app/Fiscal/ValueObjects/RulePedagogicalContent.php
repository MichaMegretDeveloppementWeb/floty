<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;

/**
 * Contenu pédagogique d'une règle fiscale, affiché sur la page
 * « Règles de calcul » (Phase 13 D5.12 · ADR-0022 finalisée).
 *
 * **Doctrine** · le contenu pédagogique d'une règle vit DANS sa
 * classe PHP, accessible via `FiscalRule::pedagogicalContent()`. Le
 * fichier TS `resources/js/data/fiscalRulesContent.ts` a été supprimé
 * en faveur de cette projection PHP unique, conformément à la
 * doctrine « 1 règle = 1 classe PHP, source de vérité unique ».
 *
 * Tous les champs textuels sont en français, écrits pour un
 * utilisateur métier (gestionnaire de flotte), pas pour un juriste.
 *
 * Les champs `progressiveBrackets` / `flatBrackets` portent les
 * barèmes formatés pour affichage (libellés et taux sous forme de
 * strings prêtes à rendre · ex. `'0 à 14 g/km'`, `'1 €'`). La
 * formalisation purement numérique des barèmes (utilisée par le
 * moteur de calcul) vit dans la logique `apply()` de la classe Rule
 * elle-même · les deux représentations doivent rester cohérentes.
 */
final readonly class RulePedagogicalContent
{
    public function __construct(
        public RuleTab $tab,
        public RuleSection $section,
        /** Titre court affiché en tête de carte. */
        public string $title,
        /** Phrase d'une ligne résumant l'essentiel. */
        public string $pitch,
        /** Paragraphe plus long explicitant le calcul ou la condition. */
        public ?string $body = null,
        /** Condition d'application (pour exonérations et aiguillage). */
        public ?string $appliesWhen = null,
        /** Effet sur le calcul (pour exonérations). */
        public ?string $effect = null,
        public ?ProgressiveBracketsTable $progressiveBrackets = null,
        public ?FlatBracketsTable $flatBrackets = null,
        /** Exemple chiffré concret. */
        public ?string $example = null,
    ) {}
}
