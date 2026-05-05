<script setup lang="ts">
import { computed } from 'vue';
import { daysInYear as daysInYearOf } from '@/Utils/date/daysInYear';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    preview: App.Data.User.Fiscal.FiscalPreviewData | null;
    loading: boolean;
    /** Année qui pilote le diviseur 365/366 du compteur cumul (chantier J). */
    year: number;
}>();

const daysInYear = computed<number>(() => daysInYearOf(props.year));
</script>

<template>
    <div class="rounded-lg border border-blue-200 bg-blue-50/40 p-3">
        <p class="eyebrow mb-1 text-blue-700">
            Taxes induites par cette attribution
        </p>

        <div v-if="loading" class="text-xs text-slate-500">
            Calcul en cours…
        </div>

        <div v-else-if="preview" class="flex flex-col gap-1.5 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-600">
                    Nouveaux jours pour ce couple
                </span>
                <span class="font-mono text-slate-900">
                    +{{ preview.newDaysCount }} j
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Cumul futur</span>
                <span class="font-mono text-slate-900">
                    {{ preview.futureCumul }} j / {{ daysInYear }}
                </span>
            </div>
            <div
                v-if="preview.after.appliedExemptions.length > 0"
                class="mt-1 flex flex-col gap-1 text-xs text-emerald-700"
            >
                <p
                    v-for="exemption in preview.after.appliedExemptions"
                    :key="exemption.ruleCode"
                    class="rounded-md bg-emerald-50 px-2 py-1"
                >
                    ✓ {{ exemption.reason }}
                    <span class="font-mono text-[10px] text-emerald-600">
                        ({{ exemption.ruleCode }})
                    </span>
                </p>
            </div>
            <div
                class="mt-1 flex justify-between border-t border-blue-200 pt-2"
            >
                <span class="text-slate-600">
                    Taxe CO₂ ({{ preview.after.co2Method }})
                </span>
                <span class="font-mono text-slate-900">
                    {{ formatEur(preview.after.co2Due, 2) }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Taxe polluants</span>
                <span class="font-mono text-slate-900">
                    {{ formatEur(preview.after.pollutantsDue, 2) }}
                </span>
            </div>
            <div
                class="mt-1 flex justify-between border-t border-blue-200 pt-2 text-base"
            >
                <span class="font-medium text-slate-900">
                    Total annuel du couple
                </span>
                <span class="font-mono font-semibold text-slate-900">
                    {{ formatEur(preview.after.totalDue, 2) }}
                </span>
            </div>
            <div
                v-if="preview.incrementalDue > 0"
                class="flex justify-between text-xs text-slate-500"
            >
                <span>dont induit par ces dates</span>
                <span class="font-mono">
                    +{{ formatEur(preview.incrementalDue, 2) }}
                </span>
            </div>
        </div>
    </div>
</template>
