<script setup lang="ts">
import { onKeyStroke, useScrollLock } from '@vueuse/core';
import { X } from 'lucide-vue-next';
import { nextTick, useSlots, useTemplateRef, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        width?: 'sm' | 'md' | 'lg';
        closeOnBackdrop?: boolean;
    }>(),
    {
        width: 'md',
        closeOnBackdrop: true,
    },
);

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    close: [];
}>();

const panel = useTemplateRef<HTMLDivElement>('panel');
const bodyScrollLock = useScrollLock(
    typeof document !== 'undefined' ? document.body : null,
);

const close = (): void => {
    open.value = false;
    emit('close');
};

onKeyStroke('Escape', (event) => {
    if (!open.value) {
return;
}

    event.preventDefault();
    close();
});

watch(
    () => open.value,
    async (value) => {
        bodyScrollLock.value = value;

        if (value) {
            await nextTick();
            const target = panel.value?.querySelector<HTMLElement>(
                '[data-autofocus], button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
            );
            target?.focus();
        }
    },
);

const slots = useSlots();

const widthClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-xl',
};

const handleBackdropClick = (): void => {
    if (props.closeOnBackdrop) {
close();
}
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <div
                class="absolute inset-0 bg-slate-900/40"
                @click="handleBackdropClick"
            />
            <div
                ref="panel"
                :class="[
                    'absolute inset-y-0 right-0 flex w-full flex-col overflow-hidden bg-white shadow-2xl',
                    widthClass[width],
                ]"
            >
                <header
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4"
                >
                    <div class="flex flex-col gap-1">
                        <h2
                            class="text-xl font-semibold leading-tight text-slate-900"
                        >
                            {{ title }}
                        </h2>
                        <p
                            v-if="description"
                            class="text-sm leading-snug text-slate-500"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="-mr-1 shrink-0 rounded-md p-1 text-slate-400 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
                        @click="close"
                    >
                        <X :size="18" :stroke-width="1.75" />
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <slot />
                </div>
                <footer
                    v-if="slots.footer"
                    class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/60 px-6 py-4"
                >
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
