<script setup lang="ts">
import { computed } from 'vue';
import type { ButtonSize, ButtonVariant } from '@/types/ui';

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant;
        size?: ButtonSize;
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        loading?: boolean;
        block?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        disabled: false,
        loading: false,
        block: false,
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const isInert = computed<boolean>(() => props.disabled || props.loading);

const variantClasses = computed<string>(() => {
    if (props.disabled) {
        if (props.variant === 'ghost') {
            return 'bg-transparent text-slate-300 border-transparent cursor-not-allowed';
        }

        return 'bg-slate-100 text-slate-400 border-transparent cursor-not-allowed';
    }

    switch (props.variant) {
        case 'primary':
            return 'bg-slate-900 text-white border-transparent hover:bg-slate-800';
        case 'secondary':
            return 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 hover:border-slate-300';
        case 'ghost':
            return 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100 hover:text-slate-900';
        case 'destructive-soft':
            return 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 hover:border-rose-300';
        case 'destructive':
            return 'bg-rose-600 text-white border-transparent hover:bg-rose-700';
        default: {
            const _exhaustive: never = props.variant;

            throw new Error(`Variant non géré : ${_exhaustive as string}`);
        }
    }
});

const sizeClasses = computed<string>(() => {
    switch (props.size) {
        case 'md':
            return 'h-[34px] px-3.5 text-base gap-2';
        case 'sm':
            return 'h-[28px] px-2.5 text-sm gap-1.5';
        case 'icon':
            return 'h-[30px] w-[30px] p-0 justify-center';
        default: {
            const _exhaustive: never = props.size;

            throw new Error(`Size non géré : ${_exhaustive as string}`);
        }
    }
});

const handleClick = (event: MouseEvent): void => {
    if (isInert.value) {
return;
}

    emit('click', event);
};
</script>

<template>
    <button
        :type="type"
        :disabled="isInert"
        :aria-busy="loading || undefined"
        :class="[
            'inline-flex items-center justify-center rounded-lg border font-medium leading-none',
            'transition-colors duration-[120ms] ease-out',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-100 focus-visible:ring-offset-0',
            variantClasses,
            sizeClasses,
            block && 'w-full',
        ]"
        @click="handleClick"
    >
        <span
            v-if="loading"
            class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
            aria-hidden="true"
        />
        <slot v-else name="icon-left" />
        <slot />
        <slot v-if="!loading" name="icon-right" />
    </button>
</template>
