<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { leave as leaveRoute } from '@/routes/user/drivers/memberships';

const props = defineProps<{
    driverId: number;
    companyId: number;
    driverFullName: string;
    companyName: string;
}>();

const emit = defineEmits<{ close: [] }>();

const open = ref(true);

type FormShape = {
    left_at: string;
    future_contracts_resolution: 'replace' | 'detach' | 'none';
    replacement_map: Record<number, number | null>;
};

const form = useForm<FormShape>({
    left_at: new Date().toISOString().slice(0, 10),
    future_contracts_resolution: 'none',
    replacement_map: {},
});

const resolutionOptions = [
    { value: 'none', label: 'Aucun contrat à venir à résoudre' },
    {
        value: 'replace',
        label: 'Remplacer par un autre conducteur (à indiquer pour chaque contrat)',
    },
    {
        value: 'detach',
        label: 'Retirer le conducteur des contrats à venir (driver_id = null)',
    },
];

function close(): void {
    open.value = false;
    emit('close');
}

function submit(): void {
    form.patch(leaveRoute([props.driverId, props.companyId]).url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Sortir le conducteur de l'entreprise"
        size="lg"
        @close="emit('close')"
    >
        <p class="text-sm text-slate-700">
            Sortir <strong>{{ driverFullName }}</strong> de
            <strong>{{ companyName }}</strong
            >.
        </p>
        <p class="mt-2 text-xs text-slate-500">
            Cette action pose une date de sortie sur le rattachement.
            L'historique des contrats passés est conservé. Si le conducteur a
            des contrats à venir après cette date, choisissez comment les
            résoudre.
        </p>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <FieldLabel for="leave-left-at">Date de sortie</FieldLabel>
                <DateInput id="leave-left-at" v-model="form.left_at" />
                <InputError :message="form.errors.left_at" />
            </div>

            <div>
                <FieldLabel for="leave-resolution"
                    >Contrats à venir après la date de sortie</FieldLabel
                >
                <SelectInput
                    id="leave-resolution"
                    v-model="form.future_contracts_resolution"
                    :options="resolutionOptions"
                />
                <InputError
                    :message="form.errors.future_contracts_resolution"
                />
                <p class="mt-1 text-xs text-amber-600">
                    L'enrichissement de la modale (liste des contrats à résoudre
                    + sélecteurs de remplacement par contrat) sera livré
                    ultérieurement. Pour l'instant, choisissez 'none' si aucun
                    contrat à venir, sinon 'detach' pour les retirer en bloc.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" type="button" @click="close"
                    >Annuler</Button
                >
                <Button
                    type="submit"
                    variant="destructive"
                    :loading="form.processing"
                >
                    Confirmer la sortie
                </Button>
            </div>
        </form>
    </Modal>
</template>
