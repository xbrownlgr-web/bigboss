<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bienvenue sur BIG BOSS</title>
</head>
<body style="margin:0;padding:16px 0;background:#F2F5F3;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
    <div style="max-width:600px;margin:0 auto;background:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #E2E8F0;">
        <div style="background:#0C2A1E;color:#FFFFFF;padding:24px 28px;text-align:center;">
            <div style="display:inline-block;padding:4px 12px;border-radius:999px;background:rgba(34,197,94,0.18);color:#86EFAC;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;">
                BIG BOSS · PARIS SPORTIFS
            </div>
            <h1 style="margin:0;font-size:24px;font-weight:800;">Bienvenue sur BIG BOSS</h1>
        </div>
        <div style="padding:26px 28px;font-size:15px;line-height:1.65;color:#1E293B;">
            <h2 style="color:#0C2A1E;margin-top:0;">Bonjour {{ $user->name }},</h2>
            <p>Votre compte <strong>BIG BOSS</strong> a bien été créé.</p>
            <p>Découvrez chaque jour nos <strong>5 catégories de pronostics</strong> (Parieurs, Score exact, Grosses cotes, Cartons et Buteurs), effectuez vos recharges et retraits rapidement et contactez notre équipe directement depuis l’application.</p>
            <p style="margin-bottom:0;">Merci pour votre confiance et bons paris !</p>
        </div>
        <div style="background:#F8FAFC;padding:16px 24px;text-align:center;color:#64748B;font-size:12px;border-top:1px solid #E2E8F0;">
            Support BIG BOSS{{ ($supportEmail = \App\Models\AppSetting::value('support_email')) ? ' : ' . $supportEmail : '' }}
        </div>
    </div>
</body>
</html>
