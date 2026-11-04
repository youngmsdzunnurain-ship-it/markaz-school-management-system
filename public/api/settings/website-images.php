<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

$publicRoot = dirname(__DIR__, 2);
$imageDirectory = $publicRoot . '/assets/images/website-managed';
$imageUrlPrefix = 'assets/images/website-managed/';
$maxBytes = 8 * 1024 * 1024;

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normaliseImageUrl(string $url, string $baseUrl): ?string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if ($url === '' || str_starts_with($url, 'data:') ||
        str_starts_with($url, 'blob:') || str_starts_with($url, '#')) {
        return null;
    }

    if (preg_match('/^(javascript:|file:)/i', $url)) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        return null;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }

    $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
    $directory = str_ends_with($basePath, '.css') || str_ends_with($basePath, '.html')
        ? dirname($basePath)
        : $basePath;

    if (str_starts_with($path, '/')) {
        $combined = $path;
    } else {
        $combined = rtrim($directory, '/') . '/' . $path;
    }

    $segments = [];
    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
        } else {
            $segments[] = $segment;
        }
    }

    return '/' . implode('/', $segments);
}

function discoverImages(string $publicRoot): array
{
    $found = [];

    $add = static function (string $raw, string $base, string $label) use (&$found): void {
        $url = normaliseImageUrl($raw, $base);
        if ($url === null) {
            return;
        }

        // Do not list font stylesheets or font files as website images.
        $parsedUrl = parse_url($url);
        $host = strtolower((string) ($parsedUrl['host'] ?? ''));
        $urlPath = strtolower((string) ($parsedUrl['path'] ?? ''));

        if (
            in_array($host, ['fonts.googleapis.com', 'fonts.gstatic.com'], true) ||
            preg_match('/\.(?:css|woff2?|ttf|eot|otf)$/i', $urlPath)
        ) {
            return;
        }

        $key = hash('sha256', $url);
        $urlPath = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $filename = strtolower(pathinfo($urlPath, PATHINFO_FILENAME));

        $knownLabels = [
            'markaz-logo' => 'School logo',
            'main-building' => 'Main school building',
            'main-building-1920x1080' => 'Main school building',
            'second-building' => 'Second school building',
            'director-portrait' => 'Director portrait',
            'gallery-young-learners' => 'Young learners gallery photo',
            'gallery-event' => 'School event gallery photo',
            'gallery-graduation' => 'Graduation gallery photo'
        ];

        if (isset($knownLabels[$filename])) {
            $cleanLabel = $knownLabels[$filename];
        } elseif (str_starts_with($filename, 'photo-')) {
            $cleanLabel = 'School / facility photograph';
        } else {
            $cleanLabel = 'Website image';
        }

        if (!isset($found[$key])) {
            $found[$key] = [
                'key' => $key,
                'url' => $url,
                'label' => $cleanLabel,
                'replacement' => null
            ];
        }
    };

    foreach (glob($publicRoot . '/*.html') ?: [] as $file) {
        if (str_contains(basename($file), '.before')) {
            continue;
        }

        $html = @file_get_contents($file);
        if (!is_string($html)) {
            continue;
        }

        $page = '/' . basename($file);

        if (preg_match_all('/<(?:img|source)\b[^>]*\b(?:src|srcset)\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                foreach (preg_split('/\s*,\s*/', $value) as $candidate) {
                    $candidate = preg_split('/\s+/', trim($candidate))[0] ?? '';
                    $add($candidate, $page, basename($file));
                }
            }
        }

        if (preg_match_all('/<link\b[^>]*\brel\s*=\s*["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\'][^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $add($value, $page, basename($file) . ' icon');
            }
        }

        if (preg_match_all('/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*\brel\s*=\s*["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\']/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $add($value, $page, basename($file) . ' icon');
            }
        }

        if (preg_match_all('/<meta\b[^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $metaTags)) {
            foreach ($metaTags[0] as $tag) {
                if (preg_match('/\b(?:og:image|twitter:image)\b/i', $tag) &&
                    preg_match('/\bcontent\s*=\s*["\']([^"\']+)["\']/i', $tag, $match)) {
                    $add($match[1], $page, basename($file) . ' social image');
                }
            }
        }

        if (preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $add($value, $page, basename($file) . ' inline background');
            }
        }
    }

    foreach (glob($publicRoot . '/css/*.css') ?: [] as $file) {
        if (str_contains(basename($file), '.before')) {
            continue;
        }

        $css = @file_get_contents($file);
        if (!is_string($css)) {
            continue;
        }

        $base = '/css/' . basename($file);

        if (preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/i', $css, $matches)) {
            foreach ($matches[1] as $value) {
                $add($value, $base, basename($file) . ' background');
            }
        }
    }

    return array_values($found);
}

try {
    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS markaz_app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $images = discoverImages($publicRoot);

        $stmt = $pdo->prepare(
            'SELECT setting_value FROM markaz_app_settings WHERE setting_key = ? LIMIT 1'
        );

        $deleteStaleReplacement = $pdo->prepare(
            'DELETE FROM markaz_app_settings WHERE setting_key = ?'
        );

        foreach ($images as &$image) {
            $settingKey = 'website_image_map_' . $image['key'];
            $stmt->execute([$settingKey]);
            $replacement = $stmt->fetchColumn();

            if ($replacement !== false) {
                $relativePath = ltrim((string) $replacement, '/');
                $filename = str_starts_with($relativePath, $imageUrlPrefix)
                    ? substr($relativePath, strlen($imageUrlPrefix))
                    : '';
                $candidate = $filename !== '' && basename($filename) === $filename
                    ? $imageDirectory . '/' . $filename
                    : '';
                $resolved = $candidate !== '' ? realpath($candidate) : false;
                $expectedDirectory = realpath($imageDirectory);

                if (
                    $resolved === false ||
                    $expectedDirectory === false ||
                    dirname($resolved) !== $expectedDirectory ||
                    !is_file($resolved) ||
                    !is_readable($resolved)
                ) {
                    // A deleted/unreadable managed upload must never blank the
                    // public page: discard its stale mapping and use the source.
                    $deleteStaleReplacement->execute([$settingKey]);
                    $replacement = false;
                }
            }

            $image['replacement'] = $replacement !== false ? $replacement : null;
        }
        unset($image);

        respond([
            'success' => true,
            'images' => $images,
            'count' => count($images)
        ]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        respond(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    requireSeniorAdmin();

    $action = (string) ($_POST['action'] ?? 'replace');
    $source = (string) ($_POST['source'] ?? '');
    $key = hash('sha256', $source);

    $catalogue = discoverImages($publicRoot);
    $allowed = false;

    foreach ($catalogue as $item) {
        if (hash_equals($item['key'], $key) && hash_equals($item['url'], $source)) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        respond(['success' => false, 'message' => 'That image is not in the active website catalogue. Refresh and try again.'], 422);
    }

    $settingKey = 'website_image_map_' . $key;

    if ($action === 'reset') {
        $stmt = $pdo->prepare('DELETE FROM markaz_app_settings WHERE setting_key = ?');
        $stmt->execute([$settingKey]);
        respond(['success' => true, 'message' => 'Original image restored.']);
    }

    if ($action !== 'replace') {
        respond(['success' => false, 'message' => 'Unknown action.'], 422);
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'message' => 'Choose an image to upload.'], 422);
    }

    $file = $_FILES['image'];

    if (($file['size'] ?? 0) > $maxBytes) {
        respond(['success' => false, 'message' => 'The image must not exceed 8 MB.'], 422);
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = $imageInfo['mime'] ?? '';

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($extensions[$mime])) {
        respond(['success' => false, 'message' => 'Use a JPG, PNG, or WebP image.'], 422);
    }

    if (!is_dir($imageDirectory) &&
        !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
        respond(['success' => false, 'message' => 'Could not create the managed image folder.'], 500);
    }

    @chmod($imageDirectory, 0755);
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = $imageDirectory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        respond(['success' => false, 'message' => 'Could not save the uploaded image.'], 500);
    }

    @chmod($destination, 0644);
    $replacementUrl = $imageUrlPrefix . $filename;

    $stmt = $pdo->prepare(
        'INSERT INTO markaz_app_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$settingKey, $replacementUrl]);

    respond([
        'success' => true,
        'message' => 'Image replacement saved.',
        'source' => $source,
        'replacement' => $replacementUrl
    ]);
} catch (Throwable $error) {
    error_log('Website image settings: ' . $error->getMessage());
    respond(['success' => false, 'message' => 'The image settings request failed. Check the PHP error log.'], 500);
}
