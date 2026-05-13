<script setup lang="ts">
/**
 * Onglet Facturation de la fiche véhicule (Phase 14 V1.2). Compose deux
 * cartes :
 *   - **Tarifs jour / semaine / mois par année** : édition des tarifs
 *     annuels servant au calcul du panachage optimal.
 *   - **Recettes mensuelles** : récap 12 mois de la recette facturée à
 *     toutes les entreprises utilisatrices du véhicule sur l'année
 *     sélectionnée.
 *
 * D5.10.U · sélecteur d'année piloté par le param URL **unifié** `?year=`
 * partagé avec l'onglet Fiscalité (cf. `VehicleController::show`).
 *
 * La génération de facture se fait depuis la fiche entreprise (cumul
 * cross-véhicules par mois) ou depuis la liste Factures.
 */
import { router } from '@inertiajs/vue3';
import MonthlyBillingBreakdownCard from '@/Components/Domain/Billing/MonthlyBillingBreakdownCard.vue';
import { injectVehicleTabsState } from '@/Composables/Vehicle/Show/useVehicleTabs';
import YearPills from "@/Components/Ui/YearPills/YearPills.vue";
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

const tabsState = injectVehicleTabsState();

function selectYear(year: number): void {
    if (year === props.activeYear) {
        return;
    }

    // D5.10.U · param URL unifié `?year=` partagé avec Fiscalité.
    // D5.10.V · ne recharge QUE les props de l'onglet Billing actif ·
    // Fiscal marqué stale (re-fetch au prochain clic).
    const url = new URL(window.location.href);
    url.searchParams.set('year', String(year));
    url.searchParams.set('tab', 'billing');

    router.get(
        url.pathname + url.search,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['vehicleBilling', 'billingYear', 'fiscalYear'],
            replace: true,
            onSuccess: () => {
                tabsState?.markStale(['fiscal']);
            },
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
            :show-invoice-number-column="false"
        />
    </div>
</template>
