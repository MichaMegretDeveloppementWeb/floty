<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ControlExecution;
use App\Models\ControlExecutionDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlExecutionDocument>
 */
final class ControlExecutionDocumentFactory extends Factory
{
    protected $model = ControlExecutionDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'control_execution_id' => ControlExecution::factory(),
            'filename' => 'controle.pdf',
            'storage_path' => 'control-execution-documents/1/'.fake()->uuid().'.pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::factory(),
        ];
    }
}
