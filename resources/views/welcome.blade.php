@php
    $operators = $depositOperators ?: ['MTN MoMo', 'Moov Money', 'Celtiis'];
    $vipLevels = [
        'Silver' => ['accent' => '#C9D2E3', 'pitch' => 'Le coupon le plus sûr de chaque séance', 'items' => ['2 coupons par jour', 'Cote 2,10 ou plus', 'Matin et soir']],
        'Gold' => ['accent' => '#22C55E', 'pitch' => 'Les matchs les plus étudiés du marché', 'items' => ['3 coupons par jour', 'Avant 13 h, vers 17 h, le soir', 'Sélections exclusives']],
        'Platinum' => ['accent' => '#B794F6', 'pitch' => 'Pour ceux qui jouent toute la journée', 'items' => ['4 coupons et plus par jour', 'Lives et matchs de minuit', 'Scores exacts parfois']],
    ];
    $socials = [
        'telegram' => ['Telegram', 'M21.5 4.2 2.9 11.4c-1.3.5-1.3 1.2-.2 1.6l4.8 1.5 1.8 5.6c.2.6.4.8.9.8.4 0 .6-.2.8-.4l2.3-2.2 4.8 3.5c.9.5 1.5.2 1.7-.8l3.2-15c.3-1.3-.5-1.9-1.5-1.3Zm-3.3 4L9.9 15.7l-.3 3.3-1.5-4.9 9.6-6.1c.5-.3.9 0 .5.3Z'],
        'whatsapp' => ['WhatsApp', 'M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.6 0-3.1-.4-4.4-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1.1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z'],
        'facebook' => ['Facebook', 'M14 8V6.3c0-.8.2-1.3 1.4-1.3H17V2.1c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9V8H8.5v3.2h2.4V22H14V11.2h2.6L17 8h-3Z'],
        'tiktok' => ['TikTok', 'M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.2v12.6a2.6 2.6 0 1 1-1.8-2.5V9.8a5.9 5.9 0 1 0 5 5.8V9.3a7.4 7.4 0 0 0 4.3 1.4V7.5a4.3 4.3 0 0 1-3.2-1.7Z'],
        'instagram' => ['Instagram', 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4ZM17.3 5.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4ZM21.9 7.9c-.1-1.6-.4-3-1.6-4.2S17.7 2.2 16.1 2.1C14.4 2 9.6 2 7.9 2.1 6.3 2.2 4.9 2.5 3.7 3.7S2.2 6.3 2.1 7.9C2 9.6 2 14.4 2.1 16.1c.1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.7.1 6.5.1 8.2 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.1-1.7.1-6.5 0-8.2Zm-2.1 10.5a3.3 3.3 0 0 1-1.9 1.9c-1.3.5-4.4.4-5.9.4s-4.6.1-5.9-.4a3.3 3.3 0 0 1-1.9-1.9c-.5-1.3-.4-4.4-.4-5.9s-.1-4.6.4-5.9a3.3 3.3 0 0 1 1.9-1.9c1.3-.5 4.4-.4 5.9-.4s4.6-.1 5.9.4a3.3 3.3 0 0 1 1.9 1.9c.5 1.3.4 4.4.4 5.9s.1 4.6-.4 5.9Z'],
    ];
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BIG BOSS · Votre caisse 1xBet et Melbet, ouverte 24h/24</title>
    <meta name="description" content="Rechargez votre compte 1xBet ou Melbet et retirez vos gains par Mobile Money, 24h/24, depuis l’application BIG BOSS. Pronostics du jour, coupons VIP et assistant disponible jour et nuit.">
    <meta name="theme-color" content="#061A12">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    {{-- Aperçu des liens partagés (WhatsApp, Facebook, Telegram…) : adresse et image de BIG BOSS. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="BIG BOSS">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="BIG BOSS · Votre caisse 1xBet et Melbet, ouverte 24h/24">
    <meta property="og:description" content="Recharges et retraits par Mobile Money, pronostics du jour, coupons VIP et assistant 24h/24.">
    <meta property="og:image" content="{{ url('/images/site/partage-bigboss.jpg') }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="BIG BOSS — caisse 1xBet et Melbet 24h/24">
    <meta property="og:url" content="{{ url('/') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="BIG BOSS · Votre caisse 1xBet et Melbet, ouverte 24h/24">
    <meta name="twitter:image" content="{{ url('/images/site/partage-bigboss.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #061A12;
            --ink-2: #0F1D40;
            --navy: #0C2A1E;
            --navy-3: #24427F;
            --gold: #22C55E;
            --gold-deep: #16A34A;
            --paper: #F4F6FB;
            --white: #FFFFFF;
            --text: #0C2A1E;
            --muted: #5B6785;
            --soft: #C6CEE0;
            --line: #E2E7F1;
            --green: #16A34A;
            --radius: 22px;
            --display: 'Bricolage Grotesque', 'Plus Jakarta Sans', system-ui, sans-serif;
            --body: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--white); color: var(--text); font-family: var(--body); font-size: 16px; line-height: 1.6; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
        img { display: block; max-width: 100%; height: auto; }
        a { color: inherit; }
        h1, h2, h3 { margin: 0; font-family: var(--display); font-weight: 800; letter-spacing: -0.025em; line-height: 1.05; }
        p { margin: 0; }
        .wrap { width: 100%; max-width: 1180px; margin: 0 auto; padding: 0 16px; }
        @media (min-width: 768px) { .wrap { padding: 0 32px; } }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 3px; border-radius: 8px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* ---------- Boutons ---------- */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 54px; padding: 0 26px; border: 0; border-radius: 16px; font-family: var(--body); font-size: 1rem; font-weight: 800; text-decoration: none; cursor: pointer; transition: transform .2s ease, box-shadow .2s ease, background .2s ease; }
        .btn svg { width: 22px; height: 22px; flex: none; }
        .btn-gold { background: linear-gradient(135deg, #4ADE80, var(--gold) 45%, var(--gold-deep)); color: var(--ink); box-shadow: 0 14px 34px -10px rgba(255, 196, 0, .6); }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 20px 40px -12px rgba(255, 196, 0, .7); }
        .btn-ghost { background: rgba(255, 255, 255, .06); color: var(--white); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18); }
        .btn-ghost:hover { background: rgba(255, 255, 255, .12); }
        .btn-small { min-height: 42px; padding: 0 16px; border-radius: 12px; font-size: .9rem; }

        /* ---------- En-tête ---------- */
        .nav { position: fixed; inset: 0 0 auto; z-index: 50; transition: background .25s ease, box-shadow .25s ease; }
        .nav.scrolled { background: rgba(10, 20, 48, .86); box-shadow: 0 1px 0 rgba(255, 255, 255, .08); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
        .nav .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 72px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--white); text-decoration: none; font-family: var(--display); font-size: 1.3rem; font-weight: 800; letter-spacing: -0.02em; }
        .brand img { width: 38px; height: 38px; filter: drop-shadow(0 0 12px rgba(255, 196, 0, .45)); }
        .brand b { color: var(--gold); font-weight: 800; }
        .brand img.tile { border-radius: 10px; filter: none; }
        footer .brand b { color: #15803D; }
        .nav-links { display: none; gap: 30px; }
        .nav-links a { color: var(--soft); font-size: .95rem; font-weight: 600; text-decoration: none; transition: color .2s; }
        .nav-links a:hover { color: var(--white); }
        @media (min-width: 960px) { .nav-links { display: flex; } }

        /* ---------- Héros : tout est centré ---------- */
        .hero { position: relative; overflow: hidden; padding: 136px 0 0; background: var(--ink); color: var(--white); text-align: center; isolation: isolate; }
        .hero::before { content: ''; position: absolute; inset: 0; z-index: -2; background:
            radial-gradient(640px 420px at 50% 4%, rgba(255, 196, 0, .2), transparent 64%),
            radial-gradient(900px 620px at 50% 82%, rgba(52, 102, 214, .4), transparent 66%),
            linear-gradient(180deg, var(--ink), var(--ink-2)); }
        .hero::after { content: ''; position: absolute; inset: 0; z-index: -1; opacity: .5; background-image: linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px); background-size: 56px 56px; -webkit-mask-image: radial-gradient(ellipse at 50% 30%, #000 20%, transparent 72%); mask-image: radial-gradient(ellipse at 50% 30%, #000 20%, transparent 72%); }
        .hero-center { display: flex; flex-direction: column; align-items: center; }
        .hero-logo { width: min(340px, 74%); height: auto; margin: 0 auto 22px; display: block; filter: drop-shadow(0 8px 24px rgba(0,0,0,.45)); }
        .eyebrow { display: inline-flex; align-items: center; gap: 10px; padding: 7px 14px 7px 8px; border: 1px solid rgba(255, 215, 0, .28); border-radius: 999px; background: rgba(255, 215, 0, .08); color: #86EFAC; font-size: .85rem; font-weight: 700; }
        .eyebrow .pulse { position: relative; width: 22px; height: 22px; border-radius: 50%; background: rgba(34, 197, 94, .18); }
        .eyebrow .pulse::after { content: ''; position: absolute; inset: 7px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 0 0 rgba(34, 197, 94, .7); animation: pulse 2s infinite; }
        @keyframes pulse { 70% { box-shadow: 0 0 0 9px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
        .hero h1 { max-width: 940px; margin-top: 24px; font-size: clamp(2.5rem, 6.6vw, 4.9rem); }
        .hero h1 em { font-style: normal; background: linear-gradient(120deg, #4ADE80, var(--gold) 40%, var(--gold-deep)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero .lead { max-width: 640px; margin-top: 22px; color: var(--soft); font-size: clamp(1.05rem, 2.2vw, 1.2rem); line-height: 1.65; }
        .hero-ctas { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 34px; }
        .hero-meta { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px 14px; margin-top: 16px; color: #8C98B5; font-size: .88rem; }
        .hero-meta .new { padding: 3px 10px; border-radius: 999px; background: rgba(34, 197, 94, .14); color: #4ADE80; font-size: .78rem; font-weight: 800; }
        .operators { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; margin-top: 26px; }
        .operators small { margin-right: 4px; color: #8C98B5; font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; background: rgba(255, 255, 255, .07); color: var(--white); font-size: .85rem; font-weight: 700; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .1); }
        .chip i { width: 8px; height: 8px; border-radius: 50%; }

        /* Vitrine : trois téléphones en éventail, celui du milieu devant */
        .showcase { position: relative; width: 100%; max-width: 940px; height: 560px; margin-top: 60px; overflow: hidden; -webkit-mask-image: linear-gradient(180deg, #000 78%, transparent); mask-image: linear-gradient(180deg, #000 78%, transparent); }
        .phone { position: absolute; padding: 9px; border-radius: 42px; background: linear-gradient(160deg, #0C2A1E, #04120B 40%); box-shadow: 0 40px 80px -20px rgba(0, 0, 0, .7), inset 0 0 0 1.5px rgba(255, 255, 255, .12); }
        .phone img { width: 100%; border-radius: 34px; }
        .phone::before { content: ''; position: absolute; top: 17px; left: 50%; width: 70px; height: 20px; margin-left: -35px; border-radius: 12px; background: #04120B; z-index: 2; }
        .phone-center { top: 0; left: calc(50% - 150px); width: 300px; z-index: 3; }
        .phone-left, .phone-right { top: 64px; width: 262px; z-index: 2; opacity: .88; }
        .phone-left { left: calc(50% - 405px); transform: rotate(-7deg); }
        .phone-right { left: calc(50% + 143px); transform: rotate(7deg); }
        .float { position: absolute; z-index: 4; display: flex; align-items: center; gap: 12px; padding: 12px 16px 12px 12px; border-radius: 18px; background: rgba(255, 255, 255, .97); color: var(--text); text-align: left; box-shadow: 0 24px 50px -18px rgba(0, 0, 0, .55); animation: float 6s ease-in-out infinite; }
        .float .ico { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; flex: none; }
        .float .ico svg { width: 22px; height: 22px; }
        .float strong { display: block; font-size: .92rem; line-height: 1.25; }
        .float span { color: var(--muted); font-size: .8rem; }
        .float-1 { top: 300px; left: calc(50% - 470px); }
        .float-2 { top: 110px; left: calc(50% + 250px); animation-delay: -3s; }
        @keyframes float { 50% { transform: translateY(-10px); } }
        @media (max-width: 760px) {
            .showcase { height: 470px; margin-top: 48px; }
            .phone-center { left: calc(50% - 118px); width: 236px; }
            .phone-left, .phone-right { top: 52px; width: 200px; opacity: .7; }
            .phone-left { left: calc(50% - 205px); }
            .phone-right { left: calc(50% + 5px); }
            /* Une seule bulle sur téléphone : elle ne cache pas l'écran du milieu. */
            .float-1 { top: 300px; left: 0; }
            .float-2 { display: none; }
        }

        /* ---------- Chiffres clés ---------- */
        .stats { position: relative; z-index: 5; margin-top: -64px; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; overflow: hidden; border-radius: var(--radius); background: var(--line); box-shadow: 0 30px 60px -30px rgba(15, 27, 61, .35); }
        @media (min-width: 900px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } }
        .stat { padding: 24px 18px; background: var(--white); text-align: center; }
        .stat b { display: block; font-family: var(--display); font-size: clamp(1.6rem, 3.6vw, 2.2rem); font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; }
        .stat b em { font-style: normal; color: var(--gold-deep); }
        .stat span { display: block; margin-top: 6px; color: var(--muted); font-size: .9rem; line-height: 1.4; }

        /* ---------- Sections ---------- */
        section.block { padding: 104px 0; overflow: hidden; }
        .kicker { display: inline-block; margin-bottom: 14px; color: #B45309; font-size: .8rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .dark .kicker { color: #34D399; }
        .heading { max-width: 760px; margin: 0 auto; text-align: center; }
        .heading h2 { font-size: clamp(2rem, 5vw, 3.1rem); }
        .heading p { margin-top: 16px; color: var(--muted); font-size: 1.1rem; }
        .dark { background: var(--ink); color: var(--white); }
        .dark .heading p, .dark p { color: var(--soft); }
        .paper { background: var(--paper); }

        /* Services : cartes au contenu centré */
        .services { display: grid; gap: 16px; margin-top: 56px; }
        @media (min-width: 700px) { .services { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1040px) { .services { grid-template-columns: repeat(3, 1fr); } }
        .service { position: relative; padding: 34px 28px 30px; border: 1px solid var(--line); border-radius: var(--radius); background: var(--white); text-align: center; transition: transform .25s ease, box-shadow .25s ease, border-color .25s; }
        .service:hover { transform: translateY(-4px); border-color: #D3DBEA; box-shadow: 0 26px 50px -30px rgba(15, 27, 61, .4); }
        .service .ico { display: grid; place-items: center; width: 56px; height: 56px; margin: 0 auto 20px; border-radius: 18px; background: var(--navy); color: var(--gold); }
        .service .ico svg { width: 26px; height: 26px; }
        .service h3 { font-size: 1.35rem; letter-spacing: -0.015em; }
        .service p { max-width: 320px; margin: 10px auto 0; color: var(--muted); font-size: .98rem; }
        .service.featured { background: linear-gradient(150deg, var(--navy), var(--ink)); border-color: transparent; color: var(--white); }
        .service.featured p { color: var(--soft); }
        .service.featured .ico { background: var(--gold); color: var(--ink); }
        .tag { position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 999px; background: rgba(255, 215, 0, .16); color: #86EFAC; font-size: .75rem; font-weight: 800; }

        /* Comment ça marche et assistant : téléphone au milieu, deux étapes de chaque côté */
        .tabs { margin-top: 44px; }
        .tabs input { position: absolute; opacity: 0; pointer-events: none; }
        .tab-labels { display: flex; width: max-content; gap: 6px; margin: 0 auto; padding: 6px; border-radius: 16px; background: var(--white); box-shadow: inset 0 0 0 1px var(--line); }
        .tab-labels label { padding: 11px 24px; border-radius: 12px; color: var(--muted); font-weight: 800; cursor: pointer; transition: background .2s, color .2s; }
        #tab-recharge:checked ~ .tab-labels label[for="tab-recharge"],
        #tab-retrait:checked ~ .tab-labels label[for="tab-retrait"] { background: var(--navy); color: var(--white); }
        #tab-recharge:focus-visible ~ .tab-labels label[for="tab-recharge"],
        #tab-retrait:focus-visible ~ .tab-labels label[for="tab-retrait"] { outline: 3px solid var(--gold); }
        .tabs .panel { display: none; margin-top: 44px; }
        #tab-recharge:checked ~ .panel-recharge, #tab-retrait:checked ~ .panel-retrait { display: grid; }
        .trio { display: grid; gap: 16px; }
        /* Sur téléphone : l'image d'abord, puis les étapes dans l'ordre. */
        .trio > .middle { position: relative; order: -1; margin-bottom: 20px; isolation: isolate; }
        @media (min-width: 980px) {
            .trio { grid-template-columns: 1fr 340px 1fr; gap: 40px; align-items: center; }
            .trio > .middle { order: 0; margin-bottom: 0; }
        }
        .side { display: grid; gap: 16px; margin: 0; padding: 0; list-style: none; }
        .steps { counter-reset: step; }
        .steps.second { counter-reset: step 2; }
        .steps li { padding: 26px 22px; border: 1px solid var(--line); border-radius: 20px; background: var(--white); text-align: center; counter-increment: step; }
        .steps li::before { content: counter(step); display: grid; place-items: center; width: 46px; height: 46px; margin: 0 auto 14px; border-radius: 15px; background: var(--gold); color: var(--ink); font-family: var(--display); font-size: 1.3rem; font-weight: 800; }
        .steps strong, .feature strong { display: block; font-size: 1.08rem; line-height: 1.3; }
        .steps span { display: block; margin-top: 6px; color: var(--muted); font-size: .95rem; }
        .shot { position: relative; width: 100%; max-width: 340px; margin: 0 auto; padding: 10px; border-radius: 30px; background: var(--ink); box-shadow: 0 40px 80px -36px rgba(15, 27, 61, .6); }
        .shot img { border-radius: 22px; }
        .shot figcaption { position: absolute; left: 50%; bottom: -18px; transform: translateX(-50%); padding: 10px 16px; border-radius: 14px; background: var(--white); color: var(--text); font-size: .85rem; font-weight: 700; white-space: nowrap; box-shadow: 0 18px 40px -18px rgba(0, 0, 0, .5); }
        .shot figcaption b { color: #B45309; }
        .solo-phone { position: relative; width: 100%; max-width: 300px; margin: 0 auto; padding: 10px; border-radius: 44px; background: linear-gradient(160deg, #0C2A1E, #04120B 40%); box-shadow: 0 50px 90px -30px rgba(0, 0, 0, .6), inset 0 0 0 1.5px rgba(255, 255, 255, .12); }
        .solo-phone img { border-radius: 35px; }
        .glow { position: absolute; inset: 10% -30%; z-index: -1; border-radius: 50%; background: radial-gradient(closest-side, rgba(255, 196, 0, .28), transparent); filter: blur(10px); }
        .feature { padding: 26px 22px; border-radius: 20px; background: rgba(255, 255, 255, .05); text-align: center; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .09); }
        .feature .ico { display: grid; place-items: center; width: 46px; height: 46px; margin: 0 auto 14px; border-radius: 14px; background: rgba(255, 215, 0, .12); color: var(--gold); }
        .feature .ico svg { width: 22px; height: 22px; }
        .feature span { display: block; margin-top: 6px; color: var(--soft); font-size: .95rem; }

        /* Pronostics et VIP */
        .free { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 30px; }
        .free .chip { background: var(--white); color: var(--text); box-shadow: inset 0 0 0 1px var(--line); }
        .vip-grid { display: grid; gap: 16px; max-width: 1040px; margin: 36px auto 0; }
        @media (min-width: 900px) { .vip-grid { grid-template-columns: repeat(3, 1fr); } }
        .vip { position: relative; overflow: hidden; padding: 34px 26px 28px; border-radius: 24px; background: linear-gradient(160deg, var(--navy), var(--ink)); color: var(--white); text-align: center; }
        .vip::before { content: ''; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent); }
        .vip.featured { box-shadow: 0 0 0 2px var(--gold), 0 30px 60px -30px rgba(255, 196, 0, .55); }
        .vip .lvl { display: inline-flex; align-items: center; gap: 6px; color: var(--accent); font-size: .8rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .vip .price { margin-top: 10px; font-family: var(--display); font-size: 2.2rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; white-space: nowrap; }
        .vip .price small { display: block; margin-top: 4px; color: var(--soft); font-family: var(--body); font-size: .82rem; font-weight: 600; letter-spacing: 0; }
        .vip p { margin-top: 16px; color: var(--white); font-size: 1rem; font-weight: 700; line-height: 1.4; }
        .vip ul { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin: 14px 0 0; padding: 0; list-style: none; }
        .vip li { padding: 4px 10px; border-radius: 999px; background: rgba(255, 255, 255, .08); color: var(--soft); font-size: .8rem; font-weight: 600; }
        .note { max-width: 640px; margin: 22px auto 0; color: var(--muted); font-size: .88rem; text-align: center; }

        /* Confiance */
        .trust { display: grid; gap: 16px; margin-top: 52px; }
        @media (min-width: 760px) { .trust { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1100px) { .trust { grid-template-columns: repeat(4, 1fr); } }
        .trust div { padding: 28px 24px; border-radius: var(--radius); background: rgba(255, 255, 255, .05); text-align: center; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .09); }
        .trust .ico { display: grid; place-items: center; width: 50px; height: 50px; margin: 0 auto 18px; border-radius: 16px; background: rgba(255, 215, 0, .12); color: var(--gold); }
        .trust .ico svg { width: 24px; height: 24px; }
        .trust h3 { font-size: 1.2rem; }
        .trust p { margin-top: 8px; font-size: .95rem; }
        .countries { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 56px; }
        .countries .chip { padding: 10px 18px; font-size: .95rem; }

        /* FAQ */
        .faq { max-width: 820px; margin: 48px auto 0; }
        .faq details { border-bottom: 1px solid var(--line); }
        .faq summary { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 22px 4px; font-size: 1.1rem; font-weight: 700; list-style: none; cursor: pointer; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq summary::after { content: '+'; display: grid; place-items: center; width: 32px; height: 32px; flex: none; border-radius: 50%; background: var(--paper); color: var(--navy); font-size: 1.3rem; font-weight: 700; transition: transform .2s; }
        .faq details[open] summary::after { transform: rotate(45deg); background: var(--gold); }
        .faq details p { padding: 0 4px 22px; color: var(--muted); }

        /* Appel final, centré */
        .cta { position: relative; overflow: hidden; padding: 72px 24px; border-radius: 32px; background: linear-gradient(160deg, var(--navy-3), var(--ink) 62%); color: var(--white); text-align: center; isolation: isolate; }
        .cta::before { content: ''; position: absolute; left: 50%; top: -240px; z-index: -1; width: 640px; height: 520px; margin-left: -320px; border-radius: 50%; background: radial-gradient(closest-side, rgba(255, 196, 0, .3), transparent); }
        .cta h2 { font-size: clamp(2.1rem, 5vw, 3.3rem); }
        .cta p { max-width: 560px; margin: 16px auto 0; color: var(--soft); font-size: 1.1rem; }
        .install { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 22px; margin: 26px 0 0; padding: 0; list-style: none; counter-reset: install; color: var(--soft); font-size: .95rem; }
        .install li { display: flex; align-items: center; gap: 10px; counter-increment: install; }
        .install li::before { content: counter(install); display: grid; place-items: center; width: 24px; height: 24px; flex: none; border-radius: 50%; background: rgba(255, 255, 255, .12); color: var(--white); font-size: .78rem; font-weight: 800; }
        .guide { max-width: 640px; margin: 34px auto 0; padding: 22px 22px 18px; border-radius: 22px; text-align: left; background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .12); }
        .guide h3 { margin: 0 0 4px; font-size: 1.15rem; color: var(--white); display: flex; align-items: center; gap: 9px; }
        .guide .lead { margin: 0 0 16px; color: var(--soft); font-size: .95rem; }
        .guide ol { margin: 0; padding: 0; list-style: none; counter-reset: guide; display: grid; gap: 14px; }
        .guide li { display: flex; gap: 13px; counter-increment: guide; }
        .guide li::before { content: counter(guide); display: grid; place-items: center; width: 28px; height: 28px; flex: none; border-radius: 50%; background: var(--gold); color: #1a1a1a; font-size: .9rem; font-weight: 900; }
        .guide li b { color: var(--white); }
        .guide li span { color: var(--soft); font-size: .95rem; line-height: 1.5; }
        .guide .reassure { margin: 16px 0 0; padding: 12px 14px; border-radius: 14px; background: rgba(34, 197, 94, .12); border: 1px solid rgba(34, 197, 94, .3); color: var(--soft); font-size: .88rem; line-height: 1.55; }
        .guide .chip { display: inline-block; padding: 1px 7px; margin: 0 1px; border-radius: 6px; background: rgba(255, 255, 255, .14); color: var(--white); font-weight: 700; font-size: .85em; white-space: nowrap; }
        .qr { display: none; width: max-content; margin: 34px auto 0; padding: 16px; border-radius: 22px; background: var(--white); text-align: center; color: var(--text); box-shadow: 0 30px 60px -30px rgba(0, 0, 0, .6); }
        .qr img { width: 150px; height: 150px; margin: 0 auto; }
        .qr span { display: block; margin-top: 8px; font-size: .82rem; font-weight: 700; }
        @media (min-width: 900px) { .qr { display: block; } }
        .socials { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 30px; }
        .socials a { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 12px; background: rgba(255, 255, 255, .08); color: var(--white); font-size: .88rem; font-weight: 700; text-decoration: none; transition: background .2s; }
        .socials a:hover { background: rgba(255, 255, 255, .16); }
        .socials svg { width: 18px; height: 18px; fill: currentColor; }

        /* Pied de page, centré */
        footer { padding: 64px 0 40px; background: var(--white); color: var(--muted); font-size: .9rem; text-align: center; }
        footer .brand { justify-content: center; color: var(--text); }
        footer .about { max-width: 460px; margin: 14px auto 0; }
        .foot-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px 26px; margin: 28px 0 0; padding: 0; list-style: none; }
        footer a { text-decoration: none; font-weight: 600; }
        footer a:hover { color: var(--text); }
        .legal { max-width: 860px; margin: 36px auto 0; padding-top: 24px; border-top: 1px solid var(--line); font-size: .82rem; line-height: 1.7; }
        .badge-18 { display: inline-grid; place-items: center; width: 34px; height: 34px; margin-right: 10px; border: 2px solid #DC2626; border-radius: 50%; color: #DC2626; font-weight: 800; font-size: .8rem; vertical-align: middle; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
        }
    </style>
</head>
<body>
    <header class="nav" id="nav">
        <div class="wrap">
            <a class="brand" href="#top" aria-label="BIG BOSS, accueil">
                <img src="/images/site/logo-mark.png" alt="" width="38" height="38">
                <span>BIG <b>BOSS</b></span>
            </a>
            <nav class="nav-links" aria-label="Navigation principale">
                <a href="#services">Services</a>
                <a href="#fonctionnement">Comment ça marche</a>
                <a href="#pronostics">Pronostics</a>
                <a href="#faq">Questions</a>
            </nav>
            @if ($downloadUrl)
                <a class="btn btn-gold btn-small" href="{{ $downloadUrl }}">Télécharger</a>
            @endif
        </div>
    </header>

    <main id="top">
        <section class="hero">
            <div class="wrap hero-center">
                <img class="hero-logo" src="/images/site/wordmark.png" alt="BIG BOSS" width="340">
                <span class="eyebrow"><span class="pulse" aria-hidden="true"></span>Guichet ouvert 24h/24, 7j/7</span>
                <h1>Votre caisse 1xBet &amp; Melbet, ouverte <em>jour et nuit.</em></h1>
                <p class="lead">Rechargez votre compte et retirez vos gains par Mobile Money, directement depuis votre téléphone. Pas de file d’attente, pas de déplacement&nbsp;: BIG BOSS s’occupe de tout.</p>
                <div class="hero-ctas">
                    @if ($downloadUrl)
                        <a class="btn btn-gold" href="{{ $downloadUrl }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                            Télécharger l’application
                        </a>
                    @endif
                    <a class="btn btn-ghost" href="#fonctionnement">Voir comment ça marche</a>
                </div>
                <p class="hero-meta">
                    @if ($downloadUrl)
                        <span class="new">Dernière version {{ $version }}</span>
                        <span>Android · Gratuit · Mises à jour automatiques</span>
                    @else
                        <span>L’application Android arrive très bientôt.</span>
                    @endif
                </p>
                <div class="operators" aria-label="Mobile Money accepté">
                    <small>Recharge par</small>
                    @foreach ($operators as $operator)
                        <span class="chip"><i style="background: {{ ['MTN MoMo' => '#22C55E', 'Moov Money' => '#0A6BD6', 'Celtiis' => '#E30613'][$operator] ?? '#22C55E' }}"></i>{{ $operator }}</span>
                    @endforeach
                </div>

                <div class="showcase" aria-hidden="true">
                    <div class="phone phone-left"><img src="/images/site/app-pronostics.jpg" alt="" width="720" height="1560"></div>
                    <div class="phone phone-center"><img src="/images/site/app-accueil.jpg" alt="" width="720" height="1560"></div>
                    <div class="phone phone-right"><img src="/images/site/app-assistant.jpg" alt="" width="720" height="1560"></div>
                    <div class="float float-1">
                        <span class="ico" style="background:#DCFCE7;color:#15803D"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <div><strong>Recharge validée</strong><span>+ 5 000 FCFA sur 1xBet</span></div>
                    </div>
                    <div class="float float-2">
                        <span class="ico" style="background:#DCFCE7;color:#B45309"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                        <div><strong>LUC, votre assistant</strong><span>Je vous guide pas à pas</span></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="stats">
            <div class="wrap">
                <div class="stats-grid">
                    <div class="stat"><b>24h<em>/</em>24</b><span>Le guichet ne ferme jamais, même le week-end</span></div>
                    <div class="stat"><b>200 <em>F</em></b><span>Recharge minimum, en FCFA</span></div>
                    <div class="stat"><b>10 <em>pays</em></b><span>Afrique de l’Ouest et centrale, du Bénin au Cameroun</span></div>
                    <div class="stat"><b>2 <em>sites</em></b><span>1xBet et Melbet, dans la même application</span></div>
                </div>
            </div>
        </div>

        <section class="block" id="services">
            <div class="wrap">
                <div class="heading">
                    <span class="kicker">Services</span>
                    <h2>Tout ce que fait un guichet, dans votre poche.</h2>
                    <p>Recharger, retirer, suivre ses demandes et parier mieux informé&nbsp;: une seule application, simple et rapide.</p>
                </div>
                <div class="services">
                    <article class="service featured">
                        <span class="tag">Le plus utilisé</span>
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
                        <h3>Recharge express</h3>
                        <p>Choisissez 1xBet ou Melbet, entrez votre ID et le montant, payez par Mobile Money. Votre compte est crédité dès la vérification.</p>
                    </article>
                    <article class="service">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg></div>
                        <h3>Retrait de vos gains</h3>
                        <p>Retirez en «&nbsp;Espèces&nbsp;» vers le guichet BIG BOSS&nbsp;: l’argent arrive sur votre numéro Mobile Money.</p>
                    </article>
                    <article class="service">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></div>
                        <h3>Suivi en temps réel</h3>
                        <p>Chaque demande a son statut&nbsp;: en cours, validée ou refusée. Une notification vous prévient à chaque étape.</p>
                    </article>
                    <article class="service">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg></div>
                        <h3>Assistant 24h/24</h3>
                        <p>Une question à 3 h du matin&nbsp;? LUC, l’assistant, répond tout de suite et vous montre où toucher, captures à l’appui.</p>
                    </article>
                    <article class="service">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/></svg></div>
                        <h3>Pronostics du jour</h3>
                        <p>Des combinés analysés par l’IA BIG BOSS à partir des vraies statistiques des équipes et des cotes du marché.</p>
                    </article>
                    <article class="service">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12l4 6-10 13L2 9Z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/></svg></div>
                        <h3>Coupons VIP</h3>
                        <p>Bronze, Silver, Gold et Platinum&nbsp;: les coupons les plus travaillés, réservés aux membres, avec les codes prêts à charger.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="block paper" id="fonctionnement">
            <div class="wrap">
                <div class="heading">
                    <span class="kicker">Comment ça marche</span>
                    <h2>Quatre étapes, et c’est fait.</h2>
                    <p>L’application vous guide écran par écran. Le nom du destinataire s’affiche pour que vous payiez toujours la bonne personne.</p>
                </div>
                <div class="tabs">
                    <input type="radio" name="tab" id="tab-recharge" checked>
                    <input type="radio" name="tab" id="tab-retrait">
                    <div class="tab-labels">
                        <label for="tab-recharge">Recharger</label>
                        <label for="tab-retrait">Retirer</label>
                    </div>

                    <div class="panel panel-recharge trio">
                        <ol class="side steps">
                            <li><strong>Touchez «&nbsp;Recharger&nbsp;»</strong><span>Sur l’accueil de l’application, choisissez 1xBet ou Melbet.</span></li>
                            <li><strong>Entrez votre ID et le montant</strong><span>Dès 200 FCFA. Votre ID est mémorisé pour la fois suivante.</span></li>
                        </ol>
                        <figure class="shot middle">
                            <img src="/images/site/guide-recharge.jpg" alt="Écran de dépôt de l’application avec les étapes numérotées" width="720" height="1092" loading="lazy">
                            <figcaption><b>Guidé</b> à chaque étape</figcaption>
                        </figure>
                        <ol class="side steps second">
                            <li><strong>Payez par Mobile Money</strong><span>Envoyez le montant au numéro affiché, en vérifiant le nom du destinataire.</span></li>
                            <li><strong>Confirmez avec l’ID du SMS</strong><span>Recopiez l’ID de la transaction reçu par SMS&nbsp;: votre compte est crédité après vérification.</span></li>
                        </ol>
                    </div>

                    <div class="panel panel-retrait trio">
                        <ol class="side steps">
                            <li><strong>Touchez «&nbsp;Retirer&nbsp;»</strong><span>L’application vous donne l’adresse du guichet BIG BOSS&nbsp;: la ville et la rue.</span></li>
                            <li><strong>Retirez en «&nbsp;Espèces&nbsp;»</strong><span>Sur 1xBet ou Melbet, indiquez exactement cette adresse et le montant, puis copiez le code de retrait.</span></li>
                        </ol>
                        <figure class="shot middle">
                            <img src="/images/site/guide-retrait.jpg" alt="Écran des instructions de retrait avec l’adresse du guichet" width="720" height="1510" loading="lazy">
                            <figcaption><b>L’adresse</b> toujours sous les yeux</figcaption>
                        </figure>
                        <ol class="side steps second">
                            <li><strong>Collez le code dans BIG BOSS</strong><span>Choisissez votre opérateur et le numéro qui recevra l’argent.</span></li>
                            <li><strong>Recevez votre argent</strong><span>Dès la validation du code, l’argent part sur votre Mobile Money. Vous êtes notifié.</span></li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="block dark">
            <div class="wrap">
                <div class="heading">
                    <span class="kicker">Assistant 24h/24</span>
                    <h2>Un conseiller dans la poche, même à 3 h du matin.</h2>
                    <p>Voici LUC, l’assistant BIG BOSS. Il connaît l’application par cœur et répond comme un agent au guichet, avec des mots simples.</p>
                </div>
                <div class="trio" style="margin-top:56px">
                    <div class="side">
                        <div class="feature"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span><strong>Réponse en quelques secondes</strong><span>Jour et nuit, week-end compris.</span></div>
                        <div class="feature"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg></span><strong>Des captures annotées</strong><span>Il vous montre où toucher&nbsp;: en 1, en 2, en 3.</span></div>
                    </div>
                    <div class="middle">
                        <div class="glow" aria-hidden="true"></div>
                        <div class="solo-phone"><img src="/images/site/app-assistant.jpg" alt="Conversation avec l’assistant BIG BOSS, avec un guide illustré" width="720" height="1560" loading="lazy"></div>
                    </div>
                    <div class="side">
                        <div class="feature"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg></span><strong>Il connaît vos demandes</strong><span>«&nbsp;Ma recharge est en attente&nbsp;»&nbsp;: il sait de laquelle vous parlez.</span></div>
                        <div class="feature"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg></span><strong>Un humain quand il le faut</strong><span>Pour une action sur votre compte, un conseiller prend le relais.</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="block paper" id="pronostics">
            <div class="wrap">
                <div class="heading">
                    <span class="kicker">Pronostics &amp; VIP</span>
                    <h2>Pariez mieux informé, chaque jour.</h2>
                    <p>Tout au long de la journée, l’IA BIG BOSS analyse les matchs (forme, classement, buts, cotes de plusieurs bookmakers) et compose des coupons prudents. Sans sélection assez fiable, rien n’est publié.</p>
                </div>
                <div class="free">
                    <span class="chip"><i style="background:#14B8A6"></i>Starter · gratuit</span>
                    <span class="chip"><i style="background:#C2410C"></i>Bronze · gratuit</span>
                </div>
                <div class="vip-grid">
                    @foreach ($vipLevels as $level => $vip)
                        <article class="vip{{ $level === 'Gold' ? ' featured' : '' }}" style="--accent: {{ $vip['accent'] }}">
                            <span class="lvl">◆ VIP {{ $level }}</span>
                            <div class="price">
                                @if (! empty($vipOffers[$level]['price']))
                                    {{ number_format($vipOffers[$level]['price'], 0, ',', ' ') }} F
                                    <small>pour 30 jours</small>
                                @else
                                    Sur demande
                                    <small>prix dans l’application</small>
                                @endif
                            </div>
                            <p>{{ $vip['pitch'] }}</p>
                            <ul>
                                @foreach ($vip['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
                <p class="note">Les pronostics sont des analyses, pas des certitudes&nbsp;: aucun résultat n’est garanti. Misez raisonnablement.</p>
            </div>
        </section>

        <section class="block dark">
            <div class="wrap">
                <div class="heading">
                    <span class="kicker">Confiance</span>
                    <h2>Votre argent, en sécurité à chaque étape.</h2>
                    <p>BIG BOSS a été pensé pour que vous gardiez le contrôle, du paiement jusqu’à la confirmation.</p>
                </div>
                <div class="trust">
                    <div>
                        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg></span>
                        <h3>Le bon destinataire</h3>
                        <p>Le nom du compte Mobile Money s’affiche&nbsp;: vous vérifiez avant de payer.</p>
                    </div>
                    <div>
                        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 13h4"/><path d="M10 17h4"/></svg></span>
                        <h3>Chaque paiement tracé</h3>
                        <p>L’ID de transaction de votre SMS relie votre paiement à votre demande.</p>
                    </div>
                    <div>
                        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>
                        <h3>Notifié à chaque étape</h3>
                        <p>Reçue, en traitement, validée&nbsp;: vous savez toujours où en est votre demande.</p>
                    </div>
                    <div>
                        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg></span>
                        <h3>Une vraie équipe</h3>
                        <p>Un problème&nbsp;? Un conseiller vous répond directement dans l’application.</p>
                    </div>
                </div>
                <div class="countries" aria-label="Pays servis">
                    <span class="chip">Bénin</span>
                    <span class="chip">Côte d’Ivoire</span>
                    <span class="chip">Sénégal</span>
                    <span class="chip">Burkina Faso</span>
                    <span class="chip">Mali</span>
                    <span class="chip">Niger</span>
                    <span class="chip">Togo</span>
                    <span class="chip">Gabon</span>
                    <span class="chip">RD Congo</span>
                    <span class="chip">Cameroun</span>
                </div>
            </div>
        </section>

        <section class="block" id="faq">
            <div class="wrap">
                <div class="heading">
                    <span class="kicker">Questions fréquentes</span>
                    <h2>Vous vous demandez…</h2>
                </div>
                <div class="faq">
                    <details open>
                        <summary>Combien de temps prend une recharge&nbsp;?</summary>
                        <p>Dès que BIG BOSS a vérifié votre paiement, votre compte 1xBet ou Melbet est crédité. Vous suivez la demande en direct dans l’onglet Historique, et une notification vous prévient quand c’est fait.</p>
                    </details>
                    <details>
                        <summary>Quels sont les montants minimums&nbsp;?</summary>
                        <p>200 FCFA pour une recharge et 500 FCFA pour un retrait.</p>
                    </details>
                    <details>
                        <summary>Avec quel Mobile Money puis-je payer&nbsp;?</summary>
                        <p>Les recharges se paient avec {{ implode(', ', $operators) }}. Pour les retraits, vous recevez l’argent sur le Mobile Money de votre pays&nbsp;: MTN MoMo, Moov Money, Celtiis, Orange Money, Wave, Free Money, Airtel Money, M-Pesa, Flooz ou Mixx selon le pays.</p>
                    </details>
                    <details>
                        <summary>Où trouver mon ID 1xBet ou Melbet&nbsp;?</summary>
                        <p>Dans l’application ou sur le site 1xBet / Melbet, ouvrez votre profil&nbsp;: le numéro de compte (ID) s’affiche en haut, sous votre nom. BIG BOSS le mémorise ensuite pour vous.</p>
                    </details>
                    <details>
                        <summary>Comment retirer mes gains&nbsp;?</summary>
                        <p>Touchez «&nbsp;Retirer&nbsp;» dans l’application&nbsp;: elle vous donne l’adresse du guichet BIG BOSS. Faites un retrait «&nbsp;Espèces&nbsp;» sur 1xBet ou Melbet avec exactement cette adresse, collez le code de retrait dans l’application, et l’argent part sur votre Mobile Money.</p>
                    </details>
                    <details>
                        <summary>L’application est-elle gratuite&nbsp;?</summary>
                        <p>Oui. Recharges, retraits, suivi, assistant et pronostics Starter sont gratuits. Seuls les coupons VIP (Bronze, Silver, Gold, Platinum) sont payants, si vous le souhaitez.</p>
                    </details>
                    <details>
                        <summary>Comment installer l’application&nbsp;?</summary>
                        <p>Depuis votre téléphone Android, touchez «&nbsp;Télécharger l’application&nbsp;», ouvrez le fichier big-boss.apk, autorisez l’installation si Android le demande, puis créez votre compte. Si un message «&nbsp;application non vérifiée&nbsp;» ou Play&nbsp;Protect apparaît, touchez «&nbsp;Plus de détails&nbsp;» puis «&nbsp;Installer quand même&nbsp;»&nbsp;: c’est normal pour une application hors Play&nbsp;Store, voir le guide d’installation plus haut. Les mises à jour arrivent ensuite automatiquement.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="block" style="padding-top:0">
            <div class="wrap">
                <div class="cta">
                    <h2>Votre caisse est ouverte.<br>Maintenant.</h2>
                    <p>Téléchargez la dernière version de BIG BOSS et faites votre première recharge en quelques minutes.</p>
                    <div class="hero-ctas">
                        @if ($downloadUrl)
                            <a class="btn btn-gold" href="{{ $downloadUrl }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                                Télécharger la version {{ $version }}
                            </a>
                        @else
                            <span class="btn btn-ghost">Bientôt disponible</span>
                        @endif
                    </div>
                    <ol class="install">
                        <li>Ouvrez le fichier big-boss.apk</li>
                        <li>Autorisez l’installation si Android le demande</li>
                        <li>Créez votre compte&nbsp;: c’est prêt</li>
                    </ol>

                    @if ($downloadUrl)
                    <div class="guide">
                        <h3>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Installation bloquée&nbsp;? Suivez ce guide (2&nbsp;min)
                        </h3>
                        <p class="lead">Comme l’application n’est pas (encore) sur le Play&nbsp;Store, Android affiche un avertissement. C’est normal et sans danger&nbsp;: voici comment l’installer.</p>
                        <ol>
                            <li><span>Au téléchargement, si le navigateur affiche «&nbsp;fichier potentiellement dangereux&nbsp;», touchez <b>Télécharger quand même</b> (ou <b>Conserver</b>).</span></li>
                            <li><span>Ouvrez le fichier <span class="chip">big-boss.apk</span>. Si Android dit que l’installation depuis cette source n’est pas autorisée, touchez <b>Paramètres</b> puis activez <b>Autoriser depuis cette source</b>, et revenez en arrière.</span></li>
                            <li><span>Si <b>Play&nbsp;Protect</b> affiche «&nbsp;application non vérifiée&nbsp;», touchez <b>Plus de détails</b> puis <b>Installer quand même</b>.</span></li>
                            <li><span><b>Pour éviter tout blocage</b> (facultatif, recommandé)&nbsp;: ouvrez le <b>Play&nbsp;Store</b> → touchez votre <b>photo de profil</b> → <b>Play&nbsp;Protect</b> → l’icône <b>⚙️</b> → désactivez <b>Analyser les applications</b>. Réactivez-le après l’installation.</span></li>
                            <li><span>Lancez BIG&nbsp;BOSS et <b>créez votre compte</b>. Les mises à jour arrivent ensuite automatiquement.</span></li>
                        </ol>
                        <p class="reassure">🔒 BIG&nbsp;BOSS est sûre. Cet avertissement apparaît uniquement parce que l’application s’installe directement (hors Play&nbsp;Store), pas parce qu’elle est dangereuse.</p>
                    </div>
                    @endif
                    @if ($downloadUrl)
                        <div class="qr">
                            <img src="/images/site/qr-telecharger.png" alt="QR code pour télécharger l’application" width="150" height="150" loading="lazy">
                            <span>Sur ordinateur&nbsp;? Scannez avec votre téléphone</span>
                        </div>
                    @endif
                    @if ($communityLinks)
                        <div class="socials" aria-label="Nos réseaux">
                            @foreach ($communityLinks as $network => $link)
                                @if (isset($socials[$network]))
                                    <a href="{{ $link }}" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $socials[$network][1] }}"/></svg>{{ $socials[$network][0] }}</a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <a class="brand" href="#top"><img class="tile" src="/images/logo.png" alt="" width="38" height="38"><span>BIG <b>BOSS</b></span></a>
            <p class="about">Votre caisse 1xBet et Melbet par Mobile Money, ouverte 24h/24. Recharges, retraits, pronostics et assistant dans une seule application.</p>
            <ul class="foot-links">
                @if ($downloadUrl)<li><a href="{{ $downloadUrl }}">Télécharger (Android)</a></li>@endif
                <li><a href="#fonctionnement">Comment ça marche</a></li>
                <li><a href="#pronostics">Pronostics et VIP</a></li>
                <li><a href="#faq">Questions fréquentes</a></li>
                <li><a href="/confidentialite">Politique de confidentialité</a></li>
                <li><a href="/suppression-compte">Supprimer mon compte</a></li>
            </ul>
            <p class="legal">
                <span class="badge-18" aria-hidden="true">18+</span>
                Réservé aux personnes majeures. Les jeux d’argent comportent des risques&nbsp;: endettement, dépendance. Pariez uniquement ce que vous pouvez vous permettre de perdre. 1xBet et Melbet sont des marques de leurs propriétaires respectifs.
                <br>© {{ date('Y') }} BIG BOSS. Tous droits réservés.
            </p>
        </div>
    </footer>

    <script>
        // En-tête opaque dès qu'on fait défiler.
        (function () {
            var nav = document.getElementById('nav');
            var update = function () { nav.classList.toggle('scrolled', window.scrollY > 12); };
            update();
            window.addEventListener('scroll', update, { passive: true });
        })();
    </script>
</body>
</html>
