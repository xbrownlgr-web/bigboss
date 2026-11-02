@extends('admin.layout')

@section('title', 'Transactions')
@section('subtitle', 'Recharges et retraits à vérifier, valider ou refuser')

@php
    $fcfa = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $tabs = [
        '' => ['Toutes', $totalCount],
        'pending' => ['En attente', $statusCounts['pending'] ?? 0],
        'processing' => ['En cours', $statusCounts['processing'] ?? 0],
        'completed' => ['Approuvées', $statusCounts['completed'] ?? 0],
        'rejected' => ['Refusées', $statusCounts['rejected'] ?? 0],
    ];
    $baseQuery = array_filter([
        'type' => $selectedType,
        'platform' => $selectedPlatform,
        'search' => $search,
    ]);
    $detailsFor = function ($transaction) {
        return $transaction->type === 'recharge'
            ? [
                'ID joueur' => $transaction->player_id,
                'Opérateur' => $transaction->operator,
                'ID transaction' => $transaction->reference_code,
            ]
            : [
                'ID joueur' => $transaction->player_id,
                'Code retrait' => $transaction->withdrawal_code,
                'Opérateur' => $transaction->operator,
                'N° Mobile Money' => $transaction->mobile_money_number,
            ];
    };
    $copyable = ['ID joueur', 'ID transaction', 'Code retrait', 'N° Mobile Money'];
@endphp

@push('head')
    <style>
        .bf-tx-card { padding: 14px 16px; border-bottom: 1px solid var(--bf-border); }
        .bf-tx-card:last-child { border-bottom: 0; }
        .bf-check-step { display: flex; gap: 12px; padding: 12px; border-radius: 12px; background: var(--bf-surface-2); }
        .bf-check-step .num { display: grid; flex: 0 0 26px; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: var(--bf-gold); color: var(--bf-navy-deep); font-weight: 800; font-size: .8rem; }
        .bf-check-step strong { color: var(--bf-navy); }
        .bf-copy-chip { display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; padding: 3px 10px; border: 1px solid var(--bf-border); border-radius: 8px; background: #fff; font-weight: 700; font-family: ui-monospace, monospace; color: var(--bf-text); }
    </style>
@endpush

@section('content')
    <div class="bf-tabs mb-3">
        @foreach ($tabs as $value => [$label, $count])
            <a href="/admin/transactions?{{ http_build_query(array_filter([...$baseQuery, 'status' => $value])) }}" class="bf-tab {{ $selectedStatus === $value ? 'active' : '' }}">
                {{ $label }} <span class="tabular">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    <div class="bf-card mb-3">
        <form class="bf-card-body row g-2 align-items-end" method="GET" action="/admin/transactions">
            @if ($selectedStatus)
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
            @endif
            <div class="col-12 col-lg-5">
                <label class="form-label" for="search">Recherche</label>
                <div class="bf-search">
                    <i class="bi bi-search"></i>
                    <input id="search" class="form-control" name="search" value="{{ $search }}" placeholder="N°, nom, e-mail, ID joueur, ID transaction, numéro…">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="type">Type</label>
                <select id="type" class="form-select" name="type">
                    <option value="">Tous</option>
                    <option value="recharge" @selected($selectedType === 'recharge')>Recharge</option>
                    <option value="retrait" @selected($selectedType === 'retrait')>Retrait</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="platform">Plateforme</label>
                <select id="platform" class="form-select" name="platform">
                    <option value="">Toutes</option>
                    <option value="1xbet" @selected($selectedPlatform === '1xbet')>1xBet</option>
                    <option value="melbet" @selected($selectedPlatform === 'melbet')>Melbet</option>
                </select>
            </div>
            <div class="col-12 col-lg-3 d-flex gap-2">
                <button class="btn bf-btn-primary flex-grow-1" type="submit"><i class="bi bi-funnel"></i> Filtrer</button>
                <a class="btn bf-btn-light" href="/admin/transactions" title="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="bf-card">
        {{-- Ordinateur : tableau --}}
        <div class="table-responsive d-none d-lg-block">
            <table class="table bf-table align-middle">
                <thead>
                    <tr>
                        <th>Demande</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th style="min-width:240px;">Informations de traitement</th>
                        <th class="text-end">Montant</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-bold">#{{ $transaction->id }}</div>
                                <div class="bf-small bf-muted">{{ $transaction->created_at->format('d/m/Y H:i') }}</div>
                                <div class="bf-small bf-muted">{{ $transaction->created_at->locale('fr')->diffForHumans() }}</div>
                            </td>
                            <td>
                                @if ($transaction->user)
                                    <a href="/admin/users/{{ $transaction->user_id }}" class="d-flex align-items-center gap-2 text-body">
                                        @include('admin.partials.avatar', ['name' => $transaction->user->name, 'id' => $transaction->user_id])
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate" style="max-width:180px;">{{ $transaction->user->name }}</div>
                                            <div class="bf-small bf-muted text-truncate" style="max-width:180px;">{{ $transaction->user->phone ?: $transaction->user->email }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="bf-muted">Client supprimé</span>
                                @endif
                            </td>
                            <td>@include('admin.partials.tx-type', ['transaction' => $transaction])</td>
                            <td>
                                <dl class="bf-kv mb-0">
                                    @foreach ($detailsFor($transaction) as $label => $value)
                                        <dt>{{ $label }}</dt>
                                        <dd>
                                            {{ $value ?: '—' }}
                                            @if ($value && in_array($label, $copyable, true))
                                                <button type="button" class="bf-copy" data-copy="{{ $value }}" title="Copier"><i class="bi bi-copy"></i></button>
                                            @endif
                                        </dd>
                                    @endforeach
                                </dl>
                                @if ($transaction->admin_note)
                                    <div class="mt-2 bf-small px-2 py-1 rounded-2" style="background:var(--bf-gold-soft);color:#5C4500;">
                                        <i class="bi bi-sticky"></i> {{ $transaction->admin_note }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="fw-bold tabular fs-6">{{ $fcfa($transaction->amount) }}</div>
                                <div class="bf-small bf-muted">FCFA</div>
                            </td>
                            <td>
                                @include('admin.partials.status', ['status' => $transaction->status])
                                @if ($transaction->status === 'rejected' && $transaction->rejection_reason)
                                    <div class="bf-small bf-muted mt-1" style="max-width:180px;">{{ $transaction->rejection_reason }}</div>
                                @elseif ($transaction->processed_at)
                                    <div class="bf-small bf-muted mt-1">le {{ $transaction->processed_at->format('d/m H:i') }}</div>
                                @endif
                            </td>
                            <td class="text-end">@include('admin.partials.tx-actions', ['transaction' => $transaction])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><div class="bf-empty"><i class="bi bi-search"></i>Aucune transaction ne correspond à ces filtres</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Téléphone : cartes lisibles, sans défilement horizontal --}}
        <div class="d-lg-none">
            @forelse ($transactions as $transaction)
                <article class="bf-tx-card">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        @include('admin.partials.tx-type', ['transaction' => $transaction])
                        <div class="text-end">
                            <div class="fw-bold tabular fs-5">{{ $fcfa($transaction->amount) }} <span class="bf-small bf-muted">FCFA</span></div>
                            @include('admin.partials.status', ['status' => $transaction->status])
                        </div>
                    </div>
                    <div class="bf-small bf-muted mt-2">
                        #{{ $transaction->id }} · {{ $transaction->created_at->locale('fr')->diffForHumans() }} ·
                        @if ($transaction->user)
                            <a href="/admin/users/{{ $transaction->user_id }}">{{ $transaction->user->name }}</a>
                            @if ($transaction->user->phone) · {{ $transaction->user->phone }} @endif
                        @else
                            client supprimé
                        @endif
                    </div>
                    <dl class="bf-kv mt-2 mb-2">
                        @foreach ($detailsFor($transaction) as $label => $value)
                            <dt>{{ $label }}</dt>
                            <dd>
                                {{ $value ?: '—' }}
                                @if ($value && in_array($label, $copyable, true))
                                    <button type="button" class="bf-copy" data-copy="{{ $value }}" title="Copier"><i class="bi bi-copy"></i></button>
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                    @if ($transaction->admin_note)
                        <div class="mb-2 bf-small px-2 py-1 rounded-2" style="background:var(--bf-gold-soft);color:#5C4500;"><i class="bi bi-sticky"></i> {{ $transaction->admin_note }}</div>
                    @endif
                    @if ($transaction->status === 'rejected' && $transaction->rejection_reason)
                        <div class="mb-2 bf-small bf-muted">Motif : {{ $transaction->rejection_reason }}</div>
                    @endif
                    @include('admin.partials.tx-actions', ['transaction' => $transaction, 'align' => 'justify-content-start'])
                </article>
            @empty
                <div class="bf-empty"><i class="bi bi-search"></i>Aucune transaction ne correspond à ces filtres</div>
            @endforelse
        </div>

        @if ($transactions->hasPages())
            <div class="bf-pagination">{{ $transactions->links('admin.partials.pagination') }}</div>
        @endif
    </div>

    {{-- Approbation : liste de contrôle avant de valider --}}
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5 fw-bold" id="approveModalTitle">Approuver la demande</h2>
                        <div class="bf-small bf-muted" data-field="subtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body vstack gap-2" data-field="steps"></div>
                <div class="modal-footer">
                    <button type="button" class="btn bf-btn-light" data-bs-dismiss="modal">Pas encore</button>
                    <button type="submit" class="btn bf-btn-success"><i class="bi bi-check-lg"></i> <span data-field="confirm">C’est fait, approuver</span></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Refus --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5 fw-bold" id="rejectModalTitle">Refuser la demande</h2>
                        <div class="bf-small bf-muted" data-label></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="rejection_reason">Motif communiqué au client</label>
                    <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" maxlength="1000" placeholder="Ex. : ID de transaction introuvable, montant différent…"></textarea>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach (['ID de transaction introuvable', 'Montant reçu différent', 'ID joueur invalide', 'Code de retrait invalide ou expiré'] as $reason)
                            <button type="button" class="btn btn-sm bf-btn-light" data-reason="{{ $reason }}">{{ $reason }}</button>
                        @endforeach
                    </div>
                    <p class="bf-small bf-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> Le client reçoit une notification et un e-mail avec ce motif.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bf-btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i> Confirmer le refus</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Note interne --}}
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5 fw-bold" id="noteModalTitle">Note interne</h2>
                        <div class="bf-small bf-muted" data-label></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" name="admin_note" rows="4" maxlength="2000" placeholder="Visible uniquement par l’administration"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bf-btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn bf-btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // Refus et note : le formulaire de la fenêtre reprend la transaction cliquée.
            ['rejectModal', 'noteModal'].forEach(function (id) {
                const modal = document.getElementById(id);

                modal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    const form = modal.querySelector('form');
                    form.action = trigger.dataset.action;
                    modal.querySelector('[data-label]').textContent = trigger.dataset.label || '';

                    const field = form.querySelector('textarea');
                    field.value = trigger.dataset.note || '';
                    setTimeout(function () { field.focus(); }, 250);
                });
            });

            document.querySelectorAll('[data-reason]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('rejection_reason').value = button.dataset.reason;
                });
            });

            // Approbation : liste de contrôle selon le type de demande (texte inséré sans HTML).
            const approve = document.getElementById('approveModal');

            function copyChip(value) {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'bf-copy-chip';
                chip.dataset.copy = value;
                chip.title = 'Copier';
                chip.append(document.createTextNode(value + ' '));
                const icon = document.createElement('i');
                icon.className = 'bi bi-copy';
                chip.append(icon);
                return chip;
            }

            function step(number, parts) {
                const row = document.createElement('div');
                row.className = 'bf-check-step';
                const badge = document.createElement('span');
                badge.className = 'num';
                badge.textContent = number;
                const body = document.createElement('div');

                parts.forEach(function (part) {
                    if (typeof part === 'string') {
                        body.append(document.createTextNode(part));
                    } else if (part.strong) {
                        const strong = document.createElement('strong');
                        strong.textContent = part.strong;
                        body.append(strong);
                    } else if (part.copy) {
                        body.append(document.createElement('br'), copyChip(part.copy));
                    }
                });

                row.append(badge, body);
                return row;
            }

            approve.addEventListener('show.bs.modal', function (event) {
                const tx = JSON.parse(event.relatedTarget.dataset.tx);
                const steps = approve.querySelector('[data-field="steps"]');
                approve.querySelector('form').action = tx.action;
                approve.querySelector('[data-field="subtitle"]').textContent =
                    '#' + tx.id + ' · ' + (tx.client || 'Client') + (tx.phone ? ' · ' + tx.phone : '');
                steps.replaceChildren();

                if (tx.type === 'recharge') {
                    approve.querySelector('[data-field="confirm"]').textContent = 'Compte rechargé, approuver';
                    steps.append(
                        step(1, ['Vérifiez sur votre téléphone ', { strong: tx.operator || 'Mobile Money' }, ' que vous avez bien reçu ', { strong: tx.amount }, ' avec l’ID de transaction :', { copy: tx.reference || '—' }]),
                        step(2, ['Rechargez ', { strong: tx.amount }, ' sur le compte ', { strong: tx.platform }, ' n° :', { copy: tx.player_id || '—' }]),
                        step(3, ['Cliquez sur « Compte rechargé, approuver » : le client reçoit une notification et un e-mail.']),
                    );
                } else {
                    approve.querySelector('[data-field="confirm"]').textContent = 'Argent envoyé, approuver';
                    steps.append(
                        step(1, ['Validez le retrait sur ', { strong: tx.platform }, tx.player_id ? ' (compte n° ' + tx.player_id + ')' : '', ' avec le code :', { copy: tx.withdrawal_code || '—' }]),
                        step(2, ['Envoyez ', { strong: tx.amount }, ' par ', { strong: tx.operator || 'Mobile Money' }, ' au numéro :', { copy: tx.momo_number || '—' }]),
                        step(3, ['Cliquez sur « Argent envoyé, approuver » : le client reçoit une notification et un e-mail.']),
                    );
                }
            });
        })();
    </script>
@endpush
