<script setup lang="ts">
/**
 * Boîte visuelle entourant les contrats consécutifs d'un même cluster
 * LCD à risque (Phase 11 D5.8, refondu D5.9.B/D5.9.C, sémantique
 * plage couverte D5.10.N).
 *
 * Rôle purement présentationnel · rend le header de cluster (badges
 * niveau + plage couverte + nombre de véhicules + état décision +
 * bouton « Décider » si `interactive`) + les rows enfants via slot
 * par défaut + une row de fermeture pour matérialiser la fin du
 * cluster.
 *
 * Encadrement visuel net (D5.9.C) · le header, les rows enfants et
 * la row de fermeture partagent un `bg-slate-50` continu, des bordures
 * latérales `border-x border-slate-200` et l'accent de niveau
 * (`border-l-2` rose ou amber) sur le bord gauche. L'utilisateur voit
 * d'un coup d'œil où le cluster commence et finit.
 *
 * Le groupement physique des contrats du cluster est garanti par le
 * tri snapshot strictement chronologique côté `DeclarationFiscalEngine`
 * combiné à la définition métier d'une chaîne (LCD consécutifs
 * temporellement proches) · les contrats du cluster sont naturellement
 * contigus, le groupement par contiguïté de fingerprint dans
 * `<DeclarationContractList>` ne casse plus pour les chaînes
 * multi-véhicules.
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
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = withDefaults(
    defineProps<{
        riskCode: App.Enums.FiscalReviewDecision.RiskCode;
        riskLevel: App.Enums.FiscalReviewDecision.RiskLevel;
        contractsCount: number;
        /** Phase 13 D5.10.N · plage couverte en jours (= max_end − min_start + 1, bornée année). */
        coveragePeriodDays: number;
        /** Phase 13 D5.10.N · date de début effective bornée à l'année (ISO Y-m-d). */
        coverageStartDate: string;
        /** Phase 13 D5.10.N · date de fin effective bornée à l'année (ISO Y-m-d). */
        coverageEndDate: string;
        /** Phase 13 D5.10.N · nombre de véhicules distincts touchés par la chaîne. */
        distinctVehiclesCount: number;
        /** Nombre de colonnes de la table parent (pour le colspan du header + footer). */
        colspan: number;
        /** Décision en cours pour ce cluster (`null` = à trancher). */
        decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null;
        /**
         * Active le bouton « Décider » dans le header (mode Review).
         * `false` côté page Show · le cluster s'affiche en lecture
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
        /**
         * Phase 13 D5.10.P · id HTML à appliquer sur le premier <tr>
         * (header) pour permettre à `scrollToCluster()` de cibler le
         * cluster · un composant multi-root Vue 3 ne propage pas les
         * attributs implicites donc on passe explicitement via prop.
         */
        clusterId?: string;
    }>(),
    {
        interactive: false,
        justification: null,
        clusterId: undefined,
    },
);

const emit = defineEmits<{
    'edit-decision': [];
}>();

const isHighLevel = computed<boolean>(() => props.riskLevel === 'eleve');

// La distinction R-LCD-CHAIN vs R-LCD-CHAIN-FORT est déjà portée par
// le pill « Risque élevé » / « Risque moyen » juste à côté · pas besoin
// de la dédoubler dans le libellé du code.
const codeLabel = computed<string>(() => 'LCD successifs');

const levelTone = computed<StatusTone>(() => (isHighLevel.value ? 'rose' : 'amber'));
const levelLabel = computed<string>(() => (isHighLevel.value ? 'Risque élevé' : 'Risque moyen'));

const decisionPill = computed<{ tone: StatusTone; label: string } | null>(() => {
    if (props.decision === 'conserved') {
        return { tone: 'emerald', label: 'LCD maintenue' };
    }

    if (props.decision === 'requalified') {
        return { tone: 'rose', label: 'Requalifiée LLD' };
    }

    return null;
});

const accentBorderClass = computed<string>(() =>
    isHighLevel.value ? 'border-l-2 border-l-rose-400' : 'border-l-2 border-l-amber-400',
);

const editButtonLabel = computed<string>(
    () => (props.decision === null ? 'Arbitrer' : 'Réviser l\'arbitrage'),
);

const vehiclesLabel = computed<string>(() =>
    props.distinctVehiclesCount > 1
        ? `${props.distinctVehiclesCount} véhicules`
        : '1 véhicule',
);
</script>

<template>
    <!-- Header de cluster · row dédiée avec bouton Décider à droite -->
    <tr :id="clusterId" class="bg-slate-50">
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
                        {{ contractsCount }} contrats du
                        {{ formatDateFr(coverageStartDate) }} au
                        {{ formatDateFr(coverageEndDate) }} ·
                        {{ coveragePeriodDays }} jours couverts ·
                        {{ vehiclesLabel }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <StatusPill v-if="decisionPill !== null" :tone="decisionPill.tone">
                        <CheckCircle2 :size="12" :stroke-width="1.75" />
                        {{ decisionPill.label }}
                    </StatusPill>
                    <StatusPill v-else tone="amber">À arbitrer</StatusPill>
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
