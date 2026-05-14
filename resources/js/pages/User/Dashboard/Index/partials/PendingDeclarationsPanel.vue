<script setup lang="ts">
/**
 * Panel « Déclarations en attente » du Dashboard (Phase 13 D5.15).
 *
 * Affiche jusqu'à 5 items triés par urgence (overdue puis année
 * croissante). Si plus de 5 items en attente, un lien « Voir les N
 * autres entreprises » pointe vers la liste des entreprises · les
 * déclarations `Untouched` n'existent pas dans l'Index Déclarations,
 * il faut passer par l'onglet Fiscalité de chaque fiche entreprise
 * pour les préparer.
 *
 * État vide · message explicite et apaisant (« Rien à générer pour
 * l'instant · les déclarations de l'année N seront à préparer à
 * partir du 01/01/N+1 ») plutôt qu'un « 0 » anxiogène.
 */
import { FileClock } from 'lucide-vue-next';
import { computed } from 'vue';
import { index as companiesIndex } from '@/routes/user/companies';
import PendingDeclarationRow from './PendingDeclarationRow.vue';

type Tasks = App.Data.User.Dashboard.DashboardPendingTasksData;

const props = defineProps<{
    count: Tasks['pendingDeclarationsCount'];
    items: Tasks['pendingDeclarations'];
}>();

const remainingCount = computed<number>(() =>
    Math.max(0, props.count - props.items.length),
);

const indexUrl = computed<string>(() => companiesIndex().url);
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

        <a
            v-if="remainingCount > 0"
            :href="indexUrl"
            class="text-xs font-medium text-slate-600 underline decoration-slate-300 underline-offset-2 transition-colors duration-[120ms] ease-out hover:text-slate-900 hover:decoration-slate-600"
        >
            Voir les {{ remainingCount }} autre{{ remainingCount > 1 ? 's' : '' }} entreprise{{ remainingCount > 1 ? 's' : '' }} →
        </a>
    </article>
</template>
