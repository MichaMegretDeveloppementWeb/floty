<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useCreateDriverForm } from '@/Composables/Driver/useDriverForm';
import type { CompanyOption } from '@/Composables/Driver/useDriverForm';
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
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <Link
                :href="indexRoute().url"
                class="inline-flex items-center gap-1 text-sm text-slate-500 transition-colors hover:text-slate-700"
            >
                <ChevronLeft :size="16" :stroke-width="1.75" />
                Conducteurs
            </Link>

            <header>
                <p class="eyebrow mb-1">Données · Conducteurs</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Nouveau conducteur
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Renseignez l'identité du conducteur et son entreprise
                    initiale. Les rattachements supplémentaires se font depuis
                    sa fiche.
                </p>
            </header>

            <form class="flex flex-col gap-8" @submit.prevent="submit">
                <DriverFormFields :form="form" :companies="props.companies" />

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="indexRoute().url">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        Créer le conducteur
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
