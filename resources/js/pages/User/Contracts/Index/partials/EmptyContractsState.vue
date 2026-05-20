<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText, Plus } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { create as contractsCreateRoute } from '@/routes/user/contracts';
</script>

<template>
    <EmptyState
        title="Aucune location enregistrée"
        description="Créez une location pour attribuer un véhicule à une entreprise utilisatrice sur une plage de dates."
    >
        <template #icon>
            <FileText :size="20" :stroke-width="1.75" />
        </template>
        <template #actions>
            <Link :href="contractsCreateRoute.url()">
                <Button>
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Nouvelle location
                </Button>
            </Link>
        </template>
    </EmptyState>
</template>
