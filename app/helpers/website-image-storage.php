<?php
declare(strict_types=1);

/**
 * Read website image replacements from a persistent JSON manifest.
 * Invalid or missing manifests safely behave as an empty mapping.
 */
function readWebsiteImageManifest(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if (!is_string($contents) || $contents === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        return [];
    }

    $mappings = [];
    foreach ($decoded as $source => $replacement) {
        if (is_string($source) && $source !== '' &&
            is_string($replacement) && $replacement !== '') {
            $mappings[$source] = $replacement;
        }
    }

    return $mappings;
}

/**
 * Atomically replace the full website image mapping manifest.
 * The unique temporary file is created beside the target so rename remains
 * atomic. Do not request LOCK_EX: Android shared storage (the app's normal
 * Termux project location) does not implement flock for PHP streams.
 */
function writeWebsiteImageManifest(string $path, array $mappings): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the website image settings directory.');
    }

    $json = json_encode($mappings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Could not encode website image settings.');
    }

    $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    try {
        $written = file_put_contents($temporary, $json);
        if ($written !== strlen($json)) {
            throw new RuntimeException('Could not write website image settings.');
        }
        @chmod($temporary, 0640);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Could not publish website image settings.');
        }
        @chmod($path, 0640);
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}
