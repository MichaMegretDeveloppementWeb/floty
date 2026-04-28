import { computed } from 'vue';
import type { ComputedRef, WritableComputedRef } from 'vue';

type VehicleOption = App.Data.User.Vehicle.VehicleOptionData;
type CompanyOption = App.Data.User.Company.CompanyOptionData;
type SelectOption = { value: string; label: string };

/**
 * Transformations I/O du partial `AssignmentForm` (page Index
 * Attributions) :
 *
 *   - `vehicleOptions` / `companyOptions` : adapte les DTOs backend
 *     au shape attendu par `<SelectInput>` (value/label en string)
 *   - `vehicleIdString` / `companyIdString` : ponts WritableComputed
 *     entre les `<select>` (value `string`) et les v-model parent
 *     (typé `number | null`)
 *   - `datesProxy` : pont 1:1 vers l'`update:selectedDates` parent,
 *     pour permettre `v-model:selected` sur le `<MultiDatePicker>`
 */
export function useAssignmentFormShape(
    props: {
        vehicles: VehicleOption[];
        companies: CompanyOption[];
        selectedVehicleId: number | null;
        selectedCompanyId: number | null;
        selectedDates: string[];
    },
    emit: {
        (e: 'update:selectedVehicleId', value: number | null): void;
        (e: 'update:selectedCompanyId', value: number | null): void;
        (e: 'update:selectedDates', value: string[]): void;
    },
): {
    vehicleOptions: ComputedRef<SelectOption[]>;
    companyOptions: ComputedRef<SelectOption[]>;
    vehicleIdString: WritableComputedRef<string>;
    companyIdString: WritableComputedRef<string>;
    datesProxy: WritableComputedRef<string[]>;
} {
    const vehicleOptions = computed<SelectOption[]>(() =>
        props.vehicles.map((v) => ({
            value: String(v.id),
            label: v.label,
        })),
    );

    const companyOptions = computed<SelectOption[]>(() =>
        props.companies.map((c) => ({
            value: String(c.id),
            label: `${c.shortCode} — ${c.legalName}`,
        })),
    );

    const vehicleIdString = computed<string>({
        get: () =>
            props.selectedVehicleId !== null ? String(props.selectedVehicleId) : '',
        set: (v) =>
            emit('update:selectedVehicleId', v === '' ? null : Number(v)),
    });

    const companyIdString = computed<string>({
        get: () =>
            props.selectedCompanyId !== null ? String(props.selectedCompanyId) : '',
        set: (v) =>
            emit('update:selectedCompanyId', v === '' ? null : Number(v)),
    });

    const datesProxy = computed<string[]>({
        get: () => props.selectedDates,
        set: (v) => emit('update:selectedDates', v),
    });

    return {
        vehicleOptions,
        companyOptions,
        vehicleIdString,
        companyIdString,
        datesProxy,
    };
}
