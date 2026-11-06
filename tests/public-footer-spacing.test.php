<?php
declare(strict_types=1);

function publicFooterSpacingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$css = file_get_contents(__DIR__ . '/../public/css/home.css');

publicFooterSpacingCheck(
    preg_match('/\.footer \.footer-main\s*\{[^}]*padding-bottom:\s*18px\s*;/s', $css) === 1,
    'desktop footer main area should use compact bottom padding'
);

publicFooterSpacingCheck(
    preg_match('/\.footer \.footer-bottom-inner\s*\{[^}]*padding-top:\s*10px\s*;[^}]*padding-bottom:\s*10px\s*;/s', $css) === 1,
    'desktop footer copyright strip should use balanced vertical padding'
);

publicFooterSpacingCheck(
    preg_match('/@media\s*\(max-width:\s*540px\)[\s\S]*?\.footer \.footer-main\s*\{[^}]*padding-bottom:\s*18px\s*;/s', $css) === 1,
    'mobile footer bottom spacing must remain compact'
);

echo "Public footer spacing regression tests passed.\n";
