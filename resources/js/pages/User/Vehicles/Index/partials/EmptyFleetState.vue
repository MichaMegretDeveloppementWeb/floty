<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Car, Plus } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { create as vehiclesCreateRoute } from '@/routes/user/vehicles';
</script>

<template>
    <EmptyState
        title="Aucun véhicule enregistré"
        description="Commencez par saisir les véhicules de la flotte partagée."
    >
        <template #icon>
            <Car :size="20" :stroke-width="1.75" />
        </template>
        <template #actions>
            <Link :href="vehiclesCreateRoute.url()">
                <Button>
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Nouveau véhicule
                </Button>
            </Link>
        </template>
    </EmptyState>
</template>
