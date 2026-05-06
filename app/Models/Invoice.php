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
use Illuminate\Support\Carbon;

/**
 * Facture mensuelle d'une entreprise utilisatrice (Phase 14.E V1.2).
 *
 * **Doctrine immuabilité** : une facture émise est figée. Les colonnes
 * `total_ht_cents`, `pdf_path`, `pdf_hash` capturent l'état au moment
 * de l'émission ; toute modification ultérieure des contrats / tarifs
 * ne propage **pas** sur la facture (responsabilité utilisateur de
 * regénérer si besoin — non automatique en V1.2, cf. mémoire
 * `roadmap_v12_facturation`).
 *
 * @property int $id
 * @property int $company_id
 * @property int $year
 * @property int $month
 * @property string $invoice_number
 * @property int $total_ht_cents
 * @property string $pdf_path
 * @property string $pdf_hash
 * @property Carbon $generated_at
 * @property int $generated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read User $generatedBy
 * @property-read Collection<int, InvoiceLine> $lines
 */
#[Fillable([
    'company_id',
    'year',
    'month',
    'invoice_number',
    'total_ht_cents',
    'pdf_path',
    'pdf_hash',
    'generated_at',
    'generated_by_user_id',
])]
final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'total_ht_cents' => 'integer',
            'generated_at' => 'datetime',
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
}
