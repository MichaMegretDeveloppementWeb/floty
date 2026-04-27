<script setup lang="ts">
import { densityClass } from '@/Components/Features/Planning/Heatmap/utils/density';
</script>

<template>
    <div class="flex items-center gap-2 text-xs text-slate-500">
        <span>0 j</span>
        <div class="flex gap-0.5">
            <span
                v-for="n in 8"
                :key="n"
                :class="[densityClass(n - 1), 'h-4 w-4 rounded-[3px]']"
            />
        </div>
        <span>7 j / 7</span>
    </div>
</template>
