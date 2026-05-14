<script setup lang="ts">
/**
 * Ligne « factures en attente » sur le Dashboard (Phase 13 D5.15).
 *
 * Affiche · contexte entreprise + année + nombre de factures
 * mensuelles à générer. Toute la ligne est cliquable vers la page
 * Index Factures filtrée sur l'entreprise et l'année · l'utilisateur
 * y trouve les boutons individuels de génération mensuelle.
 */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

type Item = App.Data.User.Dashboard.DashboardPendingInvoiceItemData;

const props = defineProps<{ item: Item }>();

const targetUrl = computed<string>(() =>
    `/app/invoices?companyId=${props.item.companyId}&year=${props.item.fiscalYear}`,
);

const countLabel = computed<string>(() => {
    const n = props.item.missingInvoicesCount;

    return `${n} facture${n > 1 ? 's' : ''}`;
});

function open(): void {
    router.visit(targetUrl.value);
}
</script>

<template>
    <button
        type="button"
        class="group flex w-full items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left transition-all duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
        @click="open"
    >
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <p class="truncate text-sm font-medium text-slate-900">
                {{ item.companyShortCode }} · {{ item.fiscalYear }}
            </p>
            <p class="truncate text-xs text-slate-500">
                {{ item.companyLegalName }}
            </p>
        </div>

        <span
            class="shrink-0 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-mono text-[10px] font-medium tabular-nums text-amber-700"
        >
            {{ countLabel }}
        </span>

        <span class="shrink-0 text-xs font-medium text-slate-600 group-hover:text-slate-900">
            Générer →
        </span>
    </button>
</template>
