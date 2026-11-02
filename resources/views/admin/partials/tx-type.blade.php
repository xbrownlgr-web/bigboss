@php
    $isRecharge = $transaction->type === 'recharge';
@endphp
<span class="bf-type {{ $transaction->type }}">
    <i class="bi {{ $isRecharge ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}"></i>
    <span>
        {{ $isRecharge ? 'Recharge' : 'Retrait' }}
        <span class="d-block bf-muted bf-small fw-medium">{{ $transaction->platform === '1xbet' ? '1xBet' : 'Melbet' }}</span>
    </span>
</span>
