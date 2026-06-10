<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VehicleEventDetailSuggestion;
use Illuminate\Database\Seeder;

/**
 * Starter list of event detail suggestions; updateOrCreate keeps re-seeds
 * duplicate-free and preserves user entries.
 */
final class VehicleEventDetailSuggestionSeeder extends Seeder
{
    /** @var list<string> */
    private const array SUGGESTIONS = [
        'Vidange',
        'Remplacement filtre à huile',
        'Remplacement filtre à air',
        'Remplacement filtre habitacle',
        'Contrôle des niveaux',
        'Remplacement batterie',
        'Changement courroie de distribution',
        'Remplacement plaquettes de frein',
        'Remplacement disques de frein',
        'Changement des pneus',
        'Permutation des pneus',
        'Géométrie et parallélisme',
        'Recharge climatisation',
        'Remplacement balais d\'essuie-glace',
        'Passage au contrôle technique',
        'Contre-visite',
        'Réparation carrosserie',
        'Remplacement pare-brise',
    ];

    public function run(): void
    {
        foreach (self::SUGGESTIONS as $label) {
            VehicleEventDetailSuggestion::updateOrCreate(['label' => $label]);
        }
    }
}
