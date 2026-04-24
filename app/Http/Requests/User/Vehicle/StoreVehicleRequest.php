<?php

namespace App\Http\Requests\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'license_plate' => [
                'required',
                'string',
                'max:20',
                Rule::unique('vehicles', 'license_plate_active'),
            ],
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:120'],
            'vin' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:30'],

            'first_french_registration_date' => ['required', 'date'],
            'first_origin_registration_date' => ['required', 'date', 'before_or_equal:first_french_registration_date'],
            'first_economic_use_date' => ['required', 'date'],
            'acquisition_date' => ['required', 'date'],

            'mileage_current' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Caractéristiques fiscales initiales (créent la 1re ligne de
            // vehicle_fiscal_characteristics, `effective_from` = acquisition).
            'reception_category' => ['required', new Enum(ReceptionCategory::class)],
            'vehicle_user_type' => ['required', new Enum(VehicleUserType::class)],
            'body_type' => ['required', new Enum(BodyType::class)],
            'seats_count' => ['required', 'integer', 'min:1', 'max:20'],
            'energy_source' => ['required', new Enum(EnergySource::class)],
            'euro_standard' => ['nullable', new Enum(EuroStandard::class)],
            'pollutant_category' => ['required', new Enum(PollutantCategory::class)],
            'homologation_method' => ['required', new Enum(HomologationMethod::class)],
            'co2_wltp' => [
                'nullable',
                'integer',
                'min:0',
                'max:999',
                Rule::requiredIf(fn (): bool => $this->input('homologation_method') === HomologationMethod::Wltp->value),
            ],
            'co2_nedc' => [
                'nullable',
                'integer',
                'min:0',
                'max:999',
                Rule::requiredIf(fn (): bool => $this->input('homologation_method') === HomologationMethod::Nedc->value),
            ],
            'taxable_horsepower' => [
                'nullable',
                'integer',
                'min:1',
                'max:99',
                Rule::requiredIf(fn (): bool => $this->input('homologation_method') === HomologationMethod::Pa->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'license_plate.unique' => 'Une autre immatriculation active est déjà enregistrée.',
            'first_origin_registration_date.before_or_equal' => "La date d'origine doit être antérieure ou égale à la date française.",
            'co2_wltp.required' => 'Le CO₂ WLTP est obligatoire quand la méthode d\'homologation est WLTP.',
            'co2_nedc.required' => 'Le CO₂ NEDC est obligatoire quand la méthode d\'homologation est NEDC.',
            'taxable_horsepower.required' => 'La puissance administrative est obligatoire quand la méthode d\'homologation est PA.',
        ];
    }
}
