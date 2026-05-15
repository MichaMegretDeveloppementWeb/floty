<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceLine;

/**
 * Écritures Invoice · création seulement. Pas d'update : conformément
 * à la doctrine immuabilité (Phase 14.E V1.2), une facture émise est
 * figée. La seule mutation autorisée est `delete` (cascade des lignes).
 */
interface InvoiceWriteRepositoryInterface
{
    /**
     * Persiste une facture en base. Les lignes sont créées séparément
     * via {@see persistLines} dans la même transaction (cf.
     * `GenerateInvoiceAction`).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persist(array $attributes): Invoice;

    /**
     * Persiste les lignes attachées à une facture. Bulk insert.
     *
     * @param  list<array<string, mixed>>  $linesAttributes
     * @return list<InvoiceLine>
     */
    public function persistLines(int $invoiceId, array $linesAttributes): array;

    /**
     * Suppression soft (Invoice utilise `SoftDeletes`). Conservée pour
     * la régénération · cf. {@see RegenerateInvoiceAction}.
     */
    public function delete(Invoice $invoice): void;

    /**
     * Suppression physique (hard delete) avec cascade des lignes. Utilisé
     * par {@see CancelInvoiceAction} pour l'annulation explicite, qui
     * effacent intégralement la facture · à distinguer du soft-delete
     * de la régénération qui matérialise l'historique.
     */
    public function forceDelete(Invoice $invoice): void;

    /**
     * Lot 4 D01 (F-34-001) · bulk UPDATE conditionnel posant
     * `is_divergent = true` sur les factures de l'entreprise dont
     * `(year, month)` matche l'un des tuples passés. Skippe les
     * factures déjà divergentes (idempotence + économie d'écriture).
     *
     * Retourne le nombre de lignes flippées.
     *
     * Note doctrinale · `is_divergent` est une métadonnée
     * d'observabilité (pas un champ figé du snapshot ADR-0008) ·
     * sa mutation post-émission est légitime.
     *
     * @param  list<array{year:int,month:int}>  $tuples
     */
    public function flagDivergentForCompanyAndTuples(int $companyId, array $tuples): int;

    /**
     * Lot 4 D01 (F-34-001) · bulk UPDATE conditionnel posant
     * `is_divergent = true` sur toutes les factures `year` donné qui
     * appartiennent à des companies ayant eu un contrat actif sur le
     * véhicule chevauchant cette année (cf. T6 / Phase 14.R V1.2 ·
     * cas tarif véhicule modifié).
     *
     * Le pivot vehicle → companies passe par une **subquery embedded**
     * dans l'UPDATE pour rester atomique côté SQL (1 round-trip BDD).
     * Contraint à `deleted_at IS NULL` car la subquery utilise le
     * Query Builder bas-niveau qui bypass le scope SoftDeletes Eloquent.
     *
     * Retourne le nombre de factures flippées.
     */
    public function flagDivergentForVehiclePricingYear(int $vehicleId, int $year): int;
}
