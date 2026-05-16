<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useCompanyEditForm } from '@/Composables/Company/Edit/useCompanyEditForm';
import AddressSection from '@/pages/User/Companies/Create/partials/AddressSection.vue';
import ContactSection from '@/pages/User/Companies/Create/partials/ContactSection.vue';
import IdentitySection from '@/pages/User/Companies/Create/partials/IdentitySection.vue';
import { show as companiesShowRoute } from '@/routes/user/companies';

const props = defineProps<{
    company: App.Data.User.Company.CompanyEditData;
    colors: App.Data.User.Company.CompanyColorOptionData[];
}>();

const { form, submit } = useCompanyEditForm(props.company);
</script>

<template>
    <Head :title="`Modifier ${company.legalName}`" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Entreprises</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Modifier {{ company.legalName }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Le code court <span class="font-mono">{{ company.shortCode }}</span> est figé à la création et ne peut pas être modifié.
                </p>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <IdentitySection :form="form" :color-options="props.colors" />
                <AddressSection :form="form" />
                <ContactSection :form="form" />

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <Link :href="companiesShowRoute.url({ company: company.id })">
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
