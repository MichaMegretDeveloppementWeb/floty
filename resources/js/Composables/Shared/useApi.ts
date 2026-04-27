import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * Wrapper sur `fetch` typé pour les endpoints JSON hors visites
 * Inertia (drawer planning, preview taxes, calendrier dispo, etc.).
 *
 * Différences avec l'ancien `lib/http.ts` :
 *   - toast erreur automatique sur 4xx/5xx (`useToasts.push({ tone: 'error' })`)
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
};

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]!) : '';
}

const baseHeaders: HeadersInit = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

/**
 * Construit le toast d'erreur affiché à l'utilisateur en cas d'échec
 * réseau ou HTTP.
 */
function toastError(toasts: ReturnType<typeof useToasts>, status?: number): void {
    const description = status !== undefined
        ? `Le serveur a renvoyé une erreur ${status}.`
        : 'Vérifiez votre connexion réseau et réessayez.';

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
                toastError(toasts);

                throw e;
            }

            if (!response.ok) {
                toastError(toasts, response.status);

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
                toastError(toasts);

                throw e;
            }

            if (!response.ok) {
                toastError(toasts, response.status);

                throw new Error(
                    `POST ${url} → ${response.status} ${response.statusText}`,
                );
            }

            return (await response.json()) as T;
        },
    };
}
