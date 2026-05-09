<script setup lang="ts">
/**
 * Page Review d'une déclaration fiscale (Phase 11 D4 + D5.6). Pilote
 * la génération sur place : pour chaque cluster détecté par D2 +
 * pré-appliqué par D3, l'utilisateur tranche conserver / requalifier
 * (justification obligatoire si conserver + niveau élevé). La barre
 * d'actions sticky en bas propose « Mettre de côté » et « Générer ».
 *
 * Le récapitulatif fiscal (FiscalSummaryCard, D5.6) prévisualise les
 * totaux post-décisions : c'est exactement ce que le PDF généré
 * contiendra si l'utilisateur clique « Générer ».
 */
import { Head } from '@inertiajs/vue3';
import { Building2, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import ClusterCard from '@/Components/Domain/Declaration/ClusterCard.vue';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useReviewForm } from '@/Composables/Declaration/useReviewForm';
import ReviewActionsBar from './partials/ReviewActionsBar.vue';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    preview: App.Data.User.FiscalDeclaration.DeclarationPreviewData;
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
}>();

const { submitting, submitDecision } = useReviewForm(props.declaration.id);

const isDeferred = computed<boolean>(() => props.declaration.status === 'deferred');

function handleSubmit(
    cluster: App.Data.User.FiscalDeclaration.ReviewClusterData,
    decision: 'conserved' | 'requalified',
    justification: string | null,
): void {
    submitDecision({
        companyId: props.declaration.companyId,
        fiscalYear: props.declaration.fiscalYear,
        riskCode: cluster.code,
        clusterFingerprint: cluster.fingerprint,
        decision,
        justification,
    });
}
</script>

<template>
    <Head :title="`Revue ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6 pb-24">
            <header class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <Building2 :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                        Revue fiscale
                    </p>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        {{ declaration.companyShortCode }} · {{ declaration.fiscalYear }}
                    </h1>
                    <p class="text-sm text-slate-500">{{ declaration.companyLegalName }}</p>
                </div>
            </header>

            <FiscalSummaryCard :snapshot="snapshot" />

            <div class="flex flex-col gap-2">
                <h2 class="text-base font-semibold text-slate-900">
                    Clusters de risque détectés
                </h2>
                <p class="text-xs text-slate-500">
                    Chaque cluster doit être tranché avant la génération. Une
                    décision déjà prise sur une déclaration précédente est
                    automatiquement reprise par fingerprint.
                </p>
            </div>

            <div v-if="preview.clusters.length === 0" class="flex flex-col items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-emerald-600">
                    <ShieldCheck :size="24" :stroke-width="1.75" />
                </div>
                <p class="text-sm font-semibold text-emerald-900">
                    Aucun cluster de risque détecté
                </p>
                <p class="max-w-md text-xs text-emerald-800">
                    Le périmètre fiscal de cette année ne révèle aucune chaîne
                    LCD nécessitant une revue. Vous pouvez générer la déclaration
                    directement.
                </p>
            </div>

            <ClusterCard
                v-for="cluster in preview.clusters"
                :key="cluster.fingerprint"
                :cluster="cluster"
                :submitting="submitting"
                @submit="(decision, justification) => handleSubmit(cluster, decision, justification)"
            />

            <ReviewActionsBar
                :declaration-id="declaration.id"
                :pending-clusters-count="preview.pendingClustersCount"
                :can-generate="preview.canGenerate"
                :is-deferred="isDeferred"
            />
        </div>
    </UserLayout>
</template>
