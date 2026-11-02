<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // La release big-boss v1.0.0 existe maintenant : on vide le cache pour que la version
        // et le lien de téléchargement se mettent à jour tout de suite.
        cache()->forget('github_latest_apk_version');
        cache()->forget('github_latest_apk_version_last_known');
    }

    public function down(): void
    {
    }
};
