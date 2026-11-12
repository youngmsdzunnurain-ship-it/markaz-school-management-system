<?php
declare(strict_types=1);

function websiteImageWorkflowCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$manager = file_get_contents(__DIR__ . '/../public/js/website-image-manager.js');
$api = file_get_contents(__DIR__ . '/../public/api/settings/website-images.php');
$polishCss = file_get_contents(__DIR__ . '/../public/css/website-image-polish.css');
$storageHelperPath = __DIR__ . '/../app/helpers/website-image-storage.php';
$storageHelper = file_get_contents($storageHelperPath);

websiteImageWorkflowCheck(
    str_contains($manager, 'pendingUploads') &&
    str_contains($manager, 'Save Changes') &&
    str_contains($manager, 'batch-replace') &&
    !str_contains($manager, 'Restore original') &&
    !str_contains($manager, 'pendingResets'),
    'manager must stage multiple replacements for one explicit save and must not show restore controls'
);

websiteImageWorkflowCheck(
    str_contains($manager, 'data.append("images[]"') &&
    str_contains($manager, 'data.append("sources[]"') &&
    str_contains($manager, 'URL.createObjectURL'),
    'selected files must preview locally and submit as a source-matched batch'
);

websiteImageWorkflowCheck(
    str_contains($api, "if (\$action === 'batch-replace')") &&
    str_contains($api, "\$_FILES['images']") &&
    str_contains($api, 'writeWebsiteImageManifest($manifestPath, $nextMappings)') &&
    str_contains($api, 'readWebsiteImageManifest($manifestPath)') &&
    str_contains($api, 'saved_images'),
    'API must validate, persist and read back the complete image batch using durable file storage'
);

websiteImageWorkflowCheck(
    str_contains($manager, 'window.addEventListener("click"') &&
    str_contains($manager, 'event.preventDefault();') &&
    str_contains($manager, 'event.stopImmediatePropagation();') &&
    str_contains($manager, 'void saveChanges();') &&
    str_contains($manager, 'saveButton.disabled = false;') &&
    str_contains($manager, 'id="saveWebsiteImagesButton" aria-disabled="true"') &&
    !str_contains($manager, 'section.addEventListener("click"'),
    'Save Changes must be intercepted at window capture before page-level handlers can swallow the tap'
);

websiteImageWorkflowCheck(
    str_contains($polishCss, '.website-image-savebar {') &&
    str_contains($polishCss, 'position: relative;') &&
    !preg_match('/\.website-image-savebar\s*\{[^}]*position:\s*sticky/s', $polishCss),
    'the save bar must remain in normal clickable document flow without sticky overlay stacking'
);

websiteImageWorkflowCheck(
    str_contains($api, 'empty($_POST) && empty($_FILES)') &&
    str_contains($api, 'exceeded the server request-size limit'),
    'oversized PHP upload requests must return a clear JSON error rather than appearing to do nothing'
);

websiteImageWorkflowCheck(
    str_contains($manager, 'saved_images') &&
    str_contains($manager, 'await loadCatalogue()') &&
    str_contains($manager, 'persisted.replacement !== saved.replacement'),
    'manager must verify the replacement survives a fresh catalogue read before reporting success'
);

websiteImageWorkflowCheck(
    preg_match('/\\.director-grid \\.public-photo\\s*\\{[^}]*overflow:\\s*hidden\\s*;/s', $polishCss) === 1 &&
    preg_match('/\\.director-grid \\.public-photo\\s*>\\s*img\\s*\\{[^}]*object-fit:\\s*cover\\s*;/s', $polishCss) === 1,
    'director portrait must fill its actual public-photo frame without stretching'
);

$storageExecutable = '';
foreach (token_get_all($storageHelper) as $token) {
    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
        continue;
    }
    $storageExecutable .= is_array($token) ? $token[1] : $token;
}
websiteImageWorkflowCheck(
    !str_contains($storageExecutable, 'LOCK_EX'),
    'manifest writes must not request unsupported exclusive locks on Android shared storage'
);

require_once $storageHelperPath;
$testDirectory = sys_get_temp_dir() . '/markaz-website-image-storage-' . bin2hex(random_bytes(6));
$testManifest = $testDirectory . '/website-image-map.json';
$testMappings = ['/assets/example.jpg' => 'assets/images/website-managed/example.jpg'];
try {
    writeWebsiteImageManifest($testManifest, $testMappings);
    websiteImageWorkflowCheck(
        readWebsiteImageManifest($testManifest) === $testMappings,
        'manifest writer must atomically save mappings that can be read back'
    );
} finally {
    if (is_file($testManifest)) {
        unlink($testManifest);
    }
    if (is_dir($testDirectory)) {
        rmdir($testDirectory);
    }
}

echo "Website image manager save workflow regression tests passed.\n";
