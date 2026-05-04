<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Compte applicatif gestionnaire flotte Floty.
 *
 * Cf. 01-schema-metier.md § 1 + ADR-0012.
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $email_verified_at
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 */
#[Fillable([
    'email',
    'password',
    'first_name',
    'last_name',
    'must_change_password',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Concatène `first_name` + `last_name` pour l'affichage compact.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.$this->last_name),
        )->shouldCache();
    }

    /**
     * Déclarations dont cet utilisateur a changé le statut (audit).
     *
     * @return HasMany<Declaration, $this>
     */
    public function changedDeclarations(): HasMany
    {
        return $this->hasMany(Declaration::class, 'status_changed_by');
    }

    /**
     * PDF de déclaration générés par cet utilisateur.
     *
     * @return HasMany<DeclarationPdf, $this>
     */
    public function generatedPdfs(): HasMany
    {
        return $this->hasMany(DeclarationPdf::class, 'generated_by');
    }
}
