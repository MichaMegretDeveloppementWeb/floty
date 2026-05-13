<script setup lang="ts">
/**
 * Card adaptative « À traiter » sur l'onglet Vue d'ensemble de la fiche
 * entreprise (Phase D5.10.S · refonte multi-actions).
 *
 * Affiche les actions en attente (déclarations + factures) intercalées
 * par année, de la plus ancienne à la plus récente. Pour chaque année :
 *   - 0 ou 1 ligne « Déclaration YYYY · état » (selon le cycle de vie)
 *   - 0 ou 1 ligne « N facture(s) à générer pour YYYY »
 *
 * Les CTAs naviguent vers l'onglet Fiscalité ou Facturation de l'année
 * concernée (URL `?tab=fiscal&fiscalYear=Y` ou `?tab=billing&billingYear=Y`).
 *
 * **Pourquoi pas de POST inline** · garder la pleine pédagogie côté
 * onglet cible évite les clics destructeurs sans confirmation depuis
 * le Vue d'ensemble.
 */
import {
    AlertTriangle,
    ChevronRight,
    Clock,
    FileCheck2,
    FilePlus2,
    FileText,
    Receipt,
    Recycle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component } from 'vue';

type PendingDeclaration = App.Data.User.FiscalDeclaration.PendingDeclarationData;
type PendingInvoice = App.Data.User.Billing.PendingInvoiceYearData;

const props = defineProps<{
    pendingDeclarations: PendingDeclaration[];
    pendingInvoices: PendingInvoice[];
    companyId: number;
}>();

const emit = defineEmits<{
    'goto-fiscal-year': [year: number];
    'goto-billing-year': [year: number];
}>();

interface EntryConfig {
    icon: Component;
    title: string;
    cta: string;
    iconTone: string;
}

function declarationConfig(entry: PendingDeclaration): EntryConfig {
    switch (entry.state) {
        case 'untouched':
            return {
                icon: FilePlus2,
                title: 'Jamais préparée',
                cta: 'Préparer',
                iconTone: 'text-slate-500',
            };
        case 'draft_pending':
            return {
                icon: FileText,
                title: entry.pendingClustersCount > 0
                    ? `Brouillon · ${entry.pendingClustersCount} décision${entry.pendingClustersCount > 1 ? 's' : ''} à trancher`
                    : 'Brouillon en cours',
                cta: 'Reprendre',
                iconTone: 'text-slate-500',
            };
        case 'draft_ready_to_generate':
            return {
                icon: FileCheck2,
                title: 'Brouillon prêt à générer',
                cta: 'Reprendre',
                iconTone: 'text-emerald-500',
            };
        case 'deferred':
            return {
                icon: Clock,
                title: 'Mise de côté',
                cta: 'Reprendre',
                iconTone: 'text-slate-500',
            };
        case 'deferred_regeneration':
            return {
                icon: Clock,
                title: 'Mise de côté · régénération en attente',
                cta: 'Reprendre',
                iconTone: 'text-amber-500',
            };
        case 'generated_obsolete_orphan':
            return {
                icon: AlertTriangle,
                title: 'Périmètre modifié · obsolète',
                cta: 'Régénérer',
                iconTone: 'text-rose-500',
            };
        case 'regeneration_in_progress':
            return {
                icon: Recycle,
                title: 'Régénération en cours',
                cta: 'Reprendre',
                iconTone: 'text-amber-500',
            };
        case 'generated_active':
            // Théoriquement jamais dans la liste (filtré côté backend)
            return {
                icon: FileText,
                title: 'Générée',
                cta: 'Ouvrir',
                iconTone: 'text-emerald-500',
            };
    }
}

function formatDateFr(dateIso: string): string {
    return new Date(dateIso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

interface SubtitleResult {
    text: string;
    toneClass: string;
}

function declarationSubtitle(entry: PendingDeclaration): SubtitleResult {
    switch (entry.state) {
        case 'untouched':
        case 'draft_pending':
        case 'draft_ready_to_generate':
        case 'deferred': {
            const prefix = entry.isOverdue ? 'Échéance dépassée le' : 'Échéance le';

            return {
                text: `${prefix} ${formatDateFr(entry.deadline)}`,
                toneClass: entry.isOverdue ? 'text-rose-600' : 'text-slate-500',
            };
        }
        case 'generated_obsolete_orphan': {
            if (entry.obsoleteReasonsCount === 0 || entry.obsoleteSinceDate === null) {
                return { text: 'Périmètre modifié', toneClass: 'text-slate-500' };
            }

            const plural = entry.obsoleteReasonsCount > 1 ? 's' : '';

            return {
                text: `${entry.obsoleteReasonsCount} motif${plural} depuis le ${formatDateFr(entry.obsoleteSinceDate)}`,
                toneClass: 'text-slate-500',
            };
        }
        case 'regeneration_in_progress': {
            const parts: string[] = [];

            if (entry.pendingClustersCount > 0) {
                const plural = entry.pendingClustersCount > 1 ? 's' : '';
                parts.push(`${entry.pendingClustersCount} décision${plural} à trancher`);
            } else {
                parts.push('Prêt à générer la nouvelle version');
            }

            if (entry.obsoleteSinceDate !== null) {
                parts.push(`obsolète depuis le ${formatDateFr(entry.obsoleteSinceDate)}`);
            }

            return { text: parts.join(' · '), toneClass: 'text-slate-500' };
        }
        case 'deferred_regeneration': {
            const parts: string[] = ['Brouillon mis de côté'];

            if (entry.obsoleteSinceDate !== null) {
                parts.push(`obsolète depuis le ${formatDateFr(entry.obsoleteSinceDate)}`);
            }

            return { text: parts.join(' · '), toneClass: 'text-slate-500' };
        }
        case 'generated_active':
            return { text: '', toneClass: 'text-slate-500' };
    }
}

/**
 * Construit une liste plate d'items triés par année (croissant), avec
 * pour chaque année : déclaration d'abord puis facture. Les types sont
 * discriminés par `kind`.
 */
type FlatItem =
    | { kind: 'declaration'; year: number; data: PendingDeclaration }
    | { kind: 'invoice'; year: number; data: PendingInvoice };

const items = computed<FlatItem[]>(() => {
    const years = new Set<number>();
    for (const d of props.pendingDeclarations) {
        years.add(d.fiscalYear);
    }
    for (const i of props.pendingInvoices) {
        years.add(i.fiscalYear);
    }

    const sortedYears = [...years].sort((a, b) => a - b);
    const result: FlatItem[] = [];

    for (const year of sortedYears) {
        const decl = props.pendingDeclarations.find((d) => d.fiscalYear === year);
        if (decl) {
            result.push({ kind: 'declaration', year, data: decl });
        }
        const inv = props.pendingInvoices.find((i) => i.fiscalYear === year);
        if (inv) {
            result.push({ kind: 'invoice', year, data: inv });
        }
    }

    return result;
});

const oldestIsOverdue = computed<boolean>(() => {
    const firstDecl = props.pendingDeclarations[0];
    return firstDecl?.isOverdue ?? false;
});

const accentBorderClass = computed<string>(
    () => (oldestIsOverdue.value ? 'border-l-rose-400' : 'border-l-amber-400'),
);

const headerTitle = computed<string>(() => {
    const n = items.value.length;

    if (n === 0) {
        return '';
    }

    if (n === 1) {
        return '1 action à traiter';
    }

    return `${n} actions à traiter`;
});

function handleClick(item: FlatItem): void {
    if (item.kind === 'declaration') {
        emit('goto-fiscal-year', item.year);
    } else {
        emit('goto-billing-year', item.year);
    }
}
</script>

<template>
    <div
        v-if="items.length > 0"
        :class="[
            'flex flex-col gap-3 rounded-sm border border-slate-200 border-l-2 bg-white p-4',
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
                v-for="item in items"
                :key="`${item.kind}-${item.year}`"
            >
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
                    @click="handleClick(item)"
                >
                    <template v-if="item.kind === 'declaration'">
                        <div class="flex items-start gap-3">
                            <component
                                :is="declarationConfig(item.data).icon"
                                :size="16"
                                :stroke-width="1.75"
                                :class="['mt-0.5 shrink-0', declarationConfig(item.data).iconTone]"
                            />
                            <div class="flex flex-col items-start gap-0.5">
                                <span class="font-medium">
                                    Déclaration {{ item.year }}
                                    <span class="ml-1 font-normal text-slate-500">
                                        · {{ declarationConfig(item.data).title }}
                                    </span>
                                </span>
                                <span
                                    v-if="declarationSubtitle(item.data).text"
                                    :class="['text-xs', declarationSubtitle(item.data).toneClass]"
                                >
                                    {{ declarationSubtitle(item.data).text }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="hidden text-xs font-medium text-slate-600 sm:inline">
                                {{ declarationConfig(item.data).cta }}
                            </span>
                            <ChevronRight :size="16" :stroke-width="1.75" class="text-slate-400" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex items-start gap-3">
                            <Receipt
                                :size="16"
                                :stroke-width="1.75"
                                class="mt-0.5 shrink-0 text-slate-500"
                            />
                            <div class="flex flex-col items-start gap-0.5">
                                <span class="font-medium">
                                    {{ item.data.missingInvoicesCount }} facture{{ item.data.missingInvoicesCount > 1 ? 's' : '' }} à générer
                                    <span class="ml-1 font-normal text-slate-500">
                                        · {{ item.year }}
                                    </span>
                                </span>
                                <span class="text-xs text-slate-500">
                                    Mois écoulés non encore facturés
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="hidden text-xs font-medium text-slate-600 sm:inline">
                                Générer
                            </span>
                            <ChevronRight :size="16" :stroke-width="1.75" class="text-slate-400" />
                        </div>
                    </template>
                </button>
            </li>
        </ul>
    </div>
</template>
