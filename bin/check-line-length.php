<?php

declare(strict_types=1);

const MAX_LINE_LENGTH = 120;

$root = dirname(__DIR__);
$baselinePath = __DIR__.'/line-length-baseline.json';
$writeBaseline = in_array('--write-baseline', $argv, true);
$rawPaths = shell_exec('git -C '.escapeshellarg($root).' ls-files --cached --others --exclude-standard -z -- "*.php" "*.blade.php"');
if ($rawPaths === null) {
    fwrite(STDERR, "Cannot list project files.\n");
    exit(2);
}

$counts = [];
$violations = [];
foreach (array_filter(explode("\0", $rawPaths)) as $path) {
    if (! str_starts_with($path, 'app/')
        && ! str_starts_with($path, 'routes/')
        && ! str_starts_with($path, 'tests/')
        && ! str_starts_with($path, 'database/')
        && ! str_starts_with($path, 'config/')
        && ! str_starts_with($path, 'resources/views/')
        && ! str_starts_with($path, 'bin/')) {
        continue;
    }

    $content = file_get_contents($root.'/'.$path);
    if ($content === false) {
        fwrite(STDERR, "Cannot read {$path}.\n");
        exit(2);
    }
    foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $index => $line) {
        $length = mb_strlen($line, 'UTF-8');
        if ($length <= MAX_LINE_LENGTH) {
            continue;
        }
        $key = $path.':'.hash('sha256', $line);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        $violations[] = ['key' => $key, 'path' => $path, 'line' => $index + 1, 'length' => $length];
    }
}

ksort($counts);
if ($writeBaseline) {
    file_put_contents($baselinePath, json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    printf("Recorded %d existing long lines.\n", count($violations));
    exit(0);
}

$baseline = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($baseline)) {
    fwrite(STDERR, "Invalid line-length baseline.\n");
    exit(2);
}

$seen = [];
$new = [];
foreach ($violations as $violation) {
    $key = $violation['key'];
    $seen[$key] = ($seen[$key] ?? 0) + 1;
    if ($seen[$key] > ($baseline[$key] ?? 0)) {
        $new[] = $violation;
    }
}

if ($new !== []) {
    foreach (array_slice($new, 0, 30) as $violation) {
        printf("%s:%d: %d characters (maximum %d)\n", $violation['path'], $violation['line'], $violation['length'], MAX_LINE_LENGTH);
    }
    printf("Line length failed: %d new violations; %d existing baseline entries.\n", count($new), array_sum($baseline));
    exit(1);
}

printf("Line length passed: maximum %d; %d existing exceptions.\n", MAX_LINE_LENGTH, count($violations));
