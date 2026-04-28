<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useCompanyCreateForm } from '@/Composables/Company/Create/useCompanyCreateForm';
import { index as companiesIndexRoute } from '@/routes/user/companies';
import AddressSection from './partials/AddressSection.vue';
import ContactSection from './partials/ContactSection.vue';
import IdentitySection from './partials/IdentitySection.vue';

const props = defineProps<{
    colors: App.Data.User.Company.CompanyColorOptionData[];
}>();

const { form, submit } = useCompanyCreateForm();
</script>

<template>
    <Head title="Nouvelle entreprise" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Entreprises</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Nouvelle entreprise
                </h1>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <IdentitySection :form="form" :color-options="props.colors" />
                <AddressSection :form="form" />
                <ContactSection :form="form" />

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <Link :href="companiesIndexRoute.url()">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
