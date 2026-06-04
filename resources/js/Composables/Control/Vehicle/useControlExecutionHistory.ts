import { ref } from 'vue';
import type { Ref } from 'vue';
import { history as historyRoute } from '@/routes/user/vehicles/controls';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;

export type ExecutionHistoryDocument = {
    id: number;
    filename: string;
    sizeBytes: number;
    mimeType: string;
};

export type ExecutionHistoryEntry = {
    id: number;
    executedOn: string;
    note: string | null;
    documents: ExecutionHistoryDocument[];
};

/**
 * Lazy loader for a control's execution history (Chantier B / B2). Fetches the
 * JSON endpoint on demand (modal open), keyed by the control's global definition
 * or vehicle-specific override.
 */
export function useControlExecutionHistory(vehicleId: number): {
    entries: Ref<ExecutionHistoryEntry[]>;
    loading: Ref<boolean>;
    error: Ref<boolean>;
    load: (control: EffectiveControl) => Promise<void>;
} {
    const entries = ref<ExecutionHistoryEntry[]>([]);
    const loading = ref<boolean>(false);
    const error = ref<boolean>(false);

    async function load(control: EffectiveControl): Promise<void> {
        loading.value = true;
        error.value = false;
        entries.value = [];

        const query: Record<string, number> = control.isVehicleSpecific
            ? { override_id: control.overrideId ?? 0 }
            : { definition_id: control.definitionId ?? 0 };

        try {
            const response = await fetch(historyRoute.url(vehicleId, { query }), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('history request failed');
            }

            const payload = (await response.json()) as { executions: ExecutionHistoryEntry[] };
            entries.value = payload.executions;
        } catch {
            error.value = true;
        } finally {
            loading.value = false;
        }
    }

    return { entries, loading, error, load };
}
