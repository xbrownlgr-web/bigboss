<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Décision du fondateur (2026-11-08) : Gold à 12:00 et Platinum à 14:00 au lieu de 16:00 et 20:00.
 * Le soir, il ne restait plus assez de matchs (avec cotes) pour 4 et 5 événements : les coupons
 * sortaient en « coupon de secours » à 1-2 sélections. À la mi-journée, toute la journée de matchs
 * est encore disponible.
 */
return new class extends Migration
{
    private const OLD_PLATINUM_LINE = '⚡ Notifications prioritaires matin et soir.';

    private const NEW_PLATINUM_LINE = '⚡ Notification prioritaire dès la publication, chaque jour à 14h.';

    public function up(): void
    {
        AppSetting::setValue('ai_schedule_gold', '12:00');
        AppSetting::setValue('ai_schedule_platinum', '14:00');

        // « matin et soir » ne serait plus vrai : une publication par jour, à 14h.
        $details = (string) AppSetting::value('vip_platinum_details');

        if (str_contains($details, self::OLD_PLATINUM_LINE)) {
            AppSetting::setValue('vip_platinum_details', str_replace(self::OLD_PLATINUM_LINE, self::NEW_PLATINUM_LINE, $details));
        }
    }

    public function down(): void
    {
        AppSetting::setValue('ai_schedule_gold', '16:00');
        AppSetting::setValue('ai_schedule_platinum', '20:00');
    }
};
