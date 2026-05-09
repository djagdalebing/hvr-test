<?php
// Extract component-scoped CSS from compiled Angular bundle and write into
// the matching .component.scss files in src/.

// Locate the original Vebto bundle backup. Local checkout has the repo
// rooted at .../hervisionnetwork.com/public_html/, so the bundle lives at
//   __DIR__/../public/client_original_backup
// CI checks the repo out as /home/runner/work/<repo>/<repo>/, with the same
// internal layout, so the same path works there. The legacy "public_html"
// segment is kept as a fallback for older local layouts.
$candidates = [
    __DIR__ . '/../public/client_original_backup',
    __DIR__ . '/../public_html/public/client_original_backup',
];
$bundleDir = null;
foreach ($candidates as $c) {
    if (is_dir($c)) { $bundleDir = $c; break; }
}
$srcDir = __DIR__ . '/src';

if (!$bundleDir) {
    fwrite(STDERR, "Bundle dir not found. Tried:\n  " . implode("\n  ", $candidates) . "\n");
    exit(1);
}
if (!is_dir($srcDir))    { fwrite(STDERR, "Src dir not found: $srcDir\n"); exit(1); }

// 1. Build a selector → scss-path index by scanning every component.ts in src
echo "Indexing component selectors...\n";
$selectorIndex = []; // [selector => path-to-scss]
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'ts') continue;
    $body = file_get_contents($f);
    // Match: selector: 'foo-bar'  OR  selector: "foo-bar"
    if (preg_match("#selector\s*:\s*['\"]([^'\"]+)['\"]#", $body, $m)) {
        $selector = $m[1];
        // Look for styleUrls: ['./foo.scss']
        if (preg_match("#styleUrls\s*:\s*\[\s*['\"]([^'\"]+)['\"]#", $body, $m2)) {
            $rel = $m2[1];
            $scssPath = realpath(dirname($f) . '/' . $rel);
            if (!$scssPath) {
                // file might not exist yet; keep desired path
                $scssPath = dirname($f) . '/' . $rel;
            }
            $selectorIndex[$selector] = $scssPath;
        }
    }
}
echo "Indexed " . count($selectorIndex) . " component selectors with styleUrls.\n";

// 2. Walk every .js bundle, extract pairs of (selector, css) by scanning each
//    styles:[ and searching back for the nearest selectors:[[
function extractStylesArrays($source) {
    $out = []; // each entry: [selectorOrNull, css]
    $i = 0; $n = strlen($source);
    while (($pos = strpos($source, 'styles:[', $i)) !== false) {
        $i = $pos + 8;
        if ($i >= $n || ($source[$i] !== '"' && $source[$i] !== "'")) { $i++; continue; }

        // Find nearest preceding selectors:[[ within ~6 KB
        $back = max(0, $pos - 6144);
        $window = substr($source, $back, $pos - $back);
        $selector = null;
        if (preg_match_all('/selectors:\[\[\s*"([^"]+)"/', $window, $sm)) {
            $selector = end($sm[1]);
        } elseif (preg_match_all("/selectors:\\[\\[\\s*'([^']+)'/", $window, $sm)) {
            $selector = end($sm[1]);
        }

        // Now read the JS string literal
        $quote = $source[$i++];
        $buf = '';
        while ($i < $n) {
            $c = $source[$i];
            if ($c === '\\' && $i + 1 < $n) {
                $next = $source[$i + 1];
                $i += 2;
                switch ($next) {
                    case 'n': $buf .= "\n"; break;
                    case 't': $buf .= "\t"; break;
                    case 'r': $buf .= "\r"; break;
                    case '"': $buf .= '"'; break;
                    case "'": $buf .= "'"; break;
                    case '\\': $buf .= "\\"; break;
                    case '/':  $buf .= '/'; break;
                    case 'u':
                        if ($i + 4 <= $n) {
                            $hex = substr($source, $i, 4);
                            $i += 4;
                            $buf .= mb_chr(hexdec($hex), 'UTF-8');
                        }
                        break;
                    default: $buf .= $next; break;
                }
                continue;
            }
            if ($c === $quote) { $i++; break; }
            $buf .= $c;
            $i++;
        }
        $out[] = [$selector, $buf];
    }
    return $out;
}

// 3. For each extracted CSS string, guess component selector (first identifier)
function guessSelector($css) {
    // Angular component CSS often starts with the host selector, e.g.:
    //   "my-component { ... }" or
    //   "my-component .child { ... }" or
    //   ".some-class { ... }"
    // We try to find the first token that looks like an Angular component selector
    // (kebab-case word with at least one hyphen).
    if (preg_match('/^\s*([a-z][a-z0-9]*-[a-z0-9-]+)/i', $css, $m)) {
        return $m[1];
    }
    return null;
}

$writeMap = []; // [scssPath => [css, ...]]
$totalExtracted = 0; $unmapped = 0;

foreach (glob("$bundleDir/*.js") as $jsFile) {
    if (preg_match('/runtime|polyfills/', basename($jsFile))) continue;
    $source = file_get_contents($jsFile);
    $pairs = extractStylesArrays($source);
    $totalExtracted += count($pairs);
    foreach ($pairs as [$selectorFromBundle, $css]) {
        // Prefer selector found in bundle's selectors:[[ ]]; fall back to guess from CSS
        $selector = $selectorFromBundle;
        if (!$selector || !isset($selectorIndex[$selector])) {
            $guess = guessSelector($css);
            if ($guess && isset($selectorIndex[$guess])) {
                $selector = $guess;
            }
        }
        if (!$selector || !isset($selectorIndex[$selector])) {
            $unmapped++;
            continue;
        }
        $scssPath = $selectorIndex[$selector];
        if (!isset($writeMap[$scssPath])) $writeMap[$scssPath] = [];
        $writeMap[$scssPath][] = $css;
    }
}

echo "Extracted $totalExtracted style strings.\n";
echo "Mapped to " . count($writeMap) . " component .scss files.\n";
echo "Unmapped (no matching component selector): $unmapped\n";

// 4. Write CSS into .scss files
function cleanCss($css) {
    // Convert Angular's encapsulation markers back to source CSS:
    //   [_nghost-...]  → :host
    //   [_ngcontent-...] → '' (just a content marker, removed)
    $css = str_replace('[_nghost-%COMP%]', ':host', $css);
    $css = str_replace('[_ngcontent-%COMP%]', '', $css);
    $css = preg_replace('/\[_nghost-[a-z0-9-]+\]/', ':host', $css);
    $css = preg_replace('/\[_ngcontent-[a-z0-9-]+\]/', '', $css);
    return $css;
}

$written = 0;
foreach ($writeMap as $scssPath => $cssList) {
    $combined = implode("\n", array_map('cleanCss', $cssList));
    @mkdir(dirname($scssPath), 0755, true);
    file_put_contents($scssPath, "/* extracted from production bundle */\n" . $combined . "\n");
    $written++;
}
echo "Wrote $written .scss files.\n";
echo "Done.\n";
