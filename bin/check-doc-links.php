<?php

declare(strict_types=1);

/**
 * Checks docs/ and .claude/ markdown files for:
 *   - broken relative links (file does not exist)
 *   - HIST-XXX references without a matching docs/history/HIST-XXX.md
 *
 * Code blocks (fenced and inline) are excluded from both checks.
 */
$projectRoot = dirname(__DIR__);
$errors = [];

$markdownFiles = [];
foreach (['/docs', '/.claude'] as $dir) {
    $absDir = $projectRoot.$dir;
    if (! is_dir($absDir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() === 'md'
            && ! str_contains($file->getPathname(), '/.claude/worktrees/')
        ) {
            $markdownFiles[] = $file->getPathname();
        }
    }
}

foreach ($markdownFiles as $filePath) {
    $raw = file_get_contents($filePath);
    if ($raw === false) {
        continue;
    }
    $relFile = str_replace($projectRoot.'/', '', $filePath);
    $fileDir = dirname($filePath);

    // Strip fenced code blocks (``` ... ```) before analysis
    $content = preg_replace('/```.*?```/s', '', $raw) ?? $raw;
    // Strip inline code (`...`)
    $content = preg_replace('/`[^`]+`/', '', $content) ?? $content;

    // Relative markdown links: [text](path) or [text](path#anchor)
    preg_match_all('/\[([^\]]+)]\(([^)]+)\)/', $content, $m);
    foreach ($m[2] as $link) {
        if (str_starts_with($link, 'http') || str_starts_with($link, '#') || str_starts_with($link, 'mailto:')) {
            continue;
        }
        $path = explode('#', $link, 2)[0];
        if ($path === '') {
            continue;
        }
        $resolved = str_starts_with($path, '/')
            ? $projectRoot.$path
            : realpath($fileDir.'/'.$path);
        if ($resolved === false || ! file_exists($resolved)) {
            $errors[] = "Broken link in {$relFile}: {$link}";
        }
    }

    // HIST-XXX inline references (outside code blocks)
    preg_match_all('/HIST-(\d+)/', $content, $m);
    foreach ($m[1] as $num) {
        $padded = str_pad($num, 3, '0', STR_PAD_LEFT);
        $histFile = $projectRoot.'/docs/history/HIST-'.$padded.'.md';
        if (! file_exists($histFile)) {
            $errors[] = "HIST-{$num} referenced in {$relFile} but docs/history/HIST-{$padded}.md not found";
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        echo 'ERROR: '.$error.PHP_EOL;
    }
    exit(1);
}

echo 'docs:check passed — no broken links.'.PHP_EOL;
exit(0);
