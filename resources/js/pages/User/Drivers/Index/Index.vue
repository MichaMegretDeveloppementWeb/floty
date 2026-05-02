<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { create as createRoute } from '@/routes/user/drivers';
import DriversTable from './partials/DriversTable.vue';

const props = defineProps<{
    drivers: App.Data.User.Driver.DriverListItemData[];
}>();

const stats = computed<{ total: number; active: number; multi: number }>(
    () => ({
        total: props.drivers.length,
        active: props.drivers.filter((d) => d.totalActiveCompaniesCount > 0)
            .length,
        multi: props.drivers.filter((d) => d.totalActiveCompaniesCount >= 2)
            .length,
    }),
);
</script>

<template>
    <Head title="Conducteurs" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="flex flex-col gap-1">
                    <p
                        class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                    >
                        Données
                    </p>
                    <h1 class="text-2xl font-bold text-slate-900">
                        Conducteurs
                    </h1>
                    <p class="text-sm text-slate-500">
                        {{ stats.active }} actif{{
                            stats.active > 1 ? 's' : ''
                        }}
                        sur {{ stats.total }} au total
                        <span v-if="stats.multi > 0" class="text-slate-400">
                            · {{ stats.multi }} multi-entreprises
                        </span>
                    </p>
                </div>
                <Link :href="createRoute().url">
                    <Button>
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Ajouter un conducteur
                    </Button>
                </Link>
            </div>

            <div
                v-if="drivers.length === 0"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center"
            >
                <span
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600"
                >
                    <UserPlus :size="22" :stroke-width="1.75" />
                </span>
                <p class="text-base font-semibold text-slate-900">
                    Aucun conducteur
                </p>
                <p class="max-w-sm text-sm text-slate-500">
                    Commencez par créer votre premier conducteur. Vous pourrez
                    ensuite l'affecter à un ou plusieurs contrats.
                </p>
                <Link :href="createRoute().url" class="mt-2">
                    <Button>Créer un conducteur</Button>
                </Link>
            </div>
            <DriversTable v-else :drivers="drivers" />
        </div>
    </UserLayout>
</template>
