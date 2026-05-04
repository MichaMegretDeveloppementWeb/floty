<?php

declare(strict_types=1);

namespace App\DTO\Vehicle;

use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;

/**
 * Effet de bord que l'édition d'une VFC produit sur l'une de ses
 * voisines dans l'historique du véhicule.
 *
 * Posé par {@see App\Services\Vehicle\FiscalCharacteristicsImpactComputer}
 * et appliqué (DELETE / UPDATE de borne) par
 * {@see App\Actions\Vehicle\UpdateFiscalCharacteristicsAction}.
 *
 * Les bornes sont conservées en `Y-m-d` pour faciliter les comparaisons
 * et la sérialisation dans les messages utilisateur (toast info,
 * confirmation modale). Aucune logique métier ici - pure structure de
 * données.
 */
final readonly class FiscalCharacteristicsImpact
{
    /**
     * @param  string  $targetEffectiveFrom  Borne actuelle (avant impact, format Y-m-d)
     * @param  ?string  $targetEffectiveTo  Borne actuelle (avant impact)
     */
    public function __construct(
        public FiscalCharacteristicsImpactType $type,
        public int $targetId,
        public string $targetEffectiveFrom,
        public ?string $targetEffectiveTo,
        public ?CarbonImmutable $newEffectiveFrom = null,
        public ?CarbonImmutable $newEffectiveTo = null,
    ) {}

    public static function delete(VehicleFiscalCharacteristics $target): self
    {
        return new self(
            type: FiscalCharacteristicsImpactType::Delete,
            targetId: $target->id,
            targetEffectiveFrom: $target->effective_from->toDateString(),
            targetEffectiveTo: $target->effective_to?->toDateString(),
        );
    }

    public static function adjustEffectiveTo(
        VehicleFiscalCharacteristics $target,
        CarbonImmutable $newEffectiveTo,
    ): self {
        return new self(
            type: FiscalCharacteristicsImpactType::AdjustEffectiveTo,
            targetId: $target->id,
            targetEffectiveFrom: $target->effective_from->toDateString(),
            targetEffectiveTo: $target->effective_to?->toDateString(),
            newEffectiveTo: $newEffectiveTo,
        );
    }

    public static function adjustEffectiveFrom(
        VehicleFiscalCharacteristics $target,
        CarbonImmutable $newEffectiveFrom,
    ): self {
        return new self(
            type: FiscalCharacteristicsImpactType::AdjustEffectiveFrom,
            targetId: $target->id,
            targetEffectiveFrom: $target->effective_from->toDateString(),
            targetEffectiveTo: $target->effective_to?->toDateString(),
            newEffectiveFrom: $newEffectiveFrom,
        );
    }

    public function isDestructive(): bool
    {
        return $this->type === FiscalCharacteristicsImpactType::Delete;
    }

    /**
     * Détermine l'ordre d'application relatif au `UPDATE` de la VFC
     * éditée pour ne jamais violer le trigger DB qui interdit deux
     * périodes chevauchantes pour un même véhicule (« protection
     * triple »).
     *
     * Règle :
     *   - une cascade qui **rétrécit** une voisine (libère de l'espace)
     *     ou la **supprime** doit s'exécuter AVANT l'`UPDATE` afin
     *     d'éviter un état intermédiaire chevauchant.
     *   - une cascade qui **prolonge** une voisine (comble un trou)
     *     doit s'exécuter APRÈS l'`UPDATE` pour la même raison -
     *     prolonger avant aurait empiété sur les bornes existantes.
     */
    public function mustApplyBeforeUpdate(): bool
    {
        return match ($this->type) {
            FiscalCharacteristicsImpactType::Delete => true,

            FiscalCharacteristicsImpactType::AdjustEffectiveTo => $this->isShrinkingEffectiveTo(),

            FiscalCharacteristicsImpactType::AdjustEffectiveFrom => $this->isShrinkingEffectiveFrom(),
        };
    }

    private function isShrinkingEffectiveTo(): bool
    {
        if ($this->newEffectiveTo === null) {
            return false;
        }

        // Borne courante ouverte (effective_to = null) → toute valeur
        // concrète est un raccourcissement.
        if ($this->targetEffectiveTo === null) {
            return true;
        }

        return $this->newEffectiveTo->lessThan(
            CarbonImmutable::parse($this->targetEffectiveTo),
        );
    }

    private function isShrinkingEffectiveFrom(): bool
    {
        if ($this->newEffectiveFrom === null) {
            return false;
        }

        return $this->newEffectiveFrom->greaterThan(
            CarbonImmutable::parse($this->targetEffectiveFrom),
        );
    }

    /**
     * Phrase française décrivant l'impact, prête à être empilée dans
     * un message utilisateur (toast info ou confirmation).
     */
    public function describe(): string
    {
        $period = $this->formatPeriod($this->targetEffectiveFrom, $this->targetEffectiveTo);

        return match ($this->type) {
            FiscalCharacteristicsImpactType::Delete => "Suppression de la version {$period}",
            FiscalCharacteristicsImpactType::AdjustEffectiveTo => sprintf(
                'Date de fin de la version %s ramenée au %s',
                $period,
                $this->newEffectiveTo?->format('d/m/Y') ?? 'sans fin',
            ),
            FiscalCharacteristicsImpactType::AdjustEffectiveFrom => sprintf(
                'Date de début de la version %s ramenée au %s',
                $period,
                $this->newEffectiveFrom?->format('d/m/Y') ?? 'sans début',
            ),
        };
    }

    private function formatPeriod(string $from, ?string $to): string
    {
        $fromFr = CarbonImmutable::parse($from)->format('d/m/Y');

        if ($to === null) {
            return "depuis le {$fromFr}";
        }

        $toFr = CarbonImmutable::parse($to)->format('d/m/Y');

        return "du {$fromFr} au {$toFr}";
    }
}
