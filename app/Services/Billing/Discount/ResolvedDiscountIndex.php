<?php

declare(strict_types=1);

namespace App\Services\Billing\Discount;

use App\Models\RentalDiscount;

/**
 * Index in-memory `(vehicleId, dateStr Y-m-d) -> RentalDiscount|null`
 * pré-calculé pour une (entreprise × année) (ou multi-entreprises selon
 * le mode de préchargement).
 *
 * Sémantique « pivot vide = tous les véhicules » prise en compte au
 * preload · si une réduction n'a aucun pivot, elle est enregistrée
 * sous le wildcard `null` pour le vehicleId.
 *
 * Lookup O(1) via {@see findFor}. Conçu pour être créé une fois par
 * (entreprise × année) puis interrogé en boucle par le `DiscountApplier`
 * sans nouvelle SQL.
 */
final class ResolvedDiscountIndex
{
    /**
     * Réductions à appliquer aux véhicules listés explicitement. Indexé
     * `vehicleId -> list<RentalDiscount>`. Les réductions sont triées
     * par start_date pour assurer un ordre déterministe (et l'unicité
     * d'application garantie par le ConflictService permet de prendre
     * la première qui matche la date).
     *
     * @var array<int, list<RentalDiscount>>
     */
    private array $byVehicle = [];

    /**
     * Réductions « tous véhicules » (pivot vide) pour cette
     * (entreprise × année). Triées par start_date.
     *
     * @var list<RentalDiscount>
     */
    private array $allVehicles = [];

    private bool $empty = true;

    /**
     * @param  iterable<int, RentalDiscount>  $discounts  Doivent avoir leur relation `vehicles` eager-loadée.
     */
    public static function fromDiscounts(iterable $discounts): self
    {
        $index = new self;

        foreach ($discounts as $discount) {
            $index->empty = false;

            $vehicleIds = $discount->vehicles->pluck('id')->all();

            if ($vehicleIds === []) {
                $index->allVehicles[] = $discount;

                continue;
            }

            foreach ($vehicleIds as $vehicleId) {
                $index->byVehicle[(int) $vehicleId][] = $discount;
            }
        }

        return $index;
    }

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Retourne la réduction active pour `(vehicleId, dateStr)` ou `null`
     * si aucune.
     *
     * Précédence · si une réduction "tous véhicules" et une réduction
     * "subset" se trouvent toutes deux applicables sur le même véhicule
     * à la même date, la garantie du `RentalDiscountConflictService`
     * exclut ce cas en amont (overlap interdit). On peut donc prendre
     * la première trouvée sans risque d'ambiguïté.
     */
    public function findFor(int $vehicleId, string $date): ?RentalDiscount
    {
        // 1. lookup explicite par véhicule.
        foreach ($this->byVehicle[$vehicleId] ?? [] as $discount) {
            if ($this->dateInDiscount($date, $discount)) {
                return $discount;
            }
        }

        // 2. fallback "tous véhicules".
        foreach ($this->allVehicles as $discount) {
            if ($this->dateInDiscount($date, $discount)) {
                return $discount;
            }
        }

        return null;
    }

    /**
     * Hot path · permet aux consommateurs d'esquiver entièrement le
     * pipeline réduction si aucune réduction n'est active. Garantie
     * d'équivalence stricte avec le pipeline pre-Lot 2.
     */
    public function isEmpty(): bool
    {
        return $this->empty;
    }

    private function dateInDiscount(string $date, RentalDiscount $discount): bool
    {
        $start = $discount->start_date->toDateString();
        $end = $discount->end_date->toDateString();

        return $date >= $start && $date <= $end;
    }
}
