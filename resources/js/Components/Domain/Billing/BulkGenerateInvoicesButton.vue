<script setup lang="ts">
/**
 * Bouton « Générer tout » de l'onglet Facturation d'une entreprise.
 *
 * Déclenche une génération en masse de toutes les annexes en attente pour
 * le couple (entreprise × année). Délègue intégralement la doctrine
 * d'exécution (séquence, best-effort, transactions) au
 * `BulkGenerateInvoicesAction` côté backend.
 *
 * Comportement ·
 *   1. Bouton visible ssi au moins 1 mois en attente sur l'année active.
 *   2. Modale de confirmation listant les mois qui seront générés.
 *   3. State `processing` pendant le POST · bouton désactivé · l'opération
 *      peut durer ~10-30 s sur année complète (rendu PDF séquentiel).
 *   4. Le rapport de fin (`bulkInvoiceReport`) revient via une shared prop
 *      Inertia. Il est consommé par `BulkInvoiceGenerationReportAlert`
 *      affiché juste en dessous · récap inline 7s avec fade-out.
 */
import { router } from '@inertiajs/vue3';
import { FileStack, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { bulkGenerate as invoicesBulkGenerateRoute } from '@/routes/user/invoices';
import { monthLabel } from '@/Utils/format/monthLabels';

const props = defineProps<{
    companyId: number;
    year: number;
    /**
     * Mois civils (1-12) en attente de facturation pour ce couple
     * (entreprise × année). Liste **dérivée côté parent** depuis
     * `monthlyBilling.entries` avec les mêmes critères que le
     * `PendingInvoicesResolver` backend (daysUsed > 0, pas de tarif
     * manquant, pas de facture existante, mois écoulé).
     */
    pendingMonths: readonly number[];
}>();

const processing = ref<boolean>(false);
const modalOpen = ref<boolean>(false);

const pendingCount = computed<number>(() => props.pendingMonths.length);

const buttonLabel = computed<string>(
    () => `Générer tout (${pendingCount.value})`,
);

const pendingMonthsLabels = computed<string>(() =>
    props.pendingMonths.map((m) => monthLabel(m)).join(', '),
);

const modalMessage = computed<string>(() => {
    const n = pendingCount.value;
    const subject = n > 1
        ? `${n} annexes de facture vont être générées`
        : '1 annexe de facture va être générée';

    return `${subject} pour ${props.year} (${pendingMonthsLabels.value}). Chaque mois est traité dans sa propre transaction · un échec sur un mois n'interrompt pas les autres. Confirmer le lancement ?`;
});

function submit(): void {
    if (processing.value || pendingCount.value === 0) {
        return;
    }

    processing.value = true;

    router.post(
        invoicesBulkGenerateRoute.url(),
        {
            company_id: props.companyId,
            year: props.year,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                modalOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <div v-if="pendingCount >= 1" class="inline-flex">
        <button
            type="button"
            :disabled="processing"
            :class="[
                'inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors duration-[120ms] whitespace-nowrap',
                processing
                    ? 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'
                    : 'border-slate-900 bg-slate-900 text-white hover:bg-slate-800 cursor-pointer',
            ]"
            @click="modalOpen = true"
        >
            <component
                :is="processing ? Loader2 : FileStack"
                :size="13"
                :stroke-width="1.75"
                :class="['shrink-0', processing && 'animate-spin']"
            />
            {{ buttonLabel }}
        </button>

        <ConfirmModal
            v-model:open="modalOpen"
            tone="default"
            :title="`Générer toutes les annexes en attente pour ${year}`"
            :message="modalMessage"
            confirm-label="Générer tout"
            :loading="processing"
            @confirm="submit"
        />
    </div>
</template>
