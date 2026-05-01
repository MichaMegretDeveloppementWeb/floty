<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { create as createRoute } from '@/routes/user/drivers';
import DriversTable from './partials/DriversTable.vue';

defineProps<{
    drivers: App.Data.User.Driver.DriverListItemData[];
}>();
</script>

<template>
    <Head title="Conducteurs" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">
                        Conducteurs
                    </h1>
                    <p class="text-sm text-slate-600">
                        {{ drivers.length }} conducteur{{
                            drivers.length > 1 ? 's' : ''
                        }}.
                    </p>
                </div>
                <Link :href="createRoute().url">
                    <Button>Ajouter un conducteur</Button>
                </Link>
            </div>

            <EmptyState
                v-if="drivers.length === 0"
                title="Aucun conducteur"
                description="Commencez par créer votre premier conducteur."
            />
            <DriversTable v-else :drivers="drivers" />
        </div>
    </UserLayout>
</template>
