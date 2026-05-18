<script setup lang="ts">
/**
 * Aperçu fiscal compact pour le drawer planning (chantier UX-Loc · SC7
 * refonte design 2026-05-18).
 *
 * Design Stripe-like (mockup B) · carte blanche border-slate-200, header
 * avec border-bottom slate-100, eyebrow uppercase 11px slate-500, valeurs
 * mono slate-900 tabular-nums. Sobriété totale · aucune couleur dominante
 * de fond, juste des accents discrets (slate-100 pour les fonds neutres,
 * emerald-600 pour les exonérations qui restent un signal positif fort).
 *
 * Affiche une ligne récap unique repliable · clic sur le header toggle
 * un détail inline (exemptions + breakdown CO₂/polluants + total).
 *
 * Sémantique · calcul standalone du contrat · la durée du contrat seule
 * qualifie LCD vs LLD (pas de cumul annuel).
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
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors duration-[120ms] ease-out hover:bg-slate-50"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">
                Taxes induites
            </span>

            <span class="inline-flex items-center gap-2">
                <span
                    v-if="loading"
                    class="text-xs text-slate-500"
                >
                    Calcul…
                </span>
                <template v-else-if="preview">
                    <span class="font-mono text-sm text-slate-500 tabular-nums">
                        {{ preview.daysCount }} j
                    </span>
                    <span class="text-slate-300">·</span>
                    <span class="font-mono text-sm font-semibold text-slate-900 tabular-nums">
                        {{ formatEur(preview.breakdown.totalDue, 2) }}
                    </span>
                </template>
                <ChevronDown
                    :size="14"
                    :stroke-width="1.75"
                    :class="['text-slate-400 transition-transform duration-[120ms] ease-out', isOpen ? 'rotate-180' : 'rotate-0']"
                    aria-hidden="true"
                />
            </span>
        </button>

        <div
            v-if="isOpen && preview"
            class="border-t border-slate-100 px-4 py-3"
        >
            <div class="flex flex-col text-sm">
                <div
                    v-if="preview.breakdown.appliedExemptions.length > 0"
                    class="mb-2 flex flex-col gap-1"
                >
                    <p
                        v-for="exemption in preview.breakdown.appliedExemptions"
                        :key="exemption.ruleCode"
                        class="rounded-md bg-slate-50 px-2.5 py-1.5 text-xs text-slate-700"
                    >
                        <span class="font-medium text-emerald-700">✓</span>
                        {{ exemption.reason }}
                        <span class="ml-1 font-mono text-[10px] text-slate-400">
                            {{ exemption.ruleCode }}
                        </span>
                    </p>
                </div>
                <div class="flex justify-between border-b border-slate-100 py-2.5">
                    <span class="text-slate-600">
                        Taxe CO₂
                        <span class="ml-1 text-xs text-slate-400">{{ preview.breakdown.co2Method }}</span>
                    </span>
                    <span class="font-mono text-slate-900 tabular-nums">
                        {{ formatEur(preview.breakdown.co2Due, 2) }}
                    </span>
                </div>
                <div class="flex justify-between border-b border-slate-100 py-2.5">
                    <span class="text-slate-600">Taxe polluants</span>
                    <span class="font-mono text-slate-900 tabular-nums">
                        {{ formatEur(preview.breakdown.pollutantsDue, 2) }}
                    </span>
                </div>
                <div class="flex justify-between pt-3">
                    <span class="font-medium text-slate-900">Total</span>
                    <span class="font-mono font-semibold text-slate-900 tabular-nums">
                        {{ formatEur(preview.breakdown.totalDue, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
