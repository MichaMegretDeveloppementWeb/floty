<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useContractForm } from '@/Composables/Contract/useContractForm';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import ContractFormFields from './partials/ContractFormFields.vue';

const props = defineProps<{
    options: {
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
    };
}>();

const { form, canSubmit, submit } = useContractForm();
</script>

<template>
    <Head title="Nouveau contrat" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <Link
                    :href="contractsIndexRoute.url()"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
                >
                    <ArrowLeft :size="14" :stroke-width="1.75" />
                    Retour aux contrats
                </Link>
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Nouveau contrat
                    </h1>
                </div>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <ContractFormFields :form="form" :options="props.options" />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <Link :href="contractsIndexRoute.url()">
                        <Button type="button" variant="secondary">
                            Annuler
                        </Button>
                    </Link>
                    <Button
                        type="submit"
                        :loading="form.processing"
                        :disabled="!canSubmit"
                    >
                        Enregistrer le contrat
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
