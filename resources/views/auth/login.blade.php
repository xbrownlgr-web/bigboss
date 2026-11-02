<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion · BIG BOSS Admin</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(900px 500px at 15% -10%, rgba(255, 215, 0, .16), transparent 60%),
                radial-gradient(700px 500px at 110% 110%, rgba(42, 120, 214, .22), transparent 60%),
                #0E1A36;
            color: #111A2E;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 36px 32px 28px;
            border-radius: 20px;
            background: #FFFFFF;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .35);
        }

        .login-logo { display: block; width: 72px; height: 72px; margin: 0 auto 18px; border-radius: 18px; box-shadow: 0 10px 24px rgba(14, 26, 54, .25); }
        .form-label { color: #344054; font-size: .82rem; font-weight: 600; }
        .form-control { min-height: 46px; border-color: #E4E7EF; border-radius: 12px; }
        .form-control:focus { border-color: #8DA2CF; box-shadow: 0 0 0 4px rgba(26, 46, 90, .1); }
        .input-icon { position: relative; }
        .input-icon i { position: absolute; top: 50%; left: 14px; transform: translateY(-50%); color: #98A2B3; }
        .input-icon .form-control { padding-left: 40px; }
        .btn-login { min-height: 48px; border: 0; border-radius: 12px; background: #FFD700; color: #0E1A36; font-weight: 700; }
        .btn-login:hover { background: #F5CC00; color: #0E1A36; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <main class="login-card">
        <img class="login-logo" src="/images/logo.png" alt="BIG BOSS">
        <h1 class="h4 fw-bold text-center mb-1" style="color:#1A2E5A;">Console d’administration</h1>
        <p class="text-center text-secondary mb-4" style="font-size:.9rem;">Connectez-vous pour gérer BIG BOSS</p>

        @if ($errors->any())
            <div class="alert alert-danger d-flex gap-2 align-items-start py-2" style="border-radius:12px;font-size:.88rem;">
                <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="/login" class="vstack gap-3">
            @csrf
            <div>
                <label class="form-label" for="email">E-mail</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </div>
            </div>
            <div>
                <label class="form-label" for="password">Mot de passe</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
            </div>
            <button class="btn btn-login w-100 mt-2" type="submit">Se connecter <i class="bi bi-arrow-right"></i></button>
        </form>

        <p class="text-center text-secondary mt-4 mb-0" style="font-size:.75rem;">
            <i class="bi bi-shield-lock"></i> Accès réservé à l’administration
        </p>
    </main>
</body>
</html>
