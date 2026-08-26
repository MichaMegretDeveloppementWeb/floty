<script setup lang="ts">
/**
 * Shortcut modal: pick a company and an exercise, land on the matching
 * tab of the company page. Callers only toggle `open`, every rule lives
 * in `useCompanyYearPicker`.
 */
import { useId, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import { useCompanyYearPicker } from '@/Composables/Company/useCompanyYearPicker';
import type {
    CompanyYearPickerDefault,
    CompanyYearPickerTarget,
} from '@/Composables/Company/useCompanyYearPicker';

const props = withDefaults(
    defineProps<{
        target: CompanyYearPickerTarget;
        defaultYear: CompanyYearPickerDefault;
        title: string;
        description: string;
        submitLabel: string;
        yearLabel?: string;
    }>(),
    { yearLabel: 'Année' },
);

const open = defineModel<boolean>('open', { required: true });

const {
    isLoading,
    isReady,
    hasCompanies,
    companyOptions,
    yearOptions,
    companyId,
    year,
    canSubmit,
    ensureLoaded,
    submit,
} = useCompanyYearPicker({
    target: props.target,
    defaultYear: props.defaultYear,
});

const companyFieldId = useId();
const yearFieldId = useId();

watch(open, (value) => {
    if (value) {
        ensureLoaded();
    }
});
</script>

<template>
    <Modal
        v-model:open="open"
        size="sm"
        overflow-visible
        :title="props.title"
        :description="props.description"
    >
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <div>
                <FieldLabel :for="companyFieldId">Entreprise</FieldLabel>
                <Skeleton v-if="!isReady" class="h-[38px] w-full rounded-lg" />
                <template v-else>
                    <SearchableSelect
                        :id="companyFieldId"
                        v-model="companyId"
                        placeholder="Sélectionner une entreprise"
                        search-placeholder="Rechercher une entreprise…"
                        :options="companyOptions"
                        :disabled="!hasCompanies"
                    />
                    <p v-if="!hasCompanies" class="mt-1 text-xs text-slate-500">
                        Aucune entreprise active enregistrée.
                    </p>
                </template>
            </div>

            <div>
                <FieldLabel :for="yearFieldId">{{ props.yearLabel }}</FieldLabel>
                <Skeleton v-if="!isReady" class="h-[38px] w-full rounded-lg" />
                <SelectInput
                    v-else
                    :id="yearFieldId"
                    v-model="year"
                    :options="yearOptions"
                />
            </div>
        </form>

        <template #footer>
            <Button variant="ghost" @click="open = false">Annuler</Button>
            <Button
                :disabled="!canSubmit"
                :loading="isLoading"
                @click="submit"
            >
                {{ props.submitLabel }}
            </Button>
        </template>
    </Modal>
</template>
