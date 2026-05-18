<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\RentalDiscount;

use App\Models\RentalDiscount;

/**
 * Écritures sur les réductions commerciales.
 *
 * Cf. ADR-0013 (couches strictes Controller→Action→Service→Repository).
 *
 * Toutes les mutations passent par les Actions du domaine
 * (`CreateRentalDiscountAction`, `UpdateRentalDiscountAction`,
 * `DeleteRentalDiscountAction` · lot 4) qui orchestrent la validation
 * chevauchement avant l'écriture en transaction.
 */
interface RentalDiscountWriteRepositoryInterface
{
    /**
     * Persiste une nouvelle réduction. La synchronisation des véhicules
     * (pivot) se fait via {@see syncVehicles}.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): RentalDiscount;

    /**
     * Met à jour les attributs scalaires de la réduction (sans toucher
     * au pivot · cf. {@see syncVehicles}).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(RentalDiscount $discount, array $attributes): RentalDiscount;

    /**
     * Soft-delete la réduction · préserve l'ID pour référence depuis
     * les invoice_lines déjà émises (audit immuable).
     */
    public function softDelete(RentalDiscount $discount): void;

    /**
     * Synchronise la liste des véhicules ciblés par la réduction.
     * Liste vide = pivot vidé = sémantique « applique à tous les
     * véhicules de la company sur la période ».
     *
     * @param  list<int>  $vehicleIds
     */
    public function syncVehicles(RentalDiscount $discount, array $vehicleIds): void;
}
