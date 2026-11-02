<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Transaction approuvée</title>
</head>
<body style="margin:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
    <div style="max-width:640px;margin:0 auto;background:#FFFFFF;">
        <div style="background:#1A2E5A;color:#FFFFFF;padding:24px;text-align:center;">
            <h1 style="margin:0;font-size:24px;">BIG BOSS</h1>
        </div>
        <div style="padding:28px;">
            <h2 style="color:#22C55E;margin-top:0;">Transaction approuvée</h2>
            <p>Bonjour {{ $transaction->user?->name }},</p>
            <p>Bonne nouvelle : votre transaction a été approuvée et traitée avec succès.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Type</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->type }}</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Plateforme</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ $transaction->platform }}</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #E0E0E0;">Montant</td><td style="padding:8px;border-bottom:1px solid #E0E0E0;text-align:right;">{{ number_format($transaction->amount, 0, ',', ' ') }} FCFA</td></tr>
            </table>
            <p>Merci d'utiliser BIG BOSS.</p>
        </div>
        <div style="background:#F5F5F5;padding:18px;text-align:center;color:#888888;font-size:13px;">
            Support BIG BOSS : support@binhoflash.com
        </div>
    </div>
</body>
</html>
