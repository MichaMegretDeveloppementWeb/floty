<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    colors: App.Data.User.Company.CompanyColorOptionData[];
}>();

const form = useForm({
    legal_name: '',
    short_code: '',
    color: 'indigo',
    siren: '',
    siret: '',
    address_line_1: '',
    address_line_2: '',
    postal_code: '',
    city: '',
    country: 'FR',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
});

const submit = (): void => {
    form.post('/app/companies');
};
</script>

<template>
    <Head title="Nouvelle entreprise" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Entreprises</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Nouvelle entreprise
                </h1>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <section class="flex flex-col gap-4">
                    <p class="eyebrow">Identité</p>
                    <TextInput
                        v-model="form.legal_name"
                        label="Raison sociale"
                        :error="form.errors.legal_name"
                        required
                    />
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput
                            v-model="form.short_code"
                            label="Code court"
                            hint="2 à 5 caractères. Sert d'étiquette courte dans le planning."
                            :error="form.errors.short_code"
                            required
                        />
                        <SelectInput
                            v-model="form.color"
                            label="Couleur"
                            :options="colors"
                            :error="form.errors.color"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput
                            v-model="form.siren"
                            label="SIREN"
                            hint="9 chiffres."
                            mono
                            :error="form.errors.siren"
                        />
                        <TextInput
                            v-model="form.siret"
                            label="SIRET"
                            hint="14 chiffres (optionnel)."
                            mono
                            :error="form.errors.siret"
                        />
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <p class="eyebrow">Adresse</p>
                    <TextInput
                        v-model="form.address_line_1"
                        label="Adresse"
                        :error="form.errors.address_line_1"
                    />
                    <TextInput
                        v-model="form.address_line_2"
                        label="Complément"
                        :error="form.errors.address_line_2"
                    />
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_2fr_1fr]"
                    >
                        <TextInput
                            v-model="form.postal_code"
                            label="Code postal"
                            :error="form.errors.postal_code"
                        />
                        <TextInput
                            v-model="form.city"
                            label="Ville"
                            :error="form.errors.city"
                        />
                        <TextInput
                            v-model="form.country"
                            label="Pays (ISO)"
                            hint="FR, BE, CH..."
                            :error="form.errors.country"
                            required
                        />
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <p class="eyebrow">Contact</p>
                    <TextInput
                        v-model="form.contact_name"
                        label="Nom du contact"
                        :error="form.errors.contact_name"
                    />
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput
                            v-model="form.contact_email"
                            type="email"
                            label="E-mail"
                            :error="form.errors.contact_email"
                        />
                        <TextInput
                            v-model="form.contact_phone"
                            type="tel"
                            label="Téléphone"
                            :error="form.errors.contact_phone"
                        />
                    </div>
                </section>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <Link href="/app/companies">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
