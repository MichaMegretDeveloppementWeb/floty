<script setup lang="ts">
/**
 * Bouton d'action sur une cellule mensuelle du récap entreprise.
 *
 * Quatre états selon le contexte du couple (entreprise × année × mois) :
 *   1. **Facture existante figée** (snapshot identique au recalcul) →
 *      lien vert « Voir #YYYY-MM-NNNN » vers la fiche facture.
 *   2. **Facture existante divergente** (un contrat ajouté/modifié/
 *      supprimé sur le mois après émission) → lien vert + chip orange
 *      « Données ont changé » qui ouvre une modal d'annulation. La
 *      facture étant immuable, on annule pour pouvoir regénérer.
 *   3. **Mois facturable** → bouton « Générer » qui POST
 *      sur `invoices.generate`. Erreurs serveur (`InvoiceAlreadyExistsException`,
 *      `MissingPricingException`) sont rattrapées par le controller en
 *      `toast-error`.
 *   4. **Mois non facturable** (`daysUsed === 0` ou `hasMissingPricing`)
 *      → bouton désactivé avec tooltip explicatif.
 */
import { Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Eye, FileText, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import {
    destroy as invoicesDestroyRoute,
    generate as invoicesGenerateRoute,
    show as invoicesShowRoute,
} from '@/routes/user/invoices';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    companyId: number;
    year: number;
    month: number;
    daysUsed: number;
    hasMissingPricing: boolean;
    /** Présent ssi une facture est déjà émise pour ce mois (immuable). */
    existingInvoiceId?: number | null;
    existingInvoiceNumber?: string | null;
    /**
     * Snapshot figé à l'émission de la facture. La comparaison avec
     * `daysUsed` / (computed `currentTotalCents` côté parent — ici on
     * récupère via `existingInvoiceTotalCents` côté backend) permet de
     * détecter une divergence post-émission.
     */
    existingInvoiceTotalCents?: number | null;
    existingInvoicedDaysUsed?: number | null;
    /** Total HT recalculé dynamiquement (peut différer du snapshot). */
    currentTotalCents?: number | null;
}>();

const processing = ref<boolean>(false);
const cancelling = ref<boolean>(false);
const cancelModalOpen = ref<boolean>(false);

const hasExisting = computed<boolean>(
    () => props.existingInvoiceId !== null && props.existingInvoiceId !== undefined,
);

const hasDivergence = computed<boolean>(() => {
    if (
        !hasExisting.value
        || props.existingInvoicedDaysUsed === null
        || props.existingInvoicedDaysUsed === undefined
    ) {
        return false;
    }

    if (props.daysUsed !== props.existingInvoicedDaysUsed) {
        return true;
    }

    if (
        props.currentTotalCents !== null
        && props.currentTotalCents !== undefined
        && props.existingInvoiceTotalCents !== null
        && props.existingInvoiceTotalCents !== undefined
        && props.currentTotalCents !== props.existingInvoiceTotalCents
    ) {
        return true;
    }

    return false;
});

const divergenceTooltip = computed<string>(() => {
    if (!hasDivergence.value) {
        return '';
    }

    const invoicedDays = props.existingInvoicedDaysUsed ?? 0;
    const invoicedTotal = formatEur((props.existingInvoiceTotalCents ?? 0) / 100, 2);
    const currentTotal = formatEur((props.currentTotalCents ?? 0) / 100, 2);

    return (
        `Facture émise sur ${invoicedDays} j / ${invoicedTotal}. `
        + `Recalcul actuel : ${props.daysUsed} j / ${currentTotal}. `
        + 'La facture étant figée, annulez-la pour pouvoir regénérer.'
    );
});

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

function cancel(): void {
    if (!props.existingInvoiceId) {
        return;
    }

    cancelling.value = true;

    router.delete(
        invoicesDestroyRoute.url({ invoice: props.existingInvoiceId }),
        {
            preserveScroll: true,
            onFinish: () => {
                cancelling.value = false;
                cancelModalOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="inline-flex items-center gap-1.5">
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
            v-if="hasDivergence"
            type="button"
            :title="divergenceTooltip"
            class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 transition-colors duration-[120ms] hover:border-amber-300 hover:bg-amber-100 cursor-pointer"
            @click="cancelModalOpen = true"
        >
            <AlertTriangle :size="12" :stroke-width="1.75" />
            Données ont changé
        </button>

        <button
            v-if="!hasExisting"
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

        <ConfirmModal
            v-model:open="cancelModalOpen"
            tone="danger"
            :title="`Annuler la facture ${existingInvoiceNumber} ?`"
            :message="`Cette action supprime définitivement la facture et son fichier PDF. Vous pourrez ensuite regénérer une facture avec les données actuelles. L'opération est irréversible.`"
            confirm-label="Annuler la facture"
            :loading="cancelling"
            @confirm="cancel"
        />
    </div>
</template>
