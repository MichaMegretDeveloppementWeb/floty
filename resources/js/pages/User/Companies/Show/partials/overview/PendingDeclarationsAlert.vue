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
            'flex flex-col gap-3 rounded-xl border p-4',
            oldest?.isOverdue
                ? 'border-rose-200 bg-rose-50'
                : 'border-amber-200 bg-amber-50',
        ]"
    >
        <div class="flex items-start gap-3">
            <component
                :is="oldest?.isOverdue ? AlertTriangle : Clock"
                :size="20"
                :stroke-width="1.75"
                :class="['shrink-0', oldest?.isOverdue ? 'text-rose-600' : 'text-amber-600']"
            />
            <div class="flex flex-col gap-1">
                <p :class="[
                    'text-sm font-semibold',
                    oldest?.isOverdue ? 'text-rose-900' : 'text-amber-900',
                ]">
                    {{ pendingDeclarations.length === 1
                        ? 'Une déclaration à finaliser'
                        : `${pendingDeclarations.length} déclarations à finaliser`
                    }}
                </p>
                <p :class="[
                    'text-xs',
                    oldest?.isOverdue ? 'text-rose-800' : 'text-amber-800',
                ]">
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
                    :class="[
                        'flex w-full cursor-pointer items-center justify-between gap-3 rounded-md border bg-white/80 px-3 py-2 text-sm transition-colors duration-[120ms] ease-out',
                        entry.isOverdue
                            ? 'border-rose-200 text-rose-900 hover:bg-rose-100'
                            : 'border-amber-200 text-amber-900 hover:bg-amber-100',
                    ]"
                    @click="handleClick(entry.fiscalYear)"
                >
                    <div class="flex flex-col items-start gap-0.5">
                        <span class="font-medium">Déclaration {{ entry.fiscalYear }}</span>
                        <span :class="['text-xs', entry.isOverdue ? 'text-rose-700' : 'text-amber-700']">
                            {{ entry.isOverdue ? 'Échéance dépassée le' : 'À finaliser avant le' }}
                            {{ formatDeadlineFr(entry.deadline) }}
                        </span>
                    </div>
                    <ChevronRight :size="16" :stroke-width="1.75" />
                </button>
            </li>
        </ul>
    </div>
</template>
