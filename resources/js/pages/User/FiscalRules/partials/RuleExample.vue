<script setup lang="ts">
defineProps<{
    example: string;
}>();
</script>

<template>
    <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50/50 p-3">
        <p class="eyebrow mb-1 text-blue-700">Exemple chiffré</p>
        <p class="text-base leading-relaxed text-slate-700">
            {{ example }}
        </p>
    </div>
</template>
