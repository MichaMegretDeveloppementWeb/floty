<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\RentalDiscount;

use App\Data\User\RentalDiscount\RentalDiscountIndexQueryData;
use App\Models\RentalDiscount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lectures sur les réductions commerciales appliquées aux loyers.
 *
 * Cf. ADR-0013 (couches strictes Controller→Action→Service→Repository).
 *
 * Consommé par ·
 *   - `RentalDiscountConflictService` pour la validation chevauchement
 *   - `DiscountResolver` (lot 2) pour le préchargement batch lors du
 *     calcul de facture
 *   - `RentalDiscountQueryService` (lot 4) pour les vues Index/Show
 *   - `Vehicle*Repository` (lot 2) pour le check « véhicule listé dans
 *     une réduction active » avant suppression
 */
interface RentalDiscountReadRepositoryInterface
{
    /**
     * Récupère une réduction par id avec ses véhicules attachés
     * (eager-load `vehicles`). Renvoie `null` si non trouvée ou
     * soft-deletée.
     */
    public function findById(int $id): ?RentalDiscount;

    /**
     * Variante incluant les soft-deletées · pour les vues d'audit /
     * détails de facture émises qui référencent une réduction archivée.
     */
    public function findByIdWithTrashed(int $id): ?RentalDiscount;

    /**
     * Retourne **vrai** ssi au moins une réduction non soft-deletée
     * existe en base. Sert au flag `hasAnyRentalDiscount` exposé en
     * payload Inertia pour distinguer empty state de "filtre actif
     * sans résultat" (mémoire `feedback_index_has_any_required`).
     */
    public function existsAny(): bool;

    /**
     * Toutes les réductions actives ou planifiées d'une entreprise qui
     * croisent l'année donnée (start_date <= 31/12/Y et end_date >= 01/01/Y).
     * Eager-load `vehicles`. Triées par `start_date` croissante.
     *
     * Utilisée par le `DiscountResolver` pour préchargement batch.
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findActiveForCompanyYear(int $companyId, int $year): Collection;

    /**
     * Variante multi-entreprises · 1 SQL pour N entreprises sur
     * l'année donnée. Eager-load `vehicles`.
     *
     * Utilisée par le `DiscountResolver` lors du batch Dashboard
     * `totalRecettesForYears` (cross-cies × cross-années).
     *
     * @param  list<int>  $companyIds
     * @return Collection<int, RentalDiscount>
     */
    public function findActiveForCompaniesYear(array $companyIds, int $year): Collection;

    /**
     * Retourne les réductions d'une entreprise dont la période
     * chevauche `[startDate, endDate]` (inclusif). Eager-load `vehicles`.
     * `excludeId` permet d'exclure une réduction en cours d'édition.
     *
     * Utilisée par le `RentalDiscountConflictService`. Le filtrage par
     * intersection véhicules se fait en PHP côté service (le pivot est
     * petit, < 50 véhicules typique).
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findOverlappingForCompany(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Retourne les réductions actives à une date donnée qui listent
     * explicitement le véhicule donné (= pivot non vide ET vehicle_id
     * y figure). Sert au check « ce véhicule est-il bloqué pour
     * suppression ? » (lot 2 · `VehicleInUseByDiscountException`).
     *
     * Note · les réductions « tous véhicules » (pivot vide) ne bloquent
     * PAS la suppression d'un véhicule, car ce dernier n'y est pas
     * référencé · sa suppression réduit silencieusement le périmètre
     * effectif (cohérent avec la sémantique « tous les véhicules
     * actuels »).
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findActiveListingVehicleOn(int $vehicleId, string $date): Collection;

    /**
     * Toutes les réductions non soft-deletées d'une entreprise, eager-load
     * `vehicles` + `company`. Triées par `start_date` décroissant (plus
     * récentes en premier, conforme à la convention UI Index). Sert à la
     * section « Réductions commerciales » de l'onglet Facturation Company
     * Show (Lot 3 · propagation UI).
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findForCompany(int $companyId): Collection;

    /**
     * Index server-side paginé (cf. ADR-0020) · Lot 4 du chantier
     * RentalDiscount. Applique filtres (companyId, status, search) et
     * tri whitelisté en SQL natif. Eager-load `vehicles` + `company`
     * pour alimenter `RentalDiscountListItemData`.
     *
     * @return LengthAwarePaginator<int, RentalDiscount>
     */
    public function paginateForIndex(RentalDiscountIndexQueryData $query): LengthAwarePaginator;

    /**
     * Détail Show · eager-load `vehicles` + `company` + `createdBy`.
     */
    public function findByIdForShow(int $id): ?RentalDiscount;

    /**
     * Compteurs pour le bandeau stats sur la page Index ·
     * `{actives, planned, expired}` par rapport à la date du jour.
     *
     * @return array{active: int, planned: int, expired: int}
     */
    public function statsForIndex(string $today): array;
}
