<script setup lang="ts">
/**
 * Timeline chronologique des versions d'une facture pour un couple
 * (entreprise × année × mois) · refonte D5.10.P (option C historique).
 *
 * Pattern aligné `DeclarationHistoryTimeline` : plus récent en haut,
 * plus ancien en bas (lecture descendante = retour dans le temps).
 *
 * **Tri stable** · le composant trie en interne par `id DESC` peu
 * importe l'ordre d'entrée, pour que l'ordre d'affichage ne dépende
 * JAMAIS de la version actuellement consultée. La version consultée
 * est repérée par `currentInvoiceId` (id à mettre en évidence) plutôt
 * que déplacée en tête de liste.
 *
 * Pour chaque entrée :
 *   - Cercle coloré selon le statut (emerald = active, rose = obsolète).
 *   - Référence en `font-mono` cliquable (Link vers Show) sauf si c'est
 *     la version actuellement consultée · dans ce cas, rendu en `<span>`
 *     non cliquable + badge « Version consultée » + ring plus épais.
 *   - Date de génération.
 *
 * Composant purement présentationnel · aucune logique métier.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as showInvoiceRoute } from '@/routes/user/invoices';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type ItemData = App.Data.User.Invoice.InvoiceHistoryEntryData;

const props = defineProps<{
    entries: ItemData[];
    currentInvoiceId: number;
}>();

interface TimelineEntry {
    invoice: ItemData;
    dotClass: string;
    label: string;
    isCurrent: boolean;
}

function dotClassFor(i: ItemData): string {
    if (i.isObsolete) {
        return 'bg-rose-400 ring-rose-100';
    }

    return 'bg-emerald-400 ring-emerald-100';
}

function labelFor(i: ItemData): string {
    return i.isObsolete ? 'Émise · obsolète' : 'Émise';
}

const sortedEntries = computed<TimelineEntry[]>(() =>
    [...props.entries]
        .sort((a, b) => b.id - a.id)
        .map((i) => ({
            invoice: i,
            dotClass: dotClassFor(i),
            label: labelFor(i),
            isCurrent: i.id === props.currentInvoiceId,
        })),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Historique des versions
        </p>
        <ol class="flex flex-col">
            <li
                v-for="(entry, index) in sortedEntries"
                :key="entry.invoice.id"
                class="relative flex items-start gap-3 pl-2"
            >
                <span
                    v-if="index < sortedEntries.length - 1"
                    class="absolute left-[13px] top-3 z-0 h-full w-px bg-slate-200"
                    aria-hidden="true"
                />
                <span
                    :class="[
                        'relative z-10 mt-1.5 inline-block size-2.5 shrink-0 rounded-full',
                        entry.isCurrent ? 'ring-4' : 'ring-2',
                        entry.dotClass,
                    ]"
                    aria-hidden="true"
                />
                <div class="flex flex-1 flex-col gap-0.5 pb-3">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span
                            v-if="entry.isCurrent"
                            class="cursor-default font-mono text-sm font-medium text-slate-900"
                        >
                            {{ entry.invoice.invoiceNumber }}
                        </span>
                        <Link
                            v-else
                            :href="showInvoiceRoute.url({ invoice: entry.invoice.id })"
                            :class="[
                                'cursor-pointer font-mono text-sm transition-colors duration-[120ms] hover:underline',
                                entry.invoice.isObsolete
                                    ? 'text-slate-500 hover:text-slate-700'
                                    : 'font-medium text-slate-800 hover:text-slate-900',
                            ]"
                        >
                            {{ entry.invoice.invoiceNumber }}
                        </Link>
                        <span class="text-xs text-slate-500">· {{ entry.label }}</span>
                        <StatusPill v-if="entry.isCurrent" tone="slate">
                            Version consultée
                        </StatusPill>
                    </div>
                    <p class="text-xs text-slate-400">
                        Émise le {{ formatDateFr(entry.invoice.generatedAt) }}
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
