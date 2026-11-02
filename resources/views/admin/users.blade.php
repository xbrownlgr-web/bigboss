@extends('admin.layout')

@section('title', 'Utilisateurs')
@section('subtitle', 'Comptes, accès VIP, suspensions et notifications')

@php
    $tabs = [
        '' => ['Tous', $counts['all']],
        'vip' => ['VIP', $counts['vip']],
        'blocked' => ['Bloqués', $counts['blocked']],
        'push' => ['Notifications actives', $counts['push']],
    ];
@endphp

@section('content')
    <div class="d-flex flex-column flex-lg-row gap-2 justify-content-between align-items-lg-center mb-3">
        <div class="bf-tabs">
            @foreach ($tabs as $value => [$label, $count])
                <a href="/admin/users?{{ http_build_query(array_filter(['filter' => $value, 'search' => $search])) }}" class="bf-tab {{ $filter === $value ? 'active' : '' }}">
                    {{ $label }} <span class="tabular">{{ $count }}</span>
                </a>
            @endforeach
        </div>
        <form method="GET" action="/admin/users" class="d-flex gap-2" style="min-width:min(100%,420px);">
            @if ($filter)
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
            <div class="bf-search flex-grow-1">
                <i class="bi bi-search"></i>
                <input class="form-control" name="search" value="{{ $search }}" placeholder="Nom, e-mail ou téléphone" aria-label="Rechercher un utilisateur">
            </div>
            <button class="btn bf-btn-primary" type="submit">Rechercher</button>
        </form>
    </div>

    <div class="bf-card">
        <div class="table-responsive">
            <table class="table bf-table align-middle">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Téléphone</th>
                        <th>Pays</th>
                        <th class="text-center">Transactions</th>
                        <th>Accès</th>
                        <th>Inscription</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $isMainAdmin = $user->email === 'admin@binhoflash.com';
                        @endphp
                        <tr>
                            <td>
                                <a href="/admin/users/{{ $user->id }}" class="d-flex align-items-center gap-2 text-body">
                                    @include('admin.partials.avatar', ['name' => $user->name, 'id' => $user->id])
                                    <div class="min-w-0">
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="bf-small bf-muted">{{ $user->email }}</div>
                                    </div>
                                </a>
                            </td>
                            <td class="text-nowrap tabular">{{ $user->phone ?: '—' }}</td>
                            <td class="text-nowrap">{{ $user->country_label }}</td>
                            <td class="text-center tabular fw-semibold">{{ $user->transactions_count }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @if ($user->is_active)
                                        <span class="bf-badge success"><span class="dot"></span>Actif</span>
                                    @else
                                        <span class="bf-badge danger"><span class="dot"></span>Bloqué</span>
                                    @endif
                                    @if ($user->is_vip)
                                        <span class="bf-badge vip"><i class="bi bi-star-fill"></i>VIP</span>
                                    @endif
                                    @if ($user->fcm_token)
                                        <span class="bf-badge neutral" title="Notifications push actives"><i class="bi bi-bell"></i></span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-nowrap bf-muted">{{ $user->created_at->format('d/m/Y') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="/admin/users/{{ $user->id }}" class="btn btn-sm bf-btn-light">Fiche</a>
                                    <div class="dropdown">
                                        <button class="btn btn-sm bf-btn-light" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Plus d’actions">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <form method="POST" action="/admin/users/{{ $user->id }}/vip">
                                                    @csrf
                                                    <button class="dropdown-item" type="submit">
                                                        <i class="bi {{ $user->is_vip ? 'bi-star' : 'bi-star-fill' }}"></i>
                                                        {{ $user->is_vip ? 'Retirer l’accès VIP' : 'Passer VIP' }}
                                                    </button>
                                                </form>
                                            </li>
                                            <li><a class="dropdown-item" href="/admin/messages?user_id={{ $user->id }}"><i class="bi bi-chat-dots"></i> Envoyer un message</a></li>
                                            <li>
                                                <form method="POST" action="/admin/users/{{ $user->id }}/test-push">
                                                    @csrf
                                                    <button class="dropdown-item" type="submit" @disabled(! $user->fcm_token)><i class="bi bi-bell"></i> Tester la notification</button>
                                                </form>
                                            </li>
                                            @unless ($isMainAdmin)
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="/admin/users/{{ $user->id }}/active" data-confirm="{{ $user->is_active ? 'Bloquer ' . $user->name . ' ? Il sera déconnecté de l’application.' : 'Réactiver le compte de ' . $user->name . ' ?' }}">
                                                        @csrf
                                                        <button class="dropdown-item {{ $user->is_active ? 'text-danger' : 'text-success' }}" type="submit">
                                                            <i class="bi {{ $user->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                                            {{ $user->is_active ? 'Bloquer le compte' : 'Réactiver le compte' }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @endunless
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><div class="bf-empty"><i class="bi bi-people"></i>Aucun utilisateur trouvé</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="bf-pagination">{{ $users->links('admin.partials.pagination') }}</div>
        @endif
    </div>
@endsection
