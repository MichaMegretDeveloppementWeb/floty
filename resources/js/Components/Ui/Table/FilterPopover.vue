<script setup lang="ts">
import { onClickOutside, onKeyStroke } from '@vueuse/core';
import { Filter } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

defineProps<{
    /** Nombre de filtres actifs (badge sur le bouton trigger). */
    activeCount: number;
}>();

defineEmits<{
    reset: [];
}>();

const open = defineModel<boolean>('open', { default: false });
const triggerRef = ref<HTMLElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);

// Position absolue du panneau dans le body (Teleport) — calculée à
// l'ouverture depuis le BoundingClientRect du bouton trigger pour
// échapper aux ancêtres `overflow-hidden`.
const panelPosition = ref<{ top: number; right: number }>({ top: 0, right: 0 });

function updatePosition(): void {
    if (triggerRef.value === null) {
        return;
    }

    const rect = triggerRef.value.getBoundingClientRect();
    panelPosition.value = {
        top: rect.bottom + window.scrollY + 8,
        right: window.innerWidth - rect.right,
    };
}

watch(open, async (value) => {
    if (value) {
        await nextTick();
        updatePosition();
    }
});

onClickOutside(panelRef, (event) => {
    if (triggerRef.value !== null && triggerRef.value.contains(event.target as Node)) {
        return;
    }

    open.value = false;
});

onKeyStroke('Escape', () => {
    if (open.value) {
        open.value = false;
    }
});

const panelStyle = computed(() => ({
    top: `${panelPosition.value.top}px`,
    right: `${panelPosition.value.right}px`,
}));
</script>

<template>
    <div class="inline-block">
        <button
            ref="triggerRef"
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

        <Teleport to="body">
            <div
                v-if="open"
                ref="panelRef"
                class="fixed z-50 w-[400px] max-w-[calc(100vw-2rem)] rounded-lg border border-slate-200 bg-white shadow-lg"
                :style="panelStyle"
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
        </Teleport>
    </div>
</template>
