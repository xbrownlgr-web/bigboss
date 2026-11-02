<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class AppSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'label',
        'value',
        'type',
    ];

    // Coupons VIP payants : leur contenu n'est jamais envoyé à l'application (voir PronosticController).
    public const VIP_LEVELS = ['Silver', 'Gold', 'Platinum'];

    public static function value(string $key, ?string $default = null): ?string
    {
        try {
            return cache()->rememberForever(
                'app_setting_' . $key,
                fn () => self::query()->where('key', $key)->value('value') ?? $default,
            );
        } catch (Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, ?string $value): void
    {
        self::query()->where('key', $key)->update(['value' => $value]);
        cache()->forget('app_setting_' . $key);
    }

    public static function grouped(): Collection
    {
        $order = ['brand', 'app', 'mobile_money', 'retrait', 'vip', 'assistant', 'ia', 'videos', 'whatsapp', 'general', 'community'];

        return self::query()
            ->orderBy('id')
            ->get()
            ->sortBy(function (AppSetting $setting) use ($order) {
                $position = array_search($setting->group, $order, true);

                return $position === false ? count($order) : $position;
            })
            ->groupBy('group');
    }

    /**
     * Numéros de dépôt du Bénin (Admin > Dépôts Mobile Money) : ce que lisent les anciennes
     * versions de l'application, qui ne connaissent pas les numéros par pays.
     *
     * @return array<int, array<string, string>>
     */
    public static function depositOperators(): array
    {
        return DepositNumber::forCountry('benin');
    }

    /**
     * @return array<string, array{price: ?int, details: ?string}>
     */
    public static function vipOffers(): array
    {
        $offers = [];

        foreach (self::VIP_LEVELS as $level) {
            $key = 'vip_' . strtolower($level);
            $price = (int) preg_replace('/\D+/', '', (string) self::value($key . '_price'));

            $offers[$level] = [
                'price' => $price > 0 ? $price : null,
                'details' => trim((string) self::value($key . '_details')) ?: null,
            ];
        }

        return $offers;
    }

    public static function publicPayload(): array
    {
        return [
            // Marque : nom affiché, logo et palette de couleurs, choisis dans Admin > Marque & couleurs.
            // L'application mobile les applique à chaud (nom, thème) ; défaut si rien n'est réglé.
            'branding' => [
                'app_name' => trim((string) self::value('brand_app_name')) ?: 'BIG BOSS',
                'logo_url' => trim((string) self::value('brand_logo_url')) ?: null,
                'palette' => \App\Support\BrandPalettes::resolve(self::value('brand_palette')),
            ],
            'mobile_money_operators' => self::depositOperators(),
            // Numéros de dépôt de chaque pays : l'application affiche ceux du pays du client.
            'deposit_operators' => DepositNumber::byCountry(),
            // Adresse à choisir sur 1xBet / Melbet pour un retrait « Espèces » : ville et rue (« point »).
            'retrait' => [
                'ville' => trim((string) self::value('retrait_ville')) ?: null,
                'point' => trim((string) self::value('retrait_point')) ?: null,
            ],
            // Vidéos tutoriels verticales, gérées dans Admin > Vidéos.
            'videos' => [
                'recharge' => self::value('video_recharge_url') ?: null,
                'retrait' => self::value('video_retrait_url') ?: null,
            ],
            // Accès aux coupons VIP : prix et détails par niveau, et numéro WhatsApp (chiffres
            // seulement, indicatif compris) pour le lien wa.me avec le message pré-écrit.
            'vip' => [
                'whatsapp' => preg_replace('/\D+/', '', (string) self::value('vip_whatsapp_number')) ?: null,
                'offers' => self::vipOffers(),
                // Coupons VIP gagnés sur les 30 derniers jours, par niveau (argument de vente).
                'stats' => app(\App\Services\VipService::class)->stats(),
            ],
            'community_links' => self::communityLinks(),
        ];
    }

    /**
     * Canaux officiels (Admin > Paramètres > Communautés), affichés dans l'application et sur le site.
     *
     * @return array<string, ?string>
     */
    public static function communityLinks(): array
    {
        return [
            'telegram' => self::value('telegram_url', ''),
            'whatsapp' => self::value('whatsapp_channel_url', 'https://whatsapp.com/channel/0029VbCycEHISTkBkokScd0K'),
            'facebook' => self::value('facebook_url', 'https://www.facebook.com/binhoflash'),
            'tiktok' => self::value('tiktok_url', 'https://www.tiktok.com/@binhoflash'),
            'instagram' => self::value('instagram_url', 'https://www.instagram.com/binhoflash'),
        ];
    }
}
