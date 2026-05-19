<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use App\Data\User\FiscalRiskSettings\FiscalRiskSettingsData;
use App\Models\FiscalRiskSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FiscalRiskSettingsDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function from_model_mappe_les_champs_correctement(): void
    {
        $settings = FiscalRiskSettings::singleton();
        $settings->fill([
            'max_interval' => 7,
            'threshold_low' => 20,
            'threshold_high' => 60,
            'count_high' => 3,
        ])->save();

        $dto = FiscalRiskSettingsData::fromModel($settings->fresh());

        self::assertSame(7, $dto->maxInterval);
        self::assertSame(20, $dto->thresholdLow);
        self::assertSame(60, $dto->thresholdHigh);
        self::assertSame(3, $dto->countHigh);
    }

    #[Test]
    public function validation_rejette_max_interval_zero(): void
    {
        $rules = FiscalRiskSettingsData::getValidationRules([]);
        $validator = Validator::make([
            'max_interval' => 0,
            'threshold_low' => 30,
            'threshold_high' => 90,
            'count_high' => 5,
        ], $rules);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('max_interval', $validator->errors()->toArray());
    }

    #[Test]
    public function validation_rejette_threshold_high_inferieur_ou_egal_au_low(): void
    {
        $rules = FiscalRiskSettingsData::getValidationRules([]);
        $validator = Validator::make([
            'max_interval' => 15,
            'threshold_low' => 50,
            'threshold_high' => 30,
            'count_high' => 5,
        ], $rules);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('threshold_high', $validator->errors()->toArray());
    }

    #[Test]
    public function validation_accepte_payload_valide(): void
    {
        $rules = FiscalRiskSettingsData::getValidationRules([]);
        $validator = Validator::make([
            'max_interval' => 15,
            'threshold_low' => 30,
            'threshold_high' => 90,
            'count_high' => 5,
        ], $rules);

        self::assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function validation_rejette_threshold_low_negatif(): void
    {
        $rules = FiscalRiskSettingsData::getValidationRules([]);
        $validator = Validator::make([
            'max_interval' => 15,
            'threshold_low' => -1,
            'threshold_high' => 90,
            'count_high' => 5,
        ], $rules);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('threshold_low', $validator->errors()->toArray());
    }
}
