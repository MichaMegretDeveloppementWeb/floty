<script setup lang="ts">
/**
 * Aperçu loyer compact pour le drawer planning (SC4 · 2026-05-18).
 *
 * Pendant non-fiscal de {@link FiscalPreviewCard} · même structure
 * « ligne récap repliable + détail inline ». Affiche le loyer NET
 * (après réductions) et expose le détail des réductions appliquées
 * dans la zone dépliée.
 *
 * Wording strict « Réductions appliquées · -XX € (-X,X %, libellé) »
 * conforme à la mémoire `feedback_wording_reductions_appliquees`.
 *
 * Le calcul est strictement équivalent à la facture finale ·
 * `OptimalRateBreakdown` par mois civil + `DiscountApplier`.
 */
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    preview: App.Data.User.Planning.RentalPreviewData | null;
    loading: boolean;
}>();

const isOpen = ref<boolean>(false);

function toggle(): void {
    isOpen.value = !isOpen.value;
}

const hasDiscount = computed<boolean>(
    () => props.preview !== null
        && (props.preview.discountCents ?? 0) > 0,
);

/**
 * Taux en pourcent (basisPoints → % avec 1 décimale max).
 * 1050 → 10,5 % · 1000 → 10 %
 */
const discountPercentLabel = computed<string>(() => {
    const bp = props.preview?.appliedDiscountBasisPoints ?? null;
    if (bp === null) {
        return '';
    }

    const pct = bp / 100;
    return pct % 1 === 0
        ? `${pct} %`
        : `${pct.toString().replace('.', ',')} %`;
});
</script>

<template>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50/40">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span class="eyebrow text-emerald-700">Loyer induit</span>

            <span class="inline-flex items-center gap-2">
                <span
                    v-if="loading"
                    class="text-xs text-slate-500"
                >
                    Calcul…
                </span>
                <template v-else-if="preview">
                    <template v-if="preview.hasMissingPricing">
                        <span class="text-xs text-amber-700">
                            Tarif manquant
                        </span>
                    </template>
                    <template v-else-if="preview.netTotalCents !== null">
                        <span class="font-mono text-sm text-slate-700">
                            {{ preview.daysCount }} j
                        </span>
                        <span class="text-slate-300">·</span>
                        <span class="font-mono text-sm font-semibold text-slate-900">
                            {{ formatEur(preview.netTotalCents / 100, 0) }}
                        </span>
                    </template>
                </template>
                <ChevronDown
                    :size="14"
                    :stroke-width="1.75"
                    :class="['text-emerald-600 transition-transform duration-[120ms] ease-out', isOpen ? 'rotate-180' : 'rotate-0']"
                    aria-hidden="true"
                />
            </span>
        </button>

        <div
            v-if="isOpen && preview"
            class="border-t border-emerald-200 px-3 py-3"
        >
            <div
                v-if="preview.hasMissingPricing"
                class="text-xs text-amber-700"
            >
                Tarif annuel non renseigné pour ce véhicule · rends-toi sur
                la fiche véhicule pour compléter la grille jour/semaine/mois.
            </div>
            <div
                v-else
                class="flex flex-col gap-1.5 text-sm"
            >
                <div class="flex justify-between">
                    <span class="text-slate-600">Loyer brut</span>
                    <span class="font-mono text-slate-900">
                        {{ formatEur((preview.grossTotalCents ?? 0) / 100, 0) }}
                    </span>
                </div>
                <div
                    v-if="hasDiscount"
                    class="flex justify-between text-emerald-700"
                >
                    <span>
                        Réductions appliquées
                        <span
                            v-if="preview.appliedDiscountLabel"
                            class="text-emerald-600"
                        >
                            ({{ discountPercentLabel }}, {{ preview.appliedDiscountLabel }})
                        </span>
                        <span v-else class="text-emerald-600">
                            ({{ discountPercentLabel }})
                        </span>
                    </span>
                    <span class="font-mono">
                        -{{ formatEur((preview.discountCents ?? 0) / 100, 0) }}
                    </span>
                </div>
                <div
                    class="mt-1 flex justify-between border-t border-emerald-200 pt-2 text-base"
                >
                    <span class="font-medium text-slate-900">Total net</span>
                    <span class="font-mono font-semibold text-slate-900">
                        {{ formatEur((preview.netTotalCents ?? 0) / 100, 0) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
