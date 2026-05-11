<script setup lang="ts">
/**
 * Carte adaptative du cycle de vie d'une déclaration fiscale annuelle
 * pour un couple `(company, year)` (Phase 11 D5.8.4, Proposition IV).
 *
 * Rend une carte différente selon `lifecycle.state` :
 *   - S1 Untouched : invitation à préparer.
 *   - S2 DraftPending : carte « En cours » avec compteur de
 *     décisions à trancher + CTA Reprendre la revue.
 *   - S3 DraftReadyToGenerate : carte « Prête à générer » avec
 *     mention « toutes les décisions sont prises » + CTA Reprendre.
 *   - S4 Deferred : carte « Mise de côté » + CTA Reprendre.
 *   - S5 GeneratedActive : carte succès avec ref + date + actions
 *     Ouvrir / Télécharger PDF, accordion historique replié.
 *   - S6 GeneratedObsoleteOrphan : carte d'alerte ostensible avec
 *     ref + date + liste des motifs d'obsolescence + CTA Régénérer.
 *   - S7 RegenerationInProgress : carte « Régénération en cours »
 *     pointant vers le Draft chaîné + lien vers la version obsolète.
 *
 * Source unique de vérité de l'état déclaratif côté entreprise :
 * remplace l'encart « Déclaration {année} » legacy qui consommait
 * `fiscalActiveDeclaration` et masquait les versions obsolètes
 * orphelines.
 */
import { Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
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

const props = defineProps<{
    lifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
    companyId: number;
    fiscalYear: number;
}>();

const preparing = ref<boolean>(false);
const regenerating = ref<boolean>(false);
const historyExpanded = ref<boolean>(false);

const current = computed(() => props.lifecycle.currentDeclaration);
const predecessor = computed(() => props.lifecycle.predecessorDeclaration);
const reasons = computed(() => props.lifecycle.obsoleteReasons);
const reasonsToShow = computed(() => reasons.value.slice(0, 3));
const extraReasonsCount = computed(() => Math.max(reasons.value.length - 3, 0));
const firstReasonOccurredAt = computed(() => reasons.value[0]?.occurredAt ?? null);

const state = computed(() => props.lifecycle.state);

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
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-blue-500"
                >
                    <FilePlus2 :size="18" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-0.5">
                    <h4 class="text-sm font-semibold text-slate-900">
                        Déclaration {{ fiscalYear }}
                    </h4>
                    <p class="text-xs text-slate-500">
                        Aucune déclaration préparée pour cet exercice. Préparer
                        ouvre l'écran de revue où vous tranchez les éventuels
                        clusters de risque avant génération.
                    </p>
                </div>
            </div>
            <Button :disabled="preparing" @click="handlePrepare">
                <LoaderCircle v-if="preparing" :size="16" :stroke-width="1.75" class="animate-spin" />
                <FileCheck2 v-else :size="16" :stroke-width="1.75" />
                {{ preparing ? 'Préparation…' : 'Préparer la déclaration' }}
            </Button>
        </div>
    </Card>

    <!-- S2 + S3 · Draft (en cours / prêt à générer) -->
    <Card v-else-if="(state === 'draft_pending' || state === 'draft_ready_to_generate') && current">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-600"
                >
                    <FileText :size="18" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-0.5">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        Déclaration {{ fiscalYear }}
                        <StatusPill tone="slate">Brouillon</StatusPill>
                    </h4>
                    <p v-if="state === 'draft_pending'" class="text-xs text-slate-500">
                        Préparation en cours · {{ lifecycle.pendingClustersCount }}
                        décision<template v-if="lifecycle.pendingClustersCount > 1">s</template>
                        à trancher avant génération.
                    </p>
                    <p v-else class="text-xs text-emerald-700">
                        Toutes les décisions sont prises · la déclaration peut être
                        générée depuis l'écran de revue.
                    </p>
                </div>
            </div>
            <Link :href="reviewDeclarationRoute.url({ declaration: current.id })">
                <Button>
                    <FileText :size="16" :stroke-width="1.75" />
                    Reprendre la revue
                </Button>
            </Link>
        </div>
    </Card>

    <!-- S4 · Mise de côté -->
    <Card v-else-if="state === 'deferred' && current">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-amber-500"
                >
                    <Clock :size="18" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-0.5">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        Déclaration {{ fiscalYear }}
                        <StatusPill tone="amber">Mise de côté</StatusPill>
                    </h4>
                    <p class="text-xs text-slate-500">
                        Préparation en pause volontaire. Vous pouvez la reprendre
                        à tout moment, aucune décision déjà prise ne sera perdue.
                    </p>
                </div>
            </div>
            <Link :href="reviewDeclarationRoute.url({ declaration: current.id })">
                <Button>
                    <FileText :size="16" :stroke-width="1.75" />
                    Reprendre la revue
                </Button>
            </Link>
        </div>
    </Card>

    <!-- S5 · Generated active -->
    <Card v-else-if="state === 'generated_active' && current">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-emerald-500"
                    >
                        <CheckCircle2 :size="18" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            Déclaration {{ fiscalYear }}
                            <StatusPill tone="emerald">Générée</StatusPill>
                        </h4>
                        <p v-if="current.reference" class="font-mono text-[11px] text-slate-500">
                            {{ current.reference }}
                            <span v-if="current.generatedAt" class="ml-1 text-slate-400">
                                · générée le {{ new Date(current.generatedAt).toLocaleDateString('fr-FR') }}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                        <Button variant="secondary">
                            <FileText :size="16" :stroke-width="1.75" />
                            Ouvrir
                        </Button>
                    </Link>
                    <a
                        :href="downloadDeclarationRoute.url({ declaration: current.id })"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 h-[34px] text-base font-medium leading-none text-slate-700 transition-colors duration-[120ms] hover:bg-slate-50 hover:border-slate-300"
                    >
                        <Download :size="16" :stroke-width="1.75" />
                        Télécharger PDF
                    </a>
                </div>
            </div>

            <div v-if="lifecycle.historyChain.length > 0" class="border-t border-slate-100 pt-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-700"
                    @click="historyExpanded = !historyExpanded"
                >
                    <component
                        :is="historyExpanded ? ChevronUp : ChevronDown"
                        :size="14"
                        :stroke-width="1.75"
                    />
                    Historique des versions ({{ lifecycle.historyChain.length }})
                </button>
                <ul v-if="historyExpanded" class="mt-2 flex flex-col gap-1">
                    <li
                        v-for="version in lifecycle.historyChain"
                        :key="version.id"
                        class="flex items-center gap-2 text-xs text-slate-500"
                    >
                        <Link
                            :href="showDeclarationRoute.url({ declaration: version.id })"
                            class="font-mono text-slate-600 hover:text-slate-900 hover:underline"
                        >
                            {{ version.reference ?? `Version #${version.id}` }}
                        </Link>
                        <StatusPill tone="rose">Obsolète</StatusPill>
                        <span v-if="version.generatedAt" class="text-slate-400">
                            · {{ new Date(version.generatedAt).toLocaleDateString('fr-FR') }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </Card>

    <!-- S6 · Generated obsolète orphan (la version est périmée, pas de Draft chaîné) -->
    <Card v-else-if="state === 'generated_obsolete_orphan' && current">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-rose-500"
                    >
                        <AlertTriangle :size="18" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            Déclaration {{ fiscalYear }}
                            <StatusPill tone="rose">Générée · obsolète</StatusPill>
                        </h4>
                        <p v-if="current.reference" class="font-mono text-[11px] text-slate-500">
                            {{ current.reference }}
                            <span v-if="current.generatedAt" class="ml-1 text-slate-400">
                                · générée le {{ new Date(current.generatedAt).toLocaleDateString('fr-FR') }}
                            </span>
                        </p>
                    </div>
                </div>
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button variant="secondary">
                        <FileText :size="16" :stroke-width="1.75" />
                        Ouvrir
                    </Button>
                </Link>
            </div>

            <div class="rounded-lg border border-slate-200 border-l-2 border-l-rose-400 bg-slate-50 p-3">
                <p class="text-sm font-semibold text-slate-900">
                    Cette déclaration est périmée
                </p>
                <p class="mt-0.5 text-xs text-slate-600">
                    Le périmètre fiscal {{ fiscalYear }} a évolué depuis la
                    génération. Régénérer pour reprendre le calcul à jour.
                </p>
                <ul
                    v-if="reasonsToShow.length > 0"
                    class="mt-2 flex flex-col gap-1"
                >
                    <li
                        v-for="(reason, index) in reasonsToShow"
                        :key="index"
                        class="flex items-baseline gap-1.5 text-xs text-slate-700"
                    >
                        <span class="inline-block size-1.5 shrink-0 translate-y-[-1px] rounded-full bg-rose-400" />
                        <span>
                            <span class="font-medium">{{ formatInvalidationReason(reason) }}</span>
                            <span class="text-slate-500">
                                · {{ formatInvalidationOccurredAt(reason.occurredAt) }}
                            </span>
                        </span>
                    </li>
                    <li v-if="extraReasonsCount > 0" class="pl-3 text-[11px] italic text-slate-500">
                        +{{ extraReasonsCount }} autre<template v-if="extraReasonsCount > 1">s</template> motif<template v-if="extraReasonsCount > 1">s</template>
                    </li>
                </ul>
                <div class="mt-3">
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
        </div>
    </Card>

    <!-- S7 · Régénération en cours (Draft chaîné, version obsolète remplacée) -->
    <Card v-else-if="state === 'regeneration_in_progress' && current">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-amber-500"
                    >
                        <Recycle :size="18" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            Régénération de la déclaration {{ fiscalYear }}
                            <StatusPill tone="amber">En cours</StatusPill>
                        </h4>
                        <p class="text-xs text-slate-500">
                            <template v-if="lifecycle.pendingClustersCount > 0">
                                {{ lifecycle.pendingClustersCount }}
                                décision<template v-if="lifecycle.pendingClustersCount > 1">s</template>
                                à trancher avant la nouvelle génération.
                            </template>
                            <template v-else>
                                Toutes les décisions sont reprises · prêt à générer la nouvelle version.
                            </template>
                            <span
                                v-if="predecessor?.reference"
                                class="block font-mono text-[11px] text-slate-400"
                            >
                                Remplace {{ predecessor.reference }}<template
                                    v-if="firstReasonOccurredAt"
                                > · obsolète depuis le
                                    {{ formatInvalidationOccurredAt(firstReasonOccurredAt) }}</template>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="predecessor"
                        :href="showDeclarationRoute.url({ declaration: predecessor.id })"
                        class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-900 hover:underline"
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
</template>
