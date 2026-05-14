<script setup lang="ts">
/**
 * Ligne « déclaration en attente » sur le Dashboard (Phase 13 D5.15).
 *
 * Affiche · contexte entreprise + année + badge échéance (overdue
 * rose, soon amber, later slate) + CTA contextuelle pilotée par le
 * lifecycle state du DTO. Toute la ligne est cliquable vers la
 * destination la plus pertinente · Show pour les états avec une
 * declaration courante, route prepare/Review sinon.
 */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    review as reviewRoute,
    show as showRoute,
} from '@/routes/user/declarations';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Item = App.Data.User.Dashboard.DashboardPendingDeclarationItemData;
type LifecycleState = App.Enums.FiscalDeclaration.DeclarationLifecycleState;

const props = defineProps<{ item: Item }>();

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
        default:
            return 'Ouvrir';
    }
});

const targetUrl = computed<string>(() => {
    // Si on a une déclaration courante, on l'ouvre (Show ou Review
    // selon ce qui a du sens). Les `draft` et `deferred` veulent une
    // Review pour décider/générer. Les `generated_obsolete_orphan`
    // vont sur Show pour proposer la régénération.
    if (props.item.currentDeclarationId !== null) {
        const isDraftLike = [
            'draft_pending',
            'draft_ready_to_generate',
            'deferred',
            'regeneration_in_progress',
            'deferred_regeneration',
        ].includes(props.item.state);

        return isDraftLike
            ? reviewRoute(props.item.currentDeclarationId).url
            : showRoute(props.item.currentDeclarationId).url;
    }

    // Pas de déclaration courante (état Untouched) · pointer vers
    // l'index Déclarations filtré par entreprise + année, l'utilisateur
    // y trouvera le bouton « Préparer une déclaration ».
    return `/app/declarations?companyId=${props.item.companyId}&fiscalYear=${props.item.fiscalYear}`;
});

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
