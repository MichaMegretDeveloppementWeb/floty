/**
 * Maps de traduction FR pour les enums du domaine Vehicle.
 *
 * Synchronisés à la main avec les méthodes `label()` côté PHP — le
 * typage `Record<EnumValue, string>` force l'exhaustivité TS, donc
 * vue-tsc échouera si on ajoute un case enum côté PHP sans mettre
 * à jour ce fichier.
 *
 * Pourquoi pas un système auto-généré : pour la phase V1, la
 * duplication contrôlée est plus pragmatique qu'un générateur
 * custom. À reconsidérer en V2 si le nombre d'enums explose.
 */

export const energySourceLabel: Record<App.Enums.Vehicle.EnergySource, string> = {
    gasoline: 'Essence',
    diesel: 'Diesel',
    electric: 'Électrique',
    hydrogen: 'Hydrogène',
    plugin_hybrid: 'Hybride rechargeable',
    non_plugin_hybrid: 'Hybride non rechargeable',
    lpg: 'GPL',
    cng: 'Gaz naturel (GNV)',
    e85: 'Superéthanol E85',
    electric_hydrogen: 'Électrique + hydrogène',
};

export const receptionCategoryLabel: Record<App.Enums.Vehicle.ReceptionCategory, string> = {
    M1: 'M1 - Voiture particulière',
    N1: 'N1 - Camionnette',
};

export const vehicleUserTypeLabel: Record<App.Enums.Vehicle.VehicleUserType, string> = {
    VP: 'Voiture particulière',
    VU: 'Véhicule utilitaire',
};

export const bodyTypeLabel: Record<App.Enums.Vehicle.BodyType, string> = {
    CI: 'Conduite intérieure',
    BB: 'Break',
    CTTE: 'Camionnette',
    BE: 'Pick-up',
    HB: 'Aménagé handicap',
};

export const euroStandardLabel: Record<App.Enums.Vehicle.EuroStandard, string> = {
    euro_1: 'Euro 1',
    euro_2: 'Euro 2',
    euro_3: 'Euro 3',
    euro_4: 'Euro 4',
    euro_5: 'Euro 5',
    euro_5a: 'Euro 5a',
    euro_5b: 'Euro 5b',
    euro_6: 'Euro 6',
    euro_6b: 'Euro 6b',
    euro_6c: 'Euro 6c',
    euro_6d_temp: 'Euro 6d-Temp',
    euro_6d: 'Euro 6d',
    euro_6d_isc: 'Euro 6d-ISC',
    euro_6d_isc_fcm: 'Euro 6d-ISC-FCM',
};

export const homologationMethodLabel: Record<App.Enums.Vehicle.HomologationMethod, string> = {
    WLTP: 'WLTP',
    NEDC: 'NEDC',
    PA: 'Puissance administrative',
};

export const pollutantCategoryLabel: Record<App.Enums.Vehicle.PollutantCategory, string> = {
    e: 'E (électrique / hydrogène)',
    category_1: 'Catégorie 1 (essence/gaz Euro 5+)',
    most_polluting: 'Les plus polluants',
};

export const fiscalCharacteristicsChangeReasonLabel: Record<App.Enums.Vehicle.FiscalCharacteristicsChangeReason, string> = {
    initial_creation: 'Création initiale',
    recharacterization: 'Reclassement fiscal',
    regulation_change: 'Changement réglementaire',
    other_change: 'Autre changement',
    input_correction: 'Correction de saisie',
};

export const fiscalCharacteristicsExtensionStrategyLabel: Record<App.Enums.Vehicle.FiscalCharacteristicsExtensionStrategy, string> = {
    extend_previous: 'Étendre la version précédente sur la période supprimée',
    extend_next: 'Étendre la version suivante sur la période supprimée',
};

export const underlyingCombustionEngineTypeLabel: Record<App.Enums.Vehicle.UnderlyingCombustionEngineType, string> = {
    gasoline: 'Essence',
    diesel: 'Diesel',
    not_applicable: 'Sans objet',
};

export const vehicleStatusLabel: Record<App.Enums.Vehicle.VehicleStatus, string> = {
    active: 'Actif',
    maintenance: 'Maintenance',
    sold: 'Vendu',
    destroyed: 'Détruit',
    other: 'Autre',
};

export const vehicleExitReasonLabel: Record<App.Enums.Vehicle.VehicleExitReason, string> = {
    sold: 'Vendu',
    destroyed: 'Détruit',
    transferred: 'Transféré',
    other: 'Autre',
};
