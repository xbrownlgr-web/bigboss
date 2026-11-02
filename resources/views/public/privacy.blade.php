@extends('public.layout')

@section('title', 'Politique de confidentialité')
@section('subtitle', 'Dernière mise à jour : 15 octobre 2026')

@section('content')
    <h2>Qui sommes-nous ?</h2>
    <p>Binho Flash est une application mobile qui facilite les recharges et retraits de comptes de paris par Mobile Money et publie des pronostics football. Contact : <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>

    <h2>Réservé aux personnes majeures</h2>
    <p>L’application est réservée aux personnes de 18 ans ou plus. L’âge est confirmé à l’inscription. Si nous apprenons qu’un compte appartient à un mineur, il est supprimé.</p>

    <h2>Données que nous collectons</h2>
    <ul>
        <li><strong>Compte</strong> : nom, adresse e-mail, numéro de téléphone, pays, mot de passe (chiffré).</li>
        <li><strong>Opérations</strong> : recharges et retraits demandés (montant, opérateur Mobile Money, numéro utilisé, identifiant de compte joueur, références et statut).</li>
        <li><strong>Support</strong> : messages échangés avec notre équipe.</li>
        <li><strong>Abonnements VIP</strong> : niveau, clé d’accès, dates de début et de fin.</li>
        <li><strong>Appareil</strong> : jeton de notification, pour vous prévenir (opérations, pronostics, VIP).</li>
        <li><strong>Publicité</strong> : Google AdMob peut utiliser l’identifiant publicitaire de votre appareil pour afficher et mesurer des publicités.</li>
    </ul>

    <h2>Pourquoi nous les utilisons</h2>
    <ul>
        <li>Traiter vos recharges, retraits et abonnements VIP, et vous en informer.</li>
        <li>Répondre à vos messages au support.</li>
        <li>Vous envoyer les notifications que vous avez acceptées.</li>
        <li>Prévenir la fraude et respecter nos obligations comptables.</li>
    </ul>

    <h2>Avec qui elles sont partagées</h2>
    <p>Nous ne vendons pas vos données. Elles sont traitées par nos prestataires techniques, uniquement pour faire fonctionner le service : hébergement (Render, Aiven), envoi des notifications (Expo, Google Firebase), envoi des e-mails (Brevo) et publicité (Google AdMob). Les paiements Mobile Money passent par votre opérateur.</p>

    <h2>Durée de conservation</h2>
    <p>Vos données de compte sont conservées tant que votre compte existe. L’historique des opérations est conservé pour nos obligations comptables et en cas de litige, sans vos coordonnées après la suppression du compte.</p>

    <h2>Vos droits</h2>
    <p>Vous pouvez consulter et corriger vos informations en écrivant au support, et <strong>supprimer votre compte à tout moment</strong> depuis l’application (Profil › Supprimer mon compte) ou sur la page <a href="/suppression-compte">binhoflash.com/suppression-compte</a>.</p>

    <h2>Jeu responsable</h2>
    <p>Les pronostics ne sont jamais garantis : des pertes sont normales. Ne misez que ce que vous pouvez vous permettre de perdre. Si le jeu devient un problème, faites une pause et parlez-en à un proche ou à un professionnel.</p>

    <h2>Sécurité</h2>
    <p>Les échanges avec nos serveurs sont chiffrés (HTTPS) et les mots de passe sont stockés sous forme chiffrée.</p>

    <h2>Modifications</h2>
    <p>Nous pouvons mettre à jour cette politique. La date de mise à jour figure en haut de cette page.</p>
@endsection
