<?php

namespace App\Support;

/**
 * Palettes de couleurs proposées dans l'admin (Marque & couleurs). L'administrateur en choisit
 * une ; l'application mobile la lit via /settings/public et l'applique à tout son thème.
 * Chaque palette donne : couleur principale, principale foncée, accent, et la couleur du texte
 * posé sur l'accent (lisible). Ajouter une palette ici suffit à la proposer dans l'admin.
 */
class BrandPalettes
{
    public const DEFAULT = 'navy_gold';

    /**
     * @var array<string, array{label: string, primary: string, primary_dark: string, accent: string, on_accent: string}>
     */
    public const PALETTES = [
        'navy_gold' => ['label' => 'Bleu nuit & Or', 'primary' => '#1A2E5A', 'primary_dark' => '#0E1B3A', 'accent' => '#FFD700', 'on_accent' => '#1A2E5A'],
        'emerald_gold' => ['label' => 'Émeraude & Or', 'primary' => '#064E3B', 'primary_dark' => '#022C22', 'accent' => '#F5C542', 'on_accent' => '#022C22'],
        'black_gold' => ['label' => 'Noir & Or', 'primary' => '#1C1C1C', 'primary_dark' => '#0A0A0A', 'accent' => '#E0A100', 'on_accent' => '#1C1C1C'],
        'royal_purple' => ['label' => 'Violet royal & Or', 'primary' => '#3B0764', 'primary_dark' => '#250544', 'accent' => '#FFD700', 'on_accent' => '#250544'],
        'crimson_gold' => ['label' => 'Rouge & Or', 'primary' => '#7F1D1D', 'primary_dark' => '#4C0D0D', 'accent' => '#F5C542', 'on_accent' => '#4C0D0D'],
        'ocean_cyan' => ['label' => 'Océan & Cyan', 'primary' => '#0E3A5A', 'primary_dark' => '#06233A', 'accent' => '#22D3EE', 'on_accent' => '#06233A'],
        'forest_lime' => ['label' => 'Forêt & Citron', 'primary' => '#14532D', 'primary_dark' => '#082016', 'accent' => '#A3E635', 'on_accent' => '#082016'],
        'slate_orange' => ['label' => 'Ardoise & Orange', 'primary' => '#1E293B', 'primary_dark' => '#0B1220', 'accent' => '#F97316', 'on_accent' => '#0B1220'],
        'green_black' => ['label' => 'Vert & Noir', 'primary' => '#0C2A1E', 'primary_dark' => '#061A12', 'accent' => '#22C55E', 'on_accent' => '#06231A'],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::PALETTES);
    }

    /**
     * Couleurs d'une palette (la palette par défaut si la clé est inconnue ou vide).
     *
     * @return array{key: string, label: string, primary: string, primary_dark: string, accent: string, on_accent: string}
     */
    public static function resolve(?string $key): array
    {
        $key = $key !== null && isset(self::PALETTES[$key]) ? $key : self::DEFAULT;

        return ['key' => $key, ...self::PALETTES[$key]];
    }
}
