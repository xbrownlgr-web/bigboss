{{-- Génération des combinés par l'IA (OpenRouter + matchs réels), puis relecture avant publication --}}
@php
    // Les cinq groupes sont générés par l'IA, chacun à ses heures (Admin > Paramètres).
    $levelNames = \App\Services\AiPronosticGenerator::AI_LEVELS;
    $oldLevels = old('levels', $levelNames);
    $defaultSource = old('source', $aiReady['football'] ? 'auto' : 'manual');
@endphp

<section class="bf-card mb-3">
    <div class="bf-card-header">
        <div class="d-flex align-items-center gap-3">
            <span class="bf-kpi-icon" style="background:#F3EEFF;color:#6D28D9;"><i class="bi bi-stars"></i></span>
            <div>
                <h2 class="bf-card-title">Générer les combinés avec l’IA</h2>
                <p class="bf-card-sub">Modèle {{ $aiReady['model'] }} via OpenRouter · vous relisez avant de publier</p>
            </div>
        </div>
        @unless ($aiReady['openrouter'])
            <a href="/admin/settings" class="btn btn-sm bf-btn-gold"><i class="bi bi-key"></i> Ajouter la clé OpenRouter</a>
        @endunless
    </div>

    <form method="POST" action="/admin/pronostics/generate" class="bf-card-body" id="aiForm">
        @csrf
        <div class="row g-3 align-items-end">
            <div class="col-6 col-lg-2">
                <label class="form-label" for="aiDate">Date des matchs</label>
                <input class="form-control" type="date" id="aiDate" name="date" value="{{ old('date', now()->toDateString()) }}" required>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="aiSource">Matchs</label>
                <select class="form-select" id="aiSource" name="source">
                    <option value="auto" @selected($defaultSource === 'auto')>Matchs réels (Highlightly){{ $aiReady['football'] ? '' : ' – clé manquante' }}</option>
                    <option value="manual" @selected($defaultSource === 'manual')>Je colle ma liste de matchs (sans cotes ni statistiques : aucune valeur vérifiable)</option>
                </select>
            </div>
            <div class="col-12 col-lg-5">
                <span class="form-label d-block">Niveaux</span>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($levelNames as $level)
                        <input type="checkbox" class="btn-check" name="levels[]" value="{{ $level }}" id="lvl{{ $level }}" @checked(in_array($level, $oldLevels, true))>
                        <label class="btn btn-sm bf-btn-light" for="lvl{{ $level }}">
                            <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $levelColors[$level] ?? '#6B7280' }};"></span>{{ $level }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button class="btn bf-btn-primary" type="submit" @disabled(! $aiReady['openrouter'])>
                    <i class="bi bi-stars"></i> <span data-label>Générer</span>
                </button>
            </div>
            <div class="col-12" id="aiManual" @if ($defaultSource !== 'manual') hidden @endif>
                <label class="form-label" for="manualMatches">Matchs du jour</label>
                <textarea class="form-control font-monospace" id="manualMatches" name="manual_matches" rows="4" style="font-size:.8rem;" placeholder="Arsenal vs Chelsea | Premier League | 18:30&#10;Enyimba vs Rivers United | Ligue CAF | 16:00">{{ old('manual_matches') }}</textarea>
                <div class="form-text">Un match par ligne : <strong>Équipe A vs Équipe B | Compétition | Heure</strong> (compétition et heure facultatives).</div>
            </div>
        </div>
        <p class="bf-small bf-muted mt-3 mb-0">
            <i class="bi bi-info-circle"></i> Chaque sélection est chiffrée à partir des vraies données (classement, forme, H2H) et de la moyenne des cotes de plusieurs bookmakers, puis gardée seulement si sa probabilité atteint {{ (int) \App\Models\AppSetting::value('ai_min_probability_free', 75) }} % (gratuits) ou {{ (int) \App\Models\AppSetting::value('ai_min_probability_vip', 80) }} % (VIP). Cote totale d’au moins 2,00 (2,10 en Silver), coupons courts, une victoire sèche au plus et seulement pour un favori très net, jamais de nul sec, ni corners ni cartons. Un match d’un coupon gratuit n’est jamais repris dans un VIP (et inversement). La cote double chance est calculée depuis les cotes 1N2 : vérifiez-la sur 1xBet ou Melbet et ajoutez le vrai code du coupon avant de publier.
        </p>
    </form>

    @php
        // État de chaque créneau : [classe du badge, icône, libellé].
        $slotStyles = [
            'published' => ['success', 'bi-check-circle-fill', 'Publié'],
            'empty' => ['pending', 'bi-dash-circle', 'Rien de fiable'],
            'error' => ['danger', 'bi-x-octagon-fill', 'Erreur'],
            'running' => ['processing', 'bi-hourglass-split', 'En cours'],
            'due' => ['processing', 'bi-hourglass-split', 'Imminent'],
            'upcoming' => ['neutral', 'bi-clock', 'À venir'],
            'missed' => ['danger', 'bi-moon', 'Non lancé'],
        ];
        $published = collect($slots)->where('status', 'published')->count();
        $planned = collect($slots)->where('manual', false)->count();
    @endphp
    <div class="bf-card-body pt-0">
        <div class="p-3 rounded-3" style="background:var(--bf-surface-2);border:1px solid var(--bf-border);">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <strong><i class="bi bi-calendar2-week"></i> Créneaux du jour</strong>
                <span class="bf-badge neutral">{{ $published }} publié{{ $published > 1 ? 's' : '' }} sur {{ $planned }} prévu{{ $planned > 1 ? 's' : '' }}</span>
                @if ($oddsUsage['remaining'] !== null)
                    <span class="bf-badge {{ $oddsUsage['remaining'] < 60 ? 'danger' : 'neutral' }}">
                        Cotes : offre {{ $oddsUsage['extended'] ? 'payante' : 'gratuite' }} · {{ $oddsUsage['remaining'] }} crédits restants · aujourd’hui {{ $oddsUsage['spent_today'] }}/{{ $oddsUsage['allowance'] }}
                    </span>
                @endif
                <a href="/admin/settings#ia" class="ms-auto bf-small"><i class="bi bi-sliders"></i> Heures et seuils</a>
            </div>
            @if ($oddsUsage['error'])
                <div class="bf-small mb-2" style="color:#B91C1C;"><i class="bi bi-exclamation-triangle"></i> {{ $oddsUsage['error'] }}</div>
            @endif
            @if ($slots === [])
                <div class="bf-small bf-muted">Aucun créneau : toutes les heures de publication sont vides dans Admin > Paramètres.</div>
            @else
                {{-- Sur téléphone, le détail passe sous l'heure, le groupe et l'état. --}}
                <div class="bf-small">
                    @foreach ($slots as $slot)
                        @php [$badge, $icon, $label] = $slotStyles[$slot['status']] ?? $slotStyles['upcoming']; @endphp
                        <div class="row g-1 align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="col-auto tabular fw-semibold" style="width:52px;">{{ $slot['time'] }}</div>
                            <div class="col-auto" style="width:118px;">
                                <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $levelColors[$slot['level']] ?? '#6B7280' }};"></span>{{ \App\Support\PronosticRules::isFree($slot['level']) ? '' : 'VIP ' }}{{ $slot['level'] }}
                            </div>
                            <div class="col-auto" style="min-width:140px;"><span class="bf-badge {{ $badge }}"><i class="bi {{ $icon }}"></i> {{ $label }}{{ $slot['manual'] ? ' (manuel)' : '' }}</span></div>
                            @if ($slot['status'] === 'missed' || $slot['message'])
                                <div class="col-12 col-lg bf-muted" title="{{ $slot['message'] }}">
                                    @if ($slot['status'] === 'missed')
                                        Le serveur n’a pas lancé ce créneau (redémarrage ou mise en veille) : relancez-le avec « Générer et publier maintenant ».
                                    @else
                                        {{ \Illuminate\Support\Str::limit((string) $slot['message'], 220) }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($aiReady['football'])
        <div class="bf-card-body pt-0">
            <div class="d-flex flex-wrap align-items-center gap-2 p-3 rounded-3" style="background:#FFF7E6;border:1px solid #F3D27A;">
                <div class="flex-grow-1">
                    <strong><i class="bi bi-lightning-charge-fill" style="color:#B45309;"></i> Automatisation (comme sur le serveur)</strong>
                    <div class="bf-small bf-muted">
                        <strong>Générer et publier</strong> fait comme un créneau automatique : coupon publié et notifié, sans relecture (VIP : abonnés seulement).
                        <strong>Vérifier les résultats</strong> fait comme chaque heure : valide les combinés terminés et notifie les coupons gagnants.
                    </div>
                </div>
                @if ($aiReady['openrouter'])
                    <form method="POST" action="/admin/pronostics/generate-publish" class="d-flex gap-2" data-confirm="Générer, publier directement et notifier maintenant ?">
                        @csrf
                        <select class="form-select form-select-sm" name="level" aria-label="Groupe à générer" style="width:auto;">
                            @foreach ($levelNames as $level)
                                <option value="{{ $level }}">{{ \App\Support\PronosticRules::isFree($level) ? '' : 'VIP ' }}{{ $level }}</option>
                            @endforeach
                        </select>
                        <button class="btn bf-btn-gold" type="submit">
                            <i class="bi bi-lightning-charge-fill"></i> Générer et publier maintenant
                        </button>
                    </form>
                @endif
                <form method="POST" action="/admin/pronostics/settle" data-confirm="Vérifier les résultats des matchs et notifier les coupons gagnants maintenant ?">
                    @csrf
                    <button class="btn bf-btn-light" type="submit">
                        <i class="bi bi-trophy"></i> Vérifier les résultats maintenant
                    </button>
                </form>
            </div>
        </div>
    @endif
</section>

@if ($aiDrafts)
    <section class="bf-card mb-3" style="border-color:#D8C8FB;">
        <div class="bf-card-header" style="background:#FAF7FF;">
            <div>
                <h2 class="bf-card-title"><i class="bi bi-stars" style="color:#6D28D9;"></i> Propositions de l’IA à vérifier ({{ count($aiDrafts) }})</h2>
                <p class="bf-card-sub">Corrigez si besoin, ajoutez les codes coupon, puis publiez : une notification part à tous les utilisateurs.</p>
            </div>
        </div>
        <div class="bf-card-body vstack gap-3">
            @foreach ($aiDrafts as $index => $draft)
                @php
                    $color = $levelColors[$draft['niveau']] ?? '#6B7280';
                    $eventsText = collect($draft['evenements'])->map(fn ($event) => $event['match'] . ' | ' . $event['pronostic'] . ' | ' . number_format($event['cote'], 2, '.', ''))->implode("\n");
                @endphp
                <div class="p-3 rounded-3" style="border:1px solid var(--bf-border);border-left:4px solid {{ $color }};">
                    <form method="POST" action="/admin/pronostics" class="row g-2" data-confirm="Publier ce combiné {{ $draft['niveau'] }} et notifier tous les utilisateurs ?">
                        @csrf
                        <input type="hidden" name="draft" value="{{ $index }}">
                        <input type="hidden" name="match_date" value="{{ $draft['match_date'] }}">
                        <div class="col-12 d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="bf-badge" style="background: {{ $color }}1A; color:#111A2E;"><span class="dot" style="color: {{ $color }};"></span>{{ $draft['niveau'] }}</span>
                            <input type="hidden" name="niveau" value="{{ $draft['niveau'] }}">
                            <span class="bf-small bf-muted">matchs du {{ \Illuminate\Support\Carbon::parse($draft['match_date'])->format('d/m/Y') }}</span>
                        </div>
                        <div class="col-12 col-lg-7">
                            <label class="form-label">Événements <span class="bf-muted fw-normal">(Match | Pronostic | Cote)</span></label>
                            <textarea class="form-control font-monospace" name="evenements_text" rows="{{ max(2, count($draft['evenements'])) }}" style="font-size:.78rem;" data-events required>{{ $eventsText }}</textarea>
                            <label class="form-label mt-2">Analyse</label>
                            <textarea class="form-control" name="analysis" rows="2" style="font-size:.85rem;">{{ $draft['analysis'] }}</textarea>
                        </div>
                        <div class="col-12 col-lg-5">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Cote totale</label>
                                    <input class="form-control tabular" type="number" step="0.01" min="1" name="cote_totale" value="{{ number_format($draft['cote_totale'], 2, '.', '') }}" data-total required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Probabilité (%)</label>
                                    <input class="form-control tabular" type="number" min="0" max="100" name="probabilite" value="{{ $draft['probabilite'] }}" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Code 1xBet</label>
                                    <input class="form-control" name="code_1xbet" placeholder="Code réel du coupon">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Code Melbet</label>
                                    <input class="form-control" name="code_melbet" placeholder="Code réel du coupon">
                                </div>
                                <div class="col-12 form-check form-switch ms-1 mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_vip" value="1" id="draftVip{{ $index }}" @checked($draft['niveau'] !== 'Starter')>
                                    <label class="form-check-label" for="draftVip{{ $index }}">Réservé aux membres VIP</label>
                                </div>
                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button class="btn bf-btn-gold flex-grow-1" type="submit"><i class="bi bi-send-fill"></i> Publier</button>
                                    <button class="btn bf-btn-light" type="submit" form="dismissDraft{{ $index }}" title="Ignorer cette proposition"><i class="bi bi-x-lg"></i> Ignorer</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <form method="POST" action="/admin/pronostics/drafts/{{ $index }}/dismiss" id="dismissDraft{{ $index }}" hidden>
                        @csrf
                    </form>
                </div>
            @endforeach
        </div>
    </section>
@endif

@push('scripts')
    <script>
        (function () {
            const source = document.getElementById('aiSource');
            const manual = document.getElementById('aiManual');
            const form = document.getElementById('aiForm');

            source?.addEventListener('change', function () {
                manual.hidden = source.value !== 'manual';
            });

            form?.addEventListener('submit', function () {
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.querySelector('[data-label]').textContent = 'Génération… (jusqu’à 1 min)';
            });

            // Brouillons : la cote totale suit les cotes des événements modifiées à la main.
            document.querySelectorAll('[data-events]').forEach(function (field) {
                field.addEventListener('input', function () {
                    const total = field.closest('form').querySelector('[data-total]');
                    let product = 1;
                    let valid = 0;

                    field.value.split(/\r?\n/).forEach(function (line) {
                        const parts = line.split('|').map((part) => part.trim());
                        const odd = parseFloat((parts[2] || '').replace(',', '.'));

                        if (parts.length === 3 && parts[0] && parts[1] && odd > 0) {
                            valid++;
                            product *= odd;
                        }
                    });

                    if (valid) {
                        total.value = product.toFixed(2);
                    }
                });
            });
        })();
    </script>
@endpush
