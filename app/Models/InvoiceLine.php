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
 * Monthly invoice line covering a single vehicle.
 *
 * Full snapshot at emission time: `vehicle_label_snapshot` archives the
 * vehicle identity (plate + brand + model) so the line stays valid even if
 * the vehicle is later renamed or removed from the fleet.
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
 * @property int $gross_total_cents
 * @property int $discount_cents
 * @property int|null $applied_discount_id
 * @property string|null $applied_discount_label_snapshot
 * @property int|null $applied_discount_basis_points_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Vehicle $vehicle
 * @property-read RentalDiscount|null $appliedDiscount
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
    'gross_total_cents',
    'discount_cents',
    'applied_discount_id',
    'applied_discount_label_snapshot',
    'applied_discount_basis_points_snapshot',
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
            'gross_total_cents' => 'integer',
            'discount_cents' => 'integer',
            'applied_discount_basis_points_snapshot' => 'integer',
        ];
    }

    /**
     * Parent invoice.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Billed vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Rental discount applied to this line. Label and basis points are read
     * from the `*_snapshot` columns for immutability; this relation only
     * exists to build a "Show" link while the discount still exists.
     *
     * @return BelongsTo<RentalDiscount, $this>
     */
    public function appliedDiscount(): BelongsTo
    {
        return $this->belongsTo(RentalDiscount::class, 'applied_discount_id');
    }
}
