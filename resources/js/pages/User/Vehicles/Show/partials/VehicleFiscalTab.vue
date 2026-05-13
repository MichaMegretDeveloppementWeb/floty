<script setup lang="ts">
/**
 * Onglet Fiscalité de la fiche véhicule. Affiche les caractéristiques
 * fiscales appliquées au calcul + le détail de la Taxe pleine pour
 * l'année sélectionnée (méthode CO₂, polluants, exonérations, règles).
 *
 * D5.10.U · sélecteur d'année piloté par le param URL **unifié**
 * `?year=` partagé avec l'onglet Facturation (cf. `VehicleController::
 * show`). Partial reload Inertia sur les props dépendantes du year.
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import YearPills from '@/Components/Ui/YearPills/YearPills.vue';
import { useVehicleFiscalSelectedYear } from '@/Composables/Vehicle/Show/useVehicleFiscalSelectedYear';
import AppliedVfcCard from './fiscal/AppliedVfcCard.vue';
import FullYearTaxBreakdownPanel from './FullYearTaxBreakdownPanel.vue';

type Breakdown = App.Data.User.Vehicle.VehicleFullYearTaxBreakdownData;
type UsageStats = App.Data.User.Vehicle.VehicleUsageStatsData;

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    fiscalYearBreakdown: Breakdown;
    fiscalYear: number;
}>();

const { selectedYear, selectYear, loading } = useVehicleFiscalSelectedYear(
    props.fiscalYear,
);

const isCurrentYear = computed<boolean>(
    () => selectedYear.value === props.vehicle.kpiYear,
);

// Reconstruction stats-like pour le panel · il ne lit que `fiscalYear`
// et `fullYearTaxBreakdown`. Les autres champs ne sont pas accédés
// par ce composant.
const statsLike = computed<UsageStats>(() => ({
    ...props.vehicle.usageStats,
    fiscalYear: props.fiscalYear,
    fullYearTaxBreakdown: props.fiscalYearBreakdown,
}));
</script>

<template>
    <div class="flex flex-col gap-6">
        <Card>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h3 class="text-base font-semibold text-slate-900">
                        Fiscalité
                        <span
                            v-if="isCurrentYear"
                            class="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                            title="Exercice fiscal en cours · chiffres provisoires"
                        >
                            En cours
                        </span>
                    </h3>
                    <p class="text-sm text-slate-500">
                        Exercice {{ props.fiscalYear }}
                    </p>
                </div>

                <YearPills
                    v-if="props.vehicle.yearScope.availableYears.length > 0"
                    :years="props.vehicle.yearScope.availableYears"
                    :active-year="selectedYear"
                    :loading="loading"
                    @select="selectYear"
                />
            </div>
        </Card>

        <AppliedVfcCard :segments="props.fiscalYearBreakdown.taxSegments" />

        <Card>
            <template #header>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Calcul de la Taxe pleine
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Détail théorique pour 100 % d'utilisation ·
                        méthode CO₂, polluants, exonérations, règles
                        appliquées.
                    </p>
                </div>
            </template>

            <div :class="{ 'opacity-60': loading }">
                <FullYearTaxBreakdownPanel :stats="statsLike" />
            </div>
        </Card>
    </div>
</template>
