<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VehicleEventDetailSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One autocomplete suggestion for the event « Détails » lines. Entirely
 * user-managed (« Ajouter à la liste » / retrait), no frozen base: deleting
 * a suggestion never touches the detail lines already attached to events.
 *
 * @property int $id
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'label',
])]
final class VehicleEventDetailSuggestion extends Model
{
    /** @use HasFactory<VehicleEventDetailSuggestionFactory> */
    use HasFactory;

    protected $table = 'vehicle_event_detail_suggestions';
}
