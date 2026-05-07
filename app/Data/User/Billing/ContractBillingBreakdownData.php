<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Récap facturation **contrat-isolé** · une ligne par mois civil que
 * le contrat couvre (Phase 14.D V1.2).
 *
 * **Convention de calcul (caveat important)** : ce récap calcule le
 * coût en isolation du contrat. Si le véhicule a plusieurs contrats
 * sur le même mois pour la même entreprise, l'addition individuelle
 * des contrats peut différer de la facture mensuelle réelle (qui
 * consolide les jours pour un combo optimal multi-contrats).
 *
 * Concrètement :
 *   - 1 contrat 10 jours en mars (90/500/1800) = 1 sem + 3 jours = 77 000
 *   - 2 contrats de 10 jours sur le même mois pour la même entreprise =
 *     20 jours consolidés = 3 sem = 150 000
 *   - L'addition isolée donnerait 154 000 ; la facture réelle 150 000.
 *
 * En V1.2 on assume cette approximation : c'est utile et lisible pour
 * la fiche contrat, et la facture réelle reste la source de vérité.
 */
#[TypeScript]
final class ContractBillingBreakdownData extends Data
{
    /**
     * @param  list<ContractBillingMonthData>  $months  Triés par (year, month) croissant.
     */
    public function __construct(
        #[DataCollectionOf(ContractBillingMonthData::class)]
        public array $months,
        public int $totalDaysUsed,
        public ?int $totalCents,
        public bool $hasAnyMissingPricing,
    ) {}
}
