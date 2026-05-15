<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnavailabilityDocument>
 */
final class UnavailabilityDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $filename = fake()->slug(3).'.pdf';

        return [
            'unavailability_id' => Unavailability::factory(),
            'filename' => $filename,
            'storage_path' => "unavailability-documents/{$uuid}.pdf",
            'size_bytes' => fake()->numberBetween(50_000, 5_000_000),
            'sha256' => str_repeat('a', 64),
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::factory(),
        ];
    }

    public function forUnavailability(Unavailability $unavailability): static
    {
        return $this->state(fn (): array => ['unavailability_id' => $unavailability->id]);
    }
}
