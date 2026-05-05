<script setup lang="ts">
/**
 * Onglet Fiscalité de la fiche véhicule (chantier η Phase 2 onglets) :
 * détail du calcul Coût plein pour une année (méthode CO₂, polluants,
 * exonérations, règles fiscales appliquées).
 *
 * Sélecteur d'année **indépendant** de celui de Vue d'ensemble — chaque
 * onglet a son propre cache `useYearLazy` et sa propre année courante.
 *
 * Le panel `FullYearTaxBreakdownPanel` consomme `stats.fiscalYear` +
 * `stats.fullYearTaxBreakdown`. On lui passe un objet stats-like
 * minimal construit à partir du DTO `VehicleFullYearTaxBreakdownData`
 * fetché.
 */
import { computed } from 'vue';
import { fullYearBreakdown as fullYearBreakdownRoute } from '@/actions/App/Http/Controllers/User/Vehicle/VehicleController';
import Card from '@/Components/Ui/Card/Card.vue';
import YearSelector from '@/Components/Ui/YearSelector/YearSelector.vue';
import { useYearLazy } from '@/Composables/Shared/useYearLazy';
import FullYearTaxBreakdownPanel from './FullYearTaxBreakdownPanel.vue';

type Breakdown = App.Data.User.Vehicle.VehicleFullYearTaxBreakdownData;
type UsageStats = App.Data.User.Vehicle.VehicleUsageStatsData;

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();

const initialBreakdown = props.vehicle.usageStats.fullYearTaxBreakdown;
const initialYear = props.vehicle.usageStats.fiscalYear;

const { yearModel, year, data, isLoading } = useYearLazy<Breakdown>(
    initialYear,
    initialBreakdown,
    async (target) => {
        const url = fullYearBreakdownRoute.url(props.vehicle.id, { query: { year: target } });
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return (await response.json()) as Breakdown;
    },
);

// Reconstruction stats-like pour le panel — il ne lit que `fiscalYear`
// et `fullYearTaxBreakdown`. Les autres champs ne sont pas accédés
// par ce composant, on peut les laisser indéfinis.
const statsLike = computed<UsageStats>(() => ({
    ...props.vehicle.usageStats,
    fiscalYear: year.value,
    fullYearTaxBreakdown: data.value ?? initialBreakdown,
}));
</script>

<template>
    <div class="flex flex-col gap-6">
        <Card>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Calcul du Coût plein
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Détail théorique pour 100 % d'utilisation —
                            méthode CO₂, polluants, exonérations, règles
                            appliquées.
                        </p>
                    </div>
                    <YearSelector
                        v-model="yearModel"
                        :available-years="vehicle.yearScope.availableYears"
                        :disabled="isLoading"
                    />
                </div>
            </template>

            <div :class="{ 'opacity-60': isLoading }">
                <FullYearTaxBreakdownPanel :stats="statsLike" />
            </div>
        </Card>
    </div>
</template>
