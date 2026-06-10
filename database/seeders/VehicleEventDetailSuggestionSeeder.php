<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VehicleEventDetailSuggestion;
use Illuminate\Database\Seeder;

/**
 * Liste de départ des suggestions de la section « Détails » des événements
 * (interventions courantes d'entretien, freinage, pneumatiques, contrôle,
 * carrosserie). `updateOrCreate` sur le label : un re-seed (prod compris) ne
 * crée aucun doublon et préserve les entrées ajoutées par l'utilisateur,
 * comme pour les natures.
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
