<?php

declare(strict_types=1);

$roots = array_slice($argv, 1);
if ($roots === []) {
    fwrite(STDERR, "Usage: php scripts/lint-php.php <directory> [...]\n");
    exit(2);
}

$failures = [];
$checked = 0;

foreach ($roots as $root) {
    if (! is_dir($root)) {
        $failures[] = "Missing lint root: {$root}";
        continue;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $checked++;
        try {
            token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
        } catch (ParseError $error) {
            $failures[] = $file->getPathname().': '.$error->getMessage();
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "PHP lint passed for {$checked} files.\n");
