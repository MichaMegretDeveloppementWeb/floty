<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\Fiscal\ValueObjects\AppliedExemption;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO de présentation d'une exonération appliquée — couple `(reason,
 * ruleCode)` exposé dans les panneaux "Exonérations applicables" du
 * frontend (Show véhicule + Show contrat).
 *
 * Permet à l'UI d'afficher à la fois la raison textuelle (ex.
 * « Exonération hybride conditionnelle 2024 (CIBS L. 421-125) ») et le
 * code de la règle métier (R-2024-XXX) qui ouvre la fiche détaillée
 * de la règle dans la modale RuleCard.
 */
#[TypeScript]
final class AppliedExemptionData extends Data
{
    public function __construct(
        public string $reason,
        public string $ruleCode,
    ) {}

    public static function fromValueObject(AppliedExemption $exemption): self
    {
        return new self(
            reason: $exemption->reason,
            ruleCode: $exemption->ruleCode,
        );
    }
}
