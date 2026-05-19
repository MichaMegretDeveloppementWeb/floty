<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Company\CompanyColor;
use App\Models\Pivot\DriverCompany;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * User company sharing the fleet.
 *
 * @property int $id
 * @property string $legal_name
 * @property string|null $siren
 * @property string|null $siret
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $postal_code
 * @property string|null $city
 * @property string $country
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string $short_code
 * @property CompanyColor $color
 * @property bool $is_active
 * @property bool $is_oig
 * @property bool $is_individual_business
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
// V1 note: `is_oig` and `is_individual_business` are deliberately excluded
// from `Fillable` because the matching R-2024-018 / R-2024-019 exemption rules
// are V1 stubs. Mass-assigning these flags would yield silently incorrect tax
// calculations; reintroduce them once the rules are fully implemented.
#[Fillable([
    'legal_name',
    'siren',
    'siret',
    'address_line_1',
    'address_line_2',
    'postal_code',
    'city',
    'country',
    'contact_name',
    'contact_email',
    'contact_phone',
    'short_code',
    'color',
    'is_active',
    'deactivated_at',
])]
final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => CompanyColor::class,
            'is_active' => 'boolean',
            'is_oig' => 'boolean',
            'is_individual_business' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Drivers attached to this company (current or historical) with join/leave dates.
     *
     * @return BelongsToMany<Driver, $this, DriverCompany, 'pivot'>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'driver_company')
            ->using(DriverCompany::class)
            ->withPivot(['id', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Rental contracts signed by this company (ADR-0014).
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Generates a three-letter uppercase short code from the legal name.
     *
     * Strategy: strip accents, keep [A-Za-z], split on words, then take
     * either the first letter of the first three words, the first letter of
     * the first word and the first two of the second, or the first three
     * letters of the single word. Pads with 'X' if the result is shorter than 3.
     *
     * Pure helper; uniqueness must be enforced by the caller (CreateCompanyAction).
     */
    public static function generateShortCode(string $legalName): string
    {
        // Strip accents: "Café Hôtelier" -> "Cafe Hotelier".
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $legalName);
        if ($ascii === false) {
            $ascii = $legalName;
        }

        // Keep letters + spaces, drop the rest, trim, collapse repeated spaces.
        $cleaned = preg_replace('/[^A-Za-z\s]/', '', $ascii) ?? '';
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        if ($cleaned === '') {
            return 'XXX';
        }

        $words = explode(' ', $cleaned);

        if (count($words) >= 3) {
            $code = mb_strtoupper($words[0][0].$words[1][0].$words[2][0]);
        } elseif (count($words) === 2) {
            $code = mb_strtoupper($words[0][0].mb_substr($words[1], 0, 2));
        } else {
            $code = mb_strtoupper(mb_substr($words[0], 0, 3));
        }

        // Right-pad to 3 chars with 'X' (e.g. short word "ON" -> "ONX").
        return str_pad($code, 3, 'X');
    }
}
