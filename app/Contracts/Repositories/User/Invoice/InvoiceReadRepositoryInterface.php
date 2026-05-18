<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Invoice;

use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lectures Invoice · interface slim conforme ADR-0013 (zéro
 * transformation, zéro décision métier ; les retours sont des Models /
 * Collections bruts).
 */
interface InvoiceReadRepositoryInterface
{
    /**
     * Inclut les factures soft-deletées (versions obsolètes après
     * régénération) pour permettre la navigation sur la page Show de
     * n'importe quelle version historique. L'UI affichera le bandeau
     * « Remplacée par #XXX » sur les obsolètes.
     */
    public function findById(int $id): ?Invoice;

    /**
     * Récupère la facture qui a été remplacée par celle d'id `$invoiceId`
     * via une régénération (la « predecessor »). Retourne `null` pour
     * les factures qui n'ont pas remplacé de version antérieure. Inclut
     * les soft-deletées · une predecessor est par construction obsolète.
     */
    public function findPredecessor(int $invoiceId): ?Invoice;

    /**
     * Reconstitue la chaîne complète des versions pour le couple
     * (entreprise × année × mois) d'une facture donnée. Inclut la
     * facture courante elle-même et toutes les versions obsolètes
     * (soft-deletées). Aucun ordre garanti · le tri est fait par
     * le composant front (`InvoiceHistoryTimeline`).
     *
     * @return list<Invoice>
     */
    public function findHistoryChainFor(Invoice $invoice): array;

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
     * Lot 2 réductions commerciales · ajoute `grossTotalCents` et
     * `totalDiscountCents` au snapshot pour exposer le détail
     * brut/réduction de la facture émise sur les écrans billing.
     *
     * @return array<int, array{id: int, invoiceNumber: string, totalHtCents: int, invoicedDaysUsed: int, grossTotalCents: int, totalDiscountCents: int}> map[month] => snapshot
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
     * Le filtre `divergentOnly` est appliqué en SQL natif (`WHERE
     * is_divergent = 1`) · la flag matérialisée est posée par les
     * observers (T6 / Phase 14.R), supprimant le N+1
     * `BillingCalculator::calculate` qui dégradait l'Index.
     *
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForIndex(InvoiceIndexQueryData $query): LengthAwarePaginator;

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
