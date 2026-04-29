<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';

defineProps<{
    contracts: App.Data.User.Contract.ContractListItemData[];
}>();
</script>

<template>
    <Head title="Contrats" />

    <UserLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold">Contrats</h1>
            <p class="mt-2 text-sm text-neutral-600">
                Page placeholder — interface livrée au chantier 04.G.
            </p>
        </div>
    </UserLayout>
</template>
