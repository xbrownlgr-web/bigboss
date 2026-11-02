<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Numéro Mobile Money qui reçoit les recharges, par pays : le client d'un pays voit seulement les
 * numéros de son pays dans l'écran Recharger (Admin > Dépôts Mobile Money).
 */
class DepositNumber extends Model
{
    // Couleur de chaque opérateur dans l'application (pastilles de l'écran Recharger).
    public const OPERATORS = [
        'MTN MoMo' => '#FFD700',
        'Moov Money' => '#0066CC',
        'Celtiis' => '#E30613',
        'Orange Money' => '#FF7900',
        'Wave' => '#1DC8F2',
        'Free Money' => '#CD1E25',
        'Airtel Money' => '#E40000',
        'M-Pesa' => '#00A651',
        'Afrimoney' => '#F7941D',
        'Mixx (T-Money)' => '#FFD100',
        'Flooz (Moov)' => '#0066CC',
    ];

    // Opérateurs présents dans chaque pays (dépôts et retraits), mêmes listes que l'application.
    public const COUNTRY_OPERATORS = [
        'benin' => ['MTN MoMo', 'Moov Money', 'Celtiis'],
        'cote_ivoire' => ['Orange Money', 'MTN MoMo', 'Moov Money', 'Wave'],
        'senegal' => ['Orange Money', 'Wave', 'Free Money'],
        'burkina_faso' => ['Orange Money', 'Moov Money'],
        'mali' => ['Orange Money', 'Moov Money'],
        'niger' => ['Airtel Money', 'Moov Money'],
        'togo' => ['Mixx (T-Money)', 'Flooz (Moov)'],
        'gabon' => ['Airtel Money', 'Moov Money'],
        'rdc' => ['M-Pesa', 'Orange Money', 'Airtel Money', 'Afrimoney'],
        'cameroun' => ['MTN MoMo', 'Orange Money'],
    ];

    protected $fillable = [
        'country',
        'operator',
        'number',
        'account_name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Opérateurs d'un pays au format attendu par l'application (« ussd » et « numero_marchand »
     * restent pour les anciennes versions).
     *
     * @return array<int, array<string, string>>
     */
    public static function forCountry(?string $country): array
    {
        return self::byCountry()[$country ?: 'benin'] ?? [];
    }

    /**
     * Tous les numéros actifs, groupés par pays. Une base injoignable ne casse ni l'API ni le site.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public static function byCountry(): array
    {
        try {
            return self::query()
                ->active()
                ->get()
                ->groupBy('country')
                ->map(fn ($numbers) => $numbers->map(fn (self $number) => $number->toApp())->values()->all())
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    public function toApp(): array
    {
        return [
            'key' => $this->country . '-' . $this->id,
            'nom' => $this->operator,
            'couleur' => self::OPERATORS[$this->operator] ?? '#FFD700',
            'numero' => $this->number,
            'nom_compte' => (string) $this->account_name,
            'numero_marchand' => $this->number,
            'ussd' => '',
        ];
    }
}
