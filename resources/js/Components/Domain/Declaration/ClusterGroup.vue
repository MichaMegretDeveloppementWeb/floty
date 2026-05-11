<script setup lang="ts">
/**
 * Boîte visuelle entourant les contrats consécutifs d'un même cluster
 * LCD à risque (Phase 11 D5.8). Bordure colorée gauche selon le
 * niveau (rose = élevé, amber = moyen), header avec badge cluster +
 * résumé chiffré + slot d'actions (Conserver / Requalifier + champ
 * justification quand actif).
 *
 * Conçu pour s'intégrer dans une table : le `<tbody>` parent doit
 * contenir un `<tr>` pour le header (slot `#header-row` rendu en
 * interne) puis les `<tr>` enfants (slot par défaut). La cellule
 * header est sur toute la largeur via `colspan`.
 *
 * Source unique de vérité visuelle : un cluster apparaît une seule
 * fois dans la déclaration, à l'endroit où ses contrats apparaissent
 * dans la chronologie (groupe naturellement adjacent grâce au tri
 * `(vehicleLabel, startDate)` côté backend).
 */
import { CheckCircle2, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui';

const props = defineProps<{
    riskCode: App.Enums.FiscalReviewDecision.RiskCode;
    riskLevel: App.Enums.FiscalReviewDecision.RiskLevel;
    contractsCount: number;
    cumulativeDaysInYear: number;
    /** Nombre de colonnes de la table parent (pour le colspan du header). */
    colspan: number;
    /** Décision en cours pour ce cluster (`null` = à trancher). */
    decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null;
    /** Optionnel : label véhicule récurrent dans la chaîne (extrait du 1er contrat). */
    vehicleLabel?: string;
}>();

const isHighLevel = computed<boolean>(() => props.riskLevel === 'eleve');

const codeLabel = computed<string>(() =>
    props.riskCode === 'R-LCD-CHAIN-FORT' ? 'Chaîne LCD forte' : 'Chaîne LCD',
);

const levelTone = computed<StatusTone>(() => (isHighLevel.value ? 'rose' : 'amber'));
const levelLabel = computed<string>(() => (isHighLevel.value ? 'Risque élevé' : 'Risque moyen'));

const decisionPill = computed<{ tone: StatusTone; label: string } | null>(() => {
    if (props.decision === 'conserved') {
        return { tone: 'emerald', label: 'Conservée' };
    }

    if (props.decision === 'requalified') {
        return { tone: 'rose', label: 'Requalifiée' };
    }

    return null;
});

const borderClass = computed<string>(() =>
    isHighLevel.value ? 'border-l-2 border-l-rose-400' : 'border-l-2 border-l-amber-400',
);
</script>

<template>
    <tr :class="['bg-slate-50', borderClass]">
        <td :colspan="props.colspan" class="px-3 py-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <component
                        :is="isHighLevel ? ShieldAlert : ShieldCheck"
                        :size="16"
                        :stroke-width="1.75"
                        :class="isHighLevel ? 'text-rose-500' : 'text-amber-500'"
                    />
                    <span class="text-sm font-semibold text-slate-900">
                        {{ codeLabel }}
                    </span>
                    <StatusPill :tone="levelTone">{{ levelLabel }}</StatusPill>
                    <span class="text-xs text-slate-500">
                        {{ contractsCount }} contrats LCD · cumul {{ cumulativeDaysInYear }}
                        jour<template v-if="cumulativeDaysInYear > 1">s</template>
                        <template v-if="vehicleLabel"> · {{ vehicleLabel }}</template>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <StatusPill v-if="decisionPill !== null" :tone="decisionPill.tone">
                        <CheckCircle2 :size="12" :stroke-width="1.75" />
                        {{ decisionPill.label }}
                    </StatusPill>
                    <StatusPill v-else tone="amber">À trancher</StatusPill>
                    <slot name="actions" />
                </div>
            </div>
            <div v-if="$slots['justification-form']" class="mt-2 border-t border-slate-200 pt-2">
                <slot name="justification-form" />
            </div>
        </td>
    </tr>
    <slot />
    <tr :class="[borderClass, 'h-1']" />
</template>
