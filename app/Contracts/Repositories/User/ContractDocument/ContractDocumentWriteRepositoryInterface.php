<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\ContractDocument;

use App\Models\ContractDocument;

/**
 * ContractDocument writes · slim interface per ADR-0013.
 *
 * No business decision here (5-document cap, hash, physical upload) ·
 * that is the role of the domain Actions.
 */
interface ContractDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function create(array $row): ContractDocument;

    /**
     * Hard-delete (no soft-delete on ContractDocument in V1).
     */
    public function delete(int $id): void;
}
