<?php

declare(strict_types=1);

namespace App\Repositories\User\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class InvoiceWriteRepository implements InvoiceWriteRepositoryInterface
{
    public function persist(array $attributes): Invoice
    {
        $invoice = new Invoice;
        $invoice->fill($attributes);
        $invoice->save();

        return $invoice;
    }

    public function persistLines(int $invoiceId, array $linesAttributes): array
    {
        $created = [];
        foreach ($linesAttributes as $attrs) {
            $line = new InvoiceLine;
            $line->fill([...$attrs, 'invoice_id' => $invoiceId]);
            $line->save();
            $created[] = $line;
        }

        return $created;
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    public function forceDelete(Invoice $invoice): void
    {
        $invoice->forceDelete();
    }

    public function flagDivergentForCompanyAndTuples(int $companyId, array $tuples): int
    {
        if ($tuples === []) {
            return 0;
        }

        return Invoice::query()
            ->where('company_id', $companyId)
            ->where('is_divergent', false)
            ->where(function ($q) use ($tuples): void {
                foreach ($tuples as $tuple) {
                    $q->orWhere(function ($sub) use ($tuple): void {
                        $sub->where('year', $tuple['year'])
                            ->where('month', $tuple['month']);
                    });
                }
            })
            ->update(['is_divergent' => true]);
    }

    public function flagDivergentForVehiclePricingYear(int $vehicleId, int $year): int
    {
        return Invoice::query()
            ->where('year', $year)
            ->where('is_divergent', false)
            ->whereIn('company_id', function (QueryBuilder $sub) use ($vehicleId, $year): void {
                $sub->select('company_id')->distinct()
                    ->from('contracts')
                    ->where('vehicle_id', $vehicleId)
                    ->where('start_date', '<=', "{$year}-12-31")
                    ->where('end_date', '>=', "{$year}-01-01")
                    ->whereNull('deleted_at');
            })
            ->update(['is_divergent' => true]);
    }
}
