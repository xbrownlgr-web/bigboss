@extends('admin.layout')

@section('title', 'Messages')
@section('subtitle', 'Support utilisateurs · chaque réponse arrive en notification sur le téléphone')

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
        .bf-live { margin-left: auto; display: inline-flex; align-items: center; gap: 5px; color: var(--bf-subtle); font-size: .68rem; }
        .bf-live::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 0 3px rgba(34, 197, 94, .18); }
        .bf-threads-list { flex: 1; overflow-y: auto; overscroll-behavior: contain; }
        .bf-thread { display: flex; align-items: center; gap: 11px; padding: 11px 14px; border-bottom: 1px solid #EEF1EF; color: var(--bf-text); transition: background .12s; }
        .bf-thread:hover { background: var(--bf-surface-2); }
        .bf-thread.active { background: #ECFDF3; box-shadow: inset 3px 0 0 var(--bf-gold); }
        .bf-thread .t-name { font-weight: 600; }
        .bf-thread .t-time { color: var(--bf-subtle); font-size: .72rem; white-space: nowrap; }
        .bf-thread .t-preview { color: var(--bf-muted); font-size: .8rem; }
        .bf-thread .t-preview .bi { margin-right: 2px; }
        .bf-thread .t-read { color: #16A34A; }
        .bf-thread.is-unread .t-name { font-weight: 800; }
        .bf-thread.is-unread .t-time { color: var(--bf-success); font-weight: 700; }
        .bf-thread.is-unread .t-preview { color: var(--bf-text); font-weight: 600; }
        .bf-thread .unread { display: grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--bf-gold); color: var(--bf-navy-deep); font-size: .7rem; font-weight: 800; }

        /* Conversation */
        .bf-conversation { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
        .bf-conv-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--bf-border); background: #fff; }
        .bf-conv-head .c-name { font-weight: 700; }
        .bf-conv-head .c-sub { color: var(--bf-muted); font-size: .76rem; }
        .bf-conv-head .c-subs { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 3px; }
        .bf-conv-head .c-subs .bf-badge { font-size: .66rem; }
        .bf-icon-btn { display: grid; flex: 0 0 auto; place-items: center; width: 36px; height: 36px; border: 1px solid var(--bf-border); border-radius: 10px; background: #fff; color: var(--bf-text-2); }
        .bf-icon-btn:hover { background: var(--bf-surface-2); color: var(--bf-text); }
        .bf-icon-btn.wa { color: #16A34A; }
        .bf-back { display: none; border: 0; }
        .bf-messages { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 16px; background: #F4F7F5; }
        .bf-day { position: sticky; top: 0; z-index: 1; margin: 6px 0 12px; text-align: center; }
        .bf-day span { padding: 3px 10px; border-radius: 999px; background: #E3EAE6; color: var(--bf-muted); font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .bf-bubble { width: fit-content; max-width: min(78%, 560px); margin-bottom: 6px; padding: 8px 12px; border-radius: 16px; line-height: 1.4; overflow-wrap: anywhere; box-shadow: 0 1px 1px rgba(0, 0, 0, .04); }
        .bf-bubble.user + .bf-bubble.admin, .bf-bubble.admin + .bf-bubble.user { margin-top: 12px; }
        .bf-bubble-text { white-space: pre-wrap; }
        .bf-bubble.user { border: 1px solid var(--bf-border); border-bottom-left-radius: 5px; background: #fff; color: var(--bf-text); }
        .bf-bubble.admin { margin-left: auto; border-bottom-right-radius: 5px; background: var(--bf-navy); color: #fff; }
        .bf-bubble time { display: flex; justify-content: flex-end; align-items: center; gap: 4px; margin-top: 2px; font-size: .66rem; opacity: .7; }
        .bf-tick { font-size: .85rem; }
        .bf-tick.read { color: #4ADE80; opacity: 1; }
        .bf-tx { display: inline-flex; align-items: center; gap: 4px; margin-bottom: 2px; color: inherit; font-size: .74rem; font-weight: 700; text-decoration: underline; }
        .bf-voice { display: flex; align-items: center; gap: 8px; }
        .bf-voice i { color: #16A34A; font-size: 1.05rem; }
        .bf-voice audio { height: 36px; max-width: 240px; }
        .bf-voice-len { color: var(--bf-muted); font-size: .72rem; font-weight: 700; }
        .bf-voice.expired { color: var(--bf-muted); font-size: .82rem; font-style: italic; }
        .bf-status { padding: 7px 14px; font-size: .78rem; font-weight: 600; }
        .bf-status.error { background: var(--bf-danger-bg); color: var(--bf-danger); }
        .bf-status.info { background: #FEF3C7; color: #92400E; }
        .bf-quick { display: flex; gap: 6px; padding: 8px 12px 0; overflow-x: auto; scrollbar-width: none; background: #fff; border-top: 1px solid var(--bf-border); }
        .bf-quick::-webkit-scrollbar { display: none; }
        .bf-quick button { flex: 0 0 auto; max-width: 260px; padding: 5px 11px; border: 1px solid var(--bf-border); border-radius: 999px; background: var(--bf-surface-2); color: var(--bf-text-2); font-size: .74rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bf-quick button:hover { border-color: var(--bf-gold); color: var(--bf-text); }
        .bf-composer { display: flex; align-items: flex-end; gap: 8px; padding: 8px 12px calc(10px + env(safe-area-inset-bottom)); background: #fff; }
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
            .bf-voice audio { max-width: 190px; }
        }
    </style>
@endpush

@section('content')
    <div class="bf-chat {{ $selectedUser ? 'has-conversation' : '' }}"
         data-filter="{{ $filter }}" data-search="{{ $search }}" data-open-id="{{ $selectedUser?->id ?? 0 }}">
        <aside class="bf-threads">
            <div class="bf-threads-head">
                <form method="GET" action="/admin/messages" class="bf-search">
                    <i class="bi bi-search"></i>
                    @if ($filter === 'unread')
                        <input type="hidden" name="filter" value="unread">
                    @endif
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Rechercher : nom, e-mail, téléphone" aria-label="Rechercher une conversation">
                </form>
                <div class="bf-filters">
                    <a href="/admin/messages?{{ http_build_query(array_filter(['search' => $search ?: null])) }}" class="bf-chip {{ $filter === 'all' ? 'active' : '' }}">Toutes <span data-count="all">{{ $counts['all'] }}</span></a>
                    <a href="/admin/messages?{{ http_build_query(array_filter(['filter' => 'unread', 'search' => $search ?: null])) }}" class="bf-chip {{ $filter === 'unread' ? 'active' : '' }}">Non lues <span data-count="unread">{{ $counts['unread'] }}</span></a>
                    <span class="bf-live" title="La liste se met à jour toute seule">en direct</span>
                </div>
            </div>

            <div class="bf-threads-list" id="bfThreads">
                @include('admin.partials.message-threads', ['activeUserId' => $selectedUser?->id])
            </div>
        </aside>

        <section class="bf-conversation" id="bfConversation">
            @if ($selectedUser)
                @include('admin.partials.message-conversation')
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
            const chat = document.querySelector('.bf-chat');
            if (! chat) return;

            const threads = document.getElementById('bfThreads');
            const section = document.getElementById('bfConversation');
            const filter = chat.dataset.filter;
            const search = chat.dataset.search;
            const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
            const placeholder = '<div class="bf-placeholder"><i class="bi bi-chat-left-text"></i>Choisissez une conversation pour répondre</div>';
            const ajax = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            let openId = Number(chat.dataset.openId || 0);
            let conversation = null;

            const pageUrl = function (userId) {
                const params = new URLSearchParams();
                if (filter === 'unread') params.set('filter', 'unread');
                if (search) params.set('search', search);
                if (userId) params.set('user_id', userId);
                const query = params.toString();
                return '/admin/messages' + (query ? '?' + query : '');
            };

            const markActive = function () {
                threads.querySelectorAll('.bf-thread').forEach(function (link) {
                    link.classList.toggle('active', Number(link.dataset.userId) === openId);
                });
            };

            // ---------- Ouvrir / fermer une conversation sans recharger la page ----------
            const open = async function (userId, push) {
                conversation?.destroy();
                conversation = null;
                openId = userId;
                markActive();
                chat.classList.add('has-conversation');
                section.innerHTML = '<div class="bf-placeholder"><span class="spinner-border text-success"></span></div>';

                const thread = threads.querySelector('[data-user-id="' + userId + '"]');
                thread?.classList.remove('is-unread');
                thread?.querySelector('.unread')?.remove();

                try {
                    const response = await fetch('/admin/messages/' + userId + '/conversation', { credentials: 'same-origin', headers: ajax });
                    if (! response.ok) throw new Error(String(response.status));
                    const data = await response.json();
                    if (openId !== userId) return;
                    section.innerHTML = data.html;
                    if (push) history.pushState({ userId: userId }, '', pageUrl(userId));
                    conversation = initConversation();
                } catch (error) {
                    location.href = pageUrl(userId);
                }
            };

            const close = function (push) {
                conversation?.destroy();
                conversation = null;
                openId = 0;
                markActive();
                chat.classList.remove('has-conversation');
                section.innerHTML = placeholder;
                if (push) history.pushState({}, '', pageUrl(0));
            };

            threads.addEventListener('click', function (event) {
                const link = event.target.closest('.bf-thread');
                if (! link || event.metaKey || event.ctrlKey || event.shiftKey) return;
                event.preventDefault();
                open(Number(link.dataset.userId), true);
            });

            section.addEventListener('click', function (event) {
                if (! event.target.closest('.bf-back')) return;
                event.preventDefault();
                close(true);
            });

            window.addEventListener('popstate', function () {
                const userId = Number(new URLSearchParams(location.search).get('user_id') || 0);
                userId ? open(userId, false) : close(false);
            });

            // ---------- Liste des conversations : mise à jour toute seule ----------
            const refreshThreads = async function () {
                if (document.visibilityState !== 'visible') return;

                const params = new URLSearchParams({ active: String(openId) });
                if (filter === 'unread') params.set('filter', 'unread');
                if (search) params.set('search', search);

                try {
                    const response = await fetch('/admin/messages/threads?' + params, { credentials: 'same-origin', headers: ajax });
                    if (! response.ok) return;
                    const data = await response.json();
                    const scroll = threads.scrollTop;
                    threads.innerHTML = data.html;
                    threads.scrollTop = scroll;
                    markActive();
                    chat.querySelector('[data-count="all"]').textContent = data.counts.all;
                    chat.querySelector('[data-count="unread"]').textContent = data.counts.unread;
                    document.title = (data.unread_total ? '(' + data.unread_total + ') ' : '') + baseTitle;
                } catch (error) {
                    // Réseau coupé : on réessaie au prochain passage.
                }
            };

            setInterval(refreshThreads, 8000);
            document.addEventListener('visibilitychange', refreshThreads);

            // ---------- Conversation ouverte : envoi, réponses rapides, nouveaux messages ----------
            function initConversation() {
                const list = document.getElementById('bfMessages');
                const form = document.getElementById('bfComposer');
                if (! list || ! form) return null;

                const field = form.querySelector('textarea');
                const button = form.querySelector('.bf-send');
                const status = document.getElementById('bfStatus');
                const token = form.querySelector('input[name="_token"]').value;
                // Sur téléphone, « Entrée » va à la ligne : on envoie avec le bouton.
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
                const tick = function (read) {
                    const icon = document.createElement('i');
                    icon.className = 'bf-tick bi ' + (read ? 'bi-check2-all read' : 'bi-check2');
                    icon.title = read ? 'Lu par le client' : 'Envoyé';
                    return icon;
                };
                const duration = function (seconds) {
                    seconds = Number(seconds || 0);
                    return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
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

                    if (message.audio_url || message.audio_expired) {
                        const voice = document.createElement('div');
                        voice.className = 'bf-voice' + (message.audio_url ? '' : ' expired');
                        if (message.audio_url) {
                            voice.innerHTML = '<i class="bi bi-mic-fill"></i>';
                            const audio = document.createElement('audio');
                            audio.controls = true;
                            audio.preload = 'none';
                            audio.src = message.audio_url;
                            const length = document.createElement('span');
                            length.className = 'bf-voice-len';
                            length.textContent = duration(message.audio_duration);
                            voice.append(audio, length);
                        } else {
                            voice.innerHTML = '<i class="bi bi-mic-mute"></i> Message vocal supprimé (gardé 3 jours)';
                        }
                        bubble.appendChild(voice);
                    } else {
                        const text = document.createElement('div');
                        text.className = 'bf-bubble-text';
                        text.textContent = message.content;
                        bubble.appendChild(text);
                    }

                    const time = document.createElement('time');
                    time.textContent = message.time;
                    if (message.sender === 'admin') time.appendChild(tick(message.read));
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

                section.querySelectorAll('.bf-quick button').forEach(function (quick) {
                    quick.addEventListener('click', function () {
                        field.value = field.value.trim() ? field.value.trim() + '\n' + quick.dataset.text : quick.dataset.text;
                        grow();
                        field.focus();
                    });
                });

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
                            headers: { ...ajax, 'X-CSRF-TOKEN': token },
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
                        refreshThreads();

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

                // Nouveaux messages du client et coches « Lu » : mis à jour sans recharger.
                const poll = async function () {
                    if (document.visibilityState !== 'visible' || sending) return;

                    try {
                        const response = await fetch(list.dataset.feed + '?after=' + lastId, { credentials: 'same-origin', headers: ajax });
                        if (! response.ok) return;

                        const data = await response.json();
                        (data.read_ids || []).forEach(function (id) {
                            const icon = list.querySelector('.bf-bubble.admin[data-id="' + id + '"] .bf-tick:not(.read)');
                            if (icon) icon.replaceWith(tick(true));
                        });
                        if (! data.messages || ! data.messages.length) return;

                        const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 140;
                        data.messages.forEach(append);
                        if (nearBottom) scrollBottom();
                    } catch (error) {
                        // Réseau coupé : on réessaie au prochain passage.
                    }
                };

                const timer = setInterval(poll, 5000);
                document.addEventListener('visibilitychange', poll);

                return {
                    destroy: function () {
                        clearInterval(timer);
                        document.removeEventListener('visibilitychange', poll);
                    },
                };
            }

            conversation = initConversation();
        })();
    </script>
@endpush
