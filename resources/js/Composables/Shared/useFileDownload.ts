import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * Triggers a binary file download from a POST endpoint that streams the
 * file back (e.g. the planning PDF export).
 *
 * Inertia visits and {@see useApi} can only handle JSON · this helper does
 * the one thing they cannot · a `fetch` POST that reads the response as a
 * Blob and hands it to the browser as a download, keeping the user on the
 * current page. Errors raise a French toast (mirroring {@see useApi}) and
 * rethrow so the caller can reset its loading state.
 */

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]!) : '';
}

function filenameFromDisposition(
    header: string | null,
    fallback: string,
): string {
    if (header === null) {
        return fallback;
    }

    const match = header.match(/filename="?([^";]+)"?/i);

    return match ? match[1]! : fallback;
}

function saveBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}

export type UseFileDownloadReturn = {
    downloadViaPost: (
        url: string,
        body: Record<string, unknown>,
        fallbackFilename: string,
    ) => Promise<void>;
};

export function useFileDownload(): UseFileDownloadReturn {
    const toasts = useToasts();

    async function downloadViaPost(
        url: string,
        body: Record<string, unknown>,
        fallbackFilename: string,
    ): Promise<void> {
        let response: Response;

        try {
            response = await fetch(url, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/pdf',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify(body),
            });
        } catch (e) {
            toasts.push({
                tone: 'error',
                title: 'Échec du téléchargement',
                description: 'Vérifiez votre connexion réseau et réessayez.',
            });

            throw e;
        }

        if (!response.ok) {
            const description =
                response.status === 429
                    ? 'Trop de requêtes en peu de temps. Patientez quelques secondes avant de réessayer.'
                    : "L'export n'a pas pu être généré. Veuillez réessayer ; si le problème persiste, contactez le support.";

            toasts.push({
                tone: 'error',
                title: "Échec de l'export",
                description,
            });

            throw new Error(
                `POST ${url} → ${response.status} ${response.statusText}`,
            );
        }

        const blob = await response.blob();
        const filename = filenameFromDisposition(
            response.headers.get('Content-Disposition'),
            fallbackFilename,
        );

        saveBlob(blob, filename);
    }

    return { downloadViaPost };
}
