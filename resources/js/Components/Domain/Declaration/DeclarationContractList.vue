<script setup lang="ts">
/**
 * Table chronologique des contrats d'une déclaration fiscale avec
 * groupage visuel par cluster LCD à risque (Phase 11 D5.8).
 *
 * Itère sur `contractBreakdown` (déjà trié `(vehicleLabel, startDate)`
 * côté backend), détecte les transitions de `clusterFingerprint` pour
 * regrouper les contrats consécutifs partageant le même cluster dans
 * une boîte visuelle (composant `<ClusterGroup>`). Les contrats sans
 * cluster sont rendus directement comme des `<ContractRow>` isolées.
 *
 * **Mode interactif** : quand `reviewClusters` est fourni (page
 * Review), le composant affiche les actions Conserver / Requalifier
 * + champ justification sur chaque cluster. L'événement `submit` est
 * émis avec les arguments nécessaires au useReviewForm appelant.
 *
 * **Mode passif** (page Show) : `reviewClusters` est `undefined`, les
 * boutons d'action ne sont pas rendus. La décision déjà persistée
 * (portée par `contract.clusterDecision`) est affichée dans le
 * header du `ClusterGroup`.
 */
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import ClusterGroup from './ClusterGroup.vue';
import ContractRow from './ContractRow.vue';

type Contract = App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
type ReviewCluster = App.Data.User.FiscalDeclaration.ReviewClusterData;

type Group =
    | { kind: 'cluster'; fingerprint: string; contracts: Contract[] }
    | { kind: 'single'; contract: Contract };

const props = defineProps<{
    contractBreakdown: Contract[];
    /** Clusters fournis en page Review pour activer les boutons d'action. Absent en page Show (mode passif). */
    reviewClusters?: ReviewCluster[];
    submitting?: boolean;
}>();

const emit = defineEmits<{
    submit: [
        cluster: ReviewCluster,
        decision: 'conserved' | 'requalified',
        justification: string | null,
    ];
}>();

const COLSPAN = 5;

const isInteractive = computed<boolean>(() => props.reviewClusters !== undefined);

const groups = computed<Group[]>(() => {
    const result: Group[] = [];
    let currentCluster: { fingerprint: string; contracts: Contract[] } | null = null;

    for (const contract of props.contractBreakdown) {
        const fp = contract.clusterFingerprint;

        if (fp === null) {
            if (currentCluster !== null) {
                result.push({
                    kind: 'cluster',
                    fingerprint: currentCluster.fingerprint,
                    contracts: currentCluster.contracts,
                });
                currentCluster = null;
            }

            result.push({ kind: 'single', contract });

            continue;
        }

        if (currentCluster === null || currentCluster.fingerprint !== fp) {
            if (currentCluster !== null) {
                result.push({
                    kind: 'cluster',
                    fingerprint: currentCluster.fingerprint,
                    contracts: currentCluster.contracts,
                });
            }

            currentCluster = { fingerprint: fp, contracts: [contract] };
        } else {
            currentCluster.contracts.push(contract);
        }
    }

    if (currentCluster !== null) {
        result.push({
            kind: 'cluster',
            fingerprint: currentCluster.fingerprint,
            contracts: currentCluster.contracts,
        });
    }

    return result;
});

/**
 * Lookup riche du cluster côté Review (contractsCount, cumul, decision
 * pré-appliquée, justification éditable, level). Quand le cluster
 * n'existe pas dans `reviewClusters` (par exemple en page Show), on
 * dérive les méta-données minimales depuis les contrats eux-mêmes
 * (decision persistée portée par `contract.clusterDecision`).
 */
const reviewClusterByFingerprint = computed<Map<string, ReviewCluster>>(() => {
    const map = new Map<string, ReviewCluster>();

    for (const cluster of props.reviewClusters ?? []) {
        map.set(cluster.fingerprint, cluster);
    }

    return map;
});

interface ClusterMeta {
    riskCode: App.Enums.FiscalReviewDecision.RiskCode;
    riskLevel: App.Enums.FiscalReviewDecision.RiskLevel;
    contractsCount: number;
    cumulativeDaysInYear: number;
    decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null;
    justification: string | null;
    vehicleLabel: string;
}

function metaFromCluster(
    fingerprint: string,
    contracts: Contract[],
): ClusterMeta | null {
    const firstContract = contracts[0];

    if (firstContract === undefined) {
        return null;
    }

    const cluster = reviewClusterByFingerprint.value.get(fingerprint);

    if (cluster) {
        return {
            riskCode: cluster.code,
            riskLevel: cluster.level,
            contractsCount: cluster.contractsCount,
            cumulativeDaysInYear: cluster.cumulativeDaysInYear,
            decision: cluster.decision,
            justification: cluster.justification,
            vehicleLabel: firstContract.vehicleLabel,
        };
    }

    // Fallback Show : pas de cluster Review (snapshot persisté seul).
    // On déduit les méta-données depuis les contrats eux-mêmes.
    if (firstContract.clusterRiskCode === null || firstContract.clusterRiskLevel === null) {
        return null;
    }

    return {
        riskCode: firstContract.clusterRiskCode,
        riskLevel: firstContract.clusterRiskLevel,
        contractsCount: contracts.length,
        cumulativeDaysInYear: contracts.reduce(
            (acc, c) => acc + c.daysInYearAssigned,
            0,
        ),
        decision: firstContract.clusterDecision,
        justification: firstContract.clusterJustification,
        vehicleLabel: firstContract.vehicleLabel,
    };
}

/**
 * Justifications saisies en cours par fingerprint. Initialisées depuis
 * la justification existante du cluster, mises à jour à chaque saisie.
 */
const justifications = ref<Record<string, string>>({});

watch(
    () => props.reviewClusters,
    (clusters) => {
        if (clusters === undefined) {
            return;
        }

        for (const cluster of clusters) {
            if (!(cluster.fingerprint in justifications.value)) {
                justifications.value[cluster.fingerprint] = cluster.justification ?? '';
            }
        }
    },
    { immediate: true, deep: true },
);

function isHighLevel(level: App.Enums.FiscalReviewDecision.RiskLevel): boolean {
    return level === 'eleve';
}

function justificationRequired(level: App.Enums.FiscalReviewDecision.RiskLevel): boolean {
    return isHighLevel(level);
}

function handleConserve(cluster: ReviewCluster): void {
    const raw = justifications.value[cluster.fingerprint] ?? '';

    if (justificationRequired(cluster.level) && raw.trim() === '') {
        return;
    }

    emit('submit', cluster, 'conserved', raw.trim() || null);
}

function handleRequalify(cluster: ReviewCluster): void {
    const raw = justifications.value[cluster.fingerprint] ?? '';
    emit('submit', cluster, 'requalified', raw.trim() || null);
}

defineExpose({
    /** Permet à `<DeclarationClustersRecap>` de faire défiler la page jusqu'au cluster ciblé. */
    scrollToCluster(fingerprint: string): void {
        const el = document.getElementById(`cluster-${fingerprint}`);

        if (el !== null) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs font-medium tracking-wide text-slate-500 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">Période</th>
                    <th class="px-3 py-2 text-left font-medium">Type</th>
                    <th class="px-3 py-2 text-left font-medium">Véhicule</th>
                    <th class="px-3 py-2 text-right font-medium">Jours</th>
                    <th class="px-3 py-2 text-right font-medium">Taxe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template v-for="(group, idx) in groups" :key="idx">
                    <ContractRow
                        v-if="group.kind === 'single'"
                        :contract="group.contract"
                    />
                    <template v-else>
                        <template v-if="metaFromCluster(group.fingerprint, group.contracts)">
                            <ClusterGroup
                                :id="`cluster-${group.fingerprint}`"
                                :risk-code="metaFromCluster(group.fingerprint, group.contracts)!.riskCode"
                                :risk-level="metaFromCluster(group.fingerprint, group.contracts)!.riskLevel"
                                :contracts-count="metaFromCluster(group.fingerprint, group.contracts)!.contractsCount"
                                :cumulative-days-in-year="metaFromCluster(group.fingerprint, group.contracts)!.cumulativeDaysInYear"
                                :colspan="COLSPAN"
                                :decision="metaFromCluster(group.fingerprint, group.contracts)!.decision"
                                :vehicle-label="metaFromCluster(group.fingerprint, group.contracts)!.vehicleLabel"
                            >
                                <template v-if="isInteractive && reviewClusterByFingerprint.get(group.fingerprint)" #actions>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        :loading="submitting"
                                        :disabled="
                                            justificationRequired(reviewClusterByFingerprint.get(group.fingerprint)!.level)
                                                && (justifications[group.fingerprint] ?? '').trim() === ''
                                        "
                                        @click="handleConserve(reviewClusterByFingerprint.get(group.fingerprint)!)"
                                    >
                                        Conserver
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive-soft"
                                        :loading="submitting"
                                        @click="handleRequalify(reviewClusterByFingerprint.get(group.fingerprint)!)"
                                    >
                                        Requalifier en LLD
                                    </Button>
                                </template>

                                <template v-if="isInteractive && reviewClusterByFingerprint.get(group.fingerprint)" #justification-form>
                                    <TextInput
                                        :model-value="justifications[group.fingerprint] ?? ''"
                                        :label="
                                            justificationRequired(reviewClusterByFingerprint.get(group.fingerprint)!.level)
                                                ? 'Justification (obligatoire si conservée)'
                                                : 'Justification (optionnelle)'
                                        "
                                        placeholder="Décrivez le contexte qui justifie votre choix..."
                                        @update:model-value="(v: string) => { justifications[group.fingerprint] = v; }"
                                    />
                                </template>

                                <template v-else-if="metaFromCluster(group.fingerprint, group.contracts)!.justification" #justification-form>
                                    <p class="text-xs italic text-slate-600">
                                        {{ metaFromCluster(group.fingerprint, group.contracts)!.justification }}
                                    </p>
                                </template>

                                <ContractRow
                                    v-for="contract in group.contracts"
                                    :key="contract.contractId"
                                    :contract="contract"
                                />
                            </ClusterGroup>
                        </template>
                        <template v-else>
                            <ContractRow
                                v-for="contract in group.contracts"
                                :key="contract.contractId"
                                :contract="contract"
                            />
                        </template>
                    </template>
                </template>

                <tr v-if="contractBreakdown.length === 0">
                    <td :colspan="COLSPAN" class="px-3 py-4 text-center text-sm italic text-slate-500">
                        Aucun véhicule attribué sur cet exercice.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
