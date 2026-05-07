<script setup lang="ts">
import { ListTree } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';

defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
}>();
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center gap-2">
                <ListTree :size="18" :stroke-width="1.75" class="text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">
                    Décisions de revue
                </h2>
            </div>
        </template>

        <p class="text-sm text-slate-500">
            Le détail des clusters et des décisions prises sera affiché ici en
            D5, en lien avec le contenu du PDF annexe documentaire.
        </p>
    </Card>
</template>
