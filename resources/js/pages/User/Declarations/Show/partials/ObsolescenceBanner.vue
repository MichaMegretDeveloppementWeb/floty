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
        Une déclaration `Draft` ne devrait pas pouvoir devenir obsolète
        (ADR-0015 D8 : invalidation seulement sur Generated/Deferred).
        Guard défensif au cas où un trigger anormal poserait
        `is_obsolete = true` sur une Draft - on évite l'affichage d'une
        banner sans `obsoleteReasons` ni cohérence métier.
    -->
    <div
        v-if="declaration.isObsolete && declaration.status !== 'draft'"
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
