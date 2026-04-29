<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';

defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();
</script>

<template>
    <Head title="Modifier le contrat" />

    <UserLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold">Modifier le contrat #{{ contract.id }}</h1>
            <p class="mt-2 text-sm text-neutral-600">
                Page placeholder — interface livrée au chantier 04.G.
            </p>
        </div>
    </UserLayout>
</template>
