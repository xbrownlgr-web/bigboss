@extends('admin.layout')

@section('title', $user->name)
@section('subtitle', 'Fiche utilisateur · inscrit le ' . $user->created_at->locale('fr')->isoFormat('D MMMM YYYY'))

@section('actions')
    <a href="/admin/users" class="btn bf-btn-light"><i class="bi bi-arrow-left"></i> <span class="btn-label">Utilisateurs</span></a>
    <a href="/admin/messages?user_id={{ $user->id }}" class="btn bf-btn-primary"><i class="bi bi-chat-dots"></i> <span class="btn-label">Message</span></a>
@endsection

@php
    $fcfa = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $isMainAdmin = $user->email === 'admin@binhoflash.com';
    $recharges = $totals->get('recharge');
    $retraits = $totals->get('retrait');
@endphp

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4">
            <div class="bf-card h-100">
                <div class="bf-card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        @include('admin.partials.avatar', ['name' => $user->name, 'id' => $user->id, 'size' => 'lg'])
                        <div class="min-w-0">
                            <div class="fw-bold fs-5 text-truncate">{{ $user->name }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @if ($user->is_active)
                                    <span class="bf-badge success"><span class="dot"></span>Actif</span>
                                @else
                                    <span class="bf-badge danger"><span class="dot"></span>Bloqué</span>
                                @endif
                                @if ($user->is_vip)
                                    <span class="bf-badge vip"><i class="bi bi-star-fill"></i>VIP</span>
                                @else
                                    <span class="bf-badge neutral">Standard</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <dl class="bf-kv mb-3" style="font-size:.85rem;row-gap:8px;">
                        <dt>E-mail</dt>
                        <dd>{{ $user->email }} <button type="button" class="bf-copy" data-copy="{{ $user->email }}" title="Copier"><i class="bi bi-copy"></i></button></dd>
                        <dt>Téléphone</dt>
                        <dd>
                            {{ $user->phone ?: '—' }}
                            @if ($user->phone)
                                <button type="button" class="bf-copy" data-copy="{{ $user->phone }}" title="Copier"><i class="bi bi-copy"></i></button>
                            @endif
                        </dd>
                        <dt>Pays</dt>
                        <dd>{{ $user->country_label }}</dd>
                        <dt>Notifications</dt>
                        <dd>{{ $user->fcm_token ? 'Activées' : 'Non activées' }}</dd>
                    </dl>

                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="/admin/users/{{ $user->id }}/vip">
                            @csrf
                            <button class="btn btn-sm bf-btn-gold" type="submit">
                                <i class="bi bi-star-fill"></i> {{ $user->is_vip ? 'Retirer VIP' : 'Passer VIP' }}
                            </button>
                        </form>
                        <form method="POST" action="/admin/users/{{ $user->id }}/test-push">
                            @csrf
                            <button class="btn btn-sm bf-btn-light" type="submit" @disabled(! $user->fcm_token)><i class="bi bi-bell"></i> Test push</button>
                        </form>
                        @unless ($isMainAdmin)
                            <form method="POST" action="/admin/users/{{ $user->id }}/active" data-confirm="{{ $user->is_active ? 'Bloquer ce compte ? L’utilisateur sera déconnecté.' : 'Réactiver ce compte ?' }}">
                                @csrf
                                <button class="btn btn-sm {{ $user->is_active ? 'bf-btn-danger-soft' : 'bf-btn-success' }}" type="submit">
                                    <i class="bi {{ $user->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                    {{ $user->is_active ? 'Bloquer' : 'Réactiver' }}
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="row g-3 h-100">
                <div class="col-6 col-md-3">
                    <div class="bf-card bf-kpi">
                        <div class="bf-kpi-label mb-2">Transactions</div>
                        <div class="bf-kpi-value tabular">{{ $user->transactions_count }}</div>
                        <div class="bf-kpi-foot">au total</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bf-card bf-kpi">
                        <div class="bf-kpi-label mb-2">Recharges validées</div>
                        <div class="bf-kpi-value tabular">{{ $recharges?->total ?? 0 }}</div>
                        <div class="bf-kpi-foot">{{ $fcfa($recharges?->volume ?? 0) }} FCFA</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bf-card bf-kpi">
                        <div class="bf-kpi-label mb-2">Retraits validés</div>
                        <div class="bf-kpi-value tabular">{{ $retraits?->total ?? 0 }}</div>
                        <div class="bf-kpi-foot">{{ $fcfa($retraits?->volume ?? 0) }} FCFA</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <a href="/admin/messages?user_id={{ $user->id }}" class="bf-card bf-kpi d-block">
                        <div class="bf-kpi-label mb-2">Messages</div>
                        <div class="bf-kpi-value tabular">{{ $messagesCount }}</div>
                        <div class="bf-kpi-foot">échangés avec le support</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bf-card">
        <div class="bf-card-header">
            <div>
                <h2 class="bf-card-title">Historique des transactions</h2>
                <p class="bf-card-sub">Toutes les demandes de cet utilisateur</p>
            </div>
            <a href="/admin/transactions?search={{ urlencode($user->email) }}" class="btn btn-sm bf-btn-light">Traiter dans Transactions</a>
        </div>
        <div class="table-responsive">
            <table class="table bf-table align-middle">
                <thead>
                    <tr>
                        <th>Demande</th>
                        <th>Type</th>
                        <th>Détails</th>
                        <th class="text-end">Montant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-bold">#{{ $transaction->id }}</div>
                                <div class="bf-small bf-muted">{{ $transaction->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>@include('admin.partials.tx-type', ['transaction' => $transaction])</td>
                            <td class="bf-small">
                                @if ($transaction->type === 'recharge')
                                    ID joueur <strong>{{ $transaction->player_id ?: '—' }}</strong> · ID transaction <strong>{{ $transaction->reference_code ?: '—' }}</strong>
                                @else
                                    {{ $transaction->operator ?: '—' }} <strong>{{ $transaction->mobile_money_number ?: '—' }}</strong> · Code <strong>{{ $transaction->withdrawal_code ?: '—' }}</strong>
                                @endif
                            </td>
                            <td class="text-end fw-bold tabular text-nowrap">{{ $fcfa($transaction->amount) }} FCFA</td>
                            <td>@include('admin.partials.status', ['status' => $transaction->status])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><div class="bf-empty"><i class="bi bi-receipt"></i>Aucune transaction</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="bf-pagination">{{ $transactions->links('admin.partials.pagination') }}</div>
        @endif
    </div>
@endsection
