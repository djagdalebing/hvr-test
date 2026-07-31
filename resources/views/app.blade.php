@extends('common::framework')

@section('head-end')
    {{-- Make "Add to Home Screen" behave like a native app (full-screen,
         branded icon + name) on iOS and Android. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Her Vision Network">
    <link rel="apple-touch-icon" sizes="152x152" href="client/favicon/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="client/favicon/icon-192x192.png">

    {{-- Native app safe-area insets. Kept as raw CSS here (NOT in the Angular
         build) because Angular 11's CSS optimizer strips env(). The
         `hvn-native` class is added on <body> by the app when running in the
         Capacitor shell, so this is inert on the web. --}}
    <style>
        body.hvn-native app-root {
            padding-top: env(safe-area-inset-top, 0px);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
    </style>
@endsection

@section('body-end')
<script>
(function () {
    // Only intercept on PUBLIC-FACING pages. Inside /admin/*, the SPA's router
    // (Angular) handles all navigation natively — Creators and Community are
    // real Angular routes now (src/app/admin/creators-admin, community-admin).
    if (window.location.pathname.indexOf('/admin') === 0) return;

    function cleanUrl(url) {
        if (!url) return url;
        return url.replace(/(%20|\s)+$/, '');
    }

    // Fix address bar on hard load
    var p = window.location.pathname;
    if (/(%20|\s)+$/.test(p)) {
        history.replaceState(null, '', cleanUrl(p) + window.location.search + window.location.hash);
    }

    // Map link text → correct HVN public path. Angular's stored menu actions
    // sometimes point at /news or /people; we redirect those to our Blade pages.
    var HVN_TEXT_MAP = {
        'community': '/community',
        'creators':  '/creators',
    };
    // Paths that must always be a hard browser navigation (Blade pages).
    // /creator-signup removed — it's a regular SPA route now.
    var HVN_PREFIXES = ['/community', '/creators'];

    function isHvnPath(path) {
        return HVN_PREFIXES.some(function(p) {
            return path === p || (typeof path === 'string' && path.indexOf(p + '/') === 0);
        });
    }

    function hvnPathForText(text) {
        var t = (text || '').trim().toLowerCase();
        for (var key in HVN_TEXT_MAP) {
            if (t === key || new RegExp('\\b' + key + '\\b').test(t)) {
                return HVN_TEXT_MAP[key];
            }
        }
        return null;
    }

    // Patch nav anchors with wrongly-stored hrefs (Angular's primary menu data has /news, /people, etc.).
    function patchNavLinks() {
        // Re-check at every invocation — the IIFE early-return only fires once at
        // page load, but if the user later SPA-navigates into /admin/* this
        // observer-driven patcher would rewrite the native admin sidebar links.
        if (window.location.pathname.indexOf('/admin') === 0) return;
        // Only target the public site header/navbar, NOT admin sidenav nav.
        var links = document.querySelectorAll('[class*="navbar"] a, [class*="header"] a, .primary-menu a');
        links.forEach(function(a) {
            // Skip anything inside admin chrome (sidenav, admin component, mat-drawer, etc.)
            if (a.closest('admin, sidenav, mat-drawer, .admin-page, custom-menu.vertical')) return;
            var path = hvnPathForText(a.textContent);
            if (!path) return;
            if (a.getAttribute('href') !== path) a.setAttribute('href', path);
            if (!a.__hvnPatched) {
                a.__hvnPatched = true;
                a.addEventListener('click', function(e) {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    window.location.href = path;
                }, true);
            }
        });
    }

    setTimeout(patchNavLinks, 400);
    setTimeout(patchNavLinks, 1200);
    var navPatchTimer;
    new MutationObserver(function() {
        clearTimeout(navPatchTimer);
        navPatchTimer = setTimeout(patchNavLinks, 150);
    }).observe(document.body, { childList: true, subtree: true });

    // Capture-phase click interceptor: any link to /community, /creators,
    // /creator-signup or anything matching HVN_TEXT_MAP must hard-nav.
    document.addEventListener('click', function (e) {
        // Re-check on every click — initial IIFE return only protects page load.
        if (window.location.pathname.indexOf('/admin') === 0) return;
        var a = e.target && e.target.closest ? e.target.closest('a') : null;
        if (!a) return;
        // Never hijack clicks originating from admin chrome.
        if (a.closest('admin, sidenav, mat-drawer, .admin-page, custom-menu.vertical')) return;
        var href = a.getAttribute('href') || '';

        var hvnPath = hvnPathForText(a.textContent);
        if (hvnPath) {
            e.stopImmediatePropagation();
            e.preventDefault();
            window.location.href = hvnPath;
            return;
        }
        if (isHvnPath(href)) {
            e.stopImmediatePropagation();
            e.preventDefault();
            window.location.href = href;
            return;
        }
        if (!/(%20|\s)+$/.test(href)) return;
        var clean = cleanUrl(href);
        if (!clean || clean === href) return;
        e.stopImmediatePropagation();
        e.preventDefault();
        history.pushState(null, '', clean);
        window.dispatchEvent(new PopStateEvent('popstate', { state: history.state }));
    }, true);

    // HVN: 'Want to share your content? Join as a Creator →' CTA removed —
    // every signup is a creator now, so the separate prompt is meaningless.
}());
</script>
@endsection

@section('angular-styles')
    {{--angular styles begin--}}
		{{-- This is the ORIGINAL Vebto-built CSS. Do not regenerate — our reconstructed SCSS is a stub. --}}
		<link rel="stylesheet" href="client/styles.dd30edb2e30333fe4043.css">
	{{--angular styles end--}}
@endsection

@section('angular-scripts')
    {{--angular scripts begin — bundle hashes auto-rewritten by CI --}}
		<script src="client/runtime-es2015.99e448decfab06770d11.js" type="module"></script>
		<script src="client/runtime-es5.99e448decfab06770d11.js" nomodule defer></script>
		<script src="client/polyfills-es2015.efb9d3bbd257407bb0da.js" type="module"></script>
		<script src="client/polyfills-es5.6f7ddfe1967d03b32b3b.js" nomodule defer></script>
		<script src="client/main-es2015.cd43516521650b9ac50c.js" type="module"></script>
		<script src="client/main-es5.cd43516521650b9ac50c.js" nomodule defer></script>
	{{--angular scripts end--}}
@endsection
