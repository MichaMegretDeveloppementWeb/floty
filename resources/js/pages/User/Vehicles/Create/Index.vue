<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ClipboardCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useVehicleCreateForm } from '@/Composables/Vehicle/Create/useVehicleCreateForm';
import { useVehicleRegistryLookup } from '@/Composables/Vehicle/Create/useVehicleRegistryLookup';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import FiscalCharacteristicsSection from './partials/FiscalCharacteristicsSection.vue';
import IdentitySection from './partials/IdentitySection.vue';
import RegistrationSection from './partials/RegistrationSection.vue';

const props = defineProps<{
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const { form, submit } = useVehicleCreateForm();

const page = usePage();
const registryLookupEnabled = computed(
    () => (page.props as Record<string, unknown>).registryLookupEnabled === true,
);

const {
    loading: registryLookupLoading,
    errorMessage: registryLookupError,
    missingFields,
    isMissing,
    lookup: registryLookup,
} = useVehicleRegistryLookup(form);

const missingCountLabel = computed(() => {
    const n = missingFields.value.length;

    return `${n} ${n > 1 ? 'éléments à vérifier' : 'élément à vérifier'}`;
});
</script>

<template>
    <Head title="Nouveau véhicule" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Flotte</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Nouveau véhicule
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Les caractéristiques fiscales initiales sont effectives à la
                    date d'acquisition.
                </p>
            </header>

            <form
                class="flex flex-col gap-8"
                @submit.prevent="submit"
            >
                <IdentitySection
                    :form="form"
                    :is-missing="isMissing"
                    :registry-lookup-enabled="registryLookupEnabled"
                    :registry-lookup-loading="registryLookupLoading"
                    :registry-lookup-error="registryLookupError"
                    @trigger-registry-lookup="registryLookup"
                />
                <RegistrationSection :form="form" :is-missing="isMissing" />
                <FiscalCharacteristicsSection
                    :form="form"
                    :options="props.options"
                    :is-missing="isMissing"
                />

                <section
                    v-if="missingFields.length > 0"
                    class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 md:p-8"
                >
                    <header class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                            <ClipboardCheck :size="18" :stroke-width="1.75" />
                        </span>
                        <div class="flex flex-col gap-1">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-amber-700">
                                À compléter manuellement · {{ missingCountLabel }}
                            </p>
                            <p class="text-sm text-slate-600">
                                Ces informations n'ont pas été fournies par le service de récupération. Repérez les champs marqués d'une pastille
                                <span class="mx-0.5 inline-block h-1.5 w-1.5 translate-y-[-1px] rounded-full bg-amber-500 align-middle" />
                                et vérifiez-les avant d'enregistrer le véhicule.
                            </p>
                        </div>
                    </header>

                    <ul class="flex flex-col gap-2.5 pl-12">
                        <li
                            v-for="field in missingFields"
                            :key="field.key"
                            class="flex items-start gap-3 text-sm text-slate-800"
                        >
                            <span
                                class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"
                                aria-hidden="true"
                            />
                            <span>{{ field.label }}</span>
                        </li>
                    </ul>
                </section>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="vehiclesIndexRoute.url()">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        Enregistrer le véhicule
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
