<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Binho Flash</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #1A2E5A; --navy-deep: #0E1B3A; --gold: #FFD700; --muted: #6B7280; --border: #E6EAF2; --bg: #F4F6FB; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: #1F2937; font-family: Inter, system-ui, sans-serif; line-height: 1.6; }
        header { background: linear-gradient(135deg, var(--navy-deep), var(--navy)); color: #fff; padding: 28px 16px 40px; }
        header .inner, main { max-width: 760px; margin: 0 auto; }
        header a { color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; font-weight: 800; }
        header img { width: 34px; height: 34px; border-radius: 10px; }
        header h1 { margin: 18px 0 4px; font-size: 1.7rem; line-height: 1.25; }
        header p { margin: 0; color: #B9C2D3; }
        main { padding: 0 16px 48px; margin-top: -22px; }
        .card { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 6px 18px rgba(14, 27, 58, .06); }
        h2 { color: var(--navy); font-size: 1.1rem; margin: 26px 0 6px; }
        h2:first-child { margin-top: 0; }
        ul { padding-left: 20px; }
        a { color: var(--navy); }
        label { display: block; font-weight: 700; margin: 14px 0 6px; color: var(--navy); }
        input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px; font: inherit; }
        button { margin-top: 18px; width: 100%; padding: 13px; border: 0; border-radius: 12px; background: #DC2626; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
        .alert { padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; }
        .alert.ok { background: #DCFCE7; color: #166534; }
        .alert.err { background: #FEE2E2; color: #991B1B; }
        .muted { color: var(--muted); font-size: .92rem; }
        footer { text-align: center; color: var(--muted); font-size: .85rem; margin-top: 22px; }
    </style>
</head>
<body>
    <header>
        <div class="inner">
            <a href="/"><img src="/images/logo.png" alt="">Binho Flash</a>
            <h1>@yield('title')</h1>
            <p>@yield('subtitle')</p>
        </div>
    </header>
    <main>
        <div class="card">@yield('content')</div>
        <footer>
            <a href="/confidentialite">Politique de confidentialité</a> · <a href="/suppression-compte">Supprimer mon compte</a>
        </footer>
    </main>
</body>
</html>
