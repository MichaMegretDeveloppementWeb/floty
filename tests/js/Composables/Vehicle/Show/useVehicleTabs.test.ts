import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { useVehicleTabs } from '@/Composables/Vehicle/Show/useVehicleTabs';

type TabsState = ReturnType<typeof useVehicleTabs>;

function mountTabs(): { state: TabsState } {
    let captured: TabsState | null = null;

    const Wrapper = defineComponent({
        setup() {
            captured = useVehicleTabs();

            return () => h('div');
        },
    });

    mount(Wrapper);

    return { state: captured! };
}

describe('useVehicleTabs', () => {
    beforeEach(() => {
        vi.mocked(router.get).mockClear();
        // Land directly on the controls tab (its lazy prop is eager + cached).
        window.history.replaceState({}, '', '/app/vehicles/64?tab=controls');
    });

    it('refreshAfterMutation re-fetches the active tab lazy prop (exit/reactivation fix)', async () => {
        const { state } = mountTabs();
        await nextTick();

        // On a direct ?tab=controls load, nothing is re-fetched (eager + cached).
        vi.mocked(router.get).mockClear();

        // Simulate the post-mutation redirect dropping the optional prop.
        state.refreshAfterMutation();
        await nextTick();

        expect(router.get).toHaveBeenCalledTimes(1);
        const call = vi.mocked(router.get).mock.calls[0]!;
        const options = call[2] as { only?: string[] };
        expect(options.only).toContain('vehicleControls');
    });

    it('refreshAfterMutation on a no-lazy-prop tab does not trigger a reload', async () => {
        window.history.replaceState({}, '', '/app/vehicles/64?tab=events');

        const { state } = mountTabs();
        await nextTick();
        vi.mocked(router.get).mockClear();

        state.refreshAfterMutation();
        await nextTick();

        // The events tab has no lazy prop (data lives in the eager vehicle DTO).
        expect(router.get).not.toHaveBeenCalled();
    });
});
