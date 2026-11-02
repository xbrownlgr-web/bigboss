<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Transaction refusée</title>
</head>
<body style="margin:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
    <div style="max-width:640px;margin:0 auto;background:#FFFFFF;">
        <div style="background:#1A2E5A;color:#FFFFFF;padding:24px;text-align:center;">
            <h1 style="margin:0;font-size:24px;">BIG BOSS</h1>
        </div>
        <div style="padding:28px;">
            <h2 style="color:#EF4444;margin-top:0;">Transaction refusée</h2>
            <p>Bonjour {{ $transaction->user?->name }},</p>
            <p>Votre transaction n'a pas pu être approuvée par notre équipe.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Type</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->type }}</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Montant</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ number_format($transaction->amount, 0, ',', ' ') }} FCFA</td></tr>
            </table>
            @if ($transaction->rejection_reason)
                <div style="background:#FEE2E2;color:#991B1B;padding:14px;border-radius:8px;">
                    Motif : {{ $transaction->rejection_reason }}
                </div>
            @endif
            <p>Si vous pensez qu'il s'agit d'une erreur, contactez le support.</p>
        </div>
        <div style="background:#F5F5F5;padding:18px;text-align:center;color:#888888;font-size:13px;">
            Support BIG BOSS : support@binhoflash.com
        </div>
    </div>
</body>
</html>
