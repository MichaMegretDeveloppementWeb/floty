<?php

namespace App\Http\Requests\User\Company;

use App\Enums\Company\CompanyColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCompanyRequest extends FormRequest
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
            'legal_name' => ['required', 'string', 'max:255'],
            'short_code' => [
                'required',
                'string',
                'max:5',
                Rule::unique('companies', 'short_code')->whereNull('deleted_at'),
            ],
            'color' => ['required', 'string', new Enum(CompanyColor::class)],
            'siren' => ['nullable', 'string', 'size:9', 'regex:/^\d{9}$/'],
            'siret' => ['nullable', 'string', 'size:14', 'regex:/^\d{14}$/'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'legal_name.required' => 'La raison sociale est obligatoire.',
            'short_code.required' => 'Le code court est obligatoire.',
            'short_code.unique' => 'Ce code court est déjà utilisé par une autre entreprise.',
            'color.required' => 'La couleur est obligatoire.',
            'siren.size' => 'Le SIREN doit contenir exactement 9 chiffres.',
            'siren.regex' => 'Le SIREN doit contenir uniquement des chiffres.',
            'siret.size' => 'Le SIRET doit contenir exactement 14 chiffres.',
            'siret.regex' => 'Le SIRET doit contenir uniquement des chiffres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => $this->input('country') ?: 'FR',
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
