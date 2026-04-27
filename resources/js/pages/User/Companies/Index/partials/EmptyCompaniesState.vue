<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, Plus } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { create as companiesCreateRoute } from '@/routes/user/companies';
</script>

<template>
    <EmptyState
        title="Aucune entreprise enregistrée"
        description="Ajoutez votre première entreprise utilisatrice pour commencer à créer des attributions."
    >
        <template #icon>
            <Building2 :size="20" :stroke-width="1.75" />
        </template>
        <template #actions>
            <Link :href="companiesCreateRoute.url()">
                <Button>
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Nouvelle entreprise
                </Button>
            </Link>
        </template>
    </EmptyState>
</template>
