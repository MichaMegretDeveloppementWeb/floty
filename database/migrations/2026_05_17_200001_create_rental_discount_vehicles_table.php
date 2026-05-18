<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot `rental_discount_vehicles` · liste des véhicules concernés
 * par une réduction commerciale donnée.
 *
 * **Sémantique applicative critique** ·
 *   - pivot peuplé = la réduction s'applique uniquement à ces véhicules
 *   - pivot vide = la réduction s'applique à TOUS les véhicules de la
 *     company sur la période (décision applicative, lue dans
 *     `DiscountResolver`)
 *
 * **`vehicle_id` est `restrictOnDelete`** ·
 *   - on bloque la suppression d'un véhicule listé dans une réduction
 *     active (cohérent avec ADR-0018 cycle de vie véhicule · pas de
 *     suppression silencieuse)
 *   - l'utilisateur doit d'abord retirer le véhicule de la réduction
 *     ou supprimer/archiver la réduction avant
 *   - levé via `VehicleInUseByDiscountException` au lot 2 lors du
 *     check préalable dans `DeleteVehicleAction`
 *
 * Pas de colonnes additionnelles · juste l'association binaire. Pas de
 * timestamps (la réduction parent porte la traçabilité).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_discount_vehicles', function (Blueprint $table): void {
            $table->foreignId('rental_discount_id')
                ->constrained('rental_discounts')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            // Clé primaire composite · garantit l'unicité de l'association
            // et sert d'index pour les lookups par discount ou par vehicle.
            $table->primary(['rental_discount_id', 'vehicle_id'], 'rdv_pk');

            // Index inverse pour la requête « ce véhicule est-il listé
            // dans une réduction active ? » utilisée par le check de
            // suppression véhicule (lot 2).
            $table->index('vehicle_id', 'rdv_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_discount_vehicles');
    }
};
