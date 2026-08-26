<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Invoice;

use App\Actions\Invoice\BulkGenerateInvoicesAction;
use App\Actions\Invoice\CancelInvoiceAction;
use App\Actions\Invoice\GenerateInvoiceAction;
use App\Actions\Invoice\RegenerateInvoiceAction;
use App\Contracts\Repositories\User\Billing\BillingSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Data\User\Billing\BillingSettingsData;
use App\Data\User\Invoice\BulkGenerateInvoicesRequestData;
use App\Data\User\Invoice\GenerateInvoiceRequestData;
use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Data\User\Invoice\RegenerateInvoiceRequestData;
use App\Enums\Invoice\RegenerateRedirectTarget;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Invoice\InvoiceAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Company\CompanyListingService;
use App\Services\Company\CompanyYearPickerService;
use App\Services\Invoice\InvoicePdfStorage;
use App\Services\Invoice\InvoiceQueryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Monthly invoice HTTP endpoints (slim, per ADR-0013).
 */
final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceQueryService $invoiceQuery,
        private readonly InvoiceReadRepositoryInterface $invoiceRead,
        private readonly BillingSettingsReadRepositoryInterface $billingSettings,
        private readonly CompanyListingService $companyQuery,
        private readonly CompanyYearPickerService $companyYearPicker,
    ) {}

    /**
     * List invoices with filters and pagination.
     */
    public function index(InvoiceIndexQueryData $query): InertiaResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        return Inertia::render('User/Invoices/Index/Index', [
            'invoices' => $this->invoiceQuery->listPaginated($query),
            'query' => $query,
            'options' => [
                'companies' => $this->companyQuery->listForOptions(),
                // Year bounds covered by emitted invoices; the front-end
                // expands them to a contiguous list up to the current year.
                'yearBounds' => $this->invoiceRead->findYearBounds(),
            ],
            'hasAnyInvoice' => $this->invoiceRead->existsAny(),
            // Company + exercise shortcut modal. Optional so the index
            // never pays for it: pulled by a partial reload on the first
            // opening.
            'companyYearPicker' => Inertia::optional(fn () => $this->companyYearPicker->build()),
        ]);
    }

    /**
     * Render the invoice detail page with deferred divergence check.
     */
    public function show(Invoice $invoice): InertiaResponse
    {
        Gate::authorize('view', $invoice);

        $data = $this->invoiceQuery->findInvoiceData($invoice->id);

        // Route model binding already guarantees the invoice exists; the
        // null guard keeps PHPStan happy and stays defensive if soft-delete
        // is later introduced.
        if ($data === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('User/Invoices/Show/Index', [
            'invoice' => $data,
            'divergence' => Inertia::defer(fn () => $this->invoiceQuery->divergenceForInvoice($invoice->id)),
        ]);
    }

    /**
     * Generate a single invoice for the given (company, year, month).
     *
     * Recoverable domain failures (already-exists, missing pricing, period
     * not yet elapsed) are mapped to toast-error responses instead of 500s.
     */
    public function generate(
        GenerateInvoiceRequestData $data,
        Request $request,
        GenerateInvoiceAction $action,
    ): RedirectResponse {
        Gate::authorize('create', Invoice::class);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $invoice = $action->execute(
                companyId: $data->companyId,
                year: $data->year,
                month: $data->month,
                generatedByUserId: $user->id,
                issuer: BillingSettingsData::fromModel($this->billingSettings->get())->toIssuerPayload(),
            );
        } catch (InvoiceAlreadyExistsException) {
            return back()->with(
                'toast-error',
                "Une facture est déjà émise pour cette entreprise sur {$data->year}-".
                str_pad((string) $data->month, 2, '0', STR_PAD_LEFT).'.',
            );
        } catch (MissingPricingException) {
            return back()->with(
                'toast-error',
                'Tarif annuel manquant pour au moins un véhicule du mois. '.
                'Renseignez les tarifs depuis la fiche véhicule avant de générer la facture.',
            );
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return back()->with('toast-success', "Facture {$invoice->invoice_number} générée.");
    }

    /**
     * Generate every pending invoice for a (company, year) in one shot.
     *
     * The bulk action keeps full control of the execution doctrine (order,
     * best-effort, report). The report is flashed under `bulkInvoiceReport`
     * for inline display on the billing tab.
     */
    public function bulkGenerate(
        BulkGenerateInvoicesRequestData $data,
        Request $request,
        BulkGenerateInvoicesAction $action,
    ): RedirectResponse {
        Gate::authorize('create', Invoice::class);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $report = $action->execute(
            companyId: $data->companyId,
            year: $data->year,
            generatedByUserId: $user->id,
            issuer: BillingSettingsData::fromModel($this->billingSettings->get())->toIssuerPayload(),
        );

        $generatedCount = count($report->generated);
        $failedCount = count($report->failed);

        if ($generatedCount === 0 && $failedCount === 0) {
            return back()->with(
                'toast-info',
                "Aucune annexe à générer pour {$data->year}.",
            );
        }

        $toastKey = $failedCount === 0 ? 'toast-success' : 'toast-warning';
        $message = $failedCount === 0
            ? "{$generatedCount} annexe".($generatedCount > 1 ? 's' : '').' générée'.($generatedCount > 1 ? 's' : '')." pour {$data->year}."
            : "{$generatedCount} générée".($generatedCount > 1 ? 's' : '').", {$failedCount} en échec pour {$data->year}.";

        return back()
            ->with($toastKey, $message)
            ->with('bulkInvoiceReport', $report);
    }

    /**
     * Cancel an emitted invoice (removes PDF + lines via DB cascade).
     */
    public function destroy(Invoice $invoice, CancelInvoiceAction $action): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $number = $invoice->invoice_number;

        $action->execute($invoice);

        return back()->with(
            'toast-success',
            "Facture {$number} annulée. La regénération est désormais possible.",
        );
    }

    /**
     * Cancel and regenerate an invoice for the same (company, year, month).
     *
     * The redirect target is provided explicitly by the client via
     * {@see RegenerateInvoiceRequestData::$redirectTarget}; the default
     * (`Show`) covers the usual call from the invoice detail page.
     */
    public function regenerate(
        RegenerateInvoiceRequestData $data,
        Request $request,
        Invoice $invoice,
        RegenerateInvoiceAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $invoice);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $newInvoice = $action->execute(
                invoice: $invoice,
                generatedByUserId: $user->id,
                issuer: BillingSettingsData::fromModel($this->billingSettings->get())->toIssuerPayload(),
            );
        } catch (MissingPricingException) {
            return back()->with(
                'toast-error',
                'Tarif annuel manquant pour au moins un véhicule du mois. '.
                'Renseignez les tarifs depuis la fiche véhicule avant de regénérer.',
            );
        }

        $message = "Facture régénérée : {$newInvoice->invoice_number}.";

        return match ($data->redirectTarget) {
            RegenerateRedirectTarget::Show => redirect()
                ->route('user.invoices.show', ['invoice' => $newInvoice->id])
                ->with('toast-success', $message),
            RegenerateRedirectTarget::Index => redirect()
                ->route('user.invoices.index')
                ->with('toast-success', $message),
            RegenerateRedirectTarget::CompanyTab => redirect()
                ->to(route('user.companies.show', ['company' => $newInvoice->company_id]).'?tab=billing')
                ->with('toast-success', $message),
        };
    }

    /**
     * Stream the invoice PDF as an attachment.
     */
    public function download(Invoice $invoice, InvoicePdfStorage $storage): Response
    {
        Gate::authorize('view', $invoice);

        $binary = $storage->read($invoice->pdf_path);

        if ($binary === null) {
            abort(Response::HTTP_NOT_FOUND, 'Fichier PDF introuvable sur le serveur.');
        }

        return new Response(
            $binary,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s.pdf"',
                    $invoice->invoice_number,
                ),
            ],
        );
    }
}
