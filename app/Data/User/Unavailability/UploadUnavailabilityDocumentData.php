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
 * Payload d'upload d'un justificatif d'indisponibilité (P1).
 *
 * Validation V1 ·
 *   - Required + File · un fichier doit être présent
 *   - Mimes · jpg/jpeg/png/webp ou pdf (validation MIME serveur, plus
 *     stricte que l'extension côté client)
 *   - Max 5120 KB (= 5 Mo)
 *
 * La limite des 5 documents par indispo est vérifiée dans
 * {@see UploadUnavailabilityDocumentAction} (lève
 * `TooManyUnavailabilityDocumentsException` avec message FR).
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
