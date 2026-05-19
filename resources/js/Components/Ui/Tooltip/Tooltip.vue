<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Max width of the tooltip (defaults to auto). */
        maxWidth?: string;
        /** Vertical offset in pixels above the trigger. */
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
    // Wait for the tooltip to render before measuring its size.
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

// Listeners registered in `onMounted` for symmetry with `onBeforeUnmount`
// and SSR safety (window does not exist server-side).
onMounted(() => {
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleScroll);
});

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
