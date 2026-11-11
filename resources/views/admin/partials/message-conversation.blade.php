@php
    $phoneDigits = preg_replace('/\D+/', '', (string) $selectedUser->phone);
    // Réponses rapides : un clic les place dans la zone de texte (modifiables avant l'envoi).
    $quickReplies = [
        'Bonjour 👋 Je regarde votre demande tout de suite.',
        '✅ C’est fait : votre opération est validée.',
        'Merci de m’envoyer la capture du paiement avec l’ID de la transaction reçu par SMS.',
        'Votre abonnement est actif ✅ Bonne chance avec les coupons !',
    ];
@endphp
<header class="bf-conv-head">
    <a href="/admin/messages" class="bf-icon-btn bf-back" aria-label="Retour aux conversations"><i class="bi bi-arrow-left"></i></a>
    @include('admin.partials.avatar', ['name' => $selectedUser->name, 'id' => $selectedUser->id])
    <div class="min-w-0 flex-grow-1">
        <div class="c-name text-truncate">{{ $selectedUser->name }}</div>
        <div class="c-sub text-truncate">{{ $selectedUser->phone ?: 'sans téléphone' }} · {{ $selectedUser->email }}</div>
        @if ($subscriptions->isNotEmpty())
            <div class="c-subs">
                @foreach ($subscriptions as $subscription)
                    <span class="bf-badge vip"><i class="bi bi-gem"></i>{{ \App\Models\AppSetting::offerName($subscription->level) }} · {{ $subscription->ends_at->timezone(config('app.timezone'))->format('d/m') }}</span>
                @endforeach
            </div>
        @endif
    </div>
    @if ($phoneDigits)
        <a href="tel:+{{ $phoneDigits }}" class="bf-icon-btn" title="Appeler" aria-label="Appeler"><i class="bi bi-telephone"></i></a>
        <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="bf-icon-btn wa" title="WhatsApp" aria-label="Écrire sur WhatsApp"><i class="bi bi-whatsapp"></i></a>
    @endif
    <a href="/admin/users/{{ $selectedUser->id }}" class="bf-icon-btn" title="Fiche client (abonnements)" aria-label="Fiche client"><i class="bi bi-person"></i></a>
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
        @include('admin.partials.message-bubble', ['message' => $message])
    @empty
        <div class="bf-empty" data-empty><i class="bi bi-chat-dots"></i>Aucun message. Écrivez le premier.</div>
    @endforelse
</div>

<div class="bf-status" id="bfStatus" role="status" hidden></div>

<div class="bf-quick" aria-label="Réponses rapides">
    @foreach ($quickReplies as $reply)
        <button type="button" data-text="{{ $reply }}">{{ $reply }}</button>
    @endforeach
</div>

<form method="POST" action="/admin/messages/{{ $selectedUser->id }}/reply" class="bf-composer" id="bfComposer">
    @csrf
    <textarea class="form-control" name="content" rows="1" maxlength="2000" placeholder="Votre réponse…" required aria-label="Votre réponse"></textarea>
    <button class="bf-send" type="submit" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
</form>
