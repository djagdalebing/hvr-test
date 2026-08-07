#!/usr/bin/env bash
#
# Build the Phase-2 bundled web assets (capacitor `webDir`) for the native app.
#
# The real production frontend is the pre-built Vebto bundle served by the live
# site (CI rebuilds it on every deploy; the hashes are NOT committed here). So
# instead of `ng build` (which would regenerate a stub stylesheet and mismatched
# hashes), we mirror the exact deployed assets:
#
#   1. Fetch the live app shell (index.html) and strip the server-injected
#      `window.bootstrapData` so the app fetches bootstrap-data at runtime — with
#      the stored bearer token attached, it boots already-authenticated.
#   2. Parse the live webpack runtime for the lazy-chunk hash map and download
#      every JS chunk + main/runtime/polyfills + styles into webDir/client.
#   3. Copy the static assets (favicon/, assets/, manifest) from local public/client.
#   4. Inject a tiny <img> URL rewriter so backend-relative `storage/…` media
#      (branding logo, avatars, uploaded posters) resolve against the backend
#      (they can't load from the capacitor://localhost origin otherwise).
#
# After running this, from frontend-source/:  npx cap copy ios  &&  rebuild.
#
set -euo pipefail

BACKEND="https://aqua-narwhal-640720.hostingersite.com"
HERE="$(cd "$(dirname "$0")" && pwd)"
DIST="$HERE/mobile-dist"
PUB="$HERE/../public/client"

echo "==> resetting $DIST"
rm -rf "$DIST"; mkdir -p "$DIST/client"

echo "==> fetching live shell"
curl -fsS "$BACKEND/" -o "$DIST/index.raw.html"

echo "==> transforming index.html (strip bootstrapData + GA, base=/)"
python3 - "$DIST/index.raw.html" "$DIST/index.html" <<'PY'
import sys, re
html = open(sys.argv[1], encoding='utf-8').read()
html = re.sub(r'window\.bootstrapData\s*=\s*"[^"]*";', 'window.bootstrapData = "";', html)
html = re.sub(r'<script>\s*\(function\(i,s,o,g,r,a,m\).*?</script>', '', html, flags=re.S)
html = re.sub(r'<base href="[^"]*">', '<base href="/">', html)
# Inject native-app chrome CSS (safe areas + polish) + the storage/ <img>
# URL rewriter, right before </head>. The CSS uses !important and comes after
# the fetched head, so it overrides the live app-root safe-area rule.
inject = '''    <style>
        /* Native app (Capacitor) chrome — safe areas + polish. Applies only in
           the native shell (body.hvn-native); the website is unaffected. */
        body.hvn-native app-root { padding-top: 0 !important; padding-bottom: env(safe-area-inset-bottom, 0px); }
        body.hvn-native material-navbar {
            padding-top: env(safe-area-inset-top, 0px);
            height: calc(70px + env(safe-area-inset-top, 0px)) !important;
            box-sizing: border-box;
        }
        body.hvn-native .landing-header, body.hvn-native header.transparent { padding-top: env(safe-area-inset-top, 0px); }
        body.hvn-native::before {
            content: ''; position: fixed; top: 0; left: 0; right: 0;
            height: env(safe-area-inset-top, 0px);
            background: var(--be-primary-default, #121212); z-index: 6; pointer-events: none;
        }
        body.hvn-native .fixed-bottom, body.hvn-native footer { padding-bottom: env(safe-area-inset-bottom, 0px); }
        /* Home hero: clear the caption from the absolute transparent navbar. */
        body.hvn-native slider .slide-cover {
            align-items: flex-start;
            padding-top: calc(70px + env(safe-area-inset-top, 0px) + 22px);
            box-sizing: border-box;
        }
        body.hvn-native slider::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: calc(120px + env(safe-area-inset-top, 0px));
            background: linear-gradient(to bottom, rgba(0,0,0,.65), rgba(0,0,0,0));
            pointer-events: none; z-index: 1;
        }
        body.hvn-native slider material-navbar { z-index: 2; }
        body.hvn-native material-navbar .mat-icon-button { width: 44px; height: 44px; line-height: 44px; }
        /* Web cookie-consent banner is useless in native (capacitor:// scheme
           doesn't persist the consent cookie, so it reappears everywhere). */
        body.hvn-native cookie-notice { display: none !important; }
    </style>
    <script>
    (function () {
        var BACKEND = 'BACKEND_URL';
        function absolutize(u){ if(!u) return u; var p=u.replace(/^\\.?\\//,''); return /^storage\\//.test(p)?BACKEND+'/'+p:u; }
        function fix(el){ var s=el.getAttribute&&el.getAttribute('src'); if(s){var a=absolutize(s); if(a!==s) el.setAttribute('src',a);} }
        function scan(r){ if(r&&r.querySelectorAll){var n=r.querySelectorAll('img,source'); for(var i=0;i<n.length;i++) fix(n[i]);} }
        document.addEventListener('DOMContentLoaded',function(){scan(document);});
        new MutationObserver(function(m){for(var i=0;i<m.length;i++){var a=m[i].addedNodes;if(a)for(var j=0;j<a.length;j++){var n=a[j];if(n.nodeType===1){if(n.tagName==='IMG'||n.tagName==='SOURCE')fix(n);scan(n);}}if(m[i].type==='attributes'&&m[i].target)fix(m[i].target);}}).observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:['src']});
    })();
    </script>
'''.replace('BACKEND_URL', "https://aqua-narwhal-640720.hostingersite.com")
html = html.replace('</head>', inject + '</head>', 1)
open(sys.argv[2], 'w', encoding='utf-8').write(html)
PY
rm -f "$DIST/index.raw.html"

echo "==> downloading live JS/CSS bundles"
main=$(grep -oE 'client/main-es2015\.[a-f0-9]+\.js' "$DIST/index.html" | head -1 | sed 's#client/##')
rt=$(grep -oE 'client/runtime-es2015\.[a-f0-9]+\.js' "$DIST/index.html" | head -1 | sed 's#client/##')
poly2015=$(grep -oE 'client/polyfills-es2015\.[a-f0-9]+\.js' "$DIST/index.html" | head -1 | sed 's#client/##')
rthash=$(echo "$rt" | sed -E 's/runtime-es2015\.([a-f0-9]+)\.js/\1/')
mainhash=$(echo "$main" | sed -E 's/main-es2015\.([a-f0-9]+)\.js/\1/')
curl -fsS "$BACKEND/client/$rt" -o "/tmp/rt2015.js"
curl -fsS "$BACKEND/client/runtime-es5.$rthash.js" -o "/tmp/rt5.js"

python3 - "$BACKEND" "$DIST/client" "$mainhash" "$rthash" "$poly2015" <<'PY'
import sys, re, subprocess, os
backend, out, mainhash, rthash, poly2015 = sys.argv[1:6]
def chunkmap(p):
    js=open(p,encoding='utf-8',errors='ignore').read(); m={}
    for obj in re.findall(r'\{((?:"?\d+"?:"[a-f0-9]{20}",?)+)\}', js):
        for cid,h in re.findall(r'"?(\d+)"?:"([a-f0-9]{20})"',obj): m[cid]=h
    return m
files=set([f"runtime-es2015.{rthash}.js",f"runtime-es5.{rthash}.js",
           f"main-es2015.{mainhash}.js",f"main-es5.{mainhash}.js", poly2015])
# es5 polyfill hash differs — read from index? just grab from live dir listing via known name pattern
for v,p in [('es2015','/tmp/rt2015.js'),('es5','/tmp/rt5.js')]:
    for cid,h in chunkmap(p).items(): files.add(f"{cid}-{v}.{h}.js")
for f in sorted(files):
    subprocess.run(["curl","-fsS",f"{backend}/client/{f}","-o",os.path.join(out,f)], check=False)
print("downloaded", len(files), "hashed bundles")
PY
# es5 polyfill (hash differs from es2015) — pull the one referenced by the shell if present locally
poly5=$(grep -oE 'client/polyfills-es5\.[a-f0-9]+\.js' "$DIST/index.html" | head -1 | sed 's#client/##') || true
[ -n "${poly5:-}" ] && curl -fsS "$BACKEND/client/$poly5" -o "$DIST/client/$poly5" || true

echo "==> copying static assets (favicon/, assets/, manifest) + patched styles from local public/client"
for item in favicon assets manifest.json favicon.ico 3rdpartylicenses.txt styles.dd30edb2e30333fe4043.css; do
    [ -e "$PUB/$item" ] && cp -R "$PUB/$item" "$DIST/client/" || true
done

echo "==> done. webDir at: $DIST"
echo "    next:  npx cap copy ios   (then rebuild the Xcode App scheme)"
