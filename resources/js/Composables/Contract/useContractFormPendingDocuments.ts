/**
 * sessionStorage handover between the contract Create page and the post-redirect Show page
 * for PDF documents pending upload.
 *
 * Inertia useForm submit issues a POST + 302 redirect to Show. Local `File[]` accumulated
 * on Create are lost on the redirect (Inertia has no native binary transport). We serialise
 * them temporarily in `sessionStorage`, which is naturally tab-scoped (one-shot handover).
 *
 * sessionStorage stores strings only, so each File is encoded base64 + metadata (name, type)
 * in a JSON structure, then reconstructed on Show.
 *
 * The base64 encoding cost (e.g. 5 × 10 MB) is acceptable for V1. Future work could swap
 * to IndexedDB which supports native Blobs.
 */

export const PENDING_DOCUMENTS_STORAGE_KEY = 'floty:pending-contract-documents';

type PendingDocument = {
    name: string;
    type: string;
    contentBase64: string;
};

type PendingPayload = {
    contractId: number;
    documents: PendingDocument[];
};

async function fileToBase64(file: File): Promise<string> {
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]!);
    }

    return btoa(binary);
}

function base64ToFile(name: string, type: string, base64: string): File {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return new File([bytes], name, { type });
}

/**
 * Stores the files to upload after contract creation. The `contractId` is set after creation
 * (typically in Inertia useForm `onSuccess` from the redirect URL or shared prop).
 *
 * At call time the contractId is unknown (creation hasn't happened yet). Documents are stored
 * with a placeholder `contractId: 0`; the post-redirect Show page either matches the current
 * page's contract.id OR accepts contractId = 0 (legacy unassigned pending).
 *
 * V1 simplification: any payload found on Show is accepted regardless of contractId.
 * No leakage risk since sessionStorage is tab/context-scoped.
 */
export async function storePendingDocuments(files: File[]): Promise<void> {
    if (files.length === 0) {
        sessionStorage.removeItem(PENDING_DOCUMENTS_STORAGE_KEY);

        return;
    }

    const documents = await Promise.all(
        files.map(
            async (file): Promise<PendingDocument> => ({
                name: file.name,
                type: file.type,
                contentBase64: await fileToBase64(file),
            }),
        ),
    );

    const payload: PendingPayload = {
        contractId: 0, // placeholder, consumed on the Show page
        documents,
    };

    sessionStorage.setItem(PENDING_DOCUMENTS_STORAGE_KEY, JSON.stringify(payload));
}

/**
 * Reads and removes pending documents. No argument in V1 (one-shot per tab,
 * sessionStorage is tab-isolated so there is no cross-contract leak risk).
 */
export function consumePendingDocuments(): File[] {
    const raw = sessionStorage.getItem(PENDING_DOCUMENTS_STORAGE_KEY);

    if (raw === null) {
        return [];
    }

    sessionStorage.removeItem(PENDING_DOCUMENTS_STORAGE_KEY);

    try {
        const payload = JSON.parse(raw) as PendingPayload;

        return payload.documents.map((d) =>
            base64ToFile(d.name, d.type, d.contentBase64),
        );
    } catch {
        return [];
    }
}
