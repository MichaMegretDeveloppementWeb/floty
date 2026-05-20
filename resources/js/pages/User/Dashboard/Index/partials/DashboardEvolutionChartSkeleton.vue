<script setup lang="ts">
/** Evolution chart skeleton mimicking the Chart.js layout exactly. */
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';

defineProps<{
    /** Render only the chart area; the header stays on the parent component. */
    chartOnly?: boolean;
}>();

const BARS: readonly { heightPx: number }[] = [
    { heightPx: 210 },
    { heightPx: 150 },
    { heightPx: 100 },
];
</script>

<template>
    <div v-if="chartOnly" class="flex h-[280px]" aria-busy="true">
        <div class="flex w-[50px] shrink-0 flex-col justify-between py-1 pr-3">
            <Skeleton v-for="i in 6" :key="i" class="h-2 w-full rounded" />
        </div>
        <div class="flex flex-1 flex-col">
            <div class="flex h-[240px] items-end">
                <div
                    v-for="(bar, i) in BARS"
                    :key="i"
                    class="flex flex-1 justify-center"
                >
                    <Skeleton
                        class="w-[80%] rounded-sm"
                        :style="{ height: bar.heightPx + 'px' }"
                    />
                </div>
            </div>
            <div class="flex pt-2">
                <div
                    v-for="(_, i) in BARS"
                    :key="i"
                    class="flex flex-1 justify-center"
                >
                    <Skeleton class="h-2.5 w-12 rounded" />
                </div>
            </div>
        </div>
    </div>

    <div
        v-else
        class="rounded-xl border border-slate-200 bg-white p-6"
        aria-busy="true"
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-2">
                <Skeleton class="h-4 w-44 rounded" />
                <Skeleton class="h-3 w-60 rounded" />
            </div>
            <div class="flex gap-1.5 rounded-lg border border-slate-200 bg-white p-1">
                <Skeleton v-for="i in 4" :key="i" class="h-6 w-20 rounded-md" />
            </div>
        </div>
        <div class="flex h-[280px]">
            <div class="flex w-[50px] shrink-0 flex-col justify-between py-1 pr-3">
                <Skeleton v-for="i in 6" :key="i" class="h-2 w-full rounded" />
            </div>
            <div class="flex flex-1 flex-col">
                <div class="flex h-[240px] items-end">
                    <div
                        v-for="(bar, i) in BARS"
                        :key="i"
                        class="flex flex-1 justify-center"
                    >
                        <Skeleton
                            class="w-[80%] rounded-sm"
                            :style="{ height: bar.heightPx + 'px' }"
                        />
                    </div>
                </div>
                <div class="flex pt-2">
                    <div
                        v-for="(_, i) in BARS"
                        :key="i"
                        class="flex flex-1 justify-center"
                    >
                        <Skeleton class="h-2.5 w-12 rounded" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
