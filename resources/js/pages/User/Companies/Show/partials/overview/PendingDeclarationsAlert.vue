<script setup lang="ts">
/**
 * Alerte « Déclarations à finaliser » sur l'onglet Vue d'ensemble de
 * la fiche entreprise (Phase 11 D4). Affichée si au moins une
 * `(company, year)` attend une déclaration. Plus ancienne année en
 * premier (la plus critique). Clic → onglet Fiscalité de l'année.
 */
import { AlertTriangle, ChevronRight, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    pendingDeclarations: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
    companyId: number;
}>();

const emit = defineEmits<{
    'goto-fiscal-year': [year: number];
}>();

const oldest = computed(() => props.pendingDeclarations[0] ?? null);

function formatDeadlineFr(deadline: string): string {
    return new Date(deadline).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function handleClick(year: number): void {
    emit('goto-fiscal-year', year);
}
</script>

<template>
    <div
        v-if="pendingDeclarations.length > 0"
        :class="[
            'flex flex-col gap-3 rounded-xl border border-slate-200 border-l-2 bg-slate-50 p-4',
            oldest?.isOverdue ? 'border-l-rose-400' : 'border-l-amber-400',
        ]"
    >
        <div class="flex items-start gap-3">
            <component
                :is="oldest?.isOverdue ? AlertTriangle : Clock"
                :size="20"
                :stroke-width="1.75"
                :class="['shrink-0', oldest?.isOverdue ? 'text-rose-500' : 'text-amber-500']"
            />
            <div class="flex flex-col gap-1">
                <p class="text-sm font-semibold text-slate-900">
                    {{ pendingDeclarations.length === 1
                        ? 'Une déclaration à finaliser'
                        : `${pendingDeclarations.length} déclarations à finaliser`
                    }}
                </p>
                <p class="text-xs text-slate-600">
                    Prépare la déclaration depuis l'onglet Fiscalité.
                    {{ oldest?.isOverdue ? 'Échéance dépassée.' : 'Date limite réglementaire CIBS : 30 avril N+1.' }}
                </p>
            </div>
        </div>

        <ul class="flex flex-col gap-1">
            <li
                v-for="entry in pendingDeclarations"
                :key="entry.fiscalYear"
            >
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:bg-slate-50 hover:border-slate-300"
                    @click="handleClick(entry.fiscalYear)"
                >
                    <div class="flex items-center gap-2">
                        <span :class="['inline-block size-1.5 shrink-0 rounded-full', entry.isOverdue ? 'bg-rose-400' : 'bg-amber-400']" />
                        <div class="flex flex-col items-start gap-0.5">
                            <span class="font-medium">Déclaration {{ entry.fiscalYear }}</span>
                            <span class="text-xs text-slate-500">
                                {{ entry.isOverdue ? 'Échéance dépassée le' : 'À finaliser avant le' }}
                                {{ formatDeadlineFr(entry.deadline) }}
                            </span>
                        </div>
                    </div>
                    <ChevronRight :size="16" :stroke-width="1.75" class="text-slate-400" />
                </button>
            </li>
        </ul>
    </div>
</template>
