import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * Helper : crée une Response mockée minimaliste compatible avec
 * `useApi` (qui appelle `response.clone().json()` pour extraire le
 * `{ message }` du backend).
 */
function makeResponse(opts: {
    ok: boolean;
    status?: number;
    statusText?: string;
    body?: unknown;
}): Response {
    const json = async () => opts.body ?? null;
    const response = {
        ok: opts.ok,
        status: opts.status ?? (opts.ok ? 200 : 500),
        statusText: opts.statusText ?? '',
        json,
        clone() {
            return { json };
        },
    } as unknown as Response;

    return response;
}

describe('useApi', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        // @ts-expect-error - override global fetch dans l'env test
        globalThis.fetch = fetchMock;
        useToasts().clear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('get', () => {
        it('retourne les données JSON parsées en cas de 2xx', async () => {
            fetchMock.mockResolvedValueOnce(
                makeResponse({ ok: true, status: 200, statusText: 'OK', body: { hello: 'world' } }),
            );

            const api = useApi();
            const result = await api.get<{ hello: string }>('/api/test');

            expect(result).toEqual({ hello: 'world' });
        });

        it('sérialise les params en query string', async () => {
            fetchMock.mockResolvedValueOnce(makeResponse({ ok: true, body: {} }));

            const api = useApi();
            await api.get('/api/test', { vehicleId: 42, week: 10 });

            expect(fetchMock).toHaveBeenCalledWith(
                '/api/test?vehicleId=42&week=10',
                expect.objectContaining({ method: 'GET' }),
            );
        });

        it('push un toast erreur et throw sur 4xx (sans body)', async () => {
            fetchMock.mockResolvedValueOnce(
                makeResponse({ ok: false, status: 422, statusText: 'Unprocessable Entity' }),
            );

            const api = useApi();
            const toasts = useToasts();

            await expect(api.get('/api/test')).rejects.toThrow();
            expect(toasts.toasts).toHaveLength(1);
            expect(toasts.toasts[0]?.tone).toBe('error');
        });

        it("affiche le message du body serveur quand il est présent (BaseAppException)", async () => {
            fetchMock.mockResolvedValueOnce(
                makeResponse({
                    ok: false,
                    status: 422,
                    statusText: 'Unprocessable Entity',
                    body: { message: "L'année fiscale 2099 n'est pas supportée.", code: 'FiscalCalculationException' },
                }),
            );

            const api = useApi();
            const toasts = useToasts();

            await expect(api.get('/api/test')).rejects.toThrow();
            expect(toasts.toasts).toHaveLength(1);
            expect(toasts.toasts[0]?.description).toContain("2099");
            expect(toasts.toasts[0]?.description).toContain("n'est pas supportée");
        });

        it('utilise un message générique 5xx en fallback', async () => {
            fetchMock.mockResolvedValueOnce(
                makeResponse({ ok: false, status: 500, statusText: 'Internal Server Error' }),
            );

            const api = useApi();
            const toasts = useToasts();

            await expect(api.get('/api/test')).rejects.toThrow();
            expect(toasts.toasts[0]?.description).toContain('serveur');
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
            fetchMock.mockResolvedValueOnce(makeResponse({ ok: true, body: { inserted: 3 } }));

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
