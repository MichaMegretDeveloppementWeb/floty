<script setup lang="ts">
/**
 * "Obsolete data" alert banner on the invoice Show page.
 * Shows snapshot vs current diff and offers a regenerate button with
 * confirmation modal. Only renders when divergence.hasDivergence is true.
 */
import { AlertTriangle, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { useInvoiceRegeneration } from '@/Composables/Billing/useInvoiceRegeneration';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    invoiceId: number;
    invoiceNumber: string;
    divergence: App.Data.User.Invoice.InvoiceDivergenceData;
}>();

const modalOpen = ref<boolean>(false);

// After regeneration the invoice id changes; redirect to the new Show.
const { regenerating, regenerate: triggerRegeneration } = useInvoiceRegeneration({
    redirectTarget: 'show',
    onFinish: () => {
        modalOpen.value = false;
    },
});

function regenerate(): void {
    triggerRegeneration(props.invoiceId);
}
</script>

<template>
    <div class="flex items-start gap-3">
        <AlertTriangle class="mt-0.5 shrink-0 text-amber-600" :size="18" :stroke-width="1.75" />
        <div class="flex-1">
            <p class="text-sm font-semibold text-amber-900">
                Données obsolètes
            </p>
            <p class="mt-0.5 text-xs text-slate-600">
                Le périmètre contractuel a changé depuis l'émission de cette annexe.
                Émise sur
                <span class="font-mono tabular-nums">
                    {{ divergence.invoicedDaysUsed }} j / {{ formatEur(divergence.invoicedTotalCents / 100, 2) }}
                </span>.
                <template v-if="divergence.currentDaysUsed !== null && divergence.currentTotalCents !== null">
                    Recalcul actuel :
                    <span class="font-mono tabular-nums">
                        {{ divergence.currentDaysUsed }} j / {{ formatEur(divergence.currentTotalCents / 100, 2) }}
                    </span>.
                </template>
                <template v-else>
                    Le recalcul actuel est impossible (tarif annuel manquant pour un véhicule du mois).
                </template>
            </p>
        </div>
        <button
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50 cursor-pointer whitespace-nowrap"
            @click="modalOpen = true"
        >
            <RefreshCw class="shrink-0" :size="12" :stroke-width="1.75" />
            Régénérer
        </button>

        <ConfirmModal
            v-model:open="modalOpen"
            tone="default"
            :title="`Régénérer l'annexe ${invoiceNumber} ?`"
            :message="`L'annexe actuelle sera remplacée par une nouvelle, calculée avec les données du périmètre actuel. Le numéro d'annexe changera. L'opération est irréversible.`"
            confirm-label="Régénérer"
            :loading="regenerating"
            @confirm="regenerate"
        />
    </div>
</template>
