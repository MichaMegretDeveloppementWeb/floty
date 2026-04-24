<?php

namespace App\Models;

use App\Actions\User\Declaration\GenerateDeclarationPdfAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PDF généré pour une déclaration — **immutable** (ADR-0003).
 *
 * Cf. 02-schema-fiscal.md § 3.
 *
 * Chaque ligne correspond à un fichier PDF sur le filesystem Laravel
 * (chemin relatif dans `pdf_path`). Le `snapshot_json` capture l'ensemble
 * des données utilisées pour produire ce PDF — son hash SHA-256 sert à
 * détecter l'invalidation (ADR-0004).
 *
 * **Invariants** :
 *   - Pas de `updated_at`, pas de `deleted_at` — immuabilité stricte.
 *   - Pas d'`$touches` ni d'événements de modification.
 *   - `version_number` calculé applicativement dans une transaction
 *     (cf. {@see GenerateDeclarationPdfAction}
 *     prévue phase 12).
 *
 * @property int $id
 * @property int $declaration_id
 * @property string $pdf_path
 * @property string $pdf_filename
 * @property int $pdf_size_bytes
 * @property string $pdf_sha256
 * @property array<string, mixed> $snapshot_json
 * @property string $snapshot_sha256
 * @property Carbon $generated_at
 * @property int|null $generated_by
 * @property int $version_number
 * @property Carbon $created_at
 */
#[Fillable([
    'declaration_id',
    'pdf_path',
    'pdf_filename',
    'pdf_size_bytes',
    'pdf_sha256',
    'snapshot_json',
    'snapshot_sha256',
    'generated_at',
    'generated_by',
    'version_number',
])]
class DeclarationPdf extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Declaration, $this>
     */
    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
