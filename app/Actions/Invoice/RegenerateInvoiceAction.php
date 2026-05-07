<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Régénère une facture émise (Phase 14.I+ V1.2). Combine en une seule
 * transaction l'annulation de la facture existante (suppression DB +
 * PDF) puis la génération d'une nouvelle facture pour le même couple
 * (entreprise × année × mois) avec les données contractuelles
 * actuelles.
 *
 * **Cas d'usage** : un contrat est ajouté/modifié/supprimé sur un mois
 * déjà facturé. L'UI signale la divergence à l'utilisateur, qui clique
 * « Régénérer » et obtient une nouvelle facture cohérente avec la
 * réalité actuelle. La doctrine immuabilité (ADR-0008) est respectée :
 * la régénération est une suppression-puis-création explicite, pas une
 * mutation in-place.
 *
 * **Composition** : délègue à `CancelInvoiceAction` puis
 * `GenerateInvoiceAction` (réutilisation, pas de duplication). Wrappé
 * dans `DB::transaction` pour rollback complet si l'une des étapes
 * échoue (ex. `MissingPricingException` levée par `Generate`).
 */
final readonly class RegenerateInvoiceAction
{
    public function __construct(
        private CancelInvoiceAction $cancel,
        private GenerateInvoiceAction $generate,
    ) {}

    /**
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     */
    public function execute(Invoice $invoice, int $generatedByUserId, array $issuer): Invoice
    {
        return DB::transaction(function () use ($invoice, $generatedByUserId, $issuer): Invoice {
            $companyId = (int) $invoice->company_id;
            $year = (int) $invoice->year;
            $month = (int) $invoice->month;

            $this->cancel->execute($invoice);

            return $this->generate->execute(
                companyId: $companyId,
                year: $year,
                month: $month,
                generatedByUserId: $generatedByUserId,
                issuer: $issuer,
            );
        });
    }
}
