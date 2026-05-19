<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Contract;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Reacts to every {@see Contract} mutation by:
 *   1. invalidating the cached list of selectable fiscal years,
 *   2. flagging the impacted invoices as divergent,
 *   3. flagging the impacted fiscal declarations as obsolete.
 *
 * Implemented as an Observer (rather than an event listener or a call
 * from inside each Action) so factories, seeders, console code and tests
 * benefit from the same guarantees without having to know about these
 * invalidations themselves.
 *
 * Wired through `#[ObservedBy([ContractObserver::class])]` on the model
 * (no manual binding in `AppServiceProvider::boot()`).
 *
 * Only field changes that affect the billable scope trigger a flag;
 * purely annotative changes (`notes`, `contract_reference`) are skipped
 * to avoid false positives.
 */
final class ContractObserver
{
    private const array IMPACTING_FIELDS = [
        'start_date',
        'end_date',
        'company_id',
        'vehicle_id',
    ];

    public function __construct(
        private readonly AvailableYearsResolver $resolver,
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationDetector,
    ) {}

    /**
     * Invalidate caches and propagate the creation to invoices and
     * declarations.
     */
    public function created(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractCreated,
            $this->actorUserId(),
        );
    }

    /**
     * Invalidate caches and propagate the update only when an impacting
     * field changed; flag both the previous and the new periods (and both
     * companies when the contract was moved across companies).
     */
    public function updated(Contract $contract): void
    {
        $this->resolver->forgetCache();

        if (! $contract->wasChanged(self::IMPACTING_FIELDS)) {
            return;
        }

        $oldCompanyId = (int) ($contract->getOriginal('company_id') ?? $contract->company_id);
        $newCompanyId = (int) $contract->company_id;
        $oldStart = $this->dateToString($contract->getOriginal('start_date'));
        $oldEnd = $this->dateToString($contract->getOriginal('end_date'));
        $newStart = $contract->start_date->toDateString();
        $newEnd = $contract->end_date->toDateString();

        if ($oldCompanyId === $newCompanyId) {
            $this->flagger->flagForContractRange(
                $newCompanyId,
                $newStart,
                $newEnd,
                $oldStart,
                $oldEnd,
            );
        } else {
            $this->flagger->flagForContractRange($oldCompanyId, $oldStart, $oldEnd);
            $this->flagger->flagForContractRange($newCompanyId, $newStart, $newEnd);
        }

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractUpdated,
            $this->actorUserId(),
            previousStartDate: $oldStart,
            previousEndDate: $oldEnd,
            previousCompanyId: $oldCompanyId !== $newCompanyId ? $oldCompanyId : null,
            fieldsChanged: array_values(array_intersect(
                self::IMPACTING_FIELDS,
                array_keys($contract->getChanges()),
            )),
        );
    }

    /**
     * Invalidate caches and propagate the soft deletion.
     */
    public function deleted(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractDeleted,
            $this->actorUserId(),
        );
    }

    /**
     * Invalidate caches and propagate the restoration; treated as a fresh
     * creation since the contract reappears in the billable scope.
     */
    public function restored(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractCreated,
            $this->actorUserId(),
        );
    }

    /**
     * Invalidate caches and propagate the hard deletion.
     */
    public function forceDeleted(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractDeleted,
            $this->actorUserId(),
        );
    }

    /**
     * Resolve the acting user id for traceability; falls back to 0 in
     * contexts without auth (CLI, seeders, tests without `actingAs()`).
     */
    private function actorUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    /**
     * Normalise a date value coming from `getOriginal()` to a `Y-m-d`
     * string regardless of whether the cast already ran.
     */
    private function dateToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
