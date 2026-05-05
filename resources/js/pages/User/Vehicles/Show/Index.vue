<script setup lang="ts">
/**
 * Page Show Véhicule — composition selon doctrine temporelle
 * (chantier η Phase 2) avec 3 lentilles distinctes :
 *
 *   1. **Présent** (KPIs en haut) : année calendaire courante figée.
 *   2. **Évolution** (Historique) : `[minYear..currentYear-1]`, lignes
 *      neutres pour les années sans contrat.
 *   3. **Exploration** (Timeline / Breakdown / FullYearTax) : pilotée
 *      par un sélecteur d'année **partagé** entre les 3 partials.
 *
 * Le sélecteur Exploration utilise `useYearScope` en mode reload — le
 * pipeline fiscal est trop lourd pour pré-calculer toutes les années
 * côté front. Le scope est le scope global complet (`yearScope.availableYears`)
 * — toutes les années où il y a au moins un contrat. Si l'année
 * sélectionnée n'a pas de règles fiscales codées, le backend renvoie
 * Timeline + jours intacts mais taxes à 0 et FullYear neutre.
 */
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import YearSelector from '@/Components/Ui/YearSelector/YearSelector.vue';
import { useYearScope } from '@/Composables/Shared/useYearScope';
import CompanyFiscalBreakdownTable from './partials/CompanyFiscalBreakdownTable.vue';
import CurrentFiscalCharacteristicsCard from './partials/CurrentFiscalCharacteristicsCard.vue';
import FullYearTaxBreakdownPanel from './partials/FullYearTaxBreakdownPanel.vue';
import UnavailabilitiesCard from './partials/UnavailabilitiesCard.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleKpiCards from './partials/VehicleKpiCards.vue';
import VehicleYearHistoryCard from './partials/VehicleYearHistoryCard.vue';
import VehicleYearlyUsageTimeline from './partials/VehicleYearlyUsageTimeline.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const fullYearModalOpen = ref<boolean>(false);

// Sélecteur d'année Exploration — partagé entre Timeline, Breakdown,
// FullYearTax. Mode reload (Inertia partial reload du DTO `vehicle`)
// car le pipeline fiscal est trop lourd à pré-calculer toutes années.
// Scope = scope global complet (`yearScope.availableYears`) — doctrine
// « données métier ⊥ règles fiscales » : on peut explorer n'importe
// quelle année. Si l'année n'a pas de règles fiscales codées, le
// backend renvoie Timeline + jours intacts, taxes à 0, breakdown
// FullYear neutre avec message « Règles non implémentées ».
const { selectedYearModel, canSelect } = useYearScope(props.vehicle.yearScope, {
    reloadKeys: ['vehicle'],
    initialYear: props.vehicle.selectedYear,
});
</script>

<template>
    <Head :title="`${props.vehicle.licensePlate} · ${props.vehicle.brand} ${props.vehicle.model}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <VehicleHeader :vehicle="props.vehicle" />

            <!-- Présent — KPIs année courante (figés, pas de sélecteur) -->
            <VehicleKpiCards
                :kpi-stats="props.vehicle.kpiStats"
                :kpi-year="props.vehicle.kpiYear"
                :kpi-fiscal-available="props.vehicle.kpiFiscalAvailable"
            />

            <!-- Caractéristiques fiscales (atemporel) -->
            <CurrentFiscalCharacteristicsCard
                :fiscal="props.vehicle.currentFiscalCharacteristics"
                :history="props.vehicle.fiscalCharacteristicsHistory"
                :options="props.options"
            />

            <!-- Évolution — exercices passés (sans année courante) -->
            <VehicleYearHistoryCard :history="props.vehicle.history" />

            <!-- Exploration — sélecteur année partagé + 3 partials liés -->
            <section class="flex flex-col gap-6">
                <header class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                            Exploration d'un exercice
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Détail timeline + répartition + coût plein pour
                            l'année sélectionnée.
                        </p>
                    </div>
                    <YearSelector
                        v-if="canSelect"
                        v-model="selectedYearModel"
                        :available-years="props.vehicle.yearScope.availableYears"
                    />
                </header>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    <!-- Colonne principale -->
                    <div class="flex flex-col gap-6 xl:col-span-2">
                        <VehicleYearlyUsageTimeline :stats="props.vehicle.usageStats" />
                        <CompanyFiscalBreakdownTable :stats="props.vehicle.usageStats" />
                    </div>

                    <!-- Aside visible xl+ uniquement -->
                    <aside class="hidden xl:col-span-1 xl:block">
                        <FullYearTaxBreakdownPanel :stats="props.vehicle.usageStats" />
                    </aside>
                </div>

                <!-- < xl : bouton ouvre le panel en modale -->
                <button
                    type="button"
                    class="self-start text-xs font-medium text-blue-600 hover:underline xl:hidden"
                    @click="fullYearModalOpen = true"
                >
                    Voir le détail du Coût plein {{ props.vehicle.usageStats.fiscalYear }}
                </button>
            </section>

            <!-- Indispos (atemporel) -->
            <UnavailabilitiesCard
                :vehicle-id="props.vehicle.id"
                :unavailabilities="props.vehicle.unavailabilities"
                :busy-dates="props.vehicle.busyDates"
            />

            <!-- Modale FullYearTax (mobile/< xl) -->
            <Modal
                v-model:open="fullYearModalOpen"
                :title="`Détail du Coût plein ${props.vehicle.usageStats.fiscalYear}`"
                size="lg"
            >
                <FullYearTaxBreakdownPanel :stats="props.vehicle.usageStats" />
            </Modal>
        </div>
    </UserLayout>
</template>
