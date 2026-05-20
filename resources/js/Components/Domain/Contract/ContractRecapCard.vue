<script setup lang="ts">
/**
 * Live recap card for a contract being created or edited.
 * Two placement modes: aside (sticky, expanded) and collapsible (in-flow, toggle).
 * Pure presentational; the parent owns the preview fetch (useContractFiscalPreview).
 */
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

const MONTH_LABELS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

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
        /** Net rental preview after applied discounts. */
        rentalPreview?: App.Data.User.Planning.RentalPreviewData | null;
        rentalPreviewLoading?: boolean;
        /** 12 monthly NET amounts for the selected company × year. */
        companyMonthlyRentals?: Record<number, number | null> | null;
        companyMonthlyRentalsLoading?: boolean;
        companyMonthlyRentalsYear?: number | null;
        /** Collapsed by default with toggle (placement < xl). */
        collapsible?: boolean;
    }>(),
    {
        rentalPreview: null,
        rentalPreviewLoading: false,
        companyMonthlyRentals: null,
        companyMonthlyRentalsLoading: false,
        companyMonthlyRentalsYear: null,
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

const hasRentalDiscount = computed<boolean>(
    () => props.rentalPreview !== null
        && (props.rentalPreview.discountCents ?? 0) > 0,
);

const rentalDiscountPercentLabel = computed<string>(() => {
    const bp = props.rentalPreview?.appliedDiscountBasisPoints ?? null;
    if (bp === null) {
        return '';
    }

    const pct = bp / 100;
    return pct % 1 === 0
        ? `${pct} %`
        : `${pct.toString().replace('.', ',')} %`;
});

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
            <div
                v-if="company !== null"
                class="overflow-hidden rounded-lg border border-slate-200 bg-white"
            >
                <div class="flex items-center justify-between gap-3 px-3 py-2.5 border-b border-slate-100">
                    <span class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">
                        Loyer mensuel
                    </span>
                    <span
                        v-if="companyMonthlyRentalsYear !== null"
                        class="font-mono text-xs text-slate-400 tabular-nums"
                    >
                        {{ companyMonthlyRentalsYear }}
                    </span>
                </div>
                <div class="px-3 py-3">
                    <div v-if="companyMonthlyRentalsLoading" class="text-xs text-slate-500">
                        Calcul en cours…
                    </div>
                    <div
                        v-else-if="companyMonthlyRentals"
                        class="grid grid-cols-3 gap-x-6 gap-y-2 text-xs"
                    >
                        <div
                            v-for="m in 12"
                            :key="`mc-${m}`"
                            class="flex items-baseline gap-1.5"
                        >
                            <span class="text-slate-500">{{ MONTH_LABELS[m - 1] }}</span>
                            <span
                                v-if="companyMonthlyRentals[m] === null"
                                class="text-slate-300"
                            >-</span>
                            <span
                                v-else
                                class="font-mono text-slate-900 tabular-nums"
                            >
                                {{ formatEur(companyMonthlyRentals[m]! / 100, 0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!isComplete" class="text-sm text-slate-500">
                Compléter véhicule, entreprise et plage pour voir le récapitulatif
                de cette location.
            </div>

            <template v-else>
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

                <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-sm">
                    <span class="text-slate-600">Conducteurs</span>
                    <span class="font-mono text-slate-900">
                        {{ driversCount === 0 ? '·' : driversCount + (driversCount > 1 ? ' désignés' : ' désigné') }}
                    </span>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5 border-b border-slate-100">
                        <span class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">
                            Taxes induites
                        </span>
                        <span v-if="!previewLoading && preview !== null" class="inline-flex items-center gap-2 text-xs">
                            <span class="font-mono text-slate-500 tabular-nums">{{ preview.daysCount }} j</span>
                            <span class="text-slate-300">·</span>
                            <span class="font-mono font-semibold text-slate-900 tabular-nums">
                                {{ formatEur(preview.breakdown.totalDue, 2) }}
                            </span>
                        </span>
                    </div>
                    <div class="px-3 py-3 text-sm">
                        <div v-if="previewLoading" class="text-xs text-slate-500">
                            Calcul en cours…
                        </div>
                        <template v-else-if="preview !== null">
                            <div
                                v-if="preview.breakdown.appliedExemptions.length > 0"
                                class="mb-2 rounded-md bg-slate-50 px-2.5 py-1.5 text-xs text-slate-700"
                            >
                                <span class="font-medium text-emerald-700">✓</span>
                                {{ preview.breakdown.appliedExemptions[0]!.reason }}
                                <span
                                    v-if="preview.breakdown.appliedExemptions.length > 1"
                                    class="ml-1 text-slate-400"
                                >
                                    + {{ preview.breakdown.appliedExemptions.length - 1 }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="text-xs font-medium text-slate-600 underline-offset-2 transition-colors duration-[120ms] hover:text-slate-900 hover:underline"
                                @click="$emit('open-detail')"
                            >
                                Voir le détail →
                            </button>
                        </template>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5 border-b border-slate-100">
                        <span class="text-[11px] font-semibold tracking-[0.1em] text-slate-500 uppercase">
                            Loyer induit
                        </span>
                        <template v-if="!rentalPreviewLoading && rentalPreview !== null">
                            <span v-if="rentalPreview.hasMissingPricing" class="text-xs text-amber-700">
                                Tarif manquant
                            </span>
                            <span v-else-if="rentalPreview.netTotalCents !== null" class="inline-flex items-center gap-2 text-xs">
                                <span class="font-mono text-slate-500 tabular-nums">{{ rentalPreview.daysCount }} j</span>
                                <span class="text-slate-300">·</span>
                                <span class="font-mono font-semibold text-slate-900 tabular-nums">
                                    {{ formatEur(rentalPreview.netTotalCents / 100, 0) }}
                                </span>
                            </span>
                        </template>
                    </div>
                    <div class="px-3 py-3 text-sm">
                        <div v-if="rentalPreviewLoading" class="text-xs text-slate-500">
                            Calcul en cours…
                        </div>
                        <template v-else-if="rentalPreview !== null">
                            <div
                                v-if="rentalPreview.hasMissingPricing"
                                class="rounded-md bg-slate-50 px-2.5 py-2 text-xs text-amber-700"
                            >
                                Tarif annuel non renseigné · complète la grille
                                jour/semaine/mois sur la fiche véhicule pour
                                calculer le loyer.
                            </div>
                            <div v-else class="flex flex-col">
                                <div class="flex justify-between border-b border-slate-100 py-2">
                                    <span class="text-slate-600">Loyer brut</span>
                                    <span class="font-mono text-slate-900 tabular-nums">
                                        {{ formatEur((rentalPreview.grossTotalCents ?? 0) / 100, 0) }}
                                    </span>
                                </div>
                                <div
                                    v-if="hasRentalDiscount"
                                    class="flex justify-between border-b border-slate-100 py-2"
                                >
                                    <span class="text-emerald-700">
                                        Réductions appliquées
                                        <span
                                            v-if="rentalPreview.appliedDiscountLabel"
                                            class="ml-1 text-xs text-slate-400"
                                        >
                                            {{ rentalDiscountPercentLabel }} · {{ rentalPreview.appliedDiscountLabel }}
                                        </span>
                                        <span v-else class="ml-1 text-xs text-slate-400">
                                            {{ rentalDiscountPercentLabel }}
                                        </span>
                                    </span>
                                    <span class="font-mono text-emerald-700 tabular-nums">
                                        −{{ formatEur((rentalPreview.discountCents ?? 0) / 100, 0) }}
                                    </span>
                                </div>
                                <div class="flex justify-between pt-3">
                                    <span class="font-medium text-slate-900">Total net</span>
                                    <span class="font-mono font-semibold text-slate-900 tabular-nums">
                                        {{ formatEur((rentalPreview.netTotalCents ?? 0) / 100, 0) }}
                                    </span>
                                </div>
                                <div
                                    v-if="rentalPreview.monthlyImpact && rentalPreview.monthlyImpact.length > 0"
                                    class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-xs text-slate-500"
                                >
                                    <span class="text-[10px] font-semibold tracking-[0.1em] text-slate-400 uppercase">
                                        Nouveau total
                                    </span>
                                    <template
                                        v-for="(impact, idx) in rentalPreview.monthlyImpact"
                                        :key="`${impact.year}-${impact.month}`"
                                    >
                                        <span v-if="idx > 0" class="text-slate-300">·</span>
                                        <span class="inline-flex items-baseline gap-1">
                                            <span class="text-slate-600">{{ MONTH_LABELS[impact.month - 1] }}</span>
                                            <span class="font-mono text-slate-900 tabular-nums">
                                                {{ formatEur(impact.newTotalCents / 100, 0) }}
                                            </span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </template>
        </div>
    </div>
</template>
