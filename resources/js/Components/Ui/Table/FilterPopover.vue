<script setup lang="ts">
import { Filter } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    /** Nombre de filtres actifs (badge sur le bouton trigger). */
    activeCount: number;
}>();

defineEmits<{
    reset: [];
}>();

const open = defineModel<boolean>('open', { default: false });
const rootRef = ref<HTMLElement | null>(null);

function toggle(event: MouseEvent): void {
    event.stopPropagation();
    open.value = !open.value;
}

function handleDocumentMouseDown(event: MouseEvent): void {
    if (!open.value) {
        return;
    }

    const target = event.target as Node | null;

    if (target === null) {
        return;
    }

    if (rootRef.value !== null && rootRef.value.contains(target)) {
        return;
    }

    open.value = false;
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleDocumentMouseDown);
    document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentMouseDown);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <div
        ref="rootRef"
        class="relative inline-block"
    >
        <button
            type="button"
            :class="[
                'inline-flex cursor-pointer items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors duration-[120ms] ease-out',
                activeCount > 0
                    ? 'border-blue-300 bg-blue-50 text-blue-900 hover:bg-blue-100'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50',
            ]"
            @click="toggle"
        >
            <Filter
                :size="14"
                :stroke-width="1.75"
            />
            <span>Filtres</span>
            <span
                v-if="activeCount > 0"
                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 text-xs font-medium text-white"
            >
                {{ activeCount }}
            </span>
        </button>

        <!--
            Mobile (< sm) : bottom sheet centrée + backdrop léger pour
            focus visuel, hauteur bornée à 80vh + scroll interne.
            Desktop (≥ sm) : popover ancré sous le bouton, max 400px de
            large, hauteur bornée à viewport-8rem + scroll interne pour
            les longs contenus (filtres avec grille année, par ex.).
        -->
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-slate-900/20 sm:hidden"
            aria-hidden="true"
            @click="open = false"
        />
        <div
            v-if="open"
            class="fixed inset-x-4 bottom-4 z-50 flex max-h-[80vh] flex-col rounded-lg border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:bottom-auto sm:left-0 sm:top-full sm:mt-2 sm:max-h-[calc(100vh-8rem)] sm:w-[400px] sm:max-w-[calc(100vw-2rem)] sm:shadow-lg"
        >
            <div class="flex flex-col gap-3 overflow-y-auto p-4">
                <slot />
            </div>
            <div
                class="flex shrink-0 items-center justify-between border-t border-slate-100 px-4 py-2"
            >
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
