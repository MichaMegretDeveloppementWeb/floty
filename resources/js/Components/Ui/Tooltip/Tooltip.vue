<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    /** Largeur max du tooltip (par défaut auto). */
    maxWidth?: string;
}>();

const visible = ref<boolean>(false);

const show = (): void => {
    visible.value = true;
};

const hide = (): void => {
    visible.value = false;
};
</script>

<template>
    <span
        class="relative inline-flex"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="hide"
    >
        <slot />

        <span
            v-show="visible"
            role="tooltip"
            :class="[
                'pointer-events-none absolute bottom-full left-1/2 z-50 mb-2',
                '-translate-x-1/2 rounded-md bg-slate-900/95 px-3 py-2',
                'text-xs leading-relaxed text-slate-100 shadow-lg',
                'whitespace-nowrap',
            ]"
            :style="maxWidth ? { maxWidth, whiteSpace: 'normal' } : {}"
        >
            <slot name="content" />
            <span
                class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900/95"
                aria-hidden="true"
            />
        </span>
    </span>
</template>
