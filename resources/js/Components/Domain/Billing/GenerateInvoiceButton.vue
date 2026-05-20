<script setup lang="ts">
/**
 * Actions for a month cell in the company billing recap.
 * Renders one of three states: Generate, View, View + Regenerate (with modal).
 */
import { Link, router } from '@inertiajs/vue3';
import { Eye, FileText, Loader2, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import { useInvoiceRegeneration } from '@/Composables/Billing/useInvoiceRegeneration';
import {
    generate as invoicesGenerateRoute,
    show as invoicesShowRoute,
} from '@/routes/user/invoices';

const props = defineProps<{
    companyId: number;
    year: number;
    month: number;
    daysUsed: number;
    hasMissingPricing: boolean;
    /** Current civil year/month used as a UI guard against future/current month invoicing. */
    currentRealYear: number;
    currentRealMonth: number;
    /** Set iff an invoice already exists for this month. */
    existingInvoiceId?: number | null;
    existingInvoiceNumber?: string | null;
    /** Snapshot frozen at issuance; compared to current values for divergence. */
    existingInvoiceTotalCents?: number | null;
    existingInvoicedDaysUsed?: number | null;
    /** Dynamically recomputed HT total. */
    currentTotalCents?: number | null;
}>();

const processing = ref<boolean>(false);
const regenerateModalOpen = ref<boolean>(false);

// Stays on the company tab after regeneration rather than redirecting to the new invoice.
const { regenerating, regenerate: triggerRegeneration } = useInvoiceRegeneration({
    redirectTarget: 'company-tab',
    onFinish: () => {
        regenerateModalOpen.value = false;
    },
});

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

/** True iff the month is strictly before the current civil month. */
const isPastMonth = computed<boolean>(
    () => props.year < props.currentRealYear
        || (props.year === props.currentRealYear && props.month < props.currentRealMonth),
);

const generateDisabled = computed<boolean>(
    () => processing.value
        || props.daysUsed === 0
        || props.hasMissingPricing
        || !isPastMonth.value,
);

const generateTooltip = computed<string>(() => {
    if (props.hasMissingPricing) {
        return 'Tarif annuel manquant. Renseignez les tarifs sur les fiches véhicule.';
    }

    if (props.daysUsed === 0) {
        return 'Aucune utilisation ce mois-ci.';
    }

    return 'Générer l\'annexe';
});

const viewTooltip = computed<string>(
    () => `Voir l'annexe ${props.existingInvoiceNumber ?? ''}`.trim(),
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

    triggerRegeneration(props.existingInvoiceId);
}
</script>

<template>
    <div class="inline-flex items-center gap-1.5">
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
            <template #content>Régénérer l'annexe avec les données actuelles</template>
        </Tooltip>

        <Tooltip v-if="!hasExisting && isPastMonth" max-width="18rem">
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
            :title="`Régénérer l'annexe ${existingInvoiceNumber} ?`"
            :message="`L'annexe actuelle sera remplacée par une nouvelle, calculée avec les données du périmètre actuel. Le numéro d'annexe changera. L'opération est irréversible.`"
            confirm-label="Régénérer"
            :loading="regenerating"
            @confirm="regenerate"
        />
    </div>
</template>
