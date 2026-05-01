<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText, X } from 'lucide-vue-next';
import { ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';
import { useContractForm } from '@/Composables/Contract/useContractForm';
import { storePendingDocuments } from '@/Composables/Contract/useContractFormPendingDocuments';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import ContractFormFields from './partials/ContractFormFields.vue';

const props = defineProps<{
    options: {
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
    };
    busyDatesByVehicleId: Record<number, string[]>;
}>();

const MAX_DOCUMENTS = 5;
const MAX_SIZE_BYTES = 10 * 1024 * 1024;

const { form, canSubmit, submit } = useContractForm();
const pendingFiles = ref<File[]>([]);

function onFilesAdded(files: File[]): void {
    const remaining = MAX_DOCUMENTS - pendingFiles.value.length;
    pendingFiles.value = [...pendingFiles.value, ...files.slice(0, remaining)];
}

function removePendingFile(index: number): void {
    pendingFiles.value = pendingFiles.value.filter((_, i) => i !== index);
}

async function submitWithDocuments(): Promise<void> {
    // Sérialise les fichiers dans sessionStorage avant le submit Inertia.
    // La page Show post-redirect les uploadera en arrière-plan.
    if (pendingFiles.value.length > 0) {
        await storePendingDocuments(pendingFiles.value);
    }

    submit();
}
</script>

<template>
    <Head title="Nouveau contrat" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <Link
                    :href="contractsIndexRoute.url()"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
                >
                    <ArrowLeft :size="14" :stroke-width="1.75" />
                    Retour aux contrats
                </Link>
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Nouveau contrat
                    </h1>
                </div>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submitWithDocuments"
            >
                <ContractFormFields
                    :form="form"
                    :options="props.options"
                    :busy-dates-by-vehicle-id="props.busyDatesByVehicleId"
                />

                <section class="flex flex-col gap-3 border-t border-slate-100 pt-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-600">
                            Documents (optionnel)
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Joindre jusqu'à {{ MAX_DOCUMENTS }} PDF (10 Mo max chacun).
                            Les documents seront uploadés après la création du contrat.
                        </p>
                    </div>

                    <ul
                        v-if="pendingFiles.length > 0"
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="(file, idx) in pendingFiles"
                            :key="idx"
                            class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"
                        >
                            <FileText
                                :size="20"
                                :stroke-width="1.75"
                                class="shrink-0 text-rose-500"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">
                                    {{ file.name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ (file.size / (1024 * 1024)).toFixed(1) }} Mo
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                                :title="`Retirer ${file.name}`"
                                :aria-label="`Retirer ${file.name}`"
                                @click="removePendingFile(idx)"
                            >
                                <X :size="14" :stroke-width="1.75" />
                            </button>
                        </li>
                    </ul>

                    <DocumentDropZone
                        v-if="pendingFiles.length < MAX_DOCUMENTS"
                        accept="application/pdf"
                        :max-size-bytes="MAX_SIZE_BYTES"
                        :max-files="MAX_DOCUMENTS - pendingFiles.length"
                        multiple
                        @files-added="onFilesAdded"
                    />
                </section>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <Link :href="contractsIndexRoute.url()">
                        <Button type="button" variant="secondary">
                            Annuler
                        </Button>
                    </Link>
                    <Button
                        type="submit"
                        :loading="form.processing"
                        :disabled="!canSubmit"
                    >
                        Enregistrer le contrat
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
