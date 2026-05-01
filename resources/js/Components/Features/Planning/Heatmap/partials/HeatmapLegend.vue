<script setup lang="ts">
import { densityClass } from '@/Components/Features/Planning/Heatmap/utils/density';
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500">
        <div class="flex items-center gap-2">
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
        <div class="flex items-center gap-1.5">
            <span
                aria-hidden="true"
                class="inline-block h-4 w-4 rounded-[3px] bg-white ring-1 ring-rose-500 ring-inset"
            />
            <span>Présence d'une indisponibilité</span>
        </div>
    </div>
</template>
