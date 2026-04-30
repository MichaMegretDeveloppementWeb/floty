<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Document PDF joint à un contrat (chantier 04.N).
 *
 * Pas de soft-delete : la suppression côté UI fait un hard-delete DB
 * + fichier physique (cf. `DeleteContractDocumentAction`).
 *
 * @property int $id
 * @property int $contract_id
 * @property string $filename Nom original du fichier uploadé
 * @property string $storage_path Chemin relatif sur le disk
 * @property int $size_bytes
 * @property string $sha256
 * @property string $mime_type
 * @property int $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'contract_id',
    'filename',
    'storage_path',
    'size_bytes',
    'sha256',
    'mime_type',
    'uploaded_by',
])]
final class ContractDocument extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
