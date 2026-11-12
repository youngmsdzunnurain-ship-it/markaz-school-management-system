<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/public/css/online-class.css');
$js = (string) file_get_contents($root . '/public/js/online-class.js');

function assertOnlineLevelUi(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\\n");
        exit(1);
    }
}

assertOnlineLevelUi(
    str_contains($css, '#onlineLevelSettings{display:grid') &&
    str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))') &&
    str_contains($css, '@media(max-width:720px)'),
    'Level settings must use a balanced two-column desktop layout and collapse cleanly on small screens'
);
assertOnlineLevelUi(
    str_contains($js, 'online-level-toggle') &&
    str_contains($js, 'aria-expanded') &&
    str_contains($js, 'online-level-settings-fields'),
    'Each level settings card must start collapsed and expose an accessible expand/collapse control'
);
assertOnlineLevelUi(
    str_contains($css, '.online-level-toggle') &&
    str_contains($css, 'border-radius:50%') &&
    str_contains($css, '.online-workspace-page .online-btn:focus-visible') &&
    str_contains($css, '.online-workspace-page .attendance-status:focus-visible'),
    'Circular level controls and consistent keyboard-visible focus styles must be present'
);
assertOnlineLevelUi(
    str_contains($css, '.online-workspace-page .online-actions') &&
    str_contains($css, '.online-workspace-page .online-btn.danger') &&
    str_contains($css, '.online-workspace-page .attendance-status[data-status="present"]') &&
    str_contains($css, '.online-workspace-page .attendance-status[data-status="absent"]'),
    'Attendance controls and action buttons must retain clear, consistent status styling'
);

echo "Online Class level settings and attendance UI regression checks passed (4 checks).\\n";
