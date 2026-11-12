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

websiteImageWorkflowCheck(
    str_contains($manager, 'pendingUploads') &&
    str_contains($manager, 'pendingResets') &&
    str_contains($manager, 'Save Changes') &&
    str_contains($manager, 'batch-replace'),
    'website image manager must stage multiple replacements and resets until one explicit save'
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
    str_contains($api, "\$pdo->beginTransaction()") &&
    str_contains($api, "\$pdo->commit()") &&
    str_contains($api, "\$pdo->rollBack()"),
    'API must validate and persist the complete image batch transactionally'
);

websiteImageWorkflowCheck(
    preg_match('/\\.director-grid \\.public-photo\\s*\\{[^}]*overflow:\\s*hidden\\s*;/s', $polishCss) === 1 &&
    preg_match('/\\.director-grid \\.public-photo\\s*>\\s*img\\s*\\{[^}]*object-fit:\\s*cover\\s*;/s', $polishCss) === 1,
    'director portrait must fill its actual public-photo frame without stretching'
);

echo "Website image manager save workflow regression tests passed.\n";
