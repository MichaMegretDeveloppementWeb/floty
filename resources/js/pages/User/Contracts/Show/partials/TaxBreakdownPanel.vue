<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import {
    formatSegmentRange,
    hasScission,
    useTaxBreakdownPanel,
} from '@/Composables/Contract/Show/useTaxBreakdownPanel';
import RuleCard from '@/pages/User/FiscalRules/Index/partials/RuleCard.vue';
import { formatEur } from '@/Utils/format/formatEur';
import {
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

const props = defineProps<{
    taxBreakdown: App.Data.User.Contract.ContractTaxBreakdownData | null;
}>();

const {
    years,
    isMultiYear,
    selectedCode,
    selectedYear,
    selectedRule,
    modalOpen,
    openRule,
} = useTaxBreakdownPanel(props);
</script>

<template>
    <Card>
        <template #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Taxes générées par cette location
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Calcul fiscal exact selon les règles de l'année concernée.
                </p>
            </div>
        </template>

        <!-- Empty state : VFC manquantes ou contrat sans véhicule fiscal -->
        <p
            v-if="taxBreakdown === null"
            class="text-sm text-slate-500"
        >
            Calcul fiscal indisponible (caractéristiques fiscales du véhicule manquantes).
        </p>

        <div
            v-else
            class="flex flex-col gap-6"
        >
            <section
                v-for="year in years"
                :key="year.year"
                class="flex flex-col gap-4"
                :class="isMultiYear ? 'rounded-lg border border-slate-100 p-4' : ''"
            >
                <h3
                    v-if="isMultiYear"
                    class="text-sm font-semibold text-slate-700"
                >
                    Année {{ year.year }}
                </h3>

                <!-- Cas LCD pur ou autre exonération totale : 0 € -->
                <div v-if="year.daysAssigned === 0" class="flex flex-col gap-3">
                    <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        Location exonérée pour {{ year.year }} : 0 €.
                        <span class="block text-xs text-emerald-700/80 mt-0.5">
                            Aucun jour retenu au numérateur du prorata après application
                            des règles d'exonération.
                        </span>
                    </p>

                    <!-- D5.10.T · montant hypothétique « si pas LCD ».
                         Affiché uniquement quand R-2024-021 a été
                         effectivement appliquée (les autres exonérations
                         totales ne génèrent pas cet indicateur). -->
                    <section
                        v-if="year.hypotheticalTotalDueIfNoLcd !== null"
                        class="rounded-md border border-dashed border-slate-200 bg-slate-50/60 px-3 py-2.5"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            À titre indicatif · si requalifié en LLD
                        </p>
                        <div class="mt-2 flex flex-col gap-1 text-sm text-slate-700">
                            <p class="flex items-baseline justify-between gap-2">
                                <span class="text-xs text-slate-500">Taxe CO₂</span>
                                <span class="font-mono">
                                    {{ formatEur(year.hypotheticalCo2DueIfNoLcd ?? 0) }}
                                </span>
                            </p>
                            <p class="flex items-baseline justify-between gap-2">
                                <span class="text-xs text-slate-500">Taxe polluants</span>
                                <span class="font-mono">
                                    {{ formatEur(year.hypotheticalPollutantsDueIfNoLcd ?? 0) }}
                                </span>
                            </p>
                            <p class="flex items-baseline justify-between gap-2 border-t border-slate-200 pt-1.5">
                                <span class="text-xs font-semibold text-slate-700">Total</span>
                                <span class="font-mono font-semibold text-slate-900">
                                    {{ formatEur(year.hypotheticalTotalDueIfNoLcd) }}
                                </span>
                            </p>
                        </div>
                        <p class="mt-2 text-[11px] leading-snug text-slate-500">
                            Estimation du montant qui serait dû si ce contrat
                            était requalifié en LLD.
                        </p>
                    </section>
                </div>

                <!-- Cas taxable : formule explicite -->
                <div
                    v-else
                    class="flex flex-col gap-4"
                >
                    <!-- Section CO₂ -->
                    <section class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span
                                class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >
                                Taxe CO₂
                            </span>
                            <Badge tone="blue">
                                {{ homologationMethodLabel[year.co2Method] }}
                            </Badge>
                        </div>

                        <!-- Mono-segment · formule directe -->
                        <p
                            v-if="!hasScission(year, 'co2')"
                            class="font-mono text-sm text-slate-600"
                        >
                            {{ formatEur(year.co2FullYearTariff) }} ×
                            {{ year.daysAssigned }} / {{ year.daysInYear }}
                            <span class="text-slate-400">=</span>
                            <span class="font-semibold text-slate-900 ml-2">
                                {{ formatEur(year.co2Due) }}
                            </span>
                        </p>

                        <!-- Multi-segment · scission CO₂ (rare, prévu pour V2+) -->
                        <div v-else class="flex flex-col gap-1">
                            <p
                                v-for="(segment, idx) in year.segments"
                                :key="`co2-${idx}`"
                                class="font-mono text-xs text-slate-600"
                            >
                                <span class="text-slate-400">{{ formatSegmentRange(segment.effectiveFromInYear, segment.effectiveToInYear) }}</span>
                                · {{ formatEur(segment.co2FullYearTariff) }} ×
                                {{ segment.daysAssignedToContract }} / {{ segment.daysInYear }}
                                <span class="text-slate-400">=</span>
                                <span class="font-semibold text-slate-900 ml-1">
                                    {{ formatEur(segment.co2Due) }}
                                </span>
                            </p>
                            <p class="font-mono text-sm text-slate-700 mt-0.5 pl-2 border-l-2 border-slate-200">
                                Total CO₂ ·
                                <span class="font-semibold text-slate-900 ml-1">
                                    {{ formatEur(year.co2Due) }}
                                </span>
                            </p>
                        </div>
                    </section>

                    <!-- Section Polluants -->
                    <section class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span
                                class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >
                                Taxe polluants
                            </span>
                            <Badge tone="amber">
                                {{ pollutantCategoryLabel[year.pollutantCategory] }}
                            </Badge>
                        </div>

                        <!-- Mono-segment · formule directe -->
                        <p
                            v-if="!hasScission(year, 'pollutants')"
                            class="font-mono text-sm text-slate-600"
                        >
                            {{ formatEur(year.pollutantsFullYearTariff) }} ×
                            {{ year.daysAssigned }} / {{ year.daysInYear }}
                            <span class="text-slate-400">=</span>
                            <span class="font-semibold text-slate-900 ml-2">
                                {{ formatEur(year.pollutantsDue) }}
                            </span>
                        </p>

                        <!-- Multi-segment · scission polluants (ex. LF 2026 art. 58 V IV au 01/03) -->
                        <div v-else class="flex flex-col gap-1">
                            <p
                                v-for="(segment, idx) in year.segments"
                                :key="`pollutants-${idx}`"
                                class="font-mono text-xs text-slate-600"
                            >
                                <span class="text-slate-400">{{ formatSegmentRange(segment.effectiveFromInYear, segment.effectiveToInYear) }}</span>
                                · {{ formatEur(segment.pollutantsFullYearTariff) }} ×
                                {{ segment.daysAssignedToContract }} / {{ segment.daysInYear }}
                                <span class="text-slate-400">=</span>
                                <span class="font-semibold text-slate-900 ml-1">
                                    {{ formatEur(segment.pollutantsDue) }}
                                </span>
                            </p>
                            <p class="font-mono text-sm text-slate-700 mt-0.5 pl-2 border-l-2 border-slate-200">
                                Total polluants ·
                                <span class="font-semibold text-slate-900 ml-1">
                                    {{ formatEur(year.pollutantsDue) }}
                                </span>
                            </p>
                        </div>
                    </section>

                    <!-- Total année -->
                    <div
                        class="flex items-center justify-between gap-2 border-t border-slate-100 pt-2"
                    >
                        <span class="text-xs text-slate-500">
                            Sous-total {{ year.year }}
                            ({{ year.daysInContractInYear }} jour{{ year.daysInContractInYear > 1 ? 's' : '' }} de location)
                        </span>
                        <span class="font-mono text-sm font-semibold text-slate-900">
                            {{ formatEur(year.totalDue) }}
                        </span>
                    </div>
                </div>

                <!-- Exonérations appliquées -->
                <section
                    v-if="year.appliedExemptions.length > 0"
                    class="flex flex-col gap-1.5 border-t border-slate-100 pt-3"
                >
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >
                        Exonérations appliquées
                    </span>
                    <ul class="flex flex-col gap-1.5 text-sm text-slate-700">
                        <li
                            v-for="exemption in year.appliedExemptions"
                            :key="exemption.ruleCode"
                            class="flex items-start gap-2"
                        >
                            <span class="mt-0.5 shrink-0 text-emerald-600">✓</span>
                            <span class="flex-1">{{ exemption.reason }}</span>
                            <button
                                type="button"
                                class="shrink-0 cursor-pointer rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-200 hover:text-slate-900 focus-visible:bg-slate-200 focus-visible:outline-none"
                                :title="`Voir le détail de la règle ${exemption.ruleCode}`"
                                @click="openRule(exemption.ruleCode, year.year)"
                            >
                                {{ exemption.ruleCode }}
                            </button>
                        </li>
                    </ul>
                </section>

                <!-- Règles appliquées (badges cliquables) -->
                <section
                    v-if="year.appliedRuleCodes.length > 0"
                    class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs"
                >
                    <span class="text-slate-400">Règles appliquées :</span>
                    <button
                        v-for="code in year.appliedRuleCodes"
                        :key="code"
                        type="button"
                        class="cursor-pointer rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-200 hover:text-slate-900 focus-visible:bg-slate-200 focus-visible:outline-none"
                        :title="`Voir le détail de la règle ${code}`"
                        @click="openRule(code, year.year)"
                    >
                        {{ code }}
                    </button>
                </section>
            </section>

            <!-- Total agrégé (visible seulement en multi-année) -->
            <section
                class="flex items-center justify-between gap-2 rounded-lg bg-transparent px-4 py-3 shadow-[0_0_2px_silver]"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-700 uppercase"
                >
                    Total location
                </span>
                <span class="font-mono text-lg font-semibold text-slate-700">
                    {{ formatEur(taxBreakdown.totalDue) }}
                </span>
            </section>

        </div>

        <Modal
            v-model:open="modalOpen"
            :title="selectedRule?.name ?? selectedCode ?? 'Règle fiscale'"
            :description="`Code ${selectedCode}`"
            size="lg"
        >
            <RuleCard
                v-if="selectedCode && selectedYear !== null"
                :code="selectedCode"
                :rule="selectedRule ?? undefined"
            />
        </Modal>
    </Card>
</template>
