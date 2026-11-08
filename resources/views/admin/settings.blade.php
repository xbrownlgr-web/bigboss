@extends('admin.layout')

@section('title', 'Paramètres')
@section('subtitle', 'Modifiez les informations visibles dans l’application sans toucher au code')

@php
    $groupIcons = [
        'brand' => 'bi-palette',
        'payment' => 'bi-credit-card',
        'app' => 'bi-phone',
        'ia' => 'bi-stars',
        'assistant' => 'bi-robot',
        'videos' => 'bi-play-btn',
        'retrait' => 'bi-cash-coin',
        'mobile_money' => 'bi-wallet2',
        'whatsapp' => 'bi-whatsapp',
        'general' => 'bi-envelope',
        'community' => 'bi-megaphone',
        'vip' => 'bi-gem',
    ];
    $placeholders = [
        'ai_pronostic_instructions' => 'Ex. privilégier les marchés prudents et éviter les derbys difficiles à prévoir',
        'assistant_instructions' => 'Ex. Les recharges sont validées en 5 à 15 minutes de 7h à 23h. MTN MoMo : composez *880#. Promo : 10 % offerts dès 10 000 FCFA ce week-end.',
    ];
    $hints = [
        'app_latest_version' => 'Suivi automatiquement : la dernière version publiée sur GitHub est utilisée si elle est plus récente.',
        'app_min_version' => 'En dessous de cette version, l’application est bloquée jusqu’à la mise à jour.',
        'app_download_url' => 'Lien direct vers le fichier .apk (ex. https://big-boss-gure.onrender.com/big-boss.apk).',
        'cloudinary_cloud_name' => 'Visible en haut du tableau de bord Cloudinary (Dashboard > Product environment).',
        'video_recharge_url' => 'Rempli automatiquement depuis Admin > Vidéos.',
        'video_retrait_url' => 'Rempli automatiquement depuis Admin > Vidéos.',
        'retrait_ville' => 'Ex. Cotonou : la ville à choisir après « Espèces » sur 1xBet / Melbet.',
        'retrait_point' => 'Ex. Chez Boss17 : la rue exacte du guichet, telle qu’elle apparaît sur 1xBet / Melbet.',
        'callmebot_phone' => 'Votre numéro WhatsApp au format international, avec l’indicatif : +229 01 97 00 00 00. Le numéro qui a envoyé le message d’activation à CallMeBot.',
        'callmebot_apikey' => 'La clé reçue de CallMeBot sur WhatsApp après l’activation.',
        'admin_email' => 'Votre vraie adresse : vous y recevez chaque nouvelle recharge, chaque retrait et chaque message. Plusieurs adresses possibles, séparées par des virgules.',
        'openrouter_api_key' => 'À créer sur openrouter.ai/keys, puis créditer quelques dollars : une génération coûte moins d’un centime.',
        'openrouter_model' => 'Défaut : openai/gpt-4o-mini. Autres choix : deepseek/deepseek-chat-v3.1, google/gemini-2.5-flash.',
        'ai_pronostic_instructions' => 'Le document « BIG BOSS — instructions principales » : groupes, sports, cotes, marchés, format. Il complète les règles du serveur, qui restent appliquées quoi qu’il arrive (seuils, cotes, matchs différents entre gratuits et VIP). 10 000 caractères maximum.',
        'ai_schedule_starter' => 'Gratuit, seul niveau gratuit : 1 événement par coupon, 2 coupons par jour (matin et soir, il reste à 1 événement). Heure du Bénin, ex. 09:00, 19:00. Vide = groupe désactivé.',
        'ai_schedule_bronze' => 'VIP : 2 événements par coupon, 1 coupon par jour. Ex. 11:00. Vide = groupe désactivé.',
        'ai_schedule_silver' => 'VIP : 3 événements par coupon, 1 coupon par jour. Ex. 13:00.',
        'ai_schedule_gold' => 'VIP : 4 événements par coupon, 1 coupon par jour. Ex. 16:00.',
        'ai_schedule_platinum' => 'VIP, le niveau le plus complet : 5 événements par coupon, 1 coupon par jour. Ex. 20:00.',
        'ai_schedule_score' => 'Offre Score exact : à chaque heure, l’IA cherche les matchs les plus prévisibles et publie 2 scores exacts au plus (au moins 4 par jour si les données le permettent, 6 au maximum). Chaque score est notifié dès sa publication. Ex. 08:00, 12:00, 16:00, 20:00.',
        'ai_exact_score_instructions' => 'Votre méthode d’analyse des scores exacts. Le serveur fait les calculs (buts attendus, grille de Poisson, comparaison avec le marché, confiance) ; l’IA applique ces consignes pour les ajustements chiffrés et la rédaction, sans jamais inventer de données.',
        'ai_min_probability_free' => 'Probabilité estimée minimale de chaque sélection Starter et Bronze (consigne : 75). Plus bas = plus de coupons, mais moins fiables.',
        'ai_min_probability_vip' => 'Probabilité estimée minimale de chaque sélection VIP (consigne : 80). À 80, beaucoup de créneaux ne publieront rien : c’est voulu.',
        'ai_vip_teasers_per_day' => 'Les abonnés reçoivent chaque coupon VIP ; les non-abonnés ne reçoivent que ce nombre d’annonces « nouveau coupon VIP » par jour (évite de les saturer).',
        'assistant_model' => 'Vide = même modèle que les pronostics. Pour des réponses plus naturelles : openai/gpt-4.1-mini ou google/gemini-2.5-flash (moins d’un centime par message).',
        'assistant_instructions' => 'Tout ce que l’assistant doit savoir en plus : horaires de traitement, délais habituels, codes USSD de vos opérateurs, promotions en cours, réponses à donner aux questions fréquentes. Ces informations passent avant ses connaissances générales.',
        'assistant_daily_limit' => 'Au-delà, le client est invité à écrire au conseiller (protège votre crédit OpenRouter).',
        'highlightly_api_key' => 'Gratuite sur highlightly.net (100 requêtes par jour). La liste des matchs est gardée 6 h, les statistiques 12 h, pour tenir dans ce quota.',
        'football_data_api_key' => 'Optionnelle, gratuite sur football-data.org (10 requêtes/minute, sans plafond quotidien) : décharge Highlightly du classement et de la forme pour 10 grands championnats (Premier League, Liga, Bundesliga, Ligue 1, Serie A, Eredivisie, Liga Portugal, Championship, Brésil, Ligue des champions). Vide = tout reste sur Highlightly comme avant.',
        'vip_whatsapp_number' => 'Format international avec l’indicatif, ex. +229 01 97 00 00 00. C’est vers ce numéro que part le message de l’utilisateur.',
        'vip_bronze_price' => 'En FCFA, chiffres seulement (ex. 1500). Vide = prix non affiché.',
        'vip_silver_price' => 'En FCFA, chiffres seulement (ex. 2000). Vide = prix non affiché.',
        'vip_gold_price' => 'En FCFA, chiffres seulement (ex. 5000). Vide = prix non affiché.',
        'vip_platinum_price' => 'En FCFA, chiffres seulement (ex. 10000). Vide = prix non affiché.',
        'vip_bronze_price_week' => 'Facultatif : un abonnement d’essai à la semaine, moins engageant que le mois. Vide = seul le mois est proposé.',
        'vip_silver_price_week' => 'Facultatif : un abonnement d’essai à la semaine, moins engageant que le mois. Vide = seul le mois est proposé.',
        'vip_gold_price_week' => 'Facultatif : un abonnement d’essai à la semaine, moins engageant que le mois. Vide = seul le mois est proposé.',
        'vip_platinum_price_week' => 'Facultatif : un abonnement d’essai à la semaine, moins engageant que le mois. Vide = seul le mois est proposé.',
        'vip_bronze_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'vip_silver_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'vip_gold_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'vip_platinum_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'vip_score_price' => 'Offre Score exact, abonnement à part (ex. 5000). Vide = prix non affiché.',
        'vip_score_price_week' => 'Facultatif : essai à la semaine pour l’offre Score exact. Vide = seul le mois est proposé.',
        'vip_score_details' => 'Affiché sous le prix. Gardez l’avertissement « peut perdre » : le score exact est un pari à haut risque.',
        'odds_api_key' => 'Gratuite sur the-odds-api.com (500 crédits par mois) : les crédits sont répartis sur les jours restants et les cotes gardées 8 h, en commençant par les championnats prioritaires. Avec un abonnement payant, plus de championnats et les marchés « les deux équipes marquent » s’ajoutent automatiquement.',
    ];
@endphp

@section('content')
    <form method="POST" action="/admin/settings">
        @csrf

        <div class="row g-3">
            @foreach ($groups as $groupKey => $settings)
                <div class="col-12 {{ in_array($groupKey, ['general', 'whatsapp'], true) ? 'col-xl-6' : '' }}">
                    <details class="bf-card bf-fold h-100" id="{{ $groupKey }}" @if ($errors->hasAny($settings->pluck('key')->all())) open @endif data-desktop-open>
                        <summary class="bf-card-header justify-content-start">
                            <span class="bf-kpi-icon"><i class="bi {{ $groupIcons[$groupKey] ?? 'bi-gear' }}"></i></span>
                            <div class="min-w-0">
                                <h2 class="bf-card-title">{{ $groupLabels[$groupKey] ?? ucfirst($groupKey) }}</h2>
                                <p class="bf-card-sub">{{ $groupDescriptions[$groupKey] ?? '' }}</p>
                            </div>
                            <span class="bf-pill muted ms-auto">{{ $settings->count() }}</span>
                            <i class="bi bi-chevron-down bf-chev"></i>
                        </summary>
                        <div class="bf-card-body">
                            @if ($groupKey === 'general')
                                <button class="btn btn-sm bf-btn-light mb-3" type="submit" form="testEmailForm"><i class="bi bi-envelope-check"></i> Envoyer un e-mail de test</button>
                            @endif
                            @if ($groupKey === 'app')
                                <button class="btn btn-sm bf-btn-gold mb-3" type="submit" form="notifyAppUpdateForm" data-confirm="Prévenir tous les utilisateurs qu'une mise à jour de BIG BOSS est prête ?"><i class="bi bi-megaphone"></i> Notifier une mise à jour disponible</button>
                            @endif
                            @if ($groupKey === 'assistant')
                                <button class="btn btn-sm bf-btn-light mb-2" type="submit" form="testAssistantForm"><i class="bi bi-chat-dots"></i> Tester LUC</button>
                                @if ($assistantError = \App\Services\SupportAssistant::lastError())
                                    <div class="bf-small mb-3" style="color:#B91C1C;"><i class="bi bi-exclamation-triangle"></i> Dernière panne ({{ \Illuminate\Support\Carbon::parse($assistantError['at'])->timezone(config('app.timezone'))->format('d/m H:i') }}) : {{ $assistantError['error'] }}</div>
                                @endif
                            @endif
                            @if ($groupKey === 'whatsapp')
                                <button class="btn btn-sm bf-btn-light mb-2" type="submit" form="testWhatsAppForm"><i class="bi bi-whatsapp"></i> Envoyer un WhatsApp de test (CallMeBot)</button>
                                <button class="btn btn-sm bf-btn-light mb-3" type="submit" form="testWhatsAppCloudForm"><i class="bi bi-whatsapp"></i> Tester l’alerte Cloud API (recharges/retraits)</button>
                                <div class="bf-small mb-3">L’alerte Cloud API (Meta) n’est utilisée que pour les nouvelles recharges/retraits, configurée par variables d’environnement (Render), pas ici. Si elle échoue, le message ci-dessus (CallMeBot) prend automatiquement le relais.</div>
                            @endif
                            <div class="row g-3">
                                @foreach ($settings as $setting)
                                    @php
                                        $value = old($setting->key, $setting->value);
                                        $isPlaceholder = false;
                                    @endphp
                                    <div class="col-12 {{ (in_array($groupKey, ['general', 'whatsapp'], true) || $setting->type === 'palette') ? '' : 'col-md-6' }}">
                                        <label class="form-label" for="{{ $setting->key }}">{{ $setting->label }}</label>
                                        @if ($setting->type === 'palette')
                                            @once
                                                <style>
                                                    .bf-palette-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
                                                    .bf-swatch { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px; border: 2px solid #E4E9F2; border-radius: 12px; cursor: pointer; background: #fff; transition: border-color .15s, box-shadow .15s; margin: 0; }
                                                    .bf-swatch:hover { border-color: #C7D0E0; }
                                                    .bf-swatch.is-selected { border-color: #15803D; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
                                                    .bf-swatch input { position: absolute; opacity: 0; pointer-events: none; }
                                                    .bf-swatch-colors { display: flex; border-radius: 8px; overflow: hidden; width: 46px; height: 30px; flex: 0 0 auto; border: 1px solid rgba(0,0,0,.08); }
                                                    .bf-swatch-colors span { flex: 1; }
                                                    .bf-swatch-label { font-size: 13px; font-weight: 600; color: #1F2937; }
                                                </style>
                                            @endonce
                                            <div class="bf-palette-grid">
                                                @foreach ($palettes as $paletteKey => $palette)
                                                    <label class="bf-swatch {{ $value === $paletteKey ? 'is-selected' : '' }}" data-palette-option>
                                                        <input type="radio" name="{{ $setting->key }}" value="{{ $paletteKey }}" {{ $value === $paletteKey ? 'checked' : '' }}>
                                                        <span class="bf-swatch-colors">
                                                            <span style="background: {{ $palette['primary'] }}"></span>
                                                            <span style="background: {{ $palette['accent'] }}"></span>
                                                        </span>
                                                        <span class="bf-swatch-label">{{ $palette['label'] }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($setting->type === 'textarea')
                                            <textarea id="{{ $setting->key }}" class="form-control" name="{{ $setting->key }}" rows="{{ $setting->key === 'ai_pronostic_instructions' ? 14 : 6 }}" maxlength="10000" placeholder="{{ $placeholders[$setting->key] ?? '' }}">{{ $value }}</textarea>
                                        @elseif ($setting->type === 'password')
                                            <div class="input-group">
                                                <input id="{{ $setting->key }}" class="form-control" type="password" name="{{ $setting->key }}" value="{{ $value }}" autocomplete="off">
                                                <button class="btn bf-btn-light" type="button" data-reveal="{{ $setting->key }}" aria-label="Afficher la valeur"><i class="bi bi-eye"></i></button>
                                            </div>
                                        @else
                                            <input
                                                id="{{ $setting->key }}"
                                                class="form-control {{ $isPlaceholder ? 'is-invalid' : '' }}"
                                                type="{{ $setting->type }}"
                                                name="{{ $setting->key }}"
                                                value="{{ $value }}"
                                                placeholder="{{ $setting->label }}"
                                            >
                                        @endif
                                        @if ($isPlaceholder)
                                            <div class="invalid-feedback d-block">Valeur provisoire : remplacez-la par votre vrai numéro marchand.</div>
                                        @elseif (isset($hints[$setting->key]))
                                            <div class="form-text">{{ $hints[$setting->key] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>

        <div class="bf-card bf-sticky-save mt-3" style="position:sticky;bottom:16px;z-index:5;">
            <div class="bf-card-body d-flex align-items-center justify-content-between gap-3 py-2">
                <div class="bf-small bf-muted d-none d-md-block">
                    <i class="bi bi-lightning-charge"></i> Les nouvelles valeurs sont utilisées immédiatement par l’API et l’application mobile.
                </div>
                <button class="btn bf-btn-primary px-4 flex-grow-1 flex-md-grow-0" type="submit"><i class="bi bi-check2"></i> Enregistrer</button>
            </div>
        </div>
    </form>
@endsection

{{-- Hors du formulaire principal (les formulaires ne s'imbriquent pas) --}}
@push('scripts')
    <form method="POST" action="/admin/settings/test-email" id="testEmailForm" hidden>
        @csrf
    </form>
    <form method="POST" action="/admin/settings/test-whatsapp" id="testWhatsAppForm" hidden>
        @csrf
    </form>
    <form method="POST" action="/admin/settings/test-whatsapp-cloud" id="testWhatsAppCloudForm" hidden>
        @csrf
    </form>
    <form method="POST" action="/admin/settings/test-assistant" id="testAssistantForm" hidden>
        @csrf
    </form>
    <form method="POST" action="/admin/settings/notify-app-update" id="notifyAppUpdateForm" hidden>
        @csrf
    </form>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-reveal]').forEach(function (button) {
            button.addEventListener('click', function () {
                const field = document.getElementById(button.dataset.reveal);
                const show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });

        // Surbrillance immédiate de la palette choisie.
        document.querySelectorAll('[data-palette-option] input[type="radio"]').forEach(function (input) {
            input.addEventListener('change', function () {
                document.querySelectorAll('[data-palette-option]').forEach(function (label) {
                    label.classList.toggle('is-selected', label.contains(input) && input.checked);
                });
            });
        });
    </script>
@endpush
