@extends('common::framework')

@section('body-end')
<script>
(function () {
    function cleanUrl(url) {
        if (!url) return url;
        return url.replace(/(%20|\s)+$/, '');
    }

    // Fix address bar on hard load
    var p = window.location.pathname;
    if (/(%20|\s)+$/.test(p)) {
        history.replaceState(null, '', cleanUrl(p) + window.location.search + window.location.hash);
    }

    // Map link text → correct HVN path (Angular stores wrong actions in the DB)
    // Keys are matched as exact words only — 'creators' must not match 'Join as Creator'.
    var HVN_TEXT_MAP = {
        'community': '/community',
        'creators':  '/creators',
    };
    // Paths that must always be a hard browser navigation (Angular has no route for them)
    var HVN_PREFIXES = ['/community', '/creators', '/creator-signup'];

    function isHvnPath(path) {
        return HVN_PREFIXES.some(function(p) {
            return path === p || path.startsWith(p + '/');
        });
    }

    function hvnPathForText(text) {
        var t = (text || '').trim().toLowerCase();
        // "Join as Creator" / "Join as a Creator" CTAs always go to signup
        if (t.indexOf('join') !== -1 && t.indexOf('creator') !== -1) {
            return '/creator-signup';
        }
        for (var key in HVN_TEXT_MAP) {
            if (t === key || new RegExp('\\b' + key + '\\b').test(t)) {
                return HVN_TEXT_MAP[key];
            }
        }
        return null;
    }

    // Patch every nav <a> whose visible text matches an HVN label.
    // This fixes links that Angular rendered with the wrong href (e.g. /news).
    function patchNavLinks() {
        var links = document.querySelectorAll('nav a, .nav a, [class*="navbar"] a, [class*="header"] a');
        links.forEach(function(a) {
            var path = hvnPathForText(a.textContent);
            if (!path) return;
            if (a.getAttribute('href') !== path) {
                a.setAttribute('href', path);
            }
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

    // Run once Angular has rendered, then watch for re-renders
    setTimeout(patchNavLinks, 400);
    setTimeout(patchNavLinks, 1200);
    var navPatchTimer;
    var navObs = new MutationObserver(function() {
        clearTimeout(navPatchTimer);
        navPatchTimer = setTimeout(patchNavLinks, 150);
    });
    navObs.observe(document.body, { childList: true, subtree: true });

    // Intercept ALL link clicks (capture phase) — catches any remaining HVN hrefs
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a') : null;
        if (!a) return;
        var href = a.getAttribute('href') || '';

        // Text-based check takes priority — corrects Angular's wrong hrefs (e.g. "Join as Creator" → /creator-signup)
        var hvnPath = hvnPathForText(a.textContent);
        if (hvnPath) {
            e.stopImmediatePropagation();
            e.preventDefault();
            window.location.href = hvnPath;
            return;
        }

        // Force hard navigation for HVN paths
        if (isHvnPath(href)) {
            e.stopImmediatePropagation();
            e.preventDefault();
            window.location.href = href;
            return;
        }

        // Clean trailing %20 from other links
        if (!/(%20|\s)+$/.test(href)) return;
        var clean = cleanUrl(href);
        if (!clean || clean === href) return;
        e.stopImmediatePropagation();
        e.preventDefault();
        history.pushState(null, '', clean);
        window.dispatchEvent(new PopStateEvent('popstate', { state: history.state }));
    }, true);

    // Patch Angular admin sidebar: rename People→Creators and News→Community,
    // redirecting to HVN admin pages instead of Angular routes /admin/people and /admin/news.
    (function patchAdminSidebar() {
        if (window.location.pathname.indexOf('/admin') !== 0) return;

        // Match by href suffix (most reliable — labels can vary, hrefs cannot).
        var HREF_MAP = {
            '/admin/people': { label: 'Creators',  href: '/hvn/admin/creators', oldText: 'people' },
            '/admin/news':   { label: 'Community', href: '/hvn/admin/community', oldText: 'news' },
        };

        function hrefMatch(href) {
            if (!href) return null;
            // Strip absolute-URL scheme/host so '/admin/people' matches 'https://site/admin/people'
            var path = href.replace(/^https?:\/\/[^/]+/, '');
            for (var k in HREF_MAP) {
                if (path === k || path.indexOf(k + '/') === 0 || path.indexOf(k + '?') === 0) {
                    return HREF_MAP[k];
                }
            }
            return null;
        }

        function replaceLabelText(el, oldText, newText) {
            // Walk text nodes recursively, replace any node whose trimmed lowercase text matches oldText.
            var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
            var node, replaced = false;
            while ((node = walker.nextNode())) {
                var t = (node.textContent || '').trim().toLowerCase();
                if (t === oldText) {
                    node.textContent = node.textContent.replace(/\S.*\S|\S/, newText);
                    replaced = true;
                }
            }
            return replaced;
        }

        function patchSidebarLinks() {
            // Catch every <a>, including ones nested inside Angular components.
            document.querySelectorAll('a[href]').forEach(function(a) {
                var map = hrefMatch(a.getAttribute('href'));
                if (!map) return;
                if (a.__hvnAdminPatched) return;
                a.__hvnAdminPatched = true;
                a.setAttribute('href', map.href);
                replaceLabelText(a, map.oldText, map.label);
                a.addEventListener('click', function(e) {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    window.location.href = map.href;
                }, true);
            });
        }

        // Global capture-phase click interceptor — catches any element whose ancestor
        // <a> points at /admin/people or /admin/news, even if patchSidebarLinks missed it
        // (e.g. dynamically inserted after observer disconnect).
        document.addEventListener('click', function(e) {
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            var map = hrefMatch(a.getAttribute('href'));
            if (!map) return;
            e.stopImmediatePropagation();
            e.preventDefault();
            window.location.href = map.href;
        }, true);

        // If the admin user navigates directly to /admin/people or /admin/news,
        // bounce them to the HVN admin equivalent.
        var here = window.location.pathname;
        var hereMap = hrefMatch(here);
        if (hereMap) {
            window.location.replace(hereMap.href);
            return;
        }

        setTimeout(patchSidebarLinks, 300);
        setTimeout(patchSidebarLinks, 1200);
        setTimeout(patchSidebarLinks, 3000);
        var obs = new MutationObserver(function() { patchSidebarLinks(); });
        obs.observe(document.body, { childList: true, subtree: true });
    }());

    // Inject "Join as a Creator" inside Angular's auth-page sign-in/register form.
    // auth-page .info-row  = the "Don't have an account?" row inside the panel.
    // auth-page .auth-panel = the white card containing the form.
    (function injectCreatorLink() {
        var LINK_ID = 'hvn-creator-link';

        function inject() {
            if (document.getElementById(LINK_ID)) return;
            if (!document.querySelector('auth-page')) return;

            // Best spot: right after the info-row (sign-in↔register toggle) inside the panel
            var infoRow   = document.querySelector('auth-page .info-row');
            var authPanel = document.querySelector('auth-page .auth-panel');
            var target    = infoRow || authPanel || document.querySelector('auth-page-footer') || document.querySelector('auth-page');
            if (!target) return;

            var div = document.createElement('div');
            div.id = LINK_ID;
            div.style.cssText = [
                'margin-top:16px',
                'padding-top:16px',
                'border-top:1px solid rgba(255,255,255,0.08)',
                'text-align:center',
                'font-size:13px',
                'color:rgba(255,255,255,0.6)',
                'font-family:Roboto,sans-serif',
            ].join(';');
            div.innerHTML = 'Want to share your content? '
                + '<a href="/creator-signup" onclick="event.stopPropagation();window.location.href=\'/creator-signup\';return false;" '
                + 'style="color:#F65F54;font-weight:500;text-decoration:none;">Join as a Creator →</a>';

            // Insert after infoRow, or append to panel/page
            if (infoRow && infoRow.parentNode) {
                infoRow.parentNode.insertBefore(div, infoRow.nextSibling);
            } else {
                target.appendChild(div);
            }
        }

        function cleanup() {
            if (!document.querySelector('auth-page')) {
                var el = document.getElementById(LINK_ID);
                if (el) el.remove();
            }
        }

        var obs = new MutationObserver(function() { inject(); cleanup(); });
        obs.observe(document.body, { childList: true, subtree: true });
    }());
}());
</script>
@endsection

@section('angular-styles')
    {{--angular styles begin--}}
		<link rel="stylesheet" href="client/styles.dd30edb2e30333fe4043.css" media="print" onload="this.media='all'">
	{{--angular styles end--}}
@endsection

@section('angular-scripts')
    {{--angular scripts begin--}}
		<script src="client/runtime.da6032f6256ba37882c7.js" defer=""></script>
		<script src="client/polyfills.d433a9329e434544e226.js" defer=""></script>
		<script src="client/main.51d3ab87516a2e615d53.js" defer=""></script>
	{{--angular scripts end--}}
@endsection
