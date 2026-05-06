<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'une facture mensuelle — un véhicule sur le mois (Phase 14.E
 * V1.2).
 *
 * Snapshot complet à l'émission : `vehicle_label_snapshot` archive
 * l'identité du véhicule (plaque + marque + modèle) au moment de la
 * facture pour rester valide même si le véhicule est ensuite renommé
 * ou retiré de flotte.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $vehicle_id
 * @property string $vehicle_label_snapshot
 * @property int $days_used
 * @property int $months_billed
 * @property int $weeks_billed
 * @property int $days_billed
 * @property int $daily_rate_cents
 * @property int $weekly_rate_cents
 * @property int $monthly_rate_cents
 * @property int $total_ht_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Vehicle $vehicle
 */
#[Fillable([
    'invoice_id',
    'vehicle_id',
    'vehicle_label_snapshot',
    'days_used',
    'months_billed',
    'weeks_billed',
    'days_billed',
    'daily_rate_cents',
    'weekly_rate_cents',
    'monthly_rate_cents',
    'total_ht_cents',
])]
final class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_used' => 'integer',
            'months_billed' => 'integer',
            'weeks_billed' => 'integer',
            'days_billed' => 'integer',
            'daily_rate_cents' => 'integer',
            'weekly_rate_cents' => 'integer',
            'monthly_rate_cents' => 'integer',
            'total_ht_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
