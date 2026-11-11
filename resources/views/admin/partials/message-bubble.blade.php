@php $isAdmin = $message->sender === 'admin'; @endphp
<div class="bf-bubble {{ $isAdmin ? 'admin' : 'user' }}" data-id="{{ $message->id }}">
    @if ($message->transaction_id)
        <a href="/admin/transactions?search={{ $message->transaction_id }}" class="bf-tx"><i class="bi bi-link-45deg"></i> Transaction #{{ $message->transaction_id }}</a>
    @endif
    @if ($message->isVoice())
        @if ($message->hasAudio())
            <div class="bf-voice">
                <i class="bi bi-mic-fill"></i>
                <audio controls preload="none" src="/admin/messages/audio/{{ $message->id }}"></audio>
                <span class="bf-voice-len">{{ \App\Services\SupportInbox::duration((int) $message->audio_duration) }}</span>
            </div>
        @else
            <div class="bf-voice expired"><i class="bi bi-mic-mute"></i> Message vocal supprimé (gardé 3 jours)</div>
        @endif
    @else
        <div class="bf-bubble-text">{{ $message->content }}</div>
    @endif
    <time>
        {{ $message->created_at->format('H:i') }}
        @if ($isAdmin)
            <i class="bf-tick bi {{ $message->is_read ? 'bi-check2-all read' : 'bi-check2' }}" title="{{ $message->is_read ? 'Lu par le client' : 'Envoyé' }}"></i>
        @endif
    </time>
</div>
