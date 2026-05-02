<script setup lang="ts">
withDefaults(
    defineProps<{
        fullName: string;
        initials: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        size: 'md',
    },
);
</script>

<template>
    <div
        class="inline-flex items-center"
        :class="size === 'lg' ? 'gap-3' : 'gap-2'"
    >
        <span
            class="inline-flex items-center justify-center rounded-full bg-blue-100 font-semibold text-blue-700"
            :class="{
                'h-6 w-6 text-xs': size === 'sm',
                'h-8 w-8 text-sm': size === 'md',
                'h-12 w-12 text-base': size === 'lg',
            }"
        >
            {{ initials }}
        </span>
        <span
            :class="{
                'text-sm': size === 'sm',
                'text-base': size === 'md',
                'text-xl font-semibold text-slate-900': size === 'lg',
            }"
        >
            {{ fullName }}
        </span>
    </div>
</template>
