<script setup lang="ts">
import { onClickOutside, onKeyStroke } from '@vueuse/core';
import { Filter } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    /** Nombre de filtres actifs (badge sur le bouton trigger). */
    activeCount: number;
}>();

defineEmits<{
    reset: [];
}>();

const open = defineModel<boolean>('open', { default: false });
const rootRef = ref<HTMLElement | null>(null);

onClickOutside(rootRef, () => {
    open.value = false;
});

onKeyStroke('Escape', () => {
    if (open.value) {
        open.value = false;
    }
});
</script>

<template>
    <div
        ref="rootRef"
        class="relative"
    >
        <button
            type="button"
            :class="[
                'inline-flex cursor-pointer items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors duration-[120ms] ease-out',
                activeCount > 0
                    ? 'border-blue-300 bg-blue-50 text-blue-900 hover:bg-blue-100'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50',
            ]"
            @click="open = !open"
        >
            <Filter :size="14" :stroke-width="1.75" />
            <span>Filtres</span>
            <span
                v-if="activeCount > 0"
                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 text-xs font-medium text-white"
            >
                {{ activeCount }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-30 mt-2 w-[400px] rounded-lg border border-slate-200 bg-white shadow-lg"
        >
            <div class="flex flex-col gap-3 p-4">
                <slot />
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-2">
                <button
                    type="button"
                    :class="[
                        'cursor-pointer text-xs underline-offset-2 transition-colors duration-[120ms] ease-out',
                        activeCount > 0
                            ? 'text-rose-600 hover:text-rose-700 hover:underline'
                            : 'cursor-not-allowed text-slate-300',
                    ]"
                    :disabled="activeCount === 0"
                    @click="$emit('reset')"
                >
                    Réinitialiser
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-md border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
                    @click="open = false"
                >
                    Fermer
                </button>
            </div>
        </div>
    </div>
</template>
