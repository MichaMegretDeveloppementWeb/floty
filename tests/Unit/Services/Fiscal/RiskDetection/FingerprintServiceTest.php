<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\RiskDetection;

use App\Models\Contract;
use App\Services\Fiscal\RiskDetection\FingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le calcul du fingerprint déterministe d'un cluster
 * (Phase 11 D2, ADR-0015 § D5).
 *
 * Le fingerprint est la pierre angulaire de la persistance des
 * décisions à travers les régénérations : il doit être strictement
 * déterministe et invariant à l'ordre des contrats.
 */
final class FingerprintServiceTest extends TestCase
{
    use RefreshDatabase;

    private FingerprintService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FingerprintService;
    }

    #[Test]
    public function produit_un_hex_sha256_de_64_caracteres(): void
    {
        $cluster = [
            $this->makeContract('2025-01-01', '2025-01-15'),
            $this->makeContract('2025-02-01', '2025-02-10'),
        ];

        $fp = $this->service->compute($cluster);

        self::assertSame(64, strlen($fp));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fp);
    }

    #[Test]
    public function est_deterministe_pour_le_meme_cluster(): void
    {
        $cluster = [
            $this->makeContract('2025-01-01', '2025-01-15'),
            $this->makeContract('2025-02-01', '2025-02-10'),
        ];

        $fp1 = $this->service->compute($cluster);
        $fp2 = $this->service->compute($cluster);

        self::assertSame($fp1, $fp2);
    }

    #[Test]
    public function est_invariant_a_l_ordre_des_contrats(): void
    {
        $c1 = $this->makeContract('2025-01-01', '2025-01-15');
        $c2 = $this->makeContract('2025-02-01', '2025-02-10');

        $fp1 = $this->service->compute([$c1, $c2]);
        $fp2 = $this->service->compute([$c2, $c1]);

        self::assertSame($fp1, $fp2);
    }

    #[Test]
    public function change_si_un_contrat_id_change(): void
    {
        $c1 = $this->makeContract('2025-01-01', '2025-01-15');
        $c2 = $this->makeContract('2025-02-01', '2025-02-10');
        $c3 = $this->makeContract('2025-03-01', '2025-03-15');

        $fpA = $this->service->compute([$c1, $c2]);
        $fpB = $this->service->compute([$c1, $c3]);

        self::assertNotSame($fpA, $fpB);
    }

    #[Test]
    public function change_si_un_start_date_change(): void
    {
        $original = $this->makeContract('2025-01-01', '2025-01-15');
        $modified = clone $original;
        $modified->start_date = $modified->start_date->copy()->subDay();

        $fpA = $this->service->compute([$original]);
        $fpB = $this->service->compute([$modified]);

        self::assertNotSame($fpA, $fpB);
    }

    #[Test]
    public function change_si_un_end_date_change(): void
    {
        $original = $this->makeContract('2025-01-01', '2025-01-15');
        $modified = clone $original;
        $modified->end_date = $modified->end_date->copy()->addDay();

        $fpA = $this->service->compute([$original]);
        $fpB = $this->service->compute([$modified]);

        self::assertNotSame($fpA, $fpB);
    }

    #[Test]
    public function change_si_un_vehicle_id_change(): void
    {
        $original = $this->makeContract('2025-01-01', '2025-01-15');
        $modified = clone $original;
        $modified->vehicle_id = $modified->vehicle_id + 999;

        $fpA = $this->service->compute([$original]);
        $fpB = $this->service->compute([$modified]);

        self::assertNotSame($fpA, $fpB);
    }

    #[Test]
    public function differe_pour_des_clusters_de_taille_differente(): void
    {
        $c1 = $this->makeContract('2025-01-01', '2025-01-15');
        $c2 = $this->makeContract('2025-02-01', '2025-02-10');

        $fpA = $this->service->compute([$c1]);
        $fpB = $this->service->compute([$c1, $c2]);

        self::assertNotSame($fpA, $fpB);
    }

    #[Test]
    public function lot5_d10_leve_json_exception_sur_payload_invalide(): void
    {
        // Lot 5 D10 (F-19-019) · garde-fou · le `JSON_THROW_ON_ERROR`
        // doit propager toute corruption d'encodage JSON (ex. payload
        // contenant un float non-finite NAN/INF). Sans ce flag, json_encode
        // retournerait `false` silencieusement et le hash serait dérivé
        // d'une chaîne vide · fingerprint corrompu non détecté.
        //
        // On contourne le typage iterable<Contract> en passant directement
        // un objet anonyme avec les propriétés Carbon attendues qui force
        // le serializer à exécuter le json_encode des champs.
        $service = new FingerprintService;

        // Construit un Contract « pathologique » dont `vehicle_id` est NAN
        // (non encodable en JSON). On ne peut pas réellement persister un
        // tel modèle, mais le service accepte iterable<Contract> donc on
        // s'appuie sur la duck-typing PHP via une closure qui yield un
        // objet stdClass ressemblant.
        $bad = new \stdClass;
        $bad->id = 1;
        $bad->start_date = now();
        $bad->end_date = now();
        $bad->vehicle_id = NAN;

        $this->expectException(\JsonException::class);

        // L'iterable accepte tout objet · le service va lire les
        // propriétés et passer NAN à json_encode qui lève l'exception.
        $service->compute([$bad]);
    }

    private function makeContract(string $start, string $end): Contract
    {
        return Contract::factory()->create([
            'start_date' => $start,
            'end_date' => $end,
            'contract_type' => Contract::deriveTypeFromDates($start, $end),
        ]);
    }
}
