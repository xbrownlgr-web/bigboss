{{-- Actions d'une transaction (tableau ordinateur et cartes téléphone) --}}
@php
    $isOpen = in_array($transaction->status, ['pending', 'processing'], true);
    $amountLabel = number_format((float) $transaction->amount, 0, ',', ' ') . ' FCFA';
    $payload = [
        'id' => $transaction->id,
        'type' => $transaction->type,
        'platform' => $transaction->platform === '1xbet' ? '1xBet' : 'Melbet',
        'amount' => $amountLabel,
        'operator' => $transaction->operator,
        'player_id' => $transaction->player_id,
        'reference' => $transaction->reference_code,
        'withdrawal_code' => $transaction->withdrawal_code,
        'momo_number' => $transaction->mobile_money_number,
        'client' => $transaction->user?->name,
        'phone' => $transaction->user?->phone,
        'action' => '/admin/transactions/' . $transaction->id . '/approve',
    ];
@endphp
<div class="d-flex flex-wrap gap-1 {{ $align ?? 'justify-content-end' }}">
    @if ($isOpen)
        <button type="button" class="btn btn-sm bf-btn-success" data-bs-toggle="modal" data-bs-target="#approveModal" data-tx='@json($payload)'>
            <i class="bi bi-check-lg"></i> Approuver
        </button>
        <button
            type="button"
            class="btn btn-sm bf-btn-danger-soft"
            data-bs-toggle="modal"
            data-bs-target="#rejectModal"
            data-action="/admin/transactions/{{ $transaction->id }}/reject"
            data-label="#{{ $transaction->id }} · {{ $transaction->user?->name }} · {{ $amountLabel }}"
        >
            <i class="bi bi-x-lg"></i> Refuser
        </button>
    @endif
    <div class="dropdown">
        <button class="btn btn-sm bf-btn-light" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Plus d’actions">
            <i class="bi bi-three-dots"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            @if ($transaction->status === 'pending')
                <li>
                    <form method="POST" action="/admin/transactions/{{ $transaction->id }}/processing">
                        @csrf
                        <button class="dropdown-item" type="submit"><i class="bi bi-play-circle"></i> Marquer « en cours »</button>
                    </form>
                </li>
            @endif
            <li>
                <button
                    class="dropdown-item"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#noteModal"
                    data-action="/admin/transactions/{{ $transaction->id }}/note"
                    data-note="{{ $transaction->admin_note }}"
                    data-label="#{{ $transaction->id }} · {{ $transaction->user?->name }}"
                >
                    <i class="bi bi-sticky"></i> Note interne
                </button>
            </li>
            @if ($transaction->user)
                <li><a class="dropdown-item" href="/admin/messages?user_id={{ $transaction->user_id }}"><i class="bi bi-chat-dots"></i> Écrire au client</a></li>
                <li><a class="dropdown-item" href="/admin/users/{{ $transaction->user_id }}"><i class="bi bi-person"></i> Fiche client</a></li>
            @endif
        </ul>
    </div>
</div>
