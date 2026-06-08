import { useForm } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { useVehicleEditForm } from '@/Composables/Vehicle/Edit/useVehicleEditForm';

type Vehicle = App.Data.User.Vehicle.VehicleData;

/**
 * The global Inertia mock exposes `useForm` as a bare `vi.fn()`. Here we
 * back it with a real reactive object so the composable's watchers fire
 * on mutation, which is exactly what the acquisition -> economic-use
 * propagation under test relies on.
 */
function makeReactiveForm() {
    return (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            transform() {
                return this;
            },
            patch: vi.fn(),
            reset: vi.fn(),
        });
}

function makeVehicle(overrides: Partial<Vehicle> = {}): Vehicle {
    return {
        id: 1,
        licensePlate: 'AA-123-BB',
        brand: 'Peugeot',
        model: 'Partner',
        vin: null,
        color: null,
        firstFrenchRegistrationDate: '2023-03-05',
        firstOriginRegistrationDate: '2023-03-05',
        firstEconomicUseDate: '2023-03-05',
        acquisitionDate: '2023-03-05',
        mileageCurrent: null,
        notes: null,
        currentFiscalCharacteristics: null,
        fiscalCharacteristicsHistory: [],
        ...overrides,
    } as unknown as Vehicle;
}

function mountComposable(vehicle: Vehicle) {
    let captured: ReturnType<typeof useVehicleEditForm> | null = null;

    const Wrapper = defineComponent({
        setup() {
            captured = useVehicleEditForm({ vehicle });

            return () => h('div');
        },
    });

    const wrapper = mount(Wrapper);

    return { ctx: captured!, wrapper };
}

describe('useVehicleEditForm', () => {
    beforeEach(() => {
        vi.mocked(useForm).mockImplementation(
            makeReactiveForm() as unknown as typeof useForm,
        );
    });

    it('propage acquisition_date vers first_economic_use_date quand on la modifie', async () => {
        const { ctx } = mountComposable(makeVehicle());
        await nextTick();

        ctx.form.acquisition_date = '2025-03-15';
        await nextTick();

        expect(ctx.form.first_economic_use_date).toBe('2025-03-15');
    });

    it('préserve une affectation économique divergente tant que l\'acquisition n\'est pas touchée', async () => {
        const { ctx } = mountComposable(
            makeVehicle({
                acquisitionDate: '2023-03-05',
                firstEconomicUseDate: '2022-01-10',
            }),
        );
        await nextTick();

        // No mutation of acquisition_date: the pre-existing divergent value stays.
        expect(ctx.form.first_economic_use_date).toBe('2022-01-10');
    });

    it('ne propage pas en sens inverse (economic-use -> acquisition)', async () => {
        const { ctx } = mountComposable(makeVehicle());
        await nextTick();

        ctx.form.first_economic_use_date = '2025-12-31';
        await nextTick();

        expect(ctx.form.acquisition_date).toBe('2023-03-05');
    });
});
