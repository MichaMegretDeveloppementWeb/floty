<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Régénère une facture émise (Phase 14.I+ V1.2 · refonte historique
 * D5.10.P · option C).
 *
 * Sémantique « régénération » : l'ancienne facture n'est PAS supprimée
 * mais soft-deletée et marquée obsolète, sa colonne `superseded_by_id`
 * pointant vers la nouvelle facture. Son PDF reste sur disque pour
 * préserver l'audit trail (cf. art. 286 quater CGI · obligation de
 * conservation des pièces facturées).
 *
 * Chaîne historique reconstituable à tout moment :
 * `v1.superseded_by → v2.superseded_by → v3 (courante)`.
 *
 * **Numérotation** : la nouvelle facture obtient un **nouveau numéro
 * séquentiel** dans la suite chronologique (pas de suffixe « bis »),
 * conforme à l'art. 242 nonies A annexe II CGI (numérotation continue
 * sans rupture) et à la doctrine BOFiP BOI-TVA-DECLA-30-20-20-10
 * (facture rectificative = nouveau numéro + mention de remplacement).
 *
 * **Doctrine T4 (Phase 14.P) · cohérence DB ↔ filesystem** : la
 * suppression hard du PDF n'a plus lieu (préservation systématique).
 * Si `Generate` throw, la transaction rollback restaure l'ancienne
 * facture (le softDelete et l'`obsolete_at` sont annulés). Aucun
 * filesystem cleanup nécessaire.
 */
final readonly class RegenerateInvoiceAction
{
    public function __construct(
        private GenerateInvoiceAction $generate,
    ) {}

    /**
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     */
    public function execute(Invoice $invoice, int $generatedByUserId, array $issuer): Invoice
    {
        $companyId = (int) $invoice->company_id;
        $year = (int) $invoice->year;
        $month = (int) $invoice->month;

        return DB::transaction(function () use ($invoice, $generatedByUserId, $issuer, $companyId, $year, $month): Invoice {
            // 1. Soft-delete l'ancienne facture + marque l'instant d'obsolescence.
            //    Note : `is_divergent` reste à sa valeur · c'était un flag
            //    instantané de divergence, distinct du marqueur définitif
            //    de remplacement (`superseded_by_id`).
            $invoice->obsolete_at = Carbon::now();
            $invoice->save();
            $invoice->delete(); // softDelete grâce au trait SoftDeletes.

            // 2. Génère la nouvelle facture (numéro séquentiel suivant
            //    dans la suite chronologique, conforme aux exigences
            //    légales françaises de numérotation continue).
            $newInvoice = $this->generate->execute(
                companyId: $companyId,
                year: $year,
                month: $month,
                generatedByUserId: $generatedByUserId,
                issuer: $issuer,
            );

            // 3. Chaîne historique : l'ancienne pointe vers la nouvelle.
            //    Mise à jour via QueryBuilder pour bypass le scope
            //    SoftDeletes (l'instance Eloquent est soft-deletée et
            //    ne se laisserait pas re-save sans `restore()`).
            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['superseded_by_id' => $newInvoice->id]);

            return $newInvoice;
        });
    }
}
