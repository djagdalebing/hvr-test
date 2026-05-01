<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — HVN Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #1D1D1D; color: #e0e0e0; font-family: 'Roboto', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }

        /* TOP NAV */
        .hvn-nav {
            background: #121212; border-bottom: 1px solid rgba(255,255,255,0.06);
            padding: 0 24px; display: flex; align-items: center; height: 64px;
            position: sticky; top: 0; z-index: 100; flex-shrink: 0;
        }
        .hvn-logo { display: flex; align-items: center; text-decoration: none; flex-shrink: 0; margin-right: 24px; }
        .hvn-logo img { height: 36px; width: auto; display: block; }
        .admin-badge {
            font-size: 11px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase;
            background: #F65F54; color: #fff; padding: 3px 8px; border-radius: 4px; margin-left: 10px;
        }
        .nav-spacer { flex: 1; }
        .hvn-user {
            display: flex; align-items: center; gap: 8px; flex-shrink: 0;
            cursor: pointer; position: relative;
        }
        .hvn-user-avatar {
            width: 32px; height: 32px; border-radius: 4px; background: #3a3a3a;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 600; color: #ccc; overflow: hidden;
        }
        .hvn-user-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
        .hvn-user-email { font-size: 13px; color: #ccc; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .hvn-user-caret { color: #888; font-size: 10px; }
        .hvn-user-menu {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            background: #2a2a2a; border: 1px solid rgba(255,255,255,0.08);
            border-radius: 6px; min-width: 160px; padding: 6px 0; z-index: 200;
        }
        .hvn-user-menu.open { display: block; }
        .hvn-user-menu a, .hvn-user-menu button {
            display: block; width: 100%; text-align: left; padding: 9px 16px;
            font-size: 14px; color: #ccc; text-decoration: none;
            background: none; border: none; cursor: pointer; font-family: inherit;
        }
        .hvn-user-menu a:hover, .hvn-user-menu button:hover { background: rgba(255,255,255,0.06); color: #fff; }

        /* BODY LAYOUT: sidebar + content */
        .admin-body { display: flex; flex: 1; min-height: 0; }

        /* SIDEBAR */
        .admin-sidebar {
            width: 220px; flex-shrink: 0; background: #161616;
            border-right: 1px solid rgba(255,255,255,0.05);
            padding: 24px 0; display: flex; flex-direction: column;
        }
        .sidebar-section { font-size: 10px; font-weight: 600; letter-spacing: 1px;
            text-transform: uppercase; color: #444; padding: 0 20px 10px; margin-top: 16px; }
        .sidebar-section:first-child { margin-top: 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px; color: #888; text-decoration: none;
            font-size: 14px; border-left: 3px solid transparent;
            transition: color .15s, background .15s, border-color .15s;
        }
        .sidebar-nav a:hover { color: #e0e0e0; background: rgba(255,255,255,0.04); }
        .sidebar-nav a.active { color: #fff; background: rgba(246,95,84,0.1); border-left-color: #F65F54; }
        .sidebar-nav a svg { width: 16px; height: 16px; flex-shrink: 0; opacity: .7; }
        .sidebar-nav a.active svg { opacity: 1; }
        .sidebar-footer { margin-top: auto; padding: 16px 20px 0; }
        .sidebar-footer a { font-size: 13px; color: #555; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .sidebar-footer a:hover { color: #888; }

        /* MAIN CONTENT */
        .admin-main { flex: 1; min-width: 0; padding: 32px 36px 64px; overflow-y: auto; }

        /* PAGE HEADING */
        .page-heading { margin-bottom: 28px; }
        .page-heading h1 { font-size: 24px; font-weight: 500; color: #fff; }
        .page-heading p { color: #666; font-size: 14px; margin-top: 4px; }

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: #2a2a2a; border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 20px; }
        .stat-card .stat-label { font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 32px; font-weight: 600; color: #fff; line-height: 1; }
        .stat-card .stat-sub { font-size: 12px; color: #444; margin-top: 4px; }

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
            .admin-sidebar { display: none; }
            .admin-main { padding: 20px 16px 48px; }
        }
    </style>
    @yield('head')
</head>
<body>

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
    <aside class="admin-sidebar">
        <div class="sidebar-section">Overview</div>
        <nav class="sidebar-nav">
            <a href="/hvn/admin" class="{{ request()->is('hvn/admin') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
        </nav>
        <div class="sidebar-section">Manage</div>
        <nav class="sidebar-nav">
            <a href="/hvn/admin/creators" class="{{ request()->is('hvn/admin/creators*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Creators
            </a>
            <a href="/hvn/admin/community" class="{{ request()->is('hvn/admin/community*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Community
            </a>
            <a href="/hvn/admin/content" class="{{ request()->is('hvn/admin/content*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                Content
            </a>
        </nav>
        <div class="sidebar-section">Site Admin</div>
        <nav class="sidebar-nav">
            <a href="/admin/analytics">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21H4.6a.6.6 0 0 1-.6-.6V3"/><path d="M8 17l4-4 4 4 4-8"/></svg>
                Analytics
            </a>
            <a href="/admin/appearance">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/></svg>
                Appearance
            </a>
            <a href="/admin/settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
            <a href="/admin/plans">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                Plans
            </a>
            <a href="/admin/subscriptions">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                Subscriptions
            </a>
            <a href="/admin/titles">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h10M7 12h10M7 17h6"/></svg>
                Titles
            </a>
            <a href="/admin/videos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                Videos
            </a>
            <a href="/admin/lists">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Lists
            </a>
            <a href="/admin/reviews">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Reviews
            </a>
            <a href="/admin/comments">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Comments
            </a>
            <a href="/admin/users">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Users
            </a>
            <a href="/admin/roles">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Roles
            </a>
            <a href="/admin/pages">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Pages
            </a>
            <a href="/admin/tags">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Tags
            </a>
            <a href="/admin/files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Files
            </a>
            <a href="/admin/translations">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/></svg>
                Translations
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="/">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Site
            </a>
        </div>
    </aside>

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
