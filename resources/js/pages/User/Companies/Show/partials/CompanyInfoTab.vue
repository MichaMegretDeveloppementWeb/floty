<script setup lang="ts">
import Card from '@/Components/Ui/Card/Card.vue';

defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
}>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <Card>
            <h3 class="mb-3 text-base font-semibold text-slate-900">
                Identité
            </h3>
            <dl class="grid grid-cols-[140px_1fr] gap-2 text-sm">
                <dt class="text-slate-500">Raison sociale</dt>
                <dd class="text-slate-900">{{ company.legalName }}</dd>
                <dt class="text-slate-500">Code court</dt>
                <dd class="font-mono text-slate-900">
                    {{ company.shortCode }}
                </dd>
                <dt class="text-slate-500">SIREN</dt>
                <dd class="font-mono text-slate-900">
                    {{ company.siren ?? '—' }}
                </dd>
                <dt class="text-slate-500">SIRET</dt>
                <dd class="font-mono text-slate-900">
                    {{ company.siret ?? '—' }}
                </dd>
            </dl>
        </Card>

        <Card>
            <h3 class="mb-3 text-base font-semibold text-slate-900">Adresse</h3>
            <p class="text-sm text-slate-700">
                {{ company.addressLine1 ?? '—'
                }}<br v-if="company.addressLine2 !== null" />
                <span v-if="company.addressLine2 !== null">{{
                    company.addressLine2
                }}</span>
                <span v-if="company.addressLine2 !== null"><br /></span>
                {{ company.postalCode ?? '' }} {{ company.city ?? '' }}<br />
                {{ company.country }}
            </p>
        </Card>

        <Card>
            <h3 class="mb-3 text-base font-semibold text-slate-900">Contact</h3>
            <dl class="grid grid-cols-[100px_1fr] gap-2 text-sm">
                <dt class="text-slate-500">Nom</dt>
                <dd class="text-slate-900">{{ company.contactName ?? '—' }}</dd>
                <dt class="text-slate-500">Email</dt>
                <dd class="text-slate-900">
                    {{ company.contactEmail ?? '—' }}
                </dd>
                <dt class="text-slate-500">Téléphone</dt>
                <dd class="text-slate-900">
                    {{ company.contactPhone ?? '—' }}
                </dd>
            </dl>
        </Card>

        <Card>
            <h3 class="mb-3 text-base font-semibold text-slate-900">Statut</h3>
            <div class="flex flex-wrap gap-2">
                <span
                    :class="
                        company.isActive
                            ? 'bg-green-100 text-green-700'
                            : 'bg-slate-200 text-slate-600'
                    "
                    class="rounded px-2 py-0.5 text-xs font-semibold"
                >
                    {{ company.isActive ? 'Active' : 'Inactive' }}
                </span>
                <span
                    v-if="company.isOig"
                    class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700"
                >
                    OIG (organisme d'intérêt général)
                </span>
                <span
                    v-if="company.isIndividualBusiness"
                    class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700"
                >
                    Entreprise individuelle
                </span>
            </div>
        </Card>
    </div>
</template>
