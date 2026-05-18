<script setup lang="ts">
/**
 * Aperçu loyer compact pour le drawer planning (SC4 · 2026-05-18,
 * refonte design SC7 · 2026-05-18).
 *
 * Design Stripe-like (mockup B) · pendant non-fiscal de
 * {@link FiscalPreviewCard} · même squelette, mêmes tokens. Aucune
 * couleur dominante de fond (retiré bg-emerald-50/40 + border-emerald-200) ·
 * carte blanche border-slate-200, header avec border-bottom slate-100,
 * eyebrow uppercase 11px slate-500, valeurs mono slate-900.
 *
 * Le wording « Réductions appliquées · -X € (-X,X %, libellé) » conforme
 * à la mémoire `feedback_wording_reductions_appliquees`. La ligne
 * réduction reste teintée emerald-700 (texte uniquement) pour conserver
 * un signal positif fort, mais SANS fond ni encart coloré.
 *
 * Calcul strictement équivalent à la facture finale ·
 * `OptimalRateBreakdown` par mois civil + `DiscountApplier`.
 */
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { formatEur } from '@/Utils/format/formatEur';

/**
 * Libellés courts FR pour les 12 mois (1=jan, 12=déc) · utilisés sur la
 * ligne « Nouveau total · Juin 1 350 € · Juil 980 € » (SC8 · 2026-05-18).
 * Format spécifique à cet affichage compact · pas de mutualisation
 * (cf. doctrine `chargement-strict-par-ecran`).
 */
const MONTH_LABELS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

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
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors duration-[120ms] ease-out hover:bg-slate-50"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">
                Loyer induit
            </span>

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
                        <span class="font-mono text-sm text-slate-500 tabular-nums">
                            {{ preview.daysCount }} j
                        </span>
                        <span class="text-slate-300">·</span>
                        <span class="font-mono text-sm font-semibold text-slate-900 tabular-nums">
                            {{ formatEur(preview.netTotalCents / 100, 0) }}
                        </span>
                    </template>
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
            <div
                v-if="preview.hasMissingPricing"
                class="rounded-md bg-slate-50 px-2.5 py-2 text-xs text-amber-700"
            >
                Tarif annuel non renseigné pour ce véhicule · rends-toi sur
                la fiche véhicule pour compléter la grille jour/semaine/mois.
            </div>
            <div
                v-else
                class="flex flex-col text-sm"
            >
                <div class="flex justify-between border-b border-slate-100 py-2.5">
                    <span class="text-slate-600">Loyer brut</span>
                    <span class="font-mono text-slate-900 tabular-nums">
                        {{ formatEur((preview.grossTotalCents ?? 0) / 100, 0) }}
                    </span>
                </div>
                <div
                    v-if="hasDiscount"
                    class="flex justify-between border-b border-slate-100 py-2.5"
                >
                    <span class="text-emerald-700">
                        Réductions appliquées
                        <span
                            v-if="preview.appliedDiscountLabel"
                            class="ml-1 text-xs text-slate-400"
                        >
                            {{ discountPercentLabel }} · {{ preview.appliedDiscountLabel }}
                        </span>
                        <span v-else class="ml-1 text-xs text-slate-400">
                            {{ discountPercentLabel }}
                        </span>
                    </span>
                    <span class="font-mono text-emerald-700 tabular-nums">
                        −{{ formatEur((preview.discountCents ?? 0) / 100, 0) }}
                    </span>
                </div>
                <div class="flex justify-between pt-3">
                    <span class="font-medium text-slate-900">Total net</span>
                    <span class="font-mono font-semibold text-slate-900 tabular-nums">
                        {{ formatEur((preview.netTotalCents ?? 0) / 100, 0) }}
                    </span>
                </div>
                <!-- SC8 (2026-05-18) · impact mois par mois sur la facture de
                     l'entreprise · pour chaque mois civil touché, affiche le
                     nouveau total mensuel après ajout de cette location.
                     Format compact `Juin 1 350 € · Juil 980 €` (entier sans
                     centimes pour cohérence avec le planning). -->
                <div
                    v-if="preview.monthlyImpact && preview.monthlyImpact.length > 0"
                    class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-xs text-slate-500"
                >
                    <span class="text-[10px] font-semibold tracking-[0.1em] text-slate-400 uppercase">
                        Nouveau total
                    </span>
                    <template
                        v-for="(impact, idx) in preview.monthlyImpact"
                        :key="`${impact.year}-${impact.month}`"
                    >
                        <span v-if="idx > 0" class="text-slate-300">·</span>
                        <span class="inline-flex items-baseline gap-1">
                            <span class="text-slate-600">{{ MONTH_LABELS[impact.month - 1] }}</span>
                            <span class="font-mono text-slate-900 tabular-nums">
                                {{ formatEur(impact.newTotalCents / 100, 0) }}
                            </span>
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
