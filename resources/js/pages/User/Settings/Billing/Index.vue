<script setup lang="ts">
/**
 * Singleton issuer settings powering every invoice PDF. All fields are
 * optional; previously emitted invoices stay immutable.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Receipt } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { update as updateRoute } from '@/routes/user/settings/billing';

const props = defineProps<{
    settings: App.Data.User.Billing.BillingSettingsData;
}>();

const form = useForm({
    name: props.settings.name ?? '',
    addressLine1: props.settings.addressLine1 ?? '',
    addressLine2: props.settings.addressLine2 ?? '',
    postalCode: props.settings.postalCode ?? '',
    city: props.settings.city ?? '',
    siren: props.settings.siren ?? '',
    contactEmail: props.settings.contactEmail ?? '',
});

function submit(): void {
    form.transform((data) => ({
        name: data.name === '' ? null : data.name,
        address_line_1: data.addressLine1 === '' ? null : data.addressLine1,
        address_line_2: data.addressLine2 === '' ? null : data.addressLine2,
        postal_code: data.postalCode === '' ? null : data.postalCode,
        city: data.city === '' ? null : data.city,
        siren: data.siren === '' ? null : data.siren,
        contact_email: data.contactEmail === '' ? null : data.contactEmail,
    })).post(updateRoute.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Paramètres · Facturation" />

    <UserLayout>
        <div class="flex flex-col gap-6 max-w-[48em] m-auto w-full">
            <header class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <Receipt :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wider uppercase text-slate-500">
                        Paramètres
                    </p>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        Émetteur
                    </h1>
                    <p class="text-sm text-slate-500">
                        Coordonnées imprimées sur les annexes de factures émises depuis Floty.
                        Les annexes déjà émises ne sont pas modifiées rétroactivement
                        (immuabilité comptable).
                    </p>
                </div>
            </header>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Identité
                        </h2>
                    </template>

                    <div class="flex flex-col gap-4">
                        <TextInput
                            v-model="form.name"
                            label="Raison sociale"
                            placeholder="Ex. Floty Location SAS"
                            :error="form.errors.name"
                        />
                        <TextInput
                            v-model="form.siren"
                            label="SIREN"
                            placeholder="9 chiffres"
                            :error="form.errors.siren"
                        />
                        <TextInput
                            v-model="form.contactEmail"
                            type="email"
                            label="Email de contact"
                            placeholder="contact@…"
                            :error="form.errors.contactEmail"
                        />
                    </div>
                </Card>

                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Adresse
                        </h2>
                    </template>

                    <div class="flex flex-col gap-4">
                        <TextInput
                            v-model="form.addressLine1"
                            label="Adresse"
                            placeholder="N° et nom de rue"
                            :error="form.errors.addressLine1"
                        />
                        <TextInput
                            v-model="form.addressLine2"
                            label="Complément (optionnel)"
                            placeholder="Bâtiment, étage…"
                            :error="form.errors.addressLine2"
                        />
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <TextInput
                                v-model="form.postalCode"
                                label="Code postal"
                                :error="form.errors.postalCode"
                            />
                            <div class="sm:col-span-2">
                                <TextInput
                                    v-model="form.city"
                                    label="Ville"
                                    :error="form.errors.city"
                                />
                            </div>
                        </div>
                    </div>
                </Card>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        :loading="form.processing"
                    >
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
