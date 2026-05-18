<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Facture mensuelle d'une entreprise utilisatrice (Phase 14.E V1.2).
 *
 * **Doctrine immuabilité** : une facture émise est figée. Les colonnes
 * `total_ht_cents`, `pdf_path`, `pdf_hash` capturent l'état au moment
 * de l'émission ; toute modification ultérieure des contrats / tarifs
 * ne propage **pas** sur la facture (responsabilité utilisateur de
 * regénérer si besoin · non automatique en V1.2, cf. mémoire
 * `roadmap_v12_facturation`).
 *
 * @property int $id
 * @property int $company_id
 * @property int $year
 * @property int $month
 * @property string $invoice_number
 * @property int $total_ht_cents
 * @property int $total_gross_cents
 * @property int $total_discount_cents
 * @property bool $is_divergent
 * @property int|null $superseded_by_id
 * @property Carbon|null $obsolete_at
 * @property string $pdf_path
 * @property string $pdf_hash
 * @property Carbon $generated_at
 * @property int $generated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company $company
 * @property-read User $generatedBy
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Invoice|null $supersededBy
 */
#[Fillable([
    'company_id',
    'year',
    'month',
    'invoice_number',
    'total_ht_cents',
    'total_gross_cents',
    'total_discount_cents',
    'is_divergent',
    'superseded_by_id',
    'obsolete_at',
    'pdf_path',
    'pdf_hash',
    'generated_at',
    'generated_by_user_id',
])]
final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'total_ht_cents' => 'integer',
            'total_gross_cents' => 'integer',
            'total_discount_cents' => 'integer',
            'is_divergent' => 'boolean',
            'generated_at' => 'datetime',
            'obsolete_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Facture qui remplace celle-ci (NULL pour la dernière version
     * active). Permet de tracer la chaîne « v1 → v2 → v3 » suite à
     * une ou plusieurs régénérations.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'superseded_by_id');
    }
}
