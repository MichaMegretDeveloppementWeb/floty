<script setup lang="ts">
withDefaults(
    defineProps<{
        fullName: string;
        initials: string;
        size?: 'sm' | 'md';
    }>(),
    {
        size: 'md',
    },
);
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <span
            class="inline-flex items-center justify-center rounded-full bg-blue-100 font-semibold text-blue-700"
            :class="size === 'sm' ? 'h-6 w-6 text-xs' : 'h-8 w-8 text-sm'"
        >
            {{ initials }}
        </span>
        <span :class="size === 'sm' ? 'text-sm' : 'text-base'">{{
            fullName
        }}</span>
    </div>
</template>
