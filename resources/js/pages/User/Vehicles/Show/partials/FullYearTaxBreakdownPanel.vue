<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';
import {
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

const breakdown = computed(() => props.stats.fullYearTaxBreakdown);
</script>

<template>
    <Card>
        <template #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Détail du Coût plein {{ props.stats.fiscalYear }}
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Calcul théorique pour 100 % d'utilisation
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-5">
            <!-- Section CO₂ -->
            <section class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >
                        Taxe CO₂
                    </span>
                    <Badge tone="blue">
                        {{ homologationMethodLabel[breakdown.co2Method] }}
                    </Badge>
                </div>
                <p class="font-mono text-base font-semibold text-slate-900">
                    {{ formatEur(breakdown.co2FullYearTariff) }}
                </p>
            </section>

            <!-- Section Polluants -->
            <section class="flex flex-col gap-2 border-t border-slate-100 pt-4">
                <div class="flex items-center justify-between gap-2">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >
                        Taxe polluants
                    </span>
                    <Badge tone="amber">
                        {{ pollutantCategoryLabel[breakdown.pollutantCategory] }}
                    </Badge>
                </div>
                <p class="font-mono text-base font-semibold text-slate-900">
                    {{ formatEur(breakdown.pollutantsFullYearTariff) }}
                </p>
            </section>

            <!-- Exonérations / abattements (si présents) -->
            <section
                v-if="breakdown.exemptionReasons.length > 0"
                class="flex flex-col gap-2 border-t border-slate-100 pt-4"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Exonérations applicables
                </span>
                <ul class="flex flex-col gap-1 text-sm text-slate-700">
                    <li
                        v-for="reason in breakdown.exemptionReasons"
                        :key="reason"
                        class="flex items-start gap-2"
                    >
                        <span class="text-emerald-600">✓</span>
                        <span>{{ reason }}</span>
                    </li>
                </ul>
            </section>

            <!-- Total final mis en valeur -->
            <section
                class="flex items-center justify-between gap-2 rounded-lg bg-slate-900 px-4 py-3"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-300 uppercase"
                >
                    Total {{ props.stats.fiscalYear }}
                </span>
                <span class="font-mono text-lg font-semibold text-white">
                    {{ formatEur(breakdown.total) }}
                </span>
            </section>
        </div>

        <template
            v-if="breakdown.appliedRuleCodes.length > 0"
            #footer
        >
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-400">Règles appliquées :</span>
                <code
                    v-for="code in breakdown.appliedRuleCodes"
                    :key="code"
                    class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600"
                >
                    {{ code }}
                </code>
            </div>
        </template>
    </Card>
</template>
