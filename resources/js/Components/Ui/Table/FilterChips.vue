<script setup lang="ts">
import { X } from 'lucide-vue-next';

defineProps<{
    /** Liste des filtres actifs : `key` = identifiant pour le retrait, `label` = texte affiché. */
    chips: Array<{ key: string; label: string }>;
}>();

defineEmits<{
    remove: [key: string];
}>();
</script>

<template>
    <div
        v-if="chips.length > 0"
        class="flex flex-wrap items-center gap-1.5"
    >
        <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-0.5 pr-1 pl-2.5 text-xs text-blue-900 transition-colors duration-[120ms] ease-out hover:bg-blue-100"
            :title="`Retirer ${chip.label}`"
            :aria-label="`Retirer ${chip.label}`"
            @click="$emit('remove', chip.key)"
        >
            <span>{{ chip.label }}</span>
            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                <X :size="10" :stroke-width="2.5" />
            </span>
        </button>
    </div>
</template>
