<script setup lang="ts">
/**
 * Mini banner narratif sur la page Show, signalant qu'une déclaration
 * remplace une version précédente obsolète (Phase 11 D5.8.3).
 *
 * Distinct du `<ObsolescenceBanner>` qui s'affiche quand la
 * déclaration courante elle-même est devenue obsolète. Ce composant
 * ci s'affiche au contraire sur la NOUVELLE génération (chaîne
 * aval) pour assurer la traçabilité historique.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Recycle } from 'lucide-vue-next';
import { show as showDeclarationRoute } from '@/routes/user/declarations';

defineProps<{
    predecessor: App.Data.User.FiscalDeclaration.DeclarationListItemData;
}>();
</script>

<template>
    <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
        <Recycle :size="18" :stroke-width="1.75" class="shrink-0 text-slate-500" />
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-slate-900">
                Cette déclaration remplace
                <template v-if="predecessor.reference"> {{ predecessor.reference }}</template>
                <template v-else> une version antérieure</template>
            </p>
            <Link
                :href="showDeclarationRoute.url({ declaration: predecessor.id })"
                class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
            >
                Voir la version remplacée
                <ArrowUpRight :size="12" :stroke-width="1.75" />
            </Link>
        </div>
    </div>
</template>
