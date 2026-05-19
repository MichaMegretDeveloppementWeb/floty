<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issuer (lessor) metadata printed on invoices. Application singleton (always `id=1`).
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $siren
 * @property string|null $contact_email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'address_line_1',
    'address_line_2',
    'postal_code',
    'city',
    'siren',
    'contact_email',
])]
final class BillingSettings extends Model
{
    protected $table = 'billing_settings';

    /**
     * Returns the application singleton row, auto-creating it on first access.
     *
     * Uses `firstOrCreate(['id' => 1], [])` so a concurrent second insertion is
     * rejected by MySQL with a PK violation; the loser atomically retrieves the
     * winner's row, leaving no duplicates.
     */
    public static function singleton(): self
    {
        return self::unguarded(fn (): self => self::query()->firstOrCreate(['id' => 1], []));
    }
}
