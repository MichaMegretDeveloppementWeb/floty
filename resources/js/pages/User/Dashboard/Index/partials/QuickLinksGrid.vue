<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from 'lucide-vue-next';
import { useQuickLinksGrid } from '@/Composables/Dashboard/Index/useQuickLinksGrid';

const { quickLinks } = useQuickLinksGrid();
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900">
            Accès rapide
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="link in quickLinks"
                :key="link.href"
                :href="link.href"
                class="group relative flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5 transition-colors duration-[120ms] ease-out hover:border-slate-400"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-slate-900">
                        <component
                            :is="link.icon"
                            :size="18"
                            :stroke-width="1.75"
                            class="text-slate-700"
                            aria-hidden="true"
                        />
                        <p class="text-base font-semibold tracking-tight">
                            {{ link.label }}
                        </p>
                    </div>
                    <ArrowUpRight
                        :size="16"
                        :stroke-width="1.75"
                        class="text-slate-300 transition-colors duration-[120ms] ease-out group-hover:text-slate-700"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-sm text-slate-600">
                    {{ link.description }}
                </p>
            </Link>
        </div>
    </section>
</template>
