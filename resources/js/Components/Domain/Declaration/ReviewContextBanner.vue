<script setup lang="ts">
/**
 * Banner narratif en haut de la page Review d'une déclaration (Phase
 * 11 D5.8, amélioration A).
 *
 * Distingue deux modes :
 *   - **preparation** : première préparation, banner vert d'invitation.
 *     Court rappel du contexte fiscal (CIBS, deadline 30/04 N+1).
 *   - **regeneration** : régénération d'une déclaration obsolète, banner
 *     ambre/rose avec le numéro de la version remplacée, la date
 *     d'obsolescence, les motifs principaux, et un lien vers la
 *     version obsolète pour référence.
 *
 * Le mode est déduit côté caller via présence/absence de la
 * `predecessorDeclaration` dans le lifecycle resolver.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, FilePlus2, Recycle } from 'lucide-vue-next';
import { computed } from 'vue';
import { show as showDeclarationRoute } from '@/routes/user/declarations';
import {
    formatInvalidationOccurredAt,
    formatInvalidationReason,
} from '@/Utils/format/invalidationReason';

const props = defineProps<{
    mode: 'preparation' | 'regeneration';
    /** Version obsolète remplacée (seulement en mode régénération). */
    predecessor?: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
    /** Motifs d'obsolescence portés par la version remplacée. */
    obsoleteReasons?: App.Data.User.FiscalDeclaration.InvalidationReasonData[];
    fiscalYear: number;
}>();

const isRegeneration = computed<boolean>(() => props.mode === 'regeneration');

const predecessorRef = computed<string | null>(
    () => props.predecessor?.reference ?? null,
);

const firstReasonOccurredAt = computed<string | null>(() => {
    const reasons = props.obsoleteReasons ?? [];
    const first = reasons[0];

    return first ? first.occurredAt : null;
});

const reasonsToShow = computed<App.Data.User.FiscalDeclaration.InvalidationReasonData[]>(
    () => (props.obsoleteReasons ?? []).slice(0, 3),
);

const extraReasonsCount = computed<number>(() => {
    const total = (props.obsoleteReasons ?? []).length;

    return total > 3 ? total - 3 : 0;
});
</script>

<template>
    <div
        v-if="isRegeneration"
        class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
    >
        <div class="flex items-start gap-3">
            <Recycle :size="20" :stroke-width="1.75" class="shrink-0 text-amber-600" />
            <div class="flex flex-col gap-1">
                <p class="text-sm font-semibold text-amber-900">
                    Régénération de la déclaration
                    <template v-if="predecessorRef"> {{ predecessorRef }}</template>
                </p>
                <p class="text-xs text-amber-800">
                    Le périmètre fiscal {{ fiscalYear }} a évolué depuis la
                    précédente génération. Cette revue produit une nouvelle
                    version qui remplacera la précédente. Les décisions
                    inchangées sont reprises automatiquement par fingerprint.
                </p>
                <p v-if="firstReasonOccurredAt" class="text-[11px] text-amber-700">
                    Version précédente devenue obsolète le
                    {{ formatInvalidationOccurredAt(firstReasonOccurredAt) }}
                </p>
            </div>
        </div>

        <ul
            v-if="reasonsToShow.length > 0"
            class="flex flex-col gap-1.5 rounded-md border border-amber-200 bg-white/60 p-3"
        >
            <li
                v-for="(reason, index) in reasonsToShow"
                :key="index"
                class="text-xs text-amber-800"
            >
                <span class="font-medium">{{ formatInvalidationReason(reason) }}</span>
                <span class="text-amber-600">
                    · {{ formatInvalidationOccurredAt(reason.occurredAt) }}
                </span>
            </li>
            <li v-if="extraReasonsCount > 0" class="text-[11px] italic text-amber-700">
                +{{ extraReasonsCount }} autre<template v-if="extraReasonsCount > 1">s</template> motif<template v-if="extraReasonsCount > 1">s</template>
            </li>
        </ul>

        <Link
            v-if="predecessor"
            :href="showDeclarationRoute.url({ declaration: predecessor.id })"
            class="inline-flex w-fit items-center gap-1 text-xs font-medium text-amber-900 underline-offset-2 hover:underline"
        >
            Voir la version obsolète
            <ArrowUpRight :size="13" :stroke-width="1.75" />
        </Link>
    </div>

    <div
        v-else
        class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
    >
        <FilePlus2 :size="20" :stroke-width="1.75" class="shrink-0 text-emerald-600" />
        <div class="flex flex-col gap-1">
            <p class="text-sm font-semibold text-emerald-900">
                Préparation de la déclaration {{ fiscalYear }}
            </p>
            <p class="text-xs text-emerald-800">
                Tranchez les éventuels clusters de risque LCD ci-dessous, puis
                générez la déclaration. Le PDF annexe contiendra exactement la
                synthèse prévisualisée.
            </p>
        </div>
    </div>
</template>
