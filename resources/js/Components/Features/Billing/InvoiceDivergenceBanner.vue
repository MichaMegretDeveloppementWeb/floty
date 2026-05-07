<script setup lang="ts">
/**
 * Bandeau d'alerte « Données obsolètes » pour la page Show facture
 * (Phase 14.I+). Affiche le différentiel snapshot vs réalité actuelle
 * et propose un bouton « Régénérer » qui ouvre une modal de
 * confirmation. Sur confirmation, supprime la facture courante puis
 * génère une nouvelle facture pour le même couple (entreprise × année
 * × mois) avec les données du périmètre actuel — en une transaction
 * backend.
 *
 * Le composant ne s'affiche que si `divergence.hasDivergence === true`.
 * Sur le détail facture, il est placé sous la ligne titre + bouton PDF.
 */
import { router } from '@inertiajs/vue3';
import { AlertTriangle, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { regenerate as invoicesRegenerateRoute } from '@/routes/user/invoices';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    invoiceId: number;
    invoiceNumber: string;
    divergence: App.Data.User.Invoice.InvoiceDivergenceData;
}>();

const regenerating = ref<boolean>(false);
const modalOpen = ref<boolean>(false);

function regenerate(): void {
    regenerating.value = true;

    router.post(
        invoicesRegenerateRoute.url({ invoice: props.invoiceId }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                regenerating.value = false;
                modalOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
        <AlertTriangle :size="20" :stroke-width="1.75" class="mt-0.5 shrink-0 text-amber-600" />
        <div class="flex-1">
            <p class="text-sm font-semibold text-amber-900">
                Données obsolètes
            </p>
            <p class="mt-1 text-xs text-amber-800">
                Le périmètre contractuel a changé depuis l'émission de cette facture.
                <span class="font-mono tabular-nums">
                    Émise sur {{ divergence.invoicedDaysUsed }} j / {{ formatEur(divergence.invoicedTotalCents / 100, 2) }}.
                    <template v-if="divergence.currentDaysUsed !== null && divergence.currentTotalCents !== null">
                        Recalcul actuel : {{ divergence.currentDaysUsed }} j / {{ formatEur(divergence.currentTotalCents / 100, 2) }}.
                    </template>
                    <template v-else>
                        Le recalcul actuel est impossible (tarif annuel manquant pour un véhicule du mois).
                    </template>
                </span>
            </p>
        </div>
        <button
            type="button"
            class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-900 transition-colors duration-[120ms] hover:border-amber-400 hover:bg-amber-100 cursor-pointer"
            @click="modalOpen = true"
        >
            <RefreshCw :size="14" :stroke-width="1.75" />
            Régénérer
        </button>

        <ConfirmModal
            v-model:open="modalOpen"
            tone="default"
            :title="`Régénérer la facture ${invoiceNumber} ?`"
            :message="`La facture actuelle sera remplacée par une nouvelle, calculée avec les données du périmètre actuel. Le numéro de facture changera. L'opération est irréversible.`"
            confirm-label="Régénérer"
            :loading="regenerating"
            @confirm="regenerate"
        />
    </div>
</template>
