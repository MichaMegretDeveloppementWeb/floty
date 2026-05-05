<script setup lang="ts">
/**
 * Rangée de 3 KPIs **Présent** — reflète l'année calendaire courante
 * uniquement (chantier η Phase 2, doctrine temporelle).
 *
 * Refonte : avant le bouton « Voir le détail » du KPI Coût plein
 * pointait vers le `FullYearTaxBreakdownPanel`, lui-même calé sur
 * l'année active de la page (= une seule année). Désormais, le panel
 * vit dans la section Exploration et est piloté par le sélecteur
 * partagé. Les KPIs Présent ne portent plus que des valeurs scalaires
 * sur l'année courante (sans drill-down).
 *
 * Spécificités :
 *   - Si `kpiFiscalAvailable === false` (règles fiscales pas codées
 *     pour l'année courante), les KPIs « Taxe réelle » et « Coût plein »
 *     affichent un `—` neutre + caption « Règles {YYYY} non implémentées »
 *     (cohérent CompanyKpiCards, doctrine HD6).
 *   - La KPI « Jours d'utilisation » reste toujours significative
 *     (donnée brute, indépendante des règles fiscales).
 */
import { Calendar, Coins, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    kpiStats: App.Data.User.Vehicle.VehicleYearStatsData;
    kpiYear: number;
    kpiFiscalAvailable: boolean;
}>();

const fiscalCaption = computed<string>(() =>
    props.kpiFiscalAvailable
        ? `année ${props.kpiYear}`
        : `Règles fiscales ${props.kpiYear} non implémentées`,
);

const actualTaxValue = computed<string>(() =>
    props.kpiFiscalAvailable ? formatEur(props.kpiStats.actualTax) : '—',
);

const fullYearTaxValue = computed<string>(() =>
    props.kpiFiscalAvailable ? formatEur(props.kpiStats.fullYearTax) : '—',
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
            tone="slate"
            :value="`${props.kpiStats.daysUsed} j`"
            label="Jours d'utilisation"
            :caption="`année ${props.kpiYear}`"
        >
            <template #icon>
                <Calendar :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="emerald"
            :value="actualTaxValue"
            label="Taxe réelle"
            :caption="fiscalCaption"
        >
            <template #icon>
                <Receipt :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="fullYearTaxValue"
            label="Coût plein"
            :caption="fiscalCaption"
        >
            <template #icon>
                <Coins :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>
    </div>
</template>
