<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout du flag `accepts_e85` sur `vehicle_fiscal_characteristics` pour
 * l'abattement E85 entré en vigueur le 01/01/2025 (CIBS art. L. 421-125
 * réformé par LF 2024 art. 97, 23°).
 *
 * Sémantique : `true` ssi la rubrique P.3 du certificat d'immatriculation
 * appartient à la liste opposable des 9 codes BOFiP `BOI-AIS-MOB-10-20-40
 * -20250604` § 160 : FE, FG, FN, FL, FH, FR, FQ, FM, FP.
 *
 * Default `false` : sémantiquement neutre pour les VFC pré-2025 (l'abattement
 * E85 n'existait pas en 2024) et sûr en cas de saisie utilisateur incomplète
 * (pas d'application abusive de l'abattement).
 *
 * Historisé via la segmentation VFC standard (ADR-0021) : un changement de
 * `accepts_e85` (recharacterization) ferme la version courante et en ouvre
 * une nouvelle.
 *
 * Référence : `project-management/recherches-fiscales/2025/cas-particuliers
 * /decisions.md` Décision 6 v0.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->boolean('accepts_e85')
                ->default(false)
                ->after('co2_nedc');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->dropColumn('accepts_e85');
        });
    }
};
