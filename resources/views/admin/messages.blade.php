@extends('admin.layout')

@section('title', 'Messages')
@section('subtitle', 'Support utilisateurs · chaque réponse arrive en notification sur le téléphone')

@php
    $query = fn (array $extra = []) => http_build_query(array_filter([
        'filter' => $filter === 'unread' ? 'unread' : null,
        'search' => $search ?: null,
        ...$extra,
    ]));
    $phoneDigits = $selectedUser ? preg_replace('/\D+/', '', (string) $selectedUser->phone) : '';
@endphp

@push('head')
    <style>
        .bf-chat { display: grid; grid-template-columns: 340px 1fr; height: calc(100vh - 140px); height: calc(100dvh - 140px); min-height: 480px; overflow: hidden; border: 1px solid var(--bf-border); border-radius: var(--bf-radius); background: #fff; box-shadow: var(--bf-shadow); }

        /* Liste des conversations */
        .bf-threads { display: flex; flex-direction: column; min-height: 0; border-right: 1px solid var(--bf-border); }
        .bf-threads-head { display: grid; gap: 10px; padding: 12px; border-bottom: 1px solid var(--bf-border); }
        .bf-filters { display: flex; align-items: center; gap: 6px; }
        .bf-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid var(--bf-border); border-radius: 999px; background: #fff; color: var(--bf-text-2); font-size: .78rem; font-weight: 600; }
        .bf-chip span { color: var(--bf-muted); font-size: .7rem; }
        .bf-chip.active { border-color: var(--bf-navy); background: var(--bf-navy); color: #fff; }
        .bf-chip.active span { color: #A7F3D0; }
        .bf-sort-hint { margin-left: auto; color: var(--bf-subtle); font-size: .68rem; }
        .bf-threads-list { flex: 1; overflow-y: auto; overscroll-behavior: contain; }
        .bf-thread { display: flex; align-items: center; gap: 11px; padding: 11px 14px; border-bottom: 1px solid #EEF1EF; color: var(--bf-text); }
        .bf-thread:hover { background: var(--bf-surface-2); }
        .bf-thread.active { background: #ECFDF3; box-shadow: inset 3px 0 0 var(--bf-gold); }
        .bf-thread .t-name { font-weight: 600; }
        .bf-thread .t-time { color: var(--bf-subtle); font-size: .72rem; white-space: nowrap; }
        .bf-thread .t-preview { color: var(--bf-muted); font-size: .8rem; }
        .bf-thread.is-unread .t-name { font-weight: 800; }
        .bf-thread.is-unread .t-time { color: var(--bf-success); font-weight: 700; }
        .bf-thread.is-unread .t-preview { color: var(--bf-text); font-weight: 600; }
        .bf-thread .unread { display: grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--bf-gold); color: var(--bf-navy-deep); font-size: .7rem; font-weight: 800; }

        /* Conversation */
        .bf-conversation { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
        .bf-conv-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--bf-border); background: #fff; }
        .bf-conv-head .c-name { font-weight: 700; }
        .bf-conv-head .c-sub { color: var(--bf-muted); font-size: .76rem; }
        .bf-icon-btn { display: grid; flex: 0 0 auto; place-items: center; width: 36px; height: 36px; border: 1px solid var(--bf-border); border-radius: 10px; background: #fff; color: var(--bf-text-2); }
        .bf-icon-btn:hover { background: var(--bf-surface-2); color: var(--bf-text); }
        .bf-icon-btn.wa { color: #16A34A; }
        .bf-back { display: none; border: 0; }
        .bf-messages { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 16px; background: #F4F7F5; }
        .bf-day { margin: 6px 0 12px; text-align: center; }
        .bf-day span { padding: 3px 10px; border-radius: 999px; background: #E3EAE6; color: var(--bf-muted); font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .bf-bubble { width: fit-content; max-width: min(78%, 560px); margin-bottom: 8px; padding: 8px 12px; border-radius: 16px; line-height: 1.4; overflow-wrap: anywhere; box-shadow: 0 1px 1px rgba(0, 0, 0, .04); }
        .bf-bubble-text { white-space: pre-wrap; }
        .bf-bubble.user { border: 1px solid var(--bf-border); border-bottom-left-radius: 5px; background: #fff; color: var(--bf-text); }
        .bf-bubble.admin { margin-left: auto; border-bottom-right-radius: 5px; background: var(--bf-navy); color: #fff; }
        .bf-bubble time { display: block; margin-top: 2px; font-size: .66rem; opacity: .6; text-align: right; }
        .bf-tx { display: inline-flex; align-items: center; gap: 4px; margin-bottom: 2px; color: inherit; font-size: .74rem; font-weight: 700; text-decoration: underline; }
        .bf-status { padding: 7px 14px; font-size: .78rem; font-weight: 600; }
        .bf-status.error { background: var(--bf-danger-bg); color: var(--bf-danger); }
        .bf-status.info { background: #FEF3C7; color: #92400E; }
        .bf-composer { display: flex; align-items: flex-end; gap: 8px; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); border-top: 1px solid var(--bf-border); background: #fff; }
        .bf-composer textarea { flex: 1; min-height: 44px; max-height: 140px; padding: 10px 16px; border-radius: 22px; line-height: 1.35; resize: none; }
        .bf-send { display: grid; flex: 0 0 auto; place-items: center; width: 44px; height: 44px; border: 0; border-radius: 50%; background: var(--bf-gold); color: var(--bf-navy-deep); font-size: 1.05rem; }
        .bf-send:disabled { opacity: .55; }
        .bf-placeholder { margin: auto; padding: 24px; color: var(--bf-muted); text-align: center; }
        .bf-placeholder i { display: block; margin-bottom: 8px; color: var(--bf-subtle); font-size: 2.2rem; }

        /* Téléphone : la liste, puis la conversation en plein écran (comme WhatsApp). */
        @media (max-width: 991.98px) {
            .bf-content { padding: 8px 8px calc(76px + env(safe-area-inset-bottom)) !important; }
            .bf-chat { grid-template-columns: 1fr; height: calc(100vh - 132px); height: calc(100dvh - 132px - env(safe-area-inset-bottom)); min-height: 0; }
            .bf-threads { border-right: 0; }
            .bf-chat.has-conversation .bf-threads { display: none; }
            .bf-chat:not(.has-conversation) .bf-conversation { display: none; }
            body:has(.bf-chat.has-conversation) .bf-topbar,
            body:has(.bf-chat.has-conversation) .bf-bottomnav,
            body:has(.bf-chat.has-conversation) .bf-flash { display: none; }
            body:has(.bf-chat.has-conversation) .bf-content { padding: 0 !important; }
            .bf-chat.has-conversation { height: 100vh; height: 100dvh; border: 0; border-radius: 0; box-shadow: none; }
            .bf-back { display: grid; }
            .bf-bubble { max-width: 86%; }
        }
    </style>
@endpush

@section('content')
    <div class="bf-chat {{ $selectedUser ? 'has-conversation' : '' }}">
        <aside class="bf-threads">
            <div class="bf-threads-head">
                <form method="GET" action="/admin/messages" class="bf-search">
                    <i class="bi bi-search"></i>
                    @if ($filter === 'unread')
                        <input type="hidden" name="filter" value="unread">
                    @endif
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Rechercher un client" aria-label="Rechercher une conversation">
                </form>
                <div class="bf-filters">
                    <a href="/admin/messages?{{ http_build_query(array_filter(['search' => $search ?: null])) }}" class="bf-chip {{ $filter === 'all' ? 'active' : '' }}">Toutes <span>{{ $counts['all'] }}</span></a>
                    <a href="/admin/messages?{{ http_build_query(array_filter(['filter' => 'unread', 'search' => $search ?: null])) }}" class="bf-chip {{ $filter === 'unread' ? 'active' : '' }}">Non lues <span>{{ $counts['unread'] }}</span></a>
                    <span class="bf-sort-hint">non lues en haut</span>
                </div>
            </div>

            <div class="bf-threads-list">
                @forelse ($conversations as $conversation)
                    @php
                        $last = $conversation->last;
                        $isActive = $selectedUser?->id === $conversation->user_id;
                    @endphp
                    <a href="/admin/messages?{{ $query(['user_id' => $conversation->user_id]) }}" class="bf-thread {{ $isActive ? 'active' : '' }} {{ $conversation->unread ? 'is-unread' : '' }}">
                        @include('admin.partials.avatar', ['name' => $conversation->user?->name, 'id' => $conversation->user_id])
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex justify-content-between align-items-baseline gap-2">
                                <span class="t-name text-truncate">{{ $conversation->user?->name ?? 'Utilisateur supprimé' }}</span>
                                <span class="t-time">{{ $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->format('d/m') }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="t-preview text-truncate">{{ $last->sender === 'admin' ? 'Vous : ' : '' }}{{ $last->content }}</span>
                                @if ($conversation->unread)
                                    <span class="unread">{{ $conversation->unread }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="bf-empty">
                        <i class="bi bi-chat-square"></i>
                        {{ $filter === 'unread' ? 'Aucun message non lu 🎉' : 'Aucune conversation' }}
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="bf-conversation">
            @if ($selectedUser)
                <header class="bf-conv-head">
                    <a href="/admin/messages?{{ $query() }}" class="bf-icon-btn bf-back" aria-label="Retour aux conversations"><i class="bi bi-arrow-left"></i></a>
                    @include('admin.partials.avatar', ['name' => $selectedUser->name, 'id' => $selectedUser->id])
                    <div class="min-w-0 flex-grow-1">
                        <div class="c-name text-truncate">{{ $selectedUser->name }}</div>
                        <div class="c-sub text-truncate">{{ $selectedUser->phone ?: 'sans téléphone' }} · {{ $selectedUser->email }}</div>
                    </div>
                    @if ($phoneDigits)
                        <a href="tel:+{{ $phoneDigits }}" class="bf-icon-btn" title="Appeler" aria-label="Appeler"><i class="bi bi-telephone"></i></a>
                        <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="bf-icon-btn wa" title="WhatsApp" aria-label="Écrire sur WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    @endif
                    <a href="/admin/users/{{ $selectedUser->id }}" class="bf-icon-btn" title="Fiche client" aria-label="Fiche client"><i class="bi bi-person"></i></a>
                </header>

                <div class="bf-messages" id="bfMessages"
                     data-feed="/admin/messages/{{ $selectedUser->id }}/feed"
                     data-last-id="{{ $messages->max('id') ?? 0 }}"
                     data-last-day="{{ $messages->last()?->created_at->toDateString() }}">
                    @php $currentDay = null; @endphp
                    @forelse ($messages as $message)
                        @php $day = $message->created_at->toDateString(); @endphp
                        @if ($day !== $currentDay)
                            <div class="bf-day"><span>{{ $message->created_at->isToday() ? 'Aujourd’hui' : $message->created_at->locale('fr')->isoFormat('dddd D MMMM') }}</span></div>
                            @php $currentDay = $day; @endphp
                        @endif
                        <div class="bf-bubble {{ $message->sender === 'admin' ? 'admin' : 'user' }}" data-id="{{ $message->id }}">
                            @if ($message->transaction_id)
                                <a href="/admin/transactions?search={{ $message->transaction_id }}" class="bf-tx"><i class="bi bi-link-45deg"></i> Transaction #{{ $message->transaction_id }}</a>
                            @endif
                            <div class="bf-bubble-text">{{ $message->content }}</div>
                            <time>{{ $message->created_at->format('H:i') }}</time>
                        </div>
                    @empty
                        <div class="bf-empty" data-empty><i class="bi bi-chat-dots"></i>Aucun message. Écrivez le premier.</div>
                    @endforelse
                </div>

                <div class="bf-status" id="bfStatus" role="status" hidden></div>

                <form method="POST" action="/admin/messages/{{ $selectedUser->id }}/reply" class="bf-composer" id="bfComposer">
                    @csrf
                    <textarea class="form-control" name="content" rows="1" maxlength="2000" placeholder="Votre réponse…" required aria-label="Votre réponse"></textarea>
                    <button class="bf-send" type="submit" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
                </form>
            @else
                <div class="bf-placeholder">
                    <i class="bi bi-chat-left-text"></i>
                    Choisissez une conversation pour répondre
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const list = document.getElementById('bfMessages');
            const form = document.getElementById('bfComposer');
            if (! list || ! form) return;

            const field = form.querySelector('textarea');
            const button = form.querySelector('.bf-send');
            const status = document.getElementById('bfStatus');
            const token = form.querySelector('input[name="_token"]').value;
            // Sur téléphone, « Entrée » va à la ligne : on envoie avec le bouton (évite les envois
            // vides quand le clavier n'a pas encore validé le mot en cours de saisie).
            const touch = window.matchMedia('(pointer: coarse)').matches;
            const sendIcon = '<i class="bi bi-send-fill"></i>';
            let lastId = Number(list.dataset.lastId || 0);
            let lastDay = list.dataset.lastDay || '';
            let sending = false;

            const scrollBottom = function () { list.scrollTop = list.scrollHeight; };
            const showStatus = function (text, kind) {
                status.hidden = ! text;
                status.textContent = text || '';
                status.className = 'bf-status ' + (kind || 'error');
            };

            const append = function (message) {
                if (list.querySelector('[data-id="' + message.id + '"]')) return;
                list.querySelector('[data-empty]')?.remove();

                if (message.day !== lastDay) {
                    const day = document.createElement('div');
                    const label = document.createElement('span');
                    day.className = 'bf-day';
                    label.textContent = message.day_label;
                    day.appendChild(label);
                    list.appendChild(day);
                    lastDay = message.day;
                }

                const bubble = document.createElement('div');
                bubble.className = 'bf-bubble ' + message.sender;
                bubble.dataset.id = message.id;

                if (message.transaction_id) {
                    const link = document.createElement('a');
                    link.className = 'bf-tx';
                    link.href = '/admin/transactions?search=' + message.transaction_id;
                    link.textContent = 'Transaction #' + message.transaction_id;
                    bubble.appendChild(link);
                }

                const text = document.createElement('div');
                text.className = 'bf-bubble-text';
                text.textContent = message.content;
                bubble.appendChild(text);

                const time = document.createElement('time');
                time.textContent = message.time;
                bubble.appendChild(time);

                list.appendChild(bubble);
                lastId = Math.max(lastId, message.id);
            };

            const grow = function () {
                field.style.height = 'auto';
                field.style.height = Math.min(field.scrollHeight, 140) + 'px';
            };

            scrollBottom();
            field.addEventListener('input', grow);
            field.addEventListener('keydown', function (event) {
                if (touch || event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) return;
                event.preventDefault();
                form.requestSubmit();
            });
            if (! touch) field.focus();

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (sending || ! field.value.trim()) return;

                sending = true;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                showStatus('');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                        body: new FormData(form),
                    });

                    if (response.status === 419) throw new Error('Session expirée : rechargez la page puis renvoyez (votre texte est conservé).');
                    if (response.status === 401 || response.status === 403) throw new Error('Vous êtes déconnecté : reconnectez-vous, puis renvoyez.');

                    const data = await response.json().catch(function () { return null; });

                    if (! response.ok || ! data || ! data.success) {
                        throw new Error((data && (data.errors?.content?.[0] || data.message)) || 'Envoi impossible. Réessayez.');
                    }

                    append(data.message);
                    field.value = '';
                    grow();
                    scrollBottom();

                    if (data.push) {
                        showStatus('Envoyé ✓ — mais pas de notification sur son téléphone (' + data.push + '). Il verra le message en ouvrant l’app.', 'info');
                    }
                } catch (error) {
                    showStatus(error instanceof TypeError ? 'Connexion impossible : vérifiez internet puis réessayez.' : error.message, 'error');
                } finally {
                    sending = false;
                    button.disabled = false;
                    button.innerHTML = sendIcon;
                    if (! touch) field.focus();
                }
            });

            // Nouveaux messages du client : affichés automatiquement, sans recharger.
            const poll = async function () {
                if (document.visibilityState !== 'visible' || sending) return;

                try {
                    const response = await fetch(list.dataset.feed + '?after=' + lastId, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (! response.ok) return;

                    const data = await response.json();
                    if (! data.messages || ! data.messages.length) return;

                    const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 140;
                    data.messages.forEach(append);
                    if (nearBottom) scrollBottom();
                } catch (error) {
                    // Réseau coupé : on réessaie au prochain passage.
                }
            };

            setInterval(poll, 6000);
            document.addEventListener('visibilitychange', poll);
        })();
    </script>
@endpush
