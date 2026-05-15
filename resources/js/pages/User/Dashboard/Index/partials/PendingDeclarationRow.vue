<script setup lang="ts">
/**
 * Ligne « déclaration en attente » sur le Dashboard (Phase 13 D5.15).
 *
 * Affiche · contexte entreprise + année + badge échéance (overdue
 * rose, soon amber, later slate) + CTA contextuelle pilotée par le
 * lifecycle state du DTO. Toute la ligne est cliquable vers la fiche
 * entreprise sur l'onglet Fiscalité de l'année concernée · c'est de
 * là que l'utilisateur lance la préparation, la reprise, ou la
 * régénération · les déclarations en `Untouched` n'existent même
 * pas encore dans la liste Index.
 */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as companyShow } from '@/routes/user/companies';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Item = App.Data.User.Dashboard.DashboardPendingDeclarationItemData;
type LifecycleState = App.Enums.FiscalDeclaration.DeclarationLifecycleState;

const props = defineProps<{ item: Item }>();

// Lot 5 D11 (F-19D-010) · pattern exhaustif avec garde `assertNever` ·
// si un nouveau case est ajouté à `LifecycleState` côté backend, le
// switch refuse de compiler en TypeScript strict (au lieu d'afficher
// silencieusement « Ouvrir » qui masquerait un état non-géré).
function assertNever(value: never, context: string): never {
    throw new Error(`${context} · état non-géré : ${String(value)}`);
}

const ctaLabel = computed<string>(() => {
    const state = props.item.state as LifecycleState;
    switch (state) {
        case 'untouched':
            return 'Préparer';
        case 'draft_pending':
            return 'Continuer la revue';
        case 'draft_ready_to_generate':
            return 'Générer';
        case 'deferred':
            return 'Reprendre';
        case 'generated_obsolete_orphan':
            return 'Régénérer';
        case 'regeneration_in_progress':
            return 'Finaliser';
        case 'deferred_regeneration':
            return 'Reprendre la régénération';
        case 'generated_active':
            // Cas safety net · une `generated_active` ne devrait jamais
            // apparaître dans la liste des `pending` (terminée pour
            // l'année). Si elle remonte, libellé neutre.
            return 'Ouvrir';
        default:
            assertNever(state, 'PendingDeclarationRow.ctaLabel');
    }
});

const targetUrl = computed<string>(() =>
    companyShow(props.item.companyId, {
        query: { tab: 'fiscal', year: props.item.fiscalYear },
    }).url,
);

const deadlineLabel = computed<string>(() => formatDateFr(props.item.deadline));

const deadlineTone = computed<string>(() => {
    if (props.item.isOverdue) {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
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
            :class="[
                'shrink-0 rounded-full border px-2 py-0.5 font-mono text-[10px] font-medium tabular-nums',
                deadlineTone,
            ]"
            :title="item.isOverdue ? `En retard depuis le ${deadlineLabel}` : `Échéance · ${deadlineLabel}`"
        >
            {{ item.isOverdue ? 'En retard' : deadlineLabel }}
        </span>

        <span class="shrink-0 text-xs font-medium text-slate-600 group-hover:text-slate-900">
            {{ ctaLabel }} →
        </span>
    </button>
</template>
