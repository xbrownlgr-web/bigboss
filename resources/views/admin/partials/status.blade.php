@php
    $statusLabels = [
        'pending' => 'En attente',
        'processing' => 'En cours',
        'completed' => 'Approuvée',
        'rejected' => 'Refusée',
    ];
@endphp
<span class="bf-badge {{ $status }}">
    <span class="dot"></span>{{ $statusLabels[$status] ?? ucfirst($status) }}
</span>
