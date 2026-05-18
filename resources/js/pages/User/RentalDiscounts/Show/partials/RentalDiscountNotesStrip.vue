<script setup lang="ts">
import { FileText } from 'lucide-vue-next';

defineProps<{
    notes: string;
}>();
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white">
        <header class="flex items-center gap-2 px-5 py-3.5 border-b border-slate-100">
            <FileText :size="13" :stroke-width="1.75" class="text-slate-400" />
            <h2 class="text-sm font-semibold text-slate-900">
                Notes internes
            </h2>
        </header>
        <div class="px-5 py-4">
            <p class="text-sm whitespace-pre-line text-slate-700 leading-relaxed">
                {{ notes }}
            </p>
        </div>
    </section>
</template>
