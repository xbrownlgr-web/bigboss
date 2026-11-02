@extends('admin.layout')

@section('title', 'Paramètres')
@section('subtitle', 'Modifiez les informations visibles dans l’application sans toucher au code')

@php
    $groupIcons = [
        'brand' => 'bi-palette',
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
        'app_download_url' => 'Lien direct vers le fichier .apk (ex. https://binhoflash.com/app.apk).',
        'cloudinary_cloud_name' => 'Visible en haut du tableau de bord Cloudinary (Dashboard > Product environment).',
        'video_recharge_url' => 'Rempli automatiquement depuis Admin > Vidéos.',
        'video_retrait_url' => 'Rempli automatiquement depuis Admin > Vidéos.',
        'retrait_ville' => 'Ex. Natitingou : la ville à choisir après « Espèces » sur 1xBet / Melbet.',
        'retrait_point' => 'Ex. Chez Binho17 : la rue exacte du guichet, telle qu’elle apparaît sur 1xBet / Melbet.',
        'callmebot_phone' => 'Votre numéro WhatsApp au format international, avec l’indicatif : +229 01 97 00 00 00. Le numéro qui a envoyé le message d’activation à CallMeBot.',
        'callmebot_apikey' => 'La clé reçue de CallMeBot sur WhatsApp après l’activation.',
        'admin_email' => 'Votre vraie adresse : vous y recevez chaque nouvelle recharge, chaque retrait et chaque message. Plusieurs adresses possibles, séparées par des virgules.',
        'openrouter_api_key' => 'À créer sur openrouter.ai/keys, puis créditer quelques dollars : une génération coûte moins d’un centime.',
        'openrouter_model' => 'Défaut : openai/gpt-4o-mini. Autres choix : deepseek/deepseek-chat-v3.1, google/gemini-2.5-flash.',
        'ai_pronostic_instructions' => 'Le document « Binho Flash — instructions principales » : groupes, sports, cotes, marchés, format. Il complète les règles du serveur, qui restent appliquées quoi qu’il arrive (seuils, cotes, matchs différents entre gratuits et VIP). 10 000 caractères maximum.',
        'ai_schedule_starter' => 'Gratuit : 1 coupon par jour, 1 à 2 sélections. Heure du Bénin, ex. 09:00. Vide = groupe désactivé.',
        'ai_schedule_bronze' => 'Gratuit : 1 coupon par jour, 1 à 3 sélections. Ex. 16:30. Vide = groupe désactivé.',
        'ai_schedule_silver' => 'VIP : jusqu’à 3 coupons par jour, 3 sélections au plus, cote 2,10 visée. Ex. 10:00, 15:00, 20:00.',
        'ai_schedule_gold' => 'VIP : jusqu’à 4 coupons par jour. Ex. 09:00, 13:00, 18:00, 23:30.',
        'ai_schedule_platinum' => 'VIP : jusqu’à 4 coupons par jour, analyses les plus poussées. Ex. 08:00, 12:00, 16:00, 21:00.',
        'ai_min_probability_free' => 'Probabilité estimée minimale de chaque sélection Starter et Bronze (consigne : 75). Plus bas = plus de coupons, mais moins fiables.',
        'ai_min_probability_vip' => 'Probabilité estimée minimale de chaque sélection VIP (consigne : 80). À 80, beaucoup de créneaux ne publieront rien : c’est voulu.',
        'ai_vip_teasers_per_day' => 'Les abonnés reçoivent chaque coupon VIP ; les non-abonnés ne reçoivent que ce nombre d’annonces « nouveau coupon VIP » par jour (évite de les saturer).',
        'assistant_model' => 'Vide = même modèle que les pronostics. Pour des réponses plus naturelles : openai/gpt-4.1-mini ou google/gemini-2.5-flash (moins d’un centime par message).',
        'assistant_instructions' => 'Tout ce que l’assistant doit savoir en plus : horaires de traitement, délais habituels, codes USSD de vos opérateurs, promotions en cours, réponses à donner aux questions fréquentes. Ces informations passent avant ses connaissances générales.',
        'assistant_daily_limit' => 'Au-delà, le client est invité à écrire au conseiller (protège votre crédit OpenRouter).',
        'highlightly_api_key' => 'Gratuite sur highlightly.net (100 requêtes par jour). La liste des matchs est gardée 6 h, les statistiques 12 h, pour tenir dans ce quota.',
        'vip_whatsapp_number' => 'Format international avec l’indicatif, ex. +229 01 97 00 00 00. C’est vers ce numéro que part le message de l’utilisateur.',
        'vip_silver_price' => 'En FCFA, chiffres seulement (ex. 2000). Vide = prix non affiché.',
        'vip_gold_price' => 'En FCFA, chiffres seulement (ex. 5000). Vide = prix non affiché.',
        'vip_platinum_price' => 'En FCFA, chiffres seulement (ex. 10000). Vide = prix non affiché.',
        'vip_silver_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'vip_gold_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'vip_platinum_details' => 'Ce qui est compris, la durée, etc. Affiché sous le prix dans l’application.',
        'odds_api_key' => 'Gratuite sur the-odds-api.com (500 crédits par mois) : les crédits sont répartis sur les jours restants et les cotes gardées 8 h, en commençant par les championnats prioritaires. Avec un abonnement payant, plus de championnats et les marchés « les deux équipes marquent » s’ajoutent automatiquement.',
    ];
@endphp

@section('content')
    <form method="POST" action="/admin/settings">
        @csrf

        <div class="row g-3">
            @foreach ($groups as $groupKey => $settings)
                <div class="col-12 {{ in_array($groupKey, ['general', 'whatsapp'], true) ? 'col-xl-6' : '' }}">
                    <section class="bf-card h-100" id="{{ $groupKey }}">
                        <div class="bf-card-header justify-content-start">
                            <span class="bf-kpi-icon"><i class="bi {{ $groupIcons[$groupKey] ?? 'bi-gear' }}"></i></span>
                            <div>
                                <h2 class="bf-card-title">{{ $groupLabels[$groupKey] ?? ucfirst($groupKey) }}</h2>
                                @if ($groupKey === 'general')
                                    <button class="btn btn-sm bf-btn-light mt-2" type="submit" form="testEmailForm"><i class="bi bi-envelope-check"></i> Envoyer un e-mail de test</button>
                                @endif
                                @if ($groupKey === 'assistant')
                                    <button class="btn btn-sm bf-btn-light mt-2" type="submit" form="testAssistantForm"><i class="bi bi-chat-dots"></i> Tester George</button>
                                    @if ($assistantError = \App\Services\SupportAssistant::lastError())
                                        <div class="bf-small mt-2" style="color:#B91C1C;"><i class="bi bi-exclamation-triangle"></i> Dernière panne ({{ \Illuminate\Support\Carbon::parse($assistantError['at'])->timezone(config('app.timezone'))->format('d/m H:i') }}) : {{ $assistantError['error'] }}</div>
                                    @endif
                                @endif
                                @if ($groupKey === 'whatsapp')
                                    <button class="btn btn-sm bf-btn-light mt-2" type="submit" form="testWhatsAppForm"><i class="bi bi-whatsapp"></i> Envoyer un WhatsApp de test</button>
                                @endif
                                <p class="bf-card-sub">{{ $groupDescriptions[$groupKey] ?? '' }}</p>
                            </div>
                        </div>
                        <div class="bf-card-body">
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
                                                    .bf-swatch.is-selected { border-color: #1A2E5A; box-shadow: 0 0 0 3px rgba(26,46,90,.12); }
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
                    </section>
                </div>
            @endforeach
        </div>

        <div class="bf-card mt-3" style="position:sticky;bottom:16px;z-index:5;">
            <div class="bf-card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3">
                <div class="bf-small bf-muted">
                    <i class="bi bi-lightning-charge"></i> Les nouvelles valeurs sont utilisées immédiatement par l’API et l’application mobile.
                </div>
                <button class="btn bf-btn-primary px-4" type="submit"><i class="bi bi-check2"></i> Enregistrer les paramètres</button>
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
    <form method="POST" action="/admin/settings/test-assistant" id="testAssistantForm" hidden>
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
