<!DOCTYPE html>
<html lang="en" class="be-dark-mode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — HVN Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/client/styles.dd30edb2e30333fe4043.css">
    <style id="be-css-variables">
        :root {
            --be-primary-lighter:#333; --be-primary-default:#242424; --be-primary-darker:#1e1e1e;
            --be-accent-default:rgba(19,128,208,1); --be-accent-lighter:rgba(4,202,234,1);
            --be-accent-contrast:rgba(255,255,255,1); --be-accent-emphasis:rgba(233,236,254,0.1);
            --be-background:#1D1D1D; --be-background-alternative:#121212;
            --be-foreground-base:#fff; --be-text:#fff;
            --be-hint-text:rgba(255,255,255,0.5); --be-secondary-text:rgba(255,255,255,0.7);
            --be-label:rgba(255,255,255,0.7); --be-disabled-button-text:rgba(255,255,255,0.3);
            --be-divider-lighter:rgba(255,255,255,0.06); --be-divider-default:rgba(255,255,255,0.12);
            --be-hover:rgba(255,255,255,0.04); --be-selected-button:#212121;
            --be-chip:#616161; --be-link:#c5cae9; --be-backdrop:#BDBDBD; --be-raised-button:#424242;
            --be-disabled-toggle:#000; --be-disabled-button:rgba(255,255,255,0.12);
        }
    </style>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--be-background); color: var(--be-text); font-family: 'Roboto', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }

        /* TOP NAV */
        .hvn-nav {
            background: var(--be-primary-default); color: #fff;
            border-bottom: 1px solid var(--be-divider-default);
            padding: 0 24px; display: flex; align-items: center; height: 64px;
            position: sticky; top: 0; z-index: 100; flex-shrink: 0;
        }
        .hvn-logo { display: flex; align-items: center; text-decoration: none; flex-shrink: 0; margin-right: 24px; }
        .hvn-logo img { height: 36px; width: auto; display: block; }
        .admin-badge {
            font-size: 11px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase;
            background: var(--be-accent-default); color: var(--be-accent-contrast);
            padding: 3px 8px; border-radius: 4px; margin-left: 10px;
        }
        .nav-spacer { flex: 1; }
        .hvn-user { display: flex; align-items: center; gap: 8px; flex-shrink: 0; cursor: pointer; position: relative; }
        .hvn-user-avatar {
            width: 32px; height: 32px; border-radius: 4px; background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 600; color: #fff; overflow: hidden;
        }
        .hvn-user-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
        .hvn-user-email { font-size: 13px; color: rgba(255,255,255,0.85); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .hvn-user-caret { color: rgba(255,255,255,0.6); font-size: 10px; }
        .hvn-user-menu {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            background: var(--be-background); border: 1px solid var(--be-divider-default);
            border-radius: 4px; min-width: 160px; padding: 6px 0; z-index: 200;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .hvn-user-menu.open { display: block; }
        .hvn-user-menu a, .hvn-user-menu button {
            display: block; width: 100%; text-align: left; padding: 9px 16px;
            font-size: 14px; color: var(--be-text); text-decoration: none;
            background: none; border: none; cursor: pointer; font-family: inherit;
        }
        .hvn-user-menu a:hover, .hvn-user-menu button:hover { background: var(--be-hover); }

        /* BODY LAYOUT */
        .admin-body { display: flex; flex: 1; min-height: 0; }

        /* MAIN CONTENT */
        .admin-main { flex: 1; min-width: 0; padding: 32px 36px 64px; overflow-y: auto; background: var(--be-background); }

        /* PAGE HEADING */
        .page-heading { margin-bottom: 28px; }
        .page-heading h1 { font-size: 24px; font-weight: 500; color: #fff; }
        .page-heading p { color: #666; font-size: 14px; margin-top: 4px; }

        /* TABLE */
        .admin-table-wrap { background: #2a2a2a; border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; overflow: hidden; }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .admin-table th { background: #222; text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: #555; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .admin-table td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #ccc; vertical-align: middle; }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: rgba(255,255,255,0.02); }

        /* BADGES / STATUS */
        .badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-green  { background: #152d1a; color: #4ade80; border: 1px solid #1e5c2d; }
        .badge-red    { background: #2d1515; color: #f87171; border: 1px solid #6b2020; }
        .badge-gray   { background: #222; color: #666; border: 1px solid #333; }
        .badge-amber  { background: #2d2010; color: #fbbf24; border: 1px solid #5c3e10; }

        /* ACTIONS */
        .action-btns { display: flex; gap: 8px; align-items: center; }
        .btn-action {
            background: none; border: 1px solid #333; border-radius: 5px;
            padding: 5px 12px; font-size: 12px; color: #aaa;
            cursor: pointer; font-family: inherit; transition: border-color .15s, color .15s, background .15s;
        }
        .btn-action:hover { border-color: #F65F54; color: #F65F54; }
        .btn-action.danger:hover { border-color: #f87171; color: #f87171; }
        .btn-action.success { border-color: #1e5c2d; color: #4ade80; }
        .btn-action.success:hover { background: rgba(74,222,128,.08); }

        /* SEARCH BAR */
        .admin-search-bar { display: flex; gap: 12px; margin-bottom: 20px; }
        .admin-search-bar input {
            flex: 1; background: #222; border: 1px solid #333; border-radius: 6px;
            color: #e0e0e0; padding: 9px 14px; font-size: 14px; font-family: inherit;
            outline: none; transition: border-color .2s;
        }
        .admin-search-bar input:focus { border-color: #F65F54; }
        .admin-search-bar button {
            background: #F65F54; color: #fff; border: none; border-radius: 6px;
            padding: 9px 20px; font-size: 14px; font-family: inherit; cursor: pointer;
        }
        .admin-search-bar button:hover { background: #d94f45; }

        /* SECTION HEADER */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .section-header h2 { font-size: 16px; font-weight: 500; color: #bbb; }

        /* FLASH */
        .flash-msg { padding: 12px 18px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; }
        .flash-success { background: #152d1a; border: 1px solid #1e5c2d; color: #4ade80; }
        .flash-error   { background: #2d1515; border: 1px solid #6b2020; color: #f87171; }

        /* PAGINATION */
        .pagination { display: flex; gap: 8px; justify-content: center; align-items: center; margin-top: 24px; }
        .pagination a, .pagination span {
            padding: 7px 14px; border-radius: 5px; font-size: 13px;
            background: #222; border: 1px solid #333; color: #aaa; text-decoration: none;
        }
        .pagination a:hover { background: #F65F54; border-color: #F65F54; color: #fff; }
        .pagination .pg-info { background: transparent; border-color: transparent; color: #555; }

        @media (max-width: 768px) {
            .admin-main { padding: 20px 16px 48px; }
        }

        /* BARE MODE — when embedded as an iframe inside the SPA admin */
        body.bare .hvn-nav { display: none !important; }
        body.bare .admin-main { padding: 20px 24px 48px; }
        body.bare .admin-body { display: block; }
    </style>
    @yield('head')
</head>
<body class="{{ request()->boolean('bare') ? 'bare' : '' }}">

<nav class="hvn-nav">
    <a href="/" class="hvn-logo">
        <img src="/storage/branding_media/BYnrmXBiztBYfakdtYol94onVywTZ2TfQDGCUYId.png" alt="Her Vision Network">
    </a>
    <span class="admin-badge">Admin</span>
    <div class="nav-spacer"></div>
    @auth
        @php $u = auth()->user(); @endphp
        <div class="hvn-user">
            <div class="hvn-user-avatar">
                @if($u->avatar)
                    <img src="{{ $u->avatar }}" alt="{{ $u->username }}">
                @else
                    {{ strtoupper(substr($u->username ?? $u->email, 0, 1)) }}
                @endif
            </div>
            <span class="hvn-user-email">{{ $u->email }}</span>
            <span class="hvn-user-caret">▼</span>
            <div class="hvn-user-menu">
                <a href="/">Back to Site</a>
                <form action="/logout" method="POST" style="margin:0">
                    @csrf
                    <button type="submit">Sign Out</button>
                </form>
            </div>
        </div>
    @endauth
</nav>

<div class="admin-body">
    <main class="admin-main">
        @if(session('flash'))
            @php $flash = session('flash'); @endphp
            <div class="flash-msg flash-{{ $flash['type'] === 'success' ? 'success' : 'error' }}">
                {{ $flash['message'] }}
            </div>
        @endif

        @yield('content')
    </main>
</div>

@yield('scripts')
<script>
(function () {
    var toggle = document.querySelector('.hvn-user');
    var menu   = document.querySelector('.hvn-user-menu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function () { menu.classList.remove('open'); });
}());
</script>
</body>
</html>
