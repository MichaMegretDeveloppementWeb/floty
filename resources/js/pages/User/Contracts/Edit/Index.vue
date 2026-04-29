<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useContractForm } from '@/Composables/Contract/useContractForm';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import ContractFormFields from '../Create/partials/ContractFormFields.vue';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
    options: {
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
        contractTypes: { value: string; label: string }[];
    };
}>();

const { form, canSubmit, submit } = useContractForm(props.contract);
</script>

<template>
    <Head :title="`Modifier contrat ${props.contract.vehicleLicensePlate}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <Link
                    :href="contractsShowRoute.url({ contract: props.contract.id })"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
                >
                    <ArrowLeft :size="14" :stroke-width="1.75" />
                    Retour au contrat
                </Link>
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Modifier le contrat #{{ props.contract.id }}
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        {{ props.contract.vehicleLicensePlate }} ·
                        {{ props.contract.companyShortCode }}
                    </p>
                </div>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <ContractFormFields :form="form" :options="props.options" />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <Link
                        :href="contractsShowRoute.url({ contract: props.contract.id })"
                    >
                        <Button type="button" variant="secondary">
                            Annuler
                        </Button>
                    </Link>
                    <Button
                        type="submit"
                        :loading="form.processing"
                        :disabled="!canSubmit"
                    >
                        Mettre à jour le contrat
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
