import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * Wrapper sur `fetch` typé pour les endpoints JSON hors visites
 * Inertia (drawer planning, preview taxes, calendrier dispo, etc.).
 *
 * Différences avec l'ancien `lib/http.ts` :
 *   - toast erreur automatique sur 4xx/5xx (`useToasts.push({ tone: 'error' })`)
 *   - extrait `{ message }` JSON du backend si présent (cas des
 *     `BaseAppException` traitées par le handler `bootstrap/app.php`)
 *   - throw conservé : l'appelant peut try/catch pour gérer un état
 *     local (`previewLoading = false`, reset UI, etc.)
 *   - URLs typées : on appelle systématiquement `route.url()` depuis
 *     `@/routes/user/...` (jamais de chaîne hardcodée).
 *
 * Inertia v3 a retiré Axios. Pour les visites SSR utiliser `router.*`,
 * pour les fetches JSON ponctuels c'est ici.
 */

export type UseApiReturn = {
    get: <T>(
        url: string,
        params?: Record<string, string | number | boolean>,
    ) => Promise<T>;
    post: <T>(url: string, body?: Record<string, unknown>) => Promise<T>;
    /**
     * POST avec `FormData` (multipart) pour les uploads de fichiers.
     * N'envoie pas de Content-Type (le navigateur ajoute le boundary
     * multipart automatiquement).
     */
    postFormData: <T>(url: string, formData: FormData) => Promise<T>;
    /**
     * DELETE - pas de body, retourne `void` (typiquement 204).
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
 * Tente d'extraire le `message` français d'une réponse JSON serveur
 * (renvoyée par le handler `bootstrap/app.php` pour `BaseAppException`).
 * En l'absence, fallback sur le message générique pour le statut.
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
