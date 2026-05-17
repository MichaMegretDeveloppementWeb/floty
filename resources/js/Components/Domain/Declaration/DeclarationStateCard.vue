<script setup lang="ts">
/**
 * Bloc « Déclaration » de l'onglet Fiscalité d'une entreprise.
 *
 * Refonte design D5.10.W (direction « Linear-éditorial ») · ce
 * composant ne rend **plus** de Card. Il s'inscrit dans le flux
 * typographique du tab parent · eyebrow `DÉCLARATION` géré côté
 * `<CompanyFiscalTab>`, ce composant rend uniquement le corps de la
 * section.
 *
 * 7 branches (cf. `DeclarationLifecycleState`) ·
 *   - S1 Untouched · géré directement dans `<CompanyFiscalTab>` (prose),
 *     ce composant n'est pas invoqué dans ce cas.
 *   - S2 DraftPending · « Brouillon · X décisions à trancher »
 *   - S3 DraftReadyToGenerate · « Brouillon prêt »
 *   - S4 Deferred · « Mise en pause »
 *   - S4-bis DeferredRegeneration · « Régénération mise en pause »
 *   - S5 GeneratedActive · « Générée · REF · date » + actions PDF
 *   - S6 GeneratedObsoleteOrphan · « Obsolète » + liste motifs
 *   - S7 RegenerationInProgress · « Régénération en cours »
 *
 * Chaque branche partage le même squelette ·
 *   1. Status row · dot coloré + nom de l'état + meta optionnel (REF
 *      mono, date d'obsolescence ou de génération).
 *   2. Prose · 1 paragraphe en `text-[15px] leading-relaxed
 *      text-slate-700` qui raconte l'état actuel et la prochaine étape.
 *   3. Actions · CTA primaire (Button slate-900) + lien secondaire
 *      optionnel inline.
 *   4. Annexe contextuelle (S6 motifs / S5-S7 timeline) en dessous.
 */
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Download,
    FileText,
    LoaderCircle,
    Recycle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import {
    download as downloadDeclarationRoute,
    regenerate as regenerateDeclarationRoute,
    show as showDeclarationRoute,
} from '@/routes/user/declarations';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import {
    formatInvalidationOccurredAt,
    formatInvalidationReason,
} from '@/Utils/format/invalidationReason';
import DeclarationHistoryTimeline from './DeclarationHistoryTimeline.vue';

const props = withDefaults(
    defineProps<{
        lifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
        companyId: number;
        fiscalYear: number;
        /**
         * Une déclaration n'est juridiquement préparable qu'à partir de
         * janvier N+1 (CIBS L. 421-159). Prop conservée pour cohérence
         * d'API avec `<CompanyFiscalTab>`, mais inutilisée ici · la
         * branche `untouched + non déclarable` est gérée côté parent en
         * prose, ce composant n'est invoqué que sur les états S2-S7.
         */
        isDeclarable?: boolean;
    }>(),
    { isDeclarable: true },
);

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

    return formatDateFr(current.value.generatedAt);
});

const pendingPlural = computed<string>(
    () => (props.lifecycle.pendingClustersCount > 1 ? 's' : ''),
);

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
    <div class="flex flex-col gap-4">
        <!-- S2 · Draft pending -->
        <template v-if="state === 'draft_pending' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-slate-400" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Brouillon en préparation</span>
                <span v-if="current.reference" class="font-mono text-xs text-slate-500">
                    · {{ current.reference }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                La préparation de la déclaration est en cours. Il reste
                <strong class="font-medium text-slate-900">
                    {{ lifecycle.pendingClustersCount }} arbitrage{{ pendingPlural }}
                </strong>
                en attente dans l'écran de revue avant de générer le document.
                Les arbitrages déjà rendus sont conservés.
            </p>
            <div>
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button>
                        <FileText :size="14" :stroke-width="1.75" />
                        Poursuivre la revue
                    </Button>
                </Link>
            </div>
        </template>

        <!-- S3 · Draft ready to generate -->
        <template v-else-if="state === 'draft_ready_to_generate' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Brouillon prêt à générer</span>
                <span v-if="current.reference" class="font-mono text-xs text-slate-500">
                    · {{ current.reference }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                Tous les arbitrages de revue ont été rendus. La déclaration
                peut désormais être générée depuis l'écran de revue · le document
                produit sera figé et conservé en annexe.
            </p>
            <div>
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button>
                        <FileText :size="14" :stroke-width="1.75" />
                        Générer la déclaration
                    </Button>
                </Link>
            </div>
        </template>

        <!-- S4 · Deferred -->
        <template v-else-if="state === 'deferred' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-amber-500" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Préparation reportée</span>
                <span v-if="current.reference" class="font-mono text-xs text-slate-500">
                    · {{ current.reference }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                La préparation a été volontairement reportée. Elle peut être
                reprise à tout moment depuis l'écran de revue · aucun arbitrage
                déjà rendu ne sera perdu.
            </p>
            <div>
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button>
                        <FileText :size="14" :stroke-width="1.75" />
                        Poursuivre la revue
                    </Button>
                </Link>
            </div>
        </template>

        <!-- S4-bis · Deferred regeneration -->
        <template v-else-if="state === 'deferred_regeneration' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-amber-500" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Régénération reportée</span>
                <span v-if="predecessor?.reference" class="font-mono text-xs text-slate-500">
                    · remplace {{ predecessor.reference }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                Le brouillon de régénération a été volontairement reporté.
                La déclaration précédente reste obsolète tant que la nouvelle
                version n'est pas générée.
                <template v-if="firstReasonOccurredAt">
                    Le périmètre a divergé depuis le
                    <strong class="font-medium text-slate-900">
                        {{ formatInvalidationOccurredAt(firstReasonOccurredAt) }}</strong>.
                </template>
            </p>
            <div class="flex flex-wrap items-center gap-5">
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button>
                        <FileText :size="14" :stroke-width="1.75" />
                        Poursuivre la régénération
                    </Button>
                </Link>
                <Link
                    v-if="predecessor"
                    :href="showDeclarationRoute.url({ declaration: predecessor.id })"
                    class="inline-flex cursor-pointer items-center gap-1 text-sm text-slate-500 transition-colors duration-[120ms] hover:text-slate-900"
                >
                    Voir la version obsolète
                    <ArrowUpRight :size="13" :stroke-width="1.75" />
                </Link>
            </div>
        </template>

        <!-- S5 · Generated active -->
        <template v-else-if="state === 'generated_active' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Déclaration générée</span>
                <span v-if="current.reference" class="font-mono text-xs text-slate-500">
                    · {{ current.reference }}
                </span>
                <span v-if="generatedAtFormatted" class="text-xs text-slate-500">
                    · le {{ generatedAtFormatted }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                Le document PDF est disponible en téléchargement. Le périmètre
                est à jour · aucune mutation contractuelle ne l'a invalidée
                depuis la génération.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button variant="secondary">
                        <FileText :size="14" :stroke-width="1.75" />
                        Ouvrir
                    </Button>
                </Link>
                <a
                    :href="downloadDeclarationRoute.url({ declaration: current.id })"
                    class="inline-flex h-[34px] cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-sm font-medium leading-none text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
                >
                    <Download :size="14" :stroke-width="1.75" />
                    Télécharger le PDF
                </a>
            </div>

            <DeclarationHistoryTimeline
                v-if="lifecycle.historyChain.length > 0 && current"
                :entries="[current, ...lifecycle.historyChain]"
                :current-declaration-id="current.id"
                class="mt-4 border-t border-slate-100 pt-5"
            />
        </template>

        <!-- S6 · Generated obsolete orphan -->
        <template v-else-if="state === 'generated_obsolete_orphan' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-rose-500" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Déclaration obsolète</span>
                <span v-if="current.reference" class="font-mono text-xs text-slate-500">
                    · {{ current.reference }}
                </span>
                <span v-if="generatedAtFormatted" class="text-xs text-slate-500">
                    · générée le {{ generatedAtFormatted }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                Le périmètre fiscal a évolué depuis la génération. La régénération
                crée un nouveau brouillon de revue où les décisions inchangées
                sont automatiquement reprises par fingerprint · seuls les
                clusters impactés sont à reprendre. Le PDF historique reste
                consultable pour archive.
            </p>

            <ul
                v-if="reasonsToShow.length > 0"
                class="flex max-w-2xl flex-col gap-2 text-[14px] leading-relaxed text-slate-700"
            >
                <li
                    v-for="(reason, index) in reasonsToShow"
                    :key="index"
                    class="flex items-baseline gap-2"
                >
                    <span class="inline-block size-1 shrink-0 translate-y-[-2px] rounded-full bg-rose-500" aria-hidden="true" />
                    <span>
                        <span class="font-medium text-slate-900">{{ formatInvalidationReason(reason) }}</span>
                        <span class="text-slate-500"> · {{ formatInvalidationOccurredAt(reason.occurredAt) }}</span>
                    </span>
                </li>
                <li v-if="extraReasonsCount > 0" class="pl-3 text-sm text-slate-500">
                    + {{ extraReasonsCount }} autre<template v-if="extraReasonsCount > 1">s</template>
                    motif<template v-if="extraReasonsCount > 1">s</template>
                </li>
            </ul>

            <div class="flex flex-wrap items-center gap-5">
                <Button :disabled="regenerating" @click="handleRegenerate">
                    <LoaderCircle
                        v-if="regenerating"
                        :size="14"
                        :stroke-width="1.75"
                        class="animate-spin"
                    />
                    <Recycle v-else :size="14" :stroke-width="1.75" />
                    {{ regenerating ? 'Création du brouillon…' : 'Régénérer la déclaration' }}
                </Button>
                <Link
                    :href="showDeclarationRoute.url({ declaration: current.id })"
                    class="inline-flex cursor-pointer items-center gap-1 text-sm text-slate-500 transition-colors duration-[120ms] hover:text-slate-900"
                >
                    Ouvrir la version obsolète
                    <ArrowUpRight :size="13" :stroke-width="1.75" />
                </Link>
            </div>

            <DeclarationHistoryTimeline
                v-if="lifecycle.historyChain.length > 0 && current"
                :entries="[current, ...lifecycle.historyChain]"
                :current-declaration-id="current.id"
                class="mt-4 border-t border-slate-100 pt-5"
            />
        </template>

        <!-- S7 · Regeneration in progress -->
        <template v-else-if="state === 'regeneration_in_progress' && current">
            <div class="flex items-center gap-2">
                <span class="inline-block size-1.5 rounded-full bg-amber-500" aria-hidden="true" />
                <span class="text-sm font-medium text-slate-900">Régénération en cours</span>
                <span v-if="predecessor?.reference" class="font-mono text-xs text-slate-500">
                    · remplace {{ predecessor.reference }}
                </span>
            </div>
            <p class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                <template v-if="lifecycle.pendingClustersCount > 0">
                    Un nouveau brouillon a été créé pour remplacer la version
                    obsolète. Il reste
                    <strong class="font-medium text-slate-900">
                        {{ lifecycle.pendingClustersCount }} arbitrage{{ pendingPlural }}
                    </strong>
                    en attente dans l'écran de revue avant de générer la
                    nouvelle version.
                </template>
                <template v-else>
                    Tous les arbitrages ont été repris automatiquement par
                    fingerprint · la nouvelle version est prête à être générée
                    depuis l'écran de revue.
                </template>
                <template v-if="firstReasonOccurredAt">
                    Le périmètre a divergé depuis le
                    <strong class="font-medium text-slate-900">
                        {{ formatInvalidationOccurredAt(firstReasonOccurredAt) }}</strong>.
                </template>
            </p>
            <div class="flex flex-wrap items-center gap-5">
                <Link :href="showDeclarationRoute.url({ declaration: current.id })">
                    <Button>
                        <FileText :size="14" :stroke-width="1.75" />
                        Poursuivre la régénération
                    </Button>
                </Link>
                <Link
                    v-if="predecessor"
                    :href="showDeclarationRoute.url({ declaration: predecessor.id })"
                    class="inline-flex cursor-pointer items-center gap-1 text-sm text-slate-500 transition-colors duration-[120ms] hover:text-slate-900"
                >
                    Voir la version obsolète
                    <ArrowUpRight :size="13" :stroke-width="1.75" />
                </Link>
            </div>

            <DeclarationHistoryTimeline
                v-if="lifecycle.historyChain.length > 0 && current"
                :entries="[current, ...lifecycle.historyChain]"
                :current-declaration-id="current.id"
                class="mt-4 border-t border-slate-100 pt-5"
            />
        </template>
    </div>
</template>
