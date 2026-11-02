<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numéros de dépôt par pays. Les trois numéros béninois des paramètres (MTN, Moov, Celtiis)
 * sont repris tels quels, puis retirés des paramètres : la page Admin > Dépôts Mobile Money
 * devient le seul endroit où les gérer.
 */
return new class extends Migration
{
    private const LEGACY = [
        'mtn_momo' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'celtiis' => 'Celtiis',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('deposit_numbers')) {
            Schema::create('deposit_numbers', function (Blueprint $table) {
                $table->id();
                $table->string('country', 32);
                $table->string('operator', 40);
                $table->string('number', 40);
                $table->string('account_name', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['country', 'is_active']);
            });
        }

        $now = now();
        $order = 0;

        foreach (self::LEGACY as $key => $operator) {
            $number = trim((string) DB::table('app_settings')->where('key', $key . '_number')->value('value'));
            $name = trim((string) DB::table('app_settings')->where('key', $key . '_name')->value('value'));

            if ($number !== '') {
                DB::table('deposit_numbers')->insert([
                    'country' => 'benin',
                    'operator' => $operator,
                    'number' => $number,
                    'account_name' => $name !== '' ? $name : null,
                    'is_active' => true,
                    'sort_order' => $order++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('app_settings')->whereIn('key', [$key . '_number', $key . '_name'])->delete();
            Cache::forget('app_setting_' . $key . '_number');
            Cache::forget('app_setting_' . $key . '_name');
        }
    }

    public function down(): void
    {
        $now = now();

        foreach (self::LEGACY as $key => $operator) {
            $row = DB::table('deposit_numbers')->where('country', 'benin')->where('operator', $operator)->orderBy('id')->first();

            foreach ([
                [$key . '_number', $operator . ' : numéro de dépôt', $row->number ?? ''],
                [$key . '_name', $operator . ' : nom affiché pendant le transfert', $row->account_name ?? ''],
            ] as [$settingKey, $label, $value]) {
                DB::table('app_settings')->updateOrInsert(
                    ['key' => $settingKey],
                    ['group' => 'mobile_money', 'label' => $label, 'value' => $value, 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        Schema::dropIfExists('deposit_numbers');
    }
};
