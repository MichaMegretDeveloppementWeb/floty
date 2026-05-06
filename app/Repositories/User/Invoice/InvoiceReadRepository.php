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
        return Invoice::query()
            ->with(['lines', 'company:id,short_code,legal_name,color', 'generatedBy:id,first_name,last_name'])
            ->find($id);
    }

    public function findForCompanyYearMonth(int $companyId, int $year, int $month): ?Invoice
    {
        return Invoice::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function findExistingByMonthForCompanyYear(int $companyId, int $year): array
    {
        $rows = Invoice::query()
            ->select('id', 'month', 'invoice_number')
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->month] = [
                'id' => (int) $row->id,
                'invoiceNumber' => (string) $row->invoice_number,
            ];
        }

        return $map;
    }

    public function maxSequenceForYearMonth(int $year, int $month): int
    {
        // `invoice_number` format `YYYY-MM-NNNN` ; la séquence est les
        // 4 derniers caractères. SQLite (tests) ne supporte pas le cast
        // SUBSTRING en int dans MAX() de manière portable ; on extrait
        // côté PHP — coût O(n) acceptable (n = factures du mois, < 100).
        $rows = Invoice::query()
            ->where('year', $year)
            ->where('month', $month)
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
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Invoice::query()
            ->select('invoices.*')
            ->with('company:id,short_code,legal_name,color');

        // Filtres exact match.
        if ($query->companyId !== null) {
            $eloquentQuery->where('invoices.company_id', $query->companyId);
        }
        if ($query->year !== null) {
            $eloquentQuery->where('invoices.year', $query->year);
        }
        if ($query->month !== null) {
            $eloquentQuery->where('invoices.month', $query->month);
        }

        // Search LIKE sur invoice_number + company short_code + legal_name.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function (Builder $w) use ($term): void {
                $w->where('invoices.invoice_number', 'like', $term)
                    ->orWhereHas('company', fn (Builder $qc) => $qc
                        ->where('short_code', 'like', $term)
                        ->orWhere('legal_name', 'like', $term));
            });
        }

        // Tri whitelist.
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
            // Défaut : plus récente en premier (year+month DESC).
            default => $eloquentQuery
                ->orderByDesc('invoices.year')
                ->orderByDesc('invoices.month')
                ->orderByDesc('invoices.id'),
        };

        return $eloquentQuery->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function existsAny(): bool
    {
        return Invoice::query()->exists();
    }
}
