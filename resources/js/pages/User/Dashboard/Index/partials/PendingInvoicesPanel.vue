<script setup lang="ts">
/** Dashboard panel listing up to 5 pending monthly invoice annexes. */
import { Plus, Receipt } from 'lucide-vue-next';
import { ref } from 'vue';
import CompanyYearPickerModal from '@/Components/Domain/Company/CompanyYearPickerModal.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import PendingInvoiceRow from './PendingInvoiceRow.vue';

type Tasks = App.Data.User.Dashboard.DashboardPendingTasksData;

defineProps<{
    /** Total monthly invoices to generate; powers the header counter. */
    monthlyTotal: Tasks['pendingInvoicesMonthlyTotal'];
    items: Tasks['pendingInvoices'];
}>();

const pickerOpen = ref<boolean>(false);
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
    >
        <header class="flex flex-wrap items-center gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
            >
                <Receipt :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Annexes de factures à générer
                </p>
                <p class="text-sm text-slate-500">
                    <template v-if="monthlyTotal > 0">
                        <span class="font-mono font-medium tabular-nums text-slate-900">
                            {{ monthlyTotal }}
                        </span>
                        à générer pour les entreprises clientes
                    </template>
                    <template v-else>
                        À envoyer aux entreprises clientes
                    </template>
                </p>
            </div>
            <Button
                size="sm"
                variant="secondary"
                class="shrink-0"
                @click="pickerOpen = true"
            >
                <template #icon-left>
                    <Plus :size="13" :stroke-width="1.75" />
                </template>
                Générer une annexe
            </Button>
        </header>

        <ul v-if="items.length > 0" class="flex flex-col gap-2">
            <li v-for="item in items" :key="`${item.companyId}-${item.fiscalYear}`">
                <PendingInvoiceRow :item="item" />
            </li>
        </ul>

        <p v-else class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-600">
            Aucune annexe de facture en attente. Les recettes locatives
            sont à jour pour vos entreprises clientes.
        </p>

        <CompanyYearPickerModal
            v-model:open="pickerOpen"
            target="billing"
            default-year="current"
            title="Générer une annexe"
            description="Choisissez l'entreprise et l'exercice à facturer."
            submit-label="Ouvrir la facturation"
        />
    </article>
</template>
