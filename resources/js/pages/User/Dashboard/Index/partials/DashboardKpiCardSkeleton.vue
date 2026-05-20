<script setup lang="ts">
/** KPI card skeleton mirroring KpiCard proportions to avoid layout shift. */
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
</script>

<template>
    <article
        class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white px-5 py-4"
        aria-busy="true"
    >
        <Skeleton class="h-3 w-28 rounded" />
        <div class="flex items-end justify-between gap-3">
            <Skeleton class="h-9 w-32 rounded-md" />
            <div class="flex flex-col items-end gap-1">
                <Skeleton class="h-4 w-12 rounded" />
                <Skeleton class="h-2 w-14 rounded" />
            </div>
        </div>
        <Skeleton class="h-4 w-44 rounded" />
    </article>
</template>
