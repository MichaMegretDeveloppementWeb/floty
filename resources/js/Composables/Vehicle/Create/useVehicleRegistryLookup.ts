/**
 * Composable de pré-remplissage du formulaire véhicule depuis la
 * plaque d'immatriculation (cf. Strategy pattern côté backend dans
 * `app/Strategies/VehicleRegistryLookup`).
 *
 * Côté UX :
 *   - Le bouton « Pré-remplir depuis la carte grise » n'est rendu que
 *     si `vehicleRegistryLookupEnabled` est `true` dans les props
 *     Inertia partagées (cf. `HandleInertiaRequests`).
 *   - `lookup()` appelle l'endpoint POST et hydrate les 15 champs
 *     récupérables côté form + ajuste les 5 champs déduits côté
 *     serveur (pollutant_category live + vehicle_user_type) ou côté
 *     composable (homologation_method dérivable de la date, déjà
 *     pré-renseignée si fournie par l'API).
 *   - Les erreurs réseau/serveur sont déjà toastées par `useApi()` ·
 *     le composable expose juste un état `loading` + `lastError` pour
 *     que l'UI ajuste son rendu (badge orange, lien « réessayer »,
 *     etc.).
 *
 * Le composable ne touche PAS aux 6 cases à cocher manuelles
 * (`accepts_e85`, `handicap_access`, `m1_special_use`, `n1_*`).
 * L'utilisateur les remplit dans l'encart « À vérifier manuellement »
 * du formulaire.
 */

import type { InertiaForm } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import { registryLookup as registryLookupRoute } from '@/routes/user/vehicles';

type VehicleRegistryLookupResult =
    App.Data.User.Vehicle.VehicleRegistryLookupResultData;

export type RegistryLookupState = {
    loading: Ref<boolean>;
    lastFetchedAt: Ref<string | null>;
    lastError: Ref<string | null>;
    lookup: (licensePlate: string) => Promise<void>;
};

/**
 * Crée un binding entre l'endpoint de lookup et le formulaire Inertia
 * de création/édition véhicule. Le composable est volontairement
 * minimal · ossature en place, le polish UX (badges « auto »
 * par champ, encart « à vérifier manuellement », handling d'erreurs
 * granulaire) sera ajouté quand un provider réel sera implémenté.
 */
export function useVehicleRegistryLookup(
    form: InertiaForm<VehicleFormShape>,
): RegistryLookupState {
    const api = useApi();
    const loading = ref<boolean>(false);
    const lastFetchedAt = ref<string | null>(null);
    const lastError = ref<string | null>(null);

    async function lookup(licensePlate: string): Promise<void> {
        if (loading.value) {
            return;
        }

        loading.value = true;
        lastError.value = null;

        try {
            const result = await api.post<VehicleRegistryLookupResult>(
                registryLookupRoute.url(),
                { license_plate: licensePlate },
            );

            applyResultToForm(form, result);
            lastFetchedAt.value = result.fetchedAt;
        } catch (error) {
            lastError.value =
                error instanceof Error
                    ? error.message
                    : 'lookup failed';
        } finally {
            loading.value = false;
        }
    }

    return { loading, lastFetchedAt, lastError, lookup };
}

/**
 * Hydrate les champs du formulaire à partir du résultat API.
 *
 * - `license_plate` est ré-aligné sur la version normalisée renvoyée
 *   par le backend (uppercase, sans tirets) pour cohérence visuelle.
 * - Les enums sont assignés directement (le type est garanti par le
 *   DTO #[TypeScript]).
 * - `first_economic_use_date` n'est PAS hydraté ici · valeur Floty
 *   interne, pré-initialisée par le formulaire à partir de
 *   `acquisition_date` (saisie utilisateur).
 * - Les 6 flags manuels (E85, handicap, M1/N1) ne sont pas touchés.
 */
function applyResultToForm(
    form: InertiaForm<VehicleFormShape>,
    result: VehicleRegistryLookupResult,
): void {
    form.license_plate = result.licensePlate;

    if (result.brand !== null) {
        form.brand = result.brand;
    }
    if (result.model !== null) {
        form.model = result.model;
    }
    if (result.vin !== null) {
        form.vin = result.vin;
    }
    if (result.color !== null) {
        form.color = result.color;
    }
    if (result.firstFrenchRegistrationDate !== null) {
        form.first_french_registration_date =
            result.firstFrenchRegistrationDate;
    }
    if (result.firstOriginRegistrationDate !== null) {
        form.first_origin_registration_date =
            result.firstOriginRegistrationDate;
    }
    if (result.receptionCategory !== null) {
        form.reception_category = result.receptionCategory;
        form.vehicle_user_type =
            result.receptionCategory === 'M1' ? 'VP' : 'VU';
    }
    if (result.bodyType !== null) {
        form.body_type = result.bodyType;
    }
    if (result.seatsCount !== null) {
        form.seats_count = result.seatsCount;
    }
    if (result.energySource !== null) {
        form.energy_source = result.energySource;
    }
    if (result.underlyingCombustionEngineType !== null) {
        form.underlying_combustion_engine_type =
            result.underlyingCombustionEngineType;
    }
    if (result.euroStandard !== null) {
        form.euro_standard = result.euroStandard;
    }
    if (result.homologationMethod !== null) {
        form.homologation_method = result.homologationMethod;
    }
    if (result.co2Wltp !== null) {
        form.co2_wltp = result.co2Wltp;
    }
    if (result.co2Nedc !== null) {
        form.co2_nedc = result.co2Nedc;
    }
    if (result.taxableHorsepower !== null) {
        form.taxable_horsepower = result.taxableHorsepower;
    }
    if (result.kerbMass !== null) {
        form.kerb_mass = result.kerbMass;
    }
}
