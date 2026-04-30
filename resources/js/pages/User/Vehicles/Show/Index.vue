<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import CompanyFiscalBreakdownTable from './partials/CompanyFiscalBreakdownTable.vue';
import CurrentFiscalCharacteristicsCard from './partials/CurrentFiscalCharacteristicsCard.vue';
import FullYearTaxBreakdownPanel from './partials/FullYearTaxBreakdownPanel.vue';
import UnavailabilitiesCard from './partials/UnavailabilitiesCard.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleKpiCards from './partials/VehicleKpiCards.vue';
import VehicleYearlyUsageTimeline from './partials/VehicleYearlyUsageTimeline.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const fullYearModalOpen = ref<boolean>(false);
</script>

<template>
    <Head :title="`${props.vehicle.licensePlate} — ${props.vehicle.brand} ${props.vehicle.model}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <VehicleHeader :vehicle="props.vehicle" />
            <VehicleKpiCards
                :stats="props.vehicle.usageStats"
                @open-full-year-detail="fullYearModalOpen = true"
            />

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Colonne principale -->
                <div class="flex flex-col gap-6 xl:col-span-2">
                    <CurrentFiscalCharacteristicsCard
                        :fiscal="props.vehicle.currentFiscalCharacteristics"
                        :history="props.vehicle.fiscalCharacteristicsHistory"
                        :options="props.options"
                    />
                    <VehicleYearlyUsageTimeline :stats="props.vehicle.usageStats" />
                    <!-- < xl : Indispo dans le main, sous Yearly. En xl+, c'est l'aside qui la porte. -->
                    <UnavailabilitiesCard
                        class="xl:hidden"
                        :vehicle-id="props.vehicle.id"
                        :unavailabilities="props.vehicle.unavailabilities"
                        :busy-dates="props.vehicle.busyDates"
                    />
                    <CompanyFiscalBreakdownTable :stats="props.vehicle.usageStats" />
                </div>

                <!-- Aside visible xl+ uniquement -->
                <aside class="hidden xl:col-span-1 xl:block">
                    <div class="flex flex-col gap-6">
                        <FullYearTaxBreakdownPanel :stats="props.vehicle.usageStats" />
                        <UnavailabilitiesCard
                            :vehicle-id="props.vehicle.id"
                            :unavailabilities="props.vehicle.unavailabilities"
                            :busy-dates="props.vehicle.busyDates"
                        />
                    </div>
                </aside>
            </div>

            <!-- < xl : modale ouvrable depuis le bouton « Voir le détail » de la
                 carte KPI Coût plein. En xl+, le bouton est masqué et le panel
                 est visible directement dans l'aside. -->
            <Modal
                v-model:open="fullYearModalOpen"
                :title="`Détail du Coût plein ${props.vehicle.usageStats.fiscalYear}`"
                size="lg"
            >
                <FullYearTaxBreakdownPanel :stats="props.vehicle.usageStats" />
            </Modal>
        </div>
    </UserLayout>
</template>
