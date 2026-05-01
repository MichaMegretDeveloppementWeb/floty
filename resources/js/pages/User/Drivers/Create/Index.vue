<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useCreateDriverForm  } from '@/Composables/Driver/useDriverForm';
import type {CompanyOption} from '@/Composables/Driver/useDriverForm';
import { index as indexRoute } from '@/routes/user/drivers';
import DriverFormFields from './partials/DriverFormFields.vue';

const props = defineProps<{
    companies: CompanyOption[];
}>();

const { form, submit } = useCreateDriverForm();
</script>

<template>
    <Head title="Nouveau conducteur" />

    <UserLayout>
        <div class="mx-auto flex max-w-2xl flex-col gap-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Nouveau conducteur</h1>
                <Link :href="indexRoute().url" class="text-sm text-slate-600 hover:underline">
                    Annuler
                </Link>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <DriverFormFields :form="form" :companies="props.companies" />

                <div class="flex justify-end">
                    <Button type="submit" :loading="form.processing">
                        Créer le conducteur
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
