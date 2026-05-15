<script setup lang="ts">
/**
 * Panel « Déclarations en attente » du Dashboard (Phase 13 D5.15).
 *
 * Affiche jusqu'à 5 items triés par urgence (overdue puis année
 * croissante). Le compteur du header reflète le nombre total de
 * déclarations à soumettre · les items au-delà du top 5 sont à
 * préparer depuis l'onglet Fiscalité de chaque fiche entreprise
 * (les déclarations `Untouched` n'existent pas dans l'Index
 * Déclarations).
 *
 * État vide · message explicite et apaisant (« Rien à générer pour
 * l'instant · les déclarations de l'année N seront à préparer à
 * partir du 01/01/N+1 ») plutôt qu'un « 0 » anxiogène.
 */
import { FileClock } from 'lucide-vue-next';
import PendingDeclarationRow from './PendingDeclarationRow.vue';

type Tasks = App.Data.User.Dashboard.DashboardPendingTasksData;

defineProps<{
    count: Tasks['pendingDeclarationsCount'];
    items: Tasks['pendingDeclarations'];
}>();
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
    >
        <header class="flex items-center gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
            >
                <FileClock :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex flex-col gap-0.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Déclarations en attente
                </p>
                <p class="text-sm text-slate-500">
                    <template v-if="count > 0">
                        <span class="font-mono font-medium tabular-nums text-slate-900">
                            {{ count }}
                        </span>
                        à soumettre à la DGFiP
                    </template>
                    <template v-else>
                        À soumettre à la DGFiP
                    </template>
                </p>
            </div>
        </header>

        <ul v-if="items.length > 0" class="flex flex-col gap-2">
            <li v-for="item in items" :key="`${item.companyId}-${item.fiscalYear}`">
                <PendingDeclarationRow :item="item" />
            </li>
        </ul>

        <p v-else class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-600">
            Rien à générer pour l'instant. Les déclarations d'un exercice
            sont à préparer à partir du 1er janvier suivant.
        </p>
    </article>
</template>
