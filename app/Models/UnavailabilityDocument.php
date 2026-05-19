<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnavailabilityDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Supporting evidence (image or PDF) attached to a vehicle unavailability.
 *
 * No soft-delete: deletion hard-removes the DB row and the physical file
 * (see `DeleteUnavailabilityDocumentAction`).
 *
 * @property int $id
 * @property int $unavailability_id
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
    'unavailability_id',
    'filename',
    'storage_path',
    'size_bytes',
    'sha256',
    'mime_type',
    'uploaded_by',
])]
final class UnavailabilityDocument extends Model
{
    /** @use HasFactory<UnavailabilityDocumentFactory> */
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
     * @return BelongsTo<Unavailability, $this>
     */
    public function unavailability(): BelongsTo
    {
        return $this->belongsTo(Unavailability::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
