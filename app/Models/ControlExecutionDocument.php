<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ControlExecutionDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Supporting evidence (image or PDF) attached to a control execution
 * (Chantier B / B2). Mirrors {@see VehicleEventDocument}: no soft-delete,
 * deletion hard-removes the DB row and the physical file.
 *
 * @property int $id
 * @property int $control_execution_id
 * @property string $filename
 * @property string $storage_path
 * @property int $size_bytes
 * @property string $sha256
 * @property string $mime_type
 * @property int $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'control_execution_id',
    'filename',
    'storage_path',
    'size_bytes',
    'sha256',
    'mime_type',
    'uploaded_by',
])]
final class ControlExecutionDocument extends Model
{
    /** @use HasFactory<ControlExecutionDocumentFactory> */
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
     * @return BelongsTo<ControlExecution, $this>
     */
    public function controlExecution(): BelongsTo
    {
        return $this->belongsTo(ControlExecution::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
