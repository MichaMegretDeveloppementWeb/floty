<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\ContractDocument;

use App\Actions\Contract\UploadContractDocumentAction;
use App\Models\ContractDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures ContractDocument - interface slim conforme ADR-0013.
 */
interface ContractDocumentReadRepositoryInterface
{
    public function findById(int $id): ?ContractDocument;

    /**
     * Liste des documents d'un contrat, triés du plus récent au plus
     * ancien (UX naturelle pour la section Documents).
     *
     * @return Collection<int, ContractDocument>
     */
    public function listForContract(int $contractId): Collection;

    /**
     * Compte des documents existants pour un contrat - utilisé par
     * {@see UploadContractDocumentAction} pour
     * vérifier la limite des 5 documents avant insert.
     */
    public function countForContract(int $contractId): int;
}
