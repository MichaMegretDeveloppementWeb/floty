/**
 * Helpers HTTP JSON pour les endpoints hors-Inertia (planning drawer).
 *
 * Inertia v3 a retiré Axios, mais `router.get()`/`router.post()` sont
 * orientés visites SSR. Pour les fetches JSON ponctuels (preview taxes,
 * détail de semaine), on utilise `fetch` natif avec :
 *   - cookie `XSRF-TOKEN` → header `X-XSRF-TOKEN` (Laravel le vérifie)
 *   - header `Accept: application/json` (évite Inertia de récupérer l'appel)
 *   - `credentials: 'include'` pour envoyer les cookies de session
 */

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]!) : '';
}

/**
 * GET JSON avec params sérialisés en query string.
 */
export async function getJson<T>(
    url: string,
    params: Record<string, string | number | boolean> = {},
): Promise<T> {
    const query = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        query.set(k, String(v));
    }
    const qs = query.toString();
    const res = await fetch(qs ? `${url}?${qs}` : url, {
        method: 'GET',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    if (!res.ok) {
        throw new Error(`GET ${url} → ${res.status} ${res.statusText}`);
    }
    return (await res.json()) as T;
}

/**
 * POST JSON, corps stringifié automatiquement.
 */
export async function postJson<T>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        throw new Error(`POST ${url} → ${res.status} ${res.statusText}`);
    }
    return (await res.json()) as T;
}
