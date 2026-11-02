@extends('admin.layout')

@section('title', 'Tableau de bord')
@section('subtitle', ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) . ' · vue d’ensemble de l’activité')

@section('actions')
    <a href="/admin/pronostics" class="btn bf-btn-light"><i class="bi bi-plus-lg"></i> <span class="btn-label">Pronostic</span></a>
    <a href="/admin/transactions?status=pending" class="btn bf-btn-primary"><i class="bi bi-lightning-charge-fill"></i> <span class="btn-label">Traiter les demandes</span></a>
@endsection

@php
    $fcfa = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $delta = function ($current, $previous) {
        if ((float) $previous === 0.0) {
            return $current > 0 ? ['up', 'bi-arrow-up-short', 'nouveau'] : ['flat', 'bi-dash', 'stable'];
        }

        $percent = (int) round(($current - $previous) * 100 / $previous);

        if ($percent === 0) {
            return ['flat', 'bi-dash', '0 %'];
        }

        return $percent > 0
            ? ['up', 'bi-arrow-up-short', '+' . $percent . ' %']
            : ['down', 'bi-arrow-down-short', $percent . ' %'];
    };
    $txDelta = $delta($stats['transactions_today'], $stats['transactions_yesterday']);
    $volumeDelta = $delta($stats['volume_today'], $stats['volume_yesterday']);
    $hasChartData = array_sum($chart['recharge']) + array_sum($chart['retrait']) > 0;
@endphp

@section('content')
    @foreach ($warnings as $warning)
        <div class="bf-flash {{ $warning['level'] === 'danger' ? 'error' : 'warning' }}">
            <i class="bi {{ $warning['level'] === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill' }}"></i>
            <div class="flex-grow-1">{{ $warning['message'] }}</div>
            @if ($warning['link'])
                <a href="{{ $warning['link'] }}" class="btn btn-sm bf-btn-light flex-shrink-0">Corriger</a>
            @endif
        </div>
    @endforeach

    {{-- Indicateurs clés --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/admin/transactions?status=pending" class="bf-card bf-kpi d-block {{ $stats['pending_requests'] > 0 ? 'is-alert' : '' }}">
                <div class="bf-kpi-top">
                    <span class="bf-kpi-label">Demandes à traiter</span>
                    <span class="bf-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="bf-kpi-value tabular">{{ $stats['pending_requests'] }}</div>
                <div class="bf-kpi-foot">
                    @if ($stats['pending_requests'] > 0)
                        <span class="fw-semibold text-body">{{ $fcfa($stats['pending_amount']) }} FCFA</span> en attente de validation
                    @else
                        <i class="bi bi-check2-circle text-success"></i> Tout est traité
                    @endif
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top">
                    <span class="bf-kpi-label">Transactions aujourd’hui</span>
                    <span class="bf-kpi-icon"><i class="bi bi-arrow-left-right"></i></span>
                </div>
                <div class="bf-kpi-value tabular">{{ $stats['transactions_today'] }}</div>
                <div class="bf-kpi-foot">
                    <span class="bf-delta {{ $txDelta[0] }}"><i class="bi {{ $txDelta[1] }}"></i>{{ $txDelta[2] }}</span>
                    vs hier · {{ $stats['recharges_today'] }} recharges, {{ $stats['retraits_today'] }} retraits
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top">
                    <span class="bf-kpi-label">Volume validé aujourd’hui</span>
                    <span class="bf-kpi-icon"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="bf-kpi-value tabular">{{ $fcfa($stats['volume_today']) }} <small>FCFA</small></div>
                <div class="bf-kpi-foot">
                    <span class="bf-delta {{ $volumeDelta[0] }}"><i class="bi {{ $volumeDelta[1] }}"></i>{{ $volumeDelta[2] }}</span>
                    vs hier ({{ $fcfa($stats['volume_yesterday']) }} FCFA)
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/admin/users" class="bf-card bf-kpi d-block">
                <div class="bf-kpi-top">
                    <span class="bf-kpi-label">Utilisateurs inscrits</span>
                    <span class="bf-kpi-icon"><i class="bi bi-people"></i></span>
                </div>
                <div class="bf-kpi-value tabular">{{ $fcfa($stats['users_total']) }}</div>
                <div class="bf-kpi-foot">
                    <span class="bf-delta {{ $stats['users_week'] > 0 ? 'up' : 'flat' }}"><i class="bi {{ $stats['users_week'] > 0 ? 'bi-arrow-up-short' : 'bi-dash' }}"></i>{{ $stats['users_week'] }}</span>
                    nouveaux sur 7 jours
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Graphique 7 jours --}}
        <div class="col-12 col-xl-8">
            <div class="bf-card h-100">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">Volume des 7 derniers jours</h2>
                        <p class="bf-card-sub">Montants demandés en FCFA, hors transactions refusées</p>
                    </div>
                    <div class="d-flex gap-3 bf-small fw-semibold text-body" aria-hidden="true">
                        <span class="d-inline-flex align-items-center gap-2"><span style="width:14px;height:3px;border-radius:2px;background:#2a78d6;"></span>Recharges</span>
                        <span class="d-inline-flex align-items-center gap-2"><span style="width:14px;height:3px;border-radius:2px;background:#eb6834;"></span>Retraits</span>
                    </div>
                </div>
                <div class="bf-card-body">
                    @if ($hasChartData)
                        <div style="position:relative;height:280px;">
                            <canvas id="weeklyChart" role="img" aria-label="Volume des recharges et retraits sur les 7 derniers jours"></canvas>
                        </div>
                    @else
                        <div class="bf-empty d-flex flex-column justify-content-center" style="height:280px;">
                            <i class="bi bi-bar-chart"></i>
                            Aucune transaction sur les 7 derniers jours
                        </div>
                    @endif
                    <details class="mt-3">
                        <summary class="bf-small bf-muted fw-semibold">Voir les données en tableau</summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm bf-small tabular mb-0">
                                <thead>
                                    <tr><th>Jour</th><th class="text-end">Recharges</th><th class="text-end">Retraits</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($chart['labels'] as $index => $label)
                                        <tr>
                                            <td>{{ $label }}</td>
                                            <td class="text-end">{{ $fcfa($chart['recharge'][$index]) }} FCFA ({{ $chart['rechargeCount'][$index] }})</td>
                                            <td class="text-end">{{ $fcfa($chart['retrait'][$index]) }} FCFA ({{ $chart['retraitCount'][$index] }})</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        {{-- File d'attente --}}
        <div class="col-12 col-xl-4">
            <div class="bf-card h-100 d-flex flex-column">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">File d’attente</h2>
                        <p class="bf-card-sub">Les plus anciennes en premier</p>
                    </div>
                    <a href="/admin/transactions?status=pending" class="btn btn-sm bf-btn-light">Tout voir</a>
                </div>
                <div class="flex-grow-1">
                    @forelse ($pendingQueue as $transaction)
                        <a href="/admin/transactions?search={{ $transaction->id }}" class="d-flex align-items-center gap-3 px-3 py-3 border-bottom text-body">
                            @include('admin.partials.avatar', ['name' => $transaction->user?->name, 'id' => $transaction->user_id])
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $transaction->user?->name ?? 'Utilisateur supprimé' }}</div>
                                <div class="bf-small bf-muted">
                                    {{ $transaction->type === 'recharge' ? 'Recharge' : 'Retrait' }} {{ $transaction->platform === '1xbet' ? '1xBet' : 'Melbet' }}
                                    · {{ $transaction->created_at->locale('fr')->diffForHumans() }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold tabular">{{ $fcfa($transaction->amount) }}</div>
                                @include('admin.partials.status', ['status' => $transaction->status])
                            </div>
                        </a>
                    @empty
                        <div class="bf-empty">
                            <i class="bi bi-inbox"></i>
                            Aucune demande en attente
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Dernières transactions --}}
        <div class="col-12 col-xl-8">
            <div class="bf-card">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">Dernières transactions</h2>
                        <p class="bf-card-sub">Les 8 demandes les plus récentes</p>
                    </div>
                    <a href="/admin/transactions" class="btn btn-sm bf-btn-light">Historique complet</a>
                </div>
                <div class="table-responsive">
                    <table class="table bf-table align-middle">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Type</th>
                                <th class="text-end">Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latestTransactions as $transaction)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @include('admin.partials.avatar', ['name' => $transaction->user?->name, 'id' => $transaction->user_id])
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-truncate">{{ $transaction->user?->name ?? 'Utilisateur supprimé' }}</div>
                                                <div class="bf-small bf-muted">#{{ $transaction->id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>@include('admin.partials.tx-type', ['transaction' => $transaction])</td>
                                    <td class="text-end fw-bold tabular text-nowrap">{{ $fcfa($transaction->amount) }} FCFA</td>
                                    <td>@include('admin.partials.status', ['status' => $transaction->status])</td>
                                    <td class="bf-muted text-nowrap">{{ $transaction->created_at->format('d/m H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"><div class="bf-empty"><i class="bi bi-receipt"></i>Aucune transaction pour l’instant</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 d-flex flex-column gap-3">
            {{-- Pronostics --}}
            <div class="bf-card">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">Pronostics</h2>
                        <p class="bf-card-sub">
                            @if ($pronosticStats['last'])
                                Dernier publié {{ optional($pronosticStats['last']->published_at)->locale('fr')->diffForHumans() }}
                            @else
                                Aucun pronostic publié
                            @endif
                        </p>
                    </div>
                    <a href="/admin/pronostics" class="btn btn-sm bf-btn-light">Gérer</a>
                </div>
                <div class="bf-card-body">
                    <div class="d-flex align-items-end justify-content-between mb-2">
                        <div>
                            <div class="bf-kpi-label">Taux de réussite</div>
                            <div class="bf-kpi-value tabular">{{ $pronosticStats['win_rate'] !== null ? $pronosticStats['win_rate'] . ' %' : '—' }}</div>
                        </div>
                        <div class="text-end bf-small bf-muted">
                            {{ $pronosticStats['won'] }} gagnés sur {{ $pronosticStats['settled'] }} clôturés
                        </div>
                    </div>
                    <div class="progress mb-3" style="height:8px;background:#E6F0FB;" role="progressbar" aria-label="Taux de réussite" aria-valuenow="{{ $pronosticStats['win_rate'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $pronosticStats['win_rate'] ?? 0 }}%;background:#2a78d6;border-radius:4px;"></div>
                    </div>
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-2 rounded-3" style="background:var(--bf-surface-2);">
                                <div class="fw-bold fs-5 tabular">{{ $pronosticStats['today'] }}</div>
                                <div class="bf-small bf-muted">publiés aujourd’hui</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <a href="/admin/pronostics" class="d-block p-2 rounded-3 text-body" style="background:var(--bf-surface-2);">
                                <div class="fw-bold fs-5 tabular">{{ $pronosticStats['awaiting'] }}</div>
                                <div class="bf-small bf-muted">résultats à saisir</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Messages non lus --}}
            <div class="bf-card flex-grow-1">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">Messages non lus</h2>
                        <p class="bf-card-sub">Support utilisateurs</p>
                    </div>
                    <a href="/admin/messages" class="btn btn-sm bf-btn-light">Ouvrir</a>
                </div>
                @forelse ($unreadConversations as $message)
                    <a href="/admin/messages?user_id={{ $message->user_id }}" class="d-flex align-items-start gap-3 px-3 py-3 border-bottom text-body">
                        @include('admin.partials.avatar', ['name' => $message->user?->name, 'id' => $message->user_id])
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold text-truncate">{{ $message->user?->name ?? 'Utilisateur supprimé' }}</span>
                                <span class="bf-small bf-muted text-nowrap">{{ $message->created_at->format('H:i') }}</span>
                            </div>
                            <div class="bf-small bf-muted text-truncate">{{ $message->content }}</div>
                        </div>
                    </a>
                @empty
                    <div class="bf-empty">
                        <i class="bi bi-chat-square-heart"></i>
                        Aucun message en attente de réponse
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@if ($hasChartData)
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const data = @json($chart);
            const canvas = document.getElementById('weeklyChart');

            if (! canvas || typeof Chart === 'undefined') {
                return;
            }

            const money = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
            const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
            const bar = {
                borderRadius: { topLeft: 4, topRight: 4 },
                borderSkipped: 'start',
                maxBarThickness: 22,
                categoryPercentage: 0.62,
                barPercentage: 0.86,
            };

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { ...bar, label: 'Recharges', data: data.recharge, counts: data.rechargeCount, backgroundColor: '#2a78d6', hoverBackgroundColor: '#256abf' },
                        { ...bar, label: 'Retraits', data: data.retrait, counts: data.retraitCount, backgroundColor: '#eb6834', hoverBackgroundColor: '#d95926' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#FFFFFF',
                            titleColor: '#111A2E',
                            bodyColor: '#344054',
                            borderColor: '#E4E7EF',
                            borderWidth: 1,
                            padding: 12,
                            boxWidth: 12,
                            boxHeight: 3,
                            titleFont: { family: 'Inter', weight: '700' },
                            bodyFont: { family: 'Inter' },
                            callbacks: {
                                label(context) {
                                    const count = context.dataset.counts[context.dataIndex];
                                    return ' ' + money.format(context.parsed.y) + ' FCFA  ' + context.dataset.label + ' (' + count + ')';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { color: '#E4E7EF' },
                            ticks: { color: '#667085', font: { family: 'Inter', size: 12 } },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#EEF1F6' },
                            border: { display: false },
                            ticks: {
                                color: '#667085',
                                font: { family: 'Inter', size: 12 },
                                maxTicksLimit: 6,
                                callback: (value) => compact.format(value),
                            },
                        },
                    },
                },
            });
        })();
    </script>
@endpush
@endif
