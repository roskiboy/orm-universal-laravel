<?php

declare(strict_types=1);

/**
 * Local release helper. It never commits or pushes: the GitHub workflow does that.
 *
 * Modes:
 *   php tools/release.php --dry-run
 *   php tools/release.php --write --version=0.2.0
 *   php tools/release.php --notes --version=0.2.0
 *
 * The CHANGELOG follows Keep a Changelog: the [Unreleased] body is promoted to
 * [version] - date and the Unreleased section is left empty.
 */
$options = getopt('', ['write', 'dry-run', 'notes', 'version:', 'changelog::', 'date::']);

$changelog = $options['changelog'] ?? __DIR__.'/../CHANGELOG.md';
$date = $options['date'] ?? date('Y-m-d');
$version = $options['version'] ?? null;

if (! is_file($changelog)) {
    fwrite(STDERR, "CHANGELOG not found: {$changelog}\n");
    exit(1);
}

$content = (string) file_get_contents($changelog);
$pattern = '/^## \[Unreleased\]\s*\n(.*?)(?=^## \[|\z)/ms';

if (preg_match($pattern, $content, $matches) !== 1) {
    fwrite(STDERR, "Missing [Unreleased] section.\n");
    exit(1);
}

$body = trim($matches[1]);

if (isset($options['dry-run']) || (! isset($options['write']) && ! isset($options['notes']))) {
    echo 'Unreleased body length: '.strlen($body)." bytes\n";
    echo $body === '' ? "(empty: no release needed)\n" : $body."\n";
    exit(0);
}

if ($version === null) {
    fwrite(STDERR, "--version is required.\n");
    exit(1);
}

if (isset($options['notes'])) {
    if ($body === '') {
        $sectionPattern = '/^## \['.preg_quote($version, '/').'\][^\n]*\n(.*?)(?=^## \[|\z)/ms';

        if (preg_match($sectionPattern, $content, $sectionMatches) === 1) {
            $body = trim($sectionMatches[1]);
        }
    }

    if ($body === '') {
        fwrite(STDERR, "Nothing to release: no entries for [Unreleased] or [{$version}].\n");
        exit(2);
    }

    echo $body."\n";
    exit(0);
}

if ($body === '') {
    fwrite(STDERR, "Nothing to release: [Unreleased] is empty.\n");
    exit(2);
}

$section = "## [{$version}] - {$date}\n\n{$body}\n\n";
$updated = (string) preg_replace($pattern, "## [Unreleased]\n\n".$section, $content, 1);

if ($updated === $content) {
    fwrite(STDERR, "CHANGELOG was not modified.\n");
    exit(1);
}

file_put_contents($changelog, $updated);
echo "Promoted [Unreleased] to [{$version}] - {$date}.\n";
exit(0);
