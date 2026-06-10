<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VehicleEventNatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One suggested event nature (refonte type → nature). Natures attached to an
 * event live in {@see VehicleEventCategory}; this catalogue only feeds the
 * form autosuggestion and defines which labels are fiscally reductive.
 *
 * `is_fiscally_reductive` rows form a frozen block managed exclusively by
 * VehicleEventNatureSeeder; rows added at runtime (« Ajouter à la liste »)
 * are always non-reductive.
 *
 * @property int $id
 * @property string $label
 * @property bool $is_fiscally_reductive
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'label',
    'is_fiscally_reductive',
])]
final class VehicleEventNature extends Model
{
    /** @use HasFactory<VehicleEventNatureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_fiscally_reductive' => 'boolean',
        ];
    }
}
