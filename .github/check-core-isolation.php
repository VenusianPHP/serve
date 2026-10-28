<?php

// Mariner\Core ships only in venusian/serve, so no split package may reference it.
// Usage: php .github/check-core-isolation.php   (exits 1 and lists offenders on failure)

$root = dirname(__DIR__).'/src/Mariner';
$offenders = [];

foreach (glob("$root/*", GLOB_ONLYDIR) as $package) {
    if (basename($package) === 'Core') {
        continue;
    }

    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS)),
        '/\.(php|stub|json)$/'
    );

    foreach ($files as $file) {
        foreach (file($file) as $number => $line) {
            if (preg_match('/Mariner\\\\{1,2}Core\b|venusian-mariner\/core\b/', $line)) {
                $offenders[] = substr($file, strlen($root) + 1).':'.($number + 1).': '.trim($line);
            }
        }
    }
}

if ($offenders) {
    fwrite(STDERR, "Split packages must not reference Mariner\\Core:\n  ".implode("\n  ", $offenders)."\n");
    exit(1);
}

echo "Mariner\\Core is referenced by no split package.\n";
