<script setup lang="ts">
/**
 * Actions sur une cellule mensuelle du récap entreprise (Phase 14.I+).
 *
 * Trois états affichés :
 *   1. Pas de facture : bouton « Générer » (désactivé si non facturable)
 *   2. Facture existante sans divergence : lien « Voir »
 *   3. Facture existante avec divergence : lien « Voir » + bouton
 *      « Régénérer » (modal de confirmation, transaction cancel + create)
 *
 * La mention « Données obsolètes » ne vit plus dans ce composant : elle
 * est portée par un indicateur warning à côté du numéro de facture, dans
 * la colonne dédiée du récap (cf. MonthlyBillingBreakdownCard).
 */
import { Link, router } from '@inertiajs/vue3';
import { Eye, FileText, Loader2, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import {
    generate as invoicesGenerateRoute,
    regenerate as invoicesRegenerateRoute,
    show as invoicesShowRoute,
} from '@/routes/user/invoices';

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
     * Snapshot figé à l'émission. Comparé à `currentTotalCents` /
     * `daysUsed` pour détecter une divergence post-émission.
     */
    existingInvoiceTotalCents?: number | null;
    existingInvoicedDaysUsed?: number | null;
    /** Total HT recalculé dynamiquement (peut différer du snapshot). */
    currentTotalCents?: number | null;
}>();

const processing = ref<boolean>(false);
const regenerating = ref<boolean>(false);
const regenerateModalOpen = ref<boolean>(false);

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

const generateDisabled = computed<boolean>(
    () => processing.value || props.daysUsed === 0 || props.hasMissingPricing,
);

const generateTooltip = computed<string>(() => {
    if (props.hasMissingPricing) {
        return 'Tarif annuel manquant. Renseignez les tarifs sur les fiches véhicule.';
    }

    if (props.daysUsed === 0) {
        return 'Aucune utilisation ce mois-ci.';
    }

    return 'Générer la facture';
});

const viewTooltip = computed<string>(
    () => `Voir la facture ${props.existingInvoiceNumber ?? ''}`.trim(),
);

function generate(): void {
    if (generateDisabled.value) {
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

function regenerate(): void {
    if (!props.existingInvoiceId) {
        return;
    }

    regenerating.value = true;

    // Le composant est utilisé depuis la fiche entreprise (onglet
    // Facturation) ; on préfère y rester après régénération plutôt que
    // d'aller voir la nouvelle facture.
    router.post(
        invoicesRegenerateRoute.url({ invoice: props.existingInvoiceId }),
        { redirect_target: 'company-tab' },
        {
            preserveScroll: true,
            onFinish: () => {
                regenerating.value = false;
                regenerateModalOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="inline-flex items-center gap-1.5">
        <!-- État : facture existante (avec ou sans divergence) -->
        <Tooltip v-if="hasExisting && existingInvoiceId">
            <Link
                :href="invoicesShowRoute.url({ invoice: existingInvoiceId })"
                class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 transition-colors duration-[120ms] hover:border-emerald-300 hover:bg-emerald-100 whitespace-nowrap"
            >
                <Eye class="shrink-0" :size="12" :stroke-width="1.75" />
                Voir
            </Link>
            <template #content>{{ viewTooltip }}</template>
        </Tooltip>
        <Tooltip v-if="hasExisting && hasDivergence && existingInvoiceId">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50 cursor-pointer whitespace-nowrap"
                @click="regenerateModalOpen = true"
            >
                <RefreshCw class="shrink-0" :size="12" :stroke-width="1.75" />
                Régénérer
            </button>
            <template #content>Régénérer la facture avec les données actuelles</template>
        </Tooltip>

        <!-- État : pas de facture (générer ou désactivé) -->
        <Tooltip v-if="!hasExisting" max-width="18rem">
            <button
                type="button"
                :disabled="generateDisabled"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors duration-[120ms] whitespace-nowrap',
                    generateDisabled
                        ? 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 cursor-pointer',
                ]"
                @click="generate"
            >
                <component
                    :is="processing ? Loader2 : FileText"
                    :size="12"
                    :stroke-width="1.75"
                    :class="['shrink-0', processing && 'animate-spin']"
                />
                Générer
            </button>
            <template #content>{{ generateTooltip }}</template>
        </Tooltip>

        <ConfirmModal
            v-model:open="regenerateModalOpen"
            tone="default"
            :title="`Régénérer la facture ${existingInvoiceNumber} ?`"
            :message="`La facture actuelle sera remplacée par une nouvelle, calculée avec les données du périmètre actuel. Le numéro de facture changera. L'opération est irréversible.`"
            confirm-label="Régénérer"
            :loading="regenerating"
            @confirm="regenerate"
        />
    </div>
</template>
