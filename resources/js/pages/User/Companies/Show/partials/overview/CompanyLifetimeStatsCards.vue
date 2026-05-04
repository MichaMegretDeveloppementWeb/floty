<script setup lang="ts">
/**
 * Rangée de 4 KPIs cumulés « depuis le début » de l'entreprise — tous
 * exercices confondus. Pattern aligné avec `VehicleKpiCards` (StatCard
 * du design system, icônes Lucide, tone sémantique).
 *
 * Le 4ᵉ KPI Loyer est un placeholder — la facturation arrive en V1.2,
 * `rentTotal` est `null` jusqu'à là.
 */
import { Banknote, Calendar, FileText, Receipt } from 'lucide-vue-next';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    lifetime: App.Data.User.Company.CompanyLifetimeStatsData;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
            tone="slate"
            :value="`${props.lifetime.daysUsed} j`"
            label="Jours d'usage"
            caption="cumulés sur tous les exercices"
        >
            <template #icon>
                <Calendar :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="props.lifetime.contractsCount"
            label="Contrats"
            caption="signés depuis le début"
        >
            <template #icon>
                <FileText :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="emerald"
            :value="formatEur(props.lifetime.taxesGenerated)"
            label="Taxes générées"
            caption="cumulées sur tous les exercices"
        >
            <template #icon>
                <Receipt :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="props.lifetime.rentTotal !== null ? formatEur(props.lifetime.rentTotal) : '—'"
            label="Loyers cumulés"
            caption="V1.2 — facturation à venir"
        >
            <template #icon>
                <Banknote :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>
    </div>
</template>
