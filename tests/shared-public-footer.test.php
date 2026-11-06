<?php
declare(strict_types=1);

function sharedPublicFooterCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$globalScript = file_get_contents(__DIR__ . '/../public/js/markaz-global.js');
$canonicalPage = file_get_contents(__DIR__ . '/../public/home.html');
$publicPages = [
    'home.html',
    'about.html',
    'academics.html',
    'admissions.html',
    'facilities.html',
    'gallery.html',
    'faq.html',
    'contact.html',
];

preg_match('/const publicPages = \[(.*?)\];/s', $globalScript, $matches);
sharedPublicFooterCheck(isset($matches[1]), 'global footer script must define the public-page allowlist');

foreach ($publicPages as $page) {
    sharedPublicFooterCheck(
        str_contains($matches[1], "'{$page}'"),
        "{$page} must use the shared public footer"
    );

    $html = file_get_contents(__DIR__ . '/../public/' . $page);
    sharedPublicFooterCheck(
        str_contains($html, 'js/markaz-global.js'),
        "{$page} must load the shared footer script"
    );
}

sharedPublicFooterCheck(
    preg_match('/<footer class="footer">[\s\S]*?<\/footer>/i', $canonicalPage) === 1,
    'home.html must remain the canonical source of the shared footer'
);

sharedPublicFooterCheck(
    str_contains($globalScript, 'fetch("home.html"') &&
    str_contains($globalScript, 'parsed.querySelector("footer.footer")') &&
    str_contains($globalScript, 'existing.replaceWith(footer)'),
    'every listed public page must replace its local footer with the canonical footer'
);

echo "Shared public footer regression tests passed.\n";
