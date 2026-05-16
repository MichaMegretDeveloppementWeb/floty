<script setup lang="ts">
defineProps<{
    example: string;
}>();
</script>

<template>
    <div class="mt-6 border-l-2 border-slate-200 pl-4">
        <p class="eyebrow mb-1.5">Exemple chiffré</p>
        <p class="text-sm leading-relaxed text-slate-700">
            {{ example }}
        </p>
    </div>
</template>
