<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Administration') · BIG BOSS Admin</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --bf-navy: #1A2E5A;
            --bf-navy-deep: #0E1A36;
            --bf-gold: #FFD700;
            --bf-gold-soft: #FFF7CC;
            --bf-bg: #F3F5FA;
            --bf-surface: #FFFFFF;
            --bf-surface-2: #F8F9FC;
            --bf-border: #E4E7EF;
            --bf-text: #111A2E;
            --bf-text-2: #344054;
            --bf-muted: #667085;
            --bf-subtle: #98A2B3;
            --bf-success: #15803D;
            --bf-success-bg: #E7F6EC;
            --bf-danger: #B42318;
            --bf-danger-bg: #FDECEA;
            --bf-warning: #B54708;
            --bf-warning-bg: #FEF3E2;
            --bf-info: #1D4ED8;
            --bf-info-bg: #E8EFFE;
            --bf-radius: 14px;
            --bf-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 6px 18px rgba(16, 24, 40, .05);
            --bs-body-font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            --bs-body-color: var(--bf-text);
            --bs-border-color: var(--bf-border);
            --bs-link-color-rgb: 26, 46, 90;
            --bs-link-hover-color-rgb: 14, 26, 54;
        }

        body {
            background: var(--bf-bg);
            color: var(--bf-text);
            font-size: .9rem;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; }

        .tabular { font-variant-numeric: tabular-nums; }
        .min-w-0 { min-width: 0; }

        /* ---------- Structure ---------- */
        .bf-app { display: flex; min-height: 100vh; }

        .bf-sidebar {
            --bs-offcanvas-width: 272px;
            --bs-offcanvas-bg: var(--bf-navy-deep);
        }

        @media (min-width: 992px) {
            .bf-sidebar {
                position: sticky;
                top: 0;
                height: 100vh;
                width: 264px;
                flex: 0 0 264px;
            }
        }

        .bf-sidebar-inner {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            padding: 20px 14px 16px;
            overflow-y: auto;
            background: linear-gradient(180deg, #15284F 0%, var(--bf-navy-deep) 100%);
            color: #C6CEE0;
        }

        .bf-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 8px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .bf-brand img {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            box-shadow: 0 0 0 1px rgba(255, 215, 0, .25), 0 6px 16px rgba(0, 0, 0, .35);
        }

        .bf-brand strong { display: block; color: #fff; font-size: 1rem; letter-spacing: -.01em; }
        .bf-brand span { display: block; color: #8C98B5; font-size: .74rem; font-weight: 500; }

        .bf-nav-label {
            margin: 20px 12px 6px;
            color: #7684A3;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .bf-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2px;
            padding: 9px 12px;
            border-radius: 10px;
            color: #C6CEE0;
            font-weight: 500;
            transition: background .15s ease, color .15s ease;
        }

        .bf-nav-link i { font-size: 1.05rem; width: 20px; text-align: center; }
        .bf-nav-link:hover { background: rgba(255, 255, 255, .06); color: #fff; }

        .bf-nav-link.active {
            background: rgba(255, 215, 0, .12);
            color: var(--bf-gold);
            box-shadow: inset 3px 0 0 var(--bf-gold);
        }

        .bf-count {
            margin-left: auto;
            min-width: 22px;
            padding: 2px 7px;
            border-radius: 999px;
            background: var(--bf-gold);
            color: var(--bf-navy-deep);
            font-size: .7rem;
            font-weight: 700;
            text-align: center;
        }

        .bf-sidebar-footer {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: auto;
            padding: 14px 8px 0;
            border-top: 1px solid rgba(255, 255, 255, .08);
        }

        .bf-sidebar-footer .bf-who { min-width: 0; flex: 1; }
        .bf-sidebar-footer .bf-who strong { display: block; color: #fff; font-size: .82rem; }
        .bf-sidebar-footer .bf-who span { display: block; color: #8C98B5; font-size: .72rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .bf-logout {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 10px;
            background: transparent;
            color: #C6CEE0;
        }

        .bf-logout:hover { background: rgba(239, 68, 68, .15); border-color: rgba(239, 68, 68, .4); color: #FCA5A5; }

        .bf-main { display: flex; flex: 1; flex-direction: column; min-width: 0; }

        .bf-topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 32px;
            border-bottom: 1px solid var(--bf-border);
            background: rgba(243, 245, 250, .88);
            backdrop-filter: blur(10px);
        }

        .bf-topbar h1 { margin: 0; color: var(--bf-text); font-size: 1.3rem; font-weight: 700; letter-spacing: -.02em; }
        .bf-topbar p { margin: 2px 0 0; color: var(--bf-muted); font-size: .83rem; }
        .bf-topbar-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; }

        .bf-menu-btn {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border: 1px solid var(--bf-border);
            border-radius: 10px;
            background: var(--bf-surface);
            color: var(--bf-text);
            font-size: 1.25rem;
        }

        .bf-content { width: 100%; max-width: 1480px; padding: 24px 32px 56px; }

        @media (max-width: 991.98px) {
            .bf-topbar { padding: 12px 16px; }
            .bf-topbar h1 { font-size: 1.1rem; }
            .bf-topbar p { display: none; }
            .bf-content { padding: 16px 16px 48px; }
        }

        @media (max-width: 575.98px) {
            .bf-topbar-actions .btn-label { display: none; }
        }

        /* ---------- Surfaces ---------- */
        .bf-card {
            border: 1px solid var(--bf-border);
            border-radius: var(--bf-radius);
            background: var(--bf-surface);
            box-shadow: var(--bf-shadow);
        }

        .bf-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--bf-border);
        }

        .bf-card-title { margin: 0; color: var(--bf-text); font-size: .98rem; font-weight: 700; letter-spacing: -.01em; }
        .bf-card-sub { margin: 2px 0 0; color: var(--bf-muted); font-size: .8rem; }
        .bf-card-body { padding: 20px; }

        /* ---------- KPI ---------- */
        .bf-kpi { position: relative; height: 100%; padding: 18px 20px; }
        .bf-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .bf-kpi-label { color: var(--bf-muted); font-size: .8rem; font-weight: 600; }

        .bf-kpi-icon {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--bf-surface-2);
            color: var(--bf-navy);
            font-size: 1.05rem;
        }

        .bf-kpi-value { color: var(--bf-text); font-size: 1.75rem; font-weight: 700; letter-spacing: -.03em; line-height: 1.1; }
        .bf-kpi-value small { color: var(--bf-muted); font-size: .85rem; font-weight: 600; letter-spacing: 0; }
        .bf-kpi-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px; color: var(--bf-muted); font-size: .78rem; }

        .bf-kpi.is-alert { border-color: #F5D565; background: linear-gradient(180deg, #FFFCEB 0%, #FFFFFF 70%); }
        .bf-kpi.is-alert .bf-kpi-icon { background: var(--bf-gold); color: var(--bf-navy-deep); }

        .bf-delta { display: inline-flex; align-items: center; gap: 2px; padding: 1px 7px; border-radius: 999px; font-size: .74rem; font-weight: 600; }
        .bf-delta.up { background: var(--bf-success-bg); color: var(--bf-success); }
        .bf-delta.down { background: var(--bf-danger-bg); color: var(--bf-danger); }
        .bf-delta.flat { background: var(--bf-surface-2); color: var(--bf-muted); }

        /* ---------- Badges ---------- */
        .bf-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .bf-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .bf-badge.pending { background: var(--bf-warning-bg); color: var(--bf-warning); }
        .bf-badge.processing { background: var(--bf-info-bg); color: var(--bf-info); }
        .bf-badge.completed, .bf-badge.success { background: var(--bf-success-bg); color: var(--bf-success); }
        .bf-badge.rejected, .bf-badge.danger { background: var(--bf-danger-bg); color: var(--bf-danger); }
        .bf-badge.neutral { background: var(--bf-surface-2); color: var(--bf-text-2); border: 1px solid var(--bf-border); }
        .bf-badge.vip { background: var(--bf-gold-soft); color: #7A5B00; }

        .bf-type { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
        .bf-type i { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; font-size: .9rem; }
        .bf-type.recharge i { background: #E6F0FB; color: #1F5FAE; }
        .bf-type.retrait i { background: #FDEDE6; color: #B4461A; }

        .bf-avatar {
            display: inline-grid;
            flex: 0 0 auto;
            place-items: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: #fff;
            font-size: .8rem;
            font-weight: 700;
        }

        .bf-avatar.lg { width: 64px; height: 64px; font-size: 1.4rem; }

        /* ---------- Tables ---------- */
        .bf-table { margin: 0; }
        .bf-table > :not(caption) > * > * { padding: 12px 16px; border-bottom-color: var(--bf-border); }
        .bf-table thead th {
            background: var(--bf-surface-2);
            color: var(--bf-muted);
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .bf-table tbody tr:hover > * { background: #FAFBFD; }
        .bf-table tbody tr:last-child > * { border-bottom: 0; }

        .bf-kv { display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; font-size: .8rem; }
        .bf-kv dt { color: var(--bf-muted); font-weight: 500; }
        .bf-kv dd { margin: 0; color: var(--bf-text); font-weight: 600; word-break: break-all; }

        .bf-copy {
            margin-left: 4px;
            padding: 0 4px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--bf-subtle);
            line-height: 1;
        }

        .bf-copy:hover { background: var(--bf-surface-2); color: var(--bf-navy); }
        .bf-copy.copied { color: var(--bf-success); }

        /* ---------- Buttons & forms ---------- */
        .btn { border-radius: 10px; font-weight: 600; font-size: .85rem; }
        .btn-sm { border-radius: 8px; font-size: .78rem; }

        .bf-btn-primary { border: 1px solid var(--bf-navy); background: var(--bf-navy); color: #fff; }
        .bf-btn-primary:hover, .bf-btn-primary:focus { border-color: var(--bf-navy-deep); background: var(--bf-navy-deep); color: #fff; }
        .bf-btn-gold { border: 1px solid #F2C800; background: var(--bf-gold); color: var(--bf-navy-deep); }
        .bf-btn-gold:hover { border-color: #E6BE00; background: #F5CC00; color: var(--bf-navy-deep); }
        .bf-btn-light { border: 1px solid var(--bf-border); background: var(--bf-surface); color: var(--bf-text-2); }
        .bf-btn-light:hover { border-color: #D0D5E0; background: var(--bf-surface-2); color: var(--bf-text); }
        .bf-btn-success { border: 1px solid #16A34A; background: #16A34A; color: #fff; }
        .bf-btn-success:hover { border-color: #15803D; background: #15803D; color: #fff; }
        .bf-btn-danger-soft { border: 1px solid #F6C9C4; background: var(--bf-danger-bg); color: var(--bf-danger); }
        .bf-btn-danger-soft:hover { border-color: #EBA59D; background: #FAD7D3; color: #912018; }

        .form-control, .form-select {
            border-color: var(--bf-border);
            border-radius: 10px;
            font-size: .88rem;
            min-height: 40px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #8DA2CF;
            box-shadow: 0 0 0 4px rgba(26, 46, 90, .08);
        }

        .form-label { margin-bottom: 6px; color: var(--bf-text-2); font-size: .8rem; font-weight: 600; }

        .bf-search { position: relative; }
        .bf-search i { position: absolute; top: 50%; left: 13px; transform: translateY(-50%); color: var(--bf-subtle); }
        .bf-search .form-control { padding-left: 38px; }

        /* ---------- Tabs ---------- */
        .bf-tabs { display: flex; flex-wrap: wrap; gap: 6px; }

        .bf-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border: 1px solid var(--bf-border);
            border-radius: 10px;
            background: var(--bf-surface);
            color: var(--bf-text-2);
            font-size: .82rem;
            font-weight: 600;
        }

        .bf-tab span { padding: 0 7px; border-radius: 999px; background: var(--bf-surface-2); color: var(--bf-muted); font-size: .72rem; }
        .bf-tab:hover { border-color: #C9D0DE; color: var(--bf-text); }
        .bf-tab.active { border-color: var(--bf-navy); background: var(--bf-navy); color: #fff; }
        .bf-tab.active span { background: rgba(255, 255, 255, .16); color: #fff; }

        /* ---------- Alerts & empty ---------- */
        .bf-flash {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border: 1px solid;
            border-radius: 12px;
            font-weight: 500;
        }

        .bf-flash i { font-size: 1.1rem; }
        .bf-flash.success { border-color: #BBE5C8; background: var(--bf-success-bg); color: #14532D; }
        .bf-flash.error { border-color: #F6C9C4; background: var(--bf-danger-bg); color: #7A1A12; }
        .bf-flash.warning { border-color: #F7D9A8; background: var(--bf-warning-bg); color: #7A3306; }
        .bf-flash .btn-close { margin-left: auto; font-size: .7rem; }

        .bf-empty { padding: 40px 20px; color: var(--bf-muted); text-align: center; }
        .bf-empty i { display: block; margin-bottom: 8px; color: var(--bf-subtle); font-size: 1.8rem; }

        .bf-muted { color: var(--bf-muted); }
        .bf-small { font-size: .78rem; }

        .pagination { --bs-pagination-color: var(--bf-navy); --bs-pagination-active-bg: var(--bf-navy); --bs-pagination-active-border-color: var(--bf-navy); --bs-pagination-border-radius: 8px; margin: 0; }
        .bf-pagination { padding: 14px 20px; border-top: 1px solid var(--bf-border); }

        .modal-content { border: 0; border-radius: 16px; box-shadow: 0 24px 60px rgba(16, 24, 40, .25); }
        .modal-header, .modal-footer { border-color: var(--bf-border); }
        .dropdown-menu { border-color: var(--bf-border); border-radius: 12px; box-shadow: 0 12px 32px rgba(16, 24, 40, .12); font-size: .85rem; }
        .dropdown-item { display: flex; align-items: center; gap: 8px; padding: 7px 14px; }
    </style>
    @stack('head')
</head>
<body>
    @php
        $navCounts ??= ['transactions' => 0, 'messages' => 0];
        $admin = auth()->user();
        $navItems = [
            ['label' => 'Pilotage'],
            ['url' => '/admin', 'match' => 'admin', 'icon' => 'bi-grid-1x2', 'text' => 'Tableau de bord'],
            ['label' => 'Opérations'],
            ['url' => '/admin/transactions', 'match' => 'admin/transactions*', 'icon' => 'bi-arrow-left-right', 'text' => 'Transactions', 'count' => $navCounts['transactions']],
            ['url' => '/admin/messages', 'match' => 'admin/messages*', 'icon' => 'bi-chat-dots', 'text' => 'Messages', 'count' => $navCounts['messages']],
            ['url' => '/admin/depots', 'match' => 'admin/depots*', 'icon' => 'bi-wallet2', 'text' => 'Dépôts Mobile Money'],
            ['label' => 'Contenu'],
            ['url' => '/admin/pronostics', 'match' => 'admin/pronostics*', 'icon' => 'bi-graph-up-arrow', 'text' => 'Pronostics'],
            ['url' => '/admin/vip', 'match' => 'admin/vip*', 'icon' => 'bi-gem', 'text' => 'VIP', 'count' => $navCounts['vip'] ?? 0],
            ['url' => '/admin/videos', 'match' => 'admin/videos*', 'icon' => 'bi-play-btn', 'text' => 'Vidéos tutoriels'],
            ['label' => 'Communauté'],
            ['url' => '/admin/users', 'match' => 'admin/users*', 'icon' => 'bi-people', 'text' => 'Utilisateurs'],
            ['label' => 'Système'],
            ['url' => '/admin/settings', 'match' => 'admin/settings*', 'icon' => 'bi-sliders', 'text' => 'Paramètres'],
        ];
    @endphp

    <div class="bf-app">
        <aside class="bf-sidebar offcanvas-lg offcanvas-start" id="bfSidebar" tabindex="-1" aria-label="Navigation principale">
            <div class="bf-sidebar-inner">
                <div class="bf-brand">
                    <img src="/images/logo.png" alt="">
                    <div class="flex-grow-1">
                        <strong>BIG BOSS</strong>
                        <span>Console d’administration</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#bfSidebar" aria-label="Fermer"></button>
                </div>

                <nav class="mt-1">
                    @foreach ($navItems as $item)
                        @if (isset($item['label']))
                            <div class="bf-nav-label">{{ $item['label'] }}</div>
                        @else
                            <a href="{{ $item['url'] }}" class="bf-nav-link {{ request()->is($item['match']) ? 'active' : '' }}">
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span>{{ $item['text'] }}</span>
                                @if (! empty($item['count']))
                                    <span class="bf-count">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </nav>

                <div class="bf-sidebar-footer">
                    <span class="bf-avatar" style="background:#FFD700;color:#0E1A36;">{{ mb_strtoupper(mb_substr($admin?->name ?? 'A', 0, 1)) }}</span>
                    <div class="bf-who">
                        <strong>{{ $admin?->name ?? 'Administrateur' }}</strong>
                        <span>{{ $admin?->email }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="bf-logout" type="submit" title="Se déconnecter" aria-label="Se déconnecter">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="bf-main">
            <header class="bf-topbar">
                <button class="bf-menu-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#bfSidebar" aria-controls="bfSidebar" aria-label="Ouvrir le menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="min-w-0">
                    <h1>@yield('title', 'Administration')</h1>
                    @hasSection('subtitle')
                        <p>@yield('subtitle')</p>
                    @endif
                </div>
                <div class="bf-topbar-actions">
                    @yield('actions')
                </div>
            </header>

            <main class="bf-content">
                @if (session('success'))
                    <div class="bf-flash success" role="status">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bf-dismiss aria-label="Fermer"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="bf-flash error" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bf-dismiss aria-label="Fermer"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bf-flash error" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <div class="fw-semibold mb-1">Correction nécessaire</div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <div class="modal fade" id="bfConfirmModal" tabindex="-1" aria-labelledby="bfConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-body p-4 text-center">
                    <div class="mx-auto mb-3 d-grid" style="width:52px;height:52px;place-items:center;border-radius:50%;background:var(--bf-gold-soft);color:var(--bf-navy);font-size:1.4rem;">
                        <i class="bi bi-question-lg"></i>
                    </div>
                    <h2 class="fs-5 fw-bold mb-2" id="bfConfirmTitle">Confirmer l’action</h2>
                    <p class="bf-muted mb-0" data-confirm-text></p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
                    <button type="button" class="btn bf-btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn bf-btn-primary" data-confirm-ok>Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copie d'une valeur (ID joueur, code, numéro) en un clic.
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-copy]');

            if (button) {
                navigator.clipboard?.writeText(button.dataset.copy).then(function () {
                    const icon = button.querySelector('i');
                    button.classList.add('copied');
                    if (icon) icon.className = 'bi bi-check2';
                    setTimeout(function () {
                        button.classList.remove('copied');
                        if (icon) icon.className = 'bi bi-copy';
                    }, 1400);
                });
            }

            const dismiss = event.target.closest('[data-bf-dismiss]');

            if (dismiss) {
                dismiss.closest('.bf-flash')?.remove();
            }
        });

        // Confirmation avant les actions importantes : fenêtre aux couleurs de l'admin,
        // pas la boîte grise du navigateur.
        (function () {
            const element = document.getElementById('bfConfirmModal');
            const modal = bootstrap.Modal.getOrCreateInstance(element);
            const text = element.querySelector('[data-confirm-text]');
            const ok = element.querySelector('[data-confirm-ok]');
            let pending = null;

            document.addEventListener('submit', function (event) {
                const form = event.target;
                const message = form.dataset.confirm;

                if (! message || form.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();
                pending = { form: form, submitter: event.submitter || null };
                text.textContent = message;

                const danger = /supprimer|bloquer|retirer|refuser/i.test(message);
                ok.className = 'btn ' + (danger ? 'btn-danger' : 'bf-btn-primary');
                ok.textContent = danger ? 'Oui, confirmer' : 'Confirmer';
                modal.show();
            });

            ok.addEventListener('click', function () {
                if (! pending) return;

                const { form, submitter } = pending;
                pending = null;
                form.dataset.confirmed = '1';
                modal.hide();
                submitter && form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
            });
        })();

        document.querySelectorAll('.bf-flash.success').forEach(function (flash) {
            setTimeout(function () { flash.remove(); }, 6000);
        });
    </script>
    @stack('scripts')
</body>
</html>
