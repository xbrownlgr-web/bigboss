@forelse ($conversations as $conversation)
    @php
        $last = $conversation->last;
        $href = '/admin/messages?' . http_build_query(array_filter([
            'filter' => $filter === 'unread' ? 'unread' : null,
            'search' => $search ?: null,
            'user_id' => $conversation->user_id,
        ]));
    @endphp
    <a href="{{ $href }}" data-user-id="{{ $conversation->user_id }}"
       class="bf-thread {{ $activeUserId === $conversation->user_id ? 'active' : '' }} {{ $conversation->unread ? 'is-unread' : '' }}">
        @include('admin.partials.avatar', ['name' => $conversation->user?->name, 'id' => $conversation->user_id])
        <div class="min-w-0 flex-grow-1">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <span class="t-name text-truncate">{{ $conversation->user?->name ?? 'Utilisateur supprimé' }}</span>
                <span class="t-time">{{ $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->format('d/m') }}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="t-preview text-truncate">
                    @if ($last->sender === 'admin')
                        <i class="bi {{ $last->is_read ? 'bi-check2-all t-read' : 'bi-check2' }}"></i>
                    @endif
                    {{ $last->content }}
                </span>
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
