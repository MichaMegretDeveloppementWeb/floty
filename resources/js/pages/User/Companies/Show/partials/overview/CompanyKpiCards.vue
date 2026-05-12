<script setup lang="ts">
/**
 * Rangée de 4 KPIs **Présent** · reflète l'année calendaire courante
 * uniquement (chantier η Phase 1, doctrine temporelle).
 *
 * **Renomme `CompanyLifetimeStatsCards` (lifetime tous exercices) →
 * `CompanyKpiCards` (année courante seule)**. Cohérence avec doctrine
 * HD : KPIs en haut = présent, Historique en dessous = passé.
 *
 * Spécificités :
 *   - Si `kpiFiscalAvailable === false` (règles fiscales pas codées
 *     pour l'année courante), la KPI Taxes affiche un `·` neutre avec
 *     caption « Règles {YYYY} non implémentées » (cf. doctrine HD6 :
 *     « pas de règles ≠ pas de données »).
 *   - Montant loyer : somme des 12 facturations mensuelles de l'année,
 *     `null` si au moins un véhicule de la flotte a un pricing manquant.
 */
import { Banknote, Calendar, FileText, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    kpiStats: App.Data.User.Company.CompanyYearStatsData;
    kpiYear: number;
    kpiFiscalAvailable: boolean;
}>();

const taxValue = computed<string>(() => {
    if (!props.kpiFiscalAvailable) {
        return '·';
    }

    return formatEur(props.kpiStats.annualTaxDue);
});

const taxCaption = computed<string>(() => {
    if (!props.kpiFiscalAvailable) {
        return `Règles fiscales ${props.kpiYear} non implémentées`;
    }

    return `année ${props.kpiYear}`;
});

const rentCaption = computed<string>(() => {
    if (props.kpiStats.rent === null) {
        return 'Tarif véhicule manquant';
    }

    return `année ${props.kpiYear}`;
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
            tone="slate"
            :value="`${props.kpiStats.daysUsed} j`"
            label="Jours d'usage"
            :caption="`année ${props.kpiYear}`"
        >
            <template #icon>
                <Calendar :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="props.kpiStats.contractsCount"
            label="Locations"
            :caption="`actifs en ${props.kpiYear}`"
        >
            <template #icon>
                <FileText :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="emerald"
            :value="taxValue"
            label="Taxes dues"
            :caption="taxCaption"
        >
            <template #icon>
                <Receipt :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="props.kpiStats.rent !== null ? formatEur(props.kpiStats.rent) : '·'"
            label="Montant loyer"
            :caption="rentCaption"
        >
            <template #icon>
                <Banknote :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>
    </div>
</template>
