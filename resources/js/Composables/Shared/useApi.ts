import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * Typed `fetch` wrapper for JSON endpoints outside Inertia visits
 * (planning drawer, tax preview, availability calendar, etc.).
 *
 * - automatic error toast on 4xx/5xx (`useToasts.push({ tone: 'error' })`)
 * - extracts backend `{ message }` JSON when present (typically `BaseAppException`
 *   handled by `bootstrap/app.php`)
 * - rethrows so the caller can try/catch and manage local state (loading flags, UI reset, etc.)
 * - typed URLs: always call `route.url()` from `@/routes/user/...`, never hardcoded strings.
 *
 * Inertia v3 removed Axios. Use `router.*` for SSR visits; this helper is for one-off JSON fetches.
 */

export type UseApiReturn = {
    get: <T>(
        url: string,
        params?: Record<string, string | number | boolean>,
    ) => Promise<T>;
    post: <T>(url: string, body?: Record<string, unknown>) => Promise<T>;
    /**
     * POST with `FormData` (multipart) for file uploads.
     * Does not send Content-Type (the browser sets the multipart boundary automatically).
     */
    postFormData: <T>(url: string, formData: FormData) => Promise<T>;
    /**
     * DELETE: no body, returns `void` (typically 204).
     */
    delete: (url: string) => Promise<void>;
};

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]!) : '';
}

const baseHeaders: HeadersInit = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

function defaultMessageFor(status?: number): string {
    if (status === undefined) {
        return 'Vérifiez votre connexion réseau et réessayez.';
    }

    if (status >= 500) {
        return "Une erreur serveur s'est produite. Veuillez réessayer ; si le problème persiste, contactez le support.";
    }

    if (status === 419) {
        return 'Votre session a expiré. Rechargez la page et réessayez.';
    }

    if (status === 403) {
        return "Vous n'avez pas l'autorisation d'effectuer cette action.";
    }

    if (status === 404) {
        return 'La ressource demandée est introuvable.';
    }

    return `Le serveur a renvoyé une erreur ${status}.`;
}

/**
 * Tries to extract the server `message` (French) from a JSON response (typically `BaseAppException`
 * handled by `bootstrap/app.php`). Falls back to the generic message for the status when absent.
 */
async function extractServerMessage(
    response: Response,
    fallback: string,
): Promise<string> {
    const body = await response
        .clone()
        .json()
        .catch(() => null);

    if (body !== null && typeof body === 'object' && 'message' in body) {
        const value = (body as { message: unknown }).message;

        if (typeof value === 'string' && value.length > 0) {
            return value;
        }
    }

    return fallback;
}

function pushNetworkError(toasts: ReturnType<typeof useToasts>): void {
    toasts.push({
        tone: 'error',
        title: 'Échec de la requête',
        description: defaultMessageFor(undefined),
    });
}

async function pushHttpError(
    toasts: ReturnType<typeof useToasts>,
    response: Response,
): Promise<void> {
    const description = await extractServerMessage(
        response,
        defaultMessageFor(response.status),
    );

    toasts.push({
        tone: 'error',
        title: 'Échec de la requête',
        description,
    });
}

export function useApi(): UseApiReturn {
    const toasts = useToasts();

    return {
        async get<T>(
            url: string,
            params: Record<string, string | number | boolean> = {},
        ): Promise<T> {
            const query = new URLSearchParams();

            for (const [k, v] of Object.entries(params)) {
                query.set(k, String(v));
            }

            const qs = query.toString();
            let response: Response;

            try {
                response = await fetch(qs ? `${url}?${qs}` : url, {
                    method: 'GET',
                    credentials: 'include',
                    headers: baseHeaders,
                });
            } catch (e) {
                pushNetworkError(toasts);

                throw e;
            }

            if (!response.ok) {
                await pushHttpError(toasts, response);

                throw new Error(
                    `GET ${url} → ${response.status} ${response.statusText}`,
                );
            }

            return (await response.json()) as T;
        },

        async post<T>(
            url: string,
            body: Record<string, unknown> = {},
        ): Promise<T> {
            let response: Response;

            try {
                response = await fetch(url, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        ...baseHeaders,
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: JSON.stringify(body),
                });
            } catch (e) {
                pushNetworkError(toasts);

                throw e;
            }

            if (!response.ok) {
                await pushHttpError(toasts, response);

                throw new Error(
                    `POST ${url} → ${response.status} ${response.statusText}`,
                );
            }

            return (await response.json()) as T;
        },

        async postFormData<T>(url: string, formData: FormData): Promise<T> {
            let response: Response;

            try {
                response = await fetch(url, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        ...baseHeaders,
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: formData,
                });
            } catch (e) {
                pushNetworkError(toasts);

                throw e;
            }

            if (!response.ok) {
                await pushHttpError(toasts, response);

                throw new Error(
                    `POST ${url} → ${response.status} ${response.statusText}`,
                );
            }

            return (await response.json()) as T;
        },

        async delete(url: string): Promise<void> {
            let response: Response;

            try {
                response = await fetch(url, {
                    method: 'DELETE',
                    credentials: 'include',
                    headers: {
                        ...baseHeaders,
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                });
            } catch (e) {
                pushNetworkError(toasts);

                throw e;
            }

            if (!response.ok) {
                await pushHttpError(toasts, response);

                throw new Error(
                    `DELETE ${url} → ${response.status} ${response.statusText}`,
                );
            }
        },
    };
}
