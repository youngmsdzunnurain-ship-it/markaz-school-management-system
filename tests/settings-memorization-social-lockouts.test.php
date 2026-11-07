<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
    return (string) file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$memorizationApi = $read('api/settings/memorization.php');
$settingsJs = $read('public/js/settings.js');
$lockoutBridge = $read('public/api/settings/login-lockouts.php');
$lockoutApi = $read('api/settings/login-lockouts.php');
$socialSettings = $read('public/js/social-settings.js');
$socialApi = $read('public/api/settings/social-links.php');
$globalJs = $read('public/js/markaz-global.js');
$home = $read('public/home.html');
$settingsCss = $read('public/css/settings.css');

$assert(!str_contains($memorizationApi, "if (\$portion === '')"), 'memorization API must accept an empty portion to clear a saved default');
$assert(!str_contains($settingsJs, 'Please enter a portion of memorization.'), 'memorization UI must allow saving a cleared field');
$assert(str_contains($settingsJs, 'memorization_portion'), 'memorization settings must continue submitting the selected class portion');
$assert(str_contains($memorizationApi, 'memorization_portion = VALUES(memorization_portion)'), 'saving a cleared portion must update the existing class default to an empty value');

$assert(str_contains($lockoutBridge, "api/settings/login-lockouts.php"), 'public locked-accounts API bridge must forward to the protected backend endpoint');
$assert(str_contains($lockoutApi, 'requireSeniorAdmin();'), 'locked-accounts backend must remain senior-admin protected');

$assert(str_contains($socialSettings, 'api/settings/social-links.php'), 'social settings form must use the public social-links endpoint');
$assert(str_contains($socialApi, 'website_social_'), 'public social-links endpoint must persist the keys consumed by the public footer');
$assert(str_contains($globalJs, 'social-links.php'), 'shared public footer loader must apply saved social links after injecting the footer');
$assert(str_contains($home, 'class="footer-socials"'), 'homepage shared footer must expose social link anchors');

$assert(str_contains($settingsCss, '.social-settings-card'), 'social links settings must have dedicated styling');
$assert(str_contains($settingsCss, '.markaz-support-settings'), 'system support and emergency contact must have dedicated styling');
$assert(str_contains($settingsCss, '.markaz-locked-accounts-card'), 'locked accounts table and actions must have dedicated styling');

echo "Settings memorization, social links, locked accounts, and contact regression checks passed.\n";
