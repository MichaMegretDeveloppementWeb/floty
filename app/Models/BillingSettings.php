<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Métadonnées émetteur (loueur) imprimées sur les factures
 * (Phase 14.G V1.2). Singleton applicatif : `id=1` toujours.
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
     * Singleton applicatif : retourne l'unique ligne (création
     * automatique si la table est vide). Tous les caller du domaine
     * Invoice passent par cette méthode pour lire les paramètres
     * émetteur.
     */
    public static function singleton(): self
    {
        $instance = self::query()->first();

        if ($instance instanceof self) {
            return $instance;
        }

        $created = new self;
        $created->save();

        return $created;
    }
}
