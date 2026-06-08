<script setup lang="ts">
/** Pending-declaration row with deadline badge + lifecycle-driven CTA. */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as companyShow } from '@/routes/user/companies';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Item = App.Data.User.Dashboard.DashboardPendingDeclarationItemData;
type LifecycleState = App.Enums.FiscalDeclaration.DeclarationLifecycleState;

const props = defineProps<{ item: Item }>();

// Exhaustive switch guard: a new LifecycleState case fails type-check.
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
            return 'Poursuivre';
        case 'generated_active':
            // Safety net: a generated_active should never appear here.
            return 'Ouvrir';
        default:
            return assertNever(state, 'PendingDeclarationRow.ctaLabel');
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
