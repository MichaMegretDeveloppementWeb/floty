<script setup lang="ts">
/**
 * Actions sur une cellule mensuelle du récap entreprise (Phase 14.I+).
 *
 * Sépare clairement **mention informative** et **action utilisateur** :
 *   - **Chip « Données obsolètes »** (orange, non-cliquable) : signale
 *     que le périmètre contractuel a changé depuis l'émission de la
 *     facture. Purement décoratif — l'utilisateur ne peut rien cliquer
 *     dessus.
 *   - **Bouton « Régénérer »** (cliquable) : ouvre une modal de
 *     confirmation. Sur confirmation, supprime la facture existante
 *     puis en génère une nouvelle avec les données actuelles, en une
 *     seule transaction backend.
 *
 * Quatre états possibles :
 *   1. **Pas de facture, mois facturable** → bouton « Générer »
 *   2. **Pas de facture, mois non facturable** → bouton « Générer »
 *      désactivé avec tooltip explicatif
 *   3. **Facture existante, sans divergence** → lien vert
 *      « Voir #YYYY-MM-NNNN »
 *   4. **Facture existante, avec divergence** → lien « Voir » + chip
 *      mention « Données obsolètes » + bouton « Régénérer »
 */
import { Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Eye, FileText, Loader2, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import {
    generate as invoicesGenerateRoute,
    regenerate as invoicesRegenerateRoute,
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
        + 'Régénérez la facture pour la mettre à jour avec les données actuelles.'
    );
});

const generateDisabled = computed<boolean>(
    () => processing.value || props.daysUsed === 0 || props.hasMissingPricing,
);

const generateTooltip = computed<string>(() => {
    if (props.hasMissingPricing) {
        return 'Tarif annuel manquant — renseignez les tarifs sur les fiches véhicule.';
    }

    if (props.daysUsed === 0) {
        return 'Aucune utilisation ce mois-ci.';
    }

    return 'Générer la facture';
});

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

    router.post(
        invoicesRegenerateRoute.url({ invoice: props.existingInvoiceId }),
        {},
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
        <!-- État : facture existante, divergence détectée -->
        <template v-if="hasExisting && hasDivergence && existingInvoiceId">
            <span
                :title="divergenceTooltip"
                class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800"
            >
                <AlertTriangle :size="12" :stroke-width="1.75" />
                Données obsolètes
            </span>
            <Link
                :href="invoicesShowRoute.url({ invoice: existingInvoiceId })"
                :title="`Voir la facture ${existingInvoiceNumber}`"
                class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 transition-colors duration-[120ms] hover:border-emerald-300 hover:bg-emerald-100"
            >
                <Eye :size="12" :stroke-width="1.75" />
                Voir #{{ existingInvoiceNumber }}
            </Link>
            <button
                type="button"
                title="Régénérer la facture avec les données actuelles"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50 cursor-pointer"
                @click="regenerateModalOpen = true"
            >
                <RefreshCw :size="12" :stroke-width="1.75" />
                Régénérer
            </button>
        </template>

        <!-- État : facture existante, pas de divergence -->
        <Link
            v-else-if="hasExisting && existingInvoiceId"
            :href="invoicesShowRoute.url({ invoice: existingInvoiceId })"
            :title="`Voir la facture ${existingInvoiceNumber}`"
            class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 transition-colors duration-[120ms] hover:border-emerald-300 hover:bg-emerald-100"
        >
            <Eye :size="12" :stroke-width="1.75" />
            Voir #{{ existingInvoiceNumber }}
        </Link>

        <!-- État : pas de facture (générer ou désactivé) -->
        <button
            v-else
            type="button"
            :disabled="generateDisabled"
            :title="generateTooltip"
            :class="[
                'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors duration-[120ms]',
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
                :class="processing && 'animate-spin'"
            />
            Générer
        </button>

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
