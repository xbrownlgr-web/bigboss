@extends('admin.layout')

@section('title', 'Pronostics')
@section('subtitle', 'Combinés publiés dans l’application · une notification part à chaque publication')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Publiés au total</span><span class="bf-kpi-icon"><i class="bi bi-collection"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $stats['total'] }}</div>
                <div class="bf-kpi-foot">dont {{ $stats['vip'] }} VIP</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Matchs du jour</span><span class="bf-kpi-icon"><i class="bi bi-calendar-event"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $stats['today'] }}</div>
                <div class="bf-kpi-foot">
                    @forelse ($stats['levels'] as $level => $total)
                        <span class="bf-badge neutral"><span class="dot" style="color: {{ $levelColors[$level] ?? '#6B7280' }};"></span>{{ $level }} {{ $total }}</span>
                    @empty
                        Aucun niveau
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Taux de réussite</span><span class="bf-kpi-icon"><i class="bi bi-trophy"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $stats['win_rate'] !== null ? $stats['win_rate'] . ' %' : '—' }}</div>
                <div class="bf-kpi-foot">{{ $stats['won'] }} gagnés sur {{ $stats['settled'] }} clôturés</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi {{ $stats['awaiting'] > 0 ? 'is-alert' : '' }}">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Résultats à saisir</span><span class="bf-kpi-icon"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $stats['awaiting'] }}</div>
                <div class="bf-kpi-foot">marquez-les gagnés ou perdus</div>
            </div>
        </div>
    </div>

    @include('admin.partials.ai-generator')

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="bf-card" style="position:sticky;top:96px;">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">Nouveau combiné</h2>
                        <p class="bf-card-sub">Publié immédiatement + notification à tous</p>
                    </div>
                </div>
                <form method="POST" action="/admin/pronostics" class="bf-card-body vstack gap-3" id="pronosticForm" data-confirm="Publier ce combiné et notifier tous les utilisateurs ?">
                    @csrf
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="niveau">Niveau</label>
                            <select class="form-select" id="niveau" name="niveau" required>
                                @foreach ($levels as $level)
                                    <option value="{{ $level }}" @selected(old('niveau') === $level)>{{ $level }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="match_date">Date des matchs</label>
                            <input class="form-control" type="date" id="match_date" name="match_date" value="{{ old('match_date', now()->toDateString()) }}" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="evenements_text">Événements</label>
                        <textarea class="form-control font-monospace" id="evenements_text" name="evenements_text" rows="6" required style="font-size:.8rem;" placeholder="Arsenal vs Chelsea | Victoire Arsenal | 1.45&#10;PSG vs Lyon | Plus de 1,5 but | 1.45">{{ old('evenements_text') }}</textarea>
                        <div class="form-text" id="eventsHelp">Une ligne par événement : <strong>Match | Pronostic | Cote</strong></div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="cote_totale">Cote totale</label>
                            <input class="form-control tabular" type="number" step="0.01" min="1" id="cote_totale" name="cote_totale" value="{{ old('cote_totale') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="probabilite">Probabilité (%)</label>
                            <input class="form-control tabular" type="number" min="0" max="100" id="probabilite" name="probabilite" value="{{ old('probabilite') }}" required>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="code_1xbet">Code 1xBet</label>
                            <input class="form-control" id="code_1xbet" name="code_1xbet" value="{{ old('code_1xbet') }}" placeholder="Code réel (facultatif)">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="code_melbet">Code Melbet</label>
                            <input class="form-control" id="code_melbet" name="code_melbet" value="{{ old('code_melbet') }}" placeholder="Code réel (facultatif)">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="analysis">Analyse courte</label>
                        <textarea class="form-control" id="analysis" name="analysis" rows="2" placeholder="Optionnel">{{ old('analysis') }}</textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_vip" value="1" id="isVip" @checked(old('is_vip'))>
                        <label class="form-check-label" for="isVip">Réservé aux membres VIP</label>
                    </div>

                    <button class="btn bf-btn-gold" type="submit"><i class="bi bi-send-fill"></i> Publier le combiné</button>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <form class="bf-card bf-card-body row g-2 align-items-end mb-3" method="GET" action="/admin/pronostics">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="filterLevel">Niveau</label>
                    <select class="form-select" id="filterLevel" name="niveau">
                        <option value="">Tous les niveaux</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level }}" @selected($selectedLevel === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="filterDate">Date des matchs</label>
                    <input class="form-control" type="date" id="filterDate" name="date" value="{{ $selectedDate }}">
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn bf-btn-primary flex-grow-1" type="submit"><i class="bi bi-funnel"></i> Filtrer</button>
                    <a class="btn bf-btn-light" href="/admin/pronostics" title="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <div class="vstack gap-3">
                @forelse ($pronostics as $pronostic)
                    @php
                        $color = $pronostic->couleur ?? '#6B7280';
                        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2, ',', ' ');
                    @endphp
                    <article class="bf-card" style="border-left: 4px solid {{ $color }};">
                        <div class="bf-card-header flex-wrap">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="bf-badge" style="background: {{ $color }}1A; color: #111A2E;"><span class="dot" style="color: {{ $color }};"></span>{{ $pronostic->niveau ?? 'Simple' }}</span>
                                @if ($pronostic->is_vip)
                                    <span class="bf-badge vip"><i class="bi bi-star-fill"></i>VIP</span>
                                @endif
                                <span class="bf-small bf-muted">#{{ $pronostic->id }} · matchs du {{ $pronostic->match_date?->format('d/m/Y') }} · publié {{ $pronostic->published_at?->format('d/m H:i') }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="bf-small bf-muted">{{ $pronostic->probabilite ?? $pronostic->confidence }} %</span>
                                <span class="bf-badge" style="background:#FFD700;color:#0E1A36;font-size:.85rem;">Cote {{ $odd }}</span>
                            </div>
                        </div>
                        <div class="bf-card-body">
                            <div class="row g-3">
                                <div class="col-12 col-lg-7">
                                    @if ($pronostic->evenements)
                                        <ul class="list-unstyled mb-0 vstack gap-2">
                                            @foreach ($pronostic->evenements as $event)
                                                <li class="d-flex justify-content-between gap-3">
                                                    <span>
                                                        <span class="fw-semibold">{{ $event['match'] ?? '' }}</span>
                                                        <span class="d-block bf-small bf-muted">{{ $event['pronostic'] ?? '' }}</span>
                                                    </span>
                                                    <span class="fw-semibold tabular">{{ number_format((float) ($event['cote'] ?? 0), 2, ',', ' ') }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="fw-semibold">{{ $pronostic->team_home }} vs {{ $pronostic->team_away }}</div>
                                        <div class="bf-small bf-muted">{{ $pronostic->prediction }}</div>
                                    @endif
                                    <div class="d-flex flex-wrap gap-3 mt-3 pt-3 border-top bf-small">
                                        <span>1xBet <strong>{{ $pronostic->code_1xbet ?? '—' }}</strong>
                                            @if ($pronostic->code_1xbet)<button type="button" class="bf-copy" data-copy="{{ $pronostic->code_1xbet }}" title="Copier"><i class="bi bi-copy"></i></button>@endif
                                        </span>
                                        <span>Melbet <strong>{{ $pronostic->code_melbet ?? '—' }}</strong>
                                            @if ($pronostic->code_melbet)<button type="button" class="bf-copy" data-copy="{{ $pronostic->code_melbet }}" title="Copier"><i class="bi bi-copy"></i></button>@endif
                                        </span>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-5">
                                    <div class="p-3 rounded-3 h-100 d-flex flex-column" style="background:var(--bf-surface-2);">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="bf-kpi-label">Résultat</span>
                                            @if ($pronostic->result_status === 'won')
                                                <span class="bf-badge success"><i class="bi bi-trophy-fill"></i>Gagné</span>
                                            @elseif ($pronostic->result_status === 'lost')
                                                <span class="bf-badge danger"><i class="bi bi-x-circle-fill"></i>Perdu</span>
                                            @else
                                                <span class="bf-badge pending"><span class="dot"></span>En attente</span>
                                            @endif
                                        </div>
                                        @if ($pronostic->result_note)
                                            <div class="bf-small bf-muted mb-2">{{ $pronostic->result_note }}</div>
                                        @endif
                                        @if ($pronostic->result_status === 'won' && $pronostic->result_notified_at)
                                            <div class="bf-small bf-muted mb-2"><i class="bi bi-bell"></i> Notification envoyée le {{ $pronostic->result_notified_at->format('d/m H:i') }}</div>
                                        @endif

                                        @unless ($pronostic->result_status)
                                            <form method="POST" action="/admin/pronostics/{{ $pronostic->id }}/result" class="vstack gap-2 mt-auto">
                                                @csrf
                                                <input class="form-control form-control-sm" name="result_note" placeholder="Note (optionnel)" aria-label="Note de résultat">
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm bf-btn-success flex-fill" type="submit" name="result_status" value="won" title="Notifie tous les utilisateurs"><i class="bi bi-trophy"></i> Gagné</button>
                                                    <button class="btn btn-sm bf-btn-danger-soft flex-fill" type="submit" name="result_status" value="lost" title="Aucune notification"><i class="bi bi-x-lg"></i> Perdu</button>
                                                </div>
                                            </form>
                                        @endunless

                                        <form method="POST" action="/admin/pronostics/{{ $pronostic->id }}/delete" class="mt-2 text-end" data-confirm="Supprimer définitivement ce pronostic ?">
                                            @csrf
                                            <button class="btn btn-sm btn-link text-danger p-0 bf-small" type="submit"><i class="bi bi-trash"></i> Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="bf-card"><div class="bf-empty"><i class="bi bi-graph-up-arrow"></i>Aucun pronostic trouvé</div></div>
                @endforelse
            </div>

            @if ($pronostics->hasPages())
                <div class="bf-card bf-pagination mt-3">{{ $pronostics->links('admin.partials.pagination') }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Calcule la cote totale à partir des lignes « Match | Pronostic | Cote ».
        (function () {
            const events = document.getElementById('evenements_text');
            const total = document.getElementById('cote_totale');
            const help = document.getElementById('eventsHelp');
            let touched = total.value !== '';

            total.addEventListener('input', function () { touched = true; });

            const update = function () {
                const lines = events.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
                let valid = 0;
                let product = 1;

                lines.forEach(function (line) {
                    const parts = line.split('|').map((part) => part.trim());
                    const odd = parseFloat((parts[2] || '').replace(',', '.'));

                    if (parts.length === 3 && parts[0] && parts[1] && odd > 0) {
                        valid++;
                        product *= odd;
                    }
                });

                if (! lines.length) {
                    help.innerHTML = 'Une ligne par événement : <strong>Match | Pronostic | Cote</strong>';
                    return;
                }

                const invalid = lines.length - valid;
                help.textContent = valid + ' événement(s) valide(s)' + (invalid ? ' · ' + invalid + ' ligne(s) ignorée(s), format attendu : Match | Pronostic | Cote' : '') + (valid ? ' · cote combinée ' + product.toFixed(2) : '');

                if (valid && ! touched) {
                    total.value = product.toFixed(2);
                }
            };

            events.addEventListener('input', update);
            update();
        })();
    </script>
@endpush
