@extends('admin.layout')

@section('title', 'Messages')
@section('subtitle', 'Support utilisateurs · les réponses arrivent en notification sur le téléphone')

@push('head')
    <style>
        .bf-chat { display: grid; grid-template-columns: 340px 1fr; height: calc(100vh - 150px); min-height: 520px; overflow: hidden; }
        .bf-threads { display: flex; flex-direction: column; min-height: 0; border-right: 1px solid var(--bf-border); }
        .bf-threads-list { flex: 1; overflow-y: auto; }
        .bf-thread { display: flex; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--bf-border); color: var(--bf-text); }
        .bf-thread:hover { background: var(--bf-surface-2); }
        .bf-thread.active { background: #EEF2FA; box-shadow: inset 3px 0 0 var(--bf-navy); }
        .bf-thread .unread { min-width: 20px; padding: 1px 6px; border-radius: 999px; background: var(--bf-navy); color: #fff; font-size: .7rem; font-weight: 700; text-align: center; }
        .bf-conversation { display: flex; flex-direction: column; min-height: 0; }
        .bf-messages { flex: 1; overflow-y: auto; padding: 20px 24px; background: var(--bf-surface-2); }
        .bf-day { margin: 8px 0 16px; color: var(--bf-subtle); font-size: .72rem; font-weight: 600; text-align: center; text-transform: uppercase; letter-spacing: .06em; }
        .bf-bubble { width: fit-content; max-width: min(72%, 560px); margin-bottom: 10px; padding: 10px 14px; border-radius: 16px; overflow-wrap: anywhere; }
        .bf-bubble-text { white-space: pre-wrap; }
        .bf-bubble.user { border: 1px solid var(--bf-border); border-bottom-left-radius: 4px; background: #fff; color: var(--bf-text); }
        .bf-bubble.admin { margin-left: auto; border-bottom-right-radius: 4px; background: var(--bf-navy); color: #fff; }
        .bf-bubble time { display: block; margin-top: 4px; font-size: .7rem; opacity: .65; text-align: right; }
        .bf-composer { display: flex; gap: 10px; align-items: flex-end; padding: 14px 16px; border-top: 1px solid var(--bf-border); background: #fff; }
        .bf-composer textarea { resize: none; max-height: 160px; }

        @media (max-width: 991.98px) {
            .bf-chat { grid-template-columns: 1fr; height: auto; }
            .bf-threads { max-height: 300px; border-right: 0; border-bottom: 1px solid var(--bf-border); }
            .bf-messages { max-height: 60vh; }
            .bf-bubble { max-width: 88%; }
        }
    </style>
@endpush

@section('content')
    <div class="bf-card bf-chat">
        <aside class="bf-threads">
            <form method="GET" action="/admin/messages" class="p-3 border-bottom">
                <div class="bf-search">
                    <i class="bi bi-search"></i>
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Rechercher une conversation" aria-label="Rechercher une conversation">
                </div>
            </form>
            <div class="bf-threads-list">
                @forelse ($conversations as $conversation)
                    @php
                        $isActive = $selectedUser?->id === $conversation->user_id;
                    @endphp
                    <a href="/admin/messages?{{ http_build_query(array_filter(['user_id' => $conversation->user_id, 'search' => $search])) }}" class="bf-thread {{ $isActive ? 'active' : '' }}">
                        @include('admin.partials.avatar', ['name' => $conversation->user?->name, 'id' => $conversation->user_id])
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold text-truncate">{{ $conversation->user?->name ?? 'Utilisateur supprimé' }}</span>
                                <span class="bf-small bf-muted text-nowrap">
                                    {{ $conversation->last->created_at->isToday() ? $conversation->last->created_at->format('H:i') : $conversation->last->created_at->format('d/m') }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="bf-small {{ $conversation->unread ? 'text-body fw-semibold' : 'bf-muted' }} text-truncate">
                                    @if ($conversation->last->sender === 'admin')
                                        <i class="bi bi-reply"></i>
                                    @endif
                                    {{ $conversation->last->content }}
                                </span>
                                @if ($conversation->unread)
                                    <span class="unread">{{ $conversation->unread }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="bf-empty"><i class="bi bi-chat-square"></i>Aucune conversation</div>
                @endforelse
            </div>
        </aside>

        <section class="bf-conversation">
            @if ($selectedUser)
                <div class="d-flex align-items-center gap-3 px-3 py-3 border-bottom">
                    @include('admin.partials.avatar', ['name' => $selectedUser->name, 'id' => $selectedUser->id])
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-bold text-truncate">{{ $selectedUser->name }}</div>
                        <div class="bf-small bf-muted text-truncate">{{ $selectedUser->email }} · {{ $selectedUser->phone ?: 'sans téléphone' }}</div>
                    </div>
                    <a href="/admin/users/{{ $selectedUser->id }}" class="btn btn-sm bf-btn-light flex-shrink-0"><i class="bi bi-person"></i> <span class="btn-label">Fiche</span></a>
                </div>

                <div class="bf-messages" id="bfMessages">
                    @php $currentDay = null; @endphp
                    @forelse ($messages as $message)
                        @php $day = $message->created_at->toDateString(); @endphp
                        @if ($day !== $currentDay)
                            <div class="bf-day">{{ $message->created_at->isToday() ? 'Aujourd’hui' : $message->created_at->locale('fr')->isoFormat('dddd D MMMM') }}</div>
                            @php $currentDay = $day; @endphp
                        @endif
                        <div class="bf-bubble {{ $message->sender === 'admin' ? 'admin' : 'user' }}">
                            @if ($message->transaction)
                                <a href="/admin/transactions?search={{ $message->transaction_id }}" class="d-inline-flex align-items-center gap-1 mb-1 bf-small fw-semibold {{ $message->sender === 'admin' ? 'text-white' : '' }}">
                                    <i class="bi bi-link-45deg"></i> Transaction #{{ $message->transaction_id }}
                                </a>
                            @endif
                            <div class="bf-bubble-text">{{ $message->content }}</div>
                            <time>{{ $message->created_at->format('H:i') }}</time>
                        </div>
                    @empty
                        <div class="bf-empty"><i class="bi bi-chat-dots"></i>Aucun message. Écrivez le premier.</div>
                    @endforelse
                </div>

                <form method="POST" action="/admin/messages/{{ $selectedUser->id }}/reply" class="bf-composer" id="bfComposer">
                    @csrf
                    <textarea class="form-control" name="content" rows="1" maxlength="2000" placeholder="Écrire une réponse… (Entrée pour envoyer, Maj+Entrée pour aller à la ligne)" required aria-label="Votre réponse"></textarea>
                    <button class="btn bf-btn-primary" type="submit"><i class="bi bi-send-fill"></i> <span class="btn-label">Envoyer</span></button>
                </form>
            @else
                <div class="bf-empty my-auto">
                    <i class="bi bi-chat-left-text"></i>
                    Sélectionnez une conversation pour répondre
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const list = document.getElementById('bfMessages');
            if (list) list.scrollTop = list.scrollHeight;

            const form = document.getElementById('bfComposer');
            if (! form) return;

            const field = form.querySelector('textarea');
            const grow = function () {
                field.style.height = 'auto';
                field.style.height = Math.min(field.scrollHeight, 160) + 'px';
            };

            field.addEventListener('input', grow);
            field.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && ! event.shiftKey) {
                    event.preventDefault();
                    if (field.value.trim()) form.requestSubmit();
                }
            });
            field.focus();
        })();
    </script>
@endpush
