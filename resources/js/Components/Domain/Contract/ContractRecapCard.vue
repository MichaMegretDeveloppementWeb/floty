<script setup lang="ts">
/**
 * Récap vivant de la location en cours de saisie/édition (chantier
 * UX-Loc). Deux modes de placement (cf. parents Create/Edit) :
 *
 *  - Mode aside (par défaut, ≥ xl) : carte sticky côté droit, toujours
 *    déployée. Le contenu est visible en permanence pour suivre la
 *    saisie en live.
 *  - Mode collapsible (`collapsible` prop, < xl) : carte placée dans
 *    le flux du form juste avant le bouton de soumission, repliée par
 *    défaut. L'utilisateur déplie pour vérifier le récap avant
 *    « Enregistrer ». Affiche une mini ligne de résumé (jours +
 *    montant) toujours visible.
 *
 * Affiche : véhicule + entreprise + plage + durée + type + conducteurs
 * + taxes induites résumées + lien « Voir le détail » qui ouvre la
 * `FiscalDetailModal` (le parent gère l'ouverture).
 *
 * État empty quand la saisie est encore incomplète : tip explicite.
 *
 * Le composant est purement présentationnel : il consomme les props
 * dérivées du form parent. La récupération du preview se fait via
 * `useContractFiscalPreview` côté parent, pas ici.
 */
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

const props = withDefaults(
    defineProps<{
        vehicle: { plate: string; label: string } | null;
        company: App.Data.User.Company.CompanyOptionData | null;
        startDate: string;
        endDate: string;
        durationDays: number | null;
        contractType: 'lcd' | 'lld' | null;
        driversCount: number;
        preview: App.Data.User.Fiscal.FiscalPreviewData | null;
        previewLoading: boolean;
        /** Mode replié par défaut avec toggle (placement < xl). */
        collapsible?: boolean;
    }>(),
    {
        collapsible: false,
    },
);

defineEmits<{
    'open-detail': [];
}>();

const isComplete = computed<boolean>(
    () => props.vehicle !== null
        && props.company !== null
        && props.startDate !== ''
        && props.endDate !== ''
        && props.durationDays !== null,
);

const startFr = computed<string>(() => props.startDate ? formatDateFr(props.startDate) : '');
const endFr = computed<string>(() => props.endDate ? formatDateFr(props.endDate) : '');

const typeLabel = computed<string>(() => {
    if (props.contractType === 'lcd') {
return 'LCD';
}

    if (props.contractType === 'lld') {
return 'LLD';
}

    return '';
});

// État replié/déplié (uniquement utilisé en mode collapsible).
const isOpen = ref<boolean>(false);

function toggle(): void {
    isOpen.value = !isOpen.value;
}

const showBody = computed<boolean>(
    () => !props.collapsible || isOpen.value,
);
</script>

<template>
    <div class="flex flex-col rounded-xl border border-slate-200 bg-white">
        <!-- Header : eyebrow + (mini résumé + chevron en mode collapsible) -->
        <component
            :is="collapsible ? 'button' : 'div'"
            v-bind="collapsible ? { type: 'button', 'aria-expanded': isOpen } : {}"
            :class="[
                'flex items-center justify-between gap-3 px-5 pt-5',
                showBody ? 'pb-0' : 'pb-5',
                collapsible ? 'text-left transition-colors duration-[120ms] ease-out hover:bg-slate-50' : '',
            ]"
            @click="collapsible ? toggle() : undefined"
        >
            <p class="eyebrow">Récap</p>

            <div
                v-if="collapsible"
                class="inline-flex items-center gap-3"
            >
                <span
                    v-if="!isComplete"
                    class="text-xs text-slate-400"
                >
                    Saisie incomplète
                </span>
                <template v-else>
                    <span
                        v-if="previewLoading"
                        class="text-xs text-slate-500"
                    >
                        Calcul…
                    </span>
                    <template v-else-if="preview !== null">
                        <span class="font-mono text-sm text-slate-700">
                            {{ preview.daysCount }} j
                        </span>
                        <span class="text-slate-300">·</span>
                        <span class="font-mono text-sm font-semibold text-slate-900">
                            {{ formatEur(preview.breakdown.totalDue, 2) }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="font-mono text-sm text-slate-700">
                            {{ durationDays }} j
                        </span>
                    </template>
                </template>
                <ChevronDown
                    :size="14"
                    :stroke-width="1.75"
                    :class="['text-slate-500 transition-transform duration-[120ms] ease-out', isOpen ? 'rotate-180' : 'rotate-0']"
                    aria-hidden="true"
                />
            </div>
        </component>

        <div v-if="showBody" class="flex flex-col gap-4 px-5 pt-4 pb-5">
            <div v-if="!isComplete" class="text-sm text-slate-500">
                Compléter véhicule, entreprise et plage pour voir le récapitulatif
                de cette location.
            </div>

            <template v-else>
                <!-- Bloc identité véhicule + entreprise -->
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <Plate :value="vehicle!.plate" />
                    </div>
                    <p class="text-sm text-slate-700">{{ vehicle!.label }}</p>
                    <div class="pt-1">
                        <CompanyTag
                            :name="company!.legalName"
                            :initials="company!.shortCode"
                            :color="company!.color"
                        />
                    </div>
                </div>

                <!-- Bloc plage + durée + type -->
                <div class="flex flex-col gap-1 border-t border-slate-100 pt-3">
                    <p class="text-sm text-slate-700">
                        Du <span class="font-medium">{{ startFr }}</span>
                        au <span class="font-medium">{{ endFr }}</span>
                    </p>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="font-mono">{{ durationDays }} j</span>
                        <span class="text-slate-300">·</span>
                        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">
                            {{ typeLabel }}
                        </span>
                    </div>
                </div>

                <!-- Bloc conducteurs -->
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-sm">
                    <span class="text-slate-600">Conducteurs</span>
                    <span class="font-mono text-slate-900">
                        {{ driversCount === 0 ? '·' : driversCount + (driversCount > 1 ? ' désignés' : ' désigné') }}
                    </span>
                </div>

                <!-- Bloc taxes induites -->
                <div class="flex flex-col gap-2 rounded-lg border border-blue-200 bg-blue-50/40 p-3">
                    <p class="eyebrow text-blue-700">Taxes induites</p>

                    <div v-if="previewLoading" class="text-xs text-slate-500">
                        Calcul en cours…
                    </div>

                    <template v-else-if="preview !== null">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">Durée</span>
                            <span class="font-mono text-slate-900">
                                {{ preview.daysCount }} j
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">Total</span>
                            <span class="font-mono font-semibold text-slate-900">
                                {{ formatEur(preview.breakdown.totalDue, 2) }}
                            </span>
                        </div>
                        <div
                            v-if="preview.breakdown.appliedExemptions.length > 0"
                            class="text-xs text-emerald-700"
                        >
                            ✓ {{ preview.breakdown.appliedExemptions[0]!.reason }}
                            <span
                                v-if="preview.breakdown.appliedExemptions.length > 1"
                                class="text-emerald-600"
                            >
                                (+ {{ preview.breakdown.appliedExemptions.length - 1 }} autre{{ preview.breakdown.appliedExemptions.length - 1 > 1 ? 's' : '' }})
                            </span>
                        </div>
                        <button
                            type="button"
                            class="self-start text-xs text-blue-700 underline-offset-2 hover:text-blue-900 hover:underline"
                            @click="$emit('open-detail')"
                        >
                            Voir le détail →
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>
