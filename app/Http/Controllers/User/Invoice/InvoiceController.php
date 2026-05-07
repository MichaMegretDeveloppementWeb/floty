<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Invoice;

use App\Actions\Invoice\CancelInvoiceAction;
use App\Actions\Invoice\GenerateInvoiceAction;
use App\Actions\Invoice\RegenerateInvoiceAction;
use App\Contracts\Repositories\User\Billing\BillingSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Data\User\Billing\BillingSettingsData;
use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Data\User\Invoice\RegenerateInvoiceRequestData;
use App\Enums\Invoice\RegenerateRedirectTarget;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Invoice\InvoiceAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Company\CompanyQueryService;
use App\Services\Invoice\InvoicePdfStorage;
use App\Services\Invoice\InvoiceQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contrôleur des factures mensuelles (Phase 14.E V1.2). Slim conforme
 * ADR-0013 — pas de logique métier, délégation à `GenerateInvoiceAction`
 * pour la génération et `InvoicePdfStorage` pour le téléchargement.
 *
 * Les pages Index + Show (Inertia) sont livrées en chantier 14.F. Pour
 * V1.2 on expose minimum :
 *   - POST `generate` (déclenche la création + persistance + PDF)
 *   - GET `download` (sert le PDF binaire en attachement)
 *
 * **Émetteur** : passé en placeholder constant pour V1.2 — sera lu
 * depuis la table `billing_settings` en chantier 14.G.
 */
final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceQueryService $invoiceQuery,
        private readonly InvoiceReadRepositoryInterface $invoiceRead,
        private readonly BillingSettingsReadRepositoryInterface $billingSettings,
        private readonly CompanyQueryService $companyQuery,
    ) {}

    public function index(InvoiceIndexQueryData $query): InertiaResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        return Inertia::render('User/Invoices/Index/Index', [
            'invoices' => $this->invoiceQuery->listPaginated($query),
            'query' => $query,
            'options' => [
                'companies' => $this->companyQuery->listForOptions(),
                // Bornes des années couvertes par les factures émises.
                // Le frontend les utilise pour générer la liste complète
                // [min..max(currentYear, max)]. Null si aucune facture.
                'yearBounds' => $this->invoiceRead->findYearBounds(),
            ],
            // Cf. doctrine `hasAny` (ADR-0020 D9) : distingue table vide
            // de filtre actif retournant 0.
            'hasAnyInvoice' => $this->invoiceRead->existsAny(),
        ]);
    }

    public function show(int $invoice): InertiaResponse
    {
        $data = $this->invoiceQuery->findInvoiceData($invoice);

        if ($data === null) {
            throw new NotFoundHttpException('Facture introuvable.');
        }

        // Le DTO ne porte pas le Model (data flow read-only) — on
        // recharge l'instance pour matcher la Policy `view`.
        Gate::authorize('view', Invoice::query()->findOrFail($invoice));

        return Inertia::render('User/Invoices/Show/Index', [
            'invoice' => $data,
        ]);
    }

    public function generate(
        Request $request,
        GenerateInvoiceAction $action,
    ): RedirectResponse {
        Gate::authorize('create', Invoice::class);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'year' => ['required', 'integer', 'between:2020,2099'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $invoice = $action->execute(
                companyId: (int) $validated['company_id'],
                year: (int) $validated['year'],
                month: (int) $validated['month'],
                generatedByUserId: $user->id,
                issuer: BillingSettingsData::fromModel($this->billingSettings->get())->toIssuerPayload(),
            );
        } catch (InvoiceAlreadyExistsException $e) {
            // Cas concurrence : un autre clic / un autre onglet a déjà
            // émis la facture. Rebascule sur un toast-error explicite
            // plutôt que sur un 500 — l'utilisateur peut ouvrir la
            // facture existante depuis la même page.
            return back()->with(
                'toast-error',
                "Une facture est déjà émise pour cette entreprise sur {$validated['year']}-".
                str_pad((string) $validated['month'], 2, '0', STR_PAD_LEFT).'.',
            );
        } catch (MissingPricingException $e) {
            // Tarif manquant : on affiche un toast clair invitant à
            // renseigner les tarifs sur la fiche véhicule.
            return back()->with(
                'toast-error',
                'Tarif annuel manquant pour au moins un véhicule du mois. '.
                'Renseignez les tarifs depuis la fiche véhicule avant de générer la facture.',
            );
        }

        return back()->with('toast-success', "Facture {$invoice->invoice_number} générée.");
    }

    /**
     * Annule une facture émise (Phase 14.I V1.2). Supprime la facture +
     * son PDF + ses lignes (cascade DB). Permet de regénérer après une
     * modification du périmètre (ajout/modif/suppression d'un contrat
     * sur un mois déjà facturé).
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
     * Régénère une facture émise (Phase 14.I+ V1.2). Annule l'ancienne
     * facture + son PDF puis génère une nouvelle facture pour le même
     * couple (entreprise × année × mois) avec les données actuelles,
     * le tout dans une transaction.
     *
     * La cible de redirection est passée explicitement par le client via
     * {@see RegenerateInvoiceRequestData::$redirectTarget} ; le defaut
     * (`Show`) correspond au cas usuel d'un appel depuis la fiche détail.
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
