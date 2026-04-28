<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Largeur max du tooltip (par défaut auto). */
        maxWidth?: string;
        /** Décalage vertical en pixels au-dessus du trigger. */
        offset?: number;
    }>(),
    {
        offset: 8,
    },
);

const visible = ref<boolean>(false);
const triggerRef = ref<HTMLElement | null>(null);
const tooltipRef = ref<HTMLElement | null>(null);
const top = ref<number>(0);
const left = ref<number>(0);

const updatePosition = (): void => {
    const trigger = triggerRef.value;
    const tip = tooltipRef.value;

    if (!trigger || !tip) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();

    top.value = triggerRect.top - tipRect.height - props.offset;
    left.value = triggerRect.left + triggerRect.width / 2 - tipRect.width / 2;
};

const show = async (): Promise<void> => {
    visible.value = true;
    // Attendre le rendu du tooltip pour mesurer sa taille
    await new Promise((resolve) => requestAnimationFrame(resolve));
    updatePosition();
};

const hide = (): void => {
    visible.value = false;
};

const handleScroll = (): void => {
    if (visible.value) {
        updatePosition();
    }
};

window.addEventListener('scroll', handleScroll, true);
window.addEventListener('resize', handleScroll);

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll, true);
    window.removeEventListener('resize', handleScroll);
});
</script>

<template>
    <span
        ref="triggerRef"
        class="inline-flex"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="hide"
    >
        <slot />
    </span>

    <Teleport to="body">
        <span
            v-show="visible"
            ref="tooltipRef"
            role="tooltip"
            :class="[
                'pointer-events-none fixed z-[1000] rounded-md bg-slate-900/95 px-3 py-2',
                'text-xs leading-relaxed text-slate-100 shadow-lg',
            ]"
            :style="{
                top: `${top}px`,
                left: `${left}px`,
                maxWidth: props.maxWidth ?? 'none',
                whiteSpace: props.maxWidth ? 'normal' : 'nowrap',
            }"
        >
            <slot name="content" />
        </span>
    </Teleport>
</template>
