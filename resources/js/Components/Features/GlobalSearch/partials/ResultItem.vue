<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Item cliquable de la palette de recherche globale ⌘K (V1.1).
 *
 * Présenté comme un bouton plein-largeur · icône à gauche, label +
 * sublabel optionnel à droite. L'état `active` (navigué via flèches)
 * applique un fond `bg-slate-100`.
 *
 * Émet `select` au clic ou survol (parent CommandPalette gère la
 * navigation + le router visit).
 */
const props = defineProps<{
    icon: Component;
    label: string;
    sublabel: string | null;
    active: boolean;
}>();

const emit = defineEmits<{
    select: [];
    hover: [];
}>();

const classes = computed<string>(() => {
    return props.active
        ? 'bg-slate-100 text-slate-900'
        : 'bg-white text-slate-700 hover:bg-slate-50';
});
</script>

<template>
    <button
        type="button"
        :class="[
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors duration-[120ms] ease-out focus-visible:outline-none',
            classes,
        ]"
        @click="emit('select')"
        @mouseenter="emit('hover')"
    >
        <component
            :is="icon"
            :size="18"
            :stroke-width="1.75"
            class="shrink-0 text-slate-400"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm leading-tight font-medium">
                {{ label }}
            </p>
            <p
                v-if="sublabel"
                class="mt-0.5 truncate text-xs leading-tight text-slate-500"
            >
                {{ sublabel }}
            </p>
        </div>
    </button>
</template>
