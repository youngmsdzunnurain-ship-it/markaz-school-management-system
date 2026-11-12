<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

$publicRoot = dirname(__DIR__, 2);
$imageDirectory = $publicRoot . '/assets/images/website-managed';
$imageUrlPrefix = 'assets/images/website-managed/';
$maxBytes = 8 * 1024 * 1024;

function respond(array $data, int $status = 200): never
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normaliseImageUrl(string $url, string $baseUrl): ?string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'blob:') || str_starts_with($url, '#')) return null;
    if (preg_match('/^(javascript:|file:)/i', $url) || str_starts_with($url, '//')) return null;
    if (preg_match('/^https?:\/\//i', $url)) return $url;

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') return null;
    $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
    $directory = str_ends_with($basePath, '.css') || str_ends_with($basePath, '.html') ? dirname($basePath) : $basePath;
    $combined = str_starts_with($path, '/') ? $path : rtrim($directory, '/') . '/' . $path;
    $segments = [];
    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') continue;
        if ($segment === '..') array_pop($segments);
        else $segments[] = $segment;
    }
    return '/' . implode('/', $segments);
}

function discoverImages(string $publicRoot): array
{
    $found = [];
    $add = static function (string $raw, string $base) use (&$found): void {
        $url = normaliseImageUrl($raw, $base);
        if ($url === null) return;
        $parsedUrl = parse_url($url);
        $host = strtolower((string) ($parsedUrl['host'] ?? ''));
        $urlPath = strtolower((string) ($parsedUrl['path'] ?? ''));
        if (in_array($host, ['fonts.googleapis.com', 'fonts.gstatic.com'], true) || preg_match('/\.(?:css|woff2?|ttf|eot|otf)$/i', $urlPath)) return;

        $key = hash('sha256', $url);
        $filename = strtolower(pathinfo((string) (parse_url($url, PHP_URL_PATH) ?? ''), PATHINFO_FILENAME));
        $labels = [
            'markaz-logo' => 'School logo', 'main-building' => 'Main school building',
            'main-building-1920x1080' => 'Main school building', 'second-building' => 'Second school building',
            'director-portrait' => 'Director portrait', 'gallery-young-learners' => 'Young learners gallery photo',
            'gallery-event' => 'School event gallery photo', 'gallery-graduation' => 'Graduation gallery photo'
        ];
        $label = $labels[$filename] ?? (str_starts_with($filename, 'photo-') ? 'School / facility photograph' : 'Website image');
        $found[$key] ??= ['key' => $key, 'url' => $url, 'label' => $label, 'replacement' => null];
    };

    foreach (glob($publicRoot . '/*.html') ?: [] as $file) {
        if (str_contains(basename($file), '.before')) continue;
        $html = @file_get_contents($file);
        if (!is_string($html)) continue;
        $page = '/' . basename($file);
        if (preg_match_all('/<(?:img|source)\b[^>]*\b(?:src|srcset)\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                foreach (preg_split('/\s*,\s*/', $value) as $candidate) {
                    $add(preg_split('/\s+/', trim($candidate))[0] ?? '', $page);
                }
            }
        }
        if (preg_match_all('/<link\b[^>]*\brel\s*=\s*["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\'][^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $value) $add($value, $page);
        }
        if (preg_match_all('/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*\brel\s*=\s*["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\']/i', $html, $matches)) {
            foreach ($matches[1] as $value) $add($value, $page);
        }
        if (preg_match_all('/<meta\b[^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $metaTags)) {
            foreach ($metaTags[0] as $tag) {
                if (preg_match('/\b(?:og:image|twitter:image)\b/i', $tag) && preg_match('/\bcontent\s*=\s*["\']([^"\']+)["\']/i', $tag, $match)) $add($match[1], $page);
            }
        }
        if (preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/i', $html, $matches)) {
            foreach ($matches[1] as $value) $add($value, $page);
        }
    }
    foreach (glob($publicRoot . '/css/*.css') ?: [] as $file) {
        if (str_contains(basename($file), '.before')) continue;
        $css = @file_get_contents($file);
        if (!is_string($css)) continue;
        if (preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/i', $css, $matches)) {
            foreach ($matches[1] as $value) $add($value, '/css/' . basename($file));
        }
    }
    return array_values($found);
}

try {
    require_once dirname(__DIR__, 3) . '/app/helpers/website-image-storage.php';

    $manifestDirectory = dirname(__DIR__, 3) . '/app/storage';
    $manifestPath = $manifestDirectory . '/website-image-map.json';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // One-time migration keeps replacements saved by the earlier database-backed
    // manager. After this manifest exists, public image requests never need MariaDB.
    $loadMappings = static function (array $catalogue) use ($manifestPath): array {
        $mappings = readWebsiteImageManifest($manifestPath);
        if (is_file($manifestPath)) {
            return $mappings;
        }

        $migrationSucceeded = false;
        try {
            $pdo = db();
            $pdo->exec("CREATE TABLE IF NOT EXISTS markaz_app_settings (
                setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $lookup = $pdo->prepare('SELECT setting_value FROM markaz_app_settings WHERE setting_key = ? LIMIT 1');
            foreach ($catalogue as $image) {
                $lookup->execute(['website_image_map_' . hash('sha256', $image['url'])]);
                $replacement = $lookup->fetchColumn();
                if (is_string($replacement) && $replacement !== '') {
                    $mappings[$image['url']] = $replacement;
                }
            }
            $migrationSucceeded = true;
        } catch (Throwable $migrationError) {
            // Do not create an empty manifest on connection failure: that would
            // prevent a later successful one-time migration of old DB mappings.
            error_log('Website image settings legacy migration deferred: ' . $migrationError->getMessage());
        }

        if ($migrationSucceeded) {
            try {
                writeWebsiteImageManifest($manifestPath, $mappings);
            } catch (Throwable $storageError) {
                error_log('Website image manifest initialisation failed: ' . $storageError->getMessage());
            }
        }
        return $mappings;
    };

    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        respond(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    // The catalogue contains school image references and is part of the
    // administrator-only Settings workspace, just like upload operations.
    requireSeniorAdmin();

    if ($method === 'GET') {
        $images = discoverImages($publicRoot);
        $mappings = $loadMappings($images);
        foreach ($images as &$image) {
            $replacement = $mappings[$image['url']] ?? null;
            if (is_string($replacement)) {
                $relativePath = ltrim($replacement, '/');
                $filename = str_starts_with($relativePath, $imageUrlPrefix)
                    ? substr($relativePath, strlen($imageUrlPrefix))
                    : '';
                $candidate = $filename !== '' && basename($filename) === $filename
                    ? $imageDirectory . '/' . $filename
                    : '';
                $resolved = $candidate !== '' ? realpath($candidate) : false;
                $expectedDirectory = realpath($imageDirectory);
                if ($resolved === false || $expectedDirectory === false ||
                    dirname($resolved) !== $expectedDirectory ||
                    !is_file($resolved) || !is_readable($resolved)) {
                    $replacement = null;
                }
            }
            $image['replacement'] = $replacement;
        }
        unset($image);
        respond(['success' => true, 'images' => $images, 'count' => count($images)]);
    }

    $catalogue = discoverImages($publicRoot);
    $allowed = [];
    foreach ($catalogue as $item) $allowed[$item['url']] = $item['url'];
    $action = (string) ($_POST['action'] ?? 'replace');
    $mappings = $loadMappings($catalogue);

    if ($action === 'batch-replace') {
        $sources = $_POST['sources'] ?? [];
        $uploads = $_FILES['images'] ?? null;
        $uploadCount = $uploads && isset($uploads['name']) && is_array($uploads['name'])
            ? count($uploads['name'])
            : 0;
        if (!is_array($sources) || count($sources) < 1 || count($sources) > 30 || count($sources) !== $uploadCount) {
            respond(['success' => false, 'message' => 'Select at least one image and choose its replacement file before saving.'], 422);
        }

        $prepared = [];
        $seen = [];
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        foreach (array_values($sources) as $index => $source) {
            if (!is_string($source) || !isset($allowed[$source]) || isset($seen[$source])) {
                respond(['success' => false, 'message' => 'An image reference is invalid or duplicated. Refresh and try again.'], 422);
            }
            $seen[$source] = true;
            $error = $uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            $tmp = $uploads['tmp_name'][$index] ?? '';
            $size = (int) ($uploads['size'][$index] ?? 0);
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                respond(['success' => false, 'message' => 'A selected image could not be received. Please choose it again.'], 422);
            }
            if ($size <= 0 || $size > $maxBytes) {
                respond(['success' => false, 'message' => 'Each image must be greater than 0 bytes and no larger than 8 MB.'], 422);
            }
            $info = @getimagesize($tmp);
            $mime = $info['mime'] ?? '';
            if (!isset($extensions[$mime])) {
                respond(['success' => false, 'message' => 'Use JPG, PNG, or WebP images only.'], 422);
            }
            $prepared[] = ['source' => $source, 'tmp' => $tmp, 'extension' => $extensions[$mime]];
        }

        if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
            respond(['success' => false, 'message' => 'Could not create the managed image folder.'], 500);
        }
        @chmod($imageDirectory, 0755);
        $createdFiles = [];
        $nextMappings = $mappings;
        try {
            foreach ($prepared as &$item) {
                $filename = bin2hex(random_bytes(16)) . '.' . $item['extension'];
                $destination = $imageDirectory . '/' . $filename;
                if (!move_uploaded_file($item['tmp'], $destination)) {
                    throw new RuntimeException('Could not save one of the selected images.');
                }
                @chmod($destination, 0644);
                if (!is_file($destination) || !is_readable($destination) || filesize($destination) <= 0) {
                    throw new RuntimeException('The uploaded image could not be verified on disk.');
                }
                $createdFiles[] = $destination;
                $item['replacement'] = $imageUrlPrefix . $filename;
                $nextMappings[$item['source']] = $item['replacement'];
            }
            unset($item);

            writeWebsiteImageManifest($manifestPath, $nextMappings);
            $verifiedMappings = readWebsiteImageManifest($manifestPath);
            $savedImages = [];
            foreach ($prepared as $item) {
                if (($verifiedMappings[$item['source']] ?? null) !== $item['replacement']) {
                    throw new RuntimeException('The saved image mapping could not be verified after writing.');
                }
                $savedImages[] = ['source' => $item['source'], 'replacement' => $item['replacement']];
            }
        } catch (Throwable $error) {
            foreach ($createdFiles as $createdFile) @unlink($createdFile);
            throw $error;
        }

        respond([
            'success' => true,
            'message' => 'Image replacements were saved and verified.',
            'saved' => count($savedImages),
            'saved_images' => $savedImages
        ]);
    }

    $source = (string) ($_POST['source'] ?? '');
    if (!isset($allowed[$source])) {
        respond(['success' => false, 'message' => 'That image is not in the active website catalogue. Refresh and try again.'], 422);
    }

    if ($action === 'reset') {
        unset($mappings[$source]);
        writeWebsiteImageManifest($manifestPath, $mappings);
        respond(['success' => true, 'message' => 'Original image restored.']);
    }

    if ($action !== 'replace') {
        respond(['success' => false, 'message' => 'Unknown action.'], 422);
    }
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'message' => 'Choose an image to upload.'], 422);
    }

    $file = $_FILES['image'];
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
        respond(['success' => false, 'message' => 'The image must be greater than 0 bytes and no larger than 8 MB.'], 422);
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = $imageInfo['mime'] ?? '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) respond(['success' => false, 'message' => 'Use a JPG, PNG, or WebP image.'], 422);
    if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
        respond(['success' => false, 'message' => 'Could not create the managed image folder.'], 500);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = $imageDirectory . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        respond(['success' => false, 'message' => 'Could not save the uploaded image.'], 500);
    }
    @chmod($destination, 0644);
    $replacementUrl = $imageUrlPrefix . $filename;
    $mappings[$source] = $replacementUrl;
    writeWebsiteImageManifest($manifestPath, $mappings);
    $verified = readWebsiteImageManifest($manifestPath);
    if (($verified[$source] ?? null) !== $replacementUrl) {
        @unlink($destination);
        throw new RuntimeException('The saved image mapping could not be verified after writing.');
    }
    respond(['success' => true, 'message' => 'Image replacement saved.', 'source' => $source, 'replacement' => $replacementUrl]);
} catch (Throwable $error) {
    error_log('Website image settings: ' . $error->getMessage());
    respond(['success' => false, 'message' => 'The image settings request failed. Check the PHP error log.'], 500);
}
