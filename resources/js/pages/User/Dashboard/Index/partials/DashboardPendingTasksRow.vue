<script setup lang="ts">
import { FileClock, Receipt } from 'lucide-vue-next';

defineProps<{
    tasks: App.Data.User.Dashboard.DashboardPendingTasksData;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-10">
        <article
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                <FileClock :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex flex-col gap-0.5 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Déclarations en attente
                </p>
                <p class="font-mono text-3xl font-semibold tabular-nums text-slate-900 leading-none">
                    {{ tasks.pendingDeclarations }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    À soumettre à la DGFiP
                </p>
            </div>
        </article>

        <article
            class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4"
        >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                <Receipt :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex flex-col gap-0.5 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Factures en attente
                </p>
                <p class="font-mono text-3xl font-semibold tabular-nums text-slate-900 leading-none">
                    {{ tasks.pendingInvoices }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    À envoyer aux entreprises clientes
                </p>
            </div>
        </article>
    </div>
</template>
