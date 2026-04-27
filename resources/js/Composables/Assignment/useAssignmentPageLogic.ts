import { computed, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useAssignmentForm } from '@/Composables/Assignment/useAssignmentForm';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';
import { useVehicleAvailability } from '@/Composables/Planning/useVehicleAvailability';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';

type CompanyOption = App.Data.User.Company.CompanyOptionData;
type VehicleOption = App.Data.User.Vehicle.VehicleOptionData;
type FiscalPreview = App.Data.User.Fiscal.FiscalPreviewData;

/**
 * Orchestration de la page « Attribution rapide »
 * (Pages/User/Assignments/Index/Index.vue).
 *
 * Combine `useAssignmentForm`, `useVehicleAvailability` et
 * `useFiscalPreview` + dérive les ensembles de dates affichables
 * dans le calendrier + résout les labels véhicule/entreprise pour
 * le récapitulatif. Permet à la page de rester un orchestrateur pur
 * sans `watch` ni `computed` (R7 + R9 d'ADR-0013).
 */
export type UseAssignmentPageLogicReturn = {
    fiscalYear: Ref<number>;
    vehicleId: Ref<number | null>;
    companyId: Ref<number | null>;
    dates: Ref<string[]>;
    submitting: Ref<boolean>;
    canSubmit: ComputedRef<boolean>;
    preview: Ref<FiscalPreview | null>;
    previewLoading: Ref<boolean>;
    disabledDates: ComputedRef<string[]>;
    pairDatesForCouple: ComputedRef<string[]>;
    selectedVehicleLabel: ComputedRef<string | null>;
    selectedCompanyLabel: ComputedRef<string | null>;
    /** POST l'attribution. Retourne true si succès, false sinon. */
    submit: () => Promise<boolean>;
};

export function useAssignmentPageLogic(
    vehicles: VehicleOption[],
    companies: CompanyOption[],
): UseAssignmentPageLogicReturn {
    const { currentYear: fiscalYear } = useFiscalYear();
    const form = useAssignmentForm();
    const availability = useVehicleAvailability();
    const fiscalPreview = useFiscalPreview();

    watch(form.vehicleId, async (vehicleId) => {
        form.dates.value = [];
        fiscalPreview.reset();
        availability.reset();

        if (vehicleId === null) {
            return;
        }

        await availability.load(vehicleId, fiscalYear.value);
    });

    watch(
        () => [form.vehicleId.value, form.companyId.value, form.dates.value] as const,
        ([vehicleId, companyId, dates]) => {
            fiscalPreview.fetch({ vehicleId, companyId, dates });
        },
        { deep: true },
    );

    const disabledDates = computed((): string[] => {
        const pairSet = new Set(availability.pairDatesFor(form.companyId.value));

        return availability.busyDates.value.filter((d) => !pairSet.has(d));
    });

    const pairDatesForCouple = computed((): string[] =>
        availability.pairDatesFor(form.companyId.value),
    );

    const selectedVehicleLabel = computed((): string | null => {
        if (form.vehicleId.value === null) {
            return null;
        }

        return vehicles.find((v) => v.id === form.vehicleId.value)?.label ?? null;
    });

    const selectedCompanyLabel = computed((): string | null => {
        if (form.companyId.value === null) {
            return null;
        }

        return (
            companies.find((c) => c.id === form.companyId.value)?.legalName ?? null
        );
    });

    return {
        fiscalYear,
        vehicleId: form.vehicleId,
        companyId: form.companyId,
        dates: form.dates,
        submitting: form.submitting,
        canSubmit: form.canSubmit,
        preview: fiscalPreview.preview,
        previewLoading: fiscalPreview.loading,
        disabledDates,
        pairDatesForCouple,
        selectedVehicleLabel,
        selectedCompanyLabel,
        submit: form.submit,
    };
}
