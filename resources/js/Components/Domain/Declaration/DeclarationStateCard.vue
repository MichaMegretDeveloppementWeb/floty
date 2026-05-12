<script setup lang="ts">
/**
 * Carte adaptative du cycle de vie d'une déclaration fiscale annuelle
 * pour un couple `(company, year)` (Phase 11 D5.8.4, Proposition IV,
 * refondu aérée D5.10.I).
 *
 * Rend une carte différente selon `lifecycle.state` :
 *   - S1 Untouched : invitation à préparer.
 *   - S2 DraftPending : carte « En cours » avec compteur de décisions
 *     à trancher + CTA Reprendre la revue.
 *   - S3 DraftReadyToGenerate : carte « Prête à générer » + CTA.
 *   - S4 Deferred : carte « Mise de côté » + CTA Reprendre.
 *   - S4-bis DeferredRegeneration : régénération mise de côté avec
 *     contexte predecessor obsolète.
 *   - S5 GeneratedActive : carte succès avec ref + date + actions
 *     Ouvrir / Télécharger PDF + timeline.
 *   - S6 GeneratedObsoleteOrphan : carte d'alerte ostensible avec
 *     liste des motifs d'obsolescence + CTA Régénérer.
 *   - S7 RegenerationInProgress : carte « Régénération en cours »
 *     pointant vers le Draft chaîné + lien vers la version obsolète.
 *
 * **Aération D5.10.I** · icônes 12×12, titres text-base, descriptions
 * text-sm leading-relaxed (phrases développées), gap-4/5 entre
 * sections internes. Exploite la place disponible pour raconter
 * l'histoire de chaque état sans phrases hachées.
 */
import { Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    Download,
    FileCheck2,
    FilePlus2,
    FileText,
    LoaderCircle,
    Recycle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import {
    download as downloadDeclarationRoute,
    prepare as prepareDeclarationRoute,
    regenerate as regenerateDeclarationRoute,
    review as reviewDeclarationRoute,
    show as showDeclarationRoute,
} from '@/routes/user/declarations';
import {
    formatInvalidationOccurredAt,
    formatInvalidationReason,
} from '@/Utils/format/invalidationReason';
import DeclarationHistoryTimeline from './DeclarationHistoryTimeline.vue';

const props = defineProps<{
    lifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
    companyId: number;
    fiscalYear: number;
}>();

const preparing = ref<boolean>(false);
const regenerating = ref<boolean>(false);

const current = computed(() => props.lifecycle.currentDeclaration);
const predecessor = computed(() => props.lifecycle.predecessorDeclaration);
const reasons = computed(() => props.lifecycle.obsoleteReasons);
const reasonsToShow = computed(() => reasons.value.slice(0, 3));
const extraReasonsCount = computed(() => Math.max(reasons.value.length - 3, 0));
const firstReasonOccurredAt = computed(() => reasons.value[0]?.occurredAt ?? null);

const state = computed(() => props.lifecycle.state);

const generatedAtFormatted = computed<string | null>(() => {
    if (current.value?.generatedAt === null || current.value?.generatedAt === undefined) {
        return null;
    }

    return new Date(current.value.generatedAt).toLocaleDateString('fr-FR');
});

function handlePrepare(): void {
    if (preparing.value) {
        return;
    }

    preparing.value = true;
    router.post(
        prepareDeclarationRoute.url(),
        {
            company_id: props.companyId,
            fiscal_year: props.fiscalYear,
        },
        {
            onFinish: () => {
                preparing.value = false;
            },
        },
    );
}

function handleRegenerate(): void {
    if (regenerating.value || current.value === null) {
        return;
    }

    regenerating.value = true;
    router.post(
        regenerateDeclarationRoute.url({ declaration: current.value.id }),
        {},
        {
            onFinish: () => {
                regenerating.value = false;
            },
        },
    );
}
</script>

<template>
    <!-- S1 · aucune déclaration préparée -->
    <Card v-if="state === 'untouched'">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                >
                    <FilePlus2 :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <h4 class="text-lg font-semibold text-slate-900">
                        Déclaration {{ fiscalYear }}
                    </h4>
                    <p class="text-base leading-relaxed text-slate-600">
                        Aucune déclaration n'a encore été préparée pour cet exercice.
                        L'écran de revue vous permet de trancher les éventuels clusters
                        de risque (chaînes LCD requalifiables en LLD) avant la génération
                        du document définitif.
                    </p>
                </div>
            </div>
            <Button :disabled="preparing" class="shrink-0" @click="handlePrepare">
                <LoaderCircle v-if="preparing" :size="16" :stroke-width="1.75" class="animate-spin" />
                <FileCheck2 v-else :size="16" :stroke-width="1.75" />
                {{ preparing ? 'Préparation…' : 'Préparer la déclaration' }}
            </Button>
        </div>
    </Card>

    <!-- S2 + S3 · Draft (en cours / prêt à générer) -->
    <Card v-else-if="(state === 'draft_pending' || state === 'draft_ready_to_generate') && current">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-600"
                >
                    <FileText :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="text-lg font-semibold text-slate-900">
                            Déclaration {{ fiscalYear }}
                        </h4>
                        <StatusPill tone="slate">Brouillon</StatusPill>
                    </div>
                    <p v-if="state === 'draft_pending'" class="text-base leading-relaxed text-slate-600">
                        La préparation est en cours. Il reste
                        <strong class="font-semibold text-slate-900">{{ lifecycle.pendingClustersCount }}
                        décision<template v-if="lifecycle.pendingClustersCount > 1">s</template></strong>
                        à trancher dans l'écran de revue avant de pouvoir générer la
                        déclaration. Les décisions déjà prises sont conservées.
                    </p>
                    <p v-else class="text-base leading-relaxed text-emerald-700">
                        Toutes les décisions de revue sont prises · la déclaration peut
                        désormais être générée depuis l'écran de revue. Le document
                        produit sera figé et conservé en annexe.
                    </p>
                </div>
            </div>
            <Link :href="reviewDeclarationRoute.url({ declaration: current.id })" class="shrink-0">
                <Button>
                    <FileText :size="16" :stroke-width="1.75" />
                    Reprendre la revue
                </Button>
            </Link>
        </div>
    </Card>

    <!-- S4 · Mise de côté -->
    <Card v-else-if="state === 'deferred' && current">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                >
                    <Clock :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="text-lg font-semibold text-slate-900">
                            Déclaration {{ fiscalYear }}
                        </h4>
                        <StatusPill tone="amber">Mise de côté</StatusPill>
                    </div>
                    <p class="text-base leading-relaxed text-slate-600">
                        La préparation a été volontairement mise en pause. Vous pouvez
                        la reprendre à tout moment depuis l'écran de revue, aucune
                        décision déjà tranchée ne sera perdue.
                    </p>
                </div>
            </div>
            <Link :href="reviewDeclarationRoute.url({ declaration: current.id })" class="shrink-0">
                <Button>
                    <FileText :size="16" :stroke-width="1.75" />
                    Reprendre la revue
                </Button>
            </Link>
        </div>
    </Card>

    <!-- S4-bis · Mise de côté · régénération en attente (Phase 13 D5.10.H, aérée D5.10.I) -->
    <Card v-else-if="state === 'deferred_regeneration' && current">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                    >
                        <Clock :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-lg font-semibold text-slate-900">
                                Régénération de la déclaration {{ fiscalYear }}
                            </h4>
                            <StatusPill tone="amber">Mise de côté</StatusPill>
                        </div>
                        <p class="text-base leading-relaxed text-slate-600">
                            Le brouillon de régénération a été volontairement mis de côté.
                            La déclaration précédente reste obsolète tant que la nouvelle
                            version n'est pas générée. Reprenez la revue à tout moment
                            pour finaliser la régénération.
                        </p>
                        <p v-if="predecessor?.reference" class="font-mono text-sm text-slate-500">
                            Remplace
                            <span class="font-medium text-slate-700">{{ predecessor.reference }}</span>
                            <template v-if="firstReasonOccurredAt">
                                · obsolète depuis le
                                {{ formatInvalidationOccurredAt(firstReasonOccurredAt) }}
                            </template>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                    <Link
                        v-if="predecessor"
                        :href="showDeclarationRoute.url({ declaration: predecessor.id })"
                        class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-900 hover:underline"
                    >
                        Voir la version obsolète
                        <ArrowUpRight :size="12" :stroke-width="1.75" />
                    </Link>
                    <Link :href="reviewDeclarationRoute.url({ declaration: current.id })">
                        <Button>
                            <FileText :size="16" :stroke-width="1.75" />
                            Reprendre la régénération
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </Card>

    <!-- S5 · Generated active -->
    <Card v-else-if="state === 'generated_active' && current">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                    >
                        <CheckCircle2 :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-lg font-semibold text-slate-900">
                                Déclaration {{ fiscalYear }}
                            </h4>
                            <StatusPill tone="emerald">Générée</StatusPill>
                        </div>
                        <p class="text-base leading-relaxed text-slate-600">
                            La déclaration a été générée et figée. Le document PDF est
                            disponible en téléchargement. Le périmètre est à jour, aucune
                            mutation contractuelle ne l'a invalidée depuis la génération.
                        </p>
                        <p v-if="current.reference" class="font-mono text-sm text-slate-500">
                            <span class="font-medium text-slate-700">{{ current.reference }}</span>
                            <template v-if="generatedAtFormatted">
                                · générée le {{ generatedAtFormatted }}
                            </template>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                    <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                        <Button variant="secondary">
                            <FileText :size="16" :stroke-width="1.75" />
                            Ouvrir
                        </Button>
                    </Link>
                    <a
                        :href="downloadDeclarationRoute.url({ declaration: current.id })"
                        class="inline-flex h-[34px] cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-base font-medium leading-none text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
                    >
                        <Download :size="16" :stroke-width="1.75" />
                        Télécharger PDF
                    </a>
                </div>
            </div>

            <DeclarationHistoryTimeline
                v-if="lifecycle.historyChain.length > 0 && current"
                :entries="[current, ...lifecycle.historyChain]"
                :current-declaration-id="current.id"
                class="border-t border-slate-100 pt-4"
            />
        </div>
    </Card>

    <!-- S6 · Generated obsolète orphan (la version est périmée, pas de Draft chaîné) -->
    <Card v-else-if="state === 'generated_obsolete_orphan' && current">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600"
                    >
                        <AlertTriangle :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-lg font-semibold text-slate-900">
                                Déclaration {{ fiscalYear }}
                            </h4>
                            <StatusPill tone="rose">Générée · obsolète</StatusPill>
                        </div>
                        <p class="text-base leading-relaxed text-slate-600">
                            La déclaration a été générée mais le périmètre fiscal a évolué
                            depuis · elle est désormais obsolète et doit être régénérée
                            pour refléter le calcul à jour. Le PDF historique reste
                            consultable pour archive.
                        </p>
                        <p v-if="current.reference" class="font-mono text-sm text-slate-500">
                            <span class="font-medium text-slate-700">{{ current.reference }}</span>
                            <template v-if="generatedAtFormatted">
                                · générée le {{ generatedAtFormatted }}
                            </template>
                        </p>
                    </div>
                </div>
                <Link :href="showDeclarationRoute.url({ declaration: current.id })" class="sm:shrink-0">
                    <Button variant="secondary">
                        <FileText :size="16" :stroke-width="1.75" />
                        Ouvrir
                    </Button>
                </Link>
            </div>

            <div class="rounded-md border border-slate-200 border-l-2 border-l-rose-400 bg-slate-50 p-4">
                <p class="text-lg font-semibold text-slate-900">
                    Cette déclaration est périmée
                </p>
                <p class="mt-1.5 text-base leading-relaxed text-slate-600">
                    Le périmètre fiscal {{ fiscalYear }} a évolué depuis la génération.
                    Régénérer crée un nouveau brouillon de revue dans lequel les
                    décisions inchangées sont automatiquement reprises par fingerprint.
                </p>
                <ul
                    v-if="reasonsToShow.length > 0"
                    class="mt-3 flex flex-col gap-2"
                >
                    <li
                        v-for="(reason, index) in reasonsToShow"
                        :key="index"
                        class="flex items-baseline gap-2 text-base text-slate-700"
                    >
                        <span class="inline-block size-1.5 shrink-0 translate-y-[-1px] rounded-full bg-rose-400" />
                        <span>
                            <span class="font-medium">{{ formatInvalidationReason(reason) }}</span>
                            <span class="text-slate-500">
                                · {{ formatInvalidationOccurredAt(reason.occurredAt) }}
                            </span>
                        </span>
                    </li>
                    <li v-if="extraReasonsCount > 0" class="pl-3 text-sm italic text-slate-500">
                        +{{ extraReasonsCount }} autre<template v-if="extraReasonsCount > 1">s</template>
                        motif<template v-if="extraReasonsCount > 1">s</template>
                    </li>
                </ul>
                <div class="mt-4">
                    <Button variant="destructive-soft" :disabled="regenerating" @click="handleRegenerate">
                        <LoaderCircle
                            v-if="regenerating"
                            :size="16"
                            :stroke-width="1.75"
                            class="animate-spin"
                        />
                        <Recycle v-else :size="16" :stroke-width="1.75" />
                        {{ regenerating ? 'Création du brouillon…' : 'Régénérer la déclaration' }}
                    </Button>
                </div>
            </div>

            <DeclarationHistoryTimeline
                v-if="lifecycle.historyChain.length > 0 && current"
                :entries="[current, ...lifecycle.historyChain]"
                :current-declaration-id="current.id"
                class="border-t border-slate-100 pt-4"
            />
        </div>
    </Card>

    <!-- S7 · Régénération en cours (Draft chaîné, version obsolète remplacée) -->
    <Card v-else-if="state === 'regeneration_in_progress' && current">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                    >
                        <Recycle :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-lg font-semibold text-slate-900">
                                Régénération de la déclaration {{ fiscalYear }}
                            </h4>
                            <StatusPill tone="amber">En cours</StatusPill>
                        </div>
                        <p v-if="lifecycle.pendingClustersCount > 0" class="text-base leading-relaxed text-slate-600">
                            Un nouveau brouillon a été créé pour remplacer la version
                            obsolète. Il reste
                            <strong class="font-semibold text-slate-900">{{ lifecycle.pendingClustersCount }}
                            décision<template v-if="lifecycle.pendingClustersCount > 1">s</template></strong>
                            à trancher dans l'écran de revue avant de pouvoir générer la
                            nouvelle version.
                        </p>
                        <p v-else class="text-base leading-relaxed text-emerald-700">
                            Toutes les décisions ont été reprises automatiquement par
                            fingerprint · la nouvelle version est prête à être générée
                            depuis l'écran de revue.
                        </p>
                        <p v-if="predecessor?.reference" class="font-mono text-sm text-slate-500">
                            Remplace
                            <span class="font-medium text-slate-700">{{ predecessor.reference }}</span>
                            <template v-if="firstReasonOccurredAt">
                                · obsolète depuis le
                                {{ formatInvalidationOccurredAt(firstReasonOccurredAt) }}
                            </template>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                    <Link
                        v-if="predecessor"
                        :href="showDeclarationRoute.url({ declaration: predecessor.id })"
                        class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-900 hover:underline"
                    >
                        Voir la version obsolète
                        <ArrowUpRight :size="12" :stroke-width="1.75" />
                    </Link>
                    <Link :href="reviewDeclarationRoute.url({ declaration: current.id })">
                        <Button>
                            <FileText :size="16" :stroke-width="1.75" />
                            Reprendre la régénération
                        </Button>
                    </Link>
                </div>
            </div>

            <DeclarationHistoryTimeline
                v-if="lifecycle.historyChain.length > 0 && current"
                :entries="[current, ...lifecycle.historyChain]"
                :current-declaration-id="current.id"
                class="border-t border-slate-100 pt-4"
            />
        </div>
    </Card>
</template>
