import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useToasts } from '@/Composables/Shared/useToasts';
import { useAssignmentForm } from './useAssignmentForm';

describe('useAssignmentForm', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        // @ts-expect-error — override global fetch dans l'env test
        globalThis.fetch = fetchMock;
        useToasts().clear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('expose un état initial vide', () => {
        const form = useAssignmentForm();

        expect(form.vehicleId.value).toBeNull();
        expect(form.companyId.value).toBeNull();
        expect(form.dates.value).toEqual([]);
        expect(form.submitting.value).toBe(false);
        expect(form.canSubmit.value).toBe(false);
    });

    it('canSubmit devient true quand véhicule + entreprise + dates sont fournis', () => {
        const form = useAssignmentForm();

        form.vehicleId.value = 1;
        form.companyId.value = 2;
        form.dates.value = ['2024-01-15'];

        expect(form.canSubmit.value).toBe(true);
    });

    it("canSubmit reste false si dates vides même avec véhicule + entreprise", () => {
        const form = useAssignmentForm();

        form.vehicleId.value = 1;
        form.companyId.value = 2;
        form.dates.value = [];

        expect(form.canSubmit.value).toBe(false);
    });

    it('reset remet tout à zéro', () => {
        const form = useAssignmentForm();
        form.vehicleId.value = 1;
        form.companyId.value = 2;
        form.dates.value = ['2024-01-15'];

        form.reset();

        expect(form.vehicleId.value).toBeNull();
        expect(form.companyId.value).toBeNull();
        expect(form.dates.value).toEqual([]);
    });

    it('submit refuse si !canSubmit (retourne false sans appel fetch)', async () => {
        const form = useAssignmentForm();

        const ok = await form.submit();

        expect(ok).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('submit POST et push un toast success en cas de 2xx', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ requested: 2, inserted: 2, skipped: 0 }),
        });

        const form = useAssignmentForm();
        form.vehicleId.value = 1;
        form.companyId.value = 2;
        form.dates.value = ['2024-01-15', '2024-01-16'];

        const ok = await form.submit();

        expect(ok).toBe(true);
        expect(form.submitting.value).toBe(false);
        const toasts = useToasts();
        expect(toasts.toasts).toHaveLength(1);
        expect(toasts.toasts[0]?.tone).toBe('success');
    });
});
