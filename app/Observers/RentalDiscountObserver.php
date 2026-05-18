<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RentalDiscount;
use App\Services\Invoice\InvoiceDivergenceFlagger;

/**
 * Marque divergentes les factures impactées à chaque mutation d'une
 * {@see RentalDiscount} (Lot 3 du chantier RentalDiscount · propagation
 * UI billing + observer).
 *
 * **Pourquoi un Observer plutôt qu'un Listener applicatif** ·
 *   - **Couverture totale** · capte aussi les mutations passant par
 *     factories, seeders, console (`tinker`), tests · les chemins
 *     ne dépendent pas de l'invalidation pour rester corrects.
 *   - **Single source of truth** · 1 seul fichier porte la
 *     responsabilité « toute mutation de RentalDiscount = invalidations
 *     associées ». Aligné avec {@see ContractObserver}.
 *
 * **Branchement** · attribut `#[ObservedBy([RentalDiscountObserver::class])]`
 * sur le modèle, pas de bind manuel dans `AppServiceProvider::boot()`.
 *
 * **Hooks couverts** · created / updated / deleted / restored /
 * forceDeleted. Couvre l'intégralité du cycle de vie possible.
 *
 * **Périmètre flag** · seules les mutations modifiant la sémantique
 * commerciale (`start_date`, `end_date`, `discount_basis_points`,
 * `company_id`) déclenchent un flag. Les changements purement annotatifs
 * (`label`, `notes`) sont skippés pour éviter les faux positifs.
 *
 * **Doctrine immuabilité (ADR-0008)** · `is_divergent` est une métadonnée
 * d'observabilité, pas un champ du snapshot. Les colonnes figées sur
 * les invoices/invoice_lines (`total_ht_cents`, `gross_total_cents`,
 * `discount_cents`, `applied_discount_label_snapshot`, ...) ne sont
 * jamais mutées par ce service · seule la flag `is_divergent` bascule.
 *
 * **Périmètre véhicules** · ne déclenche PAS de flag spécifique au
 * changement de pivot véhicules (ajout/retrait via `syncVehicles`).
 * Ces mutations passent par les hooks `attached`/`detached` sur la
 * relation BelongsToMany qui ne déclenchent pas les events Eloquent
 * standards sur le modèle parent · suivront en Lot 4 quand le module
 * CRUD le justifiera (l'Action de mise à jour appellera explicitement
 * le flagger pour les pivots changés).
 */
final class RentalDiscountObserver
{
    private const array IMPACTING_FIELDS = [
        'start_date',
        'end_date',
        'discount_basis_points',
        'company_id',
    ];

    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
    ) {}

    public function created(RentalDiscount $discount): void
    {
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    public function updated(RentalDiscount $discount): void
    {
        if (! $discount->wasChanged(self::IMPACTING_FIELDS)) {
            return;
        }

        $oldCompanyId = (int) ($discount->getOriginal('company_id') ?? $discount->company_id);
        $newCompanyId = (int) $discount->company_id;
        $oldStart = $this->dateToString($discount->getOriginal('start_date'));
        $oldEnd = $this->dateToString($discount->getOriginal('end_date'));
        $newStart = $discount->start_date->toDateString();
        $newEnd = $discount->end_date->toDateString();

        if ($oldCompanyId === $newCompanyId) {
            // Cas dominant · même entreprise, dates ou taux modifiés ·
            // flag couvre ancien ET nouveau range pour ne rater aucun
            // mois (un décalage de période peut sortir un mois de
            // l'application de la réduction).
            $this->flagger->flagForDiscountPeriod(
                $newCompanyId,
                $newStart,
                $newEnd,
                $oldStart,
                $oldEnd,
            );
        } else {
            // Changement de company (rare via UI, possible via seeders) ·
            // flag les deux périmètres séparément.
            $this->flagger->flagForDiscountPeriod($oldCompanyId, $oldStart, $oldEnd);
            $this->flagger->flagForDiscountPeriod($newCompanyId, $newStart, $newEnd);
        }
    }

    public function deleted(RentalDiscount $discount): void
    {
        // Soft-delete · les factures émises avant la suppression ne
        // reflètent plus la réalité (la réduction n'existe plus pour
        // les périodes à venir, ou a été annulée rétroactivement).
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    public function restored(RentalDiscount $discount): void
    {
        // Restoration · symétrie avec deleted, les factures émises
        // pendant l'absence ne reflètent pas la réduction restaurée.
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    public function forceDeleted(RentalDiscount $discount): void
    {
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    /**
     * `getOriginal` peut retourner un Carbon (cast) ou une string (avant
     * cast). Normalise en `Y-m-d`.
     */
    private function dateToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
