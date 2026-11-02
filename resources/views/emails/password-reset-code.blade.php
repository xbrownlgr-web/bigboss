<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Réinitialisation du mot de passe</title>
</head>
<body style="margin:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
    <div style="max-width:640px;margin:0 auto;background:#FFFFFF;">
        <div style="background:#1A2E5A;color:#FFFFFF;padding:24px;text-align:center;">
            <h1 style="margin:0;font-size:24px;">BIG BOSS</h1>
        </div>
        <div style="padding:28px;">
            <h2 style="color:#1A2E5A;margin-top:0;">Bonjour {{ $user->name }}</h2>
            <p>Nous avons reçu une demande de réinitialisation du mot de passe de votre compte.</p>
            <p>Entrez ce code dans l’application pour choisir un nouveau mot de passe :</p>
            <div style="margin:24px 0;padding:18px;text-align:center;background:#F5F5F5;border-radius:12px;">
                <span style="font-size:32px;font-weight:800;letter-spacing:8px;color:#1A2E5A;">{{ $code }}</span>
            </div>
            <p>Ce code expire dans 60 minutes.</p>
            <p>Si vous n’avez pas demandé cette opération, vous pouvez ignorer cet e-mail.</p>
        </div>
        <div style="background:#F5F5F5;padding:18px;text-align:center;color:#888888;font-size:13px;">
            Support BIG BOSS : support@binhoflash.com
        </div>
    </div>
</body>
</html>
