@php
    $avatarPalette = ['#1A2E5A', '#2563EB', '#0F766E', '#7C3AED', '#B45309', '#BE123C', '#0369A1', '#4D7C0F'];
    $avatarName = trim($name ?? '') ?: '?';
    $avatarWords = preg_split('/\s+/', $avatarName);
    $avatarInitials = mb_strtoupper(mb_substr($avatarWords[0], 0, 1) . (isset($avatarWords[1]) ? mb_substr($avatarWords[1], 0, 1) : ''));
    $avatarColor = $avatarPalette[($id ?? 0) % count($avatarPalette)];
@endphp
<span class="bf-avatar {{ $size ?? '' }}" style="background: {{ $avatarColor }};" aria-hidden="true">{{ $avatarInitials }}</span>
