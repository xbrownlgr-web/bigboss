<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migration 2026_11_05_000001 comparait la valeur entière avec des \n, mais la valeur réellement
 * stockée en base utilise des \r\n (saisie via le formulaire admin sur Windows) : la comparaison
 * exacte ne correspondait jamais et rien n'a été corrigé. Remplace seulement le fragment « 3
 * événements » par « 2 événements » dans vip_bronze_details, sans dépendre des fins de ligne, et
 * seulement si le texte contient encore exactement le libellé par défaut (jamais une valeur réécrite
 * par l'admin avec d'autres mots).
 */
return new class extends Migration
{
    private const OLD_FRAGMENT = '⭐ 3 événements sélectionnés chaque jour par notre équipe.';

    private const NEW_FRAGMENT = '⭐ 2 événements sélectionnés chaque jour par notre équipe.';

    public function up(): void
    {
        $value = DB::table('app_settings')->where('key', 'vip_bronze_details')->value('value');

        if ($value === null || ! str_contains($value, self::OLD_FRAGMENT)) {
            return;
        }

        DB::table('app_settings')
            ->where('key', 'vip_bronze_details')
            ->update(['value' => str_replace(self::OLD_FRAGMENT, self::NEW_FRAGMENT, $value), 'updated_at' => now()]);
    }

    public function down(): void
    {
        $value = DB::table('app_settings')->where('key', 'vip_bronze_details')->value('value');

        if ($value === null || ! str_contains($value, self::NEW_FRAGMENT)) {
            return;
        }

        DB::table('app_settings')
            ->where('key', 'vip_bronze_details')
            ->update(['value' => str_replace(self::NEW_FRAGMENT, self::OLD_FRAGMENT, $value)]);
    }
};
