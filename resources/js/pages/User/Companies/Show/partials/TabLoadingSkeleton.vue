<script setup lang="ts">
/**
 * Skeleton shown while a Company detail tab's props are loaded for the
 * first time. Three card skeletons + a header bar match the layout of
 * Fiscal/Billing/Contracts closely enough to avoid layout shift on swap.
 */
</script>

<template>
    <div class="flex flex-col gap-6" aria-busy="true" aria-live="polite">
        <div class="rounded-md border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3">
                <div class="h-5 w-32 animate-pulse rounded bg-slate-100" />
                <div class="h-4 w-64 animate-pulse rounded bg-slate-100" />
                <div class="mt-2 flex gap-1.5">
                    <div class="h-7 w-14 animate-pulse rounded-full bg-slate-100" />
                    <div class="h-7 w-14 animate-pulse rounded-full bg-slate-100" />
                    <div class="h-7 w-14 animate-pulse rounded-full bg-slate-100" />
                </div>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3">
                <div class="h-4 w-40 animate-pulse rounded bg-slate-100" />
                <div class="h-32 w-full animate-pulse rounded bg-slate-100" />
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3">
                <div class="h-4 w-48 animate-pulse rounded bg-slate-100" />
                <div class="h-24 w-full animate-pulse rounded bg-slate-100" />
            </div>
        </div>
    </div>
</template>
