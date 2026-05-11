<script setup lang="ts">
/**
 * Bandeau récapitulatif sticky des clusters de la déclaration en revue
 * (Phase 11 D5.8). Permet à l'utilisateur de visualiser et trancher
 * les décisions sans avoir à scroller jusqu'au cluster correspondant
 * dans le tableau breakdown.
 *
 * Affiche uniquement les clusters `pending` (sans décision) pour ne
 * pas encombrer la vue après que les décisions ont été prises. Quand
 * tous les clusters sont tranchés, le récapitulatif disparaît.
 *
 * Sticky top + backdrop-blur : reste visible pendant le scroll, ne
 * masque pas le contenu derrière.
 */
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui';

const props = defineProps<{
    clusters: App.Data.User.FiscalDeclaration.ReviewClusterData[];
    submitting: boolean;
}>();

const emit = defineEmits<{
    /** Émis quand l'utilisateur clique « Requalifier » sur un cluster. La justification facultative est saisie en dessous, dans le ClusterGroup principal. */
    quickRequalify: [fingerprint: string];
    /** Émis quand l'utilisateur clique « Voir le cluster » pour scroller jusqu'à lui. */
    scrollTo: [fingerprint: string];
}>();

const pendingClusters = computed<App.Data.User.FiscalDeclaration.ReviewClusterData[]>(
    () => props.clusters.filter((c) => c.decision === null),
);

const hasPending = computed<boolean>(() => pendingClusters.value.length > 0);

function levelTone(level: App.Enums.FiscalReviewDecision.RiskLevel): StatusTone {
    return level === 'eleve' ? 'rose' : 'amber';
}

function levelLabel(level: App.Enums.FiscalReviewDecision.RiskLevel): string {
    return level === 'eleve' ? 'Risque élevé' : 'Risque moyen';
}

function codeLabel(code: App.Enums.FiscalReviewDecision.RiskCode): string {
    return code === 'R-LCD-CHAIN-FORT' ? 'Chaîne forte' : 'Chaîne';
}

function vehicleSummary(cluster: App.Data.User.FiscalDeclaration.ReviewClusterData): string {
    const plate = cluster.contracts[0]?.vehiclePlate;

    if (!plate) {
        return '';
    }

    return plate;
}
</script>

<template>
    <div
        v-if="hasPending"
        class="sticky top-3 z-10 flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50/95 p-3 shadow-sm backdrop-blur"
    >
        <div class="flex items-center gap-2">
            <ShieldAlert :size="16" :stroke-width="1.75" class="text-amber-600" />
            <p class="text-sm font-semibold text-amber-900">
                {{ pendingClusters.length }}
                décision<template v-if="pendingClusters.length > 1">s</template>
                à prendre avant génération
            </p>
        </div>
        <ul class="flex flex-wrap gap-2">
            <li
                v-for="cluster in pendingClusters"
                :key="cluster.fingerprint"
                class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-white px-3 py-2"
            >
                <component
                    :is="cluster.level === 'eleve' ? ShieldAlert : ShieldCheck"
                    :size="14"
                    :stroke-width="1.75"
                    :class="cluster.level === 'eleve' ? 'text-rose-600' : 'text-amber-600'"
                />
                <span class="text-xs font-medium text-slate-900">
                    {{ codeLabel(cluster.code) }}
                </span>
                <StatusPill :tone="levelTone(cluster.level)">
                    {{ levelLabel(cluster.level) }}
                </StatusPill>
                <span class="text-[11px] text-slate-500">
                    <template v-if="vehicleSummary(cluster)">{{ vehicleSummary(cluster) }} · </template>
                    {{ cluster.cumulativeDaysInYear }}j cumul
                </span>
                <Button size="sm" variant="ghost" :disabled="submitting" @click="emit('scrollTo', cluster.fingerprint)">
                    Voir le cluster
                </Button>
                <Button
                    size="sm"
                    variant="destructive-soft"
                    :disabled="submitting"
                    @click="emit('quickRequalify', cluster.fingerprint)"
                >
                    Requalifier
                </Button>
            </li>
        </ul>
    </div>
</template>
