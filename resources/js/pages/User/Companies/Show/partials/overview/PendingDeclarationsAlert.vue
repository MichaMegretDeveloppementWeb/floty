<script setup lang="ts">
/**
 * Card adaptative « Déclarations à traiter » sur l'onglet Vue
 * d'ensemble de la fiche entreprise (Phase 11 D4, refondu Phase 12
 * D5.9.D en composant adaptatif multi-états).
 *
 * Affiche une liste d'items adaptatifs · 1 par année qui mérite
 * l'attention, avec :
 *   - Pictogramme d'état (FilePlus2 / FileText / Clock / AlertTriangle
 *     / Recycle).
 *   - Libellé d'état explicite (« Jamais préparée », « Brouillon · N
 *     décisions à trancher », « Mise de côté », « Périmètre modifié »,
 *     « Régénération en cours »).
 *   - CTA contextuel approprié (« Préparer », « Reprendre », « Voir »
 *     selon le state).
 *   - Deadline réglementaire si non-Untouched.
 *
 * Tous les CTA mènent vers l'onglet Fiscalité de l'année concernée
 * (via `goto-fiscal-year`), où la `<DeclarationStateCard>` adaptative
 * propose ensuite l'action complète avec sa pédagogie (notamment le
 * bouton « Régénérer » sur S6 avec liste des motifs d'obsolescence).
 *
 * **Pourquoi pas de POST inline** · garder la pleine pédagogie côté
 * onglet Fiscalité évite les clics destructeurs sans confirmation
 * depuis le Vue d'ensemble.
 */
import {
    AlertTriangle,
    ChevronRight,
    Clock,
    FileCheck2,
    FilePlus2,
    FileText,
    Recycle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component } from 'vue';

type PendingEntry = App.Data.User.FiscalDeclaration.PendingDeclarationData;

const props = defineProps<{
    pendingDeclarations: PendingEntry[];
    companyId: number;
}>();

const emit = defineEmits<{
    'goto-fiscal-year': [year: number];
}>();

interface EntryConfig {
    icon: Component;
    title: string;
    cta: string;
    iconTone: string;
    dotTone: string;
}

function configFor(entry: PendingEntry): EntryConfig {
    switch (entry.state) {
        case 'untouched':
            return {
                icon: FilePlus2,
                title: 'Jamais préparée',
                cta: 'Préparer',
                iconTone: 'text-slate-500',
                dotTone: 'bg-amber-400',
            };
        case 'draft_pending':
            return {
                icon: FileText,
                title: entry.pendingClustersCount > 0
                    ? `Brouillon · ${entry.pendingClustersCount} décision${entry.pendingClustersCount > 1 ? 's' : ''} à trancher`
                    : 'Brouillon en cours',
                cta: 'Reprendre',
                iconTone: 'text-slate-500',
                dotTone: 'bg-amber-400',
            };
        case 'draft_ready_to_generate':
            return {
                icon: FileCheck2,
                title: 'Brouillon prêt à générer',
                cta: 'Reprendre',
                iconTone: 'text-emerald-500',
                dotTone: 'bg-emerald-400',
            };
        case 'deferred':
            return {
                icon: Clock,
                title: 'Mise de côté',
                cta: 'Reprendre',
                iconTone: 'text-slate-500',
                dotTone: 'bg-slate-400',
            };
        case 'generated_obsolete_orphan':
            return {
                icon: AlertTriangle,
                title: 'Périmètre modifié · obsolète',
                cta: 'Régénérer',
                iconTone: 'text-rose-500',
                dotTone: 'bg-rose-400',
            };
        case 'regeneration_in_progress':
            return {
                icon: Recycle,
                title: 'Régénération en cours',
                cta: 'Reprendre',
                iconTone: 'text-amber-500',
                dotTone: 'bg-amber-400',
            };
        case 'generated_active':
            // Théoriquement jamais dans la liste (filtré côté backend)
            return {
                icon: FileText,
                title: 'Générée',
                cta: 'Ouvrir',
                iconTone: 'text-emerald-500',
                dotTone: 'bg-emerald-400',
            };
    }
}

const oldestIsOverdue = computed<boolean>(
    () => props.pendingDeclarations[0]?.isOverdue ?? false,
);

const accentBorderClass = computed<string>(
    () => (oldestIsOverdue.value
        ? 'border-l-rose-400'
        : 'border-l-amber-400'),
);

const headerTitle = computed<string>(() => {
    const n = props.pendingDeclarations.length;

    if (n === 0) {
        return '';
    }

    if (n === 1) {
        return '1 déclaration à traiter';
    }

    return `${n} déclarations à traiter`;
});

function formatDeadlineFr(deadline: string): string {
    return new Date(deadline).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function deadlineText(entry: PendingEntry): string | null {
    if (entry.state === 'untouched') {
        return `Échéance ${formatDeadlineFr(entry.deadline)}`;
    }

    if (entry.isOverdue) {
        return `Échéance dépassée le ${formatDeadlineFr(entry.deadline)}`;
    }

    return `À finaliser avant le ${formatDeadlineFr(entry.deadline)}`;
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
            accentBorderClass,
        ]"
    >
        <div class="flex items-center gap-2">
            <AlertTriangle
                v-if="oldestIsOverdue"
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-rose-500"
            />
            <Clock
                v-else
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-amber-500"
            />
            <p class="text-sm font-semibold text-slate-900">
                {{ headerTitle }}
            </p>
        </div>

        <ul class="flex flex-col gap-1.5">
            <li
                v-for="entry in pendingDeclarations"
                :key="entry.fiscalYear"
            >
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
                    @click="handleClick(entry.fiscalYear)"
                >
                    <div class="flex items-start gap-3">
                        <component
                            :is="configFor(entry).icon"
                            :size="16"
                            :stroke-width="1.75"
                            :class="['mt-0.5 shrink-0', configFor(entry).iconTone]"
                        />
                        <div class="flex flex-col items-start gap-0.5">
                            <span class="font-medium">
                                Déclaration {{ entry.fiscalYear }}
                                <span class="ml-1 font-normal text-slate-500">
                                    · {{ configFor(entry).title }}
                                </span>
                            </span>
                            <span
                                v-if="deadlineText(entry)"
                                :class="[
                                    'text-xs',
                                    entry.isOverdue ? 'text-rose-600' : 'text-slate-500',
                                ]"
                            >
                                {{ deadlineText(entry) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="hidden text-xs font-medium text-slate-600 sm:inline">
                            {{ configFor(entry).cta }}
                        </span>
                        <ChevronRight :size="16" :stroke-width="1.75" class="text-slate-400" />
                    </div>
                </button>
            </li>
        </ul>
    </div>
</template>
