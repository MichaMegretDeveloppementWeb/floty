<script setup lang="ts">
/**
 * Bandeau récapitulatif des clusters de la déclaration en revue
 * (Phase 11 D5.8 · refonte design 2026-05-17).
 *
 * Affiche **tous** les clusters de risque détectés (pas seulement les
 * pending) · permet à l'utilisateur de garder une vue d'ensemble
 * persistante des chaînes LCD, peu importe leur état d'arbitrage. Le
 * titre s'adapte · « N arbitrage(s) requis avant génération » quand
 * il en reste, « N cluster(s) de risque LCD · tous arbitrés » sinon.
 *
 * Pas d'action d'arbitrage directe ici · seul un bouton « Voir le
 * cluster » (style `secondary`) qui scrolle vers le cluster dans le
 * tableau breakdown. L'arbitrage réel se fait via la modale
 * `<ClusterDecisionModal>` ouverte depuis le `<ClusterGroup>` du
 * tableau (cohérence UX · une seule entrée d'action).
 *
 * Layout · items pleine largeur (un par ligne) avec espacement
 * généreux entre les éléments. Ordre cohérent avec l'entête cluster
 * du tableau breakdown · icône + nom + risque + coverage à gauche,
 * pill décision + bouton « Voir le cluster » à droite.
 */
import { CheckCircle2, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    clusters: App.Data.User.FiscalDeclaration.ReviewClusterData[];
    submitting: boolean;
}>();

const emit = defineEmits<{
    /** Émis quand l'utilisateur clique « Voir le cluster » pour scroller jusqu'à lui. */
    scrollTo: [fingerprint: string];
}>();

const pendingCount = computed<number>(
    () => props.clusters.filter((c) => c.decision === null).length,
);

const totalCount = computed<number>(() => props.clusters.length);

const headerLabel = computed<string>(() => {
    if (pendingCount.value > 0) {
        const plural = pendingCount.value > 1 ? 's' : '';

        return `${pendingCount.value} arbitrage${plural} requis avant génération`;
    }

    const plural = totalCount.value > 1 ? 's' : '';

    return `${totalCount.value} cluster${plural} de risque LCD · tous arbitrés`;
});

function levelTone(level: App.Enums.FiscalReviewDecision.RiskLevel): StatusTone {
    return level === 'eleve' ? 'rose' : 'amber';
}

function levelLabel(level: App.Enums.FiscalReviewDecision.RiskLevel): string {
    return level === 'eleve' ? 'Risque élevé' : 'Risque moyen';
}

type DecisionPill = { tone: StatusTone; label: string; showCheck: boolean };

function decisionPill(
    decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null,
): DecisionPill {
    if (decision === 'conserved') {
        return { tone: 'emerald', label: 'LCD maintenue', showCheck: true };
    }

    if (decision === 'requalified') {
        return { tone: 'rose', label: 'Requalifiée LLD', showCheck: true };
    }

    return { tone: 'amber', label: 'À arbitrer', showCheck: false };
}

/**
 * Phase 13 D5.10.N · libellé plage couverte + nb véhicules · l'ancien
 * `vehicleSummary` ne renvoyait que la 1ère plaque (faux pour un
 * cluster multi-véhicules). On utilise désormais `distinctVehiclesCount`
 * + dates de plage canoniques exposées par le backend.
 */
function coverageSummary(cluster: App.Data.User.FiscalDeclaration.ReviewClusterData): string {
    const period = `du ${formatDateFr(cluster.coverageStartDate)} au ${formatDateFr(cluster.coverageEndDate)}`;
    const vehicles = cluster.distinctVehiclesCount > 1
        ? `${cluster.distinctVehiclesCount} véhicules`
        : '1 véhicule';

    return `${cluster.contractsCount} contrats ${period} · ${cluster.coveragePeriodDays} jours couverts · ${vehicles}`;
}
</script>

<template>
    <div
        v-if="totalCount > 0"
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
    >
        <div class="flex items-center gap-2">
            <ShieldAlert :size="16" :stroke-width="1.75" class="text-slate-600" />
            <p class="text-sm font-semibold text-slate-900">
                {{ headerLabel }}
            </p>
        </div>
        <ul class="flex flex-col gap-2">
            <li
                v-for="cluster in clusters"
                :key="cluster.fingerprint"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <component
                        :is="cluster.level === 'eleve' ? ShieldAlert : ShieldCheck"
                        :size="16"
                        :stroke-width="1.75"
                        :class="cluster.level === 'eleve' ? 'text-rose-500' : 'text-amber-500'"
                    />
                    <span class="text-sm font-semibold text-slate-900">
                        LCD successifs
                    </span>
                    <StatusPill :tone="levelTone(cluster.level)">
                        {{ levelLabel(cluster.level) }}
                    </StatusPill>
                    <span class="text-xs text-slate-500">
                        {{ coverageSummary(cluster) }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <StatusPill :tone="decisionPill(cluster.decision).tone">
                        <CheckCircle2
                            v-if="decisionPill(cluster.decision).showCheck"
                            :size="12"
                            :stroke-width="1.75"
                        />
                        {{ decisionPill(cluster.decision).label }}
                    </StatusPill>
                    <Button
                        size="sm"
                        variant="secondary"
                        :disabled="submitting"
                        @click="emit('scrollTo', cluster.fingerprint)"
                    >
                        Voir le cluster
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
