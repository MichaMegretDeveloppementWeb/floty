<?php

declare(strict_types=1);

namespace App\Repositories\User\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class InvoiceReadRepository implements InvoiceReadRepositoryInterface
{
    public function findById(int $id): ?Invoice
    {
        // `withTrashed()`: the Show page is navigable even for
        // obsolete versions (soft-deleted by regeneration) · the UI
        // displays a "Replaced by #XXX" banner. Listings use their
        // own builder via `paginateForIndex` which handles the
        // `includeObsolete` filter separately.
        return Invoice::query()
            ->withTrashed()
            ->with([
                'lines',
                'company:id,short_code,legal_name,color',
                'generatedBy:id,first_name,last_name',
                'supersededBy:id,invoice_number',
            ])
            ->find($id);
    }

    public function findHistoryChainFor(Invoice $invoice): array
    {
        // All versions of the same (company, year, month) couple,
        // including soft-deleted ones. Single query, no N+1.
        return Invoice::query()
            ->withTrashed()
            ->where('company_id', $invoice->company_id)
            ->where('year', $invoice->year)
            ->where('month', $invoice->month)
            ->get()
            ->all();
    }

    public function findPredecessor(int $invoiceId): ?Invoice
    {
        // An invoice is "predecessor" of $invoiceId if it points to
        // $invoiceId via `superseded_by_id`. With multiple chained
        // versions (multi-regeneration case), the most recent direct
        // predecessor is returned · earlier history is reachable via
        // the predecessor's own predecessor.
        return Invoice::query()
            ->withTrashed()
            ->where('superseded_by_id', $invoiceId)
            ->orderByDesc('id')
            ->first();
    }

    public function findForCompanyYearMonth(int $companyId, int $year, int $month): ?Invoice
    {
        // `withoutTrashed()` (default): only active invoices block a
        // new generation · soft-deleted obsolete versions are no
        // longer considered "existing".
        return Invoice::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function findExistingByMonthForCompanyYear(int $companyId, int $year): array
    {
        // `withSum` materialises a SQL sub-query for the total
        // `days_used` of attached `invoice_lines` · single query, no
        // N+1. `total_gross_cents` and `total_discount_cents` are
        // selected so {@see BillingBreakdownService} can expose the
        // gross/discount snapshot of the emitted invoice without a
        // second query.
        $rows = Invoice::query()
            ->select('id', 'month', 'invoice_number', 'total_ht_cents', 'total_gross_cents', 'total_discount_cents')
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->withSum('lines as invoiced_days_used', 'days_used')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->month] = [
                'id' => (int) $row->id,
                'invoiceNumber' => (string) $row->invoice_number,
                'totalHtCents' => (int) $row->total_ht_cents,
                'invoicedDaysUsed' => (int) ($row->invoiced_days_used ?? 0),
                'grossTotalCents' => (int) $row->total_gross_cents,
                'totalDiscountCents' => (int) $row->total_discount_cents,
            ];
        }

        return $map;
    }

    public function maxSequenceForYearMonth(int $year, int $month): int
    {
        // `invoice_number` format `YYYY-MM-NNNN`; the sequence is the
        // last 4 characters. SQLite (tests) does not portably support
        // SUBSTRING cast to int inside MAX(); extracted in PHP · O(n)
        // cost acceptable (n = invoices of the month, < 100).
        //
        // `lockForUpdate()`: pessimistic lock preventing two
        // concurrent generations from computing the same sequence and
        // violating the UNIQUE `invoice_number`. Only effective inside
        // a transaction (caller `GenerateInvoiceAction::execute()`
        // wraps in `DB::transaction`).
        //
        // `withTrashed()`: includes soft-deleted invoices (obsolete
        // versions after regeneration) to NEVER reuse a previously
        // attributed number · per art. 242 nonies A annexe II CGI
        // (continuous numbering, no gap and no reuse).
        $rows = Invoice::query()
            ->withTrashed()
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->pluck('invoice_number');

        $max = 0;
        foreach ($rows as $number) {
            $parts = explode('-', $number);
            $seq = isset($parts[2]) ? (int) $parts[2] : 0;
            if ($seq > $max) {
                $max = $seq;
            }
        }

        return $max;
    }

    public function paginateForIndex(InvoiceIndexQueryData $query): LengthAwarePaginator
    {
        return $this->buildIndexQuery($query)->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function existsAny(): bool
    {
        return Invoice::query()->exists();
    }

    /**
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array
    {
        /** @var object{min_year: int|null, max_year: int|null}|null $row */
        $row = Invoice::query()
            ->selectRaw('MIN(year) as min_year, MAX(year) as max_year')
            ->first();

        if ($row === null || $row->min_year === null) {
            return null;
        }

        return [
            'min' => (int) $row->min_year,
            'max' => (int) $row->max_year,
        ];
    }

    /**
     * Index Invoices builder. Applies all SQL filters (company, year,
     * month, search, divergentOnly) and the whitelisted sort.
     *
     * `divergentOnly` is a plain `WHERE is_divergent = 1` · the flag
     * is set by observers on write, no recompute at read time.
     *
     * @return Builder<Invoice>
     */
    private function buildIndexQuery(InvoiceIndexQueryData $query): Builder
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Invoice::query()
            ->select('invoices.*')
            ->with([
                'company:id,short_code,legal_name,color',
                'supersededBy:id,invoice_number',
            ]);

        // Include obsolete (soft-deleted) versions. Default `false`
        // to keep the list dense · with ~12 invoices/year/company,
        // hiding obsoletes by default is crucial. The "Include
        // obsolete versions" filter checkbox releases the global
        // SoftDeletes scope.
        if ($query->includeObsolete) {
            $eloquentQuery->withTrashed();
        }

        // Exact-match filters.
        if ($query->companyId !== null) {
            $eloquentQuery->where('invoices.company_id', $query->companyId);
        }
        if ($query->year !== null) {
            $eloquentQuery->where('invoices.year', $query->year);
        }
        if ($query->month !== null) {
            $eloquentQuery->where('invoices.month', $query->month);
        }
        if ($query->divergentOnly) {
            $eloquentQuery->where('invoices.is_divergent', true);
        }

        // Search LIKE on invoice_number + company short_code + legal_name.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function (Builder $w) use ($term): void {
                $w->where('invoices.invoice_number', 'like', $term)
                    ->orWhereHas('company', fn (Builder $qc) => $qc
                        ->where('short_code', 'like', $term)
                        ->orWhere('legal_name', 'like', $term));
            });
        }

        // Whitelisted sort.
        match ($query->sortKey) {
            'invoiceNumber' => $eloquentQuery->orderBy('invoices.invoice_number', $direction),
            'company' => $eloquentQuery
                ->leftJoin('companies', 'invoices.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'period' => $eloquentQuery
                ->orderBy('invoices.year', $direction)
                ->orderBy('invoices.month', $direction),
            'totalHt' => $eloquentQuery->orderBy('invoices.total_ht_cents', $direction),
            'generatedAt' => $eloquentQuery->orderBy('invoices.generated_at', $direction),
            // Default: newest first (year+month DESC).
            default => $eloquentQuery
                ->orderByDesc('invoices.year')
                ->orderByDesc('invoices.month')
                ->orderByDesc('invoices.id'),
        };

        return $eloquentQuery;
    }
}
