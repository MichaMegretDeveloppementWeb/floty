<?php

declare(strict_types=1);

namespace App\Data\User\Unavailability;

use App\Actions\Unavailability\UploadUnavailabilityDocumentAction;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Unavailability document upload payload.
 *
 * Validation: file required, MIME restricted to jpg/jpeg/png/webp/pdf
 * (stricter than the client extension check), max 5 MB.
 *
 * The 5-documents-per-unavailability limit is enforced by
 * {@see UploadUnavailabilityDocumentAction} which raises
 * `TooManyUnavailabilityDocumentsException`.
 */
final class UploadUnavailabilityDocumentData extends Data
{
    public function __construct(
        #[Required, File, Mimes('jpg,jpeg,png,webp,pdf'), Max(5120)]
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
            'file.mimes' => 'Format invalide · seuls les fichiers PDF, JPG, PNG et WebP sont acceptés.',
            'file.max' => 'Fichier trop volumineux · 5 Mo maximum.',
        ];
    }
}
