<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useContractDocuments } from '@/Composables/Contract/useContractDocuments';
import { consumePendingDocuments } from '@/Composables/Contract/useContractFormPendingDocuments';
import ActionsBar from './partials/ActionsBar.vue';
import ContractDetails from './partials/ContractDetails.vue';
import ContractDocumentsSection from './partials/ContractDocumentsSection.vue';
import ContractEntityCards from './partials/ContractEntityCards.vue';
import ContractTitle from './partials/ContractTitle.vue';
import TaxBreakdownPanel from './partials/TaxBreakdownPanel.vue';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
    taxBreakdown: App.Data.User.Contract.ContractTaxBreakdownData | null;
    documents: App.Data.User.Contract.ContractDocumentData[];
}>();

const { uploadMany } = useContractDocuments();

// Handover Create → Show : si la création du contrat avait des fichiers
// en attente (stockés dans sessionStorage par useContractForm), on les
// upload maintenant en arrière-plan puis on rafraîchit la prop documents.
onMounted(async () => {
    const pending = consumePendingDocuments();

    if (pending.length === 0) {
        return;
    }

    await uploadMany(props.contract.id, pending);
    router.reload({ only: ['documents'] });
});
</script>

<template>
    <Head :title="`Contrat ${props.contract.vehicleLicensePlate} · ${props.contract.companyShortCode}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <ContractTitle :contract="props.contract" />

            <ContractEntityCards :contract="props.contract" />

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Colonne principale -->
                <div class="flex flex-col gap-6 xl:col-span-2">
                    <ContractDetails :contract="props.contract" />
                    <TaxBreakdownPanel :tax-breakdown="props.taxBreakdown" />
                    <!-- < xl : Documents en bas du main. ≥ xl : c'est l'aside qui les porte. -->
                    <ContractDocumentsSection
                        class="xl:hidden"
                        :contract-id="props.contract.id"
                        :documents="props.documents"
                    />
                    <!-- < xl : Actions tout en bas (action secondaire, après lecture). -->
                    <ActionsBar
                        class="xl:hidden"
                        :contract-id="props.contract.id"
                    />
                </div>

                <!-- Aside ≥ xl : Actions + Documents empilés -->
                <aside class="hidden xl:col-span-1 xl:block">
                    <div class="flex flex-col gap-6">
                        <ActionsBar :contract-id="props.contract.id" />
                        <ContractDocumentsSection
                            :contract-id="props.contract.id"
                            :documents="props.documents"
                        />
                    </div>
                </aside>
            </div>
        </div>
    </UserLayout>
</template>
