<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import { create as contractsCreateRoute } from '@/routes/user/contracts';
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow mb-1">Données</p>
            <h1
                class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
            >
                Contrats
            </h1>
            <p class="mt-1 text-base text-slate-600">
                Plages d'attribution véhicule × entreprise.
            </p>
        </div>
        <Link :href="contractsCreateRoute.url()">
            <Button>
                <template #icon-left>
                    <Plus :size="14" :stroke-width="1.75" />
                </template>
                Nouveau contrat
            </Button>
        </Link>
    </header>
</template>
