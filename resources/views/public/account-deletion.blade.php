@extends('public.layout')

@section('title', 'Supprimer mon compte')
@section('subtitle', 'Binho Flash · suppression définitive de votre compte et de vos données')

@section('content')
    @if (session('deleted'))
        <div class="alert ok">Votre compte a été supprimé. Vous pouvez désinstaller l’application.</div>
    @endif
    @if ($errors->any())
        <div class="alert err">{{ $errors->first() }}</div>
    @endif

    <h2>Depuis l’application</h2>
    <p>Ouvrez Binho Flash › <strong>Profil</strong> › <strong>Supprimer mon compte</strong>, puis confirmez avec votre mot de passe.</p>

    <h2>Depuis cette page</h2>
    <p class="muted">Indiquez l’adresse e-mail et le mot de passe de votre compte. La suppression est immédiate et définitive.</p>
    <form method="POST" action="/suppression-compte">
        @csrf
        <label for="email">Adresse e-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="submit" onclick="return confirm('Supprimer définitivement votre compte Binho Flash ?')">Supprimer définitivement mon compte</button>
    </form>

    <h2>Ce qui est supprimé</h2>
    <ul>
        <li>Nom, e-mail, téléphone, pays et mot de passe.</li>
        <li>Messages au support, abonnements et demandes VIP.</li>
        <li>Connexions et appareil enregistré pour les notifications.</li>
    </ul>

    <h2>Ce qui est conservé</h2>
    <p>L’historique des recharges et retraits (montants, dates, références), sans vos coordonnées, pour nos obligations comptables et en cas de litige.</p>

    <p class="muted">Mot de passe oublié ? Réinitialisez-le depuis l’application, ou écrivez à <a href="mailto:{{ $contact }}">{{ $contact }}</a> depuis l’adresse de votre compte.</p>
@endsection
