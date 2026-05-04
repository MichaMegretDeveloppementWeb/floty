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
 * @property bool $is_oig
 * @property bool $is_individual_business
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
    'is_oig',
    'is_individual_business',
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
     * Conducteurs rattachés à cette entreprise (actuellement ou par le
     * passé) via la pivot `driver_company` avec dates d'entrée/sortie.
     *
     * Cf. Phase 06 V1.2 (refonte N:N).
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
     * Contrats de location signés par cette entreprise (entité pivot
     * post ADR-0014).
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
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

    /**
     * Génère un code court à 3 lettres en majuscules à partir du nom légal.
     *
     * Algo (validé chantier A) :
     *   1. Normalisation : retire les accents, garde [A-Za-z], trim espaces multiples
     *   2. Split en mots
     *   3. Si >= 3 mots : initiales des 3 premiers
     *   4. Si 2 mots : 1ère du 1er + 2 premières du 2e
     *   5. Si 1 mot : 3 premières lettres
     *   6. Padding 'X' à droite si moins de 3 lettres obtenues
     *
     * Helper pur, ne touche pas la BDD. La vérification d'unicité est de la
     * responsabilité de l'Action appelante (CreateCompanyAction).
     */
    public static function generateShortCode(string $legalName): string
    {
        // Normalise les accents : "Café Hôtelier" -> "Cafe Hotelier"
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $legalName);
        if ($ascii === false) {
            $ascii = $legalName;
        }

        // Garde lettres + espaces, supprime le reste, trim, normalise espaces multiples
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

        // Padding 'X' si moins de 3 chars (ex. mot court "ON" -> "ONX")
        return str_pad($code, 3, 'X');
    }
}
