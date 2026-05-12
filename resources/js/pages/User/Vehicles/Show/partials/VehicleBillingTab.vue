<script setup lang="ts">
/**
 * Onglet Facturation de la fiche véhicule (Phase 14 V1.2). Compose deux
 * cartes :
 *   - **Tarifs jour / semaine / mois par année** : édition des tarifs
 *     annuels servant au calcul du panachage optimal.
 *   - **Recettes mensuelles** : récap 12 mois de la recette facturée à
 *     toutes les entreprises utilisatrices du véhicule sur l'année
 *     sélectionnée. Le sélecteur d'année est piloté par `?billingYear=`
 *     URL (cf. `VehicleController::show`).
 *
 * La génération de facture se fait depuis la fiche entreprise (cumul
 * cross-véhicules par mois) ou depuis la liste Factures.
 */
import { router } from '@inertiajs/vue3';
import MonthlyBillingBreakdownCard from '@/Components/Domain/Billing/MonthlyBillingBreakdownCard.vue';
import YearPills from "@/Components/Ui/YearPills/YearPills.vue";
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import VehiclePricingsCard from './billing/VehiclePricingsCard.vue';

const props = defineProps<{
    vehicleId: number;
    pricings: ReadonlyArray<App.Data.User.Vehicle.VehicleYearlyPricingData>;
    monthlyBilling: App.Data.User.Billing.MonthlyBillingBreakdownData;
    /**
     * Scope global d'années (cf. `YearScopeData`). On utilise
     * `availableYears` pour les pills.
     */
    yearScope: App.Data.Shared.YearScopeData;
    activeYear: number;
}>();

function selectYear(year: number): void {
    if (year === props.activeYear) {
        return;
    }

    router.get(
        vehiclesShowRoute.url({ vehicle: props.vehicleId }),
        { billingYear: year, tab: 'billing' },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['vehicleBilling', 'billingYear'],
            replace: true,
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <VehiclePricingsCard
            :vehicle-id="props.vehicleId"
            :pricings="props.pricings"
        />

        <YearPills
            v-if="yearScope.availableYears.length > 0"
            :years="yearScope.availableYears"
            :active-year="activeYear"
            @select="selectYear"
        />

        <MonthlyBillingBreakdownCard
            :monthly-billing="monthlyBilling"
            title="Recettes mensuelles"
            description="Total cross-entreprises facturable, mois par mois, sur l'année sélectionnée."
        />
    </div>
</template>
