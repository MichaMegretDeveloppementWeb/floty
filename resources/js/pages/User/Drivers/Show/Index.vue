<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AddDriverCompanyModal from '@/Components/Domain/Driver/AddDriverCompanyModal.vue';
import LeaveDriverCompanyModal from '@/Components/Domain/Driver/LeaveDriverCompanyModal.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DriverActionsBar from './partials/DriverActionsBar.vue';
import DriverCompaniesSection from './partials/DriverCompaniesSection.vue';
import DriverHeader from './partials/DriverHeader.vue';
import DriverKpiCards from './partials/DriverKpiCards.vue';

type CompanyOption = { id: number; shortCode: string; legalName: string };

const props = defineProps<{
    driver: App.Data.User.Driver.DriverData;
    options: {
        companies: CompanyOption[];
    };
}>();

const leaveCompanyId = ref<number | null>(null);
const showAddModal = ref(false);

const canDelete = computed<boolean>(() => props.driver.contractsCount === 0);
</script>

<template>
    <Head :title="props.driver.fullName" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <DriverHeader :driver="props.driver" />
            <DriverKpiCards :driver="props.driver" />

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Colonne principale -->
                <div class="flex flex-col gap-6 xl:col-span-2">
                    <!-- < xl : Actions juste après header. ≥ xl : c'est l'aside qui les porte. -->
                    <DriverActionsBar
                        class="xl:hidden"
                        :driver-id="props.driver.id"
                        :driver-full-name="props.driver.fullName"
                        :can-delete="canDelete"
                    />
                    <DriverCompaniesSection
                        :driver-id="props.driver.id"
                        :memberships="props.driver.memberships"
                        @open-leave="
                            (companyId) => (leaveCompanyId = companyId)
                        "
                        @open-add="showAddModal = true"
                    />
                </div>

                <!-- Aside ≥ xl uniquement -->
                <aside class="hidden xl:col-span-1 xl:block">
                    <DriverActionsBar
                        :driver-id="props.driver.id"
                        :driver-full-name="props.driver.fullName"
                        :can-delete="canDelete"
                    />
                </aside>
            </div>

            <LeaveDriverCompanyModal
                v-if="leaveCompanyId !== null"
                :driver-id="props.driver.id"
                :company-id="leaveCompanyId"
                :driver-full-name="props.driver.fullName"
                :company-name="
                    props.driver.memberships.find(
                        (m) => m.companyId === leaveCompanyId,
                    )?.companyLegalName ?? ''
                "
                @close="leaveCompanyId = null"
            />

            <AddDriverCompanyModal
                v-if="showAddModal"
                :driver-id="props.driver.id"
                :existing-company-ids="
                    props.driver.memberships.map((m) => m.companyId)
                "
                :available-companies="props.options.companies"
                @close="showAddModal = false"
            />
        </div>
    </UserLayout>
</template>
