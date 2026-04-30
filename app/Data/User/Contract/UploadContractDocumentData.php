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
 * Payload d'upload d'un document PDF (chantier 04.N).
 *
 * Validation V1 :
 *   - Required + File : un fichier doit être présent
 *   - Mimes pdf : strictement PDF (validation MIME serveur)
 *   - Max 10240 KB (= 10 Mo)
 *
 * La limite des 5 documents par contrat est vérifiée dans
 * {@see UploadContractDocumentAction} (lève
 * `TooManyContractDocumentsException` avec message FR).
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
