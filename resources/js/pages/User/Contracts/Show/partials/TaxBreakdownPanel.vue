<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useTaxBreakdownPanel } from '@/Composables/Contract/Show/useTaxBreakdownPanel';
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
                    Taxes générées par ce contrat
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
                <p
                    v-if="year.daysAssigned === 0"
                    class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    Contrat exonéré pour {{ year.year }} : 0 €.
                    <span class="block text-xs text-emerald-700/80 mt-0.5">
                        Aucun jour retenu au numérateur du prorata après application
                        des règles d'exonération.
                    </span>
                </p>

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
                        <p class="font-mono text-sm text-slate-600">
                            {{ formatEur(year.co2FullYearTariff) }} ×
                            {{ year.daysAssigned }} / {{ year.daysInYear }}
                            <span class="text-slate-400">=</span>
                            <span class="font-semibold text-slate-900 ml-2">
                                {{ formatEur(year.co2Due) }}
                            </span>
                        </p>
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
                        <p class="font-mono text-sm text-slate-600">
                            {{ formatEur(year.pollutantsFullYearTariff) }} ×
                            {{ year.daysAssigned }} / {{ year.daysInYear }}
                            <span class="text-slate-400">=</span>
                            <span class="font-semibold text-slate-900 ml-2">
                                {{ formatEur(year.pollutantsDue) }}
                            </span>
                        </p>
                    </section>

                    <!-- Total année -->
                    <div
                        class="flex items-center justify-between gap-2 border-t border-slate-100 pt-2"
                    >
                        <span class="text-xs text-slate-500">
                            Sous-total {{ year.year }}
                            ({{ year.daysInContractInYear }} jour{{ year.daysInContractInYear > 1 ? 's' : '' }} de contrat)
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
                class="flex items-center justify-between gap-2 rounded-lg bg-transparent px-4 py-3 shadow-[0_0_3px_silver]"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-700 uppercase"
                >
                    Total contrat
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
                :year="selectedYear"
            />
        </Modal>
    </Card>
</template>
