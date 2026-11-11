{{-- Activation manuelle d'un abonnement : offre et durée au choix. $client (facultatif) = fiche client ouverte. --}}
@php
    $grantClient = $client ?? null;
    $grantLevel = old('level', \App\Models\AppSetting::VIP_LEVELS[0]);
    $grantDays = (int) old('days', 30);
@endphp
<form method="POST" action="/admin/vip/grant" class="bf-grant" data-confirm="Activer cet abonnement ? Le client reçoit une notification.">
    @csrf
    @if ($grantClient)
        <input type="hidden" name="user_id" value="{{ $grantClient->id }}">
    @else
        <label class="bf-grant-field bf-grant-client">
            <span>Client</span>
            <input class="form-control" name="client" value="{{ old('client') }}" placeholder="E-mail ou téléphone du client" required autocomplete="off">
        </label>
    @endif
    <label class="bf-grant-field">
        <span>Offre</span>
        <select class="form-select" name="level">
            @foreach (\App\Models\AppSetting::VIP_LEVELS as $level)
                <option value="{{ $level }}" @selected($grantLevel === $level)>{{ \App\Models\AppSetting::offerName($level) }}</option>
            @endforeach
        </select>
    </label>
    <label class="bf-grant-field">
        <span>Durée</span>
        <select class="form-select" name="days">
            @foreach (\App\Services\VipService::MANUAL_DURATIONS as $days => $label)
                <option value="{{ $days }}" @selected($grantDays === $days)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="bf-grant-field bf-grant-custom">
        <span>ou nombre de jours</span>
        <input class="form-control" type="number" name="custom_days" min="1" max="366" value="{{ old('custom_days') }}" placeholder="ex. 45">
    </label>
    <button class="btn bf-btn-gold bf-grant-submit" type="submit"><i class="bi bi-gem"></i> Activer</button>
    <p class="bf-grant-hint">Si un abonnement à cette offre est déjà actif, la durée s’ajoute à sa fin.</p>
</form>

@once
    @push('head')
        <style>
            .bf-grant { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 14px 16px 16px; }
            .bf-grant-field { display: grid; gap: 4px; margin: 0; }
            .bf-grant-field span { color: var(--bf-muted); font-size: .74rem; font-weight: 700; }
            .bf-grant-client { grid-column: 1 / -1; }
            .bf-grant-submit { grid-column: 1 / -1; height: 44px; font-weight: 800; }
            .bf-grant-hint { grid-column: 1 / -1; margin: 0; color: var(--bf-muted); font-size: .74rem; }
        </style>
    @endpush
@endonce
