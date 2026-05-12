<script setup lang="ts">
/**
 * Table chronologique strictement plate des contrats d'une déclaration
 * fiscale (Phase 11 D5.8, refondu D5.10.N).
 *
 * **Direction A · suppression du groupement visuel cluster** · les
 * contrats du breakdown sont rendus à plat, dans l'ordre chronologique
 * pur reçu du backend (`(startDate, vehicleId, contractId)` ASC). La
 * matérialisation des clusters de risque LCD se fait désormais via ·
 *   - le `<DeclarationClustersRecap>` en haut de page (Review),
 *   - un badge cliquable dans la cellule TYPE de chaque `<ContractRow>`
 *     appartenant à un cluster · le badge ouvre `<ClusterDecisionModal>`
 *     pour décider/consulter.
 *
 * Cette refonte corrige le bug d'éparpillement des clusters
 * multi-véhicules (un même cluster pouvait apparaître N fois quand ses
 * contrats étaient distribués entre N véhicules dans le tri précédent).
 *
 * **Mode interactif** · `reviewClusters` fourni (page Review) · le badge
 * ouvre la modale décisionnelle.
 *
 * **Mode passif** · `reviewClusters` absent (page Show) · le badge reste
 * affiché mais l'interaction est désactivée (lecture seule de la
 * décision déjà persistée sur le contrat).
 */
import { computed, ref } from 'vue';
import ClusterDecisionModal from './ClusterDecisionModal.vue';
import ContractRow from './ContractRow.vue';

type Contract = App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
type ReviewCluster = App.Data.User.FiscalDeclaration.ReviewClusterData;

const props = defineProps<{
    contractBreakdown: Contract[];
    /** Clusters fournis en page Review pour activer la modale de décision. Absent en page Show (mode passif). */
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

const isInteractive = computed<boolean>(() => props.reviewClusters !== undefined);

/**
 * Map fingerprint → ReviewCluster pour permettre à `<ContractRow>` de
 * résoudre son cluster en O(1) et d'émettre `open-cluster` avec le
 * payload riche.
 */
const clusterByFingerprint = computed<Map<string, ReviewCluster>>(() => {
    const map = new Map<string, ReviewCluster>();
    for (const cluster of props.reviewClusters ?? []) {
        map.set(cluster.fingerprint, cluster);
    }

    return map;
});

const modalOpen = ref<boolean>(false);
const selectedCluster = ref<ReviewCluster | null>(null);

function openModalFor(cluster: ReviewCluster): void {
    selectedCluster.value = cluster;
    modalOpen.value = true;
}

function handleModalSubmit(
    decision: 'conserved' | 'requalified',
    justification: string | null,
): void {
    if (selectedCluster.value !== null) {
        emit('submit', selectedCluster.value, decision, justification);
    }

    modalOpen.value = false;
}

defineExpose({
    /**
     * Permet à `<DeclarationClustersRecap>` de faire défiler la page
     * jusqu'à la 1ère row du cluster ciblé (recherche par
     * `data-fingerprint` sur les `<tr>`).
     */
    scrollToCluster(fingerprint: string): void {
        const el = document.querySelector<HTMLElement>(`tr[data-fingerprint="${fingerprint}"]`);

        if (el !== null) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },
});
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full min-w-[820px] text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">Période</th>
                    <th class="px-3 py-2 text-left font-medium">Type</th>
                    <th class="px-3 py-2 text-left font-medium">Véhicule</th>
                    <th class="px-3 py-2 text-right font-medium">Jours</th>
                    <th class="px-3 py-2 text-right font-medium">CO₂</th>
                    <th class="px-3 py-2 text-right font-medium">Polluants</th>
                    <th class="px-3 py-2 text-right font-medium">Taxe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <ContractRow
                    v-for="contract in contractBreakdown"
                    :key="contract.contractId"
                    :contract="contract"
                    :cluster="contract.clusterFingerprint !== null
                        ? clusterByFingerprint.get(contract.clusterFingerprint) ?? null
                        : null"
                    :interactive="isInteractive"
                    @open-cluster="openModalFor"
                />

                <tr v-if="contractBreakdown.length === 0">
                    <td colspan="7" class="px-3 py-4 text-center text-sm italic text-slate-500">
                        Aucun véhicule attribué sur cet exercice.
                    </td>
                </tr>
            </tbody>
        </table>

        <ClusterDecisionModal
            v-if="isInteractive"
            v-model:open="modalOpen"
            :cluster="selectedCluster"
            :submitting="props.submitting ?? false"
            @submit="handleModalSubmit"
        />
    </div>
</template>
