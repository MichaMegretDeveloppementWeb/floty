<script setup lang="ts">
/**
 * Panel « Factures en attente » du Dashboard (Phase 13 D5.15).
 *
 * Affiche jusqu'à 5 items triés par année croissante puis code court
 * entreprise. Si plus de 5 items en attente, un lien « Voir les N
 * autres » pointe vers la page Index Factures.
 *
 * État vide · message explicite (« Aucune facture mensuelle en
 * attente · les recettes locatives sont à jour ») plutôt qu'un
 * « 0 » anxiogène.
 */
import { Receipt } from 'lucide-vue-next';
import { computed } from 'vue';
import { index as invoicesIndex } from '@/routes/user/invoices';
import PendingInvoiceRow from './PendingInvoiceRow.vue';

type Tasks = App.Data.User.Dashboard.DashboardPendingTasksData;

const props = defineProps<{
    /** Nombre de lignes (entreprise, année) en attente. Pilote « Voir les N autres ». */
    count: Tasks['pendingInvoicesCount'];
    /** Somme des factures mensuelles à générer toutes lignes confondues. Pilote le compteur du header. */
    monthlyTotal: Tasks['pendingInvoicesMonthlyTotal'];
    items: Tasks['pendingInvoices'];
}>();

const remainingCount = computed<number>(() =>
    Math.max(0, props.count - props.items.length),
);

const indexUrl = computed<string>(() => invoicesIndex().url);
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
    >
        <header class="flex items-center gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
            >
                <Receipt :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex flex-col gap-0.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Factures en attente
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
        </header>

        <ul v-if="items.length > 0" class="flex flex-col gap-2">
            <li v-for="item in items" :key="`${item.companyId}-${item.fiscalYear}`">
                <PendingInvoiceRow :item="item" />
            </li>
        </ul>

        <p v-else class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-600">
            Aucune facture mensuelle en attente. Les recettes locatives
            sont à jour pour vos entreprises clientes.
        </p>

        <a
            v-if="remainingCount > 0"
            :href="indexUrl"
            class="text-xs font-medium text-slate-600 underline decoration-slate-300 underline-offset-2 transition-colors duration-[120ms] ease-out hover:text-slate-900 hover:decoration-slate-600"
        >
            Voir les {{ remainingCount }} autre{{ remainingCount > 1 ? 's' : '' }} entreprise{{ remainingCount > 1 ? 's' : '' }} →
        </a>
    </article>
</template>
