<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Transaction reçue</title>
</head>
<body style="margin:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
    <div style="max-width:640px;margin:0 auto;background:#FFFFFF;">
        <div style="background:#1A2E5A;color:#FFFFFF;padding:24px;text-align:center;">
            <h1 style="margin:0;font-size:24px;">Binho Flash</h1>
        </div>
        <div style="padding:28px;">
            <h2 style="color:#1A2E5A;margin-top:0;">Votre demande a bien été reçue</h2>
            <p>Bonjour {{ $transaction->user?->name }},</p>
            <p>Nous avons bien reçu votre demande de {{ $transaction->type }}. Elle est maintenant en attente de traitement par notre équipe.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Plateforme</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->platform }}</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Montant</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ number_format($transaction->amount, 0, ',', ' ') }} FCFA</td></tr>
                @if ($transaction->operator)
                    <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Opérateur</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->operator }}</td></tr>
                @endif
                @if ($transaction->reference_code)
                    <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">ID de la transaction</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->reference_code }}</td></tr>
                @endif
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Statut</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->status }}</td></tr>
            </table>
            <p>Vous recevrez une notification dès que la demande sera traitée.</p>
        </div>
        <div style="background:#F5F5F5;padding:18px;text-align:center;color:#888888;font-size:13px;">
            Support Binho Flash : support@binhoflash.com
        </div>
    </div>
</body>
</html>
