<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bienvenue</title>
</head>
<body style="margin:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
    <div style="max-width:640px;margin:0 auto;background:#FFFFFF;">
        <div style="background:#1A2E5A;color:#FFFFFF;padding:24px;text-align:center;">
            <h1 style="margin:0;font-size:24px;">BIG BOSS</h1>
        </div>
        <div style="padding:28px;">
            <h2 style="color:#1A2E5A;margin-top:0;">Bienvenue {{ $user->name }}</h2>
            <p>Votre compte BIG BOSS a bien été créé.</p>
            <p>Vous pouvez maintenant effectuer vos demandes de recharge et de retrait, suivre vos transactions et contacter le support depuis l’application.</p>
            <p>Merci pour votre confiance.</p>
        </div>
        <div style="background:#F5F5F5;padding:18px;text-align:center;color:#888888;font-size:13px;">
            Support BIG BOSS{{ ($supportEmail = \App\Models\AppSetting::value('support_email')) ? ' : ' . $supportEmail : '' }}
        </div>
    </div>
</body>
</html>
