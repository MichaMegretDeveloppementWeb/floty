<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Data\Auth\ChangePasswordData;
use App\Data\Auth\ForgotPasswordData;
use App\Data\Auth\ResetPasswordData;
use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Company\UpdateCompanyData;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Data\User\Control\ControlDefinitionFormData;
use App\Data\User\Control\ControlRecipientData;
use App\Data\User\Control\ControlReminderSettingsData;
use App\Data\User\Control\Vehicle\RecordControlExecutionData;
use App\Data\User\Control\Vehicle\SetVehicleControlStatusData;
use App\Data\User\Control\Vehicle\VehicleControlOverrideFormData;
use App\Data\User\Driver\AddDriverCompanyMembershipData;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Data\User\Driver\StoreDriverData;
use App\Data\User\Driver\UpdateDriverCompanyMembershipData;
use App\Data\User\Driver\UpdateDriverData;
use App\Data\User\FiscalDeclaration\PrepareDeclarationData;
use App\Data\User\Invoice\BulkGenerateInvoicesRequestData;
use App\Data\User\Invoice\GenerateInvoiceRequestData;
use App\Data\User\Planning\PreviewRentalsInputData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekQueryData;
use App\Data\User\RentalDiscount\StoreRentalDiscountData;
use App\Data\User\RentalDiscount\UpdateRentalDiscountData;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Data\User\Vehicle\StoreFiscalCharacteristicsData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Floty has no base lang/fr/validation.php on purpose: every user-facing
 * validation message is hand-written per field in each DTO's messages().
 *
 * This guard validates every form DTO with a sweep of invalid payloads and
 * asserts the only raw "validation.*" keys that can still surface are the
 * STRUCTURAL type guards (string / boolean / array container) that the real
 * UI never triggers (it always sends the correct primitive type). Any other
 * leaked rule (required, numeric, integer, date, max, min, between, enum,
 * exists, date_format, file, size, ...) means a missing bespoke message.
 *
 * It also flags untranslated English fallbacks: the Enum rule (and a few
 * others) ship a hardcoded English default rather than a raw key, so a missing
 * French message leaks English instead of "validation.*".
 */
final class FrenchValidationMessagesTest extends TestCase
{
    /**
     * Rules the UI cannot trigger (the frontend always sends the right type),
     * deliberately left without a bespoke message, matching the existing
     * per-DTO convention.
     *
     * @var list<string>
     */
    private const STRUCTURAL_RULES = ['string', 'boolean', 'array', 'list'];

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function formDtoProvider(): iterable
    {
        yield 'StoreVehicleData' => [StoreVehicleData::class];
        yield 'UpdateVehicleData' => [UpdateVehicleData::class];
        yield 'StoreFiscalCharacteristicsData' => [StoreFiscalCharacteristicsData::class];
        yield 'UpdateFiscalCharacteristicsData' => [UpdateFiscalCharacteristicsData::class];
        yield 'ExitVehicleData' => [ExitVehicleData::class];
        yield 'StoreContractData' => [StoreContractData::class];
        yield 'UpdateContractData' => [UpdateContractData::class];
        yield 'BulkStoreContractsData' => [BulkStoreContractsData::class];
        yield 'StoreCompanyData' => [StoreCompanyData::class];
        yield 'UpdateCompanyData' => [UpdateCompanyData::class];
        yield 'StoreDriverData' => [StoreDriverData::class];
        yield 'UpdateDriverData' => [UpdateDriverData::class];
        yield 'AddDriverCompanyMembershipData' => [AddDriverCompanyMembershipData::class];
        yield 'UpdateDriverCompanyMembershipData' => [UpdateDriverCompanyMembershipData::class];
        yield 'LeaveDriverCompanyMembershipData' => [LeaveDriverCompanyMembershipData::class];
        yield 'StoreRentalDiscountData' => [StoreRentalDiscountData::class];
        yield 'UpdateRentalDiscountData' => [UpdateRentalDiscountData::class];
        yield 'StoreVehicleEventData' => [StoreVehicleEventData::class];
        yield 'UpdateVehicleEventData' => [UpdateVehicleEventData::class];
        yield 'PrepareDeclarationData' => [PrepareDeclarationData::class];
        yield 'PreviewRentalsInputData' => [PreviewRentalsInputData::class];
        yield 'PreviewTaxesInputData' => [PreviewTaxesInputData::class];
        yield 'WeekQueryData' => [WeekQueryData::class];
        yield 'GenerateInvoiceRequestData' => [GenerateInvoiceRequestData::class];
        yield 'BulkGenerateInvoicesRequestData' => [BulkGenerateInvoicesRequestData::class];
        yield 'ChangePasswordData' => [ChangePasswordData::class];
        yield 'ForgotPasswordData' => [ForgotPasswordData::class];
        yield 'ResetPasswordData' => [ResetPasswordData::class];
        yield 'ControlDefinitionFormData' => [ControlDefinitionFormData::class];
        yield 'VehicleControlOverrideFormData' => [VehicleControlOverrideFormData::class];
        yield 'RecordControlExecutionData' => [RecordControlExecutionData::class];
        yield 'SetVehicleControlStatusData' => [SetVehicleControlStatusData::class];
        yield 'ControlReminderSettingsData' => [ControlReminderSettingsData::class];
        yield 'ControlRecipientData' => [ControlRecipientData::class];
    }

    /**
     * @param  class-string  $dataClass
     */
    #[Test]
    #[DataProvider('formDtoProvider')]
    public function every_user_facing_rule_has_a_bespoke_french_message(string $dataClass): void
    {
        $fields = $this->fieldNames($dataClass);

        $payloads = [
            [],
            array_fill_keys($fields, '###'),
            array_fill_keys($fields, str_repeat('x', 6000)),
            array_fill_keys($fields, '-5'),
            array_fill_keys($fields, '999999999'),
            array_fill_keys($fields, ['x']),
        ];

        $leaked = [];

        foreach ($payloads as $payload) {
            try {
                $dataClass::validate($payload);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        // Untranslated framework fallback. Some rules (notably the
                        // Enum rule) carry a hardcoded English default
                        // ("The selected :attribute is invalid.") instead of a raw
                        // key, so a missing French message leaks English, not
                        // "validation.*". No bespoke French message starts with "The ".
                        if (str_starts_with($message, 'The ')) {
                            $leaked[$field.' => '.$message] = true;

                            continue;
                        }

                        if (! str_starts_with($message, 'validation.')) {
                            continue;
                        }

                        $rule = explode('.', substr($message, strlen('validation.')))[0];

                        if (! in_array($rule, self::STRUCTURAL_RULES, true)) {
                            $leaked[$field.' => '.$message] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                // Extreme probes may trip a custom rule; only validation
                // messages are under test here.
            }
        }

        $this->assertSame(
            [],
            array_keys($leaked),
            "Règles sans message sur-mesure dans {$dataClass} : ".implode(', ', array_keys($leaked)),
        );
    }

    #[Test]
    public function bespoke_messages_read_naturally_in_french(): void
    {
        // required → field-specific wording, no ":attribute" code name.
        try {
            StoreVehicleData::validate([]);
            $this->fail('La validation aurait dû échouer.');
        } catch (ValidationException $e) {
            $this->assertSame('Le nombre de places est obligatoire.', $e->errors()['seats_count'][0] ?? null);
            $this->assertSame('La marque est obligatoire.', $e->errors()['brand'][0] ?? null);
        }

        // numeric → no raw "validation.numeric" (the original bug report).
        try {
            StoreVehicleData::validate(['seats_count' => 'abc']);
            $this->fail('La validation aurait dû échouer.');
        } catch (ValidationException $e) {
            $this->assertContains('Le nombre de places doit être un nombre.', $e->errors()['seats_count'] ?? []);
        }
    }

    /**
     * Distinct top-level field names a DTO validates, expanded with a present
     * payload so rules attached only to present (nullable) fields surface too.
     *
     * @param  class-string  $dataClass
     * @return list<string>
     */
    private function fieldNames(string $dataClass): array
    {
        $names = [];

        foreach (array_keys($dataClass::getValidationRules([])) as $field) {
            $names[explode('.', $field)[0]] = true;
        }

        $seed = array_fill_keys(array_keys($names), '###');

        foreach (array_keys($dataClass::getValidationRules($seed)) as $field) {
            $names[explode('.', $field)[0]] = true;
        }

        return array_keys($names);
    }
}
