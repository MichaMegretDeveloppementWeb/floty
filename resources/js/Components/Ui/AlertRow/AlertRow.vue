<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

type AlertTone = 'info' | 'success' | 'warning' | 'danger';

const props = withDefaults(
    defineProps<{
        tone?: AlertTone;
        title: string;
        description?: string;
        href?: string;
    }>(),
    {
        tone: 'info',
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const toneClasses = computed<string>(() => {
    switch (props.tone) {
        case 'info':
            return 'bg-blue-50 text-blue-700';
        case 'success':
            return 'bg-emerald-50 text-emerald-700';
        case 'warning':
            return 'bg-amber-50 text-amber-700';
        case 'danger':
            return 'bg-rose-50 text-rose-700';
        default: {
            const _exhaustive: never = props.tone;
            throw new Error(`Tonalité non gérée : ${_exhaustive as string}`);
        }
    }
});

const tag = computed<'a' | 'button' | 'div'>(() => {
    if (props.href) return 'a';
    return 'button';
});

const handleClick = (event: MouseEvent): void => {
    emit('click', event);
};
</script>

<template>
    <component
        :is="tag"
        :href="href"
        :type="tag === 'button' ? 'button' : undefined"
        class="group flex w-full cursor-pointer items-start gap-3 rounded-lg px-2 py-2.5 text-left transition-colors duration-[120ms] ease-out hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
        @click="handleClick"
    >
        <span
            :class="[
                'flex size-7 shrink-0 items-center justify-center rounded-lg',
                toneClasses,
            ]"
            aria-hidden="true"
        >
            <slot name="icon" />
        </span>
        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span
                class="text-base font-medium leading-tight text-slate-900"
            >
                {{ title }}
            </span>
            <span
                v-if="description"
                class="text-sm leading-tight text-slate-500"
            >
                {{ description }}
            </span>
        </span>
        <ChevronRight
            :size="16"
            :stroke-width="1.75"
            class="mt-1 shrink-0 text-slate-300 transition-colors duration-[120ms] ease-out group-hover:text-slate-500"
            aria-hidden="true"
        />
    </component>
</template>
