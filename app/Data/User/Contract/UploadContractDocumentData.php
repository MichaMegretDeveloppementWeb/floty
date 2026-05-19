<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Actions\Contract\UploadContractDocumentAction;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Upload payload for a contract PDF document (PDF only, max 10 MB).
 *
 * The per-contract document limit is enforced in
 * {@see UploadContractDocumentAction}.
 */
final class UploadContractDocumentData extends Data
{
    public function __construct(
        #[Required, File, Mimes('pdf'), Max(10240)]
        public UploadedFile $file,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'file.required' => 'Aucun fichier transmis.',
            'file.file' => 'Le fichier transmis est invalide.',
            'file.mimes' => 'Format invalide : seuls les fichiers PDF sont acceptés.',
            'file.max' => 'Fichier trop volumineux : 10 Mo maximum.',
        ];
    }
}
