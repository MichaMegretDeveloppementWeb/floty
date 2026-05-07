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
    <div
        v-if="declaration.isObsolete"
        class="flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4"
    >
        <div class="flex items-start gap-3">
            <AlertTriangle :size="20" :stroke-width="1.75" class="shrink-0 text-rose-600" />
            <div class="flex flex-col gap-1">
                <p class="text-sm font-semibold text-rose-900">
                    Déclaration obsolète
                </p>
                <p class="text-xs text-rose-800">
                    Le périmètre fiscal a évolué depuis la génération. Régénérer
                    pour reprendre le calcul à jour. Les décisions inchangées
                    seront automatiquement reprises par fingerprint.
                </p>
            </div>
        </div>

        <div
            v-if="declaration.obsoleteReasons && declaration.obsoleteReasons.length > 0"
            class="flex flex-col gap-1 rounded-md border border-rose-200 bg-white/60 p-3"
        >
            <p class="text-[11px] font-medium tracking-wider text-rose-700 uppercase">
                Motifs d'invalidation
            </p>
            <ul class="flex flex-col gap-1.5">
                <li
                    v-for="(reason, index) in declaration.obsoleteReasons"
                    :key="index"
                    class="text-xs text-rose-800"
                >
                    <span class="font-medium">
                        {{ formatInvalidationReason(reason) }}
                    </span>
                    <span class="text-rose-600">
                        · {{ formatInvalidationOccurredAt(reason.occurredAt) }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
