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
        var inAdmin = window.location.pathname.indexOf('/admin') === 0;
        for (var key in HVN_TEXT_MAP) {
            if (t === key || new RegExp('\\b' + key + '\\b').test(t)) {
                var path = HVN_TEXT_MAP[key];
                // Inside /admin, route community/creators links to the admin pages
                if (inAdmin && (path === '/creators' || path === '/community')) {
                    return '/admin' + path;
                }
                return path;
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

    // ----- Iframe overlay (shared by both click handlers) -----
    var ADMIN_OVERLAY_PATHS = ['/admin/creators', '/admin/community'];
    var IFRAME_ID = 'hvn-admin-iframe';
    var iframeDest = null;

    function isAdminOverlayPath(path) {
        if (!path) return false;
        for (var i = 0; i < ADMIN_OVERLAY_PATHS.length; i++) {
            var p = ADMIN_OVERLAY_PATHS[i];
            if (path === p || path.indexOf(p + '/') === 0 || path.indexOf(p + '?') === 0) return true;
        }
        return false;
    }

    function findContentHost() {
        return document.querySelector('.content-inner')
            || document.querySelector('mat-sidenav-content')
            || document.querySelector('sidenav main')
            || document.querySelector('main')
            || document.body;
    }

    var iframeHost = null;
    var iframeRepositionTimer = null;
    var sidebarPatchedLink = null;
    var sidebarPatchedClasses = [];
    var sidebarDeactivated = []; // [{el, classes}]

    // Stable class identifier on each SPA sidebar nav-item (set by Angular component, untouched by our shim).
    var DEST_TO_SIDEBAR_CLASS = {
        '/admin/creators':  'route-nav-item.people',
        '/admin/community': 'route-nav-item.news',
    };

    function findSpaSidebarLink(dest) {
        var cls = DEST_TO_SIDEBAR_CLASS[dest];
        if (!cls) return null;
        return document.querySelector('a.' + cls);
    }

    function getActiveClassesFromCurrent() {
        // Sample the SPA's currently-active link to learn what classes mean "active"
        var probes = ['sidenav a.active', 'sidenav a.router-link-active', 'aside a.active', 'nav a.active', 'a.router-link-active'];
        for (var i = 0; i < probes.length; i++) {
            var el = document.querySelector(probes[i]);
            if (el) {
                return el.className.split(/\s+/).filter(function(c) { return /active/i.test(c); });
            }
        }
        return ['active', 'router-link-active', 'router-link-active-exact'];
    }

    function activateSidebarFor(dest) {
        deactivateSidebar(); // reset previous state
        var link = findSpaSidebarLink(dest);
        if (!link) return;
        var activeClasses = getActiveClassesFromCurrent();
        // Remove active from any currently-active siblings so highlight moves over
        var currents = document.querySelectorAll('sidenav a.router-link-active, sidenav a.active, aside a.active, nav a.active, a.router-link-active-exact');
        for (var i = 0; i < currents.length; i++) {
            var el = currents[i];
            if (el === link) continue;
            var removed = activeClasses.filter(function(c) { return el.classList.contains(c); });
            removed.forEach(function(c) { el.classList.remove(c); });
            if (removed.length) sidebarDeactivated.push({el: el, classes: removed});
        }
        // Add active classes to our link
        activeClasses.forEach(function(c) { link.classList.add(c); });
        sidebarPatchedLink = link;
        sidebarPatchedClasses = activeClasses;
    }

    function deactivateSidebar() {
        if (sidebarPatchedLink) {
            sidebarPatchedClasses.forEach(function(c) { sidebarPatchedLink.classList.remove(c); });
        }
        sidebarDeactivated.forEach(function(rec) {
            rec.classes.forEach(function(c) { rec.el.classList.add(c); });
        });
        sidebarPatchedLink = null;
        sidebarPatchedClasses = [];
        sidebarDeactivated = [];
    }

    function positionIframeOverHost(iframe, host) {
        var r = host.getBoundingClientRect();
        // Use fixed positioning relative to viewport, sized from the host's rect.
        iframe.style.top    = r.top + 'px';
        iframe.style.left   = r.left + 'px';
        iframe.style.width  = r.width + 'px';
        iframe.style.height = r.height + 'px';
    }

    function showOverlay(dest) {
        if (window.location.pathname.indexOf('/admin') !== 0) {
            window.location.href = dest;
            return;
        }
        if (iframeDest === dest) return;
        var host = findContentHost();
        if (!host) { window.location.href = dest; return; }

        var iframe = document.getElementById(IFRAME_ID);
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = IFRAME_ID;
            iframe.style.cssText = 'position:fixed;border:0;background:#fff;z-index:50;';
            document.body.appendChild(iframe);
            // Hide host's children so the SPA's "real" content underneath doesn't show.
            Array.prototype.forEach.call(host.children, function(c) {
                if (c !== iframe) { c.dataset.__hvnHidden = c.style.visibility || ''; c.style.visibility = 'hidden'; }
            });
            iframeHost = host;
            // Track resizes & SPA layout changes
            window.addEventListener('resize', schedulePosition);
            schedulePosition();
        }
        positionIframeOverHost(iframe, host);
        iframe.src = dest + (dest.indexOf('?') === -1 ? '?bare=1' : '&bare=1');
        iframeDest = dest;
        try { history.pushState({hvnOverlay: dest}, '', dest); } catch (err) {}
        activateSidebarFor(dest);
    }

    function schedulePosition() {
        clearTimeout(iframeRepositionTimer);
        iframeRepositionTimer = setTimeout(function() {
            var iframe = document.getElementById(IFRAME_ID);
            if (iframe && iframeHost) positionIframeOverHost(iframe, iframeHost);
        }, 50);
    }

    function hideOverlay() {
        var iframe = document.getElementById(IFRAME_ID);
        if (!iframe) { iframeDest = null; return; }
        iframe.remove();
        if (iframeHost) {
            Array.prototype.forEach.call(iframeHost.children, function(c) {
                if ('__hvnHidden' in c.dataset) {
                    c.style.visibility = c.dataset.__hvnHidden;
                    delete c.dataset.__hvnHidden;
                }
            });
        }
        iframeHost = null;
        iframeDest = null;
        window.removeEventListener('resize', schedulePosition);
        deactivateSidebar();
    }

    // Intercept ALL link clicks (capture phase) — catches any remaining HVN hrefs
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a') : null;
        if (!a) return;
        var href = a.getAttribute('href') || '';
        var inAdmin = window.location.pathname.indexOf('/admin') === 0;

        // Text-based check takes priority — corrects Angular's wrong hrefs (e.g. "Join as Creator" → /creator-signup)
        var hvnPath = hvnPathForText(a.textContent);
        if (hvnPath) {
            e.stopImmediatePropagation();
            e.preventDefault();
            if (inAdmin && isAdminOverlayPath(hvnPath)) {
                showOverlay(hvnPath);
            } else {
                window.location.href = hvnPath;
            }
            return;
        }

        // Force hard navigation for HVN paths (or overlay if inside admin)
        if (isHvnPath(href)) {
            e.stopImmediatePropagation();
            e.preventDefault();
            if (inAdmin && isAdminOverlayPath(href)) {
                showOverlay(href);
            } else {
                window.location.href = href;
            }
            return;
        }

        // If user clicked any OTHER link while overlay is up, dismiss it so SPA can render the new route.
        if (iframeDest && !isAdminOverlayPath(href)) {
            hideOverlay();
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

    // Admin redirect + label rename: when the user clicks the Angular admin's
    // "People" or "News" sidebar items (or visits those URLs directly), take them
    // to the HVN admin pages instead. Also rename the labels in place.
    (function adminRedirect() {
        if (window.location.pathname.indexOf('/admin') !== 0) return;

        // path → { dest, label } — anchored on the href so we never touch
        // unrelated DOM. Each entry only affects <a href="/admin/{key}">.
        var ITEM_MAP = {
            '/admin/people': { dest: '/admin/creators',  label: 'Creators'  },
            '/admin/news':   { dest: '/admin/community', label: 'Community' },
            '/creators':     { dest: '/admin/creators',  label: 'Creators'  },
            '/community':    { dest: '/admin/community', label: 'Community' },
        };

        function lookup(path) {
            if (!path) return null;
            path = path.replace(/^https?:\/\/[^/]+/, '');
            for (var k in ITEM_MAP) {
                if (path === k || path.indexOf(k + '/') === 0 || path.indexOf(k + '?') === 0) {
                    return ITEM_MAP[k];
                }
            }
            return null;
        }

        // Bounce direct visits to /admin/people or /admin/news to their overlay equivalents.
        var here = lookup(window.location.pathname);
        if (here) {
            window.location.replace(here.dest);
            return;
        }

        // Click interceptor for sidebar items mapped via ITEM_MAP (People/News, etc.).
        document.addEventListener('click', function(e) {
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            var match = lookup(a.getAttribute('href'));
            if (!match) return;
            e.stopImmediatePropagation();
            e.preventDefault();
            showOverlay(match.dest);
        }, true);

        // Back/forward button support.
        window.addEventListener('popstate', function() {
            var path = window.location.pathname;
            if (isAdminOverlayPath(path)) {
                showOverlay(path);
            } else if (iframeDest) {
                hideOverlay();
            }
        });

        // Narrow rename: only touches <a href="/admin/people"> and <a href="/admin/news">.
        // Within each matched anchor we rename ONLY text nodes whose trimmed lowercase
        // text equals "people" or "news" — sibling icons, badges, and unrelated DOM
        // are never touched.
        function renameAnchorLabel(a, oldText, newText) {
            if (a.__hvnLabelPatched) return;
            // Walk only the direct descendants of this anchor.
            var walker = document.createTreeWalker(a, NodeFilter.SHOW_TEXT, null);
            var node, didReplace = false;
            while ((node = walker.nextNode())) {
                var t = (node.textContent || '').trim().toLowerCase();
                if (t === oldText) {
                    node.textContent = node.textContent.replace(/\S.*\S|\S/, newText);
                    didReplace = true;
                }
            }
            if (didReplace) a.__hvnLabelPatched = true;
        }

        function patchLabels() {
            // Match by exact href ending — Angular always renders an href on its
            // routerLink anchors, so this catches the sidebar items reliably.
            var sel = 'a[href$="/admin/people"], a[href$="/admin/news"]';
            document.querySelectorAll(sel).forEach(function(a) {
                var match = lookup(a.getAttribute('href'));
                if (!match) return;
                var oldText = a.getAttribute('href').indexOf('/admin/people') !== -1 ? 'people' : 'news';
                renameAnchorLabel(a, oldText, match.label);
            });
        }

        setTimeout(patchLabels, 300);
        setTimeout(patchLabels, 1200);
        setTimeout(patchLabels, 3000);
        var obs = new MutationObserver(function() { patchLabels(); });
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
		{{-- This is the ORIGINAL Vebto-built CSS. Do not regenerate — our reconstructed SCSS is a stub. --}}
		<link rel="stylesheet" href="client/styles.dd30edb2e30333fe4043.css">
	{{--angular styles end--}}
@endsection

@section('angular-scripts')
    {{--angular scripts begin — LOCAL TEST: pointing at our reconstructed bundle --}}
		<script src="client/runtime-es2015.4ad46ccd5c0b2bab2626.js" type="module"></script>
		<script src="client/runtime-es5.4ad46ccd5c0b2bab2626.js" nomodule defer></script>
		<script src="client/polyfills-es2015.d969de841f4a9c1b338c.js" type="module"></script>
		<script src="client/polyfills-es5.38fac56fdf9abe29758f.js" nomodule defer></script>
		<script src="client/main-es2015.562bd08746c91771597b.js" type="module"></script>
		<script src="client/main-es5.562bd08746c91771597b.js" nomodule defer></script>
	{{--angular scripts end--}}
@endsection
