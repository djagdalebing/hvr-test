<?php
$srcRoot = __DIR__ . '/src';

function resolveImport($importer, $spec, $srcRoot) {
    if (strpos($spec, '@common/') === 0) {
        return rtrim($srcRoot, '/') . '/common/' . substr($spec, 8);
    }
    if (strpos($spec, '.') === 0) {
        return dirname($importer) . '/' . $spec;
    }
    return null;
}

function isStubFile($p) {
    if (!file_exists($p) || is_dir($p)) return false;
    $s = file_get_contents($p);
    if (strlen($s) < 200 && (strpos($s, 'stub') !== false || trim($s) === 'export {};')) return true;
    return false;
}

function exists($absPath) {
    foreach (['', '.ts', '.d.ts', '/index.ts'] as $ext) {
        $p = $absPath . $ext;
        if (file_exists($p) && !is_dir($p) && !isStubFile($p)) return true;
    }
    return false;
}

function normalize($path) {
    $parts = [];
    foreach (explode('/', $path) as $p) {
        if ($p === '..') array_pop($parts);
        elseif ($p !== '.' && $p !== '') $parts[] = $p;
    }
    return '/' . implode('/', $parts);
}

// Step 1: build map missing_target -> [imported_names...]
$needs = []; // path => [names]
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'ts') continue;
    $body = file_get_contents($f);
    // import {A, B as C, ...} from 'x'
    if (preg_match_all('#import\s+(?:type\s+)?\{([^}]+)\}\s+from\s+[\'"]([^\'"]+)[\'"]#', $body, $m)) {
        foreach ($m[2] as $i => $spec) {
            if (strpos($spec, '.') !== 0 && strpos($spec, '@common/') !== 0) continue;
            $abs = resolveImport((string)$f, $spec, $srcRoot);
            if (!$abs) continue;
            $abs = normalize($abs);
            if (exists($abs)) continue;
            $names = [];
            foreach (preg_split('#,#', $m[1][$i]) as $name) {
                $name = trim($name);
                if (preg_match('/^([A-Za-z0-9_\$]+)(?:\s+as\s+([A-Za-z0-9_\$]+))?/', $name, $nm)) {
                    $names[] = $nm[1];
                }
            }
            $key = $abs;
            if (!isset($needs[$key])) $needs[$key] = [];
            foreach ($names as $n) if (!in_array($n, $needs[$key])) $needs[$key][] = $n;
        }
    }
    // import X from 'x' (default)
    if (preg_match_all('#import\s+([A-Za-z0-9_\$]+)\s+from\s+[\'"]([^\'"]+)[\'"]#', $body, $m)) {
        foreach ($m[2] as $i => $spec) {
            if (strpos($spec, '.') !== 0 && strpos($spec, '@common/') !== 0) continue;
            $abs = resolveImport((string)$f, $spec, $srcRoot);
            if (!$abs) continue;
            $abs = normalize($abs);
            if (exists($abs)) continue;
            $key = $abs;
            if (!isset($needs[$key])) $needs[$key] = [];
            if (!in_array('default', $needs[$key])) $needs[$key][] = 'default';
        }
    }
}

// Step 2: write stub files exposing the needed names
$created = 0;
foreach ($needs as $abs => $names) {
    $target = $abs . '.ts';
    if (file_exists($target)) {
        // Skip if file is non-stub (has more than ~5 lines or doesn't start with our marker)
        $cur = file_get_contents($target);
        $isStub = (substr_count($cur, "\n") < 6) || strpos($cur, 'stub') !== false || strpos($cur, 'tree-shaken') !== false;
        if (!$isStub) continue;
    }
    @mkdir(dirname($target), 0755, true);

    $base = basename($abs);
    $isModule = strpos($base, '.module') !== false;
    $isComponent = strpos($base, '.component') !== false;
    $isService = strpos($base, '.service') !== false;
    $isDirective = strpos($base, '.directive') !== false;
    $isPipe = strpos($base, '.pipe') !== false;

    $out = "// stub — original was tree-shaken from production bundle\n";
    $needsAngular = $isModule || $isComponent || $isService || $isDirective || $isPipe;
    if ($needsAngular) {
        $out .= "import {NgModule, Component, Injectable, Directive, Pipe, PipeTransform, ChangeDetectionStrategy} from '@angular/core';\n";
    }

    foreach ($names as $name) {
        if ($name === 'default') continue;
        $upperFirst = ctype_upper($name[0] ?? 'a');
        if ($isModule && $upperFirst) {
            $out .= "@NgModule({}) export class {$name} {}\n";
        } elseif ($isComponent && $upperFirst) {
            $sel = strtolower(preg_replace('/([A-Z])/', '-$1', lcfirst($name)));
            $sel = trim($sel, '-');
            $out .= "@Component({selector:'app-stub-{$sel}',template:''}) export class {$name} {}\n";
        } elseif ($isService && $upperFirst) {
            $out .= "@Injectable({providedIn:'root'}) export class {$name} { [k: string]: any; }\n";
        } elseif ($isDirective && $upperFirst) {
            $out .= "@Directive({selector:'[appStub]'}) export class {$name} {}\n";
        } elseif ($isPipe && $upperFirst) {
            $out .= "@Pipe({name:'stub'}) export class {$name} implements PipeTransform { transform(v:any){return v;} }\n";
        } elseif ($upperFirst) {
            // Type/interface — make permissive AND generic so it works as both Foo and Foo<X>
            $out .= "export interface {$name}<T = any> { [k: string]: any; }\n";
        } else {
            // const / function
            $out .= "export const {$name}: any = undefined;\n";
        }
    }
    if (in_array('default', $names)) {
        $out .= "export default {} as any;\n";
    }
    file_put_contents($target, $out);
    $created++;
}

echo "Stubbed $created files.\n";
