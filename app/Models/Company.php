<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Company\CompanyColor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Entreprise utilisatrice de la flotte partagée.
 *
 * Cf. 01-schema-metier.md § 4.
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
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
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
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => CompanyColor::class,
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Conducteurs rattachés à cette entreprise.
     *
     * @return HasMany<Driver, $this>
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /**
     * Attributions de véhicules à cette entreprise.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Déclarations fiscales annuelles de cette entreprise.
     *
     * @return HasMany<Declaration, $this>
     */
    public function declarations(): HasMany
    {
        return $this->hasMany(Declaration::class);
    }
}
