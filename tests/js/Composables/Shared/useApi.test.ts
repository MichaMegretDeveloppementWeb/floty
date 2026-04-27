import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';

describe('useApi', () => {
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

    describe('get', () => {
        it('retourne les données JSON parsées en cas de 2xx', async () => {
            fetchMock.mockResolvedValueOnce({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: async () => ({ hello: 'world' }),
            });

            const api = useApi();
            const result = await api.get<{ hello: string }>('/api/test');

            expect(result).toEqual({ hello: 'world' });
        });

        it('sérialise les params en query string', async () => {
            fetchMock.mockResolvedValueOnce({
                ok: true,
                json: async () => ({}),
            });

            const api = useApi();
            await api.get('/api/test', { vehicleId: 42, week: 10 });

            expect(fetchMock).toHaveBeenCalledWith(
                '/api/test?vehicleId=42&week=10',
                expect.objectContaining({ method: 'GET' }),
            );
        });

        it('push un toast erreur et throw sur 4xx', async () => {
            fetchMock.mockResolvedValueOnce({
                ok: false,
                status: 422,
                statusText: 'Unprocessable Entity',
            });

            const api = useApi();
            const toasts = useToasts();

            await expect(api.get('/api/test')).rejects.toThrow();
            expect(toasts.toasts).toHaveLength(1);
            expect(toasts.toasts[0]?.tone).toBe('error');
        });

        it('push un toast erreur sur network failure', async () => {
            fetchMock.mockRejectedValueOnce(new TypeError('Network down'));

            const api = useApi();
            const toasts = useToasts();

            await expect(api.get('/api/test')).rejects.toThrow();
            expect(toasts.toasts).toHaveLength(1);
        });
    });

    describe('post', () => {
        it('envoie le body en JSON et retourne la réponse parsée', async () => {
            fetchMock.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ inserted: 3 }),
            });

            const api = useApi();
            const result = await api.post<{ inserted: number }>('/api/bulk', {
                ids: [1, 2, 3],
            });

            expect(result.inserted).toBe(3);
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/bulk',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ ids: [1, 2, 3] }),
                }),
            );
        });
    });
});
