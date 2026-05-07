<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Invoice;

use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lectures Invoice — interface slim conforme ADR-0013 (zéro
 * transformation, zéro décision métier ; les retours sont des Models /
 * Collections bruts).
 */
interface InvoiceReadRepositoryInterface
{
    public function findById(int $id): ?Invoice;

    /**
     * Lookup applicatif d'unicité (entreprise × année × mois). Utilisé
     * par {@see App\Actions\Invoice\GenerateInvoiceAction} pour rejeter
     * la regénération avant d'atteindre la contrainte UNIQUE en base.
     */
    public function findForCompanyYearMonth(int $companyId, int $year, int $month): ?Invoice;

    /**
     * Map des factures émises pour une (entreprise × année), indexée par
     * mois civil. Sert au récap mensuel de la fiche entreprise pour :
     *   - basculer le bouton « Générer » → lien « Voir #YYYY-MM-NNNN »
     *   - détecter une divergence facture vs réalité (ajout/modif de
     *     contrat post-émission) en comparant le snapshot figé
     *     (`totalHtCents`, `invoicedDaysUsed`) au recalcul dynamique
     *
     * Single query (pas de N+1 sur les 12 mois).
     *
     * @return array<int, array{id: int, invoiceNumber: string, totalHtCents: int, invoicedDaysUsed: int}> map[month] => snapshot
     */
    public function findExistingByMonthForCompanyYear(int $companyId, int $year): array;

    /**
     * Récupère le numéro séquentiel le plus élevé déjà attribué pour
     * une combinaison (année, mois). Utilisé pour générer le prochain
     * `invoice_number` (sans race condition grâce à la contrainte
     * UNIQUE applicative + la transaction de l'Action).
     *
     * Retourne 0 si aucune facture n'existe encore pour ce mois.
     */
    public function maxSequenceForYearMonth(int $year, int $month): int;

    /**
     * Liste paginée server-side de l'Index Invoices (cf. ADR-0020).
     *
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForIndex(InvoiceIndexQueryData $query): LengthAwarePaginator;

    /**
     * Liste **non paginée** des factures matchant les filtres SQL
     * (companyId, year, month, search, sort) — ignore `divergentOnly`
     * qui est calculé en post-traitement PHP par le service.
     *
     * Utilisé exclusivement par {@see App\Services\Invoice\InvoiceQueryService::listPaginated}
     * quand `divergentOnly = true` : le service charge tout, filtre
     * en mémoire, puis pagine via `LengthAwarePaginator` manuel.
     *
     * @return Collection<int, Invoice>
     */
    public function findAllMatching(InvoiceIndexQueryData $query): Collection;

    /**
     * `true` ssi au moins une facture existe en base. Utilisé par
     * l'Index pour distinguer « table intrinsèquement vide » du
     * « filtre actif retournant 0 ».
     */
    public function existsAny(): bool;

    /**
     * Bornes des années couvertes par les factures émises. Utilisé pour
     * piloter l'options list du sélecteur Année du filtre Index : on
     * affiche toutes les années entre la plus ancienne facture et l'année
     * en cours (ou l'année la plus tardive si supérieure).
     *
     * Retourne `null` s'il n'y a aucune facture (cas table vide).
     *
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array;
}
