/**
 * Handover sessionStorage entre la page Create contrat et la page Show
 * post-redirect, pour les documents PDF en attente d'upload (chantier 04.N).
 *
 * **Pourquoi** : Inertia useForm submit fait un POST + redirect 302 vers
 * Show. Les `File[]` accumulés dans le state local de Create sont perdus
 * à la redirection (pas de mécanisme natif Inertia pour transporter des
 * binaires). On les sérialise temporairement dans `sessionStorage` (par
 * essence non persistant entre onglets ou sessions, parfait pour ce
 * handover one-shot).
 *
 * **Limitation** : sessionStorage stocke des strings, pas des File.
 * On encode chaque File en base64 + métadonnées (name, type) dans une
 * structure JSON, on récupère côté Show et on reconstruit les File.
 *
 * Le coût (encoding base64 de 5 × 10 Mo) est acceptable pour V1. En V2,
 * on pourra remplacer par IndexedDB qui supporte les Blobs natifs.
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
 * Stocke les fichiers à uploader après création du contrat. Le
 * `contractId` est posé après la création (typiquement dans le callback
 * `onSuccess` d'Inertia useForm — on lit l'URL de redirection ou la
 * shared prop pour le récupérer).
 *
 * **Note** : à l'appel, on ne connaît pas encore le contractId (la
 * création n'est pas faite). On stocke les documents avec un placeholder
 * `contractId: 0` ; le post-redirect Show vérifie que le contract.id de
 * la page courante == contractId du payload OU contractId = 0 (pending
 * legacy non assigné).
 *
 * Pour simplifier V1 : on accepte tout payload sessionStorage présent
 * sur la page Show, peu importe le contractId. Pas de risque de fuite
 * car les sessionStorage sont par onglet/contexte.
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
        contractId: 0, // placeholder, consommé sur la page Show
        documents,
    };

    sessionStorage.setItem(PENDING_DOCUMENTS_STORAGE_KEY, JSON.stringify(payload));
}

/**
 * Lit + supprime les documents en attente. Pas d'argument en V1
 * (handover one-shot par onglet, le sessionStorage est isolé par
 * onglet donc pas de risque de fuite cross-contrat).
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
