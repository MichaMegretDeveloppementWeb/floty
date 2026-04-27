<script setup lang="ts">
import { X } from 'lucide-vue-next';

defineProps<{
    weekNumber: number;
    fiscalYear: number;
    licensePlate: string;
    weekStart: string;
    weekEnd: string;
}>();

defineEmits<{
    close: [];
}>();
</script>

<template>
    <header
        class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white/95 px-5 py-4 backdrop-blur"
    >
        <div>
            <p class="eyebrow mb-0.5">
                Semaine {{ weekNumber }} · {{ fiscalYear }}
            </p>
            <h2 class="font-mono text-sm font-medium text-slate-900">
                {{ licensePlate }}
            </h2>
            <p class="text-xs text-slate-500">
                Du {{ weekStart }} au {{ weekEnd }}
            </p>
        </div>
        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            aria-label="Fermer"
            @click="$emit('close')"
        >
            <X :size="16" :stroke-width="1.75" />
        </button>
    </header>
</template>
