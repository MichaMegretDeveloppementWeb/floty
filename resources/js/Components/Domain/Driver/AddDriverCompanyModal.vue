<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { store as storeRoute } from '@/routes/user/drivers/memberships';

const props = defineProps<{
    driverId: number;
    existingCompanyIds: number[];
}>();

const emit = defineEmits<{ close: [] }>();

const open = ref(true);

// La liste des companies disponibles est partagée via shared props ou
// on délègue le chargement via fetch (pour V1.2 simple, on suppose que
// l'utilisateur connaît l'ID — un futur enrichissement L4/L5 ajoutera
// un endpoint dédié).
const form = useForm({
    company_id: null as number | null,
    joined_at: new Date().toISOString().slice(0, 10),
});

const companyOptions = computed(() => {
    // Pour V1.2 : pas de chargement async ici. En L4 on enrichira via shared props.
    // Placeholder : input numérique direct.
    return [];
});

function close(): void {
    open.value = false;
    emit('close');
}

function submit(): void {
    form.post(storeRoute(props.driverId).url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Ajouter une entreprise"
        @close="emit('close')"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <FieldLabel for="add-company-id">ID entreprise</FieldLabel>
                <SelectInput
                    id="add-company-id"
                    v-model="form.company_id"
                    placeholder="Sélectionner"
                    :options="companyOptions"
                />
                <p
                    v-if="companyOptions.length === 0"
                    class="mt-1 text-xs text-amber-600"
                >
                    Sélecteur company à enrichir en L4 (Show Company onglets).
                </p>
                <InputError :message="form.errors.company_id" />
            </div>
            <div>
                <FieldLabel for="add-joined-at">Date d'entrée</FieldLabel>
                <DateInput id="add-joined-at" v-model="form.joined_at" />
                <InputError :message="form.errors.joined_at" />
            </div>
            <div class="flex justify-end gap-2">
                <Button variant="ghost" type="button" @click="close"
                    >Annuler</Button
                >
                <Button type="submit" :loading="form.processing"
                    >Ajouter</Button
                >
            </div>
        </form>
    </Modal>
</template>
