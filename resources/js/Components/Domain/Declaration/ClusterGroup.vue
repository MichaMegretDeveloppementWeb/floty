<script setup lang="ts">
/**
 * Boîte visuelle entourant les contrats consécutifs d'un même cluster
 * LCD à risque (Phase 11 D5.8, refondu D5.9.B/D5.9.C).
 *
 * Rôle purement présentationnel : rend le header de cluster (badges
 * niveau + cumul + véhicule + état décision + bouton « Décider » si
 * `interactive`) + les rows enfants via slot par défaut + une row de
 * fermeture pour matérialiser la fin du cluster.
 *
 * Encadrement visuel net (D5.9.C) : le header, les rows enfants et
 * la row de fermeture partagent un `bg-slate-50` continu, des bordures
 * latérales `border-x border-slate-200` et l'accent de niveau
 * (`border-l-2` rose ou amber) sur le bord gauche. L'utilisateur voit
 * d'un coup d'œil où le cluster commence et finit.
 *
 * La décision elle-même se prend via la `<ClusterDecisionModal>` ouverte
 * depuis le bouton « Décider » du header · événement `edit-decision`
 * remonté au parent (DeclarationContractList).
 */
import { CheckCircle2, Pencil, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui';

const props = withDefaults(
    defineProps<{
        riskCode: App.Enums.FiscalReviewDecision.RiskCode;
        riskLevel: App.Enums.FiscalReviewDecision.RiskLevel;
        contractsCount: number;
        cumulativeDaysInYear: number;
        /** Nombre de colonnes de la table parent (pour le colspan du header + footer). */
        colspan: number;
        /** Décision en cours pour ce cluster (`null` = à trancher). */
        decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null;
        /** Label véhicule récurrent dans la chaîne (extrait du 1er contrat). */
        vehicleLabel?: string;
        /**
         * Active le bouton « Décider » dans le header (mode Review).
         * `false` côté page Show : le cluster s'affiche en lecture
         * seule.
         */
        interactive?: boolean;
        /**
         * Justification persistée à afficher en lecture sous le header
         * (mode Show, ou mode Review quand la décision est déjà prise
         * et l'utilisateur veut juste la consulter sans rouvrir la
         * modale).
         */
        justification?: string | null;
    }>(),
    {
        interactive: false,
        vehicleLabel: undefined,
        justification: null,
    },
);

const emit = defineEmits<{
    'edit-decision': [];
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

const accentBorderClass = computed<string>(() =>
    isHighLevel.value ? 'border-l-2 border-l-rose-400' : 'border-l-2 border-l-amber-400',
);

const editButtonLabel = computed<string>(
    () => (props.decision === null ? 'Décider' : 'Modifier la décision'),
);
</script>

<template>
    <!-- Header de cluster · row dédiée avec bouton Décider à droite -->
    <tr class="bg-slate-50">
        <td
            :colspan="props.colspan"
            :class="['border-x border-t border-slate-200 px-3 py-2.5', accentBorderClass]"
        >
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
                    <Button
                        v-if="interactive"
                        size="sm"
                        variant="secondary"
                        @click="emit('edit-decision')"
                    >
                        <Pencil :size="13" :stroke-width="1.75" />
                        {{ editButtonLabel }}
                    </Button>
                </div>
            </div>
            <p
                v-if="justification && !interactive"
                class="mt-2 border-t border-slate-200 pt-2 text-xs italic text-slate-600"
            >
                {{ justification }}
            </p>
        </td>
    </tr>

    <!-- Rows enfants (ContractRow) injectés par le parent -->
    <slot />

    <!-- Row de fermeture · matérialise la bordure bas + closing pour le HTML strict -->
    <tr class="bg-slate-50">
        <td
            :colspan="props.colspan"
            :class="['border-x border-b border-slate-200 p-0', accentBorderClass]"
        >
            <div class="h-1" />
        </td>
    </tr>
</template>
