<script setup lang="ts">
/**
 * Onglet « Fiscalité » de la page Show Company (chantier N.2, refondu
 * Phase 11 D5.8.4 et allégé Phase 12 D5.9.A).
 *
 * Architecture :
 * - Header simple (titre + badge année en cours + pills de sélection
 *   d'année). Les stats détaillées sont remontées dans la carte recap.
 * - `<CompanyFiscalRecapCard>` : montant des taxes + détail CO₂ /
 *   Polluants + 3 stats (jours cumulés, véhicules taxés, locations)
 *   + lien contextuel vers l'onglet Locations filtré sur l'exercice.
 * - `<DeclarationStateCard>` : carte adaptative (S1 vierge ... S7
 *   régénération) qui pilote toute la lecture déclarative côté
 *   utilisateur.
 *
 * Le sélecteur d'année est **local et indépendant** (ADR-0020 D3) :
 * préfixe URL `?fiscalYear=`, aucun lien avec le sélecteur global
 * ni avec celui de l'onglet Locations.
 *
 * L'ancien tableau « Détail par véhicule » a été retiré en D5.9.A
 * (info déjà disponible sur l'onglet Locations, doublon visuel).
 */
import { computed } from 'vue';
import DeclarationStateCard from '@/Components/Domain/Declaration/DeclarationStateCard.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { useCompanyFiscalSelectedYear } from '@/Composables/Company/Show/useCompanyFiscalSelectedYear';
import YearPills from '@/Components/Ui/YearPills/YearPills.vue';
import CompanyFiscalRecapCard from './CompanyFiscalRecapCard.vue';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyId: number;
    declarationLifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
}>();

const { selectedYear, selectYear, loading: yearLoading } = useCompanyFiscalSelectedYear(
    props.fiscal.year,
);

const isCurrentYear = computed<boolean>(
    () => selectedYear.value === props.fiscal.currentRealYear,
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Header simple : titre + pills années -->
        <Card>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h3 class="text-base font-semibold text-slate-900">
                        Fiscalité
                        <span
                            v-if="isCurrentYear"
                            class="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                            title="Exercice fiscal en cours · chiffres provisoires"
                        >
                            En cours
                        </span>
                    </h3>
                    <p class="text-sm text-slate-500">
                        Exercice {{ props.fiscal.year }}
                    </p>
                </div>

                <YearPills
                    v-if="props.fiscal.availableYears.length > 0"
                    :years="props.fiscal.availableYears"
                    :active-year="selectedYear"
                    :loading="yearLoading"
                    @select="selectYear"
                />
            </div>
        </Card>

        <!-- Carte recap taxes (D5.9.A) -->
        <CompanyFiscalRecapCard
            :fiscal="props.fiscal"
            :company-id="props.companyId"
        />

        <!-- Carte adaptative déclaration S1..S7 -->
        <DeclarationStateCard
            :lifecycle="props.declarationLifecycle"
            :company-id="props.companyId"
            :fiscal-year="props.fiscal.year"
        />
    </div>
</template>
