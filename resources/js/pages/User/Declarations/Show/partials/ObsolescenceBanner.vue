<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import {
    formatInvalidationOccurredAt,
    formatInvalidationReason,
} from '@/Utils/format/invalidationReason';

defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
}>();
</script>

<template>
    <!--
        Phase 13 D5.10.O · seules les déclarations `generated` peuvent
        être obsolètes. Les brouillons (`draft` / `deferred`) sont par
        essence en cours · leur périmètre est recalculé live à chaque
        ouverture en Review, donc le flag n'a pas de sens pour eux. Le
        backend ne les flag plus, mais on garde un guard défensif côté
        UI pour éviter l'affichage d'une banner sans cohérence métier
        si une donnée résiduelle remontait.
    -->
    <div
        v-if="declaration.isObsolete && declaration.status === 'generated'"
        class="flex flex-col gap-3 rounded-sm border border-slate-200 border-l-2 border-l-rose-400 bg-white p-4"
    >
        <div class="flex items-start gap-3">
            <AlertTriangle :size="20" :stroke-width="1.75" class="shrink-0 text-rose-500" />
            <div class="flex flex-col gap-1">
                <p class="text-sm font-semibold text-slate-900">
                    Déclaration obsolète
                </p>
                <p class="text-xs text-slate-600">
                    Le périmètre fiscal a évolué depuis la génération. Régénérer
                    pour reprendre le calcul à jour. Les décisions inchangées
                    seront automatiquement reprises par fingerprint.
                </p>
            </div>
        </div>

        <div
            v-if="declaration.obsoleteReasons && declaration.obsoleteReasons.length > 0"
            class="flex flex-col gap-1 rounded-md border border-slate-200 bg-white p-3"
        >
            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">
                Motifs d'invalidation
            </p>
            <ul class="flex flex-col gap-1.5">
                <li
                    v-for="(reason, index) in declaration.obsoleteReasons"
                    :key="index"
                    class="flex items-baseline gap-1.5 text-xs text-slate-700"
                >
                    <span class="inline-block size-1.5 shrink-0 translate-y-[-1px] rounded-full bg-rose-400" />
                    <span>
                        <span class="font-medium">
                            {{ formatInvalidationReason(reason) }}
                        </span>
                        <span class="text-slate-500">
                            · {{ formatInvalidationOccurredAt(reason.occurredAt) }}
                        </span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
