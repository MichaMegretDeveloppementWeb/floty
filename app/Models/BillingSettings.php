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
     * Singleton applicatif · retourne l'unique ligne (création automatique
     * si la table est vide). Tous les caller du domaine Invoice passent
     * par cette méthode pour lire les paramètres émetteur.
     *
     * Implémenté via `firstOrCreate(['id' => 1], [])` (Lot 6 D5 · F-31-015)
     * pour fermer la fenêtre de race condition entre deux requêtes HTTP
     * concurrentes au démarrage à froid (table vide) · le `id = 1` force
     * MySQL à rejeter la 2ᵉ insertion par PK violation, Laravel récupère
     * atomiquement la ligne créée par la 1ʳᵉ. Pas de doublon possible.
     *
     * L'ancienne version `firstOrCreate([], [])` (T4 / 14.P) puis
     * `first()` + `new save()` (origine) étaient vulnérables.
     */
    public static function singleton(): self
    {
        return self::unguarded(fn (): self => self::query()->firstOrCreate(['id' => 1], []));
    }
}
