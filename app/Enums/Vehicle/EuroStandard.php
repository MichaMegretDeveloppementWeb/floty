<?php

namespace App\Enums\Vehicle;

/**
 * Norme Euro du véhicule (rubrique V.9 de la carte grise).
 *
 * Les sous-déclinaisons Euro 5a/5b et Euro 6b/6c/6d-Temp/6d/6d-ISC/6d-ISC-FCM
 * sont toutes représentées explicitement car le catalogue fiscal 2024 les
 * traite (cf. R-2024-013 § catégorie polluants).
 */
enum EuroStandard: string
{
    case Euro1 = 'euro_1';
    case Euro2 = 'euro_2';
    case Euro3 = 'euro_3';
    case Euro4 = 'euro_4';
    case Euro5 = 'euro_5';
    case Euro5a = 'euro_5a';
    case Euro5b = 'euro_5b';
    case Euro6 = 'euro_6';
    case Euro6b = 'euro_6b';
    case Euro6c = 'euro_6c';
    case Euro6dTemp = 'euro_6d_temp';
    case Euro6d = 'euro_6d';
    case Euro6dIsc = 'euro_6d_isc';
    case Euro6dIscFcm = 'euro_6d_isc_fcm';

    /**
     * Vrai pour les normes Euro 5 ou Euro 6 (toutes sous-déclinaisons),
     * utilisé par la règle R-2024-013 pour la catégorisation polluants.
     */
    public function isEuro5OrAbove(): bool
    {
        return match ($this) {
            self::Euro1, self::Euro2, self::Euro3, self::Euro4 => false,
            default => true,
        };
    }
}
