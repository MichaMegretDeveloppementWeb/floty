<script setup lang="ts">
/**
 * Ligne « factures en attente » sur le Dashboard (Phase 13 D5.15).
 *
 * Affiche · contexte entreprise + année + nombre de factures
 * mensuelles à générer. Toute la ligne est cliquable vers la fiche
 * entreprise sur l'onglet Facturation de l'année concernée · c'est
 * là que l'utilisateur lance la génération mensuelle · les factures
 * en attente n'existent pas encore dans la liste Index Factures.
 */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as companyShow } from '@/routes/user/companies';

type Item = App.Data.User.Dashboard.DashboardPendingInvoiceItemData;

const props = defineProps<{ item: Item }>();

const targetUrl = computed<string>(() =>
    companyShow(props.item.companyId, {
        query: { tab: 'billing', year: props.item.fiscalYear },
    }).url,
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
        class="group flex w-full cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left transition-all duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
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
