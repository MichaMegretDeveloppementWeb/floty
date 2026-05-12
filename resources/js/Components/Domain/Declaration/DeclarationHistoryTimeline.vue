<script setup lang="ts">
/**
 * Timeline chronologique des versions d'une déclaration fiscale pour
 * un couple `(company, year)` (Phase 13 D5.10.B, refondu D5.10.G).
 *
 * Lecture · plus récent en haut, plus ancien en bas (lecture
 * descendante = retour dans le temps). Cohérent avec un fil
 * d'événements `git log` ou un changelog moderne.
 *
 * **Tri stable** (D5.10.G) · le composant trie en interne par `id
 * DESC` peu importe l'ordre d'entrée, pour que l'ordre d'affichage
 * ne dépende JAMAIS de la version actuellement consultée. La
 * version consultée est repérée par `currentDeclarationId` (id à
 * mettre en évidence) plutôt que de la déplacer en tête de liste.
 *
 * Pour chaque entrée :
 *   - Cercle coloré selon le statut (emerald = générée active, rose
 *     = obsolète, amber = mise de côté, slate = brouillon).
 *   - Référence en `font-mono` cliquable (Link vers Show) sauf si
 *     c'est la version actuellement consultée · dans ce cas, rendu
 *     en `<span>` non cliquable + badge « Version consultée » + ring
 *     plus épais sur le dot.
 *   - Libellé d'état explicite.
 *   - Date de génération si disponible.
 *
 * Pas de logique métier ni d'événement émis · composant purement
 * présentationnel.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as showDeclarationRoute } from '@/routes/user/declarations';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type ItemData = App.Data.User.FiscalDeclaration.DeclarationListItemData;

const props = defineProps<{
    entries: ItemData[];
    currentDeclarationId: number;
}>();

interface TimelineEntry {
    declaration: ItemData;
    dotClass: string;
    label: string;
    isCurrent: boolean;
}

function dotClassFor(d: ItemData): string {
    if (d.isObsolete) {
        return 'bg-rose-400 ring-rose-100';
    }

    if (d.status === 'generated') {
        return 'bg-emerald-400 ring-emerald-100';
    }

    if (d.status === 'deferred') {
        return 'bg-amber-400 ring-amber-100';
    }

    // draft
    return 'bg-slate-400 ring-slate-100';
}

function labelFor(d: ItemData): string {
    if (d.isObsolete) {
        return 'Générée · obsolète';
    }

    if (d.status === 'generated') {
        return 'Générée';
    }

    if (d.status === 'deferred') {
        return 'Mise de côté';
    }

    return 'Brouillon';
}

const sortedEntries = computed<TimelineEntry[]>(() => {
    return [...props.entries]
        .sort((a, b) => b.id - a.id)
        .map((d) => ({
            declaration: d,
            dotClass: dotClassFor(d),
            label: labelFor(d),
            isCurrent: d.id === props.currentDeclarationId,
        }));
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Historique des versions
        </p>
        <ol class="flex flex-col">
            <li
                v-for="(entry, index) in sortedEntries"
                :key="entry.declaration.id"
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
                            {{ entry.declaration.internalLabel }}
                        </span>
                        <Link
                            v-else
                            :href="showDeclarationRoute.url({ declaration: entry.declaration.id })"
                            :class="[
                                'cursor-pointer font-mono text-sm transition-colors duration-[120ms] hover:underline',
                                entry.declaration.reference !== null
                                    ? 'font-medium text-slate-800 hover:text-slate-900'
                                    : 'italic text-slate-500 hover:text-slate-700',
                            ]"
                        >
                            {{ entry.declaration.internalLabel }}
                        </Link>
                        <span class="text-xs text-slate-500">· {{ entry.label }}</span>
                        <StatusPill v-if="entry.isCurrent" tone="slate">
                            Version consultée
                        </StatusPill>
                    </div>
                    <p v-if="entry.declaration.generatedAt" class="text-xs text-slate-400">
                        Générée le {{ formatDateFr(entry.declaration.generatedAt) }}
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
