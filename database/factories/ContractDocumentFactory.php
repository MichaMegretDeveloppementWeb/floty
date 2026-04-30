<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContractDocument>
 */
final class ContractDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $filename = fake()->slug(3).'.pdf';

        return [
            'contract_id' => Contract::factory(),
            'filename' => $filename,
            // Le contract_id est résolu plus tard par le factory engine ; on
            // utilise le UUID seul comme suffixe (le chemin reste unique
            // grâce au UUID, ce qui suffit pour les tests).
            'storage_path' => "contract-documents/{$uuid}.pdf",
            'size_bytes' => fake()->numberBetween(50_000, 5_000_000),
            'sha256' => str_repeat('a', 64),
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::factory(),
        ];
    }

    public function forContract(Contract $contract): static
    {
        return $this->state(fn (): array => ['contract_id' => $contract->id]);
    }
}
