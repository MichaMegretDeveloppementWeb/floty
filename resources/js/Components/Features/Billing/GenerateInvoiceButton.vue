<script setup lang="ts">
/**
 * Bouton d'action sur une cellule mensuelle du récap entreprise.
 *
 * Trois états selon le contexte du couple (entreprise × année × mois) :
 *   1. **Facture existante** (`existingInvoiceNumber !== null`) →
 *      affiche un lien « Voir #YYYY-MM-NNNN » vers la fiche facture.
 *      Pas de regénération possible (immuabilité — cf. ADR-0008).
 *   2. **Mois facturable** → bouton « Générer » qui POST sur
 *      `invoices.generate`. Erreurs serveur (`InvoiceAlreadyExistsException`,
 *      `MissingPricingException`) sont rattrapées par le controller en
 *      `toast-error` ; ce composant n'a pas besoin de gestion défensive.
 *   3. **Mois non facturable** (`daysUsed === 0` ou `hasMissingPricing`)
 *      → bouton désactivé avec tooltip explicatif.
 */
import { Link, router } from '@inertiajs/vue3';
import { Eye, FileText, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { generate as invoicesGenerateRoute, show as invoicesShowRoute } from '@/routes/user/invoices';

const props = defineProps<{
    companyId: number;
    year: number;
    month: number;
    daysUsed: number;
    hasMissingPricing: boolean;
    /** Présent ssi une facture est déjà émise pour ce mois (immuable). */
    existingInvoiceId?: number | null;
    existingInvoiceNumber?: string | null;
}>();

const processing = ref<boolean>(false);

const hasExisting = computed<boolean>(
    () => props.existingInvoiceId !== null && props.existingInvoiceId !== undefined,
);

const disabled = computed<boolean>(
    () => processing.value || props.daysUsed === 0 || props.hasMissingPricing,
);

const tooltipReason = computed<string>(() => {
    if (props.hasMissingPricing) {
        return 'Tarif annuel manquant — renseignez les tarifs sur les fiches véhicule.';
    }

    if (props.daysUsed === 0) {
        return 'Aucune utilisation ce mois-ci.';
    }

    return 'Générer la facture';
});

function generate(): void {
    if (disabled.value) {
        return;
    }

    processing.value = true;

    router.post(
        invoicesGenerateRoute.url(),
        {
            company_id: props.companyId,
            year: props.year,
            month: props.month,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Link
        v-if="hasExisting && existingInvoiceId"
        :href="invoicesShowRoute.url({ invoice: existingInvoiceId })"
        :title="`Voir la facture ${existingInvoiceNumber}`"
        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 transition-colors duration-[120ms] hover:border-emerald-300 hover:bg-emerald-100"
    >
        <Eye :size="12" :stroke-width="1.75" />
        Voir #{{ existingInvoiceNumber }}
    </Link>
    <button
        v-else
        type="button"
        :disabled="disabled"
        :title="tooltipReason"
        :class="[
            'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors duration-[120ms]',
            disabled
                ? 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'
                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 cursor-pointer',
        ]"
        @click="generate"
    >
        <component
            :is="processing ? Loader2 : FileText"
            :size="12"
            :stroke-width="1.75"
            :class="processing && 'animate-spin'"
        />
        Générer
    </button>
</template>
