<script setup lang="ts">
/**
 * Aperçu fiscal compact pour le drawer planning (chantier UX-Loc).
 *
 * Affiche une **ligne récap unique** repliable :
 *   « TAXES INDUITES &nbsp;5 j · 0,00 € ▾ »
 *
 * Le détail (exemptions, breakdown CO₂/polluants, total) s'affiche
 * inline en dessous quand la ligne est dépliée.
 *
 * Sémantique : calcul standalone du contrat · la durée du contrat
 * seule qualifie LCD vs LLD (pas de cumul annuel).
 */
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    preview: App.Data.User.Fiscal.FiscalPreviewData | null;
    loading: boolean;
    /** Année qui pilote l'affichage (réservée pour usage futur). */
    year: number;
}>();

const isOpen = ref<boolean>(false);

function toggle(): void {
    isOpen.value = !isOpen.value;
}
</script>

<template>
    <div class="rounded-lg border border-blue-200 bg-blue-50/40">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span class="eyebrow text-blue-700">Taxes induites</span>

            <span class="inline-flex items-center gap-2">
                <span
                    v-if="loading"
                    class="text-xs text-slate-500"
                >
                    Calcul…
                </span>
                <template v-else-if="preview">
                    <span class="font-mono text-sm text-slate-700">
                        {{ preview.daysCount }} j
                    </span>
                    <span class="text-slate-300">·</span>
                    <span class="font-mono text-sm font-semibold text-slate-900">
                        {{ formatEur(preview.breakdown.totalDue, 2) }}
                    </span>
                </template>
                <ChevronDown
                    :size="14"
                    :stroke-width="1.75"
                    :class="['text-blue-600 transition-transform duration-[120ms] ease-out', isOpen ? 'rotate-180' : 'rotate-0']"
                    aria-hidden="true"
                />
            </span>
        </button>

        <div
            v-if="isOpen && preview"
            class="border-t border-blue-200 px-3 py-3"
        >
            <div class="flex flex-col gap-1.5 text-sm">
                <div
                    v-if="preview.breakdown.appliedExemptions.length > 0"
                    class="flex flex-col gap-1 text-xs text-emerald-700"
                >
                    <p
                        v-for="exemption in preview.breakdown.appliedExemptions"
                        :key="exemption.ruleCode"
                        class="rounded-md bg-emerald-50 px-2 py-1"
                    >
                        ✓ {{ exemption.reason }}
                        <span class="font-mono text-[10px] text-emerald-600">
                            ({{ exemption.ruleCode }})
                        </span>
                    </p>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">
                        Taxe CO₂ ({{ preview.breakdown.co2Method }})
                    </span>
                    <span class="font-mono text-slate-900">
                        {{ formatEur(preview.breakdown.co2Due, 2) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Taxe polluants</span>
                    <span class="font-mono text-slate-900">
                        {{ formatEur(preview.breakdown.pollutantsDue, 2) }}
                    </span>
                </div>
                <div
                    class="mt-1 flex justify-between border-t border-blue-200 pt-2 text-base"
                >
                    <span class="font-medium text-slate-900">Total</span>
                    <span class="font-mono font-semibold text-slate-900">
                        {{ formatEur(preview.breakdown.totalDue, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
